<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/analyse.php
   Main scoring endpoint.
   POST { url: "https://store.com" }
   Returns { scores, pillar_scores, owleye_score, scan_token? }
   ═══════════════════════════════════════════════════════════════ */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://genaitechlabs.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

set_time_limit(60);
ini_set('max_execution_time', 60);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/screenshot/ScreenshotAdapter.php';
require_once __DIR__ . '/ai/AiAdapter.php';

// ── 1. Validate URL ──────────────────────────────────────────────
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
$url   = isset($input['url']) ? filter_var(trim($input['url']), FILTER_SANITIZE_URL) : '';

if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid http/https URL is required']);
    exit;
}

// ── 2. Rate limit check ──────────────────────────────────────────
$ip = getClientIp();

if (defined('RATE_LIMIT_PER_HOUR') && RATE_LIMIT_PER_HOUR > 0 && defined('DB_NAME') && DB_NAME) {
    try {
        $stmt = getDB()->prepare(
            'SELECT COUNT(*) FROM owleye_scans WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR'
        );
        $stmt->execute([$ip]);
        $scanCount = (int) $stmt->fetchColumn();

        if ($scanCount >= RATE_LIMIT_PER_HOUR) {
            http_response_code(429);
            echo json_encode([
                'error' => 'Rate limit reached. Maximum ' . RATE_LIMIT_PER_HOUR . ' scans per hour. Please try again later.',
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log('[OwlEye] Rate limit check failed: ' . $e->getMessage());
        // DB unavailable — don't block the scan, just log
    }
}

// ── 3. Fetch page HTML ───────────────────────────────────────────
$html = fetchPageHtml($url);

// ── 4. Screenshots — null if no key configured yet ───────────────
$desktop = ScreenshotAdapter::capture($url, 'desktop');
$mobile  = ScreenshotAdapter::capture($url, 'mobile');

// ── 5. AI analysis ───────────────────────────────────────────────
$result = AiAdapter::analyse($url, $html, $desktop, $mobile);

// ── 6. PageSpeed Insights — overrides AI estimate for page_speed ─
if (defined('GOOGLE_PSI_KEY') && GOOGLE_PSI_KEY) {
    require_once __DIR__ . '/pagespeed/PageSpeedAdapter.php';
    $psiScore = PageSpeedAdapter::score($url);
    if ($psiScore !== null && isset($result['scores'])) {
        $result['scores']['page_speed'] = $psiScore;
    }
}

// ── 7. Compute pillar scores + OwlEye total (PHP mirror of owleye-ai.js) ─
if (isset($result['scores'])) {
    $computed = computeOwleyeScores($result['scores']);
    $result['owleye_score']  = $computed['owleye_score'];
    $result['pillar_scores'] = $computed['pillar_scores'];
}

// ── 8. Persist to database ───────────────────────────────────────
if (isset($result['scores']) && defined('DB_NAME') && DB_NAME) {
    try {
        $token = generateScanToken();
        $stmt  = getDB()->prepare(
            'INSERT INTO owleye_scans
             (scan_token, url, ip, owleye_score, pillar_scores, param_scores)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $token,
            $url,
            $ip,
            $result['owleye_score'] ?? 0,
            json_encode($result['pillar_scores'] ?? []),
            json_encode($result['scores']),
        ]);
        $result['scan_token'] = $token;
    } catch (Exception $e) {
        error_log('[OwlEye] Scan save failed: ' . $e->getMessage());
        // Don't fail the request — scan result still returned
    }
}

echo json_encode($result);


// ════════════════════════════════════════════════════════════════
// Helper functions
// ════════════════════════════════════════════════════════════════

/**
 * Fetch and clean page HTML for AI analysis.
 */
function fetchPageHtml(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; OwlEye-Scanner/1.0; +https://genaitechlabs.com/cro)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept-Language: en-US,en;q=0.9'],
    ]);
    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) return '';

    // Strip noise — keep meaningful text + structure
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is',   '', $html);
    $html = preg_replace('/<!--.*?-->/s',                     '', $html);
    $html = preg_replace('/\s{2,}/',                          ' ', $html);

    // Truncate to 8 000 chars — enough context, manageable token count
    return mb_substr(trim($html), 0, 8000);
}

/**
 * PHP mirror of owleye-ai.js getPillarScores() + calcOwleyeTotal().
 * Weights must stay in sync with OWLEYE_PILLARS in owleye-ai.js.
 *
 * Returns ['pillar_scores' => [p1,…,p6], 'owleye_score' => int]
 */
function computeOwleyeScores(array $scores): array
{
    // [pillar_weight, [[param_key, param_weight], ...]]
    static $PILLARS = [
        [28, [['checkout_flow',1.0],['payment_options',1.0],['cart_recovery',0.6],
               ['express_checkout',0.6],['cod_prominence',1.0]]],
        [22, [['landing_page',1.0],['product_pages',1.0],['search_ux',0.6],
               ['sticky_atc',0.6],['category_pages',0.6]]],
        [18, [['trust_signals',1.0],['returns_policy',1.0],['social_proof',1.0],
               ['review_quality',0.6],['guarantee_signals',0.6]]],
        [12, [['cross_sell',0.6],['email_capture',1.0],['whatsapp_marketing',1.0]]],
        [10, [['schema_markup',1.0],['content_clarity',1.0],['ai_discoverability',1.0],
               ['conversational_ux',0.6],['open_graph_quality',0.6],['canonical_health',0.6]]],
        [10, [['mobile_ux',1.0],['page_speed',1.0],['navigation_clarity',0.6],['accessibility',0.6]]],
    ];

    $pillarScores   = [];
    $totalWeightedSum = 0;
    $totalWeight      = 0;

    foreach ($PILLARS as [$pillarWeight, $params]) {
        $wSum = $wTotal = 0.0;
        foreach ($params as [$key, $w]) {
            $wSum   += ($scores[$key] ?? 50) * $w;
            $wTotal += $w;
        }
        $ps = (int) round($wSum / $wTotal);
        $pillarScores[]    = $ps;
        $totalWeightedSum += $ps * $pillarWeight;
        $totalWeight      += $pillarWeight;
    }

    return [
        'pillar_scores' => $pillarScores,
        'owleye_score'  => (int) round($totalWeightedSum / $totalWeight),
    ];
}

/**
 * Generate a UUID v4 scan token.
 */
function generateScanToken(): string
{
    $b    = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0f | 0x40); // version 4
    $b[8] = chr(ord($b[8]) & 0x3f | 0x80); // variant bits
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

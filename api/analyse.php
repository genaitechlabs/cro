<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/analyse.php
   Main scoring endpoint.
   POST { url: "https://store.com" }
   Returns { scores: { checkout_flow: 72, ... } }
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

// ── 2. Fetch page HTML ───────────────────────────────────────────
$html = fetchPageHtml($url);

// ── 3. Screenshots — null if no key configured yet ───────────────
$desktop = ScreenshotAdapter::capture($url, 'desktop');
$mobile  = ScreenshotAdapter::capture($url, 'mobile');

// ── 4. AI analysis ───────────────────────────────────────────────
$result = AiAdapter::analyse($url, $html, $desktop, $mobile);

// ── 5. PageSpeed Insights — overrides AI estimate for page_speed ──
// Runs only when GOOGLE_PSI_KEY is configured. Graceful skip otherwise.
if (defined('GOOGLE_PSI_KEY') && GOOGLE_PSI_KEY) {
    require_once __DIR__ . '/pagespeed/PageSpeedAdapter.php';
    $psiScore = PageSpeedAdapter::score($url);
    if ($psiScore !== null && isset($result['scores'])) {
        $result['scores']['page_speed'] = $psiScore;
    }
}

echo json_encode($result);


// ── Helper: fetch + clean HTML ───────────────────────────────────
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

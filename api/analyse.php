<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/analyse.php
   Main scoring endpoint.
   POST { url: "https://store.com" }
   Returns { scores, pillar_scores, owleye_score, verified_count,
             unverified_params, pages_scanned, scan_token? }
   ═══════════════════════════════════════════════════════════════ */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://genaitechlabs.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

set_time_limit(90);
ini_set('max_execution_time', 90);

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

$urlNorm = normalizeUrl($url);

// ── 2. Rate limit check ──────────────────────────────────────────
$ip = getClientIp();

if (defined('RATE_LIMIT_PER_HOUR') && RATE_LIMIT_PER_HOUR > 0 && defined('DB_NAME') && DB_NAME) {
    try {
        $stmt = getDB()->prepare(
            'SELECT COUNT(*) FROM owleye_scans WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR'
        );
        $stmt->execute([$ip]);
        if ((int) $stmt->fetchColumn() >= RATE_LIMIT_PER_HOUR) {
            http_response_code(429);
            echo json_encode([
                'error' => 'Rate limit reached. Maximum ' . RATE_LIMIT_PER_HOUR . ' scans per hour. Please try again later.',
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log('[OwlEye] Rate limit check failed: ' . $e->getMessage());
    }
}

// ── 3. Multi-page crawl ──────────────────────────────────────────
// Fetches home + discovers and fetches product, category, cart pages
$pages = discoverAndFetchPages($url);

// ── 3b. Ecommerce store check ─────────────────────────────────────
if (!isEcommerceStore($pages)) {
    $host = parse_url($url, PHP_URL_HOST) ?? $url;
    http_response_code(400);
    echo json_encode([
        'error' => "The OwlEye Scan could not determine {$host} as an online store. OwlEye Score™ is designed for online shops with products and a checkout. In case this is a genuine miss, book an audit call and we'll review it manually.",
    ]);
    exit;
}

// ── 4. Screenshots (homepage only) ───────────────────────────────
$desktop = ScreenshotAdapter::capture($url, 'desktop');
$mobile  = ScreenshotAdapter::capture($url, 'mobile');

// ── 5. AI analysis ───────────────────────────────────────────────
$result = AiAdapter::analyse($url, $pages, $desktop, $mobile);

// ── 6. PageSpeed Insights — overrides AI estimate for page_speed ─
$psiConfigured = defined('GOOGLE_PSI_KEY') && GOOGLE_PSI_KEY;
if ($psiConfigured) {
    require_once __DIR__ . '/pagespeed/PageSpeedAdapter.php';
    $psiScore = PageSpeedAdapter::score($url);
    if ($psiScore !== null && isset($result['scores'])) {
        $result['scores']['page_speed'] = $psiScore;
    }
}

// ── 7. Compute pillar scores + OwlEye total ──────────────────────
if (isset($result['scores'])) {
    $computed = computeOwleyeScores($result['scores']);
    $result['owleye_score']  = $computed['owleye_score'];
    $result['pillar_scores'] = $computed['pillar_scores'];
}

// ── 8. Compute verified / unverified params ───────────────────────
$verification = computeVerification($pages, $psiConfigured);
$result['verified_count']    = $verification['verified_count'];
$result['unverified_params'] = $verification['unverified_params'];
$result['pages_scanned']     = $verification['pages_scanned'];

// ── 9. Persist to database ───────────────────────────────────────
if (isset($result['scores']) && defined('DB_NAME') && DB_NAME) {
    try {
        $token = generateScanToken();
        $stmt  = getDB()->prepare(
            'INSERT INTO owleye_scans
             (scan_token, url, url_normalized, ip, owleye_score, pillar_scores, param_scores)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $token,
            $url,
            $urlNorm,
            $ip,
            $result['owleye_score'] ?? 0,
            json_encode($result['pillar_scores'] ?? []),
            json_encode($result['scores']),
        ]);
        $result['scan_token'] = $token;

        // Previous score — most recent other scan of the same host
        $prev = getDB()->prepare(
            'SELECT owleye_score FROM owleye_scans
             WHERE url_normalized = ? AND scan_token != ?
             ORDER BY created_at DESC LIMIT 1'
        );
        $prev->execute([$urlNorm, $token]);
        $prevScore = $prev->fetchColumn();
        if ($prevScore !== false) {
            $result['previous_score'] = (int) $prevScore;
        }
    } catch (Exception $e) {
        error_log('[OwlEye] Scan save failed: ' . $e->getMessage());
    }
}

echo json_encode($result);


// ════════════════════════════════════════════════════════════════
// Multi-page crawl helpers
// ════════════════════════════════════════════════════════════════

/**
 * Detect whether the fetched pages belong to an ecommerce store.
 * Returns false for portfolios, blogs, SaaS landing pages, etc.
 */
function isEcommerceStore(array $pages): bool
{
    $html = strtolower(
        ($pages['home']     ?? '') .
        ($pages['product']  ?? '') .
        ($pages['category'] ?? '') .
        ($pages['cart']     ?? '')
    );

    // Platform signals — definitive
    foreach (['shopify', 'woocommerce', 'magento', 'opencart', 'prestashop', 'bigcommerce'] as $p) {
        if (strpos($html, $p) !== false) return true;
    }

    // Cart / checkout actions — very strong signals
    $cartSignals = ['add to cart', 'add-to-cart', 'addtocart', '/cart', '/checkout',
                    'buy now', 'add to bag', '/basket', 'proceed to checkout'];
    foreach ($cartSignals as $s) {
        if (strpos($html, $s) !== false) return true;
    }

    // Product / price signals — require 2+ to reduce false positives
    $count = 0;
    foreach (['₹', 'mrp', '/products/', '/collections/', 'product-page',
              'free shipping', 'cash on delivery', ' cod ', 'add to wishlist',
              'out of stock', 'in stock', 'buy', 'shop now'] as $s) {
        if (strpos($html, $s) !== false && ++$count >= 2) return true;
    }

    return false;
}

/**
 * Orchestrate: fetch home → detect platform → discover page URLs → parallel fetch.
 * Returns ['home' => html, 'product' => html, 'category' => html, 'cart' => html, 'returns' => html]
 */
function discoverAndFetchPages(string $url): array
{
    $base = preg_replace('/^(https?:\/\/[^\/]+).*$/i', '$1', $url);
    $base = rtrim($base, '/');

    // Always fetch homepage first
    $homeHtml = fetchSinglePage($url);
    if (!$homeHtml) return [];

    $pages = ['home' => cleanHtml($homeHtml, 'home')];

    // Detect platform
    $platform = detectPlatform($homeHtml);

    // Discover page URLs
    $urlsToFetch = discoverPageUrls($base, $homeHtml, $platform);
    if (empty($urlsToFetch)) return $pages;

    // Fetch discovered pages in parallel
    $fetched = fetchPagesParallel($urlsToFetch);
    foreach ($fetched as $type => $html) {
        $pages[$type] = cleanHtml($html, $type);
    }

    return $pages;
}

/**
 * Detect ecommerce platform from homepage HTML signals.
 */
function detectPlatform(string $html): string
{
    if (strpos($html, 'cdn.shopify.com') !== false
     || strpos($html, 'myshopify.com')  !== false
     || strpos($html, 'Shopify.theme')   !== false) {
        return 'shopify';
    }
    if (strpos($html, 'woocommerce')            !== false
     || strpos($html, 'wp-content/plugins')     !== false) {
        return 'woocommerce';
    }
    return 'generic';
}

/**
 * Discover product, category, and cart URLs based on platform.
 * Shopify: uses /products.json and /collections.json (no auth required).
 * WooCommerce/generic: parses homepage links.
 */
function discoverPageUrls(string $base, string $html, string $platform): array
{
    $urls = [];

    if ($platform === 'shopify') {
        // Shopify JSON endpoints — unauthenticated, always available
        $pJson = fetchSinglePage($base . '/products.json?limit=1');
        $p     = json_decode($pJson, true);
        if (!empty($p['products'][0]['handle'])) {
            $urls['product'] = $base . '/products/' . $p['products'][0]['handle'];
        }

        $cJson = fetchSinglePage($base . '/collections.json?limit=1');
        $c     = json_decode($cJson, true);
        if (!empty($c['collections'][0]['handle'])) {
            $urls['category'] = $base . '/collections/' . $c['collections'][0]['handle'];
        }

        $urls['cart']    = $base . '/cart';
        $urls['returns'] = $base . '/policies/refund-policy'; // always present on Shopify

    } elseif ($platform === 'woocommerce') {
        // Try standard WooCommerce paths
        $urls['category'] = $base . '/shop';
        $urls['cart']     = $base . '/cart';

        // Find product link from homepage
        $host = parse_url($base, PHP_URL_HOST);
        preg_match_all('/href=["\']([^"\'#?]*\/product\/[^"\'?#]+)["\']/', $html, $m);
        $candidates = array_filter($m[1] ?? [], fn($l) => strpos($l, $host) !== false || str_starts_with($l, '/'));
        if (!empty($candidates)) {
            $link = reset($candidates);
            $urls['product'] = str_starts_with($link, 'http') ? $link : $base . $link;
        }

    } else {
        // Generic: score all homepage links by pattern
        preg_match_all('/href=["\']([^"\'#?]{5,})["\']/', $html, $m);
        $host  = parse_url($base, PHP_URL_HOST);
        $links = array_unique($m[1] ?? []);

        $productPats  = ['/\/(products?|item|p|pd)\/[^\/]{3,}/i'];
        $categoryPats = ['/\/(collections?|categor|shop|browse|c)\/[^\/]{2,}/i', '/\/shop\/?$/i'];
        $cartPats     = ['/\/(cart|checkout|bag)\/?$/i'];

        foreach ($links as $link) {
            // Normalise to absolute
            if (!preg_match('/^https?:\/\//i', $link)) {
                $link = $base . '/' . ltrim($link, '/');
            }
            if (strpos($link, $host) === false) continue;

            if (!isset($urls['product'])) {
                foreach ($productPats as $pat) {
                    if (preg_match($pat, $link)) { $urls['product'] = $link; break; }
                }
            }
            if (!isset($urls['category'])) {
                foreach ($categoryPats as $pat) {
                    if (preg_match($pat, $link)) { $urls['category'] = $link; break; }
                }
            }
            if (!isset($urls['cart'])) {
                foreach ($cartPats as $pat) {
                    if (preg_match($pat, $link)) { $urls['cart'] = $link; break; }
                }
            }
        }
    }

    // Returns / refund policy — scan homepage links first, then platform defaults
    if (!isset($urls['returns'])) {
        $host = parse_url($base, PHP_URL_HOST);
        preg_match_all(
            '/href=["\']([^"\'#?]*(?:refund|return|cancell|polic)[^"\'?#]*)["\']/',
            $html, $rm
        );
        foreach (($rm[1] ?? []) as $rlink) {
            if (!preg_match('/^https?:\/\//i', $rlink)) {
                $rlink = $base . '/' . ltrim($rlink, '/');
            }
            if (strpos($rlink, $host) !== false) {
                $urls['returns'] = $rlink;
                break;
            }
        }
        if (!isset($urls['returns'])) {
            $urls['returns'] = match ($platform) {
                'woocommerce' => $base . '/refund_policy',
                default       => $base . '/refund-policy',
            };
        }
    }

    return $urls;
}

/**
 * Fetch multiple URLs in parallel using cURL multi-handle.
 * Returns ['type' => 'raw html'] — only entries where response > 500 bytes.
 */
function fetchPagesParallel(array $urls): array
{
    $mh      = curl_multi_init();
    $handles = [];

    foreach ($urls as $type => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; OwlEye-Scanner/1.0; +https://genaitechlabs.com)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept-Language: en-US,en;q=0.9'],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$type] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.1);
    } while ($running > 0);

    $results = [];
    foreach ($handles as $type => $ch) {
        $html = curl_multi_getcontent($ch);
        if ($html && strlen($html) > 500) {
            $results[$type] = $html;
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    return $results;
}

/**
 * Fetch a single URL synchronously (used for platform/URL discovery).
 */
function fetchSinglePage(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; OwlEye-Scanner/1.0; +https://genaitechlabs.com)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept-Language: en-US,en;q=0.9'],
    ]);
    $html    = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log('[OwlEye] Fetch error for ' . $url . ': ' . $curlErr);
        return '';
    }
    return $html ?: '';
}

/**
 * Strip scripts/styles/comments and truncate to per-type char limit.
 */
function cleanHtml(string $html, string $type): string
{
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is',   '', $html);
    $html = preg_replace('/<!--.*?-->/s',                     '', $html);
    $html = preg_replace('/\s{2,}/',                          ' ', $html);

    $limits = ['home' => 12000, 'product' => 8000, 'category' => 6000, 'cart' => 6000, 'returns' => 6000];
    return mb_substr(trim($html), 0, $limits[$type] ?? 6000);
}


// ════════════════════════════════════════════════════════════════
// Scoring helpers
// ════════════════════════════════════════════════════════════════

/**
 * Which page type is required to verify each parameter.
 * null  = always unverifiable (behaviour/session dependent)
 * 'psi' = verified via Google PSI API
 */
const PARAM_PAGE_REQUIREMENT = [
    'checkout_flow'      => 'cart',
    'payment_options'    => 'cart',
    'cart_recovery'      => null,      // session/behaviour — always unverifiable
    'express_checkout'   => 'cart',
    'cod_prominence'     => 'cart',
    'landing_page'       => 'home',
    'product_pages'      => 'product',
    'search_ux'          => 'category',
    'sticky_atc'         => 'product',
    'category_pages'     => 'category',
    'trust_signals'      => 'home',
    'returns_policy'     => 'returns',
    'social_proof'       => 'product',
    'review_quality'     => 'product',
    'guarantee_signals'  => 'product',
    'cross_sell'         => 'product',
    'email_capture'      => 'home',
    'whatsapp_marketing' => 'home',
    'schema_markup'      => 'home',
    'content_clarity'    => 'home',
    'ai_discoverability' => 'home',
    'conversational_ux'  => 'home',
    'open_graph_quality' => 'home',
    'canonical_health'   => 'home',
    'mobile_ux'          => 'home',
    'page_speed'         => 'psi',
    'navigation_clarity' => 'home',
    'accessibility'      => 'home',
];

/**
 * Compute which params are verified vs unverified based on pages fetched.
 */
function computeVerification(array $pages, bool $psiConfigured): array
{
    $fetched    = array_keys($pages);
    $verified   = [];
    $unverified = [];

    foreach (PARAM_PAGE_REQUIREMENT as $param => $req) {
        $ok = match (true) {
            $req === null            => false,
            $req === 'psi'           => $psiConfigured,
            in_array($req, $fetched) => true,
            default                  => false,
        };
        if ($ok) $verified[] = $param;
        else     $unverified[] = $param;
    }

    return [
        'verified_params'   => $verified,
        'unverified_params' => $unverified,
        'verified_count'    => count($verified),
        'pages_scanned'     => count($pages),
    ];
}

/**
 * PHP mirror of owleye-ai.js getPillarScores() + calcOwleyeTotal().
 * Weights must stay in sync with OWLEYE_PILLARS in owleye-ai.js.
 */
function computeOwleyeScores(array $scores): array
{
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

    $pillarScores     = [];
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
 * Strip protocol + www + path → bare host for cross-URL deduplication.
 */
function normalizeUrl(string $url): string
{
    $host = parse_url(strtolower(trim($url)), PHP_URL_HOST) ?? '';
    return preg_replace('/^www\./i', '', $host) ?: strtolower(trim($url));
}

/**
 * Generate a UUID v4 scan token.
 */
function generateScanToken(): string
{
    $b    = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
    $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

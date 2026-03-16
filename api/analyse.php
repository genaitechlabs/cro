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

// Catch fatal errors and return them as JSON instead of a blank 500
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['error' => 'PHP fatal: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']]);
    }
});

set_time_limit(90);
ini_set('max_execution_time', 90);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/screenshot/ScreenshotAdapter.php';
require_once __DIR__ . '/ai/AiAdapter.php';
require_once __DIR__ . '/renderer/RendererAdapter.php';

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
// Store homepage base — strips leaf paths (products, collections…) to domain root,
// but preserves shop-subdirectory roots like /shop or /store, and always keeps subdomains.
$urlBase = extractStoreBase($url);

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

// ── 3b. Check if target site was unreachable ─────────────────────
if (!empty($pages['_unreachable'])) {
    $host = parse_url($url, PHP_URL_HOST) ?? $url;
    http_response_code(400);
    echo json_encode([
        'error' => "The target site ({$host}) is not reachable. OwlEye scan can't perform the evaluation at this time. Please retry.",
    ]);
    exit;
}

// ── 3c. Extract internal flags before passing pages to AI ────────
$jsRendered       = !empty($pages['_js_rendered']);
$renderedFallback = !empty($pages['_rendered_fallback']);
unset($pages['_js_rendered'], $pages['_rendered_fallback']);

// ── 3c.5. Detect non-English (Hindi-primary) store ───────────────
// If homepage contains Hindi ecommerce text, flag for UI disclaimer
$_homeRaw    = $pages['home'] ?? '';
$isNonEnglish = false;
foreach (['खरीदें', 'कार्ट', 'अभी खरीदें', 'कार्ट में', 'मुफ्त', 'शिपिंग', 'रुपये'] as $_hs) {
    if (strpos($_homeRaw, $_hs) !== false) { $isNonEnglish = true; break; }
}
unset($_homeRaw, $_hs);

// ── 3d. Ecommerce store check ─────────────────────────────────────
if (!isEcommerceStore($pages)) {
    $host = parse_url($url, PHP_URL_HOST) ?? $url;
    http_response_code(400);
    echo json_encode([
        'error' => "The OwlEye Scan could not determine {$host} as an online store. OwlEye Score™ is designed for online shops with products and a checkout. In case this is a genuine miss, book an audit call and we'll review it manually.",
    ]);
    exit;
}

// ── 3e. Purchase flow signal detection ────────────────────────────
// Detects payment methods, CRM tools, COD, express checkout, email capture
// signals from all crawled pages — injected into home page for AI scoring
$purchaseHint = detectPurchaseFlowSignals($pages);
if ($purchaseHint) {
    $pages['home'] = ($pages['home'] ?? '') . $purchaseHint;
}

// ── 3f. Trust signal detection ────────────────────────────────────
// Detects press/media coverage, testimonial sections, and trust counts
// Injected into home page for AI scoring of trust_signals + social_proof
$trustHint = detectTrustSignals($pages);
if ($trustHint) {
    $pages['home'] = ($pages['home'] ?? '') . $trustHint;
}

// ── 4. Screenshots (homepage only) ───────────────────────────────
// Always screenshot the homepage (base URL) — not a product/category URL the user may have pasted.
$desktop = ScreenshotAdapter::capture($urlBase, 'desktop');
$mobile  = ScreenshotAdapter::capture($urlBase, 'mobile');

// ── 5. AI analysis ───────────────────────────────────────────────
// Pass the base URL so the AI always refers to the store root, not a deep path.
$result = AiAdapter::analyse($urlBase, $pages, $desktop, $mobile);

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
        // Use JSON_UNESCAPED_UNICODE + JSON_INVALID_UTF8_SUBSTITUTE so Hindi/multilingual
        // content from crawled pages never causes json_encode() to return false and
        // silently break the INSERT (which would leave scan_token missing from the response).
        $flags      = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
        $pillarJson = json_encode($result['pillar_scores'] ?? [], $flags);
        $scoresJson = json_encode($result['scores'],              $flags);

        if ($pillarJson === false || $scoresJson === false) {
            // json_encode failure — log with detail so it's diagnosable
            error_log('[OwlEye] DB json_encode failed for ' . $url
                . ' — last_error: ' . json_last_error_msg());
        } else {
            $stmt->execute([
                $token,
                $url,
                $urlNorm,
                $ip,
                $result['owleye_score'] ?? 0,
                $pillarJson,
                $scoresJson,
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
        }
    } catch (Exception $e) {
        error_log('[OwlEye] Scan save failed for ' . ($urlNorm ?? '?')
            . ' score=' . ($result['owleye_score'] ?? '?')
            . ' code=' . $e->getCode()
            . ' — ' . $e->getMessage());
    }
}

$result['js_rendered']        = $jsRendered;
$result['rendered_fallback']  = $renderedFallback;
$result['is_non_english']     = $isNonEnglish;
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
    $rawHtml = ($pages['home'] ?? '') . ($pages['product'] ?? '') .
               ($pages['category'] ?? '') . ($pages['cart'] ?? '');

    // JSON-LD transactional schema — definitive regardless of platform/language
    // Covers: product stores, booking/hospitality, events, courses, services
    $transactionalTypes = [
        '"@type":"product"', '"@type": "product"',
        '"@type":"lodgingbusiness"', '"@type":"hotel"',
        '"@type":"accommodation"', '"@type":"vacationrental"',
        '"@type":"touristattraction"', '"@type":"lodgingreservation"',
        '"@type":"event"', '"@type":"course"', '"@type":"foodestablishment"',
    ];
    foreach ($transactionalTypes as $t) {
        if (stripos($rawHtml, $t) !== false) return true;
    }

    // Work on lowercased ASCII-safe copy for English signal checks
    $html = strtolower($rawHtml);

    // Platform signals — definitive
    foreach (['shopify', 'woocommerce', 'magento', 'opencart', 'prestashop', 'bigcommerce'] as $p) {
        if (strpos($html, $p) !== false) return true;
    }

    // ── Cart / checkout actions — English (very strong signals) ──────────────
    // Covers: Shopify, WooCommerce, custom stores, D2C brands, health-tech stores
    // "shop now" / "buy online" / "order now" — common D2C CTAs (beatoapp, kapiva, etc.)
    // "/store" — health-tech and D2C brands often have a dedicated /store section
    $cartSignals = [
        'add to cart', 'add-to-cart', 'addtocart',
        '/cart', '/checkout', '/basket',
        'buy now', 'buy online', 'order now',
        'shop now', 'shop the range',
        'add to bag', 'add to basket',
        'proceed to checkout',
        '/store',      // beatoapp, health-tech, D2C brands with store sections
    ];
    foreach ($cartSignals as $s) {
        if (strpos($html, $s) !== false) return true;
    }

    // ── Booking / hospitality / rental CTAs — villa rentals, hotels, tours, etc. ─
    // Covers: elivaas.com, makemytrip, oyo, airbnb-style custom platforms
    // "book now" alone is weak (many stores use it), but combined with pricing/dates it's definitive
    $bookingSignals = [
        'book now', 'reserve now', 'instant booking', 'book your stay',
        'check availability', 'check dates', 'enquire now', 'request booking',
        'per night', '/night', 'per stay',   // rental pricing formats
    ];
    $bookingHits = 0;
    foreach ($bookingSignals as $s) {
        if (strpos($html, $s) !== false && ++$bookingHits >= 2) return true;
    }

    // ── Hindi ecommerce signals — Indian stores ───────────────────────────────
    // खरीदें = Buy/Purchase | कार्ट = Cart | अभी खरीदें = Buy Now | कार्ट में = In cart
    // Covers: myupchar, 1mg, netmeds, any Hindi-language store
    foreach (['खरीदें', 'कार्ट', 'अभी खरीदें', 'कार्ट में'] as $s) {
        if (strpos($rawHtml, $s) !== false) return true;
    }

    // ── ₹ price signals ───────────────────────────────────────────────────────
    // ₹ ×2 = multiple product prices visible → strong ecommerce (kapiva, beatoapp, etc.)
    if (substr_count($rawHtml, '₹') >= 2) return true;

    // ── Composite product/price signals — require 2+ to reduce false positives ─
    // Each alone is weak; any combination of 2 is strong enough
    // 'free shipping' + 'in stock' → almost certainly a store
    // 'mrp' + '₹' → price listing on product/category page
    $count = 0;
    foreach ([
        '₹', 'mrp', '/products/', '/collections/', 'product-page',
        'free shipping', 'cash on delivery', ' cod ', 'add to wishlist',
        'out of stock', 'in stock', 'pincode', 'delivery charges',
        'express delivery', 'same day delivery', 'seller',
    ] as $s) {
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
    $base = extractStoreBase($url);

    // Always fetch the BASE (homepage), not the submitted URL.
    // Users often paste product/category URLs — using the domain root ensures
    // correct platform detection, homepage signal analysis, and link discovery.
    $homeHtml       = fetchSinglePage($base);
    $renderedFallback = false;

    // Empty response = site unreachable, down, or blocking — signal to caller
    if (!$homeHtml) return ['_unreachable' => true];

    // ── Bot-block fallback: re-fetch via Jina if Cloudflare/bot wall detected ──
    // Some stores (liveorganic-type) block server-side curl but are accessible
    // from real browsers. Jina renders the page via headless browser and returns
    // the full HTML — all existing signal detection works unchanged on its output.
    if (isBotBlocked($homeHtml)) {
        $jinaHtml = RendererAdapter::fetch($base);
        if ($jinaHtml) {
            $homeHtml         = $jinaHtml;
            $renderedFallback = true;
            error_log('[OwlEye] Bot-block detected for ' . $base . ' — using Jina fallback');
        }
        // If Jina also fails, continue with whatever curl returned (thin HTML)
    }

    // Detect agentic signals (WhatsApp, chat widgets, FAQ, UCP endpoint, Shopify MCP)
    $agenticHint = detectAgenticSignals($homeHtml, $base);
    $pages = ['home' => cleanHtml($homeHtml, 'home') . $agenticHint];
    if ($renderedFallback) $pages['_rendered_fallback'] = true;
    if (detectJsRendered($homeHtml)) $pages['_js_rendered'] = true;

    // Detect platform
    $platform = detectPlatform($homeHtml);

    // Discover page URLs
    $urlsToFetch = discoverPageUrls($base, $homeHtml, $platform);
    if (empty($urlsToFetch)) return $pages;

    // ── Pre-populate submitted URL if it's a product/category deep-link ────────
    // When a user pastes a product URL (e.g. domain.com/products/item), always
    // include it as the primary product page even if products.json discovery finds
    // a different product. This is critical for JS-heavy Shopify stores where the
    // homepage has thin server-rendered HTML — the submitted product URL has rich
    // content (reviews, images, ATC button) that drives accurate AI scoring.
    // Also covers booking/hospitality item pages: /villa-in-goa/slug, /property/slug
    if ($url !== $base) {
        $submittedPath = strtolower(parse_url($url, PHP_URL_PATH) ?? '');
        if (preg_match(
            '/\/(products?|item|items?|pd?|villa|property|apartment|room|tour|package|hotel|listing|experience|course)[\-\/][^\/\s]{3,}/i',
            $submittedPath
        )) {
            // Shift any already-discovered product to product1 so we don't lose it
            if (isset($urlsToFetch['product']) && $urlsToFetch['product'] !== $url) {
                $urlsToFetch['product1'] = $urlsToFetch['product'];
            }
            $urlsToFetch['product'] = $url;
        } elseif (preg_match('/\/(collections?|categor|browse|villas?|properties|listings?|apartments?|tours?|packages?)\//i', $submittedPath)
               && !isset($urlsToFetch['category'])) {
            $urlsToFetch['category'] = $url;
        }
    }

    // ── Inject platform context for booking/hospitality platforms ─────────────
    // Tells the AI this is a booking site so it interprets checkout_flow,
    // product_pages, and category_pages correctly (reservation flow, property
    // detail pages, and listings browse page respectively).
    if ($platform === 'booking') {
        $pages['home'] .= "\n[PLATFORM_CONTEXT] platform=booking_rental: This is a booking/hospitality/rental platform (villas, hotels, tours, stays). Score parameters contextually — checkout_flow=booking_reservation_flow, product_pages=property_or_item_detail_pages, category_pages=listings_browse_with_filters, cart_recovery=booking_abandonment_flows.";
    }

    // Fetch discovered pages in parallel
    $fetched = fetchPagesParallel($urlsToFetch);
    foreach ($fetched as $type => $html) {
        $pages[$type] = cleanHtml($html, $type);
    }

    // ── Merge extra product pages (product1–4) into primary product ───────────
    // Each extra product is cleaned at a smaller limit; merged total capped at 24000.
    // This gives the AI multi-product review and schema coverage without exploding prompt size.
    $productParts = [$pages['product'] ?? ''];
    for ($i = 1; $i <= 4; $i++) {
        $key = "product{$i}";
        if (!empty($pages[$key])) {
            $productParts[] = "\n\n--- PRODUCT " . ($i + 1) . " ---\n" . $pages[$key];
            unset($pages[$key]);
        }
    }
    $combined = implode('', $productParts);
    if (strlen($combined) > 0) {
        $pages['product'] = mb_substr($combined, 0, 24000);
    }

    // ── Extract blog/content signals then discard (blog not sent to AI directly) ─
    if (!empty($pages['blog'])) {
        $blogHint = detectBlogSignals($pages['blog']);
        if ($blogHint) {
            $pages['home'] = ($pages['home'] ?? '') . $blogHint;
        }
        unset($pages['blog']);
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

    // ── Booking / hospitality / rental platform ────────────────────────────
    // Requires 2+ strong booking signals to avoid false positives
    // (e.g. a Shopify store that uses "Book Now" for an appointment — already
    // caught above, but being conservative here for the generic fallback).
    $lower = strtolower($html);
    $bk = 0;
    if (strpos($lower, 'per night')         !== false || strpos($lower, '/night')          !== false) $bk++;
    if (strpos($lower, 'check-in')          !== false || strpos($lower, 'check in date')   !== false) $bk++;
    if (strpos($lower, 'check availability') !== false || strpos($lower, 'check dates')    !== false) $bk++;
    if (strpos($lower, 'instant booking')   !== false || strpos($lower, 'book your stay')  !== false) $bk++;
    if (strpos($lower, 'guests')            !== false && strpos($lower, 'bedrooms')        !== false) $bk++;
    if ($bk >= 2) return 'booking';

    return 'generic';
}

/**
 * Extract product/category URLs by following homepage CTA buttons.
 * Parses <a href> tags whose anchor text contains buy/shop signal words.
 * Used as a fallback layer for stores where URL patterns don't match standard paths
 * (e.g. kapiva-type custom Shopify, booking platforms, custom PHP stores).
 *
 * Returns ['product' => url, 'category' => url] — only keys that are found.
 */
function extractCtaLinks(string $html, string $base, string $host): array
{
    $found = [];
    $ctaWords = [
        'buy now', 'shop now', 'order now', 'add to cart', 'buy online',
        'shop all', 'explore range', 'shop the collection', 'view products',
        'shop the range', 'explore now', 'buy today', 'shop products',
        'explore products', 'shop here', 'get started',
    ];

    // Extract all <a href="...">text</a> pairs from homepage
    preg_match_all('/<a\s[^>]*href=["\']([^"\'#?]{5,})["\'][^>]*>(.*?)<\/a>/is', $html, $ctaM);
    foreach ($ctaM[1] as $ci => $href) {
        $text = strtolower(strip_tags($ctaM[2][$ci] ?? ''));
        $isCtaSig = false;
        foreach ($ctaWords as $cw) {
            if (strpos($text, $cw) !== false) { $isCtaSig = true; break; }
        }
        if (!$isCtaSig) continue;

        // Resolve to absolute URL
        $abs = preg_match('/^https?:\/\//i', $href)
            ? $href
            : $base . '/' . ltrim($href, '/');
        if (strpos($abs, $host) === false) continue;

        // Prefer longer paths as product pages (slug = product), shorter = category
        $pathDepth = substr_count(rtrim(parse_url($abs, PHP_URL_PATH) ?? '', '/'), '/');
        if (!isset($found['product']) && $pathDepth >= 2) {
            $found['product'] = $abs;
        } elseif (!isset($found['category'])) {
            $found['category'] = $abs;
        }

        if (isset($found['product'], $found['category'])) break;
    }
    return $found;
}

/**
 * Discover product, category, and cart URLs based on platform.
 * Shopify: uses /products.json and /collections.json (no auth required).
 * WooCommerce/generic: parses homepage links.
 */
function discoverPageUrls(string $base, string $html, string $platform): array
{
    $urls       = [];
    $host       = parse_url($base, PHP_URL_HOST);
    // Domain root (scheme + host, no subdirectory path) — needed for WooCommerce
    // where cart/checkout/policy pages live at the domain root even when the shop
    // is hosted in a subdirectory like /shop or /store.
    $domainRoot = parse_url($base, PHP_URL_SCHEME) . '://' . $host;

    if ($platform === 'shopify') {
        // ── Products: up to 5 via JSON API ────────────────────────────────────
        $pJson = fetchSinglePage($base . '/products.json?limit=5');
        $p     = json_decode($pJson, true);
        if (!empty($p['products'])) {
            foreach (array_slice($p['products'], 0, 5) as $i => $prod) {
                if (empty($prod['handle'])) continue;
                $key          = $i === 0 ? 'product' : "product{$i}";
                $urls[$key]   = $base . '/products/' . $prod['handle'];
            }
        }

        $cJson = fetchSinglePage($base . '/collections.json?limit=1');
        $c     = json_decode($cJson, true);
        if (!empty($c['collections'][0]['handle'])) {
            $urls['category'] = $base . '/collections/' . $c['collections'][0]['handle'];
        }

        $urls['cart']    = $base . '/cart';
        $urls['returns'] = $base . '/policies/refund-policy';

        // ── CTA fallback: if products.json returned nothing, follow buy buttons ─
        // Handles Shopify stores with custom checkout (kapiva-type) or
        // stores where the JSON API is restricted / returns empty.
        if (!isset($urls['product']) || !isset($urls['category'])) {
            $ctaFallback = extractCtaLinks($html, $base, $host);
            if (!isset($urls['product'])  && isset($ctaFallback['product']))  $urls['product']  = $ctaFallback['product'];
            if (!isset($urls['category']) && isset($ctaFallback['category'])) $urls['category'] = $ctaFallback['category'];
        }

        // ── Search ─────────────────────────────────────────────────────────────
        $urls['search'] = $base . '/search?q=*&type=product';

        // ── Blog: prefer link from homepage, fallback to /blogs/news ──────────
        preg_match('/href=["\']([^"\'#?]*\/blogs\/[^"\'?#]+)["\']/', $html, $bm);
        $urls['blog'] = !empty($bm[1])
            ? (preg_match('/^https?:/i', $bm[1]) ? $bm[1] : $base . '/' . ltrim($bm[1], '/'))
            : $base . '/blogs/news';

    } elseif ($platform === 'woocommerce') {
        // Category / shop listing page:
        //   - If $base already has a path (e.g. domain.com/shop), use $base itself as the listing.
        //   - Otherwise append /shop (standard WooCommerce at root layout).
        $basePath = trim(parse_url($base, PHP_URL_PATH) ?? '', '/');
        $urls['category'] = $basePath !== '' ? $base : $base . '/shop';

        // Cart and returns are always at the domain root for WooCommerce,
        // even when the shop lives in a /shop or /store subdirectory.
        $urls['cart'] = $domainRoot . '/cart';

        // Find up to 5 product links from homepage
        preg_match_all('/href=["\']([^"\'#?]*\/product\/[^"\'?#]+)["\']/', $html, $m);
        $candidates = array_values(array_filter(
            array_unique($m[1] ?? []),
            fn($l) => strpos($l, $host) !== false || str_starts_with($l, '/')
        ));
        foreach (array_slice($candidates, 0, 5) as $i => $link) {
            $abs = str_starts_with($link, 'http') ? $link : $base . $link;
            $urls[$i === 0 ? 'product' : "product{$i}"] = $abs;
        }

        // ── Search ─────────────────────────────────────────────────────────────
        $urls['search'] = $base . '/?s=*&post_type=product';

        // ── Blog ───────────────────────────────────────────────────────────────
        $urls['blog'] = $base . '/blog';

    } else {
        // Generic: score all homepage links by pattern
        preg_match_all('/href=["\']([^"\'#?]{5,})["\']/', $html, $m);
        $links = array_unique($m[1] ?? []);

        $productPats  = [
            '/\/(products?|item|p|pd)\/[^\/]{3,}/i',
            '/\/(medicine|medicines|drug|drugs|supplement|supplements|health-product|lab-test|labs?|otc)\/[^\/]{3,}/i',
            '/\/(device|devices|glucometer|monitor|kit|combo|pack|buy)\/[^\/]{3,}/i',
        ];
        $categoryPats = [
            '/\/(collections?|categor|shop|browse|c)\/[^\/]{2,}/i',
            '/\/shop\/?$/i',
            '/\/(pharmacy|health-products?|vitamins?|wellness|ayurved)\/?/i',
            '/\/store\/?$/i',
            '/\/store\//i',
        ];
        $cartPats     = ['/\/(cart|checkout|bag)\/?$/i'];
        $blogPats     = ['/\/(blog|news|articles?|learn|tips|reads?)\/[^\/]{3,}/i', '/\/blog\/?$/i'];
        $searchPats   = ['/\/(search|find|query)\/?/i'];

        $productCount = 0;
        foreach ($links as $link) {
            if (!preg_match('/^https?:\/\//i', $link)) {
                $link = $base . '/' . ltrim($link, '/');
            }
            if (strpos($link, $host) === false) continue;

            if ($productCount < 5) {
                foreach ($productPats as $pat) {
                    if (preg_match($pat, $link)) {
                        $key = $productCount === 0 ? 'product' : "product{$productCount}";
                        $urls[$key] = $link;
                        $productCount++;
                        break;
                    }
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
            if (!isset($urls['blog'])) {
                foreach ($blogPats as $pat) {
                    if (preg_match($pat, $link)) { $urls['blog'] = $link; break; }
                }
            }
            if (!isset($urls['search'])) {
                foreach ($searchPats as $pat) {
                    if (preg_match($pat, $link)) { $urls['search'] = $link; break; }
                }
            }
        }

        // Search fallback
        if (!isset($urls['search'])) $urls['search'] = $base . '/search?q=*';
        // Blog fallback
        if (!isset($urls['blog']))   $urls['blog']   = $base . '/blog';

        // ── CTA-follow fallback: follow buy-button hrefs when URL patterns miss ─
        // Catches custom-platform stores (kapiva-type) where product/category URLs
        // don't match standard patterns. Homepage CTAs ("Buy Now", "Shop Now") always
        // link to the store's most important pages — follow them directly.
        if (!isset($urls['product']) || !isset($urls['category'])) {
            $ctaFallback = extractCtaLinks($html, $base, $host);
            if (!isset($urls['product'])  && isset($ctaFallback['product']))  $urls['product']  = $ctaFallback['product'];
            if (!isset($urls['category']) && isset($ctaFallback['category'])) $urls['category'] = $ctaFallback['category'];
        }

        // ── Probe-based listing discovery for JS-heavy / custom platforms ─────
        // Link scraping above works for server-rendered HTML (WooCommerce, custom PHP).
        // For React/Next.js SPAs (elivaas, custom booking sites), the homepage has
        // almost no <a href> tags — direct path probing is the only way to find pages.
        if (!isset($urls['category'])) {
            $listingPaths = [
                '/villas', '/properties', '/listings', '/apartments', '/rooms',
                '/tours', '/packages', '/experiences', '/courses', '/services',
                '/catalogue', '/catalog',
            ];
            foreach ($listingPaths as $lp) {
                if (headCheckUrl($base . $lp)) {
                    $urls['category'] = $base . $lp;
                    break;
                }
            }
        }

        // ── Secondary item discovery from listing page ────────────────────────
        // Listing pages are SEO-server-rendered even on Next.js SPAs — much more
        // reliable for link scraping than the homepage. Fetch up to 3 item pages.
        if (isset($urls['category']) && !isset($urls['product'])) {
            $listingHtml = fetchSinglePage($urls['category']);
            if ($listingHtml) {
                preg_match_all('/href=["\']([^"\'#?]{10,})["\']/', $listingHtml, $lm);
                $itemPats = [
                    // Booking/hospitality: /villa-NAME, /property/NAME, /hotel-NAME
                    '/\/(villa|property|apartment|room|tour|package|experience|course|service|listing|hotel|resort)[\-\/][^\/\s]{3,}/i',
                    // City-slug format: /villa-in-goa/property-name  (elivaas pattern)
                    '/\/[a-z]+-in-[a-z][a-z\-]+\/[a-z0-9\-]{8,}/i',
                    // Generic deep slug: /item/NAME or /p/NAME
                    '/\/(item|p|pd|detail)\/[^\/\s]{3,}/i',
                ];
                $itemCount = 0;
                foreach (array_unique($lm[1] ?? []) as $iLink) {
                    if ($itemCount >= 3) break;
                    if (!preg_match('/^https?:\/\//i', $iLink)) {
                        $iLink = $base . '/' . ltrim($iLink, '/');
                    }
                    if (strpos($iLink, $host) === false) continue;
                    foreach ($itemPats as $pat) {
                        if (preg_match($pat, $iLink)) {
                            $key = $itemCount === 0 ? 'product' : "product{$itemCount}";
                            if (!isset($urls[$key])) {
                                $urls[$key] = $iLink;
                                $itemCount++;
                            }
                            break;
                        }
                    }
                }
            }
        }

        // ── Probe-based booking/cart page ─────────────────────────────────────
        // Booking platforms use /booking or /reserve instead of /cart
        if (!isset($urls['cart'])) {
            foreach (['/booking', '/book', '/reserve'] as $bp) {
                if (headCheckUrl($base . $bp)) {
                    $urls['cart'] = $base . $bp;
                    break;
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
        // Custom platform fallbacks — health/pharma stores (e.g. myupchar uses /home/refund_policy)
        // which rarely appear as plain <a href> in server-rendered HTML (JS footer).
        // Use HEAD requests (3s timeout) to find the right path quickly, then let
        // fetchPagesParallel fetch the full content — avoids double-fetch and prevents
        // the worst-case 56s delay (7 paths × 8s) that caused PHP timeout failures.
        if (!isset($urls['returns'])) {
            $customReturnPaths = [
                '/home/refund_policy',
                '/home/return-policy',
                '/help/refund',
                '/help/returns',
                '/support/refund-policy',
                '/pages/return-policy',
                '/pages/returns',
                // Booking/hospitality platforms use cancellation instead of refund
                '/cancellation-policy',
                '/cancellation',
                '/terms-and-conditions',
            ];
            foreach ($customReturnPaths as $path) {
                if (headCheckUrl($base . $path)) {
                    $urls['returns'] = $base . $path;
                    break;
                }
            }
        }

        if (!isset($urls['returns'])) {
            $urls['returns'] = match ($platform) {
                // WooCommerce policy pages live at domain root, not under a /shop subdirectory
                'woocommerce' => $domainRoot . '/refund_policy',
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
 * Detect agentic commerce signals from raw homepage HTML.
 * Returns a compact hint string injected into the cleaned HTML so the AI
 * can score conversational_ux and whatsapp_marketing with higher confidence.
 *
 * Confidence levels:
 *   HIGH  — unique CDN/script domain that unambiguously identifies the tool
 *   MED   — common patterns that may have false positives
 *
 * Signal memory (add new detections here as more stores are tested):
 *   WhatsApp widget  : wa.me/, api.whatsapp.com, widget.wati.io — HIGH
 *   Tawk.to          : embed.tawk.to — HIGH
 *   Intercom         : widget.intercom.io, intercomcdn.com — HIGH
 *   Crisp Chat       : client.crisp.chat — HIGH
 *   Freshchat        : wchat.freshchat.com, freshbots — HIGH
 *   Tidio            : code.tidio.co — HIGH
 *   Drift            : js.driftt.com — HIGH
 *   Zendesk Chat     : static.zdassets.com, zopim — HIGH
 *   Kommunicate      : widget.kommunicate.io — HIGH
 *   Verloop          : verloop.io — HIGH
 *   Yellow.ai        : cloud.yellow.ai, yellowmessenger.com — HIGH
 *   BotPenguin       : botpenguin.com — HIGH
 *   Gorgias          : config.gorgias.chat — HIGH (Shopify CS)
 *   LiveChat         : livechatinc.com — HIGH
 *   Jivochat         : jivosite.com — HIGH
 *   Smartsupp        : smartsupp.com — HIGH
 *   Chatbot.com      : chatbot.com — MED (generic domain)
 */
/**
 * Quick HEAD/GET to a URL — 3-second timeout, returns body or empty string.
 * Used for lightweight endpoint presence checks (UCP, MCP) without the 8s
 * timeout of fetchSinglePage(), which would add too much serial latency.
 */
function probeEndpoint(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; OwlEye-Scanner/1.0; +https://genaitechlabs.com)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ?: '';
}

/**
 * Lightweight URL existence check — HEAD request, 3s timeout, follows redirects.
 * Returns true if the URL responds with HTTP 200–399.
 * Used by customReturnPaths probing to find the right returns policy path without
 * fetching full page content (avoids double-fetch and serial 8s-per-path delay).
 */
function headCheckUrl(string $url): bool
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; OwlEye-Scanner/1.0; +https://genaitechlabs.com)',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 400;
}

function detectAgenticSignals(string $rawHtml, string $base = ''): string
{
    $html = strtolower($rawHtml);
    $signals = [];

    // ── WhatsApp ──────────────────────────────────────────────────
    if (strpos($html, 'wa.me/')           !== false ||
        strpos($html, 'api.whatsapp.com') !== false ||
        strpos($html, 'widget.wati.io')   !== false) {
        $signals[] = 'whatsapp_widget=true';
    }

    // ── Live Chat / Support widgets ───────────────────────────────
    // Signal memory — add new platforms here as more stores are tested:
    $chatPlatforms = [
        'embed.tawk.to'          => 'tawk.to',
        'widget.intercom.io'     => 'intercom',
        'intercomcdn.com'        => 'intercom',
        'client.crisp.chat'      => 'crisp',
        'wchat.freshchat.com'    => 'freshchat',
        'code.tidio.co'          => 'tidio',
        'js.driftt.com'          => 'drift',
        'static.zdassets.com'    => 'zendesk',
        'zopim.com'              => 'zendesk',
        'widget.kommunicate.io'  => 'kommunicate',
        'verloop.io'             => 'verloop',
        'cloud.yellow.ai'        => 'yellow.ai',
        'yellowmessenger.com'    => 'yellow.ai',
        'botpenguin.com'         => 'botpenguin',
        'config.gorgias.chat'    => 'gorgias',
        'livechatinc.com'        => 'livechat',
        'jivosite.com'           => 'jivochat',
        'smartsupp.com'          => 'smartsupp',
    ];
    foreach ($chatPlatforms as $needle => $platform) {
        if (strpos($html, $needle) !== false) {
            $signals[] = 'chat_widget=' . $platform;
            break; // one platform is enough
        }
    }

    // ── FAQ / Q&A sections ────────────────────────────────────────
    if (strpos($html, 'faq')                    !== false ||
        strpos($html, 'frequently asked')        !== false ||
        strpos($html, 'questions and answers')   !== false ||
        strpos($rawHtml, '"@type":"FAQPage"')    !== false) {
        $signals[] = 'faq_section=true';
    }

    // ── Endpoint-based agentic commerce detection ─────────────────
    // These checks run with a 3s timeout so they don't block the main scan.
    if ($base) {
        // UCP (Universal Commerce Protocol) — open standard co-developed by Google,
        // Shopify, Walmart, Target, Etsy. Enables AI agents (ChatGPT, Gemini, Copilot)
        // to discover, query, and checkout from a store without custom integration.
        // Spec: https://ucp.dev/specification/overview/
        // Discovery: GET /.well-known/ucp → JSON with 'capabilities' or 'services' array
        $ucpBody = probeEndpoint($base . '/.well-known/ucp');
        if ($ucpBody) {
            $ucp = json_decode($ucpBody, true);
            if (!empty($ucp['capabilities']) || !empty($ucp['services']) || !empty($ucp['version'])) {
                $signals[] = 'ucp_endpoint=true';
            }
        }

        // Shopify MCP (Model Context Protocol) storefront endpoint —
        // Shopify's native agentic commerce implementation. Active on all modern
        // Shopify stores and exposes product catalogue + checkout via MCP protocol.
        // Spec: https://shopify.dev/docs/agents/catalog/storefront-mcp
        // Detection: cdn.shopify.com in HTML + /api/mcp returns 200 with MCP structure
        $isShopify = strpos($html, 'cdn.shopify.com') !== false
                  || strpos($html, 'myshopify.com')   !== false;
        if ($isShopify) {
            $mcpBody = probeEndpoint($base . '/api/mcp');
            if ($mcpBody && strlen($mcpBody) > 20) {
                $mcp = json_decode($mcpBody, true);
                // MCP response includes 'tools' or 'jsonrpc' or 'protocolVersion'
                if (!empty($mcp['tools']) || isset($mcp['jsonrpc']) || isset($mcp['protocolVersion'])) {
                    $signals[] = 'shopify_mcp=true';
                }
            }
        }
    }

    if (empty($signals)) return '';
    return "\n[AGENTIC_SIGNALS] " . implode(' | ', $signals);
}

/**
 * Detect purchase flow signals from all crawled pages.
 * Returns a compact hint string injected into home page HTML so the AI
 * can score Purchase Flow params with higher confidence.
 *
 * Covers parameters that are otherwise estimated (~est.) because cart pages
 * are often gated. Scans homepage + product page HTML for payment method
 * references, CRM/email tools, COD text, and express checkout scripts.
 *
 * Signal memory:
 *   email_crm={tool}          : Klaviyo/Mailchimp/WebEngage/MoEngage → infer cart recovery flows
 *   cod_available=true        : "cash on delivery" / "COD" text on product or home page
 *   upi_available=true        : GPay/PhonePe/Paytm/UPI text anywhere
 *   bnpl_available=true       : Simpl/LazyPay/ZestMoney/Snapmint or "buy now pay later" text
 *   payment_gateway=detected  : Razorpay/Cashfree/PayU/CCAvenue script or text
 *   express_checkout_signal=true : PhonePe express, Razorpay Magic Checkout, Shopify dynamic-checkout
 *   push_capture=true         : OneSignal/iZooto/PushOwl/Omnisend — email/push capture tools
 *   sticky_atc_signal=true    : sticky/fixed CSS class near add-to-cart context in product HTML
 */
function detectPurchaseFlowSignals(array $pages): string
{
    // Combine all crawled pages for signal search (lowercased for English signals)
    $rawAll  = ($pages['home'] ?? '') . ($pages['product'] ?? '') . ($pages['category'] ?? '');
    $html    = strtolower($rawAll);

    $signals = [];

    // ── Email CRM / Cart Recovery ──────────────────────────────────────────────
    // Presence of email CRM = store almost certainly has abandoned cart flows
    $crmTools = [
        'a.klaviyo.com'      => 'klaviyo',
        'static.klaviyo.com' => 'klaviyo',
        'chimpstatic.com'    => 'mailchimp',
        'list-manage.com'    => 'mailchimp',
        'cdn.webengage.com'  => 'webengage',
        'cdn.moengage.com'   => 'moengage',
        'sdk.moengage.com'   => 'moengage',
        'wzrkt.com'          => 'clevertap',    // CleverTap
        'netcorecloud.net'   => 'netcore',
        'omnisend.com'       => 'omnisend',
        'sendx.io'           => 'sendx',
    ];
    foreach ($crmTools as $needle => $tool) {
        if (strpos($html, $needle) !== false) {
            $signals[] = 'email_crm=' . $tool;
            break;
        }
    }

    // ── COD (Cash on Delivery) ─────────────────────────────────────────────────
    if (strpos($html, 'cash on delivery') !== false ||
        strpos($html, 'cod available')    !== false ||
        strpos($html, 'pay on delivery')  !== false ||
        preg_match('/\bcod\b/', $html)) {
        $signals[] = 'cod_available=true';
    }

    // ── UPI (Unified Payments Interface) ──────────────────────────────────────
    if (strpos($html, 'gpay')       !== false ||
        strpos($html, 'google pay') !== false ||
        strpos($html, 'phonepe')    !== false ||
        strpos($html, 'paytm')      !== false ||
        preg_match('/\bupi\b/', $html)) {
        $signals[] = 'upi_available=true';
    }

    // ── BNPL (Buy Now Pay Later) ───────────────────────────────────────────────
    if (strpos($html, 'simpl')               !== false ||
        strpos($html, 'lazypay')             !== false ||
        strpos($html, 'zestmoney')           !== false ||
        strpos($html, 'snapmint')            !== false ||
        strpos($html, 'buy now pay later')   !== false ||
        preg_match('/\bbnpl\b/', $html)) {
        $signals[] = 'bnpl_available=true';
    }

    // ── Payment Gateway ────────────────────────────────────────────────────────
    if (strpos($html, 'razorpay') !== false ||
        strpos($html, 'cashfree') !== false ||
        strpos($html, 'payugbiz') !== false ||
        strpos($html, 'ccavenue') !== false) {
        $signals[] = 'payment_gateway=detected';
    }

    // ── Express Checkout ───────────────────────────────────────────────────────
    // Detect by script/widget presence — NOT by URL pattern.
    // Covers custom checkout URLs (kapiva-type /checkout-custom/) where the standard
    // /checkout path doesn't exist but an express checkout solution IS integrated.
    if (strpos($html, 'magic checkout')           !== false ||  // Razorpay Magic Checkout
        strpos($html, 'dynamic-checkout')         !== false ||  // Shopify accelerated checkout
        strpos($html, 'shopify-payment-button')   !== false ||  // Shopify payment button widget
        strpos($html, 'gokwik')                   !== false ||  // GoKwik (Indian express checkout)
        strpos($html, 'juspay')                   !== false ||  // Juspay Hyper Checkout
        strpos($html, 'checkout.razorpay.com')    !== false ||  // Razorpay hosted checkout
        strpos($html, 'cashfree.com/checkout')    !== false ||  // Cashfree Smart Checkout
        strpos($html, 'phonepe.com/checkout')     !== false) {  // PhonePe express checkout
        $signals[] = 'express_checkout_signal=true';
    }

    // ── Guest Checkout / Pre-login Flow ────────────────────────────────────────
    // Check if the purchase flow requires login BEFORE checkout (high friction) or
    // allows guest/express checkout without forced login (frictionless = good UX).
    // Uses cart + product pages — both already fetched, zero extra latency.
    $cartHtml    = strtolower($pages['cart'] ?? '');
    $checkHtml   = $cartHtml . strtolower($pages['product'] ?? '');
    if ($checkHtml) {
        if (strpos($checkHtml, 'guest checkout')       !== false ||
            strpos($checkHtml, 'checkout as guest')    !== false ||
            strpos($checkHtml, 'continue as guest')    !== false ||
            strpos($checkHtml, 'skip login')           !== false) {
            $signals[] = 'guest_checkout=true';
        } elseif ((strpos($checkHtml, 'login to checkout')    !== false ||
                   strpos($checkHtml, 'sign in to continue')  !== false ||
                   strpos($checkHtml, 'login to continue')    !== false ||
                   strpos($checkHtml, 'please login')         !== false) &&
                  strpos($checkHtml, 'guest') === false) {
            $signals[] = 'forced_login_checkout=true';
        }
    }

    // ── Email / Push Capture Tools ─────────────────────────────────────────────
    if (strpos($html, 'onesignal.com') !== false ||
        strpos($html, 'izooto.com')    !== false ||
        strpos($html, 'pushowl.com')   !== false ||
        strpos($html, 'omnisend.com')  !== false) {
        $signals[] = 'push_capture=true';
    }

    // ── Sticky ATC ─────────────────────────────────────────────────────────────
    // Sticky/fixed class found near add-to-cart context in product page HTML
    $productHtml = strtolower($pages['product'] ?? '');
    if ($productHtml &&
        (strpos($productHtml, 'sticky') !== false || strpos($productHtml, 'position:fixed') !== false) &&
        (strpos($productHtml, 'add-to-cart') !== false || strpos($productHtml, 'add_to_cart') !== false || strpos($productHtml, 'addtocart') !== false)) {
        $signals[] = 'sticky_atc_signal=true';
    }

    // ── Wishlist / Save for Later ───────────────────────────────────────────────
    // Wishlist = engagement/retention feature; signals cross-sell capability.
    // Also catches Swym (popular Shopify wishlist app) and Growave (Indian loyalty+wishlist).
    foreach ([
        'add to wishlist', 'save for later', 'add to favourites', 'add to favorites',
        'save to wishlist', 'swym', 'growave',
    ] as $wk) {
        if (strpos($html, $wk) !== false) {
            $signals[] = 'wishlist_feature=true';
            break;
        }
    }
    if (!in_array('wishlist_feature=true', $signals)) {
        if (preg_match('/href=["\'][^"\']*\/(wishlist|favourites?|favorites?)["\']/', $rawAll)) {
            $signals[] = 'wishlist_feature=true';
        }
    }

    if (empty($signals)) return '';
    return "\n[PURCHASE_SIGNALS] " . implode(' | ', $signals);
}

/**
 * Strip scripts/styles/comments, preserve JSON-LD + __NEXT_DATA__, truncate.
 * JSON-LD and hydration state are server-rendered even on Next.js/Shopify Hydrogen
 * sites, giving the AI real signals when the visible HTML is otherwise empty.
 */
function cleanHtml(string $html, string $type): string
{
    $extras = '';

    // ── Preserve JSON-LD structured data (Product, Review, FAQ, BreadcrumbList…)
    preg_match_all(
        '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is',
        $html, $ldMatches
    );
    foreach ($ldMatches[1] as $ldRaw) {
        $ldRaw = trim($ldRaw);
        if ($ldRaw) $extras .= "\n[JSON-LD] " . $ldRaw;
    }
    $extras = mb_substr($extras, 0, 2000); // cap at 2 000 chars

    // ── Preserve __NEXT_DATA__ page props (Next.js — always server-rendered)
    if (preg_match('/<script[^>]+id=["\']__NEXT_DATA__["\'][^>]*>(.*?)<\/script>/is', $html, $ndMatch)) {
        $nd = json_decode(trim($ndMatch[1]), true);
        if (!empty($nd['props']['pageProps'])) {
            $extras .= "\n[PAGE_STATE] " . mb_substr(json_encode($nd['props']['pageProps']), 0, 2000);
        }
    }

    // ── Strip scripts / styles / comments
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is',   '', $html);
    $html = preg_replace('/<!--.*?-->/s',                     '', $html);
    $html = preg_replace('/\s{2,}/',                          ' ', $html);

    $limits = [
        'home'         => 10000,
        'product'      => 18000,  // primary product; merged total capped at 24000 in discoverAndFetchPages
        'product1'     => 3000,   // extra product pages (merged before AI call)
        'product2'     => 3000,
        'product3'     => 3000,
        'product4'     => 3000,
        'category'     => 5000,
        'cart'         => 5000,
        'returns'      => 5000,
        'search'       => 5000,   // search results page — used to score search_ux
        'blog'         => 6000,   // blog page — signals extracted, not sent to AI directly
    ];
    return mb_substr(trim($html), 0, $limits[$type] ?? 5000) . $extras;
}

/**
 * Detect trust signals: press/media coverage, testimonial sections, and large trust counts.
 * Called after all pages are fetched. Injects [TRUST_SIGNALS] into pages['home'].
 *
 * Signals:
 *   press_coverage=true     : "featured in", "as seen in" + outlet names (NDTV, BBC, etc.)
 *   testimonial_section=true: testimonials/customer-stories section on home or product page
 *   trust_count=true        : large customer/order counts (1 lakh+, 1,00,000+)
 */
function detectTrustSignals(array $pages): string
{
    $rawAll = ($pages['home'] ?? '') . ($pages['product'] ?? '') . ($pages['category'] ?? '');
    $html   = strtolower($rawAll);

    $signals = [];

    // ── Press / Media Coverage ─────────────────────────────────────────────────
    // Primary: explicit "featured in" / "as seen in" sections
    foreach (['featured in', 'as seen in', 'in the news', 'media coverage', 'press coverage'] as $kw) {
        if (strpos($html, $kw) !== false) {
            $signals[] = 'press_coverage=true';
            break;
        }
    }
    // Fallback: 2+ known outlet names present anywhere on page (logo strips, footers)
    if (!in_array('press_coverage=true', $signals)) {
        $outlets  = ['ndtv', 'republic tv', 'bbc', 'economic times', 'business standard',
                     'hindustan times', 'inc42', 'yourstory', 'forbes', 'mint.com',
                     'cnbc', 'bloomberg', 'the week', 'livemint', 'techcrunch', 'financial express'];
        $outletHits = 0;
        foreach ($outlets as $o) {
            if (strpos($html, $o) !== false) $outletHits++;
        }
        if ($outletHits >= 2) $signals[] = 'press_coverage=true';
    }

    // ── Testimonial Section ────────────────────────────────────────────────────
    $testimonialKws = [
        'testimonial', 'what our customers say', 'what customers say',
        'what people say', 'customer stories', 'customer love',
        'what our users say', 'real customers', 'hear from our customers',
        'happy customers', 'our customers',
    ];
    foreach ($testimonialKws as $kw) {
        if (strpos($html, $kw) !== false) {
            $signals[] = 'testimonial_section=true';
            break;
        }
    }

    // ── Trust Count (large customer / order numbers) ───────────────────────────
    // Matches: "1 lakh+ customers", "5 crore users", "10 million orders"
    if (preg_match('/\d[\d,]*\+?\s*(lakh|crore|million|lac)\+?\s*(customer|user|order|patient|member)/i', $rawAll)) {
        $signals[] = 'trust_count=true';
    }
    // Matches formatted Indian numbers: "1,00,000+" "10,00,000+" (≥ 6 digits)
    if (!in_array('trust_count=true', $signals)) {
        if (preg_match('/[1-9]\d{0,2}(?:,\d{2,3}){2,}\+/', $rawAll)) {
            $signals[] = 'trust_count=true';
        }
    }

    if (empty($signals)) return '';
    return "\n\n[TRUST_SIGNALS]: " . implode(', ', $signals);
}

/**
 * Extract content signals from a fetched blog/content page.
 * Returns a [CONTENT_SIGNALS] hint injected into pages['home'] before AI scoring.
 * The blog page itself is NOT sent to the AI — only the signals extracted here.
 *
 * Signals:
 *   blog_howto=true   : how-to guides, step-by-step tutorials, tips content
 *   blog_faq=true     : FAQ-style content (question headings, Q&A format)
 *   blog_schema=true  : Article or BlogPosting JSON-LD structured data
 */
function detectBlogSignals(string $blogHtml): string
{
    $html    = strtolower($blogHtml);
    $signals = [];

    // ── How-to / Guide / Tips content ─────────────────────────────────────────
    if (preg_match('/how[\s\-]to|step[\s\-]\d|beginner.?guide|tips\s+for|what\s+is|best\s+\w+\s+for/i', $blogHtml)) {
        $signals[] = 'blog_howto=true';
    }

    // ── FAQ-style blog content ────────────────────────────────────────────────
    // Look for question headings (H2/H3 ending with ?) or explicit FAQ sections
    if (strpos($html, 'faq') !== false ||
        strpos($html, 'frequently asked') !== false ||
        preg_match('/<h[23][^>]*>[^<]*\?<\/h[23]>/i', $blogHtml)) {
        $signals[] = 'blog_faq=true';
    }

    // ── Article / BlogPosting schema ──────────────────────────────────────────
    if (strpos($blogHtml, '"@type":"Article"')     !== false ||
        strpos($blogHtml, '"@type":"BlogPosting"') !== false ||
        strpos($blogHtml, '"@type": "Article"')    !== false) {
        $signals[] = 'blog_schema=true';
    }

    if (empty($signals)) return '';
    return "\n\n[CONTENT_SIGNALS]: " . implode(', ', $signals);
}

/**
 * Detect whether a page is JavaScript-rendered (thin server-side HTML).
 * Checks framework fingerprints in raw HTML before scripts are stripped.
 */
function detectJsRendered(string $rawHtml): bool
{
    foreach ([
        '__NEXT_DATA__',        // Next.js
        'data-reactroot',       // React SSR
        '"__nuxt"',             // Nuxt.js
        'ng-version=',          // Angular
        '__GATSBY',             // Gatsby
        'data-server-rendered', // Vue SSR
    ] as $signal) {
        if (strpos($rawHtml, $signal) !== false) return true;
    }
    // Fallback: very thin visible text after stripping tags
    return strlen(trim(preg_replace('/\s+/', ' ', strip_tags($rawHtml)))) < 500;
}


/**
 * Detect whether a homepage HTML response is a bot-protection wall
 * (Cloudflare, Imperva, Akamai, etc.) rather than real store content.
 *
 * Returns true if the page should be re-fetched via the rendering fallback.
 */
function isBotBlocked(string $rawHtml): bool
{
    // Hard block: too thin to be a real homepage (challenge pages are ~1-3KB)
    $visibleText = trim(preg_replace('/\s+/', ' ', strip_tags($rawHtml)));
    if (strlen($visibleText) < 300) return true;

    // Known bot-protection fingerprints in page text / HTML attributes
    $lower = strtolower($rawHtml);
    foreach ([
        'cf-browser-verification',   // Cloudflare classic challenge
        'cf_chl_opt',                // Cloudflare managed challenge
        'just a moment',             // Cloudflare waiting room
        'checking your browser',     // Cloudflare / generic
        'enable javascript and cookies', // Cloudflare
        'please enable cookies',     // Cloudflare
        'ddos-guard',                // DDoS-Guard
        'human verification',        // Generic captcha wall
        '_perimeterx',               // PerimeterX
        'px-captcha',                // PerimeterX CAPTCHA
        'incapsula',                 // Imperva Incapsula
    ] as $fingerprint) {
        if (strpos($lower, $fingerprint) !== false) return true;
    }

    return false;
}

// ════════════════════════════════════════════════════════════════
// Scoring helpers
// ════════════════════════════════════════════════════════════════

/**
 * Compute which params are verified vs unverified based on pages fetched.
 * Page requirement map is defined as a static inside the function to ensure
 * it is always available regardless of PHP execution order.
 */
function computeVerification(array $pages, bool $psiConfigured): array
{
    static $PARAM_PAGE_REQUIREMENT = [
        'checkout_flow'      => 'cart',
        'payment_options'    => 'cart',
        'cart_recovery'      => null,      // session/behaviour — always unverifiable
        'express_checkout'   => 'cart',
        'cod_prominence'     => 'cart',
        'landing_page'       => 'home',
        'product_pages'      => 'product',
        'search_ux'          => 'search_or_category',
        'sticky_atc'         => 'product',
        'category_pages'     => 'category',
        'trust_signals'      => 'home',
        'returns_policy'     => 'returns',
        'social_proof'       => 'home_or_product',  // UGC/testimonials often on homepage (e.g. myupchar)
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
        'canonical_health'   => 'any_crawled',  // checks home + product + category
        'mobile_ux'          => 'home',
        'page_speed'         => 'psi',
        'navigation_clarity' => 'home',
        'accessibility'      => 'home',
    ];

    $fetched    = array_keys($pages);
    $verified   = [];
    $unverified = [];

    foreach ($PARAM_PAGE_REQUIREMENT as $param => $req) {
        $ok = match (true) {
            $req === null               => false,
            $req === 'psi'              => $psiConfigured,
            $req === 'any_crawled'      => count(array_intersect(['home', 'product', 'category'], $fetched)) > 0,
            $req === 'home_or_product'    => in_array('home', $fetched) || in_array('product', $fetched),
            $req === 'search_or_category' => in_array('search', $fetched) || in_array('category', $fetched),
            in_array($req, $fetched)      => true,
            default                     => false,
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
 * Extract the store homepage base URL from any submitted URL.
 *
 * Handles three cases:
 *   1. Subdomain stores  — shop.domain.com/products/x  → https://shop.domain.com
 *   2. Subdirectory shop — domain.com/shop/products/x  → https://domain.com/shop
 *                          domain.com/store/anything   → https://domain.com/store
 *   3. Leaf page URLs    — domain.com/products/x       → https://domain.com
 *                          domain.com/collections/y    → https://domain.com
 *
 * Only /shop and /store are treated as shop-root subdirectories.
 * All other first path segments (products, collections, category, pages…)
 * are treated as leaf pages and stripped back to the domain root.
 */
function extractStoreBase(string $url): string
{
    $scheme     = parse_url($url, PHP_URL_SCHEME) ?: 'https';
    $host       = parse_url($url, PHP_URL_HOST)   ?: '';
    $path       = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');
    $domainRoot = $scheme . '://' . $host;

    if (!$path) return $domainRoot;

    $firstSeg = strtolower(explode('/', $path)[0]);

    // Known shop-subdirectory names — store homepage lives at domain.com/<seg>
    // Common for WooCommerce sites where the WordPress blog occupies the root.
    if (in_array($firstSeg, ['shop', 'store'], true)) {
        return $domainRoot . '/' . $firstSeg;
    }

    // Everything else (products, collections, category, pages, blogs, cart…)
    // is a leaf page — strip back to the domain root.
    return $domainRoot;
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

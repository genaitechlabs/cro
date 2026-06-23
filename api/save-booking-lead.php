<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/save-booking-lead.php
   Saves a booking audit lead from the "Book your free CRO audit" form.
   POST { first_name, last_name, email, url, revenue, visitors, platform, hp }
   Returns { success: true } or { error: "..." }

   Security layers (cheapest first):
   1. Content-Type must be application/json
   2. Payload ≤ 4 KB
   3. Honeypot (hp) must be empty
   4. Null-byte stripping on all string inputs
   5. Input validation (name regex, work-email only, dropdown whitelist)
   6. PDO prepared statements — SQL injection not possible
   7. IP rate limit (5/hour via booking_leads table)
   8. 24-hour duplicate email guard
   ═══════════════════════════════════════════════════════════════ */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://genaitechlabs.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

// ── 1. Content-Type check — only reject if explicitly non-JSON ───
// (empty means unknown/stripped by proxy — allow through)
$ct = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '';
if ($ct && strpos($ct, 'json') === false) {
    http_response_code(415);
    echo json_encode(['error' => 'Unsupported media type']);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ── 2. Payload size cap (4 KB) ───────────────────────────────────
$raw = file_get_contents('php://input');
if (strlen($raw) > 4096) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large']);
    exit;
}

$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// ── 3. Honeypot — bots fill it, humans never see it ─────────────
// (frontend renders a hidden off-screen input with id="ibHp"/"bkHp")
$hp = trim($input['hp'] ?? '');
if ($hp !== '') {
    // Silently succeed — fool the bot, give nothing away
    echo json_encode(['success' => true]);
    exit;
}

// ── 4. Null-byte strip + trim all string inputs ──────────────────
function cleanInput(string $val): string {
    return trim(str_replace("\0", '', $val));
}

$firstName = cleanInput($input['first_name'] ?? '');
$lastName  = cleanInput($input['last_name']  ?? '');
$email     = strtolower(cleanInput($input['email']    ?? ''));
$url       = cleanInput($input['url']       ?? '');
$revenue   = cleanInput($input['revenue']   ?? '');
$visitors  = cleanInput($input['visitors']  ?? '');
$platform  = cleanInput($input['platform']  ?? '');

// ── 5a. Validate first + last name ──────────────────────────────
foreach ([['First name', $firstName], ['Last name', $lastName]] as [$label, $val]) {
    if (mb_strlen($val) < 2) {
        http_response_code(400); echo json_encode(['error' => $label . ' must be at least 2 characters.']); exit;
    }
    if (mb_strlen($val) > 50) {
        http_response_code(400); echo json_encode(['error' => $label . ' is too long.']); exit;
    }
    if (!preg_match("/^[\p{L}\s'\-]+$/u", $val)) {
        http_response_code(400); echo json_encode(['error' => $label . ' contains invalid characters.']); exit;
    }
}
if (mb_strtolower($firstName) === mb_strtolower($lastName)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter your real first and last name.']);
    exit;
}

// ── 5b. Validate email ──────────────────────────────────────────
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); echo json_encode(['error' => 'Please enter a valid email address.']); exit;
}

$personalDomains = [
    'gmail.com', 'googlemail.com',
    'yahoo.com', 'yahoo.in', 'yahoo.co.in', 'ymail.com',
    'hotmail.com', 'hotmail.in', 'live.com', 'live.in', 'outlook.com', 'msn.com',
    'aol.com', 'icloud.com', 'me.com', 'mac.com',
    'protonmail.com', 'pm.me', 'rediffmail.com',
];
$disposableDomains = [
    'mailinator.com', 'tempmail.com', 'guerrillamail.com', '10minutemail.com',
    'throwam.com', 'fakeinbox.com', 'yopmail.com', 'sharklasers.com',
    'trashmail.com', 'getairmail.com', 'dispostable.com', 'spamgourmet.com',
];
$emailDomain = substr(strrchr($email, '@'), 1);
if (in_array($emailDomain, $personalDomains, true)) {
    http_response_code(400); echo json_encode(['error' => 'Please use your work email, not a personal address.']); exit;
}
if (in_array($emailDomain, $disposableDomains, true)) {
    http_response_code(400); echo json_encode(['error' => 'Please use a real work email address.']); exit;
}

// ── 5c. Validate store URL ───────────────────────────────────────
if (mb_strlen($url) > 512) {
    http_response_code(400); echo json_encode(['error' => 'URL is too long.']); exit;
}
if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $url)) {
    http_response_code(400); echo json_encode(['error' => 'Please enter a valid store URL (https://yourstore.com).']); exit;
}
$host = parse_url($url, PHP_URL_HOST) ?? '';
if (!preg_match('/\.[a-z]{2,}$/i', $host)) {
    http_response_code(400); echo json_encode(['error' => 'Please enter a valid store URL with a real domain.']); exit;
}

// ── 5d. Validate dropdowns (whitelist — no arbitrary values reach DB) ──
$validRevenue  = ['Under ₹5L', '₹5L – ₹15L', '₹15L – ₹50L', '₹50L – ₹1Cr', '₹1Cr+'];
$validVisitors = ['Under 5K', '5K – 20K', '20K – 100K', '100K+'];
$validPlatform = ['Shopify', 'Shopify Plus', 'WooCommerce', 'Custom'];

if (!in_array($revenue, $validRevenue, true))   { http_response_code(400); echo json_encode(['error' => 'Please select your monthly revenue range.']);  exit; }
if (!in_array($visitors, $validVisitors, true)) { http_response_code(400); echo json_encode(['error' => 'Please select your monthly visitors range.']); exit; }
if (!in_array($platform, $validPlatform, true)) { http_response_code(400); echo json_encode(['error' => 'Please select your platform.']);               exit; }

// ── Skip DB if not configured (dev fallback) ─────────────────────
if (!defined('DB_NAME') || !DB_NAME) {
    echo json_encode(['success' => true]);
    exit;
}

// ── DB ───────────────────────────────────────────────────────────
try {
    $db = getDB();

    $db->exec('CREATE TABLE IF NOT EXISTS booking_leads (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        first_name     VARCHAR(50)   NOT NULL,
        last_name      VARCHAR(50)   NOT NULL,
        email          VARCHAR(255)  NOT NULL,
        url            VARCHAR(512)  DEFAULT NULL,
        revenue_range  VARCHAR(30)   DEFAULT NULL,
        visitors_range VARCHAR(20)   DEFAULT NULL,
        platform       VARCHAR(30)   DEFAULT NULL,
        ip             VARCHAR(64)   DEFAULT NULL,
        created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email   (email),
        INDEX idx_ip      (ip),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $ip = getClientIp();

    // ── 7. IP rate limit — max 5 submissions per IP per hour ─────
    $rateStmt = $db->prepare('SELECT COUNT(*) FROM booking_leads WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR');
    $rateStmt->execute([$ip]);
    if ((int) $rateStmt->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests. Please try again later.']);
        exit;
    }

    // ── 8. Duplicate guard — same email within 24 hours → silently succeed ──
    $dup = $db->prepare('SELECT id FROM booking_leads WHERE email = ? AND created_at > NOW() - INTERVAL 24 HOUR LIMIT 1');
    $dup->execute([$email]);
    if ($dup->fetch()) {
        echo json_encode(['success' => true]);
        exit;
    }

    $stmt = $db->prepare(
        'INSERT INTO booking_leads (first_name, last_name, email, url, revenue_range, visitors_range, platform, ip)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $url      ?: null,
        $revenue  ?: null,
        $visitors ?: null,
        $platform ?: null,
        $ip,
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('[OwlEye] Booking lead save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not save. Please try again.']);
}

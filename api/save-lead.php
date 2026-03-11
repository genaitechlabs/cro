<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/save-lead.php
   Saves a report lead (name + work email) for follow-up.
   POST { name, email, url, scan_token }
   Returns { success: true } or { error: "..." }
   ═══════════════════════════════════════════════════════════════ */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://genaitechlabs.com');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

$name      = trim($input['name']       ?? '');
$email     = strtolower(trim($input['email']      ?? ''));
$scanToken = trim($input['scan_token'] ?? '');
$url       = trim($input['url']        ?? '');

// ── Validate name ────────────────────────────────────────────────
if (!$name || mb_strlen($name) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter your full name.']);
    exit;
}

// ── Validate email format ────────────────────────────────────────
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please enter a valid email address.']);
    exit;
}

// ── Block personal email domains ─────────────────────────────────
$personalDomains = [
    'gmail.com', 'googlemail.com',
    'yahoo.com', 'yahoo.in', 'yahoo.co.in', 'ymail.com',
    'hotmail.com', 'hotmail.in', 'live.com', 'live.in', 'outlook.com', 'msn.com',
    'aol.com',
    'icloud.com', 'me.com', 'mac.com',
    'protonmail.com', 'pm.me',
    'rediffmail.com',
];
$emailDomain = substr(strrchr($email, '@'), 1);
if (in_array($emailDomain, $personalDomains, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please use your work email, not a personal address.']);
    exit;
}

// ── Skip DB if not configured (dev fallback) ─────────────────────
if (!defined('DB_NAME') || !DB_NAME) {
    echo json_encode(['success' => true]);
    exit;
}

// ── Save to DB ───────────────────────────────────────────────────
try {
    $db = getDB();

    // Create table on first use
    $db->exec('CREATE TABLE IF NOT EXISTS owleye_leads (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(255)  NOT NULL,
        email       VARCHAR(255)  NOT NULL,
        url         VARCHAR(512)  DEFAULT NULL,
        scan_token  VARCHAR(64)   DEFAULT NULL,
        ip          VARCHAR(64)   DEFAULT NULL,
        created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email   (email),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $stmt = $db->prepare(
        'INSERT INTO owleye_leads (name, email, url, scan_token, ip)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $email,
        $url       ?: null,
        $scanToken ?: null,
        getClientIp(),
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('[OwlEye] Lead save failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not save. Please try again.']);
}

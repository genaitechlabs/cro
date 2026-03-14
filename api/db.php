<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/db.php
   PDO singleton + IP helper.
   Requires DB_HOST / DB_NAME / DB_USER / DB_PASS in config.php.
   ═══════════════════════════════════════════════════════════════ */

function getDB(): PDO
{
    static $pdo = null;

    // Ping existing connection — reconnects if MySQL went away (e.g. after a long AI call)
    if ($pdo !== null) {
        try {
            $pdo->query('SELECT 1');
            return $pdo;
        } catch (\PDOException $e) {
            $pdo = null; // Stale — fall through to reconnect
        }
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/**
 * Returns the real client IP, Cloudflare-aware.
 */
function getClientIp(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return '0.0.0.0';
}

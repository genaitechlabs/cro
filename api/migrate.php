<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/migrate.php
   Run ONCE to create the scans table.
   Access: https://genaitechlabs.com/api/migrate.php?key=<MIGRATE_KEY>
   Then delete or restrict this file.
   ═══════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// Basic protection — set MIGRATE_KEY in config.php
if (!defined('MIGRATE_KEY') || empty($_GET['key']) || $_GET['key'] !== MIGRATE_KEY) {
    http_response_code(403);
    exit('Forbidden. Add ?key=<MIGRATE_KEY> to run this migration.');
}

$db = getDB();

$db->exec("
    CREATE TABLE IF NOT EXISTS owleye_scans (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        scan_token    CHAR(36)          NOT NULL,
        url           VARCHAR(2048)     NOT NULL,
        ip            VARCHAR(45)       NOT NULL,
        owleye_score  TINYINT UNSIGNED  NOT NULL,
        pillar_scores JSON              NOT NULL,
        param_scores  JSON              NOT NULL,
        created_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,

        UNIQUE KEY  uq_token   (scan_token),
        INDEX       idx_ip     (ip, created_at),
        INDEX       idx_url    (url(512)),
        INDEX       idx_date   (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "✅ owleye_scans table created (or already exists). You can now delete migrate.php.";

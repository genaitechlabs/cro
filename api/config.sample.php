<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/config.php
   Copy this file to config.php and fill in your keys.
   config.php is gitignored — never commit real keys.
   ═══════════════════════════════════════════════════════════════ */

// ── AI Provider ──────────────────────────────────────────────────
// To switch providers: change AI_PROVIDER + AI_MODEL. Nothing else.
define('AI_PROVIDER',    'openai');          // 'openai' | 'claude'
define('AI_MODEL',       'gpt-4o');          // 'gpt-4o' | 'claude-opus-4-5'

define('OPENAI_API_KEY', 'sk-...');          // ← your OpenAI key
define('CLAUDE_API_KEY', '');               // ← fill when switching to Claude

// ── Screenshot Provider ───────────────────────────────────────────
// Leave access key empty to run HTML-only analysis (no screenshots).
// Add key when ready — screenshots activate automatically.
define('SCREENSHOT_PROVIDER',      'screenshotone'); // 'screenshotone' | 'microlink'

define('SCREENSHOTONE_ACCESS_KEY', '');     // ← from screenshotone.com
define('MICROLINK_API_KEY',        '');     // ← fill when switching to Microlink

// ── PageSpeed Insights (Google) ───────────────────────────────────
// Free: 25,000 requests/day — https://developers.google.com/speed/docs/insights/v5/get-started
// Leave empty to skip real Lighthouse scoring (AI will estimate page_speed instead).
define('GOOGLE_PSI_KEY', '');              // ← your Google API key

// ── Database (MySQL) ──────────────────────────────────────────────
// Create a DB on Hostinger hPanel → Databases → MySQL Databases.
// Run api/migrate.php once to create the owleye_scans table.
define('DB_HOST', 'localhost');
define('DB_NAME', '');                     // ← your Hostinger DB name
define('DB_USER', '');                     // ← your Hostinger DB user
define('DB_PASS', '');                     // ← your Hostinger DB password

// ── Rate Limiting ─────────────────────────────────────────────────
// Scans allowed per IP address per rolling hour window.
// Set to 0 to disable rate limiting entirely.
define('RATE_LIMIT_PER_HOUR', 20);

// ── Migration Key ─────────────────────────────────────────────────
// Set any secret string, then visit: /api/migrate.php?key=<your-secret>
// Delete or leave empty after migration is done.
define('MIGRATE_KEY', 'change-me-before-running');

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

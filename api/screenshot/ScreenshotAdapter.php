<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/screenshot/ScreenshotAdapter.php
   Routes to the configured screenshot provider.
   Returns null if no API key is set — analysis runs HTML-only.
   ═══════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/screenshotone.php';
require_once __DIR__ . '/microlink.php';

class ScreenshotAdapter
{
    /**
     * Capture a screenshot of $url at the given viewport.
     *
     * @param string $url       The URL to screenshot
     * @param string $viewport  'desktop' (1280px) | 'mobile' (390px)
     * @return string|null      Base64-encoded JPEG, or null if unavailable
     */
    public static function capture(string $url, string $viewport = 'desktop'): ?string
    {
        switch (SCREENSHOT_PROVIDER) {
            case 'microlink':
                if (!MICROLINK_API_KEY) return null;
                return MicrolinkProvider::capture($url, $viewport);

            case 'screenshotone':
            default:
                if (!SCREENSHOTONE_ACCESS_KEY) return null;
                return ScreenshotOneProvider::capture($url, $viewport);
        }
    }
}

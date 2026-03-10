<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/screenshot/screenshotone.php
   ScreenshotOne provider (screenshotone.com).
   Free tier: 100 screenshots/month.
   Add SCREENSHOTONE_ACCESS_KEY to config.php to activate.
   ═══════════════════════════════════════════════════════════════ */

class ScreenshotOneProvider
{
    public static function capture(string $url, string $viewport = 'desktop'): ?string
    {
        $isMobile = ($viewport === 'mobile');

        $params = http_build_query([
            'access_key'           => SCREENSHOTONE_ACCESS_KEY,
            'url'                  => $url,
            'viewport_width'       => $isMobile ? 390  : 1280,
            'viewport_height'      => $isMobile ? 844  : 900,
            'device_scale_factor'  => $isMobile ? 2    : 1,
            'format'               => 'jpg',
            'image_quality'        => 75,
            'full_page'            => 'false',
            'delay'                => 2,          // wait for JS to render
            'block_ads'            => 'true',
            'block_cookie_banners' => 'true',     // cleaner screenshot
            'cache'                => 'true',     // reuse within 24h
            'cache_ttl'            => 86400,
        ]);

        $ch = curl_init('https://api.screenshotone.com/take?' . $params);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 22,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $imageData = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr   = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log('[OwlEye] ScreenshotOne cURL error: ' . $curlErr);
            return null;
        }

        if ($httpCode !== 200 || !$imageData) {
            error_log('[OwlEye] ScreenshotOne HTTP ' . $httpCode . ' for ' . $url);
            return null;
        }

        return base64_encode($imageData);
    }
}

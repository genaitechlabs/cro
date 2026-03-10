<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/screenshot/microlink.php
   Microlink provider — ready to activate.
   To switch: in config.php set
     define('SCREENSHOT_PROVIDER', 'microlink');
     define('MICROLINK_API_KEY',   'your-key');
   ═══════════════════════════════════════════════════════════════ */

class MicrolinkProvider
{
    public static function capture(string $url, string $viewport = 'desktop'): ?string
    {
        $isMobile = ($viewport === 'mobile');

        $params = http_build_query([
            'url'        => $url,
            'screenshot' => 'true',
            'meta'       => 'false',
            'viewport.width'  => $isMobile ? 390  : 1280,
            'viewport.height' => $isMobile ? 844  : 900,
            'waitFor'    => 2000,
        ]);

        $ch = curl_init('https://api.microlink.io/?' . $params);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . MICROLINK_API_KEY,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body    = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr || !$body) {
            error_log('[OwlEye] Microlink cURL error: ' . $curlErr);
            return null;
        }

        $data       = json_decode($body, true);
        $screenshotUrl = $data['data']['screenshot']['url'] ?? null;

        if (!$screenshotUrl) return null;

        // Fetch image and return as base64
        $img = @file_get_contents($screenshotUrl);
        return $img ? base64_encode($img) : null;
    }
}

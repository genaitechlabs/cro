<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/pagespeed/googlepsi.php
   Google PageSpeed Insights v5 — mobile Lighthouse score.
   Free tier: 25,000 requests/day.
   API key: https://developers.google.com/speed/docs/insights/v5/get-started
   ═══════════════════════════════════════════════════════════════ */

class GooglePsiProvider
{
    private static string $endpoint = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * Returns a 0–100 mobile Lighthouse performance score,
     * or null if the call fails or key is not set.
     */
    public static function score(string $url): ?int
    {
        $params = http_build_query([
            'url'      => $url,
            'strategy' => 'mobile',
            'key'      => GOOGLE_PSI_KEY,
            'category' => 'performance',
        ]);

        $ch = curl_init(self::$endpoint . '?' . $params);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body    = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr || !$body) {
            error_log('[OwlEye] Google PSI cURL error: ' . $curlErr);
            return null;
        }

        $data  = json_decode($body, true);
        $score = $data['lighthouseResult']['categories']['performance']['score'] ?? null;

        if ($score === null) {
            $msg = $data['error']['message'] ?? 'Unknown PSI error';
            error_log('[OwlEye] Google PSI error: ' . $msg);
            return null;
        }

        // PSI returns 0–1; convert to 0–100
        return max(0, min(100, (int) round($score * 100)));
    }
}

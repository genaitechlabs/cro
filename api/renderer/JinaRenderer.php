<?php
/* ═══════════════════════════════════════════════════════════════
   api/renderer/JinaRenderer.php
   Headless rendering via Jina AI Reader (r.jina.ai).

   Requires in config.php:
     define('JINA_API_KEY', 'jina_...');

   Token budget: 70,000 — covers all signal-relevant content on
   even the largest Shopify homepages (signals appear in first
   ~14k tokens; 70k gives a safe 5× margin).

   Returns X-Return-Format: html so all existing signal detection
   (script src, JSON-LD, href links, CSS classes) works unchanged.
   ═══════════════════════════════════════════════════════════════ */

class JinaRenderer implements RendererInterface
{
    private const ENDPOINT     = 'https://r.jina.ai/';
    private const TOKEN_BUDGET = 70000;
    private const TIMEOUT      = 25; // seconds — Jina renders JS, needs more time than curl

    public function fetch(string $url): string
    {
        $apiKey = defined('JINA_API_KEY') ? JINA_API_KEY : '';
        if (!$apiKey) {
            error_log('[OwlEye] JinaRenderer: JINA_API_KEY not set in config.php');
            return '';
        }

        $ch = curl_init(self::ENDPOINT . ltrim($url, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
                'X-Return-Format: html',
                'X-Timeout: 20',
                'X-Token-Budget: ' . self::TOKEN_BUDGET,
                'X-With-Links-Summary: true',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log('[OwlEye] JinaRenderer curl error: ' . $curlErr);
            return '';
        }

        if ($httpCode !== 200) {
            error_log('[OwlEye] JinaRenderer HTTP ' . $httpCode . ' for ' . $url . ' — ' . substr($response, 0, 200));
            return '';
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[OwlEye] JinaRenderer JSON decode error: ' . json_last_error_msg());
            return '';
        }

        // Extract HTML from response (X-Return-Format: html → data.html)
        $html = $data['data']['html'] ?? '';
        if (!$html) {
            error_log('[OwlEye] JinaRenderer: empty html in response for ' . $url);
            return '';
        }

        return $html;
    }
}

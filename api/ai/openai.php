<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/ai/openai.php
   OpenAI (GPT-4o) provider implementation.
   Supports vision (screenshots) when available, falls back to
   HTML-only analysis gracefully.
   ═══════════════════════════════════════════════════════════════ */

class OpenAIProvider
{
    private static string $endpoint = 'https://api.openai.com/v1/chat/completions';

    // Must match OWLEYE_PILLARS flatMap order in owleye-ai.js (13 params × 6 pillars)
    private static array $PARAMS = [
        'checkout_flow', 'payment_options', 'cart_recovery',   // Purchase Flow
        'landing_page',  'product_pages',                       // Page Experience
        'trust_signals', 'returns_policy',                      // Trust & Conversion
        'cross_sell',                                           // Engagement & Retention
        'schema_markup', 'content_clarity',                     // Agentic Commerce
        'ai_discoverability', 'conversational_ux',              // Agentic Commerce
        'mobile_ux',                                            // Technical Foundation
    ];

    public static function analyse(string $url, string $html, ?string $desktop, ?string $mobile): array
    {
        $messages = self::buildMessages($url, $html, $desktop, $mobile);

        $payload = [
            'model'           => AI_MODEL,
            'messages'        => $messages,
            'max_tokens'      => 300,
            'temperature'     => 0.1,
            'response_format' => ['type' => 'json_object'],
        ];

        $raw = self::callApi($payload);
        return self::parseScores($raw);
    }

    // ── Build prompt messages ────────────────────────────────────

    private static function buildMessages(string $url, string $html, ?string $desktop, ?string $mobile): array
    {
        $system = <<<SYS
You are an expert CRO (Conversion Rate Optimisation) analyst specialising in Indian ecommerce.
You score websites on 9 conversion parameters from 0 to 100.
Always respond with valid JSON only — no explanation, no markdown, no code fences.
SYS;

        $criteria = <<<CRIT
Score each parameter 0–100 based on what you observe:

PURCHASE FLOW
- checkout_flow      : Steps to buy, progress indicators, form length, guest checkout
- payment_options    : UPI, COD, cards, BNPL (Razorpay/PayU/Simpl/LazyPay) presence
- cart_recovery      : Exit-intent, cart persistence, recovery messaging

PAGE EXPERIENCE
- landing_page       : Above-fold clarity, benefit headline, CTA prominence & placement
- product_pages      : Image count/quality, reviews visibility, add-to-cart prominence

TRUST & CONVERSION
- trust_signals      : SSL, trust badges, review count, security logos near buy button
- returns_policy     : Policy visibility, plain language, placement near CTA

ENGAGEMENT & RETENTION
- cross_sell         : Upsell/cross-sell modules, "frequently bought together", bundles

AGENTIC COMMERCE (how well AI agents can read and rank this store)
- schema_markup      : Schema.org Product/Review/FAQ/BreadcrumbList structured data present in HTML
- content_clarity    : Plain language copy, scannable headings, clear product descriptions for LLM parsing
- ai_discoverability : Meta descriptions, OG tags, semantic HTML hierarchy, AI search signal quality
- conversational_ux  : FAQ sections, chatbot/assistant presence, Q&A format content depth

TECHNICAL FOUNDATION
- mobile_ux          : Mobile layout, tap target sizes, mobile-optimised checkout
CRIT;

        $jsonTemplate = '{"checkout_flow":0,"payment_options":0,"cart_recovery":0,"landing_page":0,"product_pages":0,"trust_signals":0,"returns_policy":0,"cross_sell":0,"schema_markup":0,"content_clarity":0,"ai_discoverability":0,"conversational_ux":0,"mobile_ux":0}';

        $hasScreenshots = ($desktop !== null || $mobile !== null);

        if ($hasScreenshots) {
            // Vision request — include screenshots as images
            $screenshotNote = '';
            if ($desktop && $mobile) $screenshotNote = 'I have provided both a desktop (1280px) and mobile (390px) screenshot.';
            elseif ($desktop)        $screenshotNote = 'I have provided a desktop (1280px) screenshot.';
            else                     $screenshotNote = 'I have provided a mobile (390px) screenshot.';

            $textContent = "Analyse this Indian ecommerce website: {$url}\n\n"
                . "{$criteria}\n\n"
                . "{$screenshotNote} I have also provided the page HTML below.\n\n"
                . "HTML (truncated):\n```\n{$html}\n```\n\n"
                . "Respond with ONLY this JSON structure (fill in real scores):\n{$jsonTemplate}";

            $userContent = [
                ['type' => 'text', 'text' => $textContent],
            ];

            if ($desktop !== null) {
                $userContent[] = [
                    'type'      => 'image_url',
                    'image_url' => ['url' => 'data:image/jpeg;base64,' . $desktop, 'detail' => 'low'],
                ];
            }
            if ($mobile !== null) {
                $userContent[] = [
                    'type'      => 'image_url',
                    'image_url' => ['url' => 'data:image/jpeg;base64,' . $mobile, 'detail' => 'low'],
                ];
            }

            return [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $userContent],
            ];

        } else {
            // HTML-only (no screenshot key configured yet)
            $text = "Analyse this Indian ecommerce website based on its HTML: {$url}\n\n"
                . "{$criteria}\n\n"
                . "HTML (truncated):\n```\n{$html}\n```\n\n"
                . "Respond with ONLY this JSON structure (fill in real scores):\n{$jsonTemplate}";

            return [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $text],
            ];
        }
    }

    // ── Call OpenAI API ──────────────────────────────────────────

    private static function callApi(array $payload): string
    {
        $ch = curl_init(self::$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENAI_API_KEY,
            ],
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result   = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log('[OwlEye] OpenAI cURL error: ' . $curlErr);
            return '';
        }

        return $result;
    }

    // ── Parse + sanitise scores ──────────────────────────────────

    private static function parseScores(string $response): array
    {
        $fallback = array_fill_keys(self::$PARAMS, 50);

        if (!$response) {
            return ['error' => 'No response from AI', 'scores' => $fallback];
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            $msg = $data['error']['message'] ?? 'OpenAI returned an error';
            error_log('[OwlEye] OpenAI API error: ' . $msg);
            return ['error' => $msg, 'scores' => $fallback];
        }

        $content = $data['choices'][0]['message']['content'] ?? '{}';
        $raw     = json_decode($content, true) ?? [];

        // Clamp every score to 0–100; default missing params to 50
        $scores = [];
        foreach (self::$PARAMS as $p) {
            $scores[$p] = isset($raw[$p])
                ? max(0, min(100, (int) round($raw[$p])))
                : 50;
        }

        return ['scores' => $scores];
    }
}

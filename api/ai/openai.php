<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/ai/openai.php
   OpenAI (GPT-4o) provider — 28 parameters × 6 pillars.
   Supports vision (screenshots) when available, falls back to
   HTML-only analysis gracefully.
   ═══════════════════════════════════════════════════════════════ */

class OpenAIProvider
{
    private static string $endpoint = 'https://api.openai.com/v1/chat/completions';

    // Must match OWLEYE_PILLARS flatMap order in owleye-ai.js (28 params × 6 pillars)
    private static array $PARAMS = [
        'checkout_flow', 'payment_options', 'cart_recovery',       // Purchase Flow
        'express_checkout', 'cod_prominence',                        // Purchase Flow
        'landing_page',  'product_pages', 'search_ux',              // Page Experience
        'sticky_atc', 'category_pages',                              // Page Experience
        'trust_signals', 'returns_policy', 'social_proof',          // Trust & Conversion
        'review_quality', 'guarantee_signals',                       // Trust & Conversion
        'cross_sell', 'email_capture', 'whatsapp_marketing',        // Engagement & Retention
        'schema_markup', 'content_clarity',                          // Agentic Commerce
        'ai_discoverability', 'conversational_ux',                   // Agentic Commerce
        'open_graph_quality', 'canonical_health',                    // Agentic Commerce
        'mobile_ux', 'page_speed',                                   // Technical Foundation
        'navigation_clarity', 'accessibility',                       // Technical Foundation
    ];

    public static function analyse(string $url, string $html, ?string $desktop, ?string $mobile): array
    {
        $messages = self::buildMessages($url, $html, $desktop, $mobile);

        $payload = [
            'model'           => AI_MODEL,
            'messages'        => $messages,
            'max_tokens'      => 400,
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
You score websites on 28 conversion parameters from 0 to 100.
Always respond with valid JSON only — no explanation, no markdown, no code fences.
SYS;

        $criteria = <<<CRIT
Score each parameter 0–100 based on what you observe:

PURCHASE FLOW
- checkout_flow      : Steps to buy, progress indicators, form length, guest checkout
- payment_options    : UPI, COD, cards, BNPL (Razorpay/PayU/Simpl/LazyPay) presence
- cart_recovery      : Exit-intent, cart persistence, recovery messaging
- express_checkout   : One-click/instant buy, Google Pay, PhonePe express options
- cod_prominence     : Cash on Delivery visibility, placement in checkout hierarchy

PAGE EXPERIENCE
- landing_page       : Above-fold clarity, benefit headline, CTA prominence & placement
- product_pages      : Image count/quality, reviews visibility, add-to-cart prominence
- search_ux          : Autocomplete, typo tolerance, search result relevance
- sticky_atc         : Sticky add-to-cart bar on mobile scroll, persistence of buy button
- category_pages     : Filter/sort options, product card quality, listing page layout

TRUST & CONVERSION
- trust_signals      : SSL, trust badges, review count, security logos near buy button
- returns_policy     : Policy visibility, plain language, placement near CTA
- social_proof       : Review volume, photo/video reviews, UGC presence, placement
- review_quality     : Review depth, recency, rating distribution, verified buyer badges
- guarantee_signals  : Money-back guarantee, warranty, risk-reversal copy near CTA

ENGAGEMENT & RETENTION
- cross_sell         : Upsell/cross-sell modules, "frequently bought together", bundles
- email_capture      : Email popup, exit-intent capture, lead magnet quality
- whatsapp_marketing : WhatsApp opt-in, abandoned cart via WhatsApp, order tracking

AGENTIC COMMERCE (how well AI agents can read and rank this store)
- schema_markup      : Schema.org Product/Review/FAQ/BreadcrumbList structured data
- content_clarity    : Plain language copy, scannable headings for LLM parsing
- ai_discoverability : Meta descriptions, OG tags, semantic HTML hierarchy
- conversational_ux  : FAQ sections, chatbot presence, Q&A content depth
- open_graph_quality : og:title, og:description, og:image presence and quality
- canonical_health   : Canonical tags on product/category pages, URL de-duplication

TECHNICAL FOUNDATION
- mobile_ux          : Mobile layout, tap target sizes, mobile-optimised checkout
- page_speed         : Estimated Lighthouse mobile score (0–100), Core Web Vitals signals
- navigation_clarity : Top-level menu structure, category hierarchy, discoverability
- accessibility      : Alt text, colour contrast, ARIA labels, keyboard navigation
CRIT;

        $jsonTemplate = '{"checkout_flow":0,"payment_options":0,"cart_recovery":0,"express_checkout":0,"cod_prominence":0,"landing_page":0,"product_pages":0,"search_ux":0,"sticky_atc":0,"category_pages":0,"trust_signals":0,"returns_policy":0,"social_proof":0,"review_quality":0,"guarantee_signals":0,"cross_sell":0,"email_capture":0,"whatsapp_marketing":0,"schema_markup":0,"content_clarity":0,"ai_discoverability":0,"conversational_ux":0,"open_graph_quality":0,"canonical_health":0,"mobile_ux":0,"page_speed":0,"navigation_clarity":0,"accessibility":0}';

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

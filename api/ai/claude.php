<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/ai/claude.php
   Claude (Anthropic) provider — 28 parameters × 6 pillars.

   To switch: in config.php set
     define('AI_PROVIDER', 'claude');
     define('AI_MODEL',    'claude-opus-4-5');
     define('CLAUDE_API_KEY', 'sk-ant-...');
   ═══════════════════════════════════════════════════════════════ */

class ClaudeProvider
{
    private static string $endpoint = 'https://api.anthropic.com/v1/messages';
    private static string $version  = '2023-06-01';

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
            'model'      => AI_MODEL,
            'max_tokens' => 400,
            'system'     => self::systemPrompt(),
            'messages'   => $messages,
        ];

        $raw = self::callApi($payload);
        return self::parseScores($raw);
    }

    private static function systemPrompt(): string
    {
        return 'You are an expert CRO analyst specialising in Indian ecommerce. '
             . 'Score websites on 28 conversion parameters across 6 pillars from 0 to 100. '
             . 'Always respond with valid JSON only — no explanation, no markdown.';
    }

    private static function buildMessages(string $url, string $html, ?string $desktop, ?string $mobile): array
    {
        $criteria = <<<CRIT
Score each parameter 0–100:

PURCHASE FLOW
- checkout_flow      : Steps to buy, progress indicators, form length, guest checkout
- payment_options    : UPI, COD, cards, BNPL (Razorpay/PayU/Simpl/LazyPay)
- cart_recovery      : Exit-intent, cart persistence, recovery messaging
- express_checkout   : One-click/instant buy, Google Pay, PhonePe express options
- cod_prominence     : Cash on Delivery visibility, placement in checkout hierarchy

PAGE EXPERIENCE
- landing_page       : Above-fold clarity, benefit headline, CTA prominence
- product_pages      : Image count/quality, reviews, add-to-cart prominence
- search_ux          : Autocomplete, typo tolerance, search result relevance
- sticky_atc         : Sticky add-to-cart bar on mobile scroll
- category_pages     : Filter/sort options, product card quality, listing layout

TRUST & CONVERSION
- trust_signals      : SSL, trust badges, review count, security logos
- returns_policy     : Policy visibility, plain language, placement near CTA
- social_proof       : Review volume, photo/video reviews, UGC presence
- review_quality     : Review depth, recency, rating distribution, verified buyer badges
- guarantee_signals  : Money-back guarantee, warranty, risk-reversal copy near CTA

ENGAGEMENT & RETENTION
- cross_sell         : Upsell/cross-sell modules, bundles, related products
- email_capture      : Email popup, exit-intent capture, lead magnet quality
- whatsapp_marketing : WhatsApp opt-in, abandoned cart, order tracking via WhatsApp

AGENTIC COMMERCE (how well AI agents can read and rank this store)
- schema_markup      : Schema.org Product/Review/FAQ structured data in HTML
- content_clarity    : Plain language copy, scannable headings for LLM parsing
- ai_discoverability : Meta descriptions, OG tags, semantic HTML hierarchy
- conversational_ux  : FAQ sections, chatbot presence, Q&A content depth
- open_graph_quality : og:title, og:description, og:image presence and quality
- canonical_health   : Canonical tags on product/category pages, URL de-duplication

TECHNICAL FOUNDATION
- mobile_ux          : Mobile layout, tap targets, mobile checkout flow
- page_speed         : Estimated Lighthouse mobile score (0–100), Core Web Vitals signals
- navigation_clarity : Top-level menu structure, category hierarchy, discoverability
- accessibility      : Alt text, colour contrast, ARIA labels, keyboard navigation

URL: {$url}
HTML:
```
{$html}
```

Respond ONLY with:
{"checkout_flow":0,"payment_options":0,"cart_recovery":0,"express_checkout":0,"cod_prominence":0,"landing_page":0,"product_pages":0,"search_ux":0,"sticky_atc":0,"category_pages":0,"trust_signals":0,"returns_policy":0,"social_proof":0,"review_quality":0,"guarantee_signals":0,"cross_sell":0,"email_capture":0,"whatsapp_marketing":0,"schema_markup":0,"content_clarity":0,"ai_discoverability":0,"conversational_ux":0,"open_graph_quality":0,"canonical_health":0,"mobile_ux":0,"page_speed":0,"navigation_clarity":0,"accessibility":0}
CRIT;

        $content = [['type' => 'text', 'text' => $criteria]];

        // Claude vision: images come before text in the content array
        if ($desktop !== null) {
            array_unshift($content, [
                'type'   => 'image',
                'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $desktop],
            ]);
        }
        if ($mobile !== null) {
            array_unshift($content, [
                'type'   => 'image',
                'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $mobile],
            ]);
        }

        return [['role' => 'user', 'content' => $content]];
    }

    private static function callApi(array $payload): string
    {
        $ch = curl_init(self::$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: '         . CLAUDE_API_KEY,
                'anthropic-version: ' . self::$version,
            ],
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result  = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log('[OwlEye] Claude cURL error: ' . $curlErr);
            return '';
        }

        return $result;
    }

    private static function parseScores(string $response): array
    {
        $fallback = array_fill_keys(self::$PARAMS, 50);

        if (!$response) return ['error' => 'No response from Claude', 'scores' => $fallback];

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            $msg = $data['error']['message'] ?? 'Claude returned an error';
            error_log('[OwlEye] Claude error: ' . $msg);
            return ['error' => $msg, 'scores' => $fallback];
        }

        // Claude returns content as array of blocks
        $content = $data['content'][0]['text'] ?? '{}';

        // Strip any accidental markdown fences
        $content = preg_replace('/```[a-z]*\n?|\n?```/', '', $content);

        $raw    = json_decode(trim($content), true) ?? [];
        $scores = [];
        foreach (self::$PARAMS as $p) {
            $scores[$p] = isset($raw[$p])
                ? max(0, min(100, (int) round($raw[$p])))
                : 50;
        }

        return ['scores' => $scores];
    }
}

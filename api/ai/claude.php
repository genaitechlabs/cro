<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/ai/claude.php
   Claude (Anthropic) provider — ready to activate.

   To switch: in config.php set
     define('AI_PROVIDER', 'claude');
     define('AI_MODEL',    'claude-opus-4-5');
     define('CLAUDE_API_KEY', 'sk-ant-...');
   ═══════════════════════════════════════════════════════════════ */

class ClaudeProvider
{
    private static string $endpoint = 'https://api.anthropic.com/v1/messages';
    private static string $version  = '2023-06-01';

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
            'model'      => AI_MODEL,
            'max_tokens' => 300,
            'system'     => self::systemPrompt(),
            'messages'   => $messages,
        ];

        $raw = self::callApi($payload);
        return self::parseScores($raw);
    }

    private static function systemPrompt(): string
    {
        return 'You are an expert CRO analyst specialising in Indian ecommerce. '
             . 'Score websites on 13 conversion parameters across 6 pillars from 0 to 100. '
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

PAGE EXPERIENCE
- landing_page       : Above-fold clarity, benefit headline, CTA prominence
- product_pages      : Image count/quality, reviews, add-to-cart prominence

TRUST & CONVERSION
- trust_signals      : SSL, trust badges, review count, security logos
- returns_policy     : Policy visibility, plain language, placement near CTA

ENGAGEMENT & RETENTION
- cross_sell         : Upsell/cross-sell modules, bundles, related products

AGENTIC COMMERCE (how well AI agents can read and rank this store)
- schema_markup      : Schema.org Product/Review/FAQ structured data in HTML
- content_clarity    : Plain language copy, scannable headings for LLM parsing
- ai_discoverability : Meta descriptions, OG tags, semantic HTML hierarchy
- conversational_ux  : FAQ sections, chatbot presence, Q&A content depth

TECHNICAL FOUNDATION
- mobile_ux          : Mobile layout, tap targets, mobile checkout flow

URL: {$url}
HTML:
```
{$html}
```

Respond ONLY with:
{"checkout_flow":0,"payment_options":0,"cart_recovery":0,"landing_page":0,"product_pages":0,"trust_signals":0,"returns_policy":0,"cross_sell":0,"schema_markup":0,"content_clarity":0,"ai_discoverability":0,"conversational_ux":0,"mobile_ux":0}
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

<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/ai/openai.php
   OpenAI (GPT-4o) provider — 28 parameters × 6 pillars.
   Receives multi-page HTML (home, product, category, cart).
   ═══════════════════════════════════════════════════════════════ */

class OpenAIProvider
{
    private static string $endpoint = 'https://api.openai.com/v1/chat/completions';

    // Must match OWLEYE_PILLARS flatMap order in owleye-ai.js
    private static array $PARAMS = [
        'checkout_flow', 'payment_options', 'cart_recovery',
        'express_checkout', 'cod_prominence',
        'landing_page',  'product_pages', 'search_ux',
        'sticky_atc', 'category_pages',
        'trust_signals', 'returns_policy', 'social_proof',
        'review_quality', 'guarantee_signals',
        'cross_sell', 'email_capture', 'whatsapp_marketing',
        'schema_markup', 'content_clarity',
        'ai_discoverability', 'conversational_ux',
        'open_graph_quality', 'canonical_health',
        'mobile_ux', 'page_speed',
        'navigation_clarity', 'accessibility',
    ];

    public static function analyse(string $url, array $pages, ?string $desktop, ?string $mobile): array
    {
        $messages = self::buildMessages($url, $pages, $desktop, $mobile);

        $payload = [
            'model'           => AI_MODEL,
            'messages'        => $messages,
            'max_tokens'      => 600,
            'temperature'     => 0.1,
            'response_format' => ['type' => 'json_object'],
        ];

        $raw = self::callApi($payload);
        return self::parseScores($raw);
    }

    private static function buildMessages(string $url, array $pages, ?string $desktop, ?string $mobile): array
    {
        $system = self::systemPrompt();
        $userText = self::buildUserPrompt($url, $pages);

        $userContent = [['type' => 'text', 'text' => $userText]];

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
    }

    private static function systemPrompt(): string
    {
        return <<<SYS
You are a senior CRO analyst benchmarking Indian ecommerce stores against industry standards.
You will receive HTML crawled from multiple pages of the same store — each section is clearly labelled.

Score each of the 28 parameters from 0 to 100 using this exact rubric:
  0–20  → Feature absent or critically broken
  21–40 → Present but poorly implemented — ALSO USE 40 when a parameter CANNOT be verified from the provided pages
  41–60 → Meets basic standard; average Indian ecommerce performance
  61–80 → Good implementation; above Indian D2C average
  81–100 → Best practice; top 10% of Indian ecommerce

CRITICAL RULES:
1. Score exactly 40 for any parameter marked [UNVERIFIABLE] or whose required page was not provided or is empty.
2. Indian ecommerce standards apply: COD is an expected feature (not a bonus), UPI is the dominant payment method, WhatsApp marketing is standard practice for Indian D2C.
3. Benchmark against Indian D2C stores — not Western ecommerce platforms.
4. Respond with valid JSON only — no explanation, no markdown, no code fences.

SCORE CALIBRATION ANCHORS:
• Top Indian store (Mamaearth/Nykaa-tier): checkout_flow≈78, payment_options≈85, trust_signals≈75, product_pages≈80
• Average Indian D2C store: checkout_flow≈48, payment_options≈58, trust_signals≈45, product_pages≈52
• Poor performer: checkout_flow≈22, payment_options≈30, trust_signals≈18, product_pages≈25
SYS;
    }

    private static function buildUserPrompt(string $url, array $pages): string
    {
        // ── Page HTML blocks ──────────────────────────────────────
        $labels = [
            'home'     => 'HOME PAGE',
            'product'  => 'PRODUCT PAGE',
            'category' => 'CATEGORY / COLLECTION PAGE',
            'cart'     => 'CART PAGE',
            'returns'  => 'RETURNS / REFUND POLICY PAGE',
        ];

        $pagesBlock = '';
        foreach ($labels as $type => $label) {
            if (!empty($pages[$type])) {
                $pagesBlock .= "\n=== {$label} ({$url}) ===\n{$pages[$type]}\n";
            } else {
                $pagesBlock .= "\n=== {$label} ===\n[Page not accessible — score {$label}-dependent parameters at 40]\n";
            }
        }

        // ── Criteria with page-context tags ───────────────────────
        $criteria = <<<CRIT
Score each parameter using only evidence from the labelled page sections above:

PURCHASE FLOW — draw evidence from CART PAGE
- checkout_flow      [CART PAGE]: Steps to complete purchase, progress bar, guest checkout option, form length
- payment_options    [CART PAGE]: UPI (GPay/PhonePe/Paytm), COD, cards, BNPL (Razorpay/Simpl/LazyPay) visibility
- cart_recovery      [UNVERIFIABLE — score 40]: Requires active session; exit-intent/email recovery cannot be observed
- express_checkout   [CART PAGE]: One-click buy, Google Pay express, PhonePe instant checkout option
- cod_prominence     [CART PAGE]: COD badge/label visibility, position in payment method hierarchy

PAGE EXPERIENCE
- landing_page       [HOME PAGE]: Above-fold clarity, hero headline quality, primary CTA placement & copy
- product_pages      [PRODUCT PAGE]: Image count/quality, reviews placement, ATC button prominence, product video
- search_ux          [CATEGORY PAGE]: Search bar, autocomplete quality, typo tolerance, filter/sort controls
- sticky_atc         [PRODUCT PAGE]: Sticky add-to-cart bar visible while scrolling, mobile-optimised persistence
- category_pages     [CATEGORY PAGE]: Filter/sort options, product grid quality, listing layout, pagination

TRUST & CONVERSION
- trust_signals      [HOME PAGE]: SSL indicator, trust badges, review count, security logos near CTA
- returns_policy     [RETURNS / REFUND POLICY PAGE]: Score based on actual policy content —
    81–100: 100% refund (money back to original payment) with simple process and clear timeline (7–15 days); easy cancellation before dispatch with full refund
    61–80:  Refund with reasonable conditions (unused item, original packaging, within stated timeframe); cancellation allowed with minor restrictions
    41–60:  Store credit / exchange only; OR conditions present but many exceptions or unclear process; cancellation with partial penalty
    21–40:  Very restrictive — refund case-by-case, no clear timeline, complex or undisclosed conditions; cancellation not clearly allowed
    0–20:   No refund policy stated, all sales final, or page not found
    If page is missing, score 40.
- social_proof       [PRODUCT PAGE]: Review volume, photo/video UGC, rating display, review recency
- review_quality     [PRODUCT PAGE]: Review depth, rating distribution, verified buyer badges, brand responses
- guarantee_signals  [PRODUCT PAGE]: Money-back guarantee, warranty, risk-reversal copy near buy button

ENGAGEMENT & RETENTION
- cross_sell         [PRODUCT PAGE]: Related products, "frequently bought together" modules, bundle offers
- email_capture      [HOME PAGE]: Email popup, exit-intent capture, lead magnet quality, newsletter signup
- whatsapp_marketing [HOME PAGE]: WhatsApp opt-in widget, chat button, marketing touchpoint visibility

AGENTIC COMMERCE — how well AI agents can read and rank this store
- schema_markup      [HOME PAGE]: JSON-LD Product/Review/FAQ/BreadcrumbList structured data present in HTML
- content_clarity    [HOME PAGE]: Plain-language copy, scannable headings, LLM-parseable structure
- ai_discoverability [HOME PAGE]: Meta descriptions, semantic HTML hierarchy, heading tag structure
- conversational_ux  [HOME PAGE]: FAQ section depth, chatbot presence, structured Q&A content
- open_graph_quality [HOME PAGE]: og:title, og:description, og:image presence and quality
- canonical_health   [HOME PAGE]: Canonical tags present, URL structure clarity, no obvious duplication

TECHNICAL FOUNDATION
- mobile_ux          [HOME PAGE]: Viewport meta, mobile layout signals, tap target size indicators
- page_speed         [PSI API — already injected if available, otherwise estimate from HTML signals]
- navigation_clarity [HOME PAGE]: Top-level menu structure, category hierarchy, mega-menu quality
- accessibility      [HOME PAGE]: Alt text on images, colour contrast signals, ARIA labels, semantic HTML
CRIT;

        $jsonTemplate = '{"checkout_flow":0,"payment_options":0,"cart_recovery":0,"express_checkout":0,"cod_prominence":0,"landing_page":0,"product_pages":0,"search_ux":0,"sticky_atc":0,"category_pages":0,"trust_signals":0,"returns_policy":0,"social_proof":0,"review_quality":0,"guarantee_signals":0,"cross_sell":0,"email_capture":0,"whatsapp_marketing":0,"schema_markup":0,"content_clarity":0,"ai_discoverability":0,"conversational_ux":0,"open_graph_quality":0,"canonical_health":0,"mobile_ux":0,"page_speed":0,"navigation_clarity":0,"accessibility":0}';

        return "Analyse this Indian ecommerce store: {$url}\n"
             . $pagesBlock . "\n"
             . $criteria . "\n\n"
             . "Respond with ONLY this JSON structure (fill in real scores):\n"
             . $jsonTemplate;
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
                'Authorization: Bearer ' . OPENAI_API_KEY,
            ],
            CURLOPT_TIMEOUT        => 50,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result  = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log('[OwlEye] OpenAI cURL error: ' . $curlErr);
            return '';
        }
        return $result;
    }

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

        $scores = [];
        foreach (self::$PARAMS as $p) {
            $scores[$p] = isset($raw[$p])
                ? max(0, min(100, (int) round($raw[$p])))
                : 50;
        }

        return ['scores' => $scores];
    }
}

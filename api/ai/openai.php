<?php
/* ═══════════════════════════════════════════════════════════════
   OWLEYE — api/ai/openai.php
   OpenAI (GPT-4o) provider — 28 parameters × 6 pillars.
   Receives multi-page HTML (home, product, category, cart).
   ═══════════════════════════════════════════════════════════════ */

class OpenAIProvider
{
    private static string $endpoint = 'https://api.openai.com/v1/chat/completions';

    // Must match OWLEYE_PILLARS flatMap order in owleye-ai.js AND PARAM_ORDER in main.js
    // Order: User Experience → Engagement & Retention → Trust & Conversion →
    //        Purchase Flow → Agentic Commerce → Technical Foundation
    private static array $PARAMS = [
        'landing_page', 'product_pages', 'search_ux',          // User Experience
        'sticky_atc', 'category_pages',                         // User Experience
        'cross_sell', 'email_capture', 'whatsapp_marketing',    // Engagement & Retention
        'trust_signals', 'returns_policy', 'social_proof',      // Trust & Conversion
        'review_quality', 'guarantee_signals',                   // Trust & Conversion
        'checkout_flow', 'payment_options', 'cart_recovery',    // Purchase Flow
        'express_checkout', 'cod_prominence',                    // Purchase Flow
        'schema_markup', 'content_clarity',                      // Agentic Commerce
        'ai_discoverability', 'conversational_ux',               // Agentic Commerce
        'open_graph_quality', 'canonical_health',                // Agentic Commerce
        'mobile_ux', 'page_speed',                               // Technical Foundation
        'navigation_clarity', 'accessibility',                   // Technical Foundation
    ];

    public static function analyse(string $url, array $pages, ?string $desktop, ?string $mobile): array
    {
        $messages = self::buildMessages($url, $pages, $desktop, $mobile);

        $payload = [
            'model'           => AI_MODEL,
            'messages'        => $messages,
            'max_tokens'      => 600,
            'temperature'     => 0.3,
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

        $criteria = <<<CRIT
Score each parameter using only evidence from the labelled page sections above:

NOTE: The HOME PAGE section may contain a [PURCHASE_SIGNALS] line — use it as confirmed hardware evidence for Purchase Flow parameters.

PURCHASE FLOW — draw evidence from CART PAGE and [PURCHASE_SIGNALS]
- checkout_flow      [CART PAGE]: Steps to complete purchase, progress bar, guest checkout option, form length
                     No cart page available → use [PURCHASE_SIGNALS] clues; score 40 if no signals
- payment_options    [CART PAGE]: UPI (GPay/PhonePe/Paytm), COD, cards, BNPL (Razorpay/Simpl/LazyPay) visibility
                     [PURCHASE_SIGNALS] upi_available=true  → UPI payment confirmed on page → score 70+
                     [PURCHASE_SIGNALS] cod_available=true  → COD confirmed on page → score 68+ (Indians expect COD)
                     [PURCHASE_SIGNALS] bnpl_available=true → BNPL option confirmed → score 78+ (adds 8–10 pts)
                     [PURCHASE_SIGNALS] payment_gateway=detected → Razorpay/Cashfree confirms payment infra → score 65+
                     Multiple signals stack: upi+cod+bnpl → score 82+
- cart_recovery      [PURCHASE_SIGNALS] email_crm={tool} → CRM/email automation confirmed → infer abandoned cart flows exist → score 62+
                     No signal → score 40 (cannot verify from crawl)
- express_checkout   [CART PAGE]: One-click buy, Google Pay express, PhonePe instant checkout option
                     [PURCHASE_SIGNALS] express_checkout_signal=true → Magic Checkout / dynamic-checkout confirmed → score 72+
                     [PURCHASE_SIGNALS] upi_available=true → PhonePe/GPay present → likely express capable → score 60+
                     No signals → score 40
- cod_prominence     [CART PAGE]: COD badge/label visibility, position in payment method hierarchy
                     [PURCHASE_SIGNALS] cod_available=true → COD confirmed in page HTML → score 65+
                     No signal and no cart page → score 40

USER EXPERIENCE
- landing_page       [HOME PAGE]: Above-fold clarity, hero headline quality, primary CTA placement & copy
- product_pages      [PRODUCT PAGE]: Image count/quality, reviews placement, ATC button prominence, product video
- search_ux          [CATEGORY PAGE]: Search bar, autocomplete quality, typo tolerance, filter/sort controls
- sticky_atc         [PRODUCT PAGE]: Sticky add-to-cart bar visible while scrolling, mobile-optimised persistence
                     [PURCHASE_SIGNALS] sticky_atc_signal=true → sticky/fixed CSS confirmed near ATC → score 68+
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
- social_proof       [PRODUCT PAGE or HOME PAGE]: Review volume, photo/video UGC, testimonials, rating display, review recency.
                     Indian stores often display UGC video testimonials, trust counts ("1 lakh+ customers"), and press mentions on the homepage — score these too.
- review_quality     [PRODUCT PAGE]: Review depth, rating distribution, verified buyer badges, brand responses
- guarantee_signals  [PRODUCT PAGE]: Money-back guarantee, warranty, risk-reversal copy near buy button

ENGAGEMENT & RETENTION
- cross_sell         [PRODUCT PAGE]: Related products, "frequently bought together" modules, bundle offers
- email_capture      [HOME PAGE]: Email popup, exit-intent capture, lead magnet quality, newsletter signup
                     [PURCHASE_SIGNALS] email_crm={tool} → CRM tool confirmed (Klaviyo/Mailchimp/etc.) → score 68+
                     [PURCHASE_SIGNALS] push_capture=true → push notification tool (OneSignal/iZooto) → score 62+
- whatsapp_marketing [HOME PAGE]: WhatsApp opt-in widget, chat button, marketing touchpoint visibility

AGENTIC COMMERCE — how well AI agents (ChatGPT, Perplexity, Gemini) can discover, read, and transact with this store
NOTE: The HOME PAGE section may contain an [AGENTIC_SIGNALS] line — use it as confirmed evidence.
- schema_markup      [HOME PAGE]: JSON-LD Product/Review/FAQ/BreadcrumbList structured data present.
                     [AGENTIC_SIGNALS] ucp_endpoint=true  → UCP (Universal Commerce Protocol) implemented — store exposes /.well-known/ucp for AI agent checkout → score 82+
                     [AGENTIC_SIGNALS] shopify_mcp=true   → Shopify MCP /api/mcp endpoint active — agents can query product catalogue and initiate checkout → score 78+
                     Absent structured data with no UCP/MCP → score ≤30
- content_clarity    [HOME PAGE]: Plain-language copy, scannable headings, LLM-parseable structure.
                     AI agents relay product descriptions verbatim — vague/keyword-stuffed copy → low score.
- ai_discoverability [HOME PAGE]: Unique meta descriptions per page, semantic H1→H2→H3 hierarchy, sitemap signals.
                     [AGENTIC_SIGNALS] ucp_endpoint=true → store is machine-discoverable via UCP standard → add 15 pts to base score
- conversational_ux  [HOME PAGE]: FAQ section depth, chatbot/virtual agent presence, structured Q&A content.
                     [AGENTIC_SIGNALS] chat_widget={platform} → live chat widget confirmed → score 58+ (presence alone = 58; quality of FAQ/chat integration can raise to 80+)
                     [AGENTIC_SIGNALS] faq_section=true        → FAQ content present → score 50+ (depth determines how much higher)
                     No chat widget AND no FAQ → score ≤30
- whatsapp_marketing [HOME PAGE]: WhatsApp opt-in widget, chat button, broadcast/marketing touchpoint.
                     [AGENTIC_SIGNALS] whatsapp_widget=true → WhatsApp widget confirmed on page → score 62+ (integration quality determines final score)
                     Not detected → score based on any WhatsApp mentions or opt-in text in HTML
- open_graph_quality [HOME PAGE]: og:title, og:description, og:image presence and quality
- canonical_health   [HOME PAGE + PRODUCT PAGE + CATEGORY PAGE]: Canonical tags present across all crawled pages.
                     Check <link rel="canonical"> in each available page. Penalise if missing on product/category pages.
                     Score across all crawled pages — not just homepage.

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

# CLAUDE.md — GenAI Tech Labs / TheOwlCo

## Project Overview
**GenAI Tech Labs** is an AI-powered CRO (Conversion Rate Optimisation) agency website for Indian ecommerce brands. The site is a static single-page application (HTML/CSS/JS + PHP backend API). No framework — vanilla JS only.

Live at: `https://genaitechlabs.com`

---

## Architecture

```
/
├── index.html          — Single-page site (all sections)
├── styles.css          — All styles (CSS variables, dark theme)
├── main.js             — UI: flip headline, scroll reveal, radar charts, calculator, modal, FAQ
├── owleye-ai.js        — OwlEye Score™ engine: 28 parameters × 6 pillars, AI scoring logic
├── recommendations.js  — Top 3 recommendations + per-pillar fix logic
└── api/
    ├── analyse.php         — Main scoring API endpoint
    ├── config.php          — DB + API keys (gitignored)
    ├── config.sample.php   — Sample config (committed)
    ├── db.php              — Database helpers
    ├── migrate.php         — DB migrations
    ├── save-lead.php       — Lead capture endpoint
    ├── ai/                 — AI sub-calls (Claude/OpenAI scoring)
    ├── pagespeed/          — Google PageSpeed API integration
    └── screenshot/         — Screenshot capture service
```

---

## Key Product: OwlEye Score™ (Beta)

Proprietary AI tool that scores an ecommerce store URL across **6 pillars × 28 parameters**.

### 6 Pillars (in order)
1. **Purchase Flow** (weight: highest) — Checkout, payment options (UPI/COD/BNPL), cart abandonment, express checkout, COD prominence
2. **User Experience** (formerly Page Experience) — Landing page, product pages, search UX, sticky ATC, category pages
3. **Trust & Conversion** — Trust signals, returns policy, social proof/UGC, reviews, guarantees
4. **Engagement & Retention** — Cross-sell/upsell, email capture, WhatsApp marketing
5. **Agentic Commerce** — Schema/structured data, LLM content clarity, AI discoverability, conversational UX, Open Graph, canonical health
6. **Technical Foundation** — Mobile UX, page speed (Lighthouse), navigation, accessibility

### Scoring
- Each parameter has `max_pts`, `industry_avg`, `weight` (0.6–1.0 confidence)
- `OWLEYE_BENCHMARKS`: industry avg CVR = 1.8%, industry avg score = 58/100
- Revenue upside calculated assuming 100K visitors at AOV ₹1,200
- Results gated behind email capture (name + work email)

### UCP (User Context Profile)
- Detects platform (Shopify/WooCommerce), store type, health/pharma signals
- Used to adjust scoring heuristics

---

## Agentic Commerce Signal Detection (`detectAgenticSignals()` in `api/analyse.php`)

Signals are injected as `[AGENTIC_SIGNALS]` into the HTML the AI analyses, making `conversational_ux` and `whatsapp_marketing` scores much more accurate.

### Signal Map

| Signal | Detection Method | Confidence |
|--------|-----------------|------------|
| `ucp_endpoint=true` | `GET /.well-known/ucp` → valid JSON with capabilities | High |
| `shopify_mcp=true` | Shopify HTML + `GET /api/mcp` → MCP protocol response | High |
| `whatsapp_widget=true` | `wa.me/`, `api.whatsapp.com`, `wati.io` in HTML | High |
| `chat_widget=tawk` | `embed.tawk.to` in HTML | High |
| `chat_widget=intercom` | `widget.intercom.io` in HTML | High |
| `chat_widget={platform}` | Unique CDN domains: Crisp/Freshchat/Tidio/Drift, Zendesk/Kommunicate/Verloop, Yellow.ai/BotPenguin/Gorgias | High |
| `faq_section=true` | `faq` keyword + FAQPage JSON-LD | Medium-High |

---

## Store Signal Memory Map (tested sites)

Used to validate detection coverage. Indian D2C brands are overwhelmingly on Shopify. Hard cases are custom-platform health/specialty stores.

| Site | Type | Platform | Primary catch | Status |
|------|------|----------|--------------|--------|
| femmella.com | Fashion D2C | Shopify | Platform detect | Easy |
| bombayshavingcompany.com | Grooming D2C | Shopify | Platform detect | Easy |
| headsupfortails.com | Pet care D2C | Shopify | Platform detect | Easy |
| nurserylive.com | — | Shopify | Platform detect | Easy |
| kapiva.in | Ayurvedic D2C | Custom/Shopify | `add to cart` + ₹×2 + `/products/` | Easy |
| beatoapp.com | Health-tech | Custom | `/store` + `shop now` + `buy online` + ₹×2 | After fix |
| myupchar.com | Health info + pharmacy | Custom | Hindi signals + `/medicine/` URLs + ₹×2 | After fix |

### Coverage by store type
| Store type | Platform | Key signals |
|-----------|----------|------------|
| Indian fashion D2C (femmella) | Shopify | Platform detect → definitive |
| Ayurvedic D2C (kapiva) | Shopify/custom | `add to cart`, ₹×2, `/products/` |
| Health-tech (beatoapp) | Custom | `/store`, `shop now`, `buy online`, ₹×2 |
| Pharmacy (myupchar) | Custom | Hindi signals, `/medicine/` URLs, ₹×2 |
| Standard WooCommerce | WooCommerce | Platform detect → definitive |

> Pattern: Fashion and standard retail self-identify via Shopify or standard English cart signals. Only hard cases are custom-platform health/specialty sites.

---

## Ecommerce Detection (`isEcommerceStore()` in `api/analyse.php`)

Signals used to confirm a URL is an ecommerce store before scoring:

**Cart/CTA signals:** `add to cart`, `add to basket`, `buy now`, `shop now`, `buy online`, `order now`, `shop the range`
**Section signals:** `/store`, `/store/*` (health-tech brands like beatoapp use `/store` sections)
**Delivery signals:** `express delivery`, `same day delivery`, `seller`
**Currency:** ₹ symbol — threshold is **2 occurrences** (lowered from 3; health stores show fewer prices on homepage)

**URL discovery patterns** (`discoverPageUrls()`):
- Standard: `/products/`, `/collections/`, `/category/`, `/shop/`
- Health/pharmacy: `/medicine/`, `/drug/`, `/supplement/`, `/lab-test/`, `/pharmacy/`, `/health-products/`, `/wellness/`, `/ayurved/`
- Health-tech/devices: `/device/`, `/glucometer/`, `/monitor/`, `/kit/`, `/combo/`, `/buy/`
- Sections: `/store`, `/store/*`

### Purchase Flow Signal Detection (`detectPurchaseFlowSignals()` in `api/analyse.php`)

Called after all pages are fetched. Injects `[PURCHASE_SIGNALS]` into `pages['home']` before AI scoring.
Converts previously estimated (~est.) Purchase Flow params into signal-verified scores.

| Signal | Detection | Param(s) it improves |
|--------|-----------|---------------------|
| `email_crm={tool}` | Klaviyo/Mailchimp/WebEngage/MoEngage/CleverTap/Netcore scripts | `cart_recovery` → 62+, `email_capture` → 68+ |
| `cod_available=true` | "cash on delivery" / "COD" / "pay on delivery" text | `payment_options` → 68+, `cod_prominence` → 65+ |
| `upi_available=true` | GPay/PhonePe/Paytm/UPI text | `payment_options` → 70+, `express_checkout` → 60+ |
| `bnpl_available=true` | Simpl/LazyPay/ZestMoney/Snapmint/BNPL text | `payment_options` → 78+ |
| `payment_gateway=detected` | Razorpay/Cashfree/PayU/CCAvenue | `payment_options` → 65+ |
| `express_checkout_signal=true` | Magic Checkout / Shopify dynamic-checkout | `express_checkout` → 72+ |
| `push_capture=true` | OneSignal/iZooto/PushOwl/Omnisend | `email_capture` → 62+ |
| `sticky_atc_signal=true` | sticky/fixed CSS near add-to-cart in product HTML | `sticky_atc` → 68+ |

> Multiple payment signals stack: `upi_available + cod_available + bnpl_available` → `payment_options` 82+

---

### Scan pipeline (3 steps)
1. **Fetch homepage** — screenshot (thum.io, background) + raw HTML
2. **Generic URL Discovery** — scan homepage links for known patterns above; crawl matched product/category/cart pages to accumulate more HTML signals
3. **Signal Check (`isEcommerceStore()`)** — run against all collected HTML:
   - JSON-LD `@type:Product` present
   - `add to cart` / `buy now` in HTML
   - ₹ ×2 occurrences
   - `/products/`, `/collections/` in HTML links (Shopify-style URLs)

> kapiva.in example: `/products?/…` → `/products/kapiva-aloe-vera-juice` ✅ | `/ayurved/` → `/ayurveda/` or `/ayurvedic-…` ✅ | `/cart` ✅ — all 4 signal checks pass

---

## AI Scoring Guidance (`api/ai/claude.php`)

### PARAM_ORDER (scan + AI scoring order)
`User Experience → Engagement & Retention → Trust → Purchase Flow → Agentic Commerce → Technical Foundation`

> Note: Scan animation starts with Landing Page (not Payment Options) due to this order.

### Agentic Commerce scoring criteria
| Parameter | Signal → Score |
|-----------|---------------|
| `schema_markup` | `ucp_endpoint=true` → 82+ \| `shopify_mcp=true` → 78+ |
| `ai_discoverability` | `ucp_endpoint=true` → +15 pts on base score |
| `conversational_ux` | `chat_widget={x}` → 58+ \| `faq_section=true` → 50+ |
| `whatsapp_marketing` | `whatsapp_widget=true` → 62+ |

**Spec references:**
- UCP: https://ucp.dev/specification/overview/ (co-developed by Google, Shopify, Walmart, Target, Etsy)
- Shopify MCP: https://shopify.dev/docs/agents/catalog/storefront-mcp

### Top 3 Quick Wins logic
- Prefer params with **curated recommendations** (verifiable from crawl)
- **Deprioritise checkout/payment params** — can't be verified server-side; audit call CTA is the right hook for those

---

## UI Patterns

### Per-pillar `~est.` badge
- Each pillar bar in Report Summary shows `~est.` when any of its params were not directly crawled
- Purchase Flow **always** shows `~est.` (can't be verified server-side)
- CSS class: `.pillar-est-tag` in `styles.css`

### Scan animation
- Screenshot (thum.io) loads in background through overlay — no dead-wait
- Scan starts immediately; screenshot appears when thum.io responds
- Unreachable error format: `"The target site (hostname) is not reachable. OwlEye scan can't perform the evaluation at this time. Please retry."` — identical in PHP and JS (AbortError)

---

## Tech Stack
- **Frontend**: Vanilla HTML/CSS/JS, Chart.js (radar charts), no build step
- **Backend**: PHP (API endpoints), MySQL (leads + scan history)
- **AI**: Claude API (via `api/ai/`) for intelligent parameter scoring
- **Third-party**: Google PageSpeed API, screenshot service, Topmate (booking)
- **Fonts**: Google Fonts — Roboto only

---

## Design System
- Dark theme (near-black background `#050A14`)
- Primary accent: `--coral` (`#FF4F2E`)
- Secondary: sky blue `#0EA5E9`
- Font: Roboto (400, 700, 900)
- CSS variables in `:root` in `styles.css`
- Brand name: **OwlEye Score™** — always with ™, often with Beta superscript

---

## Business Context
- Target market: Indian D2C ecommerce stores (Shopify + WooCommerce)
- Sweet spot: ₹15L+ monthly revenue
- CTA: Book Free Audit Call → Topmate link
- Contact: WhatsApp `+91 99110 90091`
- Lead gen: OwlEye Score email gate + booking modal

---

## Conventions
- No npm, no build step — edit files directly
- JS is modular by file: `owleye-ai.js` (scoring engine), `recommendations.js` (fix copy), `main.js` (UI)
- PHP API files in `api/` — never commit `config.php` (use `config.sample.php`)
- Parameters in `owleye-ai.js` are "secret ingredients" — can be added without touching UI
- Branch: `develop` → `main` for production

---

## Auto-Update Rule (IMPORTANT)

**After completing any feature or fix in this repo, Claude must:**
1. Update the relevant section of `CLAUDE.md` — new signals, functions, architectural decisions, scoring rules
2. Update `MEMORY.md` at `/Users/amit/.claude/projects/-Users-amit-Documents-TheOwlCo/memory/MEMORY.md` with a concise summary
3. Do this **before** the commit, not after

**What to capture:**
- New signal detection functions (name, signals, confidence level)
- New scoring rules injected into the AI prompt
- Parameter confidence changes (e.g. a param moved from `~est.` to verified)
- Store test results (new sites tested + what caught them)
- Pillar/parameter renames or restructuring
- Any decision with a "why" that would be lost without documentation

This keeps context fresh across sessions without relying on git log archaeology.

---

## Do Not
- Do not add npm/webpack/bundlers — keep it static
- Do not commit `api/config.php` (contains DB credentials and API keys)
- Do not change the OwlEye Score™ brand name formatting

## AI Provider Sync Rule (CRITICAL)

**Both `api/ai/claude.php` and `api/ai/openai.php` must always be kept in parity.**

Any change to scoring logic, signal rules, parameter criteria, or pillar names in one file **must be applied to the other file in the same commit.**

This includes:
- `$criteria` HEREDOC — scoring instructions per parameter
- Signal floor rules (`[PURCHASE_SIGNALS]`, `[AGENTIC_SIGNALS]`)
- Pillar names and parameter labels
- `$PARAMS` array order or contents
- `systemPrompt()` calibration anchors

The only intentional differences are API-specific (endpoint URL, auth headers, payload format, `response_format` for OpenAI, image placement for Claude).

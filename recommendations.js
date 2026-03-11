/* ═══════════════════════════════════════════════════════════════
   OWLEYE — recommendations.js
   Curated recommendation pool: 16 high-confidence parameters × 3 score bands.
   Low-confidence params (checkout, payment, cart, search, etc.) are kept in
   FIXES_DB (main.js) until we have enough crawl signal to recommend reliably.

   Score bands:
     Critical  : 0  – 40  (major gap, immediate priority)
     Needs Work: 41 – 65  (present but underperforming)
     Good      : 66 – 85  (solid, room to refine)
     86+       : null returned — performing well, no recommendation shown
   ═══════════════════════════════════════════════════════════════ */

const RECOMMENDATIONS = {

  /* ── Page Experience ──────────────────────────────────────── */

  landing_page: {
    critical: {
      title: 'Landing Page — No clear value proposition',
      fix:   'Visitors need to know what you sell, why you\'re different, and what to do next — all without scrolling. Your headline and primary CTA must be visible above the fold.',
    },
    needs_work: {
      title: 'Landing Page — Value proposition needs strengthening',
      fix:   'Lead with a benefit-driven headline (what the customer gets, not what you sell). Primary CTA must be visible without scrolling and use action language like "Shop Now" or "Get Yours Today".',
    },
    good: {
      title: 'Landing Page — Test social proof alongside the CTA',
      fix:   'Your landing page is strong. Add a review count or trust badge directly alongside the hero CTA — social proof at first contact reduces hesitation and lifts click-through.',
    },
  },

  product_pages: {
    critical: {
      title: 'Product Pages — Key conversion elements missing',
      fix:   'Product pages need: 4+ images with lifestyle shots, a size/variant guide, reviews visible above the fold, and a sticky Add to Cart on mobile. Missing any of these directly reduces conversion.',
    },
    needs_work: {
      title: 'Product Pages — Add video and social proof closer to the CTA',
      fix:   'Basics are there but the page needs strengthening. Add a product demo or unboxing video — video increases conversion by 80% and reduces returns. Move reviews above the fold.',
    },
    good: {
      title: 'Product Pages — Add a "Why buy from us" trust block',
      fix:   'Well-structured pages. Add a 3-icon trust block (fast delivery, easy returns, authentic products) directly below the buy button to neutralise last-moment hesitation.',
    },
  },

  /* ── Trust & Conversion ───────────────────────────────────── */

  trust_signals: {
    critical: {
      title: 'Trust Signals — None visible near the buy button',
      fix:   'Without an SSL badge, secure payment icon, or brand proof near the CTA, shoppers can\'t assess legitimacy. Add trust signals (secure checkout, authentic products, easy returns) directly beside or below Add to Cart.',
    },
    needs_work: {
      title: 'Trust Signals — Move them closer to the conversion point',
      fix:   'Trust signals exist but are too far from where buying decisions are made. Place secure checkout, returns, and authenticity badges directly below the Add to Cart button — not in the footer.',
    },
    good: {
      title: 'Trust Signals — Reinforce at checkout with order count',
      fix:   'Trust signals are solid. Add a customer order count ("1,00,000+ orders delivered") near the checkout button — volume signals reinforce confidence at the final step.',
    },
  },

  returns_policy: {
    critical: {
      title: 'Returns Policy — Hard to find or missing',
      fix:   'Fear of being stuck with the wrong item is the #1 Indian shopper hesitation. A visible "30-day hassle-free returns" label near the CTA removes this objection — don\'t make buyers search for it.',
    },
    needs_work: {
      title: 'Returns Policy — Not visible during the purchase journey',
      fix:   'Policy exists but isn\'t shown at the moment of decision. Add a returns reassurance icon or line directly below the Add to Cart button so buyers see it without scrolling.',
    },
    good: {
      title: 'Returns Policy — Make the language more specific',
      fix:   'Policy is visible. Upgrade the language: "Free doorstep pickup" or "No questions asked" converts significantly better than generic "easy returns" — specificity builds trust.',
    },
  },

  social_proof: {
    critical: {
      title: 'Social Proof — None visible',
      fix:   'Without reviews, ratings, or customer counts, buyers have no reason to trust you over any competitor. Social proof is the single highest-impact conversion lever — add it above the fold.',
    },
    needs_work: {
      title: 'Social Proof — Reviews exist but aren\'t prominent enough',
      fix:   'Show "2,400+ verified reviews" and star rating near your hero headline — not just on product pages. Homepage social proof reduces bounce before buyers even reach a product.',
    },
    good: {
      title: 'Social Proof — Add customer photos (UGC)',
      fix:   'Social proof is present. Adding real customer photos on the homepage increases conversion 29% over brand photography alone — buyers trust people who look like them.',
    },
  },

  review_quality: {
    critical: {
      title: 'Review Quality — Missing, too few, or generic',
      fix:   'Indian shoppers read 4–6 reviews before buying. You need: volume (50+ per product), recency (within 90 days), and photo/video reviews. Text-only or old reviews have significantly lower impact.',
    },
    needs_work: {
      title: 'Review Quality — Depth and recency need improving',
      fix:   'Reviews exist but lack detail and photos. Send a WhatsApp review request 5 days post-delivery. Incentivise photo reviews with loyalty points — visual reviews convert 3× better than text-only.',
    },
    good: {
      title: 'Review Quality — Feature your most detailed review',
      fix:   'Review quality is solid. Highlight one long, specific customer review prominently on the product page — a detailed review from a real buyer is more persuasive than 50 one-liners.',
    },
  },

  guarantee_signals: {
    critical: {
      title: 'Guarantee — No money-back guarantee visible',
      fix:   'Risk reversal removes the last purchase objection. "Love it or full refund" directly next to the price can increase conversion by 20–25%. If you offer it, make it unmissable.',
    },
    needs_work: {
      title: 'Guarantee — Exists but buried',
      fix:   'Your guarantee is present but not near the decision point. Place it as a visible badge directly alongside the price and CTA — not in the footer or a separate policy page.',
    },
    good: {
      title: 'Guarantee — Strengthen the language',
      fix:   'Guarantee is visible. Switch to outcome language: "Love it or full refund" outperforms "30-day returns" — buyers respond to what they get, not the process they must follow.',
    },
  },

  /* ── Agentic Commerce ─────────────────────────────────────── */

  schema_markup: {
    critical: {
      title: 'Schema Markup — None detected',
      fix:   'No structured data found. Search engines and AI agents (ChatGPT, Perplexity) cannot read your products, prices, reviews, or availability. Add Product + AggregateRating schema as an immediate priority.',
    },
    needs_work: {
      title: 'Schema Markup — Incomplete — missing key fields',
      fix:   'Basic schema present but incomplete. Product schema must include: name, price, availability, brand, and AggregateRating. Missing any of these blocks rich result eligibility in Google and AI search.',
    },
    good: {
      title: 'Schema Markup — Add FAQ schema for AI citations',
      fix:   'Product schema is solid. Add FAQ schema to product pages — FAQ is the primary format LLMs use when answering product questions. It can get your content cited in ChatGPT and Perplexity answers.',
    },
  },

  content_clarity: {
    critical: {
      title: 'Content Clarity — Descriptions are vague or unstructured',
      fix:   'AI agents relay your product descriptions verbatim to shoppers. Vague or keyword-stuffed copy means poor AI representation and lost AI-referred traffic. Rewrite using: what it is → who it\'s for → key benefits → what\'s included.',
    },
    needs_work: {
      title: 'Content Clarity — Structure descriptions for AI readability',
      fix:   'Content exists but isn\'t structured clearly. Use plain language in a consistent format: what it is → who it\'s for → key benefits → what\'s included. Plain beats marketing copy for both humans and AI parsing.',
    },
    good: {
      title: 'Content Clarity — Add comparison content for AI recommendations',
      fix:   'Content clarity is good. Adding a "Compare with similar products" section gives AI agents comparison data — which they actively use when recommending your product over competitors.',
    },
  },

  ai_discoverability: {
    critical: {
      title: 'AI Discoverability — Meta descriptions missing or duplicated',
      fix:   'ChatGPT Search and Perplexity pull meta descriptions directly when summarising your brand. Missing or duplicate metas mean your brand is described incorrectly or not found at all. Write unique metas for every page.',
    },
    needs_work: {
      title: 'AI Discoverability — Meta descriptions are too generic',
      fix:   'Metas exist but are generic. Write unique, benefit-led descriptions for your top 20 product and category pages — 150–160 characters, leading with your primary differentiator, not just the product name.',
    },
    good: {
      title: 'AI Discoverability — Build a structured About page',
      fix:   'Discoverability is solid. A structured About page (brand story, founding year, product categories, values) carries high weight for AI brand summarisation — it\'s what LLMs read first to understand your brand.',
    },
  },

  conversational_ux: {
    critical: {
      title: 'Conversational UX — No FAQ or Q&A visible',
      fix:   'LLMs (ChatGPT, Gemini, Perplexity) cite FAQ content directly when answering product questions. Without a FAQ, your brand is absent from AI-generated shopping answers — a growing source of purchase traffic.',
    },
    needs_work: {
      title: 'Conversational UX — FAQ is too generic',
      fix:   'FAQ exists but covers generic topics. Create product-specific FAQs: sizing, ingredients, compatibility, delivery timelines — these are the exact questions buyers ask AI assistants before purchasing.',
    },
    good: {
      title: 'Conversational UX — Add a product Q&A or chatbot',
      fix:   'FAQ content is solid. A basic chatbot or AI assistant answering your top 10 product questions increases conversion by 20% for consideration-stage buyers who aren\'t ready to call or email.',
    },
  },

  open_graph_quality: {
    critical: {
      title: 'Open Graph — Tags missing or broken',
      fix:   'Every WhatsApp, Instagram, or Facebook share of your URL shows a blank or broken preview. This directly kills click-through from word-of-mouth sharing — India\'s most powerful purchase driver.',
    },
    needs_work: {
      title: 'Open Graph — Incomplete tags on product pages',
      fix:   'OG tags are partially set. Every product page needs: og:title (product + brand), og:description (one-line benefit), og:image (1200×630px product photo). Test by sharing a product URL on WhatsApp.',
    },
    good: {
      title: 'Open Graph — Add price and availability for social shopping',
      fix:   'Open Graph is configured. Add og:price:amount and og:availability to product pages — these enable Facebook and Instagram shopping card previews, reducing friction for buyers who discover you via social.',
    },
  },

  canonical_health: {
    critical: {
      title: 'Canonical Tags — Missing across key pages',
      fix:   'Missing canonical tags mean filter and sort URL variants (/products?color=red, /products?sort=price) create duplicate content — diluting SEO authority and confusing AI crawlers indexing your catalogue.',
    },
    needs_work: {
      title: 'Canonical Tags — Faceted navigation URLs not canonicalised',
      fix:   'Canonicals are partially implemented. Ensure all faceted navigation URLs (filter/sort variants) point canonical to the base category page. This concentrates your link equity to the right pages.',
    },
    good: {
      title: 'Canonical Tags — Add hreflang for multi-region',
      fix:   'Canonical structure is clean. If you sell across India + international markets, implement hreflang — prevents duplicate content penalties and ensures the right page ranks in each market.',
    },
  },

  /* ── Technical Foundation ─────────────────────────────────── */

  navigation_clarity: {
    critical: {
      title: 'Navigation — Overloaded or confusing',
      fix:   'Too many top-level menu items cause cognitive overload and reduce browsing conversion by 35%. Limit to 5–7 items, each a clear buying destination. Remove anything that isn\'t a product category or key landing page.',
    },
    needs_work: {
      title: 'Navigation — Add high-converting entry points',
      fix:   'Structure is there but could be sharper. Add "Best Sellers" or "New Arrivals" to the main nav — these are the highest-converting entry points for visitors who don\'t know exactly what they want.',
    },
    good: {
      title: 'Navigation — Add a visible Offers/Sale tab',
      fix:   'Navigation is clean. A visible "Sale" or "Offers" tab in the top nav reduces bounce from price-sensitive visitors — and India has a very high proportion of deal-seeking buyers.',
    },
  },

  accessibility: {
    critical: {
      title: 'Accessibility — Missing alt text on product images',
      fix:   'Missing alt text blocks AI vision indexing — Google\'s AI and screen readers both use alt text to understand your products. Add descriptive alt text to every product image as an immediate fix.',
    },
    needs_work: {
      title: 'Accessibility — CTAs and colour contrast need attention',
      fix:   'Basic accessibility is present but incomplete. Ensure all CTAs have descriptive labels (not just "Click here"), colour contrast meets WCAG AA, and a keyboard user can navigate through checkout without a mouse.',
    },
    good: {
      title: 'Accessibility — Improve heading hierarchy',
      fix:   'Accessibility is solid. Add a clean H1→H2→H3 heading hierarchy across all pages — helps both screen readers and AI crawlers understand page structure, which improves indexing quality.',
    },
  },

  page_speed: {
    critical: {
      title: 'Page Speed — Critically slow',
      fix:   'A 1-second delay reduces conversion by 7%; 3 seconds loses 53% of mobile visitors. Immediate priorities: convert all images to WebP format, remove unused third-party scripts, and enable lazy loading.',
    },
    needs_work: {
      title: 'Page Speed — Acceptable but improvable',
      fix:   'Speed is functional but not optimised. Implement a CDN for static assets, defer non-critical JavaScript, and preconnect to external domains (fonts, payment gateways). Target sub-2.5s Largest Contentful Paint.',
    },
    good: {
      title: 'Page Speed — Fine-tune Core Web Vitals',
      fix:   'Speed is good. Focus on CLS (layout shift) — often caused by images without explicit dimensions or late-loading fonts. A clean CLS score improves both user experience and Google ranking.',
    },
  },

};

/**
 * Returns the curated recommendation for a given parameter and score.
 * Returns null for:
 *   - Low-confidence parameters (not in RECOMMENDATIONS pool)
 *   - Scores above 85 (performing well — no recommendation needed)
 *
 * @param {string} paramKey  - key from PARAM_ORDER (e.g. 'landing_page')
 * @param {number} score     - 0–100
 * @returns {{ title: string, fix: string } | null}
 */
function getRecommendation(paramKey, score) {
  const rec = RECOMMENDATIONS[paramKey];
  if (!rec)        return null;   // not a high-confidence param
  if (score > 85)  return null;   // performing well — no fix needed
  if (score <= 40) return rec.critical;
  if (score <= 65) return rec.needs_work;
  return rec.good;
}

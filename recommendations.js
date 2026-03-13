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
      fix:   'Your headline and primary CTA must be visible above the fold — no scrolling required. Visitors who can\'t immediately see what you sell and what to do next will leave.',
    },
    needs_work: {
      title: 'Landing Page — Value proposition needs strengthening',
      fix:   'Lead with what the customer gets (not what you sell) and put the CTA above the fold. Use action language: "Shop Now" or "Get Yours Today".',
    },
    good: {
      title: 'Landing Page — Add social proof beside the CTA',
      fix:   'Add a review count or trust badge directly alongside the hero CTA — social proof at first contact reduces hesitation before the visitor even scrolls.',
    },
  },

  product_pages: {
    critical: {
      title: 'Product Pages — Key conversion elements missing',
      fix:   'Need: 4+ images with lifestyle shots, variant guide, reviews above the fold, and sticky Add to Cart on mobile. Each missing element directly costs conversions.',
    },
    needs_work: {
      title: 'Product Pages — Add video and move reviews up',
      fix:   'Add a product demo or unboxing video — video lifts conversion 80% and cuts returns. Move star ratings and review count above the fold.',
    },
    good: {
      title: 'Product Pages — Add a trust block below the buy button',
      fix:   'Add a 3-icon strip (fast delivery · easy returns · authentic products) directly below Add to Cart to neutralise last-moment hesitation.',
    },
  },

  /* ── Trust & Conversion ───────────────────────────────────── */

  trust_signals: {
    critical: {
      title: 'Trust Signals — None visible near the buy button',
      fix:   'Add secure checkout badge, authenticity proof, and returns reassurance directly beside or below Add to Cart — not in the footer where no one looks.',
    },
    needs_work: {
      title: 'Trust Signals — Too far from the conversion point',
      fix:   'Trust badges exist but are buried. Place secure checkout, returns, and authenticity signals directly below the Add to Cart button.',
    },
    good: {
      title: 'Trust Signals — Add your order count near checkout',
      fix:   'Add "1,00,000+ orders delivered" near the checkout button — volume signals close the final moment of doubt.',
    },
  },

  returns_policy: {
    critical: {
      title: 'Returns Policy — Hard to find or missing',
      fix:   'Add a "30-day hassle-free returns" label near the CTA — fear of a wrong purchase is India\'s #1 conversion blocker. Don\'t make buyers hunt for the policy.',
    },
    needs_work: {
      title: 'Returns Policy — Not visible at the moment of decision',
      fix:   'Show a returns reassurance line or icon directly below Add to Cart — visible without scrolling.',
    },
    good: {
      title: 'Returns Policy — Sharpen the language',
      fix:   '"Free doorstep pickup" and "No questions asked" convert far better than generic "easy returns" — specificity builds trust.',
    },
  },

  social_proof: {
    critical: {
      title: 'Social Proof — None visible',
      fix:   'Add star ratings, review count, or customer numbers above the fold. Social proof is the single highest-impact conversion lever — buyers won\'t trust you without it.',
    },
    needs_work: {
      title: 'Social Proof — Not prominent enough',
      fix:   'Show "2,400+ verified reviews" near the hero headline — not just on product pages. Homepage social proof stops visitors from bouncing before they reach a product.',
    },
    good: {
      title: 'Social Proof — Add real customer photos',
      fix:   'Add UGC photos on the homepage — real customer images lift conversion 29% over brand photography alone.',
    },
  },

  review_quality: {
    critical: {
      title: 'Review Quality — Missing, too few, or generic',
      fix:   'Need 50+ reviews per product, within 90 days, with photo/video content. Text-only or old reviews have minimal impact on Indian shoppers.',
    },
    needs_work: {
      title: 'Review Quality — Needs depth and recency',
      fix:   'Send a WhatsApp review request 5 days post-delivery and incentivise photo reviews — visual reviews convert 3× better than text-only.',
    },
    good: {
      title: 'Review Quality — Feature your best review prominently',
      fix:   'Pin one long, specific customer review on the product page — a detailed real review is more persuasive than 50 one-liners.',
    },
  },

  guarantee_signals: {
    critical: {
      title: 'Guarantee — No money-back guarantee visible',
      fix:   '"Love it or full refund" next to the price lifts conversion 20–25%. If you offer it, make it unmissable — risk reversal removes the last purchase objection.',
    },
    needs_work: {
      title: 'Guarantee — Exists but buried',
      fix:   'Place the guarantee as a visible badge alongside price and CTA — not in the footer or a separate policy page.',
    },
    good: {
      title: 'Guarantee — Strengthen the language',
      fix:   'Switch to outcome language: "Love it or full refund" outperforms "30-day returns" — buyers respond to what they get, not the process.',
    },
  },

  /* ── Agentic Commerce ─────────────────────────────────────── */

  schema_markup: {
    critical: {
      title: 'Schema Markup — None detected',
      fix:   'AI agents (ChatGPT, Perplexity) can\'t read your products without structured data. Add Product + AggregateRating schema immediately.',
    },
    needs_work: {
      title: 'Schema Markup — Incomplete — missing key fields',
      fix:   'Add name, price, availability, brand, and AggregateRating to your Product schema — all are required for rich results and AI search visibility.',
    },
    good: {
      title: 'Schema Markup — Add FAQ schema for AI citations',
      fix:   'Add FAQ schema to product pages — it\'s the primary format LLMs use when answering product questions, and gets your content cited in ChatGPT and Perplexity.',
    },
  },

  content_clarity: {
    critical: {
      title: 'Content Clarity — Descriptions are vague or unstructured',
      fix:   'Rewrite using: what it is → who it\'s for → key benefits → what\'s included. AI agents relay your copy verbatim — vague descriptions mean poor AI representation.',
    },
    needs_work: {
      title: 'Content Clarity — Structure for AI readability',
      fix:   'Use plain language in a consistent format: what it is → who it\'s for → key benefits. Plain prose beats keyword-stuffed copy for both humans and AI parsers.',
    },
    good: {
      title: 'Content Clarity — Add comparison content',
      fix:   'Add a "Compare with similar products" section — AI agents actively use comparison data when recommending your product over competitors.',
    },
  },

  ai_discoverability: {
    critical: {
      title: 'AI Discoverability — Meta descriptions missing or duplicated',
      fix:   'ChatGPT and Perplexity pull meta descriptions when summarising brands. Missing or duplicate metas mean your brand gets described incorrectly — or not at all.',
    },
    needs_work: {
      title: 'AI Discoverability — Meta descriptions too generic',
      fix:   'Write unique 150–160 char metas for your top product and category pages, leading with your primary differentiator — not just the product name.',
    },
    good: {
      title: 'AI Discoverability — Build a structured About page',
      fix:   'A structured About page (brand story, categories, values) is what LLMs read first to understand your brand — and carries high weight in AI summarisation.',
    },
  },

  conversational_ux: {
    critical: {
      title: 'Conversational UX — No FAQ or Q&A visible',
      fix:   'LLMs cite FAQ content directly in shopping answers. Without one, your brand is absent from AI-generated recommendations — a fast-growing purchase traffic source.',
    },
    needs_work: {
      title: 'Conversational UX — FAQ is too generic',
      fix:   'Make FAQs product-specific: sizing, ingredients, compatibility, delivery timelines — the exact questions buyers ask AI assistants before purchasing.',
    },
    good: {
      title: 'Conversational UX — Add a product Q&A or chatbot',
      fix:   'A chatbot answering your top 10 product questions lifts conversion 20% for consideration-stage buyers who aren\'t ready to call or email.',
    },
  },

  open_graph_quality: {
    critical: {
      title: 'Open Graph — Tags missing or broken',
      fix:   'Every WhatsApp or social share of your URL shows a blank preview — directly killing click-through from word-of-mouth, India\'s most powerful purchase driver.',
    },
    needs_work: {
      title: 'Open Graph — Incomplete tags on product pages',
      fix:   'Each product page needs og:title, og:description, og:image (1200×630px). Test by sharing a product URL on WhatsApp.',
    },
    good: {
      title: 'Open Graph — Add price and availability',
      fix:   'Add og:price:amount and og:availability to enable Facebook and Instagram shopping card previews on your product pages.',
    },
  },

  canonical_health: {
    critical: {
      title: 'Canonical Tags — Missing across key pages',
      fix:   'Filter/sort URL variants create duplicate content that dilutes SEO authority and confuses AI crawlers. Add canonical tags to product and category pages immediately.',
    },
    needs_work: {
      title: 'Canonical Tags — Faceted URLs not canonicalised',
      fix:   'Point canonical tags on all filter/sort variants to the base category page — concentrates link equity where it matters.',
    },
    good: {
      title: 'Canonical Tags — Add hreflang for multi-region',
      fix:   'If selling across markets, add hreflang to prevent duplicate content penalties and ensure the right page ranks per region.',
    },
  },

  /* ── Technical Foundation ─────────────────────────────────── */

  navigation_clarity: {
    critical: {
      title: 'Navigation — Overloaded or confusing',
      fix:   'Limit to 5–7 top-level items, each a clear buying destination. Too many menu items reduce browsing conversion by 35% — remove anything that isn\'t a product category.',
    },
    needs_work: {
      title: 'Navigation — Add high-converting entry points',
      fix:   'Add "Best Sellers" or "New Arrivals" to the main nav — these are the highest-converting entry points for visitors who don\'t know exactly what they want.',
    },
    good: {
      title: 'Navigation — Add a visible Sale tab',
      fix:   'A "Sale" or "Offers" tab in the top nav reduces bounce from price-sensitive visitors — a large segment in the Indian market.',
    },
  },

  accessibility: {
    critical: {
      title: 'Accessibility — Missing alt text on product images',
      fix:   'Add descriptive alt text to every product image — Google\'s AI and screen readers both use it to understand your products.',
    },
    needs_work: {
      title: 'Accessibility — CTAs and contrast need attention',
      fix:   'Ensure CTAs have descriptive labels, colour contrast meets WCAG AA, and checkout can be navigated by keyboard alone.',
    },
    good: {
      title: 'Accessibility — Clean up heading hierarchy',
      fix:   'Add a consistent H1→H2→H3 structure across all pages — improves both screen reader navigation and AI crawler indexing quality.',
    },
  },

  page_speed: {
    critical: {
      title: 'Page Speed — Critically slow',
      fix:   '3 seconds loses 53% of mobile visitors. Convert images to WebP, remove unused scripts, and enable lazy loading — in that order.',
    },
    needs_work: {
      title: 'Page Speed — Acceptable but improvable',
      fix:   'Add a CDN for static assets, defer non-critical JS, and preconnect to external domains. Target sub-2.5s Largest Contentful Paint.',
    },
    good: {
      title: 'Page Speed — Fix layout shift (CLS)',
      fix:   'Set explicit dimensions on images and avoid late-loading fonts — CLS is the most common remaining Web Vitals issue and affects both UX and ranking.',
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

/* ═══════════════════════════════════════════════════════════════
   OWLEYE SCORE™ — owleye-ai.js
   AI scoring engine, benchmarks, and API endpoints spec
   28 PARAMETERS × 6 PILLARS
   weight: 1.0 = high confidence | 0.6 = medium confidence
   ═══════════════════════════════════════════════════════════════ */

// ─────────────────────────────────────────
// BENCHMARK DATA (Industry averages)
// ─────────────────────────────────────────
const OWLEYE_BENCHMARKS = {
  industry_avg_cvr: 1.8,          // % — Indian ecommerce average
  industry_avg_score: 58,         // out of 100
  cvr_lift_per_point: 0.125,      // % order lift per OwlEye point gained
  score_to_cvr_map: {
    40:  0.9,   // Critical → ~0.9% CVR
    55:  1.4,
    65:  1.8,   // Industry average
    75:  2.4,
    85:  3.1,
    95:  3.8,
    100: 4.2,   // Optimised → ~4.2% CVR
  }
};

// ─────────────────────────────────────────
// 28 PARAMETERS × 6 PILLARS
// weight: 1.0 = high confidence | 0.6 = medium confidence
// Parameters are secret ingredients — add freely without touching UI
// ─────────────────────────────────────────
const OWLEYE_PILLARS = [
  {
    id: 'purchase_flow',
    name: 'Purchase Flow',
    icon: '🛒',
    weight: 28,
    color: '#FF4F2E',
    description: 'The most critical pillar — covers the end-to-end buying journey from cart to confirmation.',
    parameters: [
      {
        id: 'checkout_flow',
        name: 'Checkout Flow',
        weight: 1.0,
        max_pts: 12,
        industry_avg: 70,
        scan_msgs: ['Mapping checkout steps…', 'Counting form fields…', 'Checking progress indicators…'],
        fixes: {
          low:  'Add a progress indicator to checkout — reduces abandonment by up to 18%.',
          mid:  'Reduce checkout to 2 screens max; auto-fill address from PIN code.',
          high: 'Checkout flow is strong. Consider adding 1-click reorder for repeat buyers.',
        }
      },
      {
        id: 'payment_options',
        name: 'Payment Options',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 68,
        scan_msgs: ['Detecting payment methods…', 'Checking UPI/COD support…', 'Validating payment UX…'],
        fixes: {
          low:  'Add UPI and COD — 62% of Indian shoppers abandon without their preferred payment.',
          mid:  'Add BNPL options (Simpl, LazyPay). They lift AOV 20–30%.',
          high: 'Payment options are comprehensive. Test "Pay Later" upsells at checkout.',
        }
      },
      {
        id: 'cart_recovery',
        name: 'Cart Recovery',
        weight: 1.0,
        max_pts: 8,
        industry_avg: 65,
        scan_msgs: ['Scanning cart behaviour…', 'Checking abandonment triggers…', 'Analysing recovery flows…'],
        fixes: {
          low:  'Implement exit-intent popup + 3-email abandoned cart sequence (1h, 24h, 72h).',
          mid:  'Add cart persistence across sessions. 43% return to buy within 24h.',
          high: 'Recovery flows are set up. Test WhatsApp recovery — 3× higher open rate than email.',
        }
      },
      {
        id: 'express_checkout',
        name: 'Express Checkout',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 42,
        scan_msgs: ['Checking one-click buy options…', 'Testing guest checkout flow…', 'Scanning express payment presence…'],
        fixes: {
          low:  'Add Google Pay or PhonePe express checkout — reduces checkout to 2 taps for 45% of buyers.',
          mid:  'Enable instant buy on product pages. Cuts checkout from 5 steps to 1.',
          high: 'Express checkout is live. Test placing the express button above the standard checkout.',
        }
      },
      {
        id: 'cod_prominence',
        name: 'COD Prominence',
        weight: 1.0,
        max_pts: 8,
        industry_avg: 65,
        scan_msgs: ['Scanning COD visibility…', 'Checking payment hierarchy…', 'Measuring COD prominence near CTA…'],
        fixes: {
          low:  'Display "Cash on Delivery available" on product pages — converts 38% of hesitant buyers.',
          mid:  'Move COD to first position in checkout — preferred by 45% of Tier 2/3 buyers.',
          high: 'COD is prominent. Add "No prepayment needed" near add-to-cart to reduce anxiety.',
        }
      },
    ]
  },
  {
    id: 'page_experience',
    name: 'Page Experience',
    icon: '🗒️',
    weight: 22,
    color: '#0EA5E9',
    description: 'Covers the quality and conversion-readiness of your landing pages and product detail pages.',
    parameters: [
      {
        id: 'landing_page',
        name: 'Landing Page',
        weight: 1.0,
        max_pts: 13,
        industry_avg: 72,
        scan_msgs: ['Reading above-fold content…', 'Scoring CTA placement…', 'Evaluating headline clarity…'],
        fixes: {
          low:  'Move primary CTA above the fold with a benefit-led headline. 73% of visitors never scroll.',
          mid:  'Add a hero social proof statement ("2,400+ happy customers") near the main CTA.',
          high: 'Landing page is well-optimised. A/B test emotional vs rational headline framing.',
        }
      },
      {
        id: 'product_pages',
        name: 'Product Pages',
        weight: 1.0,
        max_pts: 12,
        industry_avg: 70,
        scan_msgs: ['Inspecting product images…', 'Checking reviews section…', 'Analysing buy-button prominence…'],
        fixes: {
          low:  'Add 4+ product images, a video, and a size guide. Lifts conversion by 24%.',
          mid:  'Display "X bought in last 24h" social proof near add-to-cart. Proven 8–12% lift.',
          high: 'Product pages look strong. Test pinned sticky add-to-cart on mobile scroll.',
        }
      },
      {
        id: 'search_ux',
        name: 'Search UX',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 52,
        scan_msgs: ['Testing site search experience…', 'Checking autocomplete quality…', 'Evaluating search result relevance…'],
        fixes: {
          low:  'Add autocomplete search with product images — 43% of site search users have higher purchase intent.',
          mid:  'Add typo tolerance and synonym support to search. Missing matches = lost sales.',
          high: 'Search UX is solid. Add "trending searches" and "recently viewed" to boost discovery.',
        }
      },
      {
        id: 'sticky_atc',
        name: 'Sticky Add-to-Cart',
        weight: 1.0,
        max_pts: 8,
        industry_avg: 38,
        scan_msgs: ['Checking sticky add-to-cart…', 'Testing scroll behaviour on mobile…', 'Evaluating CTA persistence…'],
        fixes: {
          low:  'Add a sticky add-to-cart bar on mobile — lifts conversions by 10–20% on long product pages.',
          mid:  'Make sticky ATC show price + selected variant. Eliminates need to scroll back up.',
          high: 'Sticky ATC is in place. Add purchase count ("1,247 bought this") to the sticky bar.',
        }
      },
      {
        id: 'category_pages',
        name: 'Category Pages',
        weight: 0.6,
        max_pts: 8,
        industry_avg: 55,
        scan_msgs: ['Evaluating category page layout…', 'Checking filter & sort options…', 'Scoring product card quality…'],
        fixes: {
          low:  'Add filter and sort on category pages — 67% of shoppers use filters to find the right product.',
          mid:  'Show review star ratings on product cards in listing pages. Proven 15% CTR lift.',
          high: 'Category pages are well-structured. Test infinite scroll vs pagination for your audience.',
        }
      },
    ]
  },
  {
    id: 'trust_conversion',
    name: 'Trust & Conversion',
    icon: '🤝',
    weight: 18,
    color: '#A8E535',
    description: 'Buyers need to trust your brand before they buy. This pillar measures signals that reduce purchase anxiety.',
    parameters: [
      {
        id: 'trust_signals',
        name: 'Trust Signals',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 68,
        scan_msgs: ['Looking for trust badges…', 'Scanning security indicators…', 'Checking social proof…'],
        fixes: {
          low:  'Add SSL badge, payment logos, and customer review count near buy button.',
          mid:  'Display verified photo reviews — 72% of buyers read reviews before purchase.',
          high: 'Trust signals are well placed. Test adding a founder story to the homepage.',
        }
      },
      {
        id: 'returns_policy',
        name: 'Returns Policy',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 72,
        scan_msgs: ['Locating returns policy…', 'Evaluating visibility…', 'Scoring ease of understanding…'],
        fixes: {
          low:  'Show "30-day easy returns" prominently near CTA — removes the #1 objection.',
          mid:  'Add inline returns explainer on product page. Reduces pre-purchase anxiety by 31%.',
          high: 'Returns policy is visible. Consider adding a no-questions-asked highlight.',
        }
      },
      {
        id: 'social_proof',
        name: 'Social Proof',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 52,
        scan_msgs: ['Counting review volume…', 'Checking photo/video reviews…', 'Evaluating social proof placement…'],
        fixes: {
          low:  'Add customer review count ("2,400+ reviews") near hero CTA — reduces purchase anxiety immediately.',
          mid:  'Add real customer photos or UGC to product pages — 88% of buyers trust UGC over brand photography.',
          high: 'Social proof is strong. Test a live purchase notifications ticker for real-time credibility.',
        }
      },
      {
        id: 'review_quality',
        name: 'Review Quality',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 50,
        scan_msgs: ['Analysing review depth…', 'Checking rating distribution…', 'Scanning review recency…'],
        fixes: {
          low:  'Actively collect reviews post-purchase — fresh, detailed reviews convert 3× better than old ones.',
          mid:  'Add a Q&A section and verified buyer badge. Reduces trust gap for high-value purchases.',
          high: 'Review quality is excellent. Highlight top 3 reviews on product page to maximise impact.',
        }
      },
      {
        id: 'guarantee_signals',
        name: 'Guarantee Signals',
        weight: 0.6,
        max_pts: 8,
        industry_avg: 45,
        scan_msgs: ['Looking for money-back guarantee…', 'Checking warranty information…', 'Scoring risk-reversal copy…'],
        fixes: {
          low:  'Add a visible money-back guarantee — risk reversal increases conversions by up to 25%.',
          mid:  'Place guarantee messaging directly beside the buy button, not only on a separate policy page.',
          high: 'Guarantee signals are clear. Test a "Try Before You Pay" framing for premium SKUs.',
        }
      },
    ]
  },
  {
    id: 'engagement_retention',
    name: 'Engagement & Retention',
    icon: '⚡',
    weight: 12,
    color: '#F5C518',
    description: 'Maximising revenue per visit through intelligent cross-sell, upsell and re-engagement.',
    parameters: [
      {
        id: 'cross_sell',
        name: 'Cross-sell & Upsell',
        weight: 1.0,
        max_pts: 15,
        industry_avg: 66,
        scan_msgs: ['Detecting upsell modules…', 'Checking bundle offers…', 'Scoring AOV optimisation…'],
        fixes: {
          low:  'Add "Frequently bought together" bundles — average 12–18% AOV uplift.',
          mid:  'Add post-purchase upsell page. Highest-converting touchpoint — buyer trust is peak.',
          high: 'Cross-sell is active. Test AI-personalised recommendations vs rule-based.',
        }
      },
      {
        id: 'email_capture',
        name: 'Email Capture',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 55,
        scan_msgs: ['Checking email capture mechanisms…', 'Evaluating popup timing…', 'Scoring lead magnet quality…'],
        fixes: {
          low:  'Add a welcome offer popup (10% off) — email captures convert at 3–5% when incentivised correctly.',
          mid:  'Switch to exit-intent email capture — shown to 3× opt-in rates vs time-based popups.',
          high: 'Email capture is active. Segment by product category for better lifecycle targeting.',
        }
      },
      {
        id: 'whatsapp_marketing',
        name: 'WhatsApp Marketing',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 40,
        scan_msgs: ['Checking WhatsApp touchpoints…', 'Scanning opt-in flows…', 'Evaluating messaging integration…'],
        fixes: {
          low:  'Add a WhatsApp opt-in at checkout — WhatsApp campaigns have 98% open rate vs 22% email.',
          mid:  'Set up WhatsApp abandoned cart messages. 3× higher recovery rate than email for Indian shoppers.',
          high: 'WhatsApp integration is active. Add "Track Order on WhatsApp" to confirmation emails.',
        }
      },
    ]
  },
  {
    id: 'agentic_commerce',
    name: 'Agentic Commerce',
    icon: '🔮',
    weight: 10,
    color: '#A855F7',
    description: 'As AI agents navigate the web on behalf of shoppers, your store must be readable and rankable by machines — not just humans.',
    parameters: [
      {
        id: 'schema_markup',
        name: 'Schema Markup',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 25,
        scan_msgs: ['Checking schema markup…', 'Scanning structured data tags…', 'Validating JSON-LD implementation…'],
        fixes: {
          low:  'Add Schema.org Product + Review markup — lets AI agents accurately read your catalogue.',
          mid:  'Extend schema to include FAQPage and BreadcrumbList. Improves LLM and rich-result coverage.',
          high: 'Schema markup is solid. Add Offer schema with price/availability for real-time AI indexing.',
        }
      },
      {
        id: 'content_clarity',
        name: 'Content Clarity',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 55,
        scan_msgs: ['Analysing copy for LLM readability…', 'Scoring plain-language usage…', 'Testing AI content parsing…'],
        fixes: {
          low:  'Rewrite product descriptions in plain, conversational language — AI agents relay these verbatim.',
          mid:  'Add clear benefit statements to category pages. LLMs summarise these for shopping queries.',
          high: 'Content reads clearly. Add structured comparison tables — highly referenced by AI shopping assistants.',
        }
      },
      {
        id: 'ai_discoverability',
        name: 'AI Discoverability',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 40,
        scan_msgs: ['Testing AI search signals…', 'Checking meta structure…', 'Scanning semantic HTML hierarchy…'],
        fixes: {
          low:  'Fix missing/duplicate meta descriptions — ChatGPT Search and Perplexity pull these directly.',
          mid:  'Add semantic heading hierarchy (H1→H2→H3). LLMs use this to understand page structure.',
          high: 'AI discoverability is strong. Submit to Bing Webmaster (feeds Copilot) and update sitemap weekly.',
        }
      },
      {
        id: 'conversational_ux',
        name: 'Conversational UX',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 35,
        scan_msgs: ['Looking for FAQ sections…', 'Checking assistant/chatbot presence…', 'Scanning Q&A content depth…'],
        fixes: {
          low:  'Add a well-structured FAQ page — the primary source LLMs cite when answering product questions.',
          mid:  'Implement a WhatsApp or live chat touchpoint. AI-assisted chat converts 2.4× better than forms.',
          high: 'Conversational UX is in place. Train your chat on top 20 objections to automate resolution.',
        }
      },
      {
        id: 'open_graph_quality',
        name: 'Open Graph Quality',
        weight: 1.0,
        max_pts: 8,
        industry_avg: 45,
        scan_msgs: ['Checking OG tags…', 'Validating social share previews…', 'Testing OG image quality…'],
        fixes: {
          low:  'Add og:title, og:description, and og:image to all product pages — required for WhatsApp and Facebook previews.',
          mid:  'Set og:image to min 1200×630px. Blurry previews reduce click-through from social shares.',
          high: 'OG tags are complete. Add og:price and product OG type for enhanced Facebook Shops display.',
        }
      },
      {
        id: 'canonical_health',
        name: 'Canonical Health',
        weight: 0.6,
        max_pts: 8,
        industry_avg: 48,
        scan_msgs: ['Checking canonical tags…', 'Scanning for duplicate URL patterns…', 'Validating URL structure…'],
        fixes: {
          low:  'Add canonical tags to all product/category pages — prevents duplicate content from diluting AI rankings.',
          mid:  'Fix faceted navigation URLs (filter duplicates) by adding canonical to the base category URL.',
          high: 'Canonical structure is solid. Audit pagination to ensure rel=canonical is set correctly.',
        }
      },
    ]
  },
  {
    id: 'technical_foundation',
    name: 'Technical Foundation',
    icon: '📱',
    weight: 10,
    color: '#14B8A6',
    description: 'Speed and mobile experience are table stakes. Poor technical performance kills conversions silently.',
    parameters: [
      {
        id: 'mobile_ux',
        name: 'Mobile UX',
        weight: 1.0,
        max_pts: 10,
        industry_avg: 70,
        scan_msgs: ['Simulating mobile viewport…', 'Checking tap target sizes…', 'Measuring scroll depth…'],
        fixes: {
          low:  'Reduce mobile checkout to 2 screens. 67% of Indian ecommerce traffic is mobile.',
          mid:  'Increase tap target size to 44×44px minimum. Reduces mis-taps and frustration.',
          high: 'Mobile UX is solid. Test bottom-sheet product detail vs full-page for mobile.',
        }
      },
      {
        id: 'page_speed',
        name: 'Page Speed',
        weight: 1.0,
        max_pts: 12,
        industry_avg: 55,
        scan_msgs: ['Running Google PageSpeed check…', 'Analysing Lighthouse score…', 'Measuring Core Web Vitals…'],
        fixes: {
          low:  'Critical: Lighthouse mobile score below 50. Compress images and remove render-blocking scripts.',
          mid:  'Serve next-gen images (WebP/AVIF) and enable lazy loading — typically 20–40 point Lighthouse lift.',
          high: 'Page speed is strong. Monitor monthly — a 1s slowdown reduces conversion by 7%.',
        }
      },
      {
        id: 'navigation_clarity',
        name: 'Navigation Clarity',
        weight: 1.0,
        max_pts: 8,
        industry_avg: 60,
        scan_msgs: ['Evaluating navigation structure…', 'Checking category hierarchy…', 'Testing menu discoverability…'],
        fixes: {
          low:  'Simplify navigation to max 7 top-level items. Cognitive overload reduces browsing conversion by 35%.',
          mid:  'Add a "Shop by Category" mega-menu with product images — reduces time to find desired products.',
          high: 'Navigation is clear. Add a sticky nav with search bar to improve deep-scroll discovery.',
        }
      },
      {
        id: 'accessibility',
        name: 'Accessibility',
        weight: 0.6,
        max_pts: 8,
        industry_avg: 38,
        scan_msgs: ['Checking colour contrast…', 'Scanning alt text on images…', 'Testing keyboard navigation…'],
        fixes: {
          low:  'Add alt text to all product images — required for screen readers and AI vision indexing.',
          mid:  'Fix colour contrast on CTAs (min 4.5:1 ratio) — benefits 8% of users with colour vision deficiency.',
          high: 'Accessibility baseline is met. Conduct a full WCAG 2.1 audit to unlock assistive tech users.',
        }
      },
    ]
  }
];

// ─────────────────────────────────────────
// SCORE CALCULATION ENGINE
// ─────────────────────────────────────────

/**
 * Generate deterministic demo scores for a URL (fallback mode)
 */
function generateDemoScores(url) {
  const seed = url.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0);
  const allParams = OWLEYE_PILLARS.flatMap(p => p.parameters);
  return allParams.map((param, i) => {
    const base = 35 + Math.round(Math.abs(Math.sin(seed * (i + 1) * 0.7)) * 45);
    const pillarBonus = Math.round((i % 3) * 8);
    return Math.min(92, Math.max(22, base + pillarBonus));
  });
}

/**
 * Convert flat param scores array → pillar-level scores (weighted by param.weight)
 */
function getPillarScores(paramScores) {
  return OWLEYE_PILLARS.map((pillar, pi) => {
    const base = OWLEYE_PILLARS.slice(0, pi).reduce((a, p) => a + p.parameters.length, 0);
    let weightedSum = 0, totalWeight = 0;
    pillar.parameters.forEach((param, i) => {
      const w = param.weight ?? 1.0;
      weightedSum += paramScores[base + i] * w;
      totalWeight += w;
    });
    return Math.round(weightedSum / totalWeight);
  });
}

/**
 * Calculate total OwlEye Score™ from parameter scores (weighted by pillar, then param)
 */
function calcOwleyeTotal(paramScores) {
  const pillarScores = getPillarScores(paramScores);
  let weightedSum = 0, totalWeight = 0;
  OWLEYE_PILLARS.forEach((pillar, pi) => {
    weightedSum += pillarScores[pi] * pillar.weight;
    totalWeight += pillar.weight;
  });
  return Math.round(weightedSum / totalWeight);
}

/**
 * Slab-based score gain potential
 */
function getSlabGain(score) {
  if (score >= 100) return 0;
  if (score >= 95)  return +(score * 0.010).toFixed(2);
  if (score >= 90)  return +(score * 0.050).toFixed(2);
  if (score >= 80)  return +(score * 0.075).toFixed(2);
  if (score >= 70)  return +(score * 0.100).toFixed(2);
  return               +(score * 0.125).toFixed(2);
}

/**
 * Calculate estimated revenue upside from score improvement
 */
function calcRevenueUpside(currentScore, visitors = 100000, aov = 1200) {
  const scoreGain     = getSlabGain(currentScore);
  const targetScore   = +(currentScore + scoreGain).toFixed(1);
  const cvrLiftPct    = +(scoreGain * OWLEYE_BENCHMARKS.cvr_lift_per_point).toFixed(2);
  const extraOrders   = Math.round(visitors * (cvrLiftPct / 100));
  const monthlyUpside = extraOrders * aov;
  return { scoreGain, targetScore, cvrLiftPct, extraOrders, monthlyUpside, annualUpside: monthlyUpside * 12 };
}

/**
 * Get score band label + CSS class
 */
function getScoreBand(score) {
  if (score < 41) return { label: '⚠️ Critical',   cls: 'band-critical' };
  if (score < 66) return { label: '⚡ Needs Work', cls: 'band-needs' };
  if (score < 86) return { label: '✅ Good',        cls: 'band-good' };
  return             { label: '🚀 Optimised',       cls: 'band-optimised' };
}

/**
 * Get fix recommendation for a parameter based on score level
 */
function getFixForScore(paramId, score) {
  const param = OWLEYE_PILLARS.flatMap(p => p.parameters).find(p => p.id === paramId);
  if (!param) return '';
  if (score < 45) return param.fixes.low;
  if (score < 70) return param.fixes.mid;
  return param.fixes.high;
}

// ─────────────────────────────────────────
// INDUSTRY AVERAGE ARRAYS
// ─────────────────────────────────────────

// Parameter-level (28 values) — used internally
const OWLEYE_INDUSTRY_AVG = OWLEYE_PILLARS
  .flatMap(p => p.parameters.map(param => param.industry_avg));

// Pillar-level (6 values) — used for radar chart
const OWLEYE_PILLAR_AVG = OWLEYE_PILLARS.map(pillar =>
  Math.round(pillar.parameters.reduce((a, p) => a + p.industry_avg, 0) / pillar.parameters.length)
);

// ─────────────────────────────────────────
// API ENDPOINT SPECIFICATION
// ─────────────────────────────────────────
/*
  POST /api/owleye/analyse
  Request:  { url: string }
  Response: {
    scores: { [param_id]: number },   // 28 parameters
    total: number,
    band: string,
    pillar_breakdown: { [pillar_id]: number },
    top_fixes: { param: string, score: number, fix: string }[],
    revenue_upside: { scoreGain, cvrLiftPct, monthlyUpside, annualUpside }
  }
*/

// ─────────────────────────────────────────
// EXPORT (for module use)
// ─────────────────────────────────────────
if (typeof module !== 'undefined') {
  module.exports = {
    OWLEYE_PILLARS, OWLEYE_BENCHMARKS,
    OWLEYE_INDUSTRY_AVG, OWLEYE_PILLAR_AVG,
    generateDemoScores, getPillarScores,
    calcOwleyeTotal, getSlabGain,
    calcRevenueUpside, getScoreBand, getFixForScore,
  };
}

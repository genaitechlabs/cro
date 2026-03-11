/* ═══════════════════════════════════════════════════════════════
   OWLEYE SCORE™ — owleye-ai.js
   AI scoring engine, benchmarks, and API endpoints spec
   13 PARAMETERS × 6 PILLARS
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
// 13 PARAMETERS × 6 PILLARS
// Parameters are the secret ingredients — add freely without touching UI
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
        max_pts: 8,
        industry_avg: 65,
        scan_msgs: ['Scanning cart behaviour…', 'Checking abandonment triggers…', 'Analysing recovery flows…'],
        fixes: {
          low:  'Implement exit-intent popup + 3-email abandoned cart sequence (1h, 24h, 72h).',
          mid:  'Add cart persistence across sessions. 43% return to buy within 24h.',
          high: 'Recovery flows are set up. Test WhatsApp recovery — 3× higher open rate than email.',
        }
      }
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
        max_pts: 12,
        industry_avg: 70,
        scan_msgs: ['Inspecting product images…', 'Checking reviews section…', 'Analysing buy-button prominence…'],
        fixes: {
          low:  'Add 4+ product images, a video, and a size guide. Lifts conversion by 24%.',
          mid:  'Display "X bought in last 24h" social proof near add-to-cart. Proven 8–12% lift.',
          high: 'Product pages look strong. Test pinned sticky add-to-cart on mobile scroll.',
        }
      }
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
        max_pts: 10,
        industry_avg: 72,
        scan_msgs: ['Locating returns policy…', 'Evaluating visibility…', 'Scoring ease of understanding…'],
        fixes: {
          low:  'Show "30-day easy returns" prominently near CTA — removes the #1 objection.',
          mid:  'Add inline returns explainer on product page. Reduces pre-purchase anxiety by 31%.',
          high: 'Returns policy is visible. Consider adding a no-questions-asked highlight.',
        }
      }
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
        max_pts: 15,
        industry_avg: 66,
        scan_msgs: ['Detecting upsell modules…', 'Checking bundle offers…', 'Scoring AOV optimisation…'],
        fixes: {
          low:  'Add "Frequently bought together" bundles — average 12–18% AOV uplift.',
          mid:  'Add post-purchase upsell page. Highest-converting touchpoint — buyer trust is peak.',
          high: 'Cross-sell is active. Test AI-personalised recommendations vs rule-based.',
        }
      }
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
        max_pts: 10,
        industry_avg: 55,
        scan_msgs: ['Analysing copy for LLM readability…', 'Scoring plain-language usage…', 'Testing AI content parsing…'],
        fixes: {
          low:  'Rewrite product descriptions in plain, conversational language — AI agents relay these to shoppers verbatim.',
          mid:  'Add clear benefit statements to category pages. LLMs summarise these when answering shopping queries.',
          high: 'Content reads clearly. Add structured comparison tables — highly referenced by AI shopping assistants.',
        }
      },
      {
        id: 'ai_discoverability',
        name: 'AI Discoverability',
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
        max_pts: 10,
        industry_avg: 35,
        scan_msgs: ['Looking for FAQ sections…', 'Checking assistant/chatbot presence…', 'Scanning Q&A content depth…'],
        fixes: {
          low:  'Add a well-structured FAQ page — the primary source LLMs cite when answering product questions.',
          mid:  'Implement a WhatsApp or live chat touchpoint. AI-assisted chat converts 2.4× better than forms.',
          high: 'Conversational UX is in place. Train your chat on top 20 objections to automate resolution.',
        }
      }
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
        max_pts: 10,
        industry_avg: 70,
        scan_msgs: ['Simulating mobile viewport…', 'Checking tap target sizes…', 'Measuring scroll depth…'],
        fixes: {
          low:  'Reduce mobile checkout to 2 screens. 67% of Indian ecommerce traffic is mobile.',
          mid:  'Increase tap target size to 44×44px minimum. Reduces mis-taps and frustration.',
          high: 'Mobile UX is solid. Test bottom-sheet product detail vs full-page for mobile.',
        }
      }
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
 * Calculate total OwlEye Score™ from parameter scores (weighted by pillar)
 */
function calcOwleyeTotal(paramScores) {
  let weightedSum = 0, totalWeight = 0;
  OWLEYE_PILLARS.forEach((pillar, pi) => {
    const base = OWLEYE_PILLARS.slice(0, pi).reduce((a, p) => a + p.parameters.length, 0);
    const pillarScores = pillar.parameters.map((_, i) => paramScores[base + i]);
    const pillarAvg = pillarScores.reduce((a, b) => a + b, 0) / pillarScores.length;
    weightedSum += pillarAvg * pillar.weight;
    totalWeight += pillar.weight;
  });
  return Math.round(weightedSum / totalWeight);
}

/**
 * Convert flat param scores array → pillar-level scores array (for radar)
 */
function getPillarScores(paramScores) {
  return OWLEYE_PILLARS.map((pillar, pi) => {
    const base = OWLEYE_PILLARS.slice(0, pi).reduce((a, p) => a + p.parameters.length, 0);
    const pArr = pillar.parameters.map((_, i) => paramScores[base + i]);
    return Math.round(pArr.reduce((a, b) => a + b, 0) / pArr.length);
  });
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

// Parameter-level (13 values) — used internally
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
    scores: { [param_id]: number },   // 13 parameters
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

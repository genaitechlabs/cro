/* ═══════════════════════════════════════════════════════════════
   THE OWL CO — main.js
   ═══════════════════════════════════════════════════════════════ */

// ─────────────────────────────────────────
// FLIP HEADLINE
// ─────────────────────────────────────────
const FLIP_WORDS = ['browsing.', 'comparing.', 'hesitating.', 'abandoning.', 'distracted.', 'bouncing.', 'looking.'];
let flipIdx = 0;
const flipEl = document.getElementById('flip-word');

function flipNext() {
  flipEl.classList.remove('in');
  flipEl.classList.add('out');
  setTimeout(() => {
    flipIdx = (flipIdx + 1) % FLIP_WORDS.length;
    flipEl.textContent = FLIP_WORDS[flipIdx];
    flipEl.classList.remove('out');
    flipEl.classList.add('in');
  }, 320);
}
setInterval(flipNext, 2400);

// ─────────────────────────────────────────
// SCROLL REVEAL
// ─────────────────────────────────────────
const revObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('show'); revObs.unobserve(e.target); }
  });
}, { threshold: 0.05, rootMargin: '0px 0px -10px 0px' });

document.querySelectorAll('.reveal').forEach(el => revObs.observe(el));
setTimeout(() => document.querySelectorAll('.reveal:not(.show)').forEach(el => el.classList.add('show')), 500);

// ─────────────────────────────────────────
// COUNTER ANIMATION
// ─────────────────────────────────────────
function animateCount(el) {
  const target = +el.dataset.target;
  const prefix = el.dataset.prefix || '';
  const suffix = el.dataset.suffix || '';
  const dur = 1600, start = performance.now();
  function frame(now) {
    const p = Math.min((now - start) / dur, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    el.textContent = prefix + Math.round(ease * target) + suffix;
    if (p < 1) requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
}

const ctrObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.querySelectorAll('[data-target]').forEach(animateCount);
      ctrObs.unobserve(e.target);
    }
  });
}, { threshold: 0.3 });
document.querySelectorAll('.metric').forEach(m => ctrObs.observe(m));

// ─────────────────────────────────────────
// RADAR CHART (Canvas)
// ─────────────────────────────────────────
// 6 pillar names — radar shows pillar-level scores, not individual parameters
const RADAR_LABELS = ['Purchase Flow', 'Page Experience', 'Trust & Convert', 'Engagement', 'Agentic Commerce', 'Technical'];

function drawRadar(canvasId, yourScores, avgScores, size) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const dpr = window.devicePixelRatio || 1;
  canvas.width = size * dpr;
  canvas.height = size * dpr;
  canvas.style.width = size + 'px';
  canvas.style.height = size + 'px';
  const ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);

  const cx = size / 2, cy = size / 2, r = size * 0.33;
  const N = yourScores.length;
  ctx.clearRect(0, 0, size, size);

  // Grid rings
  for (let ring = 1; ring <= 5; ring++) {
    const fr = r * (ring / 5);
    ctx.beginPath();
    for (let i = 0; i < N; i++) {
      const angle = (2 * Math.PI * i / N) - Math.PI / 2;
      const x = cx + fr * Math.cos(angle), y = cy + fr * Math.sin(angle);
      i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    }
    ctx.closePath();
    ctx.strokeStyle = `rgba(255,255,255,${ring === 5 ? 0.14 : 0.05})`;
    ctx.lineWidth = 1;
    ctx.stroke();
  }

  // Axis lines
  for (let i = 0; i < N; i++) {
    const angle = (2 * Math.PI * i / N) - Math.PI / 2;
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.lineTo(cx + r * Math.cos(angle), cy + r * Math.sin(angle));
    ctx.strokeStyle = 'rgba(255,255,255,0.06)';
    ctx.lineWidth = 1;
    ctx.stroke();
  }

  function drawPoly(scores, fillColor, strokeColor, dotColor) {
    ctx.beginPath();
    scores.forEach((s, i) => {
      const angle = (2 * Math.PI * i / N) - Math.PI / 2;
      const fr = r * (s / 100);
      const x = cx + fr * Math.cos(angle), y = cy + fr * Math.sin(angle);
      i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.closePath();
    ctx.fillStyle = fillColor;
    ctx.fill();
    ctx.strokeStyle = strokeColor;
    ctx.lineWidth = 2;
    ctx.stroke();
    scores.forEach((s, i) => {
      const angle = (2 * Math.PI * i / N) - Math.PI / 2;
      const fr = r * (s / 100);
      ctx.beginPath();
      ctx.arc(cx + fr * Math.cos(angle), cy + fr * Math.sin(angle), 3.5, 0, Math.PI * 2);
      ctx.fillStyle = dotColor || strokeColor;
      ctx.fill();
    });
  }

  if (avgScores) drawPoly(avgScores, 'rgba(14,165,233,0.1)', 'rgba(14,165,233,0.55)');
  drawPoly(yourScores, 'rgba(255,79,46,0.15)', 'rgba(255,79,46,0.9)');

  // Labels
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  const fontSize = size < 300 ? 8 : 9;
  ctx.font = `${fontSize}px Roboto, sans-serif`;
  RADAR_LABELS.forEach((label, i) => {
    const angle = (2 * Math.PI * i / N) - Math.PI / 2;
    const lr = r * 1.25;
    const x = cx + lr * Math.cos(angle), y = cy + lr * Math.sin(angle);
    ctx.fillStyle = 'rgba(248,249,255,0.5)';
    const words = label.split(' ');
    if (words.length > 1) {
      ctx.fillText(words[0], x, y - 6);
      ctx.fillText(words.slice(1).join(' '), x, y + 6);
    } else {
      ctx.fillText(label, x, y);
    }
  });
}

// Hero radar — cycles every 3s with lerp animation
// 6 values per scenario — one per pillar (Purchase, Page Exp, Trust, Engagement, Agentic, Technical)
// Designed so scenarios fall mostly BELOW industry avg with isolated spikes — shows where stores lose revenue
const HERO_SCENARIOS = [
  [55, 60, 68, 42, 28, 58],   // typical store — below avg across board, agentic very weak
  [70, 45, 82, 52, 38, 54],   // strong trust, low page exp + engagement + agentic
  [42, 78, 55, 72, 48, 66],   // page exp + engagement spike, purchase flow critical
  [85, 58, 48, 38, 32, 40],   // purchase optimised only — rest well below avg
  [60, 72, 70, 75, 62, 80],   // close-to-avg store, technical + engagement leading
];
// Hero industry avg — fixed at ~4th radar ring (~78) for visual impact.
// Decoupled from OWLEYE_PILLAR_AVG (used in real scoring) so the blue ring
// sits clearly outward, showing the gap between most stores and best practice.
// Order: Purchase Flow, Page Experience, Trust & Convert, Engagement, Agentic, Technical
const HERO_AVG = [78, 74, 76, 70, 60, 74];
let heroSceneIdx = 0, heroAnimPct = 0, heroRaf = null;
let heroFrom = [...HERO_SCENARIOS[0]];
let heroTo = [...HERO_SCENARIOS[0]];

function lerp(a, b, t) { return a.map((v, i) => v + (b[i] - v) * t); }

function getHeroRadarSize() {
  const canvas = document.getElementById('heroRadar');
  if (!canvas) return 380;
  const container = canvas.closest('.hero-right');
  if (!container) return 380;
  const available = container.offsetWidth || window.innerWidth - 64;
  return window.innerWidth <= 900 ? Math.min(300, Math.max(220, available - 16)) : 380;
}

function drawHeroRadars(scores, avg, size) {
  drawRadar('heroRadar', scores, avg, size);
  const mobileCanvas = document.getElementById('heroRadarMobile');
  if (mobileCanvas) {
    const mSize = Math.min(280, window.innerWidth - 64);
    drawRadar('heroRadarMobile', scores, avg, mSize);
  }
}

function animateHeroRadar() {
  heroAnimPct = Math.min(heroAnimPct + 2, 100);
  const t = 1 - Math.pow(1 - heroAnimPct / 100, 3);
  drawHeroRadars(lerp(heroFrom, heroTo, t), HERO_AVG, getHeroRadarSize());
  if (heroAnimPct < 100) heroRaf = requestAnimationFrame(animateHeroRadar);
}
animateHeroRadar();

// Redraw hero radars on resize (debounced)
let heroResizeTimer;
window.addEventListener('resize', () => {
  clearTimeout(heroResizeTimer);
  heroResizeTimer = setTimeout(() => {
    drawHeroRadars(lerp(heroFrom, heroTo, 1), HERO_AVG, getHeroRadarSize());
  }, 150);
});

// ─────────────────────────────────────────
// HAMBURGER NAV
// ─────────────────────────────────────────
function toggleNav() {
  const nav = document.getElementById('navLinks');
  const btn = document.getElementById('hamburger');
  const isOpen = nav.classList.toggle('open');
  btn.classList.toggle('open', isOpen);
  btn.setAttribute('aria-expanded', isOpen);
}
function closeNav() {
  const nav = document.getElementById('navLinks');
  const btn = document.getElementById('hamburger');
  nav.classList.remove('open');
  btn.classList.remove('open');
  btn.setAttribute('aria-expanded', 'false');
}

setInterval(() => {
  heroFrom = lerp(heroFrom, heroTo, 1);
  heroSceneIdx = (heroSceneIdx + 1) % HERO_SCENARIOS.length;
  heroTo = [...HERO_SCENARIOS[heroSceneIdx]];
  heroAnimPct = 0;
  if (heroRaf) cancelAnimationFrame(heroRaf);
  animateHeroRadar();
}, 3000);

// ─────────────────────────────────────────
// REAL AI SCORE FETCHING
// ─────────────────────────────────────────
// Parameter order must match OWLEYE_PILLARS.flatMap in owleye-ai.js (28 params × 6 pillars)
const PARAM_ORDER = [
  'checkout_flow', 'payment_options', 'cart_recovery',          // Purchase Flow
  'express_checkout', 'cod_prominence',                         // Purchase Flow
  'landing_page',  'product_pages', 'search_ux',               // Page Experience
  'sticky_atc', 'category_pages',                               // Page Experience
  'trust_signals', 'returns_policy', 'social_proof',            // Trust & Conversion
  'review_quality', 'guarantee_signals',                        // Trust & Conversion
  'cross_sell', 'email_capture', 'whatsapp_marketing',          // Engagement & Retention
  'schema_markup', 'content_clarity',                           // Agentic Commerce
  'ai_discoverability', 'conversational_ux',                    // Agentic Commerce
  'open_graph_quality', 'canonical_health',                     // Agentic Commerce
  'mobile_ux', 'page_speed',                                    // Technical Foundation
  'navigation_clarity', 'accessibility',                        // Technical Foundation
];

// Holds the in-flight API promise so animation + fetch run in parallel
let _scoresPromise = null;

async function fetchRealScores(url) {
  // Hard 50s cap — prevents hanging forever if the target site is extremely slow
  const controller = new AbortController();
  const timeoutId  = setTimeout(() => controller.abort(), 50000);

  try {
    const res = await fetch('api/analyse.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ url }),
      signal:  controller.signal,
    });
    clearTimeout(timeoutId);
    const data = await res.json();
    // Known API error (e.g. not an ecommerce store, unreachable, rate limit)
    if (data.error && !data.scores) return { apiError: data.error, scores: null };
    const s = data.scores || {};
    // Convert named object → array in PARAM_ORDER
    return {
      scores:           PARAM_ORDER.map(k => (typeof s[k] === 'number' ? s[k] : 50)),
      previousScore:    typeof data.previous_score === 'number' ? data.previous_score : null,
      verifiedCount:    typeof data.verified_count === 'number'  ? data.verified_count  : null,
      unverifiedParams: Array.isArray(data.unverified_params)    ? data.unverified_params : [],
      pagesScanned:     typeof data.pages_scanned  === 'number'  ? data.pages_scanned   : null,
      jsRendered:       !!data.js_rendered,
      scanToken:        typeof data.scan_token === 'string'       ? data.scan_token       : null,
    };
  } catch (err) {
    clearTimeout(timeoutId);
    console.warn('[OwlEye] Fetch error:', err.message);
    const msg = err.name === 'AbortError'
      ? 'The scan timed out. The target site may be temporarily unavailable or very slow. Please try again in a moment.'
      : 'The scan could not complete. Please check your connection and try again.';
    return { apiError: msg, scores: null };
  }
}

// ─────────────────────────────────────────
// OWLEYE SCORE TOOL
// ─────────────────────────────────────────
const SCAN_PARAMS = [
  // Page Experience — first impression (scanned first)
  { name: 'Landing Page',       icon: '📄', msgs: ['Reading above-fold content…',       'Scoring CTA placement…',             'Evaluating headline clarity…'] },
  { name: 'Payment Options',    icon: '💳', msgs: ['Detecting payment methods…',        'Checking UPI/COD support…',          'Validating payment UX…'] },
  // Purchase Flow (remaining 4)
  { name: 'Checkout Flow',      icon: '🛒', msgs: ['Mapping checkout steps…',          'Counting form fields…',              'Checking progress indicators…'] },
  { name: 'Cart Recovery',      icon: '🔄', msgs: ['Scanning cart behaviour…',          'Checking abandonment triggers…',     'Analysing recovery flows…'] },
  { name: 'Express Checkout',   icon: '⚡', msgs: ['Checking one-click buy options…',   'Testing guest checkout flow…',       'Scanning express payment presence…'] },
  { name: 'COD Prominence',     icon: '💵', msgs: ['Scanning COD visibility…',          'Checking payment hierarchy…',        'Measuring COD prominence near CTA…'] },
  // Page Experience (remaining 4)
  { name: 'Product Pages',      icon: '🖼️', msgs: ['Inspecting product images…',        'Checking reviews section…',          'Analysing buy-button area…'] },
  { name: 'Search UX',          icon: '🔍', msgs: ['Testing site search experience…',   'Checking autocomplete quality…',     'Evaluating search result relevance…'] },
  { name: 'Sticky Add-to-Cart', icon: '📌', msgs: ['Checking sticky add-to-cart…',      'Testing scroll behaviour on mobile…','Evaluating CTA persistence…'] },
  { name: 'Category Pages',     icon: '🗂️', msgs: ['Evaluating category page layout…',  'Checking filter & sort options…',    'Scoring product card quality…'] },
  // Trust & Conversion (5)
  { name: 'Trust Signals',      icon: '🤝', msgs: ['Looking for trust badges…',         'Scanning security indicators…',      'Checking social proof…'] },
  { name: 'Returns Policy',     icon: '📋', msgs: ['Locating returns policy…',           'Evaluating visibility…',             'Scoring policy clarity…'] },
  { name: 'Social Proof',       icon: '⭐', msgs: ['Counting review volume…',            'Checking photo/video reviews…',      'Evaluating social proof placement…'] },
  { name: 'Review Quality',     icon: '💬', msgs: ['Analysing review depth…',            'Checking rating distribution…',      'Scanning review recency…'] },
  { name: 'Guarantee Signals',  icon: '🛡️', msgs: ['Looking for money-back guarantee…', 'Checking warranty information…',     'Scoring risk-reversal copy…'] },
  // Engagement & Retention (3)
  { name: 'Cross-sell & Upsell',    icon: '🔁', msgs: ['Detecting upsell modules…',         'Checking bundle offers…',            'Scoring AOV optimisation…'] },
  { name: 'Email Capture',      icon: '📧', msgs: ['Checking email capture mechanisms…','Evaluating popup timing…',            'Scoring lead magnet quality…'] },
  { name: 'WhatsApp Marketing', icon: '💬', msgs: ['Checking WhatsApp touchpoints…',    'Scanning opt-in flows…',             'Evaluating messaging integration…'] },
  // Agentic Commerce (6)
  { name: 'Schema Markup',      icon: '🔮', msgs: ['Checking schema markup…',           'Scanning structured data tags…',     'Validating JSON-LD implementation…'] },
  { name: 'Content Clarity',    icon: '🔮', msgs: ['Analysing copy for LLM readability…','Scoring plain-language usage…',      'Testing AI content parsing…'] },
  { name: 'AI Discoverability', icon: '🔮', msgs: ['Testing AI search signals…',        'Checking meta structure…',           'Scanning semantic HTML hierarchy…'] },
  { name: 'Conversational UX',  icon: '🔮', msgs: ['Looking for FAQ sections…',         'Checking assistant presence…',       'Scanning Q&A content depth…'] },
  { name: 'Open Graph Quality', icon: '🔮', msgs: ['Checking OG tags…',                 'Validating social share previews…',  'Testing OG image quality…'] },
  { name: 'Canonical Health',   icon: '🔮', msgs: ['Checking canonical tags…',          'Scanning duplicate URL patterns…',   'Validating URL structure…'] },
  // Technical Foundation (4)
  { name: 'Mobile UX',          icon: '📱', msgs: ['Simulating mobile viewport…',       'Checking tap target sizes…',         'Measuring scroll depth…'] },
  { name: 'Page Speed',         icon: '⚡', msgs: ['Running Google PageSpeed check…',   'Analysing Lighthouse score…',        'Measuring Core Web Vitals…'] },
  { name: 'Navigation Clarity', icon: '🗺️', msgs: ['Evaluating navigation structure…',  'Checking category hierarchy…',       'Testing menu discoverability…'] },
  { name: 'Accessibility',      icon: '♿', msgs: ['Checking colour contrast…',          'Scanning alt text on images…',       'Testing keyboard navigation…'] },
];

const FIXES_DB = [
  // Purchase Flow
  { param: 'Checkout Flow',      fix: 'Add a progress indicator to checkout — reduces abandonment by up to 18%.' },
  { param: 'Payment Options',    fix: 'Add UPI and COD — 62% of Indian shoppers abandon without their preferred payment.' },
  { param: 'Cart Recovery',      fix: 'Set up exit-intent popup + 3-email abandoned cart sequence (1h, 24h, 72h).' },
  { param: 'Express Checkout',   fix: 'Add Google Pay or PhonePe express checkout — reduces checkout to 2 taps for 45% of buyers.' },
  { param: 'COD Prominence',     fix: 'Display "Cash on Delivery available" on product pages — converts 38% of hesitant buyers.' },
  // Page Experience
  { param: 'Landing Page',       fix: 'Move primary CTA above the fold with a benefit-led headline. 73% of visitors never scroll.' },
  { param: 'Product Pages',      fix: 'Add 4+ images, a video, and size guide — lifts conversion by 24%.' },
  { param: 'Search UX',          fix: 'Add autocomplete with product images — 43% of site searchers have higher purchase intent.' },
  { param: 'Sticky Add-to-Cart', fix: 'Add a sticky add-to-cart bar on mobile — lifts conversions by 10–20% on long product pages.' },
  { param: 'Category Pages',     fix: 'Add filter and sort on category pages — 67% of shoppers use filters to find the right product.' },
  // Trust & Conversion
  { param: 'Trust Signals',      fix: 'Display verified photo reviews and trust badges near the buy button.' },
  { param: 'Returns Policy',     fix: 'Show "30-day easy returns" prominently near CTA — removes the #1 objection.' },
  { param: 'Social Proof',       fix: 'Add customer review count ("2,400+ reviews") near hero CTA — reduces purchase anxiety immediately.' },
  { param: 'Review Quality',     fix: 'Actively collect reviews post-purchase — fresh, detailed reviews convert 3× better.' },
  { param: 'Guarantee Signals',  fix: 'Add a visible money-back guarantee — risk reversal increases conversions by up to 25%.' },
  // Engagement & Retention
  { param: 'Cross-sell & Upsell',    fix: 'Add "Frequently bought together" bundles — average 12–18% AOV uplift.' },
  { param: 'Email Capture',      fix: 'Add a welcome offer popup (10% off) — email captures convert at 3–5% when incentivised.' },
  { param: 'WhatsApp Marketing', fix: 'Add a WhatsApp opt-in at checkout — WhatsApp campaigns have 98% open rate vs 22% email.' },
  // Agentic Commerce
  { param: 'Schema Markup',      fix: 'Add Schema.org Product + Review markup — lets AI agents accurately read your catalogue.' },
  { param: 'Content Clarity',    fix: 'Rewrite product descriptions in plain language — AI agents relay these to shoppers verbatim.' },
  { param: 'AI Discoverability', fix: 'Fix missing meta descriptions — ChatGPT Search and Perplexity pull these directly.' },
  { param: 'Conversational UX',  fix: 'Add a structured FAQ page — the primary source LLMs cite when answering product questions.' },
  { param: 'Open Graph Quality', fix: 'Add og:title, og:description, and og:image to all product pages — required for WhatsApp and Facebook previews.' },
  { param: 'Canonical Health',   fix: 'Add canonical tags to all product/category pages — prevents duplicate content diluting AI rankings.' },
  // Technical Foundation
  { param: 'Mobile UX',          fix: 'Reduce mobile checkout to 2 screens. 67% of Indian ecommerce traffic is mobile.' },
  { param: 'Page Speed',         fix: 'Compress images and remove render-blocking scripts — a 1s slowdown reduces conversion by 7%.' },
  { param: 'Navigation Clarity', fix: 'Simplify navigation to max 7 top-level items — cognitive overload reduces browsing conversion by 35%.' },
  { param: 'Accessibility',      fix: 'Add alt text to all product images — required for screen readers and AI vision indexing.' },
];

function runScoreAnalysis() {
  let raw = document.getElementById('scoreUrl').value.trim();
  const urlError = document.getElementById('urlError');

  // Normalize: prepend https:// if no protocol given
  if (raw && !/^https?:\/\//i.test(raw)) raw = 'https://' + raw;
  document.getElementById('scoreUrl').value = raw;

  let valid = false;
  try {
    const u = new URL(raw);
    // Hostname must contain a dot (e.g. 'ddd' or 'localhost' are invalid)
    valid = /^https?:/.test(u.protocol) && u.hostname.includes('.') && u.hostname.split('.').every(p => p.length > 0);
  } catch(e) {}
  if (!raw || !valid) {
    urlError.style.display = 'block';
    document.getElementById('scoreUrl').focus();
    return;
  }
  urlError.style.display = 'none';

  const url = raw;

  // Kick off real AI scoring immediately — runs in parallel with the scan animation
  _scoresPromise = fetchRealScores(url);

  // Cache result as soon as it resolves — checked in scanNext() for early termination
  let _resolvedApiData = null;
  _scoresPromise.then(d => { _resolvedApiData = d; });

  document.getElementById('scoreBtn').disabled = true;
  document.getElementById('scoreUrl').readOnly = true;
  document.getElementById('screenshotPlaceholder').style.display = 'none';
  document.getElementById('scoreResults').classList.remove('show');
  document.getElementById('scoreResults').style.display = 'none';

  // Show scan frame (mockup only) + full-width status panel below
  document.getElementById('scanFrame').style.display = 'block';
  document.getElementById('scanStatusPanel').style.display = 'block';
  document.getElementById('screenshotUrl').textContent = url;

  // Overlay starts opaque with "Fetching screenshot…" while img loads
  const scanOvEl = document.getElementById('scanOverlay');
  scanOvEl.style.background = 'rgba(5,10,20,.93)';
  scanOvEl.style.backdropFilter = '';
  scanOvEl.innerHTML = '<div style="width:36px;height:36px;border:3px solid rgba(255,79,46,.3);border-top-color:var(--coral);border-radius:50%;animation:spin .8s linear infinite"></div><div style="font-size:.76rem;color:var(--coral);font-weight:700" id="scanStatusText">Fetching screenshot…</div>';

  // Build param scan UI upfront so refs are ready for scanNext closure
  const scanList = document.getElementById('paramScanWrap');
  scanList.innerHTML = `
    <div id="scanSingleRow" style="
      opacity:0;
      transition:opacity 0.35s ease;
      background:rgba(255,255,255,0.04);
      border:1px solid rgba(255,255,255,0.08);
      border-radius:14px;
      padding:20px 24px;
      display:flex;
      align-items:center;
      gap:18px;
    ">
      <div id="scanRowIcon" style="font-size:2rem;flex-shrink:0;width:42px;text-align:center"></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <div id="scanRowName" style="font-size:1.05rem;font-weight:700;color:var(--white)"></div>
          <div id="scanRowPct" style="font-size:.95rem;font-weight:700;color:var(--coral);min-width:44px;text-align:right">0%</div>
        </div>
        <div id="scanRowMsg" style="font-size:.86rem;color:var(--muted);margin-bottom:10px;min-height:18px"></div>
        <div style="background:rgba(255,255,255,0.07);border-radius:99px;height:6px;overflow:hidden">
          <div id="scanRowBar" style="height:100%;width:0%;border-radius:99px;background:linear-gradient(90deg,var(--coral),#ff8c6b);transition:none"></div>
        </div>
      </div>
    </div>
    <div id="scanCounterLabel" style="text-align:center;margin-top:12px;font-size:.82rem;color:rgba(248,249,255,.3)">
      Parameter 1 of ${SCAN_PARAMS.length}
    </div>`;

  let paramIdx = 0;
  const rowEl = document.getElementById('scanSingleRow');
  const iconEl = document.getElementById('scanRowIcon');
  const nameEl = document.getElementById('scanRowName');
  const msgEl = document.getElementById('scanRowMsg');
  const barEl = document.getElementById('scanRowBar');
  const pctEl = document.getElementById('scanRowPct');
  const ctrEl = document.getElementById('scanCounterLabel');

  function scanNext() {
    // Early exit: API already returned an error — stop animation immediately
    if (_resolvedApiData?.apiError) {
      document.getElementById('paramScanWrap').innerHTML = '';
      document.getElementById('generatingMsg').style.display = 'none';
      showScoreResults();
      return;
    }

    if (paramIdx >= SCAN_PARAMS.length) {
      rowEl.style.opacity = '0';
      setTimeout(() => {
        document.getElementById('paramScanWrap').innerHTML = '';
        document.getElementById('generatingMsg').style.display = 'block';
        document.getElementById('currentParamLabel').textContent = '';
        setTimeout(showScoreResults, 1600);
      }, 380);
      return;
    }

    const p = SCAN_PARAMS[paramIdx];
    document.getElementById('scanStatusText').textContent = 'Scanning ' + p.name + '…';
    document.getElementById('currentParamLabel').textContent = 'Analysing: ' + p.name;
    ctrEl.textContent = `Parameter ${paramIdx + 1} of ${SCAN_PARAMS.length}`;

    iconEl.textContent = p.icon;
    nameEl.textContent = p.name;
    msgEl.textContent = p.msgs[0];
    barEl.style.width = '0%';
    pctEl.textContent = '0%';
    pctEl.style.color = 'var(--coral)';

    requestAnimationFrame(() => {
      requestAnimationFrame(() => { rowEl.style.opacity = '1'; });
    });

    let msgIdx = 1, pct = 0;
    const msgTimer = setInterval(() => {
      if (msgIdx < p.msgs.length) msgEl.textContent = p.msgs[msgIdx++];
    }, 700);

    const barTimer = setInterval(() => {
      pct = Math.min(pct + 2, 100);
      barEl.style.width = pct + '%';
      pctEl.textContent = pct + '%';
      if (pct >= 100) {
        clearInterval(barTimer);
        clearInterval(msgTimer);
        msgEl.textContent = '✓ Done';
        pctEl.style.color = 'var(--lime)';
        pctEl.textContent = '100%';
        paramIdx++;
        setTimeout(() => {
          rowEl.style.opacity = '0';
          setTimeout(scanNext, 360);
        }, 500);
      }
    }, 44);
  }

  // Once screenshot is ready, make overlay semi-transparent and begin scan
  let scanStarted = false;
  function beginScan() {
    if (scanStarted) return;
    scanStarted = true;
    // Transition overlay: screenshot shows through, scan overlay floats on top
    scanOvEl.style.background = 'rgba(5,10,20,.65)';
    scanOvEl.style.backdropFilter = 'blur(1px)';
    scanOvEl.innerHTML = '<div style="width:36px;height:36px;border:3px solid rgba(255,79,46,.3);border-top-color:var(--coral);border-radius:50%;animation:spin .8s linear infinite"></div><div style="font-size:.76rem;color:var(--coral);font-weight:700" id="scanStatusText">Connecting…</div>';
    setTimeout(scanNext, 400);
  }

  // Fetch store screenshot via thum.io (free, no API key)
  const screenshotImg = document.getElementById('scanScreenshot');
  screenshotImg.style.display = 'none';
  screenshotImg.onload = () => { screenshotImg.style.display = 'block'; beginScan(); };
  screenshotImg.onerror = () => setTimeout(beginScan, 300);
  setTimeout(() => beginScan(), 6000); // max wait fallback
  screenshotImg.src = 'https://image.thum.io/get/width/800/crop/500/' + url;
}

async function showScoreResults() {
  document.getElementById('generatingMsg').style.display = 'none';
  const url = document.getElementById('scoreUrl').value;

  // Await real AI scores (fetch started at scan begin — should already be resolved)
  const apiData = await (_scoresPromise || Promise.resolve({ scores: generateDemoScores(url), previousScore: null, verifiedCount: null, unverifiedParams: [], pagesScanned: null }));

  // Handle known API errors (non-ecommerce site, rate limit, etc.)
  if (apiData.apiError) {
    document.getElementById('scanStatusPanel').style.display = 'none';
    document.getElementById('generatingMsg').style.display = 'none';
    // Hide any previous scan results so stale data isn't visible
    document.getElementById('scoreResults').classList.remove('show');
    document.getElementById('scoreResults').style.display = 'none';
    document.getElementById('resetScanBtn').style.display = 'flex';
    document.getElementById('resetScanBtn').style.alignItems = 'center';
    document.getElementById('resetScanBtn').style.justifyContent = 'center';
    const scanOvEl = document.getElementById('scanOverlay');
    scanOvEl.style.background = 'rgba(5,10,20,.88)';
    scanOvEl.style.backdropFilter = 'blur(4px)';
    scanOvEl.innerHTML =
      '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:12px;padding:24px">' +
      '<div style="font-size:2rem">🦉</div>' +
      '<div style="font-size:.88rem;font-weight:700;color:var(--coral)">Scan could not complete</div>' +
      '<div style="font-size:.75rem;color:rgba(248,249,255,.6);max-width:260px;line-height:1.65">' + apiData.apiError + '</div>' +
      '<a href="https://topmate.io/productmentor/1026755" target="_blank" rel="noopener" ' +
      'style="margin-top:4px;padding:8px 18px;background:var(--coral);color:#fff;border-radius:100px;font-size:.75rem;font-weight:700;text-decoration:none">Book Audit Call →</a>' +
      '</div>';
    return;
  }

  const scores          = apiData.scores;
  const previousScore   = apiData.previousScore;
  const verifiedCount   = apiData.verifiedCount;
  const unverifiedParams = apiData.unverifiedParams || [];
  const pagesScanned    = apiData.pagesScanned;
  const jsRendered      = apiData.jsRendered || false;
  // Store scan token so the gate form can attach it to the lead
  window._lastScanToken = apiData.scanToken || '';
  const total = calcOwleyeTotal(scores);
  const upside = calcRevenueUpside(total);
  const band = getScoreBand(total);

  // Keep right panel + scanFrame visible — score badge will overlay the screenshot
  document.getElementById('scanStatusPanel').style.display = 'none';
  document.getElementById('generatingMsg').style.display = 'none';

  // Show refresh button now that scan is complete
  const resetBtn = document.getElementById('resetScanBtn');
  resetBtn.style.display = 'flex';
  resetBtn.style.alignItems = 'center';
  resetBtn.style.justifyContent = 'center';

  // Repurpose scan overlay: show score badge over the store screenshot
  const scanOvEl = document.getElementById('scanOverlay');
  scanOvEl.style.background = 'rgba(5,10,20,.72)';
  scanOvEl.style.backdropFilter = 'blur(2px)';
  scanOvEl.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:4px">' +
    (verifiedCount !== null ? '<div style="font-size:.65rem;color:rgba(248,249,255,.45);font-weight:700;letter-spacing:.07em;text-transform:uppercase;margin-bottom:-2px">Estimated</div>' : '') +
    '<div class="score-number" id="scoreNumber" style="font-family:\'Roboto\',sans-serif;font-size:4rem;font-weight:900;line-height:1">0</div>' +
    '<div style="font-size:.85rem;color:rgba(248,249,255,.6);margin-top:2px">out of 100</div>' +
    '<div class="score-band" id="scoreBand">—</div></div>';

  const resultsEl = document.getElementById('scoreResults');
  resultsEl.style.display = 'block';

  // Animate score counter
  let n = 0;
  const numEl = document.getElementById('scoreNumber');
  const ticker = setInterval(() => {
    n = Math.min(n + 2, total);
    numEl.textContent = n;
    if (n >= total) clearInterval(ticker);
  }, 28);

  setTimeout(() => resultsEl.classList.add('show'), 50);

  // Band
  const bandEl = document.getElementById('scoreBand');
  bandEl.className = 'score-band ' + band.cls;
  bandEl.textContent = band.label;

  // Radar — size adapts to container width so it never overflows on mobile
  setTimeout(() => {
    const radarRow = document.querySelector('.score-radar-row');
    const available = radarRow ? Math.max(220, radarRow.offsetWidth - 20) : 360;
    // Radar shows 6 pillar scores — parameters are secret ingredients
    drawRadar('scoreRadar', getPillarScores(scores), null, Math.min(360, available));
  }, 300);

  // Verified badge — shows how many of 28 params were directly observed
  const vbEl = document.getElementById('verifiedBadge');
  if (vbEl && verifiedCount !== null) {
    const unvCount = 28 - verifiedCount;
    vbEl.innerHTML =
      `<span class="verified-badge-check">✓ ${verifiedCount}/28 parameters verified</span>` +
      (unvCount > 0 ? `<span class="verified-gap">${unvCount} estimated from page signals</span>` : '');
    vbEl.style.display = 'flex';
  }

  // JS-rendered site disclaimer
  const existingJsBanner = document.getElementById('jsRenderedBanner');
  if (existingJsBanner) existingJsBanner.remove();
  if (jsRendered) {
    const jsBanner = document.createElement('div');
    jsBanner.id = 'jsRenderedBanner';
    jsBanner.style.cssText = 'display:flex;align-items:flex-start;gap:8px;background:rgba(255,79,46,.07);border:1px solid rgba(255,79,46,.2);border-radius:10px;padding:10px 14px;font-size:.78rem;color:rgba(248,249,255,.65);line-height:1.6;margin-top:10px';
    jsBanner.innerHTML = '⚡ <span>Your store\'s dynamic content couldn\'t be fully crawled. Some scores are estimated. <a href="https://topmate.io/productmentor/1026755" target="_blank" rel="noopener" style="color:var(--coral);font-weight:700;text-decoration:none">Book an Audit Call →</a> for a complete review.</span>';
    const vbEl = document.getElementById('verifiedBadge');
    if (vbEl && vbEl.parentNode) vbEl.parentNode.insertBefore(jsBanner, vbEl.nextSibling);
  }

  // Gate prompt — surface unverified params as audit hook
  const gpdEl = document.getElementById('gatePromptDesc');
  if (gpdEl && unverifiedParams.length > 0) {
    const sample = unverifiedParams.slice(0, 3).map(p => p.replace(/_/g, ' ')).join(', ');
    gpdEl.innerHTML =
      `🔒 <strong style="color:var(--white)">Get your full report</strong><br>` +
      `${unverifiedParams.length} parameters (${sample}${unverifiedParams.length > 3 ? '…' : ''}) couldn't be verified in this automated scan. Get a complete 28-parameter hands-on audit.`;
  }

  // Pillar bars
  const barsEl = document.getElementById('pillarBars');
  barsEl.innerHTML = '';
  OWLEYE_PILLARS.forEach(pillar => {
    const globalBase = OWLEYE_PILLARS
      .slice(0, OWLEYE_PILLARS.indexOf(pillar))
      .reduce((a, p) => a + p.parameters.length, 0);
    const pScores = pillar.parameters.map((_, pi) => scores[globalBase + pi]);
    const pAvg = Math.round(pScores.reduce((a, b) => a + b, 0) / pScores.length);
    barsEl.innerHTML += `
      <div class="pillar-score-row">
        <span class="pillar-score-name">${pillar.icon} ${pillar.name}</span>
        <div class="pillar-score-bar-wrap"><div class="pillar-score-bar" style="width:0%" data-w="${pAvg}"></div></div>
        <span class="pillar-score-val">${pAvg}/100</span>
      </div>`;
  });
  setTimeout(() => {
    document.querySelectorAll('.pillar-score-bar').forEach(b => { b.style.width = b.dataset.w + '%'; });
  }, 400);

  // Revenue upside
  const upsideEl = document.getElementById('revenueUpside');
  upsideEl.style.display = 'block';
  if (total >= 100) {
    // Perfect score — congratulations state
    upsideEl.innerHTML = `
      <h4>💰 Potential Revenue Upside</h4>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:16px 0;gap:10px">
        <div style="font-size:2.2rem">🏆</div>
        <p style="font-size:.9rem;color:var(--lime);font-weight:700;margin:0">Congratulations — you have a perfect <span class="owleye-brand">OwlEye Score™</span><sup style="font-size:.6em;font-weight:700;background:rgba(255,79,46,.18);color:#FF4F2E;border-radius:4px;padding:1px 5px;margin-left:2px;font-family:Roboto,sans-serif">Beta</sup></p>
        <p style="font-size:.82rem;color:var(--muted);margin:0">Keep it up!</p>
      </div>`;
  } else {
    document.getElementById('upsideExplainer').innerHTML =
      `There is a potential to increase <span class="owleye-brand">OwlEye Score™</span><sup style="font-size:.6em;font-weight:700;background:rgba(255,79,46,.18);color:#FF4F2E;border-radius:4px;padding:1px 5px;margin-left:2px;font-family:Roboto,sans-serif">Beta</sup> to <strong style="color:var(--lime)">${upside.targetScore}</strong> ; estimated to drive <strong style="color:var(--coral)">+${upside.cvrLiftPct}% incremental conversion</strong> (${upside.extraOrders.toLocaleString()} additional orders/month at ₹1,200 AOV).`;
    document.getElementById('upsideMonthly').innerHTML = '<span style="color:var(--lime);font-weight:900">↑</span> ₹' + formatNum(upside.monthlyUpside);
    document.getElementById('upsideAnnual').innerHTML = '<span style="color:var(--lime);font-weight:900">↑</span> ₹' + formatNum(upside.annualUpside);
  }

  // Top 3 fixes — use curated recommendation pool (recommendations.js) where
  // available; fall back to FIXES_DB for low-confidence parameters
  const sorted = scores.map((s, i) => ({ s, i })).sort((a, b) => a.s - b.s).slice(0, 3);
  const fixesEl = document.getElementById('topFixes');
  fixesEl.innerHTML = '<h4>🎯 Top 3 Quick Wins (Free)</h4>';
  sorted.forEach((item, rank) => {
    const paramKey = PARAM_ORDER[item.i];
    const rec      = (typeof getRecommendation === 'function') ? getRecommendation(paramKey, item.s) : null;
    const fallback = FIXES_DB[item.i];
    const title    = rec ? rec.title : fallback.param;
    const fix      = rec ? rec.fix   : fallback.fix;
    fixesEl.innerHTML += `<div class="fix-item"><strong>#${rank + 1} ${title}</strong><p>${fix}</p></div>`;
  });

  // Score change notice — shown below pillar breakdown when same URL was scanned before
  const noticeEl = document.getElementById('scoreChangeNotice');
  if (noticeEl && previousScore !== null && previousScore !== total) {
    const up = total > previousScore;
    noticeEl.className = 'score-change-notice ' + (up ? 'up' : 'down');
    noticeEl.innerHTML =
      `${up ? '↑' : '↓'} Your <strong>OwlEye Score™ beta</strong> has <strong>${up ? 'increased' : 'decreased'}</strong> ` +
      `from <strong>${previousScore}</strong> to <strong>${total}</strong>. ` +
      `If you have not made any changes, this variation could be due to temporary unavailability of some parameters scanned via third-party connections.`;
    noticeEl.style.display = 'block';
  } else if (noticeEl) {
    noticeEl.style.display = 'none';
  }
}

function resetScan() {
  // Clear input; restore editability; button disabled since input is now empty
  document.getElementById('scoreUrl').value = '';
  document.getElementById('scoreUrl').readOnly = false;
  document.getElementById('scoreBtn').disabled = true;
  document.getElementById('urlError').style.display = 'none';
  // Reset right panel to empty state
  document.getElementById('screenshotPlaceholder').style.display = 'flex';
  document.getElementById('scanFrame').style.display = 'none';
  document.getElementById('scanStatusPanel').style.display = 'none';
  document.getElementById('scoreResults').classList.remove('show');
  document.getElementById('scoreResults').style.display = 'none';
  document.getElementById('generatingMsg').style.display = 'none';
  document.getElementById('paramScanWrap').innerHTML = '';
  // Clear screenshot src to avoid stale image on next run
  document.getElementById('scanScreenshot').src = '';
  // Reset scan overlay back to initial "Fetching screenshot…" state
  const scanOvEl = document.getElementById('scanOverlay');
  if (scanOvEl) {
    scanOvEl.style.background = 'rgba(5,10,20,.93)';
    scanOvEl.style.backdropFilter = '';
    scanOvEl.innerHTML = '<div style="width:36px;height:36px;border:3px solid rgba(255,79,46,.3);border-top-color:var(--coral);border-radius:50%;animation:spin .8s linear infinite"></div><div style="font-size:.76rem;color:var(--coral);font-weight:700" id="scanStatusText">Fetching screenshot…</div>';
  }
  // Hide refresh button until next scan completes
  document.getElementById('resetScanBtn').style.display = 'none';
  // Clear score change notice
  const noticeEl = document.getElementById('scoreChangeNotice');
  if (noticeEl) noticeEl.style.display = 'none';
  // Clear verified badge
  const vbEl2 = document.getElementById('verifiedBadge');
  if (vbEl2) vbEl2.style.display = 'none';
  // Reset gate form back to initial state for next scan
  const gateFormWrap = document.getElementById('gateFormWrap');
  const gateSuccess  = document.getElementById('gateSuccess');
  if (gateFormWrap) gateFormWrap.style.display = 'block';
  if (gateSuccess)  { gateSuccess.style.display = 'none'; gateSuccess.innerHTML = ''; }
  const gateNameEl  = document.getElementById('gateName');
  const gateEmailEl = document.getElementById('gateEmail');
  const gateErrEl   = document.getElementById('gateError');
  const gateBtn     = document.getElementById('reportSubmitBtn');
  if (gateNameEl)  gateNameEl.value  = '';
  if (gateEmailEl) gateEmailEl.value = '';
  if (gateErrEl)   gateErrEl.style.display = 'none';
  if (gateBtn)     { gateBtn.disabled = true; gateBtn.textContent = 'Send Me the Full Report →'; }
  window._lastScanToken = '';
  // Focus URL input for quick re-entry
  document.getElementById('scoreUrl').focus();
}

const PERSONAL_EMAIL_DOMAINS = [
  'gmail.com', 'googlemail.com',
  'yahoo.com', 'yahoo.in', 'yahoo.co.in', 'ymail.com',
  'hotmail.com', 'hotmail.in', 'live.com', 'live.in', 'outlook.com', 'msn.com',
  'aol.com',
  'icloud.com', 'me.com', 'mac.com',
  'protonmail.com', 'pm.me',
  'rediffmail.com',
];

function unlockFullReport() {
  const name    = document.getElementById('gateName').value.trim();
  const email   = document.getElementById('gateEmail').value.trim().toLowerCase();
  const errEl   = document.getElementById('gateError');
  const btn     = document.getElementById('reportSubmitBtn');

  function showErr(msg) { errEl.textContent = msg; errEl.style.display = 'block'; }
  errEl.style.display = 'none';

  // Name validation
  if (!name || name.length < 2) { showErr('Please enter your full name.'); return; }

  // Email format
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showErr('Please enter a valid email address.'); return; }

  // Work email only
  const domain = email.split('@')[1] || '';
  if (PERSONAL_EMAIL_DOMAINS.includes(domain)) {
    showErr('Please fill correct details to get the report. Use your work email, not a personal address.');
    return;
  }

  // Loading state
  btn.disabled = true;
  btn.textContent = 'Sending…';

  const scannedUrl = document.getElementById('scoreUrl').value;

  fetch('api/save-lead.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ name, email, url: scannedUrl, scan_token: window._lastScanToken || '' }),
  })
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        showErr(data.error);
        btn.disabled = false;
        btn.textContent = 'Send Me the Full Report →';
        return;
      }
      // Success
      document.getElementById('gateFormWrap').style.display = 'none';
      const successEl = document.getElementById('gateSuccess');
      successEl.style.display = 'block';
      successEl.innerHTML =
        '<div style="font-size:1.5rem;margin-bottom:10px">✅</div>' +
        '<p style="color:var(--white);font-weight:700;margin-bottom:6px">You\'re on the list!</p>' +
        '<p style="font-size:.82rem;color:rgba(248,249,255,.6);margin-bottom:16px">You\'ll receive your full report at <strong style="color:var(--white)">' + email + '</strong> soon.</p>' +
        '<a href="https://topmate.io/productmentor/1026755" target="_blank" rel="noopener" class="btn btn-coral" style="font-size:.84rem">Book Free Audit Call →</a>';
    })
    .catch(() => {
      showErr('Something went wrong. Please try again.');
      btn.disabled = false;
      btn.textContent = 'Send Me the Full Report →';
    });
}

// ─────────────────────────────────────────
// REVENUE CALCULATOR
// ─────────────────────────────────────────
function formatNum(n) {
  if (n >= 10000000) return (n / 10000000).toFixed(1) + 'Cr';
  if (n >= 100000) return (n / 100000).toFixed(1) + 'L';
  if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
  return Math.round(n).toString();
}
function fmtRupee(n) { return '₹' + formatNum(Math.round(n)); }

let lastUpside = 0;
let calcAnimated = false;

function updateCalc() {
  const v    = +document.getElementById('calcVisitors').value || 0;
  const cvr  = +document.getElementById('calcCVR').value || 0;
  const aov  = +document.getElementById('calcAOV').value || 0;
  const lift = +document.getElementById('croSlider').value;   // direct CVR lift %

  // Update slider label — CVR only, no points
  document.getElementById('sliderVal').textContent =
    '+' + (lift % 1 === 0 ? lift : lift.toFixed(1)) + '% Incremental CVR';

  // Current revenue always updates when inputs are filled
  if (v && cvr && aov) {
    const currentRev = v * (cvr / 100) * aov;
    document.getElementById('calcLiveRevVal').textContent = fmtRupee(currentRev) + '/mo';
    document.getElementById('calcCurrentRev').textContent = fmtRupee(currentRev);
    document.getElementById('calcCurrentSub').textContent =
      `${v.toLocaleString()} visitors × ${cvr}% CVR × ₹${aov.toLocaleString()}`;

    if (lift > 0) {
      const liftedCVR = cvr + lift;
      const optRev    = v * (liftedCVR / 100) * aov;
      const monthly   = optRev - currentRev;

      document.getElementById('calcOptRev').textContent    = fmtRupee(optRev);
      document.getElementById('calcCVRNote').textContent   = `CVR: ${cvr}% → ${liftedCVR.toFixed(1)}% (+${lift}%)`;
      document.getElementById('calcMonthlyUpside').textContent = fmtRupee(monthly);
      document.getElementById('calcAnnualUpside').textContent  = fmtRupee(monthly * 12);

      // Confetti on first meaningful upside
      if (monthly > 0 && !calcAnimated) {
        calcAnimated = true;
        setTimeout(() => launchConfetti(), 200);
      }
      lastUpside = monthly;
    } else {
      // Slider at 0 — clear uplift cards
      document.getElementById('calcOptRev').textContent        = '₹ —';
      document.getElementById('calcCVRNote').textContent       = 'Move the slider to see potential';
      document.getElementById('calcMonthlyUpside').textContent = '₹ —';
      document.getElementById('calcAnnualUpside').textContent  = '₹ —';
    }
  }
}

function resetCalc() {
  document.getElementById('calcVisitors').value = '';
  document.getElementById('calcCVR').value      = '';
  document.getElementById('calcAOV').value      = '';
  document.getElementById('croSlider').value    = '0';
  document.getElementById('calcLiveRevVal').textContent    = '—';
  document.getElementById('calcCurrentRev').textContent   = '₹ —';
  document.getElementById('calcCurrentSub').textContent   = 'Enter your numbers to calculate';
  document.getElementById('calcOptRev').textContent       = '₹ —';
  document.getElementById('calcCVRNote').textContent      = 'With improved CVR';
  document.getElementById('calcMonthlyUpside').textContent = '₹ —';
  document.getElementById('calcAnnualUpside').textContent  = '₹ —';
  document.getElementById('sliderVal').textContent         = '+0% Incremental CVR';
  calcAnimated = false;
}

// ─────────────────────────────────────────
// CONFETTI
// ─────────────────────────────────────────
function launchConfetti() {
  const canvas = document.getElementById('confettiCanvas');
  const ctx = canvas.getContext('2d');
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;

  const COLORS = ['#FF4F2E', '#0EA5E9', '#A8E535', '#F5C518', '#A855F7', '#fff'];
  const pieces = Array.from({ length: 120 }, () => ({
    x: Math.random() * canvas.width,
    y: -10 - Math.random() * 100,
    r: 4 + Math.random() * 6,
    d: 2 + Math.random() * 3,
    color: COLORS[Math.floor(Math.random() * COLORS.length)],
    tilt: Math.random() * 20 - 10,
    tiltAngle: 0,
    tiltSpeed: 0.04 + Math.random() * 0.04,
  }));

  let frame = 0;
  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    frame++;
    pieces.forEach(p => {
      p.tiltAngle += p.tiltSpeed;
      p.y += p.d;
      p.tilt = Math.sin(p.tiltAngle) * 12;
      ctx.beginPath();
      ctx.lineWidth = p.r;
      ctx.strokeStyle = p.color;
      ctx.moveTo(p.x + p.tilt + p.r / 2, p.y);
      ctx.lineTo(p.x + p.tilt, p.y + p.tilt + p.r / 2);
      ctx.stroke();
    });
    if (frame < 180) requestAnimationFrame(draw);
    else ctx.clearRect(0, 0, canvas.width, canvas.height);
  }
  draw();
}

// ─────────────────────────────────────────
// FAQ
// ─────────────────────────────────────────
document.querySelectorAll('.faq-q').forEach(q => {
  q.addEventListener('click', () => {
    const item = q.parentElement;
    const wasOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
    if (!wasOpen) item.classList.add('open');
  });
});

// ─────────────────────────────────────────
// MODAL
// ─────────────────────────────────────────
function openModal() { document.getElementById('modal').classList.add('active'); document.body.style.overflow = 'hidden'; }
function closeModal() { document.getElementById('modal').classList.remove('active'); document.body.style.overflow = ''; }
function goStep(n) {
  document.querySelectorAll('.modal-step').forEach(s => s.classList.remove('active'));
  document.getElementById('mstep' + n).classList.add('active');
}
document.getElementById('modal').addEventListener('click', function (e) { if (e.target === this) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

// ─────────────────────────────────────────
// DISABLE ANALYSE BUTTON WHEN INPUT EMPTY
// ─────────────────────────────────────────
(function initUrlInputState() {
  const inp = document.getElementById('scoreUrl');
  const btn = document.getElementById('scoreBtn');
  btn.disabled = true;
  inp.addEventListener('input', () => { btn.disabled = !inp.value.trim(); });
})();

// ─────────────────────────────────────────
// DISABLE GATE SUBMIT UNTIL BOTH FIELDS FILLED
// ─────────────────────────────────────────
(function initGateInputs() {
  const nameInp = document.getElementById('gateName');
  const emailInp = document.getElementById('gateEmail');
  const btn = document.getElementById('reportSubmitBtn');
  function checkGate() { btn.disabled = !nameInp.value.trim() || !emailInp.value.trim(); }
  nameInp.addEventListener('input', checkGate);
  emailInp.addEventListener('input', checkGate);
})();
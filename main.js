/* ═══════════════════════════════════════════════════════════════
   THE OWL CO — main.js
   ═══════════════════════════════════════════════════════════════ */

// ─────────────────────────────────────────
// FLIP HEADLINE
// ─────────────────────────────────────────
const FLIP_WORDS = ['browsing.', 'comparing.', 'hesitating.', 'abandoning.', 'distracted.', 'price-checking.', 'stuck.', 'just looking.'];
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
const RADAR_LABELS = ['Checkout', 'Payment', 'Cart Recovery', 'Landing Page', 'Product Pages', 'Trust', 'Returns', 'Cross-sell', 'Mobile UX'];

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

  drawPoly(avgScores, 'rgba(14,165,233,0.1)', 'rgba(14,165,233,0.55)');
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
const HERO_SCENARIOS = [
  [58, 65, 72, 55, 68, 60, 75, 50, 62],
  [72, 48, 85, 62, 55, 78, 42, 68, 58],
  [45, 80, 60, 75, 42, 55, 88, 52, 70],
  [88, 62, 50, 45, 78, 65, 58, 75, 42],
  [55, 72, 68, 82, 50, 70, 48, 60, 85],
];
const HERO_AVG = [70, 68, 65, 72, 70, 68, 72, 66, 70];
let heroSceneIdx = 0, heroAnimPct = 0, heroRaf = null;
let heroFrom = [...HERO_SCENARIOS[0]];
let heroTo = [...HERO_SCENARIOS[0]];

function lerp(a, b, t) { return a.map((v, i) => v + (b[i] - v) * t); }

function animateHeroRadar() {
  heroAnimPct = Math.min(heroAnimPct + 2, 100);
  const t = 1 - Math.pow(1 - heroAnimPct / 100, 3);
  drawRadar('heroRadar', lerp(heroFrom, heroTo, t), HERO_AVG, 380);
  if (heroAnimPct < 100) heroRaf = requestAnimationFrame(animateHeroRadar);
}
animateHeroRadar();

setInterval(() => {
  heroFrom = lerp(heroFrom, heroTo, 1);
  heroSceneIdx = (heroSceneIdx + 1) % HERO_SCENARIOS.length;
  heroTo = [...HERO_SCENARIOS[heroSceneIdx]];
  heroAnimPct = 0;
  if (heroRaf) cancelAnimationFrame(heroRaf);
  animateHeroRadar();
}, 3000);

// ─────────────────────────────────────────
// OWLEYE SCORE TOOL
// ─────────────────────────────────────────
const SCAN_PARAMS = [
  { name: 'Checkout Flow', icon: '🛒', msgs: ['Mapping checkout steps…', 'Counting form fields…', 'Checking progress indicators…'] },
  { name: 'Payment Options', icon: '💳', msgs: ['Detecting payment methods…', 'Checking UPI/COD support…', 'Validating payment UX…'] },
  { name: 'Cart Recovery', icon: '🔄', msgs: ['Scanning cart behaviour…', 'Checking abandonment triggers…', 'Analysing recovery flows…'] },
  { name: 'Landing Page', icon: '📄', msgs: ['Reading above-fold content…', 'Scoring CTA placement…', 'Evaluating headline clarity…'] },
  { name: 'Product Pages', icon: '🖼️', msgs: ['Inspecting product images…', 'Checking reviews section…', 'Analysing buy-button area…'] },
  { name: 'Trust Signals', icon: '🤝', msgs: ['Looking for trust badges…', 'Scanning social proof…', 'Checking security indicators…'] },
  { name: 'Returns Policy', icon: '📋', msgs: ['Locating returns policy…', 'Evaluating visibility…', 'Scoring policy clarity…'] },
  { name: 'Cross-sell', icon: '⚡', msgs: ['Detecting upsell modules…', 'Checking bundle offers…', 'Scoring AOV optimisation…'] },
  { name: 'Mobile UX', icon: '📱', msgs: ['Simulating mobile viewport…', 'Checking tap targets…', 'Measuring scroll depth…'] },
];

const FIXES_DB = [
  { param: 'Checkout Flow', fix: 'Add a progress indicator — reduces abandonment by up to 18%.' },
  { param: 'Payment Options', fix: 'Add UPI and COD — 62% of Indian shoppers abandon without preferred payment.' },
  { param: 'Cart Recovery', fix: 'Set up exit-intent popup + 3-email abandoned cart sequence (1h/24h/72h).' },
  { param: 'Landing Page', fix: 'Move primary CTA above the fold with a benefit-led headline.' },
  { param: 'Product Pages', fix: 'Add 4+ images, a video, and size guide — lifts conversion by 24%.' },
  { param: 'Trust Signals', fix: 'Display verified photo reviews and trust badges near the buy button.' },
  { param: 'Returns Policy', fix: 'Show "30-day easy returns" prominently near CTA — removes #1 objection.' },
  { param: 'Cross-sell', fix: 'Add "Frequently bought together" bundles — 12–18% AOV uplift.' },
  { param: 'Mobile UX', fix: 'Reduce mobile checkout to 2 screens. 67% of Indian traffic is mobile.' },
];

function runScoreAnalysis() {
  let raw = document.getElementById('scoreUrl').value.trim();
  const urlError = document.getElementById('urlError');

  // Normalize: prepend https:// if no protocol given
  if (raw && !/^https?:\/\//i.test(raw)) raw = 'https://' + raw;
  document.getElementById('scoreUrl').value = raw;

  let valid = false;
  try { valid = /^https?:\/\/.+\..+/.test(new URL(raw).href); } catch(e) {}
  if (!raw || !valid) {
    urlError.style.display = 'block';
    document.getElementById('scoreUrl').focus();
    return;
  }
  urlError.style.display = 'none';

  const url = raw;
  document.getElementById('scoreBtn').disabled = true;
  document.getElementById('screenshotPlaceholder').style.display = 'none';
  document.getElementById('scoreResults').classList.remove('show');
  document.getElementById('scoreResults').style.display = 'none';

  // Show scan frame
  document.getElementById('scanFrame').style.display = 'grid';
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
      border-radius:12px;
      padding:16px 18px;
      display:flex;
      align-items:center;
      gap:14px;
    ">
      <div id="scanRowIcon" style="font-size:1.6rem;flex-shrink:0;width:36px;text-align:center"></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
          <div id="scanRowName" style="font-size:.9rem;font-weight:700;color:var(--white)"></div>
          <div id="scanRowPct" style="font-size:.82rem;font-weight:700;color:var(--coral);min-width:38px;text-align:right">0%</div>
        </div>
        <div id="scanRowMsg" style="font-size:.74rem;color:var(--muted);margin-bottom:8px;min-height:16px"></div>
        <div style="background:rgba(255,255,255,0.07);border-radius:99px;height:5px;overflow:hidden">
          <div id="scanRowBar" style="height:100%;width:0%;border-radius:99px;background:linear-gradient(90deg,var(--coral),#ff8c6b);transition:none"></div>
        </div>
      </div>
    </div>
    <div id="scanCounterLabel" style="text-align:center;margin-top:10px;font-size:.72rem;color:rgba(248,249,255,.3)">
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

function showScoreResults() {
  document.getElementById('generatingMsg').style.display = 'none';
  const url = document.getElementById('scoreUrl').value;

  // Use owleye-ai.js generateDemoScores
  const scores = generateDemoScores(url);
  const total = calcOwleyeTotal(scores);
  const upside = calcRevenueUpside(total);
  const band = getScoreBand(total);

  // Hide scan frame, show results frame (both are side-by-side grids)
  document.getElementById('scanFrame').style.display = 'none';
  document.getElementById('generatingMsg').style.display = 'none';

  // Populate result browser URL bar
  const resultUrlEl = document.getElementById('resultUrl');
  if (resultUrlEl) resultUrlEl.textContent = url;

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

  // Radar
  setTimeout(() => drawRadar('scoreRadar', scores, OWLEYE_INDUSTRY_AVG, 260), 300);

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
  document.getElementById('revenueUpside').style.display = 'block';
  document.getElementById('upsideExplainer').innerHTML =
    `Improving your <span class="owleye-brand">OwlEye Score™</span><sup style="font-size:.6em;font-weight:700;background:rgba(255,79,46,.18);color:#FF4F2E;border-radius:4px;padding:1px 5px;margin-left:2px;font-family:Roboto,sans-serif">Beta</sup> from <strong style="color:var(--white)">${total}</strong> to <strong style="color:var(--lime)">100</strong> is a <strong style="color:var(--white)">+${upside.scoreGap} point gain</strong> — estimated to drive <strong style="color:var(--coral)">+${upside.cvrLiftPct}% incremental conversion</strong> (${upside.extraOrders.toLocaleString()} additional orders/month at ₹1,200 AOV).`;
  document.getElementById('upsideMonthly').textContent = '₹' + formatNum(upside.monthlyUpside);
  document.getElementById('upsideAnnual').textContent = '₹' + formatNum(upside.annualUpside);

  // Top 3 fixes
  const sorted = scores.map((s, i) => ({ s, i })).sort((a, b) => a.s - b.s).slice(0, 3);
  const fixesEl = document.getElementById('topFixes');
  fixesEl.innerHTML = '<h4>🎯 Top 3 Quick Wins (Free)</h4>';
  sorted.forEach((item, rank) => {
    const fix = FIXES_DB[item.i];
    fixesEl.innerHTML += `<div class="fix-item"><strong>#${rank + 1} ${fix.param}</strong>${fix.fix}</div>`;
  });
}

function unlockFullReport() {
  const name = document.getElementById('gateName').value.trim();
  const email = document.getElementById('gateEmail').value.trim();
  if (!name || !email) { alert('Please enter your name and email'); return; }
  document.getElementById('gatePrompt').innerHTML = `
    <p style="color:var(--lime)">✅ <strong style="color:var(--white)">Report on its way!</strong><br>
    Check ${email} for your full <span class="owleye-brand">OwlEye Score™</span><sup style="font-size:.6em;font-weight:700;background:rgba(255,79,46,.18);color:#FF4F2E;border-radius:4px;padding:1px 5px;margin-left:2px;font-family:Roboto,sans-serif">Beta</sup> breakdown.</p>
    <a href="#" class="btn btn-coral" style="margin-top:12px" onclick="openModal();return false">Book Free Strategy Call →</a>`;
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
  const v = +document.getElementById('calcVisitors').value || 0;
  const cvr = +document.getElementById('calcCVR').value || 0;
  const aov = +document.getElementById('calcAOV').value || 0;
  const pts = +document.getElementById('croSlider').value || 20;

  // Update slider display
  const impliedCvrLift = (pts * 0.08).toFixed(1);
  document.getElementById('sliderVal').textContent = '+' + pts + ' pts  (+' + impliedCvrLift + '% CVR)';
  document.getElementById('sliderCVRNote').textContent =
    `Each 10-point improvement ≈ +0.8% CVR lift`;

  if (!v || !cvr || !aov) return;

  const currentRev = v * (cvr / 100) * aov;
  document.getElementById('calcLiveRevVal').textContent = fmtRupee(currentRev) + '/mo';

  const liftedCVR = cvr + parseFloat(impliedCvrLift);
  const optRev = v * (liftedCVR / 100) * aov;
  const monthly = optRev - currentRev;

  document.getElementById('calcCurrentRev').textContent = fmtRupee(currentRev);
  document.getElementById('calcCurrentSub').textContent = `${v.toLocaleString()} visitors × ${cvr}% CVR × ₹${aov.toLocaleString()}`;
  document.getElementById('calcOptRev').textContent = fmtRupee(optRev);
  document.getElementById('calcCVRNote').textContent = `CVR improved: ${cvr}% → ${liftedCVR.toFixed(1)}% (+${impliedCvrLift}%)`;
  document.getElementById('calcMonthlyUpside').textContent = fmtRupee(monthly);
  document.getElementById('calcAnnualUpside').textContent = fmtRupee(monthly * 12);

  // Confetti on first meaningful upside generation
  if (monthly > 0 && !calcAnimated) {
    calcAnimated = true;
    setTimeout(() => launchConfetti(), 200);
  }
  lastUpside = monthly;
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
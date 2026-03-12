# GenAI Tech Labs

AI-powered CRO agency website for Indian ecommerce brands. Features the **OwlEye Score™** — a free instant audit tool that analyses 28 parameters across 6 conversion pillars.

**Live:** [genaitechlabs.com](https://genaitechlabs.com)

---

## What's in this repo

| File | Purpose |
|------|---------|
| `index.html` | Single-page site — all sections |
| `styles.css` | All styles and CSS variables |
| `main.js` | UI interactions, radar charts, revenue calculator, modal |
| `owleye-ai.js` | OwlEye Score™ engine — 28 parameters × 6 pillars |
| `recommendations.js` | Fix copy and top-3 recommendation logic |
| `api/` | PHP backend — scoring, leads, pagespeed, screenshot |

---

## OwlEye Score™

The core feature. Paste a store URL → AI scans 28 parameters across 6 pillars → outputs a score, pillar breakdown, revenue upside estimate, and top 3 fix recommendations.

**6 Pillars:**
1. Purchase Flow
2. Page Experience
3. Trust & Conversion
4. Engagement & Retention
5. Agentic Commerce
6. Technical Foundation

Results are gated behind email capture (name + work email).

---

## Local Setup

No build step required — pure static files + PHP backend.

**Frontend:**
```bash
# Open index.html directly or serve with any static server
npx serve .
```

**Backend (API):**
```bash
cd api
cp config.sample.php config.php
# Edit config.php with your DB credentials, API keys
```

**Required API keys (in `api/config.php`):**
- Claude API key (for AI scoring)
- Google PageSpeed API key
- Screenshot service credentials
- MySQL database connection

---

## Deployment

Static files served from root. PHP API lives in `/api/`. No build step — deploy by syncing files to your server.

```bash
# Example: rsync to server
rsync -avz --exclude='api/config.php' . user@server:/var/www/genaitechlabs.com/
```

> Never deploy `api/config.php` — it contains secrets. Use `config.sample.php` as reference.

---

## Branching

- `develop` — active development
- `main` — production

---

## Tech

- Vanilla HTML/CSS/JS (no framework, no build tool)
- Chart.js for radar charts
- PHP + MySQL for API and lead storage
- Google Fonts (Roboto)
- Google PageSpeed API
- Claude API for intelligent scoring

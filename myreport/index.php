<?php
/* ═══════════════════════════════════════════════════════════════
   OwlEye Admin — admin/index.php
   Single-file dashboard: 3 tabs (Booking Leads / Score Leads / Scans)
   Session-based auth, no DB needed for login.
   ═══════════════════════════════════════════════════════════════ */

session_start();

define('ADMIN_USER',      'theowladmin');
define('ADMIN_PASS_HASH', '$2y$10$tgV2uRVtSmK9jp2DouVNJ.vjT5eXLw/oTzxHYeVrXZWijYX1L3l4q');
define('PER_PAGE', 25);

// Pillar display config — id → short label
$PILLARS = [
    'purchase_flow'        => 'Purchase',
    'page_experience'      => 'UX',
    'trust_conversion'     => 'Trust',
    'engagement_retention' => 'Engagement',
    'agentic_commerce'     => 'Agentic',
    'technical_foundation' => 'Technical',
];

// ── Helpers ────────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function trunc(?string $s, int $n = 42): string {
    $s = $s ?? '';
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s;
}
function scoreColor(int $s): string {
    if ($s >= 70) return '#4ade80';
    if ($s >= 50) return '#fbbf24';
    return '#f87171';
}
function tabUrl(string $tab, int $page = 1): string {
    return '?tab=' . urlencode($tab) . '&page=' . $page;
}
function getCount(PDO $db, string $table): int {
    return (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}
function pager(string $tab, int $pg, int $pages): void {
    if ($pages <= 1) return;
    echo '<div class="pager">';
    if ($pg > 1)       echo '<a href="' . tabUrl($tab, $pg - 1) . '">← Prev</a>';
    $lo = max(1, $pg - 2); $hi = min($pages, $pg + 2);
    if ($lo > 1) { echo '<a href="' . tabUrl($tab, 1) . '">1</a>'; if ($lo > 2) echo '<span class="dots">…</span>'; }
    for ($i = $lo; $i <= $hi; $i++) {
        echo $i === $pg
            ? '<span class="cur">' . $i . '</span>'
            : '<a href="' . tabUrl($tab, $i) . '">' . $i . '</a>';
    }
    if ($hi < $pages) { if ($hi < $pages - 1) echo '<span class="dots">…</span>'; echo '<a href="' . tabUrl($tab, $pages) . '">' . $pages . '</a>'; }
    if ($pg < $pages) echo '<a href="' . tabUrl($tab, $pg + 1) . '">Next →</a>';
    echo '</div>';
}

// ── Logout ─────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ── Login ──────────────────────────────────────────────────────
$loginErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    if ($_POST['username'] === ADMIN_USER && password_verify($_POST['password'] ?? '', ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_auth'] = true;
        header('Location: index.php');
        exit;
    }
    $loginErr = 'Invalid username or password.';
}

// ── Guard ──────────────────────────────────────────────────────
if (empty($_SESSION['admin_auth'])) { ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>OwlEye Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0B1120;color:#F8F9FF;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#141E2E;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:44px 40px;width:360px}
.logo{font-size:1.4rem;font-weight:800;color:#FF4F2E;margin-bottom:4px}
.sub{font-size:.78rem;color:rgba(248,249,255,.38);margin-bottom:30px}
label{display:block;font-size:.76rem;font-weight:600;color:rgba(248,249,255,.55);margin-bottom:5px;text-transform:uppercase;letter-spacing:.05em}
input{width:100%;background:#0B1120;border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:10px 13px;color:#F8F9FF;font-size:.9rem;margin-bottom:18px;outline:none;transition:.15s}
input:focus{border-color:#FF4F2E}
button{width:100%;background:#FF4F2E;color:#fff;border:none;border-radius:8px;padding:12px;font-size:.93rem;font-weight:700;cursor:pointer;transition:.15s}
button:hover{background:#e8442a}
.err{color:#f87171;font-size:.82rem;margin-bottom:16px;padding:9px 13px;background:rgba(248,113,113,.1);border-radius:7px;border-left:3px solid #f87171}
</style>
</head>
<body>
<div class="card">
  <div class="logo">🦉 OwlEye Admin</div>
  <p class="sub">GenAI Tech Labs — restricted access</p>
  <?php if ($loginErr): ?><div class="err"><?= h($loginErr) ?></div><?php endif; ?>
  <form method="post">
    <label>Username</label>
    <input type="text" name="username" autocomplete="username" autofocus required>
    <label>Password</label>
    <input type="password" name="password" autocomplete="current-password" required>
    <button type="submit">Sign In →</button>
  </form>
</div>
</body>
</html>
<?php exit; }

// ── Dashboard ──────────────────────────────────────────────────
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';

$db   = getDB();

// Ensure all tables exist (created on first API use; admin may load before any submissions)
$db->exec('CREATE TABLE IF NOT EXISTS booking_leads (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    first_name     VARCHAR(50)   NOT NULL,
    last_name      VARCHAR(50)   NOT NULL,
    email          VARCHAR(255)  NOT NULL,
    whatsapp       VARCHAR(20)   DEFAULT NULL,
    url            VARCHAR(512)  DEFAULT NULL,
    revenue_range  VARCHAR(30)   DEFAULT NULL,
    visitors_range VARCHAR(20)   DEFAULT NULL,
    platform       VARCHAR(30)   DEFAULT NULL,
    ip             VARCHAR(64)   DEFAULT NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email), INDEX idx_ip (ip), INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
try { $db->exec("ALTER TABLE booking_leads ADD COLUMN whatsapp VARCHAR(20) DEFAULT NULL AFTER email"); } catch (PDOException $e) { /* column exists */ }

$db->exec('CREATE TABLE IF NOT EXISTS owleye_leads (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255)  NOT NULL,
    email      VARCHAR(255)  NOT NULL,
    url        VARCHAR(512)  DEFAULT NULL,
    scan_token VARCHAR(64)   DEFAULT NULL,
    ip         VARCHAR(64)   DEFAULT NULL,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email), INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$tab  = in_array($_GET['tab'] ?? '', ['booking', 'leads', 'scans']) ? $_GET['tab'] : 'booking';
$pg   = max(1, (int)($_GET['page'] ?? 1));
$off  = ($pg - 1) * PER_PAGE;

// Tab counts (for badges)
$counts = [];
foreach (['booking' => 'booking_leads', 'leads' => 'owleye_leads', 'scans' => 'owleye_scans'] as $k => $tbl) {
    try { $counts[$k] = getCount($db, $tbl); } catch (Exception $e) { $counts[$k] = '?'; }
}

// Data fetch
try {
    if ($tab === 'booking') {
        $total = getCount($db, 'booking_leads');
        $stmt  = $db->prepare('SELECT id, first_name, last_name, email, whatsapp, url, revenue_range, visitors_range, platform, ip, created_at FROM booking_leads ORDER BY created_at DESC LIMIT ? OFFSET ?');
    } elseif ($tab === 'leads') {
        $total = getCount($db, 'owleye_leads');
        $stmt  = $db->prepare('SELECT id, name, email, url, scan_token, ip, created_at FROM owleye_leads ORDER BY created_at DESC LIMIT ? OFFSET ?');
    } else {
        $total = getCount($db, 'owleye_scans');
        $stmt  = $db->prepare('SELECT id, url, owleye_score, pillar_scores, ip, created_at FROM owleye_scans ORDER BY created_at DESC LIMIT ? OFFSET ?');
    }
    $stmt->execute([PER_PAGE, $off]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows  = [];
    $total = 0;
    $dbErr = $e->getMessage();
}

$pages = $total > 0 ? (int) ceil($total / PER_PAGE) : 1;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>OwlEye Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#0B1120;color:#F8F9FF;font-size:.875rem;min-height:100vh}
a{color:inherit;text-decoration:none}

/* Topbar */
.top{background:#141E2E;border-bottom:1px solid rgba(255,255,255,.07);padding:13px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
.top-logo{font-size:.95rem;font-weight:800;color:#FF4F2E}
.top-right{display:flex;align-items:center;gap:18px}
.top-site{font-size:.75rem;color:rgba(248,249,255,.35)}
.btn-out{font-size:.76rem;color:rgba(248,249,255,.5);border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:5px 12px;background:none;cursor:pointer;transition:.15s}
.btn-out:hover{color:#F8F9FF;border-color:rgba(255,255,255,.3)}

/* Wrap */
.wrap{max-width:1360px;margin:0 auto;padding:24px 20px}

/* Tabs */
.tabs{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.tab{padding:8px 18px;border-radius:8px;font-size:.81rem;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.1);color:rgba(248,249,255,.55);transition:.15s}
.tab:hover{background:rgba(255,255,255,.04);color:#F8F9FF}
.tab.on{background:#FF4F2E;border-color:#FF4F2E;color:#fff}
.badge{background:rgba(0,0,0,.28);border-radius:999px;padding:1px 7px;font-size:.7rem;margin-left:5px;font-weight:700}

/* Card */
.card{background:#141E2E;border:1px solid rgba(255,255,255,.07);border-radius:12px;overflow:hidden}
.card-hd{padding:13px 18px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;justify-content:space-between}
.card-hd strong{font-size:.85rem}
.card-hd span{font-size:.75rem;color:rgba(248,249,255,.38)}

/* Table */
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:680px}
th{text-align:left;padding:9px 14px;font-size:.7rem;text-transform:uppercase;letter-spacing:.07em;color:rgba(248,249,255,.38);border-bottom:1px solid rgba(255,255,255,.06);white-space:nowrap;font-weight:700}
td{padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.04);font-size:.81rem;color:rgba(248,249,255,.82);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.022)}

/* Score badge */
.score{display:inline-block;font-weight:800;font-size:.82rem;padding:2px 9px;border-radius:6px;background:rgba(0,0,0,.35);min-width:38px;text-align:center}

/* Misc */
.pill{display:inline-block;font-size:.7rem;padding:2px 8px;border-radius:999px;background:rgba(255,255,255,.07);color:rgba(248,249,255,.7);white-space:nowrap}
.muted{color:rgba(248,249,255,.33);font-size:.76rem}
.url a{color:#38bdf8}
.url a:hover{text-decoration:underline}
.token{font-family:monospace;font-size:.71rem;color:rgba(248,249,255,.38)}

/* Pager */
.pager{display:flex;gap:6px;align-items:center;justify-content:center;padding:20px;flex-wrap:wrap}
.pager a,.pager span{padding:6px 14px;border-radius:7px;font-size:.79rem;border:1px solid rgba(255,255,255,.1)}
.pager a{color:rgba(248,249,255,.65);transition:.15s}
.pager a:hover{background:rgba(255,255,255,.06);color:#F8F9FF}
.pager .cur{background:#FF4F2E;border-color:#FF4F2E;color:#fff;font-weight:700}
.pager .dots{border:none;color:rgba(248,249,255,.3)}

.empty{text-align:center;padding:60px 20px;color:rgba(248,249,255,.3)}
.empty big{display:block;font-size:2.4rem;margin-bottom:12px}
.dberr{padding:20px;color:#f87171;font-size:.82rem;font-family:monospace}
</style>
</head>
<body>

<div class="top">
  <div class="top-logo">🦉 OwlEye Admin</div>
  <div class="top-right">
    <span class="top-site">genaitechlabs.com</span>
    <a href="?logout=1"><button class="btn-out">Sign out</button></a>
  </div>
</div>

<div class="wrap">

  <div class="tabs">
    <a href="<?= tabUrl('booking') ?>" class="tab <?= $tab === 'booking' ? 'on' : '' ?>">
      📅 Booking Requests <span class="badge"><?= $counts['booking'] ?></span>
    </a>
    <a href="<?= tabUrl('leads') ?>" class="tab <?= $tab === 'leads' ? 'on' : '' ?>">
      📧 Score Leads <span class="badge"><?= $counts['leads'] ?></span>
    </a>
    <a href="<?= tabUrl('scans') ?>" class="tab <?= $tab === 'scans' ? 'on' : '' ?>">
      🔍 Scans <span class="badge"><?= $counts['scans'] ?></span>
    </a>
  </div>

  <div class="card">
    <div class="card-hd">
      <strong>
        <?php
        if ($tab === 'booking') echo '📅 Audit Call Requests';
        elseif ($tab === 'leads') echo '📧 OwlEye Score Leads';
        else echo '🔍 OwlEye Scans';
        echo $total > 0 ? ' &mdash; ' . number_format($total) . ' total' : '';
        ?>
      </strong>
      <span>Page <?= $pg ?> of <?= $pages ?></span>
    </div>

    <?php if (isset($dbErr)): ?>
      <div class="dberr">DB error: <?= h($dbErr) ?></div>

    <?php elseif (empty($rows)): ?>
      <div class="empty"><big>📭</big>No records yet.</div>

    <?php elseif ($tab === 'booking'): ?>
      <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>#</th><th>Name</th><th>Work Email</th><th>WhatsApp</th><th>Store URL</th>
          <th>Revenue</th><th>Visitors</th><th>Platform</th><th>IP</th><th>Submitted</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="muted"><?= (int)$r['id'] ?></td>
          <td><?= h($r['first_name'] . ' ' . $r['last_name']) ?></td>
          <td><?= h($r['email']) ?></td>
          <td><?= h($r['whatsapp'] ?? '—') ?></td>
          <td class="url"><a href="<?= h($r['url'] ?? '') ?>" target="_blank" rel="noopener"><?= h(trunc($r['url'] ?? '', 36)) ?></a></td>
          <td><span class="pill"><?= h($r['revenue_range'] ?? '—') ?></span></td>
          <td><span class="pill"><?= h($r['visitors_range'] ?? '—') ?></span></td>
          <td><?= h($r['platform'] ?? '—') ?></td>
          <td class="muted"><?= h($r['ip'] ?? '') ?></td>
          <td class="muted" title="<?= h($r['created_at']) ?>"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>

    <?php elseif ($tab === 'leads'): ?>
      <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>#</th><th>Name</th><th>Email</th><th>Store URL</th>
          <th>Scan Token</th><th>IP</th><th>Submitted</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="muted"><?= (int)$r['id'] ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($r['email']) ?></td>
          <td class="url"><a href="<?= h($r['url'] ?? '') ?>" target="_blank" rel="noopener"><?= h(trunc($r['url'] ?? '', 36)) ?></a></td>
          <td class="token"><?= h(substr($r['scan_token'] ?? '', 0, 8)) ?>…</td>
          <td class="muted"><?= h($r['ip'] ?? '') ?></td>
          <td class="muted" title="<?= h($r['created_at']) ?>"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>

    <?php else: // scans ?>
      <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>#</th><th>Store URL</th><th>Score</th>
          <?php foreach ($PILLARS as $label): ?><th><?= h($label) ?></th><?php endforeach; ?>
          <th>IP</th><th>Scanned</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $ps = json_decode($r['pillar_scores'] ?? '[]', true) ?: [];
        ?>
        <tr>
          <td class="muted"><?= (int)$r['id'] ?></td>
          <td class="url"><a href="<?= h($r['url']) ?>" target="_blank" rel="noopener"><?= h(trunc($r['url'], 38)) ?></a></td>
          <td><span class="score" style="color:<?= scoreColor((int)$r['owleye_score']) ?>"><?= (int)$r['owleye_score'] ?></span></td>
          <?php foreach (array_keys($PILLARS) as $i => $key): $v = $ps[$i] ?? null; ?>
          <td><?php if ($v !== null): ?>
            <span class="muted" style="color:<?= scoreColor((int)$v) ?>;font-weight:600"><?= (int)$v ?></span>
          <?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <?php endforeach; ?>
          <td class="muted"><?= h($r['ip'] ?? '') ?></td>
          <td class="muted" title="<?= h($r['created_at']) ?>"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>

  <?php pager($tab, $pg, $pages); ?>

</div>
</body>
</html>

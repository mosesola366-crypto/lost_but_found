<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$page_title = 'Home | Property Reporting and Recovery System';
$asset_path = '';
$active = 'home';

if (is_logged_in() && is_admin()) {
    header('Location: admin/dashboard.php');
    exit;
}

include 'includes/header.php';

$lost_count     = (int)$pdo->query('SELECT COUNT(*) FROM lost_items')->fetchColumn();
$found_count    = (int)$pdo->query('SELECT COUNT(*) FROM found_items')->fetchColumn();
$resolved_count = (int)$pdo->query("SELECT COUNT(*) FROM matches WHERE status='released'")->fetchColumn();
?>
<section class="hero">
  <h1>Lost something on campus? Found something?</h1>
  <p>
    Report in seconds using your <strong>Matriculation Number or Staff ID</strong>.
    No registration, no password, and no login required. Campus Security matches reports in real time.
  </p>
  <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap; margin-top:20px;">
    <a href="report_lost.php" class="btn btn-gold" style="padding:12px 22px; font-weight:600;">+ Report Lost Item</a>
    <a href="report_found.php" class="btn btn-primary" style="padding:12px 22px; font-weight:600; background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.4); color:#fff;">+ Report Found Item</a>
    <a href="track.php" class="btn btn-outline" style="background:transparent; color:#fff; border-color:rgba(255,255,255,0.6); padding:12px 22px;">Check Report Status</a>
  </div>
</section>

<div class="page">
  <div class="grid-3">
    <div class="stat-card alt">
      <div class="num"><?= $lost_count ?></div>
      <div class="label">Lost item reports filed</div>
    </div>
    <div class="stat-card">
      <div class="num"><?= $found_count ?></div>
      <div class="label">Found item reports filed</div>
    </div>
    <div class="stat-card success">
      <div class="num"><?= $resolved_count ?></div>
      <div class="label">Items successfully reunited</div>
    </div>
  </div>

  <div class="card" style="margin-top:24px;">
    <h2>How identifier-based reporting works</h2>
    <div class="grid-3" style="margin-top:16px;">
      <div>
        <strong>1. Report Instantly</strong>
        <p style="color:var(--muted); font-size:13.5px; line-height:1.5;">
          No account signup needed. Just provide your Matric or Staff ID, phone number, and property details.
        </p>
      </div>
      <div>
        <strong>2. Security Verification</strong>
        <p style="color:var(--muted); font-size:13.5px; line-height:1.5;">
          The Security Unit logs, inspects, and matches lost property reports with items brought to the unit.
        </p>
      </div>
      <div>
        <strong>3. Real-Time Tracking &amp; Claim</strong>
        <p style="color:var(--muted); font-size:13.5px; line-height:1.5;">
          Check your report status anytime with your ID &amp; Phone, or claim items from the public search catalogue.
        </p>
      </div>
    </div>
  </div>

  <div class="card" style="margin-top:20px; background:#fcfbf7; border-left:4px solid var(--gold);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
      <div>
        <h3 style="margin:0; font-size:16px; color:var(--navy-dark);">Looking for an item found on campus?</h3>
        <p style="margin:4px 0 0; font-size:13.5px; color:var(--muted);">
          Search all recently recovered belongings currently held at the Security Unit custody.
        </p>
      </div>
      <a href="search.php" class="btn btn-outline" style="white-space:nowrap;">Browse Found Catalogue &rarr;</a>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

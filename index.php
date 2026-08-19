<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
$page_title = 'Home | Property Reporting and Recovery System';
$asset_path = '';
if (is_logged_in()) {
    header('Location: ' . (is_admin() ? 'admin/dashboard.php' : 'dashboard.php'));
    exit;
}
include 'includes/header.php';

$lost_count = $pdo->query('SELECT COUNT(*) FROM lost_items')->fetchColumn();
$found_count = $pdo->query('SELECT COUNT(*) FROM found_items')->fetchColumn();
$resolved_count = $pdo->query("SELECT COUNT(*) FROM matches WHERE status='released'")->fetchColumn();
?>
<section class="hero">
  <h1>Lost something on campus? Found something?</h1>
  <p>Report it in minutes and let the Security Unit help match lost items with recovered ones. No more paper registers, no more physical visits just to check a status.</p>
  <a href="register.php" class="btn btn-gold">Create an Account</a>
  <a href="login.php" class="btn btn-outline" style="background:transparent;color:#fff;border-color:rgba(255,255,255,0.5);">Login</a>
</section>

<div class="page">
  <div class="grid-3">
    <div class="stat-card alt">
      <div class="num"><?= (int)$lost_count ?></div>
      <div class="label">Lost item reports filed</div>
    </div>
    <div class="stat-card">
      <div class="num"><?= (int)$found_count ?></div>
      <div class="label">Found item reports filed</div>
    </div>
    <div class="stat-card success">
      <div class="num"><?= (int)$resolved_count ?></div>
      <div class="label">Items successfully reunited</div>
    </div>
  </div>

  <div class="card" style="margin-top:24px;">
    <h2>How it works</h2>
    <div class="grid-3">
      <div>
        <strong>1. Report</strong>
        <p style="color:var(--muted); font-size:13.5px;">Submit a report describing the item you lost or found, with location and date details.</p>
      </div>
      <div>
        <strong>2. Match</strong>
        <p style="color:var(--muted); font-size:13.5px;">The Security Unit reviews reports and matches lost items with found ones in the system.</p>
      </div>
      <div>
        <strong>3. Recover</strong>
        <p style="color:var(--muted); font-size:13.5px;">You get notified, verify ownership, and collect your item from the Security Unit.</p>
      </div>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

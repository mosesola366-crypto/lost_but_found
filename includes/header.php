<?php
// $active can be set by the including page to highlight the current nav link
$active = $active ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?? 'Property Reporting and Recovery System' ?></title>
<link rel="stylesheet" href="<?= $asset_path ?? '' ?>assets/css/style.css">
<script src="<?= $asset_path ?? '' ?>assets/js/ng-phone.js" defer></script>
<script src="<?= $asset_path ?? '' ?>assets/js/field-validation.js" defer></script>
</head>
<body>
<header class="topbar">
  <a href="<?= $asset_path ?? '' ?>index.php" class="brand" style="text-decoration:none; color:inherit;">
    <div class="crest">PI</div>
    <div>
      Property Reporting &amp; Recovery System
      <small>Security Unit &middot; The Polytechnic Ibadan</small>
    </div>
  </a>
  <?php if (is_logged_in() && is_admin()): ?>
    <nav class="nav-links">
      <a href="<?= $asset_path ?? '' ?>admin/dashboard.php" class="<?= $active === 'admin_dashboard' ? 'active' : '' ?>">Dashboard</a>
      <a href="<?= $asset_path ?? '' ?>admin/manage_lost.php" class="<?= $active === 'manage_lost' ? 'active' : '' ?>">Lost Reports</a>
      <a href="<?= $asset_path ?? '' ?>admin/manage_found.php" class="<?= $active === 'manage_found' ? 'active' : '' ?>">Found Reports</a>
      <a href="<?= $asset_path ?? '' ?>admin/matches.php" class="<?= $active === 'matches' ? 'active' : '' ?>">Matches</a>
      <a href="<?= $asset_path ?? '' ?>admin/users.php" class="<?= $active === 'users' ? 'active' : '' ?>">Users</a>
    </nav>
    <div class="nav-user">
      <div class="avatar"><?= strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)) ?></div>
      <form action="<?= $asset_path ?? '' ?>logout.php" method="post" style="margin:0;">
        <?= csrf_field() ?>
        <button type="submit" class="btn-logout">Log out</button>
      </form>
    </div>
  <?php else: ?>
    <nav class="nav-links">
      <a href="<?= $asset_path ?? '' ?>index.php" class="<?= $active === 'home' ? 'active' : '' ?>">Home</a>
      <a href="<?= $asset_path ?? '' ?>report_lost.php" class="<?= $active === 'report_lost' ? 'active' : '' ?>">Report Lost</a>
      <a href="<?= $asset_path ?? '' ?>report_found.php" class="<?= $active === 'report_found' ? 'active' : '' ?>">Report Found</a>
      <a href="<?= $asset_path ?? '' ?>track.php" class="<?= $active === 'track' ? 'active' : '' ?>">Check Status</a>
      <a href="<?= $asset_path ?? '' ?>search.php" class="<?= $active === 'search' ? 'active' : '' ?>">Search Items</a>
      <a href="<?= $asset_path ?? '' ?>login.php" class="<?= $active === 'login' ? 'active' : '' ?>" style="color:var(--gold);">Admin</a>
    </nav>
  <?php endif; ?>
</header>

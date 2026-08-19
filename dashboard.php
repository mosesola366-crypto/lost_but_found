<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();
$page_title = 'Dashboard | Property Reporting and Recovery System';
$asset_path = '';
$active = 'dashboard';

$uid = $_SESSION['user_id'];

$lost = $pdo->prepare('SELECT * FROM lost_items WHERE user_id = ? ORDER BY created_at DESC');
$lost->execute([$uid]);
$lost_items = $lost->fetchAll(PDO::FETCH_ASSOC);

$found = $pdo->prepare('SELECT * FROM found_items WHERE user_id = ? ORDER BY created_at DESC');
$found->execute([$uid]);
$found_items = $found->fetchAll(PDO::FETCH_ASSOC);

$notif = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$notif->execute([$uid]);
$notifications = $notif->fetchAll(PDO::FETCH_ASSOC);

$pending_count = count(array_filter($lost_items, fn($i) => $i['status'] === 'pending'))
               + count(array_filter($found_items, fn($i) => $i['status'] === 'pending'));
$matched_count = count(array_filter($lost_items, fn($i) => $i['status'] === 'matched'))
               + count(array_filter($found_items, fn($i) => $i['status'] === 'matched'));
$resolved_count = count(array_filter($lost_items, fn($i) => $i['status'] === 'resolved'))
                + count(array_filter($found_items, fn($i) => $i['status'] === 'claimed'));

include 'includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h1>
    <p>Here is an overview of your reports and recent activity.</p>
  </div>

  <div class="grid-3">
    <div class="stat-card alt"><div class="num"><?= $pending_count ?></div><div class="label">Pending reports</div></div>
    <div class="stat-card"><div class="num"><?= $matched_count ?></div><div class="label">Matched items</div></div>
    <div class="stat-card success"><div class="num"><?= $resolved_count ?></div><div class="label">Resolved / Recovered</div></div>
  </div>

  <div class="card" style="margin-top:20px;">
    <h2>Quick actions</h2>
    <a href="report_lost.php" class="btn btn-primary">+ Report Lost Item</a>
    <a href="report_found.php" class="btn btn-gold">+ Report Found Item</a>
    <a href="search.php" class="btn btn-outline">Search Items</a>
  </div>

  <?php if (!empty($notifications)): ?>
  <div class="card">
    <h2>Recent Notifications</h2>
    <?php foreach ($notifications as $n): ?>
      <div style="padding:10px 0; border-bottom:1px solid var(--border); font-size:13.5px;">
        <?= htmlspecialchars($n['message']) ?>
        <div style="color:var(--muted); font-size:11.5px;"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>My Lost Item Reports</h2>
    <?php if (empty($lost_items)): ?>
      <div class="empty-state">You haven't reported any lost items yet.</div>
    <?php else: ?>
    <table>
      <tr><th>Item</th><th>Category</th><th>Location Lost</th><th>Date</th><th>Status</th></tr>
      <?php foreach ($lost_items as $i): ?>
        <tr>
          <td><?= htmlspecialchars($i['item_name']) ?></td>
          <td><?= htmlspecialchars($i['category']) ?></td>
          <td><?= htmlspecialchars($i['location_lost']) ?></td>
          <td><?= date('d M Y', strtotime($i['date_lost'])) ?></td>
          <td><span class="badge badge-<?= $i['status'] ?>"><?= ucfirst($i['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>My Found Item Reports</h2>
    <?php if (empty($found_items)): ?>
      <div class="empty-state">You haven't reported any found items yet.</div>
    <?php else: ?>
    <table>
      <tr><th>Item</th><th>Category</th><th>Location Found</th><th>Date</th><th>Status</th></tr>
      <?php foreach ($found_items as $i): ?>
        <tr>
          <td><?= htmlspecialchars($i['item_name']) ?></td>
          <td><?= htmlspecialchars($i['category']) ?></td>
          <td><?= htmlspecialchars($i['location_found']) ?></td>
          <td><?= date('d M Y', strtotime($i['date_found'])) ?></td>
          <td><span class="badge badge-<?= $i['status'] ?>"><?= ucfirst($i['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

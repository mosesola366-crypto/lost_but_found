<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();
$page_title = 'Admin Dashboard | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'admin_dashboard';

$total_lost = $pdo->query('SELECT COUNT(*) FROM lost_items')->fetchColumn();
$total_found = $pdo->query('SELECT COUNT(*) FROM found_items')->fetchColumn();
$pending_matches = $pdo->query("SELECT COUNT(*) FROM matches WHERE status='pending_verification'")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$resolved = $pdo->query("SELECT COUNT(*) FROM matches WHERE status='released'")->fetchColumn();

$recent_lost = $pdo->query('SELECT l.*, u.full_name FROM lost_items l JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
$recent_found = $pdo->query('SELECT f.*, u.full_name FROM found_items f JOIN users u ON u.id=f.user_id ORDER BY f.created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Administrator Dashboard</h1>
    <p>Overview of all property reports handled by the Security Unit.</p>
  </div>

  <div class="grid-3">
    <div class="stat-card alt"><div class="num"><?= $total_lost ?></div><div class="label">Total lost reports</div></div>
    <div class="stat-card"><div class="num"><?= $total_found ?></div><div class="label">Total found reports</div></div>
    <div class="stat-card success"><div class="num"><?= $resolved ?></div><div class="label">Items reunited</div></div>
  </div>
  <div class="grid-2" style="margin-top:18px;">
    <div class="stat-card"><div class="num"><?= $pending_matches ?></div><div class="label">Matches awaiting verification</div></div>
    <div class="stat-card"><div class="num"><?= $total_users ?></div><div class="label">Registered users</div></div>
  </div>

  <div class="grid-2" style="margin-top:20px; align-items:start;">
    <div class="card">
      <h2>Recent Lost Item Reports</h2>
      <?php if (empty($recent_lost)): ?><div class="empty-state">No reports yet.</div><?php else: ?>
      <table>
        <tr><th>Item</th><th>Reported By</th><th>Status</th></tr>
        <?php foreach ($recent_lost as $i): ?>
          <tr><td><?= htmlspecialchars($i['item_name']) ?></td><td><?= htmlspecialchars($i['full_name']) ?></td>
          <td><span class="badge badge-<?= $i['status'] ?>"><?= ucfirst($i['status']) ?></span></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
      <a href="manage_lost.php" class="btn btn-sm btn-outline" style="margin-top:12px;">View all lost reports</a>
    </div>
    <div class="card">
      <h2>Recent Found Item Reports</h2>
      <?php if (empty($recent_found)): ?><div class="empty-state">No reports yet.</div><?php else: ?>
      <table>
        <tr><th>Item</th><th>Reported By</th><th>Status</th></tr>
        <?php foreach ($recent_found as $i): ?>
          <tr><td><?= htmlspecialchars($i['item_name']) ?></td><td><?= htmlspecialchars($i['full_name']) ?></td>
          <td><span class="badge badge-<?= $i['status'] ?>"><?= ucfirst($i['status']) ?></span></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
      <a href="manage_found.php" class="btn btn-sm btn-outline" style="margin-top:12px;">View all found reports</a>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

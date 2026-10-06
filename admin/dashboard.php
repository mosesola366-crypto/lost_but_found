<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_admin();
$page_title = 'Admin Dashboard | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'admin_dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_match'])) {
    verify_csrf();
    $lost_id = (int)($_POST['lost_item_id'] ?? 0);
    $found_id = (int)($_POST['found_item_id'] ?? 0);

    if ($lost_id > 0 && $found_id > 0) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO matches (lost_item_id, found_item_id, matched_by, status) VALUES (?, ?, ?, "pending_verification")');
            $stmt->execute([$lost_id, $found_id, $_SESSION['user_id']]);
            $pdo->prepare("UPDATE lost_items SET status='matched' WHERE id=?")->execute([$lost_id]);
            $pdo->prepare("UPDATE found_items SET status='matched' WHERE id=?")->execute([$found_id]);
            log_audit_action($pdo, $_SESSION['user_id'], 'PROPOSE_MATCH', 'matches', (int)$pdo->lastInsertId(), "Dashboard linked lost #{$lost_id} to found #{$found_id}");
            $pdo->commit();
            flash('success', 'Potential match created! Verify and approve it under Matches.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', 'Could not link match: ' . $e->getMessage());
        }
    }
    header('Location: dashboard.php');
    exit;
}

$total_lost = $pdo->query('SELECT COUNT(*) FROM lost_items')->fetchColumn();
$total_found = $pdo->query('SELECT COUNT(*) FROM found_items')->fetchColumn();
$pending_matches = $pdo->query("SELECT COUNT(*) FROM matches WHERE status='pending_verification'")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$resolved = $pdo->query("SELECT COUNT(*) FROM matches WHERE status='released'")->fetchColumn();

$recent_lost = $pdo->query('SELECT l.*, u.full_name FROM lost_items l JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
$recent_found = $pdo->query('SELECT f.*, u.full_name FROM found_items f JOIN users u ON u.id=f.user_id ORDER BY f.created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);

// Pending lost items for matching
$pending_lost_list = $pdo->query("SELECT id, item_name, category FROM lost_items WHERE status='pending' ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Pending found items grouped by category
$found_pool = $pdo->query("SELECT id, item_name, category FROM found_items WHERE status='pending' ORDER BY category ASC, item_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$found_by_category = [];
foreach ($found_pool as $f) {
    $cat = $f['category'] ?: 'Other';
    $found_by_category[$cat][] = $f;
}

include '../includes/header.php';
$success = flash('success');
$error = flash('error');
?>
<div class="page">
  <div class="page-header">
    <h1>Administrator Dashboard</h1>
    <p>Overview of all property reports handled by the Security Unit.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="grid-3">
    <div class="stat-card alt"><div class="num"><?= $total_lost ?></div><div class="label">Total lost reports</div></div>
    <div class="stat-card"><div class="num"><?= $total_found ?></div><div class="label">Total found reports</div></div>
    <div class="stat-card success"><div class="num"><?= $resolved ?></div><div class="label">Items reunited</div></div>
  </div>
  <div class="grid-2" style="margin-top:18px;">
    <div class="stat-card"><div class="num"><?= $pending_matches ?></div><div class="label">Matches awaiting verification</div></div>
    <div class="stat-card"><div class="num"><?= $total_users ?></div><div class="label">Registered users</div></div>
  </div>

  <?php if (!empty($found_by_category) && !empty($pending_lost_list)): ?>
  <div class="card" style="margin-top:20px;">
    <h2 style="margin-top:0; font-size:16px;">Quick Match Property</h2>
    <p style="font-size:13.5px; color:var(--muted); margin-top:-4px; margin-bottom:14px;">
      Directly link an active lost report with a recovered found item. Selecting a category dynamically filters the available found items, just like Faculty and Department.
    </p>
    <form method="post" class="form-row" style="align-items:end;" data-found-pool='<?= htmlspecialchars(json_encode($found_by_category), ENT_QUOTES, 'UTF-8') ?>'>
      <?= csrf_field() ?>
      <div class="field" style="margin-bottom:0;">
        <label>1. Lost Item Report</label>
        <select name="lost_item_id" required>
          <option value="">-- Choose Pending Lost Item --</option>
          <?php foreach ($pending_lost_list as $l): ?>
            <option value="<?= $l['id'] ?>" data-category="<?= htmlspecialchars($l['category']) ?>">
              <?= htmlspecialchars($l['item_name']) ?> (<?= htmlspecialchars($l['category']) ?> #<?= $l['id'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field" style="margin-bottom:0;">
        <label>2. Category</label>
        <select name="found_category" class="prs-found-cat-select">
          <option value="">-- Select Category --</option>
          <?php foreach (PRS_CATEGORIES as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field" style="margin-bottom:0;">
        <label>3. Found Item in Category</label>
        <select name="found_item_id" class="prs-found-item-select" required>
          <option value="">-- Select Category First --</option>
        </select>
      </div>

      <div class="field" style="margin-bottom:0;">
        <button type="submit" name="quick_match" value="1" class="btn btn-primary" style="padding:10px 18px;">
          Link Match
        </button>
      </div>
    </form>
  </div>
  <?php endif; ?>


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

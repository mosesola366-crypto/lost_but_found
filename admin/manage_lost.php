<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();
$page_title = 'Manage Lost Reports | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'manage_lost';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['propose_match'])) {
    $lost_id = (int)$_POST['lost_item_id'];
    $found_id = (int)$_POST['found_item_id'];
    if ($found_id > 0) {
        $pdo->prepare('INSERT INTO matches (lost_item_id, found_item_id, matched_by, status) VALUES (?, ?, ?, "pending_verification")')
            ->execute([$lost_id, $found_id, $_SESSION['user_id']]);
        $pdo->prepare("UPDATE lost_items SET status='matched' WHERE id=?")->execute([$lost_id]);
        $pdo->prepare("UPDATE found_items SET status='matched' WHERE id=?")->execute([$found_id]);
        flash('success', 'Potential match created. Review and confirm it on the Matches page.');
    }
    header('Location: manage_lost.php');
    exit;
}

if (isset($_GET['resolve'])) {
    $pdo->prepare("UPDATE lost_items SET status='resolved' WHERE id=?")->execute([(int)$_GET['resolve']]);
    header('Location: manage_lost.php');
    exit;
}

$items = $pdo->query('SELECT l.*, u.full_name, u.phone AS user_phone FROM lost_items l JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
$found_pool = $pdo->query("SELECT id, item_name, category FROM found_items WHERE status='pending' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
$success = flash('success');
?>
<div class="page">
  <div class="page-header">
    <h1>Manage Lost Item Reports</h1>
    <p>Review reports submitted by users and link them to found items when a possible match appears.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="card">
    <?php if (empty($items)): ?>
      <div class="empty-state">No lost item reports have been submitted yet.</div>
    <?php else: ?>
    <table>
      <tr><th>Item</th><th>Description</th><th>Category</th><th>Filed By</th><th>Location</th><th>Report Date</th><th>Status</th><th>Action</th></tr>
      <?php foreach ($items as $i): ?>
        <tr>
          <td><strong><?= htmlspecialchars($i['item_name']) ?></strong></td>
          <td style="max-width:260px; white-space:normal;"><?= nl2br(htmlspecialchars($i['description'])) ?></td>
          <td><?= htmlspecialchars($i['category']) ?></td>
          <td><?= htmlspecialchars($i['full_name']) ?><br><span style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($i['user_phone']) ?></span></td>
          <td><?= htmlspecialchars($i['location_lost']) ?></td>
          <td><?= date('d M Y', strtotime($i['date_lost'])) ?></td>
          <td><span class="badge badge-<?= $i['status'] ?>"><?= ucfirst($i['status']) ?></span></td>
          <td>
            <?php if ($i['status'] === 'pending' && !empty($found_pool)): ?>
              <form method="post" style="display:flex; gap:6px; flex-wrap:wrap;">
                <input type="hidden" name="lost_item_id" value="<?= $i['id'] ?>">
                <select name="found_item_id" style="font-size:12px; padding:6px; min-width:160px;">
                  <option value="">Match with...</option>
                  <?php foreach ($found_pool as $f): ?>
                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['item_name']) ?> (<?= htmlspecialchars($f['category']) ?>)</option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="propose_match" class="btn btn-sm btn-primary">Link</button>
              </form>
            <?php elseif ($i['status'] === 'matched'): ?>
              <a href="?resolve=<?= $i['id'] ?>" class="btn btn-sm btn-success">Mark Resolved</a>
            <?php else: ?>
              <span style="color:var(--muted); font-size:12px;">No action available</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

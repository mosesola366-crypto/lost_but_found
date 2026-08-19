<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();
$page_title = 'Manage Found Reports | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'manage_found';

$items = $pdo->query('SELECT f.*, u.full_name, u.phone AS user_phone FROM found_items f JOIN users u ON u.id=f.user_id ORDER BY f.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Manage Found Item Reports</h1>
    <p>All items reported as found and submitted to the Security Unit.</p>
  </div>

  <div class="card">
    <?php if (empty($items)): ?>
      <div class="empty-state">No found item reports have been submitted yet.</div>
    <?php else: ?>
      <?php foreach ($items as $i): ?>
        <div class="item-card">
          <div class="item-thumb">
            <?php if (!empty($i['image_path'])): ?>
              <img src="../<?= htmlspecialchars($i['image_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
            <?php else: ?>&#128230;<?php endif; ?>
          </div>
          <div style="flex:1;">
            <div class="title"><?= htmlspecialchars($i['item_name']) ?> <span class="badge badge-<?= $i['status'] ?>"><?= ucfirst($i['status']) ?></span></div>
            <div class="meta">Found by <?= htmlspecialchars($i['full_name']) ?> &middot; <?= htmlspecialchars($i['location_found']) ?> &middot; <?= date('d M Y', strtotime($i['date_found'])) ?></div>
            <div class="meta">Category: <?= htmlspecialchars($i['category']) ?></div>
            <p style="margin:6px 0 0; font-size:13.5px;"><?= htmlspecialchars($i['description']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

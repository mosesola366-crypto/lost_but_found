<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();
$page_title = 'Manage Found Reports | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'manage_found';

$statusFilter = trim($_GET['status'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$categories = ['Electronics','Documents','Jewelry','Bags','Clothing','Keys','Books','Wallet/Purse','Other'];
$statusOptions = ['pending', 'matched', 'resolved', 'claimed'];
$statusFilter = in_array($statusFilter, $statusOptions, true) ? $statusFilter : '';
$categoryFilter = in_array($categoryFilter, $categories, true) ? $categoryFilter : '';
$itemsPerPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$countSql = 'SELECT COUNT(*) FROM found_items WHERE 1=1';
$params = [];
if ($statusFilter !== '') {
    $countSql .= ' AND status = ?';
    $params[] = $statusFilter;
}
if ($categoryFilter !== '') {
    $countSql .= ' AND category = ?';
    $params[] = $categoryFilter;
}
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $itemsPerPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $itemsPerPage;

$sql = 'SELECT f.*, u.full_name, u.phone AS user_phone, u.id_number FROM found_items f JOIN users u ON u.id=f.user_id WHERE 1=1';
if ($statusFilter !== '') {
    $sql .= ' AND f.status = ?';
}
if ($categoryFilter !== '') {
    $sql .= ' AND f.category = ?';
}
$sql .= ' ORDER BY f.created_at DESC LIMIT ' . $itemsPerPage . ' OFFSET ' . $offset;
$itemStmt = $pdo->prepare($sql);
$itemStmt->execute($params);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Manage Found Item Reports</h1>
    <p>All items reported as found and submitted to the Security Unit.</p>
  </div>

  <div class="card">
    <form method="get" class="form-row" style="align-items:end; margin-bottom: 18px;">
      <div class="field" style="margin-bottom:0;">
        <label>Status</label>
        <select name="status">
          <option value="">All statuses</option>
          <?php foreach ($statusOptions as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;">
        <label>Category</label>
        <select name="category">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c ?>" <?= $categoryFilter === $c ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;">
        <button type="submit" class="btn btn-primary">Filter</button>
      </div>
    </form>

    <?php if (empty($items)): ?>
      <div class="empty-state">No found item reports match the selected filter.</div>
    <?php else: ?>
      <?php foreach ($items as $i): ?>
        <div class="item-card">
          <div class="item-thumb">
            <?php if (!empty($i['image_path'])): ?>
              <img src="../<?= htmlspecialchars($i['image_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
            <?php else: ?>&#128230;<?php endif; ?>
          </div>
          <div style="flex:1;">
            <div class="title"><?= htmlspecialchars($i['item_name']) ?> <?= render_status_badge($i['status']) ?></div>
            <div class="meta">Found by <strong><?= htmlspecialchars($i['full_name']) ?></strong> (<?= htmlspecialchars($i['id_number']) ?> &middot; <?= htmlspecialchars($i['user_phone']) ?>) &middot; <?= htmlspecialchars($i['location_found']) ?> &middot; <?= date('d M Y', strtotime($i['date_found'])) ?></div>
            <div class="meta">Category: <?= htmlspecialchars($i['category']) ?></div>
            <p style="margin:6px 0 0; font-size:13.5px;"><?= htmlspecialchars($i['description']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($totalPages > 1): ?>
        <div class="pagination" aria-label="Found report pagination">
          <?php if ($page > 1): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&category=<?= urlencode($categoryFilter) ?>&page=<?= $page - 1 ?>">Prev</a>
          <?php else: ?>
            <span class="disabled">Prev</span>
          <?php endif; ?>

          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i === $page): ?>
              <span class="current"><?= $i ?></span>
            <?php else: ?>
              <a href="?status=<?= urlencode($statusFilter) ?>&category=<?= urlencode($categoryFilter) ?>&page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&category=<?= urlencode($categoryFilter) ?>&page=<?= $page + 1 ?>">Next</a>
          <?php else: ?>
            <span class="disabled">Next</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();
$page_title = 'Search Items | Property Reporting and Recovery System';
$asset_path = '';
$active = 'search';

$type = $_GET['type'] ?? 'found';
$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$categories = ['Electronics','Documents','Jewelry','Bags','Clothing','Keys','Books','Wallet/Purse','Other'];

$table = $type === 'lost' ? 'lost_items' : 'found_items';
$loc_field = $type === 'lost' ? 'location_lost' : 'location_found';

$sql = "SELECT * FROM $table WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= " AND (item_name LIKE ? OR description LIKE ? OR $loc_field LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($category !== '') {
    $sql .= " AND category = ?";
    $params[] = $category;
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Search Reported Items</h1>
    <p>Search across all lost or found item reports submitted to the Security Unit.</p>
  </div>

  <div class="tabs">
    <a href="?type=found" class="<?= $type === 'found' ? 'active' : '' ?>">Found Items</a>
    <a href="?type=lost" class="<?= $type === 'lost' ? 'active' : '' ?>">Lost Items</a>
  </div>

  <div class="card">
    <form method="get" class="form-row" style="align-items:end;">
      <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
      <div class="field" style="margin-bottom:0;">
        <label>Keyword</label>
        <input type="text" name="q" placeholder="Item name, description, or location" value="<?= htmlspecialchars($q) ?>">
      </div>
      <div class="field" style="margin-bottom:0;">
        <label>Category</label>
        <select name="category">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c ?>" <?= $category === $c ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;">
        <button type="submit" class="btn btn-primary">Search</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> found</h2>
    <?php if (empty($results)): ?>
      <div class="empty-state">No matching items found. Try a different keyword or category.</div>
    <?php else: ?>
      <?php foreach ($results as $item): ?>
        <div class="item-card">
          <div class="item-thumb">
            <?php if ($type === 'found' && !empty($item['image_path'])): ?>
              <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
            <?php else: ?>&#128230;<?php endif; ?>
          </div>
          <div style="flex:1;">
            <div class="title"><?= htmlspecialchars($item['item_name']) ?> <span class="badge badge-<?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span></div>
            <div class="meta"><?= htmlspecialchars($item['category']) ?> &middot; <?= htmlspecialchars($item[$loc_field]) ?> &middot; <?= date('d M Y', strtotime($type === 'lost' ? $item['date_lost'] : $item['date_found'])) ?></div>
            <p style="margin:6px 0 0; font-size:13.5px;"><?= htmlspecialchars($item['description']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/pagination.php';

$page_title = 'Search Items | Property Reporting and Recovery System';
$asset_path = '';
$active = 'search';

$type = ($_GET['type'] ?? 'found') === 'lost' ? 'lost' : 'found';
$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$categories = PRS_CATEGORIES;

$table = $type === 'lost' ? 'lost_items' : 'found_items';
$loc_field = $type === 'lost' ? 'location_lost' : 'location_found';

$countSql  = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
$selectSql = "SELECT * FROM {$table} WHERE 1=1";
$params = [];

if ($q !== '') {
    $clause = " AND (item_name LIKE ? OR description LIKE ? OR {$loc_field} LIKE ?)";
    $countSql  .= $clause;
    $selectSql .= $clause;
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
}

if ($category !== '') {
    $clause = " AND category = ?";
    $countSql  .= $clause;
    $selectSql .= $clause;
    $params[] = $category;
}

$selectSql .= " ORDER BY created_at DESC";

$pagination = paginate($pdo, $countSql, $selectSql, $params, 8, 'page');
$results = $pagination['items'];

include 'includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Search Reported Items</h1>
    <p>Search across lost or found item reports submitted to the Security Unit.</p>
  </div>

  <div class="tabs">
    <a href="?type=found&q=<?= urlencode($q) ?>&category=<?= urlencode($category) ?>" class="<?= $type === 'found' ? 'active' : '' ?>">Found Items</a>
    <a href="?type=lost&q=<?= urlencode($q) ?>&category=<?= urlencode($category) ?>" class="<?= $type === 'lost' ? 'active' : '' ?>">Lost Items</a>
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
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
      <h2 style="margin:0;"><?= $pagination['total'] ?> <?= ucfirst($type) ?> Item<?= $pagination['total'] === 1 ? '' : 's' ?> Found</h2>
      <?php if ($pagination['total_pages'] > 1): ?>
        <small style="color:var(--muted);">Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?></small>
      <?php endif; ?>
    </div>

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
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px;">
              <div class="title">
                <?= htmlspecialchars($item['item_name']) ?>
                <?= render_status_badge($item['status']) ?>
              </div>
              <?php if ($type === 'found' && $item['status'] === 'pending' && (!isset($_SESSION['user_id']) || $item['user_id'] !== $_SESSION['user_id'])): ?>
                <a href="claim_item.php?item_id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-gold" style="font-size:12px;">This is Mine (Claim)</a>
              <?php endif; ?>
            </div>
            <div class="meta" style="margin-top:4px;">
              <?= htmlspecialchars($item['category']) ?> &middot;
              <?= htmlspecialchars($item[$loc_field]) ?> &middot;
              <?= date('d M Y', strtotime($type === 'lost' ? $item['date_lost'] : $item['date_found'])) ?>
            </div>
            <p style="margin:8px 0 0; font-size:13.5px;"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
          </div>
        </div>
      <?php endforeach; ?>

      <?= render_pagination($pagination['current_page'], $pagination['total_pages'], ['type', 'q', 'category']) ?>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

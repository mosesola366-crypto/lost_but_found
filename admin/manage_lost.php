<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();

require_once '../includes/helpers.php';
require_once '../includes/pagination.php';
$page_title = 'Manage Lost Reports | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'manage_lost';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['propose_match'])) {
        $lost_id = (int)($_POST['lost_item_id'] ?? 0);
        $found_id = (int)($_POST['found_item_id'] ?? 0);

        if ($lost_id > 0 && $found_id > 0) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('INSERT INTO matches (lost_item_id, found_item_id, matched_by, status) VALUES (?, ?, ?, "pending_verification")');
                $stmt->execute([$lost_id, $found_id, $_SESSION['user_id']]);

                $pdo->prepare("UPDATE lost_items SET status='matched' WHERE id=?")->execute([$lost_id]);
                $pdo->prepare("UPDATE found_items SET status='matched' WHERE id=?")->execute([$found_id]);

                log_audit_action($pdo, $_SESSION['user_id'], 'PROPOSE_MATCH', 'matches', (int)$pdo->lastInsertId(), "Linked lost #{$lost_id} to found #{$found_id}");

                $pdo->commit();
                flash('success', 'Potential match created. Review and verify it on the Matches page.');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('error', 'Could not link match: ' . $e->getMessage());
            }
        }
        header('Location: manage_lost.php');
        exit;
    }

    if (isset($_POST['resolve_item'])) {
        $lost_id = (int)($_POST['lost_item_id'] ?? 0);
        if ($lost_id > 0) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE lost_items SET status='resolved' WHERE id=?")->execute([$lost_id]);
                // If there was an associated active match, update it to released/resolved
                $matchStmt = $pdo->prepare("SELECT id, found_item_id FROM matches WHERE lost_item_id=? AND status IN ('pending_verification','confirmed') LIMIT 1");
                $matchStmt->execute([$lost_id]);
                $m = $matchStmt->fetch(PDO::FETCH_ASSOC);
                if ($m) {
                    $pdo->prepare("UPDATE matches SET status='released' WHERE id=?")->execute([$m['id']]);
                    $pdo->prepare("UPDATE found_items SET status='claimed' WHERE id=?")->execute([$m['found_item_id']]);
                }
                log_audit_action($pdo, $_SESSION['user_id'], 'RESOLVE_LOST', 'lost_items', $lost_id, "Manually marked lost item as resolved");
                $pdo->commit();
                flash('success', 'Lost item report marked as resolved.');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('error', 'Failed to resolve item: ' . $e->getMessage());
            }
        }
        header('Location: manage_lost.php');
        exit;
    }
}

$countSql  = 'SELECT COUNT(*) FROM lost_items';
$selectSql = 'SELECT l.*, u.full_name, u.phone AS user_phone, u.id_number FROM lost_items l JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC';
$pagination = paginate($pdo, $countSql, $selectSql, [], 12, 'page');
$items = $pagination['items'];

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
    <h1>Manage Lost Item Reports</h1>
    <p>Review reports submitted by users and link them to found items when a possible match appears.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card">
    <?php if (empty($items)): ?>
      <div class="empty-state">No lost item reports have been submitted yet.</div>
    <?php else: ?>
    <table>
      <tr><th>Item</th><th>Description</th><th>Category</th><th>Filed By (ID &amp; Phone)</th><th>Location</th><th>Report Date</th><th>Status</th><th>Action</th></tr>
      <?php foreach ($items as $i): ?>
        <tr>
          <td><strong><?= htmlspecialchars($i['item_name']) ?></strong></td>
          <td style="max-width:260px; white-space:normal;"><?= nl2br(htmlspecialchars($i['description'])) ?></td>
          <td><?= htmlspecialchars($i['category']) ?></td>
          <td>
            <strong><?= htmlspecialchars($i['full_name']) ?></strong><br>
            <span style="font-size:12px; color:var(--navy-dark); font-weight:600;"><?= htmlspecialchars($i['id_number']) ?></span> &middot;
            <span style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($i['user_phone']) ?></span>
          </td>
          <td><?= htmlspecialchars($i['location_lost']) ?></td>
          <td><?= date('d M Y', strtotime($i['date_lost'])) ?></td>
          <td><?= render_status_badge($i['status']) ?></td>
          <td>
            <?php if ($i['status'] === 'pending' && !empty($found_by_category)): ?>
              <form method="post" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;"
                    data-found-pool='<?= htmlspecialchars(json_encode($found_by_category), ENT_QUOTES, 'UTF-8') ?>'>
                <?= csrf_field() ?>
                <input type="hidden" name="lost_item_id" value="<?= $i['id'] ?>">

                <!-- 1. Category Dropdown (like Faculty) -->
                <select name="found_category" class="prs-found-cat-select" style="font-size:12px; padding:6px; width:125px;"
                        data-selected="<?= htmlspecialchars($i['category']) ?>">
                  <option value="">Category...</option>
                  <?php foreach (PRS_CATEGORIES as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= ($c === $i['category']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($c) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <!-- 2. Found Item Dropdown (like Department) -->
                <select name="found_item_id" class="prs-found-item-select" style="font-size:12px; padding:6px; min-width:160px; max-width:220px;" required>
                  <option value="">Select Item...</option>
                </select>

                <button type="submit" name="propose_match" value="1" class="btn btn-sm btn-primary">Link</button>
              </form>
            <?php elseif ($i['status'] === 'matched'): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Mark this lost item report as fully resolved?');">
                <?= csrf_field() ?>
                <input type="hidden" name="lost_item_id" value="<?= $i['id'] ?>">
                <button type="submit" name="resolve_item" value="1" class="btn btn-sm btn-success">Mark Resolved</button>
              </form>
            <?php else: ?>
              <span style="color:var(--muted); font-size:12px;">No action available</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
      <?= render_pagination($pagination['current_page'], $pagination['total_pages']) ?>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

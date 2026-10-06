<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/pagination.php';
require_login();

$page_title = 'Notifications | Property Reporting and Recovery System';
$asset_path = '';
$active = 'notifications';
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['mark_all_read'])) {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$uid]);
        flash('success', 'All notifications marked as read.');
    } elseif (isset($_POST['notif_id'])) {
        $nid = (int)$_POST['notif_id'];
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$nid, $uid]);
    }
    header('Location: notifications.php');
    exit;
}

$countSql  = 'SELECT COUNT(*) FROM notifications WHERE user_id = ?';
$selectSql = 'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC';
$pagination = paginate($pdo, $countSql, $selectSql, [$uid], 12, 'page');
$notifications = $pagination['items'];

$unreadCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = {$uid} AND is_read = 0")->fetchColumn();

include 'includes/header.php';
$success = flash('success');
?>
<div class="page">
  <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
    <div>
      <h1>Notification Center</h1>
      <p>Updates on your reports, match confirmations, and recovery alerts from Security Unit.</p>
    </div>
    <?php if ($unreadCount > 0): ?>
      <form method="post" style="margin:0;">
        <?= csrf_field() ?>
        <input type="hidden" name="mark_all_read" value="1">
        <button type="submit" class="btn btn-sm btn-outline">&#10003; Mark All as Read</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <div class="card">
    <?php if (empty($notifications)): ?>
      <div class="empty-state">You have no notifications yet.</div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div style="padding:14px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; gap:14px; background:<?= $n['is_read'] ? '#fff' : '#fcfbf7' ?>;">
          <div style="flex:1;">
            <div style="font-size:14px; color:<?= $n['is_read'] ? 'var(--text)' : 'var(--navy-dark)' ?>; font-weight:<?= $n['is_read'] ? 'normal' : '600' ?>;">
              <?php if (!$n['is_read']): ?><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--gold); margin-right:6px;"></span><?php endif; ?>
              <?= htmlspecialchars($n['message']) ?>
            </div>
            <div style="color:var(--muted); font-size:12px; margin-top:4px;">
              <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
            </div>
          </div>

          <div style="display:flex; gap:8px;">
            <?php if (!empty($n['link_url'])): ?>
              <a href="<?= htmlspecialchars($n['link_url']) ?>" class="btn btn-sm btn-outline">View</a>
            <?php endif; ?>

            <?php if (!$n['is_read']): ?>
              <form method="post" style="margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline" title="Mark as Read">&#10003;</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <?= render_pagination($pagination['current_page'], $pagination['total_pages']) ?>
    <?php endif; ?>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

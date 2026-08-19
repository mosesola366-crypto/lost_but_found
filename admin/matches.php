<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();
$page_title = 'Matches | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'matches';

if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $match = $pdo->prepare('SELECT * FROM matches WHERE id=?');
    $match->execute([$id]);
    $m = $match->fetch(PDO::FETCH_ASSOC);

    if ($m) {
        if ($_GET['action'] === 'confirm') {
            $pdo->prepare("UPDATE matches SET status='confirmed' WHERE id=?")->execute([$id]);
            // notify both the owner (lost) and finder (found)
            $lost = $pdo->prepare('SELECT user_id, item_name FROM lost_items WHERE id=?');
            $lost->execute([$m['lost_item_id']]); $lostRow = $lost->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)')
                ->execute([$lostRow['user_id'], 'Good news! A possible match for your lost item "' . $lostRow['item_name'] . '" has been confirmed. Please visit the Security Unit with proof of ownership.']);
        } elseif ($_GET['action'] === 'reject') {
            $pdo->prepare("UPDATE matches SET status='rejected' WHERE id=?")->execute([$id]);
            $pdo->prepare("UPDATE lost_items SET status='pending' WHERE id=?")->execute([$m['lost_item_id']]);
            $pdo->prepare("UPDATE found_items SET status='pending' WHERE id=?")->execute([$m['found_item_id']]);
        } elseif ($_GET['action'] === 'release') {
            $pdo->prepare("UPDATE matches SET status='released' WHERE id=?")->execute([$id]);
            $pdo->prepare("UPDATE lost_items SET status='resolved' WHERE id=?")->execute([$m['lost_item_id']]);
            $pdo->prepare("UPDATE found_items SET status='claimed' WHERE id=?")->execute([$m['found_item_id']]);
        }
    }
    header('Location: matches.php');
    exit;
}

$matches = $pdo->query(
    'SELECT m.*, l.item_name AS lost_name, l.location_lost, l.description AS lost_description, lu.full_name AS lost_owner,
            f.item_name AS found_name, f.location_found, f.description AS found_description, fu.full_name AS finder
     FROM matches m
     JOIN lost_items l ON l.id = m.lost_item_id
     JOIN users lu ON lu.id = l.user_id
     JOIN found_items f ON f.id = m.found_item_id
     JOIN users fu ON fu.id = f.user_id
     ORDER BY m.match_date DESC'
)->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Item Matches</h1>
    <p>Verify potential matches between lost and found reports before releasing items to owners.</p>
  </div>

  <div class="card">
    <?php if (empty($matches)): ?>
      <div class="empty-state">No matches have been proposed yet. Go to "Lost Reports" to link a lost item with a found one.</div>
    <?php else: ?>
    <table>
      <tr><th>Lost Item</th><th>Found Item</th><th>Lost Location</th><th>Found Location</th><th>Status</th><th>Date</th><th>Action</th></tr>
      <?php foreach ($matches as $m): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($m['lost_name']) ?></strong>
            <div style="color:var(--muted);font-size:12px;">by <?= htmlspecialchars($m['lost_owner']) ?></div>
            <div style="color:var(--muted);font-size:12px; max-width:260px;"><?= nl2br(htmlspecialchars($m['lost_description'])) ?></div>
          </td>
          <td>
            <strong><?= htmlspecialchars($m['found_name']) ?></strong>
            <div style="color:var(--muted);font-size:12px;">by <?= htmlspecialchars($m['finder']) ?></div>
            <div style="color:var(--muted);font-size:12px; max-width:260px;"><?= nl2br(htmlspecialchars($m['found_description'])) ?></div>
          </td>
          <td><?= htmlspecialchars($m['location_lost']) ?></td>
          <td><?= htmlspecialchars($m['location_found']) ?></td>
          <td><span class="badge badge-<?= str_replace('pending_verification','pending',$m['status']) ?>"><?= ucfirst(str_replace('_',' ',$m['status'])) ?></span></td>
          <td><?= date('d M Y', strtotime($m['match_date'])) ?></td>
          <td>
            <?php if ($m['status'] === 'pending_verification'): ?>
              <a href="?action=confirm&id=<?= $m['id'] ?>" class="btn btn-sm btn-success">Confirm</a>
              <a href="?action=reject&id=<?= $m['id'] ?>" class="btn btn-sm btn-danger">Reject</a>
            <?php elseif ($m['status'] === 'confirmed'): ?>
              <a href="?action=release&id=<?= $m['id'] ?>" class="btn btn-sm btn-primary">Mark Released</a>
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

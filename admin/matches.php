<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();

require_once '../includes/helpers.php';
require_once '../includes/pagination.php';
$page_title = 'Matches | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'matches';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['match_id'] ?? 0);

    if ($id > 0 && in_array($action, ['confirm', 'reject', 'release'], true)) {
        $stmt = $pdo->prepare('SELECT * FROM matches WHERE id = ?');
        $stmt->execute([$id]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($m) {
            try {
                $pdo->beginTransaction();

                // Fetch details for notifications
                $lostStmt = $pdo->prepare('SELECT user_id, item_name FROM lost_items WHERE id = ?');
                $lostStmt->execute([$m['lost_item_id']]);
                $lostRow = $lostStmt->fetch(PDO::FETCH_ASSOC);

                $foundStmt = $pdo->prepare('SELECT user_id, item_name FROM found_items WHERE id = ?');
                $foundStmt->execute([$m['found_item_id']]);
                $foundRow = $foundStmt->fetch(PDO::FETCH_ASSOC);

                $notifStmt = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');

                if ($action === 'confirm') {
                    $pdo->prepare("UPDATE matches SET status='confirmed' WHERE id=?")->execute([$id]);

                    if ($lostRow) {
                        $notifStmt->execute([
                            $lostRow['user_id'],
                            'Good news! A possible match for your lost item "' . $lostRow['item_name'] . '" has been confirmed. Please visit the Security Unit with proof of ownership.'
                        ]);
                    }
                    if ($foundRow && (!isset($lostRow['user_id']) || $foundRow['user_id'] !== $lostRow['user_id'])) {
                        $notifStmt->execute([
                            $foundRow['user_id'],
                            'Update: A match has been confirmed for the item "' . $foundRow['item_name'] . '" you turned in. The Security Unit is handling owner verification.'
                        ]);
                    }
                    log_audit_action($pdo, $_SESSION['user_id'], 'CONFIRM_MATCH', 'matches', $id, "Confirmed match #{$id}");
                    flash('success', 'Match confirmed and bilateral notifications dispatched.');
                } elseif ($action === 'reject') {
                    $pdo->prepare("UPDATE matches SET status='rejected' WHERE id=?")->execute([$id]);
                    $pdo->prepare("UPDATE lost_items SET status='pending' WHERE id=?")->execute([$m['lost_item_id']]);
                    $pdo->prepare("UPDATE found_items SET status='pending' WHERE id=?")->execute([$m['found_item_id']]);

                    if ($lostRow) {
                        $notifStmt->execute([
                            $lostRow['user_id'],
                            'A proposed match for your item "' . $lostRow['item_name'] . '" did not match. Your report remains active and pending.'
                        ]);
                    }
                    log_audit_action($pdo, $_SESSION['user_id'], 'REJECT_MATCH', 'matches', $id, "Rejected match #{$id}");
                    flash('success', 'Match rejected. Both lost and found items returned to pending status.');
                } elseif ($action === 'release') {
                    $pdo->prepare("UPDATE matches SET status='released' WHERE id=?")->execute([$id]);
                    $pdo->prepare("UPDATE lost_items SET status='resolved' WHERE id=?")->execute([$m['lost_item_id']]);
                    $pdo->prepare("UPDATE found_items SET status='claimed' WHERE id=?")->execute([$m['found_item_id']]);

                    if ($lostRow) {
                        $notifStmt->execute([
                            $lostRow['user_id'],
                            'Your item "' . $lostRow['item_name'] . '" has been officially marked as released and reunited. Thank you!'
                        ]);
                    }
                    if ($foundRow && (!isset($lostRow['user_id']) || $foundRow['user_id'] !== $lostRow['user_id'])) {
                        $notifStmt->execute([
                            $foundRow['user_id'],
                            'Great news! The item "' . $foundRow['item_name'] . '" you found was successfully returned to its owner. Thank you for your honesty!'
                        ]);
                    }

                    // Auto-record release voucher
                    $voucherNo = 'PRS-VOUCH-' . date('Y') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
                    $claimantInfoStmt = $pdo->prepare('SELECT u.id, u.full_name, u.id_number, u.phone, u.department FROM users u JOIN lost_items l ON l.user_id = u.id WHERE l.id = ?');
                    $claimantInfoStmt->execute([$m['lost_item_id']]);
                    $claimant = $claimantInfoStmt->fetch(PDO::FETCH_ASSOC);

                    if ($claimant) {
                        $vouchStmt = $pdo->prepare(
                            'INSERT INTO vouchers (voucher_no, match_id, found_item_id, claimant_id, issued_by, claimant_name, claimant_id_number, claimant_phone, claimant_department, remarks)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE release_date = CURRENT_TIMESTAMP'
                        );
                        $vouchStmt->execute([
                            $voucherNo,
                            $id,
                            $m['found_item_id'],
                            $claimant['id'],
                            $_SESSION['user_id'],
                            $claimant['full_name'],
                            $claimant['id_number'],
                            $claimant['phone'],
                            $claimant['department'] ?? 'General',
                            'Official handover verified by Security Unit.'
                        ]);
                    }

                    log_audit_action($pdo, $_SESSION['user_id'], 'RELEASE_ITEM', 'matches', $id, "Released match #{$id} with voucher {$voucherNo}");
                    flash('success', "Item marked as released and claimed. Voucher {$voucherNo} generated.");
                }

                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash('error', 'Operation failed: ' . $e->getMessage());
            }
        }
    }

    header('Location: matches.php');
    exit;
}

$countSql = 'SELECT COUNT(*) FROM matches';
$selectSql = 'SELECT m.*, l.item_name AS lost_name, l.location_lost, l.description AS lost_description, lu.full_name AS lost_owner,
                     f.item_name AS found_name, f.location_found, f.description AS found_description, fu.full_name AS finder,
                     v.voucher_no
              FROM matches m
              JOIN lost_items l ON l.id = m.lost_item_id
              JOIN users lu ON lu.id = l.user_id
              JOIN found_items f ON f.id = m.found_item_id
              JOIN users fu ON fu.id = f.user_id
              LEFT JOIN vouchers v ON v.match_id = m.id
              ORDER BY m.match_date DESC';

$pagination = paginate($pdo, $countSql, $selectSql, [], 10, 'page');
$matches = $pagination['items'];

include '../includes/header.php';
$success = flash('success');
$error = flash('error');
?>
<div class="page">
  <div class="page-header">
    <h1>Item Matches</h1>
    <p>Verify potential matches between lost and found reports before releasing items to owners.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

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
              <div style="display:flex; gap:6px; flex-wrap:wrap;">
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="action" value="confirm">
                  <button type="submit" class="btn btn-sm btn-success">Confirm</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Reject this match and return items to pending?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                </form>
              </div>
            <?php elseif ($m['status'] === 'confirmed'): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Mark this item as officially released and collected by the owner?');">
                <?= csrf_field() ?>
                <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                <input type="hidden" name="action" value="release">
                <button type="submit" class="btn btn-sm btn-primary">Mark Released</button>
              </form>
            <?php elseif ($m['status'] === 'released'): ?>
              <a href="voucher.php?match_id=<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline" target="_blank" style="font-size:12px;">&#128196; Voucher</a>
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

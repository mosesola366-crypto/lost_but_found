<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/pagination.php';
require_admin();

$page_title = 'Review Claims | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'claims';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $claim_id = (int)($_POST['claim_id'] ?? 0);
    $action   = $_POST['action'] ?? '';
    $notes    = trim($_POST['admin_notes'] ?? '');

    if ($claim_id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $stmt = $pdo->prepare('SELECT c.*, f.item_name, f.user_id AS finder_id FROM claims c JOIN found_items f ON f.id = c.found_item_id WHERE c.id = ?');
        $stmt->execute([$claim_id]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($claim && $claim['status'] === 'pending') {
            try {
                $pdo->beginTransaction();

                if ($action === 'approve') {
                    $pdo->prepare('UPDATE claims SET status = "approved", admin_notes = ? WHERE id = ?')
                        ->execute([$notes, $claim_id]);

                    $pdo->prepare('UPDATE found_items SET status = "matched" WHERE id = ?')
                        ->execute([$claim['found_item_id']]);

                    notify_user(
                        $pdo,
                        $claim['user_id'],
                        'Congratulations! Your claim for "' . $claim['item_name'] . '" was approved by Security. Please visit Security Unit to collect your item.',
                        'dashboard.php'
                    );

                    log_audit_action($pdo, $_SESSION['user_id'], 'APPROVE_CLAIM', 'claims', $claim_id, "Approved claim for found item #{$claim['found_item_id']}");
                    flash('success', 'Claim approved successfully. Claimant has been notified.');
                } else {
                    $pdo->prepare('UPDATE claims SET status = "rejected", admin_notes = ? WHERE id = ?')
                        ->execute([$notes, $claim_id]);

                    notify_user(
                        $pdo,
                        $claim['user_id'],
                        'Your claim for "' . $claim['item_name'] . '" was reviewed and could not be verified by the Security Unit.',
                        'dashboard.php'
                    );

                    log_audit_action($pdo, $_SESSION['user_id'], 'REJECT_CLAIM', 'claims', $claim_id, "Rejected claim for found item #{$claim['found_item_id']}");
                    flash('success', 'Claim rejected.');
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
    header('Location: manage_claims.php');
    exit;
}

$statusFilter = trim($_GET['status'] ?? 'pending');
$countSql  = 'SELECT COUNT(*) FROM claims WHERE 1=1';
$selectSql = 'SELECT c.*, f.item_name, f.location_found, f.date_found, f.image_path,
                     u.full_name AS claimant_name, u.id_number, u.department, u.phone AS claimant_phone
              FROM claims c
              JOIN found_items f ON f.id = c.found_item_id
              JOIN users u ON u.id = c.user_id
              WHERE 1=1';
$params = [];

if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $clause = ' AND c.status = ?';
    $countSql  .= $clause;
    $selectSql .= $clause;
    $params[] = $statusFilter;
}

$selectSql .= ' ORDER BY c.created_at DESC';

$pagination = paginate($pdo, $countSql, $selectSql, $params, 8, 'page');
$claims = $pagination['items'];

include '../includes/header.php';
$success = flash('success');
$error = flash('error');
?>
<div class="page">
  <div class="page-header">
    <h1>Student & Staff Item Claims</h1>
    <p>Review proof of ownership submitted by students asserting ownership over recovered property.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="tabs">
    <a href="?status=pending" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending Review</a>
    <a href="?status=approved" class="<?= $statusFilter === 'approved' ? 'active' : '' ?>">Approved Claims</a>
    <a href="?status=rejected" class="<?= $statusFilter === 'rejected' ? 'active' : '' ?>">Rejected Claims</a>
    <a href="?status=" class="<?= $statusFilter === '' ? 'active' : '' ?>">All Claims</a>
  </div>

  <div class="card">
    <?php if (empty($claims)): ?>
      <div class="empty-state">No claims found under this filter.</div>
    <?php else: ?>
      <?php foreach ($claims as $c): ?>
        <div style="border:1px solid var(--border); border-radius:10px; padding:18px; margin-bottom:16px; background:#fff;">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
            <div>
              <strong style="font-size:16px; color:var(--navy-dark);"><?= htmlspecialchars($c['item_name']) ?></strong>
              <?= render_status_badge($c['status']) ?>
              <div style="color:var(--muted); font-size:12.5px; margin-top:4px;">
                Found: <?= htmlspecialchars($c['location_found']) ?> &middot; Date: <?= date('d M Y', strtotime($c['date_found'])) ?>
              </div>
            </div>
            <div style="text-align:right;">
              <small style="color:var(--muted);">Submitted: <?= date('d M Y, H:i', strtotime($c['created_at'])) ?></small>
            </div>
          </div>

          <div class="grid-2" style="margin-top:14px; padding-top:14px; border-top:1px solid var(--border);">
            <div>
              <span style="font-size:12px; font-weight:700; color:var(--muted); text-transform:uppercase;">Claimant Particulars</span>
              <div style="font-size:14px; font-weight:600; color:var(--navy-dark); margin-top:4px;"><?= htmlspecialchars($c['claimant_name']) ?></div>
              <div style="font-size:13px; color:var(--text);">Matric/Staff ID: <?= htmlspecialchars($c['id_number']) ?></div>
              <div style="font-size:13px; color:var(--text);">Dept: <?= htmlspecialchars($c['department'] ?? 'N/A') ?> &middot; Phone: <?= htmlspecialchars($c['contact_phone']) ?></div>
            </div>

            <div>
              <span style="font-size:12px; font-weight:700; color:var(--muted); text-transform:uppercase;">Proof of Ownership Submitted</span>
              <div style="background:#f8f9fb; border-radius:6px; padding:10px 12px; font-size:13.5px; color:var(--text); margin-top:4px; white-space:pre-wrap; border:1px solid #e2e6ec;"><?= htmlspecialchars($c['proof_details']) ?></div>
            </div>
          </div>

          <?php if (!empty($c['admin_notes'])): ?>
            <div style="margin-top:10px; font-size:13px; color:var(--muted);">
              <strong>Security Officer Notes:</strong> <?= htmlspecialchars($c['admin_notes']) ?>
            </div>
          <?php endif; ?>

          <?php if ($c['status'] === 'pending'): ?>
            <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px; padding-top:12px; border-top:1px dashed var(--border);">
              <form method="post" style="display:inline;" onsubmit="return confirm('Approve this claim? The claimant will be asked to come to the Security Unit for handover.');">
                <?= csrf_field() ?>
                <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="admin_notes" value="Verified by Security Unit.">
                <button type="submit" class="btn btn-sm btn-success">Approve Claim</button>
              </form>

              <form method="post" style="display:inline;" onsubmit="return confirm('Reject this claim?');">
                <?= csrf_field() ?>
                <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="admin_notes" value="Proof insufficient or mismatched.">
                <button type="submit" class="btn btn-sm btn-danger">Reject Claim</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?= render_pagination($pagination['current_page'], $pagination['total_pages'], ['status']) ?>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

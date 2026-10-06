<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/pagination.php';
require_admin();

$page_title = 'Users | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    verify_csrf();

    $id = (int)($_POST['user_id'] ?? 0);
    if ($id > 0 && $id !== (int)$_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active','suspended','active') WHERE id=? AND role='user'");
        $stmt->execute([$id]);

        $userStmt = $pdo->prepare('SELECT full_name, status FROM users WHERE id = ?');
        $userStmt->execute([$id]);
        $u = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($u) {
            $actionWord = $u['status'] === 'suspended' ? 'suspended' : 'reactivated';
            log_audit_action($pdo, $_SESSION['user_id'], 'TOGGLE_USER_STATUS', 'users', $id, "Set user account status to {$u['status']}");
            flash('success', "User \"{$u['full_name']}\" account has been {$actionWord}.");
        }
    }
    header('Location: users.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$countSql  = "SELECT COUNT(*) FROM users WHERE role = 'user'";
$selectSql = "SELECT * FROM users WHERE role = 'user'";
$params = [];

if ($search !== '') {
    $clause = " AND (full_name LIKE ? OR id_number LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $countSql  .= $clause;
    $selectSql .= $clause;
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($statusFilter !== '' && in_array($statusFilter, ['active', 'suspended'], true)) {
    $clause = " AND status = ?";
    $countSql  .= $clause;
    $selectSql .= $clause;
    $params[] = $statusFilter;
}

$selectSql .= " ORDER BY created_at DESC";

$pagination = paginate($pdo, $countSql, $selectSql, $params, 12, 'page');
$users = $pagination['items'];

include '../includes/header.php';
$success = flash('success');
$error = flash('error');
?>
<div class="page">
  <div class="page-header">
    <h1>Campus Reporters &amp; Users</h1>
    <p>Students and staff identified through property reports, claims, and campus recovery records.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card">
    <form method="get" class="form-row" style="align-items:end; margin-bottom:18px;">
      <div class="field" style="margin-bottom:0;">
        <label>Search User</label>
        <input type="text" name="q" placeholder="Name, Matric/Staff ID, email, or phone" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="field" style="margin-bottom:0;">
        <label>Account Status</label>
        <select name="status">
          <option value="">All statuses</option>
          <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;">
        <button type="submit" class="btn btn-primary">Filter Users</button>
      </div>
    </form>

    <?php if (empty($users)): ?>
      <div class="empty-state">No matching registered users found.</div>
    <?php else: ?>
    <table>
      <tr><th>Name</th><th>ID Number</th><th>Email</th><th>Phone</th><th>Department</th><th>Status</th><th>Action</th></tr>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['full_name']) ?></td>
          <td><?= htmlspecialchars($u['id_number']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['phone']) ?></td>
          <td><?= htmlspecialchars($u['department'] ?? 'N/A') ?></td>
          <td><span class="badge badge-<?= $u['status'] === 'active' ? 'confirmed' : 'rejected' ?>"><?= ucfirst($u['status']) ?></span></td>
          <td>
            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to <?= $u['status'] === 'active' ? 'suspend' : 'reactivate' ?> <?= htmlspecialchars(addslashes($u['full_name'])) ?>?');">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <input type="hidden" name="toggle_status" value="1">
              <button type="submit" class="btn btn-sm btn-outline" style="<?= $u['status'] === 'active' ? 'color:var(--danger);border-color:var(--danger);' : 'color:var(--success);border-color:var(--success);' ?>">
                <?= $u['status'] === 'active' ? 'Suspend' : 'Reactivate' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

    <?= render_pagination($pagination['current_page'], $pagination['total_pages'], ['q', 'status']) ?>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

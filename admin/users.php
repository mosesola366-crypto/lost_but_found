<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_admin();
$page_title = 'Users | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'users';

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($id !== (int)$_SESSION['user_id']) {
        $pdo->prepare("UPDATE users SET status = IF(status='active','suspended','active') WHERE id=? AND role='user'")->execute([$id]);
    }
    header('Location: users.php');
    exit;
}

$users = $pdo->query("SELECT * FROM users WHERE role='user' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
include '../includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Registered Users</h1>
    <p>Students and staff who have registered on the Property Reporting and Recovery System.</p>
  </div>

  <div class="card">
    <?php if (empty($users)): ?>
      <div class="empty-state">No users have registered yet.</div>
    <?php else: ?>
    <table>
      <tr><th>Name</th><th>ID Number</th><th>Email</th><th>Phone</th><th>Department</th><th>Status</th><th>Action</th></tr>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= htmlspecialchars($u['full_name']) ?></td>
          <td><?= htmlspecialchars($u['id_number']) ?></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['phone']) ?></td>
          <td><?= htmlspecialchars($u['department']) ?></td>
          <td><span class="badge badge-<?= $u['status'] === 'active' ? 'confirmed' : 'rejected' ?>"><?= ucfirst($u['status']) ?></span></td>
          <td><a href="?toggle=<?= $u['id'] ?>" class="btn btn-sm btn-outline"><?= $u['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></a></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

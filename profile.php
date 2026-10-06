<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_login();

$page_title = 'My Profile & Security | Property Reporting and Recovery System';
$asset_path = '';
$active = 'profile';
$uid = $_SESSION['user_id'];
$errors = [];
$success = flash('success');

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['update_profile'])) {
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');

        if ($phone === '') {
            $errors[] = 'Phone number is required.';
        } else {
            $update = $pdo->prepare('UPDATE users SET phone = ?, department = ? WHERE id = ?');
            $update->execute([$phone, $department, $uid]);
            flash('success', 'Profile information updated successfully.');
            header('Location: profile.php');
            exit;
        }
    }

    if (isset($_POST['change_password'])) {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 8 || !preg_match('/[A-Za-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
            $errors[] = 'New password must be at least 8 characters long and contain both letters and numbers.';
        } elseif ($newPass !== $confirmPass) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([$newHash, $uid]);
            log_audit_action($pdo, $uid, 'CHANGE_PASSWORD', 'users', $uid, 'User successfully changed password.');
            flash('success', 'Your password has been changed successfully.');
            header('Location: profile.php');
            exit;
        }
    }
}

include 'includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>My Account &amp; Security</h1>
    <p>Manage your campus contact details and account credentials.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if (!empty($errors)): ?><div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>

  <div class="grid-2" style="align-items:start;">
    <div class="card">
      <h2>Profile Particulars</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="update_profile" value="1">

        <div class="field">
          <label>Full Name</label>
          <input type="text" value="<?= htmlspecialchars($user['full_name']) ?>" disabled style="background:#f8f9fb; cursor:not-allowed;">
        </div>

        <div class="field">
          <label>Matric / Staff ID</label>
          <input type="text" value="<?= htmlspecialchars($user['id_number']) ?>" disabled style="background:#f8f9fb; cursor:not-allowed;">
        </div>

        <div class="field">
          <label>Email Address</label>
          <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:#f8f9fb; cursor:not-allowed;">
        </div>

        <div class="field">
          <label>Department / Unit</label>
          <input type="text" name="department" value="<?= htmlspecialchars($_POST['department'] ?? $user['department'] ?? '') ?>">
        </div>

        <div class="field">
          <label>Phone Number (Active for notifications)</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone']) ?>" required>
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>
    </div>

    <div class="card">
      <h2>Change Password</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="change_password" value="1">

        <div class="field">
          <label>Current Password</label>
          <input type="password" name="current_password" required>
        </div>

        <div class="field">
          <label>New Password (min 8 chars, letter & number)</label>
          <input type="password" name="new_password" required>
        </div>

        <div class="field">
          <label>Confirm New Password</label>
          <input type="password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn btn-gold">Update Password</button>
      </form>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

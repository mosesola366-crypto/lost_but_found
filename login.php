<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$page_title = 'Security Personnel Login | Property Reporting and Recovery System';
$asset_path = '';
$active = 'login';
$error = flash('error');
$success = flash('success');

if (is_logged_in()) {
    header('Location: ' . (is_admin() ? 'admin/dashboard.php' : 'track.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        if (($user['status'] ?? 'active') === 'suspended') {
            $error = 'This account has been suspended by the Security Unit.';
        } elseif ($user['role'] !== 'admin') {
            // Friendly redirect for non-admin users attempting password login
            flash('info', 'Student & staff reporting no longer requires a login or password. You can track your reports directly with your Matric/Staff ID.');
            header('Location: track.php?id_number=' . urlencode($user['id_number']) . '&phone=' . urlencode($user['phone']));
            exit;
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['id_number'] = $user['id_number'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['role'] = $user['role'];
            header('Location: admin/dashboard.php');
            exit;
        }
    } else {
        $error = 'Invalid administrator credentials. Please check your email and password.';
    }
}
include 'includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-box">
    <div class="crest-lg">PI</div>
    <h1>Security Staff Login</h1>
    <div class="sub">Campus Security Unit &amp; Administrative Management Portal</div>

    <div style="margin:16px 0; padding:12px 14px; background:#f0f4f9; border-radius:8px; border-left:4px solid var(--navy-dark); font-size:13px; color:var(--text); line-height:1.5;">
      <strong>Notice for Students &amp; Staff:</strong><br>
      You do not need an account or password to report or track property.
      Please use <a href="report_lost.php" style="color:var(--navy-dark); font-weight:600; text-decoration:underline;">Report Lost</a> or <a href="track.php" style="color:var(--navy-dark); font-weight:600; text-decoration:underline;">Check Status</a>.
    </div>

    <div class="demo-hint">
      <strong>Admin login:</strong> admin@polyibadan.edu.ng / admin123
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label>Admin Email Address</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="admin@polyibadan.edu.ng" required autofocus>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary">Login to Admin Console</button>
    </form>
    <div class="auth-footer" style="margin-top:18px;">
      Student or Staff? <a href="track.php">Check Status</a> &middot; <a href="report_lost.php">Report Lost Item</a>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

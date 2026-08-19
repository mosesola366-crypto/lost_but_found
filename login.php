<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
$page_title = 'Login | Property Reporting and Recovery System';
$asset_path = '';
$error = null;
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        header('Location: ' . ($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
        exit;
    } else {
        $error = 'Incorrect email or password. Please try again.';
    }
}
include 'includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-box">
    <div class="crest-lg">PI</div>
    <h1>Welcome back</h1>
    <div class="sub">Login to your Property Reporting and Recovery account</div>

    <div class="demo-hint">
      <strong>Admin demo login:</strong> admin@polyibadan.edu.ng / admin123
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post">
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary">Log In</button>
    </form>
    <div class="auth-footer">Don't have an account? <a href="register.php">Register here</a></div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

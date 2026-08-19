<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
$page_title = 'Register | Property Reporting and Recovery System';
$asset_path = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $id_number === '' || $email === '' || $phone === '' || $password === '') {
        $errors[] = 'Please fill in all required fields.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists. Please login instead.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, id_number, email, phone, department, password_hash, role)
             VALUES (?, ?, ?, ?, ?, ?, "user")'
        );
        $stmt->execute([$full_name, $id_number, $email, $phone, $department, $hash]);
        flash('success', 'Registration successful! You can now log in.');
        header('Location: login.php');
        exit;
    }
}
include 'includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-box" style="max-width:480px;">
    <div class="crest-lg">PI</div>
    <h1>Create your account</h1>
    <div class="sub">Register to report or search for lost and found property</div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
      </div>
      <div class="form-row">
        <div class="field">
          <label>Matric / Staff ID</label>
          <input type="text" name="id_number" value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Department</label>
          <input type="text" name="department" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label>Email Address</label>
          <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Phone Number</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" required>
        </div>
        <div class="field">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" required>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Create Account</button>
    </form>
    <div class="auth-footer">Already have an account? <a href="login.php">Log in</a></div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

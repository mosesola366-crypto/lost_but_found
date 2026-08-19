<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();
$page_title = 'Report Lost Item | Property Reporting and Recovery System';
$asset_path = '';
$active = 'report_lost';
$errors = [];

$categories = ['Electronics','Documents','Jewelry','Bags','Clothing','Keys','Books','Wallet/Purse','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location_lost = trim($_POST['location_lost'] ?? '');
    $date_lost = $_POST['date_lost'] ?? '';
    $contact_phone = trim($_POST['contact_phone'] ?? '');

    if ($item_name === '' || $category === '' || $description === '' || $location_lost === '' || $date_lost === '' || $contact_phone === '') {
        $errors[] = 'Please fill in all required fields.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO lost_items (user_id, item_name, category, description, location_lost, date_lost, contact_phone)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$_SESSION['user_id'], $item_name, $category, $description, $location_lost, $date_lost, $contact_phone]);
        flash('success', 'Your lost item report has been submitted successfully.');
        header('Location: dashboard.php');
        exit;
    }
}
include 'includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Report a Lost Item</h1>
    <p>Provide as much detail as possible to help the Security Unit match your item.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:640px;">
    <form method="post">
      <div class="field">
        <label>Item Name</label>
        <input type="text" name="item_name" placeholder="e.g. Samsung A14 Phone" value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Category</label>
        <select name="category" required>
          <option value="">Select category</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c ?>" <?= (($_POST['category'] ?? '') === $c) ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Description</label>
        <textarea name="description" placeholder="Colour, brand, distinguishing marks, contents, etc." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <div class="field">
          <label>Location Lost</label>
          <input type="text" name="location_lost" placeholder="e.g. ICT Library, 2nd floor" value="<?= htmlspecialchars($_POST['location_lost'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Date Lost</label>
          <input type="date" name="date_lost" value="<?= htmlspecialchars($_POST['date_lost'] ?? '') ?>" required>
        </div>
      </div>
      <div class="field">
        <label>Contact Phone Number</label>
        <input type="tel" name="contact_phone" placeholder="080XXXXXXXX" value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>" required>
      </div>
      <button type="submit" class="btn btn-primary">Submit Report</button>
    </form>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

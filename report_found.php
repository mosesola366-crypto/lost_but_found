<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();
$page_title = 'Report Found Item | Property Reporting and Recovery System';
$asset_path = '';
$active = 'report_found';
$errors = [];

$categories = ['Electronics','Documents','Jewelry','Bags','Clothing','Keys','Books','Wallet/Purse','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location_found = trim($_POST['location_found'] ?? '');
    $date_found = $_POST['date_found'] ?? '';

    if ($item_name === '' || $category === '' || $description === '' || $location_found === '' || $date_found === '') {
        $errors[] = 'Please fill in all required fields.';
    }

    $image_path = null;
    if (empty($errors) && !empty($_FILES['image']['name'])) {
        $allowed = ['jpg','jpeg','png'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $filename = uniqid('item_') . '.' . $ext;
            $dest = __DIR__ . '/uploads/' . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $image_path = 'uploads/' . $filename;
            }
        } else {
            $errors[] = 'Only JPG and PNG images are allowed.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO found_items (user_id, item_name, category, description, location_found, date_found, image_path)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$_SESSION['user_id'], $item_name, $category, $description, $location_found, $date_found, $image_path]);
        flash('success', 'Thank you! Your found item report has been submitted.');
        header('Location: dashboard.php');
        exit;
    }
}
include 'includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Report a Found Item</h1>
    <p>Help reunite this item with its owner by providing accurate details.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:640px;">
    <form method="post" enctype="multipart/form-data">
      <div class="field">
        <label>Item Name</label>
        <input type="text" name="item_name" placeholder="e.g. Black Umbrella" value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>" required>
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
        <textarea name="description" placeholder="Colour, brand, condition, contents, etc." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <div class="field">
          <label>Location Found</label>
          <input type="text" name="location_found" placeholder="e.g. Senate Building car park" value="<?= htmlspecialchars($_POST['location_found'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Date Found</label>
          <input type="date" name="date_found" value="<?= htmlspecialchars($_POST['date_found'] ?? '') ?>" required>
        </div>
      </div>
      <div class="field">
        <label>Photo of Item (optional)</label>
        <input type="file" name="image" accept="image/png, image/jpeg">
      </div>
      <button type="submit" class="btn btn-gold">Submit Report</button>
    </form>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

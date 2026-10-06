<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$page_title = 'Report Found Item | Property Reporting and Recovery System';
$asset_path = '';
$active = 'report_found';
$errors = [];

$categories = PRS_CATEGORIES;
$faculties  = PRS_FACULTIES;

$default_name  = $_SESSION['full_name'] ?? ($_POST['full_name'] ?? '');
$default_id    = $_SESSION['id_number'] ?? ($_POST['id_number'] ?? ($_GET['id_number'] ?? ''));
$default_phone = $_SESSION['phone'] ?? ($_POST['contact_phone'] ?? ($_GET['phone'] ?? ''));
$default_dept  = $_SESSION['department'] ?? ($_POST['department'] ?? '');
$default_fac   = $_POST['faculty'] ?? (get_faculty_for_department($default_dept) ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $full_name      = trim($_POST['full_name'] ?? '');
    $id_number      = strtoupper(trim($_POST['id_number'] ?? ''));
    $raw_phone      = trim($_POST['contact_phone'] ?? '');
    $faculty        = trim($_POST['faculty'] ?? '');
    $department     = trim($_POST['department'] ?? '');
    $final_dept     = $department !== '' ? $department : ($faculty !== '' ? $faculty : null);

    $item_name      = trim($_POST['item_name'] ?? '');
    $category       = trim($_POST['category'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $location_found = trim($_POST['location_found'] ?? '');
    $date_found     = $_POST['date_found'] ?? '';

    // Identifier validation
    $nameError = validate_full_name($full_name);
    if ($nameError) {
        $errors[] = $nameError;
    }

    $matricError = validate_matric_number($id_number);
    if ($matricError) {
        $errors[] = $matricError;
    }

    // Nigerian phone validation
    $phoneError = validate_ng_phone($raw_phone);
    if ($phoneError) {
        $errors[] = $phoneError;
    }

    if ($item_name === '' || $category === '' || $description === '' || $location_found === '' || $date_found === '') {
        $errors[] = 'Please fill in all required found item fields.';
    }

    $dateError = validate_past_or_today_date($date_found, 'Date found');
    if ($dateError) {
        $errors[] = $dateError;
    }


    if (!in_array($category, $categories, true)) {
        $errors[] = 'Please select a valid item category.';
    }

    $image_path = null;
    if (empty($errors) && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Photo upload encountered an error. Please try again.';
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Image size exceeds maximum allowed limit of 3MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            if (!isset($allowedMimes[$mime])) {
                $errors[] = 'Invalid image type. Only JPG, PNG, and WebP images are permitted.';
            } else {
                $imageInfo = @getimagesize($file['tmp_name']);
                if ($imageInfo === false) {
                    $errors[] = 'The uploaded file is not a valid image.';
                } else {
                    $ext = $allowedMimes[$mime];
                    $filename = 'found_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    $uploadDir = __DIR__ . '/uploads/';

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $dest = $uploadDir . $filename;
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $image_path = 'uploads/' . $filename;
                    } else {
                        $errors[] = 'Failed to save uploaded image. Please check server folder permissions.';
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            // Normalise phone to E.164
            $contact_phone = normalise_ng_phone($raw_phone);

            $userId = get_or_create_reporter($pdo, $id_number, $contact_phone, $full_name, $final_dept);

            $stmt = $pdo->prepare(
                'INSERT INTO found_items (user_id, item_name, category, description, location_found, date_found, image_path, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $stmt->execute([$userId, $item_name, $category, $description, $location_found, $date_found, $image_path]);
            $reportId = (int)$pdo->lastInsertId();

            notify_user($pdo, $userId, "Your report for found item '{$item_name}' was received under reference #" . str_pad((string)$reportId, 4, '0', STR_PAD_LEFT) . ".", "track.php?id_number=" . urlencode($id_number) . "&phone=" . urlencode($contact_phone));
            log_audit_action($pdo, $userId, 'REPORT_FOUND', 'found_items', $reportId, "Identifier-based found report for '{$item_name}' by {$id_number}");

            flash('success', 'Thank you! Your found item report has been logged with the Security Unit. Track it anytime with your Matric/Staff ID and Phone Number.');
            header('Location: track.php?id_number=' . urlencode($id_number) . '&phone=' . urlencode($contact_phone));
            exit;
        } catch (RuntimeException | InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        } catch (PDOException $e) {
            $errors[] = 'Database error recording your report. Please try again.';
        }
    }
}
include 'includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Report a Found Item</h1>
    <p>Help reunite recovered property with its rightful owner. No login or password is required.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:680px;">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <h3 style="margin-top:0; margin-bottom:12px; font-size:16px; color:var(--navy-dark); border-bottom:1px solid var(--border); padding-bottom:8px;">
        1. Finder Identification
      </h3>
      <p style="font-size:13px; color:var(--muted); margin-top:-6px; margin-bottom:14px;">
        The Security Unit records your details for official verification and recovery handover.
      </p>

      <div class="form-row">
        <div class="field">
          <label>Full Name <span style="color:#d9534f;">*</span></label>
          <input type="text" name="full_name" class="prs-name-input" placeholder="e.g. Ayo Musa" value="<?= htmlspecialchars($_POST['full_name'] ?? $default_name) ?>" required>
        </div>
        <div class="field">
          <label>Matric Number <span style="color:#d9534f;">*</span></label>
          <input type="text" name="id_number" class="prs-matric-input" placeholder="Enter 13-digit matric number" value="<?= htmlspecialchars($_POST['id_number'] ?? $default_id) ?>" required>
        </div>
      </div>

      <div class="field">
        <label>Phone Number (Active for calls) <span style="color:#d9534f;">*</span></label>
        <input type="tel" name="contact_phone" class="ng-phone-input"
          value="<?= htmlspecialchars($_POST['contact_phone'] ?? $default_phone) ?>" required>
      </div>

      <div class="form-row">
        <div class="field">
          <label>Faculty</label>
          <select name="faculty" class="prs-faculty-select">
            <option value="">-- Select Faculty --</option>
            <?php foreach ($faculties as $facName => $deptList): ?>
              <option value="<?= htmlspecialchars($facName) ?>" <?= ($default_fac === $facName) ? 'selected' : '' ?>>
                <?= htmlspecialchars($facName) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Department in Faculty</label>
          <select name="department" class="prs-department-select" data-selected="<?= htmlspecialchars($_POST['department'] ?? $default_dept) ?>">
            <option value="">-- Select Faculty First --</option>
          </select>
        </div>
      </div>

      <h3 style="margin-top:22px; margin-bottom:12px; font-size:16px; color:var(--navy-dark); border-bottom:1px solid var(--border); padding-bottom:8px;">
        2. Found Property Particulars
      </h3>

      <div class="field">
        <label>Item Name <span style="color:#d9534f;">*</span></label>
        <input type="text" name="item_name" placeholder="e.g. Scientific Calculator or Black Backpack" value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>" required>
      </div>

      <div class="field">
        <label>Category <span style="color:#d9534f;">*</span></label>
        <select name="category" required>
          <option value="">Select category</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c ?>" <?= (($_POST['category'] ?? '') === $c) ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label>Description &amp; Condition <span style="color:#d9534f;">*</span></label>
        <textarea name="description" placeholder="Brand, general color, physical condition. Avoid disclosing concealed serial numbers or lock screen codes so the claimant can prove ownership." required rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="form-row">
        <div class="field">
          <label>Location Where Found <span style="color:#d9534f;">*</span></label>
          <input type="text" name="location_found" placeholder="e.g. Near Senate Building car park" value="<?= htmlspecialchars($_POST['location_found'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Date Found <span style="color:#d9534f;">*</span></label>
          <input type="date" name="date_found" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['date_found'] ?? '') ?>" required>
        </div>
      </div>

      <div class="field">
        <label>Photo of Item (Optional, max 3MB - JPG, PNG, WebP)</label>
        <input type="file" name="image" accept="image/png, image/jpeg, image/webp">
      </div>

      <div style="margin-top:24px; display:flex; gap:12px; align-items:center;">
        <button type="submit" class="btn btn-gold" style="padding:11px 22px;">Submit Found Report</button>
        <a href="track.php" class="btn btn-outline">Check Existing Status</a>
      </div>
    </form>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

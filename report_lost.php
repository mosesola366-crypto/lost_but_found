<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$page_title = 'Report Lost Item | Property Reporting and Recovery System';
$asset_path = '';
$active = 'report_lost';
$errors = [];

$categories = PRS_CATEGORIES;
$faculties  = PRS_FACULTIES;

// Prefill values if session exists or if passed in query
$default_name  = $_SESSION['full_name'] ?? ($_POST['full_name'] ?? '');
$default_id    = $_SESSION['id_number'] ?? ($_POST['id_number'] ?? ($_GET['id_number'] ?? ''));
$default_phone = $_SESSION['phone'] ?? ($_POST['contact_phone'] ?? ($_GET['phone'] ?? ''));
$default_dept  = $_SESSION['department'] ?? ($_POST['department'] ?? '');
$default_fac   = $_POST['faculty'] ?? (get_faculty_for_department($default_dept) ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $full_name     = trim($_POST['full_name'] ?? '');
    $id_number     = strtoupper(trim($_POST['id_number'] ?? ''));
    $raw_phone     = trim($_POST['contact_phone'] ?? '');
    $faculty       = trim($_POST['faculty'] ?? '');
    $department    = trim($_POST['department'] ?? '');
    $final_dept    = $department !== '' ? $department : ($faculty !== '' ? $faculty : null);

    $item_name     = trim($_POST['item_name'] ?? '');
    $category      = trim($_POST['category'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $location_lost = trim($_POST['location_lost'] ?? '');
    $date_lost     = $_POST['date_lost'] ?? '';

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

    if ($item_name === '' || $category === '' || $description === '' || $location_lost === '' || $date_lost === '') {
        $errors[] = 'Please fill in all required property details.';
    }

    $dateError = validate_past_or_today_date($date_lost, 'Date lost');
    if ($dateError) {
        $errors[] = $dateError;
    }


    if (!in_array($category, $categories, true)) {
        $errors[] = 'Please select a valid item category.';
    }

    if (empty($errors)) {
        try {
            // Normalise phone to E.164 before saving
            $contact_phone = normalise_ng_phone($raw_phone);

            // Resolve reporter identity without requiring session or password
            $userId = get_or_create_reporter($pdo, $id_number, $contact_phone, $full_name, $final_dept);

            $stmt = $pdo->prepare(
                'INSERT INTO lost_items (user_id, item_name, category, description, location_lost, date_lost, contact_phone, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $stmt->execute([$userId, $item_name, $category, $description, $location_lost, $date_lost, $contact_phone]);
            $reportId = (int)$pdo->lastInsertId();

            notify_user($pdo, $userId, "Your report for lost '{$item_name}' was received and logged under reference #" . str_pad((string)$reportId, 4, '0', STR_PAD_LEFT) . ".", "track.php?id_number=" . urlencode($id_number) . "&phone=" . urlencode($contact_phone));
            log_audit_action($pdo, $userId, 'REPORT_LOST', 'lost_items', $reportId, "Identifier-based report for '{$item_name}' by {$id_number}");

            flash('success', 'Your lost item report has been logged successfully! Track it anytime with your Matric Number and Phone Number.');
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
    <h1>Report a Lost Item</h1>
    <p>Provide your campus identifier and item details. No login or password is required.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <div class="card" style="max-width:680px;">
    <form method="post">
      <?= csrf_field() ?>

      <h3 style="margin-top:0; margin-bottom:12px; font-size:16px; color:var(--navy-dark); border-bottom:1px solid var(--border); padding-bottom:8px;">
        1. Reporter Identification
      </h3>
      <p style="font-size:13px; color:var(--muted); margin-top:-6px; margin-bottom:14px;">
        Used by the Security Unit to contact you directly when your property is matched or recovered.
      </p>

      <div class="form-row">
        <div class="field">
          <label>Full Name <span style="color:#d9534f;">*</span></label>
          <input type="text" name="full_name" class="prs-name-input" placeholder="e.g. Zainab Adeyemi" value="<?= htmlspecialchars($_POST['full_name'] ?? $default_name) ?>" required>
        </div>
        <div class="field">
          <label>Matric Number <span style="color:#d9534f;">*</span></label>
          <input type="text" name="id_number" class="prs-matric-input" placeholder="Enter 13-digit matric number" value="<?= htmlspecialchars($_POST['id_number'] ?? $default_id) ?>" required>
        </div>
      </div>

      <div class="field">
        <label>Phone Number (WhatsApp/Calls) <span style="color:#d9534f;">*</span></label>
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
        2. Lost Property Particulars
      </h3>

      <div class="field">
        <label>Item Name <span style="color:#d9534f;">*</span></label>
        <input type="text" name="item_name" placeholder="e.g. Samsung Galaxy A14 or Brown Leather Wallet" value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>" required>
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
        <label>Detailed Description <span style="color:#d9534f;">*</span></label>
        <textarea name="description" placeholder="Color, brand, distinguishing marks, scratches, stickers, or contents (for bags/wallets)" required rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="form-row">
        <div class="field">
          <label>Location Where Lost <span style="color:#d9534f;">*</span></label>
          <input type="text" name="location_lost" placeholder="e.g. ICT Center Lab 2, North Campus" value="<?= htmlspecialchars($_POST['location_lost'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Date Lost <span style="color:#d9534f;">*</span></label>
          <input type="date" name="date_lost" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($_POST['date_lost'] ?? '') ?>" required>
        </div>
      </div>

      <div style="margin-top:24px;">
        <button type="submit" class="btn btn-primary" style="padding:11px 22px;">Submit Lost Report</button>
      </div>
    </form>

  </div>
</div>
<?php include 'includes/footer.php'; ?>

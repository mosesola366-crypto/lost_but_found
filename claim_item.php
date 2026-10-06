<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$page_title = 'Claim Found Item | Property Reporting and Recovery System';
$asset_path = '';
$active = 'search';
$errors = [];
$success = flash('success');

$faculties = PRS_FACULTIES;

$itemId = (int)($_GET['item_id'] ?? $_POST['item_id'] ?? 0);
if ($itemId <= 0) {
    header('Location: search.php');
    exit;
}

// Fetch found item
$stmt = $pdo->prepare('SELECT * FROM found_items WHERE id = ?');
$stmt->execute([$itemId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item || $item['status'] !== 'pending') {
    flash('error', 'This item is no longer available for claims.');
    header('Location: search.php');
    exit;
}

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
    $proof_details = trim($_POST['proof_details'] ?? '');

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

    if ($proof_details === '') {
        $errors[] = 'Please provide detailed distinguishing marks or proof of ownership.';
    } elseif (strlen($proof_details) < 15) {
        $errors[] = 'Please provide more detail in your proof (at least 15 characters).';
    }

    if (empty($errors)) {
        try {
            $contact_phone = normalise_ng_phone($raw_phone);
            $userId = get_or_create_reporter($pdo, $id_number, $contact_phone, $full_name, $final_dept);

            // Check if this claimant already has a pending claim for this item
            $claimCheck = $pdo->prepare('SELECT id FROM claims WHERE found_item_id = ? AND user_id = ? AND status = "pending"');
            $claimCheck->execute([$itemId, $userId]);
            if ($claimCheck->fetch()) {
                $errors[] = 'You already have a pending claim submitted for this item. Please await Security Unit review.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO claims (found_item_id, user_id, proof_details, contact_phone, status)
                     VALUES (?, ?, ?, ?, "pending")'
                );
                $stmt->execute([$itemId, $userId, $proof_details, $contact_phone]);
                $claimId = (int)$pdo->lastInsertId();

                notify_user(
                    $pdo,
                    $userId,
                    'Your claim for "' . $item['item_name'] . '" has been submitted to the Security Unit for verification.',
                    'track.php?id_number=' . urlencode($id_number) . '&phone=' . urlencode($contact_phone)
                );

                log_audit_action($pdo, $userId, 'SUBMIT_CLAIM', 'claims', $claimId, "Identifier-based claim on found #{$itemId} by {$id_number}");

                flash('success', 'Your ownership claim has been submitted! The Security Unit will review your proof details and contact you.');
                header('Location: track.php?id_number=' . urlencode($id_number) . '&phone=' . urlencode($contact_phone));
                exit;
            }
        } catch (RuntimeException | InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        } catch (PDOException $e) {
            $errors[] = 'Database error submitting your claim. Please try again.';
        }
    }
}

include 'includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Submit Ownership Claim</h1>
    <p>Provide private, distinctive proof that this recovered property belongs to you. No password required.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <div class="grid-2" style="align-items:start;">
    <div class="card">
      <h2>Item Under Claim</h2>
      <div class="item-card" style="margin-bottom:0;">
        <div class="item-thumb">
          <?php if (!empty($item['image_path'])): ?>
            <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
          <?php else: ?>&#128230;<?php endif; ?>
        </div>
        <div style="flex:1;">
          <div class="title"><?= htmlspecialchars($item['item_name']) ?> <?= render_status_badge($item['status']) ?></div>
          <div class="meta" style="margin-top:4px;">
            <?= htmlspecialchars($item['category']) ?> &middot;
            Found at <?= htmlspecialchars($item['location_found']) ?> &middot;
            <?= date('d M Y', strtotime($item['date_found'])) ?>
          </div>
          <p style="margin:8px 0 0; font-size:13.5px;"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
        </div>
      </div>

      <div style="margin-top:20px; padding:14px; background:#f8f9fb; border-radius:8px; font-size:13px; color:var(--muted); line-height:1.5;">
        <strong>Security Verification Notice:</strong><br>
        To prevent fraudulent claims, the Security Unit will scrutinize distinguishing features not publicly disclosed (such as serial numbers, internal contents, scratches, or phone lock screens). False claims will be escalated to Student Affairs.
      </div>
    </div>

    <div class="card">
      <h2>Claimant Identification &amp; Proof</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">

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
          <label>Contact Phone <span style="color:#d9534f;">*</span></label>
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

        <div class="field">
          <label>Distinctive Proof / Concealed Details <span style="color:#d9534f;">*</span></label>
          <textarea name="proof_details" placeholder="Describe private details: exact contents, lock screen PIN/pattern, IMEI/Serial number, stickers, receipts, or marks not visible in public listing." required style="min-height:130px;"><?= htmlspecialchars($_POST['proof_details'] ?? '') ?></textarea>
        </div>

        <div style="display:flex; gap:10px; margin-top:20px;">
          <button type="submit" class="btn btn-gold" style="flex:1;">Submit Claim to Security</button>
          <a href="search.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

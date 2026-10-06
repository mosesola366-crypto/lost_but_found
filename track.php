<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

$page_title = 'Track Reports & Status | Property Reporting and Recovery System';
$asset_path = '';
$active = 'track';

$id_number = strtoupper(trim($_GET['id_number'] ?? $_POST['id_number'] ?? ''));
$phone     = trim($_GET['phone'] ?? $_POST['phone'] ?? '');

$searched  = ($id_number !== '' && $phone !== '');
$reporter  = null;
$error     = flash('error');
$success   = flash('success');

$lost_items    = [];
$found_items   = [];
$claims        = [];
$notifications = [];

$pending_count  = 0;
$matched_count  = 0;
$resolved_count = 0;

if ($searched) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE UPPER(id_number) = ? LIMIT 1');
    $stmt->execute([$id_number]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Verify phone against user record or against any of their reported items
        $phoneValid = phones_match($user['phone'], $phone);

        if (!$phoneValid) {
            // Check if phone matches any contact_phone in lost_items
            $lostPhoneCheck = $pdo->prepare('SELECT id, contact_phone FROM lost_items WHERE user_id = ?');
            $lostPhoneCheck->execute([(int)$user['id']]);
            $items = $lostPhoneCheck->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $item) {
                if (phones_match($item['contact_phone'], $phone)) {
                    $phoneValid = true;
                    break;
                }
            }
        }

        if ($phoneValid) {
            $reporter = $user;
            $uid = (int)$reporter['id'];

            // Fetch lost items with potential matches
            $lostStmt = $pdo->prepare(
                'SELECT l.*, m.id AS match_id, m.status AS match_status, m.match_date
                 FROM lost_items l
                 LEFT JOIN matches m ON m.lost_item_id = l.id
                 WHERE l.user_id = ?
                 ORDER BY l.created_at DESC'
            );
            $lostStmt->execute([$uid]);
            $lost_items = $lostStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch found items
            $foundStmt = $pdo->prepare(
                'SELECT f.*, m.id AS match_id, m.status AS match_status
                 FROM found_items f
                 LEFT JOIN matches m ON m.found_item_id = f.id
                 WHERE f.user_id = ?
                 ORDER BY f.created_at DESC'
            );
            $foundStmt->execute([$uid]);
            $found_items = $foundStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch claims
            try {
                $claimsStmt = $pdo->prepare(
                    'SELECT c.*, f.item_name, f.category, f.location_found, f.date_found, f.image_path
                     FROM claims c
                     JOIN found_items f ON f.id = c.found_item_id
                     WHERE c.user_id = ?
                     ORDER BY c.created_at DESC'
                );
                $claimsStmt->execute([$uid]);
                $claims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $claims = [];
            }

            // Fetch notifications
            $notifStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8');
            $notifStmt->execute([$uid]);
            $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate status aggregates
            $pending_count = count(array_filter($lost_items, fn($i) => $i['status'] === 'pending'))
                           + count(array_filter($found_items, fn($i) => $i['status'] === 'pending'))
                           + count(array_filter($claims, fn($c) => $c['status'] === 'pending'));

            $matched_count = count(array_filter($lost_items, fn($i) => $i['status'] === 'matched'))
                           + count(array_filter($found_items, fn($i) => $i['status'] === 'matched'));

            $resolved_count = count(array_filter($lost_items, fn($i) => $i['status'] === 'resolved'))
                            + count(array_filter($found_items, fn($i) => $i['status'] === 'claimed'))
                            + count(array_filter($claims, fn($c) => $c['status'] === 'approved'));
        } else {
            $error = 'The Phone Number provided does not match the records for Matric / Staff ID ' . htmlspecialchars($id_number) . '. Please check and try again.';
        }
    } else {
        $error = 'No reports or profile found for Matric / Staff ID "' . htmlspecialchars($id_number) . '". Have you filed a report yet?';
    }
}

include 'includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Check Report &amp; Recovery Status</h1>
    <p>Passwordless status tracking for campus students, staff, and visitors.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Lookup Bar -->
  <div class="card" style="margin-bottom:24px;">
    <h2 style="margin-top:0; font-size:16px;">Track Your Property Reports</h2>
    <p style="font-size:13.5px; color:var(--muted); margin-top:-4px; margin-bottom:16px;">
      Enter your Matriculation Number or Staff ID and the Phone Number you submitted with your report.
    </p>

    <form method="get" class="form-row" style="align-items:end;">
      <div class="field" style="margin-bottom:0;">
        <label>Matric Number</label>
        <input type="text" name="id_number" class="prs-matric-input" placeholder="Enter 13-digit matric number" value="<?= htmlspecialchars($id_number) ?>" required>
      </div>

      <div class="field" style="margin-bottom:0;">
        <label>Contact Phone Number</label>
        <input type="tel" name="phone" class="ng-phone-input" value="<?= htmlspecialchars($phone) ?>" required>
      </div>

      <div class="field" style="margin-bottom:0;">
        <button type="submit" class="btn btn-primary" style="padding:10px 20px;">Check Status</button>
      </div>
    </form>
  </div>

  <?php if ($reporter): ?>
    <!-- Reporter Header Banner -->
    <div class="card" style="background:#f8f9fb; border-left:4px solid var(--navy-dark); margin-bottom:24px; padding:16px 20px;">
      <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
          <h2 style="margin:0; font-size:18px; color:var(--navy-dark);"><?= htmlspecialchars($reporter['full_name']) ?></h2>
          <div style="color:var(--muted); font-size:13px; margin-top:3px;">
            <strong>ID:</strong> <?= htmlspecialchars($reporter['id_number']) ?> &middot;
            <strong>Phone:</strong> <?= htmlspecialchars($reporter['phone']) ?>
            <?php if (!empty($reporter['department'])): ?> &middot; <strong>Dept:</strong> <?= htmlspecialchars($reporter['department']) ?><?php endif; ?>
          </div>
        </div>
        <div style="display:flex; gap:8px;">
          <a href="report_lost.php?id_number=<?= urlencode($reporter['id_number']) ?>&phone=<?= urlencode($reporter['phone']) ?>" class="btn btn-sm btn-primary">+ Report Lost</a>
          <a href="report_found.php?id_number=<?= urlencode($reporter['id_number']) ?>&phone=<?= urlencode($reporter['phone']) ?>" class="btn btn-sm btn-gold">+ Report Found</a>
        </div>
      </div>
    </div>

    <!-- Aggregate Stats -->
    <div class="grid-3" style="margin-bottom:24px;">
      <div class="stat-card alt">
        <div class="num"><?= $pending_count ?></div>
        <div class="label">Pending Reports / Claims</div>
      </div>
      <div class="stat-card">
        <div class="num"><?= $matched_count ?></div>
        <div class="label">Matched Items</div>
      </div>
      <div class="stat-card success">
        <div class="num"><?= $resolved_count ?></div>
        <div class="label">Resolved / Recovered</div>
      </div>
    </div>

    <!-- Match Alert Notice if Matched Items Exist -->
    <?php if ($matched_count > 0): ?>
      <div class="alert alert-success" style="border-left:4px solid #1e8a5f; margin-bottom:24px; font-size:14px; line-height:1.5;">
        <strong>Action Required: Potential Match Identified!</strong><br>
        Campus Security has linked one or more of your reports to recovered property.
        Please visit the <strong>Security Unit Main Office (Senate Building Ground Floor)</strong> with your Student/Staff ID Card for physical verification and collection.
      </div>
    <?php endif; ?>

    <!-- Recent Security Notifications -->
    <?php if (!empty($notifications)): ?>
      <div class="card" style="margin-bottom:24px;">
        <h2 style="font-size:16px;">Security Unit Updates &amp; Alerts</h2>
        <?php foreach ($notifications as $n): ?>
          <div style="padding:10px 0; border-bottom:1px solid var(--border); font-size:13.5px; display:flex; justify-content:space-between; gap:10px;">
            <div>
              <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:var(--gold); margin-right:6px;"></span>
              <?= htmlspecialchars($n['message']) ?>
            </div>
            <span style="color:var(--muted); font-size:12px; white-space:nowrap;"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Lost Items Section -->
    <div class="card" style="margin-bottom:24px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h2 style="margin:0; font-size:17px;">Lost Item Reports (<?= count($lost_items) ?>)</h2>
        <a href="report_lost.php?id_number=<?= urlencode($reporter['id_number']) ?>&phone=<?= urlencode($reporter['phone']) ?>" class="btn btn-sm btn-outline">+ File New</a>
      </div>

      <?php if (empty($lost_items)): ?>
        <div class="empty-state">No lost item reports currently logged under this identifier.</div>
      <?php else: ?>
        <table>
          <tr>
            <th>Ref #</th>
            <th>Item Name</th>
            <th>Category</th>
            <th>Location Lost</th>
            <th>Date Lost</th>
            <th>Status</th>
            <th>Security Action</th>
          </tr>
          <?php foreach ($lost_items as $i): ?>
            <tr>
              <td><code style="font-weight:700; color:var(--navy-dark); font-size:12px; background:#eef2f7; padding:2px 6px; border-radius:4px;">PRS-L-<?= str_pad((string)$i['id'], 4, '0', STR_PAD_LEFT) ?></code></td>
              <td><strong><?= htmlspecialchars($i['item_name']) ?></strong></td>
              <td><?= htmlspecialchars($i['category']) ?></td>
              <td><?= htmlspecialchars($i['location_lost']) ?></td>
              <td><?= date('d M Y', strtotime($i['date_lost'])) ?></td>
              <td><?= render_status_badge($i['status']) ?></td>
              <td>
                <?php if ($i['status'] === 'matched'): ?>
                  <span style="color:#1e8a5f; font-weight:700; font-size:12.5px;">&#9873; Match Found! Visit Security</span>
                <?php elseif ($i['status'] === 'resolved'): ?>
                  <span style="color:var(--muted); font-size:12.5px;">Item Recovered</span>
                <?php else: ?>
                  <span style="color:var(--muted); font-size:12.5px;">Under Review</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <!-- Found Items Section -->
    <div class="card" style="margin-bottom:24px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
        <h2 style="margin:0; font-size:17px;">Found Item Reports Submitted by You (<?= count($found_items) ?>)</h2>
        <a href="report_found.php?id_number=<?= urlencode($reporter['id_number']) ?>&phone=<?= urlencode($reporter['phone']) ?>" class="btn btn-sm btn-outline">+ Report Found</a>
      </div>

      <?php if (empty($found_items)): ?>
        <div class="empty-state">You have not submitted any found item reports.</div>
      <?php else: ?>
        <table>
          <tr>
            <th>Item Name</th>
            <th>Category</th>
            <th>Location Found</th>
            <th>Date Found</th>
            <th>Photo</th>
            <th>Status</th>
          </tr>
          <?php foreach ($found_items as $f): ?>
            <tr>
              <td><strong><?= htmlspecialchars($f['item_name']) ?></strong></td>
              <td><?= htmlspecialchars($f['category']) ?></td>
              <td><?= htmlspecialchars($f['location_found']) ?></td>
              <td><?= date('d M Y', strtotime($f['date_found'])) ?></td>
              <td>
                <?php if (!empty($f['image_path'])): ?>
                  <a href="<?= htmlspecialchars($f['image_path']) ?>" target="_blank" style="font-size:12px; color:var(--navy-dark); text-decoration:underline;">View Photo</a>
                <?php else: ?>
                  <span style="color:var(--muted); font-size:12px;">None</span>
                <?php endif; ?>
              </td>
              <td><?= render_status_badge($f['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <!-- Ownership Claims Section -->
    <div class="card">
      <h2 style="font-size:17px; margin-bottom:14px;">Ownership Claims Filed on Recovered Items (<?= count($claims) ?>)</h2>

      <?php if (empty($claims)): ?>
        <div class="empty-state">No ownership claims filed. If you find your item in the public search, you can submit an ownership claim.</div>
      <?php else: ?>
        <table>
          <tr>
            <th>Found Item</th>
            <th>Category</th>
            <th>Submitted Proof</th>
            <th>Date Filed</th>
            <th>Status</th>
            <th>Security Remarks</th>
          </tr>
          <?php foreach ($claims as $c): ?>
            <tr>
              <td><strong><?= htmlspecialchars($c['item_name']) ?></strong></td>
              <td><?= htmlspecialchars($c['category']) ?></td>
              <td style="max-width:240px; font-size:12.5px;"><?= htmlspecialchars(substr($c['proof_details'], 0, 80)) . (strlen($c['proof_details']) > 80 ? '...' : '') ?></td>
              <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
              <td><?= render_status_badge($c['status']) ?></td>
              <td style="font-size:12px; color:var(--muted);">
                <?= !empty($c['admin_notes']) ? htmlspecialchars($c['admin_notes']) : 'Pending inspection' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

  <?php elseif (!$searched): ?>
    <!-- Getting Started Guide -->
    <div class="grid-2">
      <div class="card">
        <h2>How Passwordless Tracking Works</h2>
        <p style="font-size:13.5px; color:var(--text); line-height:1.6;">
          You no longer need to remember passwords or maintain a user account.
          Every report you file is securely linked to your <strong>Matriculation Number or Staff ID</strong> and <strong>Phone Number</strong>.
        </p>
        <ul style="font-size:13.5px; color:var(--muted); line-height:1.7; padding-left:18px;">
          <li>Submit a lost or found report anytime using your ID and Phone.</li>
          <li>Enter those same credentials above to view live matching updates.</li>
          <li>Receive alerts when the Security Unit confirms a match or authorizes a property recovery voucher.</li>
        </ul>
      </div>

      <div class="card">
        <h2>Need to file a report now?</h2>
        <p style="font-size:13.5px; color:var(--text); line-height:1.6;">
          Quickly file an item report directly with the Security Unit:
        </p>
        <div style="display:flex; flex-direction:column; gap:10px; margin-top:16px;">
          <a href="report_lost.php" class="btn btn-primary" style="text-align:center;">+ Report a Lost Item</a>
          <a href="report_found.php" class="btn btn-gold" style="text-align:center;">+ Report a Found Item</a>
          <a href="search.php" class="btn btn-outline" style="text-align:center;">Browse Recovered Items</a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>

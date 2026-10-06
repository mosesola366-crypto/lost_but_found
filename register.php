<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$page_title = 'Direct Reporting | Property Reporting and Recovery System';
$asset_path = '';
$active = 'register';

include 'includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-box" style="max-width:520px; text-align:center;">
    <div class="crest-lg" style="margin:0 auto 12px;">PI</div>
    <h1 style="font-size:22px; margin-bottom:8px;">No Registration Required!</h1>
    <div class="sub" style="margin-bottom:20px;">
      The Property Reporting and Recovery System now uses <strong>instant identifier-based reporting</strong>.
      You do not need to create an account, remember passwords, or log in.
    </div>

    <div style="background:#f8f9fb; border:1px solid var(--border); border-radius:10px; padding:20px; text-align:left; margin-bottom:22px;">
      <h3 style="margin-top:0; font-size:15px; color:var(--navy-dark);">How to use the system:</h3>
      <ul style="font-size:13.5px; color:var(--text); line-height:1.7; padding-left:18px; margin-bottom:0;">
        <li><strong>Lost an item?</strong> Submit your report directly using your Matric/Staff ID and phone number.</li>
        <li><strong>Found an item?</strong> Hand over details and upload a photo without needing an account.</li>
        <li><strong>Want to check status?</strong> Simply enter your Matric/Staff ID and phone on the tracker page.</li>
      </ul>
    </div>

    <div style="display:flex; flex-direction:column; gap:10px;">
      <a href="report_lost.php" class="btn btn-primary" style="padding:12px;">+ Report a Lost Item</a>
      <a href="report_found.php" class="btn btn-gold" style="padding:12px;">+ Report a Found Item</a>
      <a href="track.php" class="btn btn-outline" style="padding:12px;">Check Report Status</a>
    </div>

    <div class="auth-footer" style="margin-top:20px;">
      Security Unit Personnel? <a href="login.php">Admin Login</a>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>

<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_admin();

$matchId = (int)($_GET['match_id'] ?? 0);
$voucherNo = trim($_GET['voucher_no'] ?? '');

$voucher = null;

if ($matchId > 0) {
    $stmt = $pdo->prepare(
        'SELECT v.*, m.match_date, f.item_name, f.category, f.description, f.location_found, f.date_found,
                u.full_name AS officer_name, u.id_number AS officer_id
         FROM vouchers v
         JOIN matches m ON m.id = v.match_id
         JOIN found_items f ON f.id = v.found_item_id
         JOIN users u ON u.id = v.issued_by
         WHERE v.match_id = ?
         LIMIT 1'
    );
    $stmt->execute([$matchId]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($voucherNo !== '') {
    $stmt = $pdo->prepare(
        'SELECT v.*, m.match_date, f.item_name, f.category, f.description, f.location_found, f.date_found,
                u.full_name AS officer_name, u.id_number AS officer_id
         FROM vouchers v
         LEFT JOIN matches m ON m.id = v.match_id
         JOIN found_items f ON f.id = v.found_item_id
         JOIN users u ON u.id = v.issued_by
         WHERE v.voucher_no = ?
         LIMIT 1'
    );
    $stmt->execute([$voucherNo]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$voucher) {
    // If match was released before vouchers table was introduced, generate fallback record on the fly
    if ($matchId > 0) {
        $mStmt = $pdo->prepare(
            'SELECT m.*, l.item_name, l.category, l.description, l.location_lost,
                    lu.full_name AS claimant_name, lu.id_number AS claimant_id_number, lu.phone AS claimant_phone, lu.department AS claimant_department,
                    f.location_found, f.date_found,
                    au.full_name AS officer_name, au.id_number AS officer_id
             FROM matches m
             JOIN lost_items l ON l.id = m.lost_item_id
             JOIN users lu ON lu.id = l.user_id
             JOIN found_items f ON f.id = m.found_item_id
             JOIN users au ON au.id = m.matched_by
             WHERE m.id = ?'
        );
        $mStmt->execute([$matchId]);
        $fallback = $mStmt->fetch(PDO::FETCH_ASSOC);

        if ($fallback) {
            $voucher = [
                'voucher_no'          => 'PRS-VOUCH-' . date('Y') . '-' . str_pad((string)$matchId, 4, '0', STR_PAD_LEFT),
                'match_id'            => $matchId,
                'item_name'           => $fallback['item_name'],
                'category'            => $fallback['category'],
                'description'         => $fallback['description'],
                'location_found'      => $fallback['location_found'],
                'date_found'          => $fallback['date_found'],
                'claimant_name'       => $fallback['claimant_name'],
                'claimant_id_number'  => $fallback['claimant_id_number'],
                'claimant_phone'      => $fallback['claimant_phone'],
                'claimant_department' => $fallback['claimant_department'] ?? 'General',
                'officer_name'        => $fallback['officer_name'],
                'officer_id'          => $fallback['officer_id'],
                'release_date'        => $fallback['match_date'],
                'remarks'             => 'Handover verified and archived.',
            ];
        }
    }
}

if (!$voucher) {
    die('Voucher record not found. Please ensure the match is marked as "Released".');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Property Handover Voucher - <?= htmlspecialchars($voucher['voucher_no']) ?></title>
<style>
  body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #eef2f6;
    margin: 0;
    padding: 24px;
    color: #1a202c;
  }
  .voucher-page {
    max-width: 800px;
    margin: 0 auto;
    background: #fff;
    padding: 40px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border: 1px solid #cbd5e0;
  }
  .header {
    text-align: center;
    border-bottom: 3px double #0f2c4c;
    padding-bottom: 18px;
    margin-bottom: 24px;
  }
  .crest {
    width: 60px;
    height: 60px;
    background: #d9a441;
    color: #0f2c4c;
    font-size: 22px;
    font-weight: 800;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
  }
  h1 { margin: 0; font-size: 22px; text-transform: uppercase; color: #0f2c4c; letter-spacing: 0.05em; }
  h2 { margin: 4px 0 0; font-size: 15px; font-weight: 600; color: #4a5568; }
  h3 { margin: 6px 0 0; font-size: 14px; text-transform: uppercase; color: #d9a441; font-weight: 700; }

  .voucher-meta {
    display: flex;
    justify-content: space-between;
    background: #f7fafc;
    padding: 12px 16px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    margin-bottom: 22px;
    font-size: 14px;
  }
  .section-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    color: #0f2c4c;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 6px;
    margin: 20px 0 10px;
  }
  table.details {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
    margin-bottom: 14px;
  }
  table.details th, table.details td {
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    text-align: left;
  }
  table.details th { background: #edf2f7; width: 28%; color: #2d3748; }

  .declaration {
    background: #fffdf5;
    border: 1px solid #f6e05e;
    padding: 12px 14px;
    border-radius: 6px;
    font-size: 12.5px;
    line-height: 1.5;
    color: #744210;
    margin-top: 20px;
  }
  .signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 50px;
    padding-top: 20px;
  }
  .sig-block {
    width: 45%;
    text-align: center;
    border-top: 1px solid #4a5568;
    padding-top: 8px;
    font-size: 13px;
  }
  .sig-title { font-weight: 700; color: #0f2c4c; }

  .actions {
    max-width: 800px;
    margin: 16px auto 0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
  }
  .btn {
    padding: 10px 18px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
  }
  .btn-print { background: #0f2c4c; color: #fff; border: none; }
  .btn-back { background: #fff; color: #0f2c4c; border: 1px solid #0f2c4c; }

  @media print {
    body { background: #fff; padding: 0; }
    .voucher-page { border: none; box-shadow: none; padding: 20px 0; }
    .actions { display: none; }
  }
</style>
</head>
<body>

<div class="actions">
  <a href="matches.php" class="btn btn-back">&larr; Back to Matches</a>
  <button onclick="window.print()" class="btn btn-print">&#128438; Print Official Voucher</button>
</div>

<div class="voucher-page">
  <div class="header">
    <div class="crest">PI</div>
    <h1>The Polytechnic, Ibadan</h1>
    <h2>Security Unit &middot; Property Reporting &amp; Recovery Service</h2>
    <h3>Official Property Handover Certificate</h3>
  </div>

  <div class="voucher-meta">
    <div><strong>Voucher Ref:</strong> <?= htmlspecialchars($voucher['voucher_no']) ?></div>
    <div><strong>Date Issued:</strong> <?= date('d M Y, H:i', strtotime($voucher['release_date'])) ?></div>
  </div>

  <div class="section-title">1. Property Particulars</div>
  <table class="details">
    <tr><th>Item Name</th><td><strong><?= htmlspecialchars($voucher['item_name']) ?></strong></td></tr>
    <tr><th>Category</th><td><?= htmlspecialchars($voucher['category']) ?></td></tr>
    <tr><th>Description</th><td><?= nl2br(htmlspecialchars($voucher['description'])) ?></td></tr>
    <tr><th>Location Recovered</th><td><?= htmlspecialchars($voucher['location_found']) ?></td></tr>
    <tr><th>Date Recovered</th><td><?= date('d F Y', strtotime($voucher['date_found'])) ?></td></tr>
  </table>

  <div class="section-title">2. Claimant &amp; Owner Details</div>
  <table class="details">
    <tr><th>Claimant Name</th><td><strong><?= htmlspecialchars($voucher['claimant_name']) ?></strong></td></tr>
    <tr><th>Matric / Staff ID</th><td><?= htmlspecialchars($voucher['claimant_id_number']) ?></td></tr>
    <tr><th>Department / Unit</th><td><?= htmlspecialchars($voucher['claimant_department'] ?? 'General') ?></td></tr>
    <tr><th>Phone Number</th><td><?= htmlspecialchars($voucher['claimant_phone']) ?></td></tr>
  </table>

  <div class="section-title">3. Issuing Authority Details</div>
  <table class="details">
    <tr><th>Issuing Security Officer</th><td><?= htmlspecialchars($voucher['officer_name']) ?> (<?= htmlspecialchars($voucher['officer_id']) ?>)</td></tr>
    <tr><th>Verification Remarks</th><td><?= htmlspecialchars($voucher['remarks'] ?? 'Verified by physical inspection and proof.') ?></td></tr>
  </table>

  <div class="declaration">
    <strong>Declaration:</strong> I, the undersigned claimant, solemnly affirm that I am the bona fide owner of the property specified above. I acknowledge receipt of the property in satisfactory condition and release the Security Unit and The Polytechnic, Ibadan from further custody obligations.
  </div>

  <div class="signatures">
    <div class="sig-block">
      <div class="sig-title">Claimant / Owner Signature</div>
      <div style="font-size:12px; color:#718096; margin-top:4px;">Date: ________________________</div>
    </div>
    <div class="sig-block">
      <div class="sig-title">Security Officer Signature &amp; Stamp</div>
      <div style="font-size:12px; color:#718096; margin-top:4px;">Date: ________________________</div>
    </div>
  </div>
</div>

</body>
</html>

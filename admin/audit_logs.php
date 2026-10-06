<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/pagination.php';
require_admin();

$page_title = 'Security Audit Logs | Property Reporting and Recovery System';
$asset_path = '../';
$active = 'audit_logs';

$actionFilter = trim($_GET['action'] ?? '');
$search = trim($_GET['q'] ?? '');

$countSql = 'SELECT COUNT(*) FROM audit_logs a JOIN users u ON u.id = a.user_id WHERE 1=1';
$selectSql = 'SELECT a.*, u.full_name AS actor_name, u.role AS actor_role, u.id_number AS actor_id_number
              FROM audit_logs a
              JOIN users u ON u.id = a.user_id
              WHERE 1=1';
$params = [];

if ($actionFilter !== '') {
    $clause = ' AND a.action = ?';
    $countSql .= $clause;
    $selectSql .= $clause;
    $params[] = $actionFilter;
}

if ($search !== '') {
    $clause = ' AND (a.details LIKE ? OR u.full_name LIKE ? OR a.ip_address LIKE ?)';
    $countSql .= $clause;
    $selectSql .= $clause;
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$selectSql .= ' ORDER BY a.created_at DESC';

$pagination = paginate($pdo, $countSql, $selectSql, $params, 15, 'page');
$logs = $pagination['items'];

$actionsList = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC')->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>
<div class="page">
  <div class="page-header">
    <h1>Security Audit Trail</h1>
    <p>Immutable record of all administrative actions, status changes, and verifications.</p>
  </div>

  <div class="card">
    <form method="get" class="form-row" style="align-items:end; margin-bottom:18px;">
      <div class="field" style="margin-bottom:0;">
        <label>Filter by Action</label>
        <select name="action">
          <option value="">All Actions</option>
          <?php foreach ($actionsList as $act): ?>
            <option value="<?= htmlspecialchars($act) ?>" <?= $actionFilter === $act ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:0;">
        <label>Keyword Search</label>
        <input type="text" name="q" placeholder="Details, actor name, or IP address" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="field" style="margin-bottom:0;">
        <button type="submit" class="btn btn-primary">Filter Logs</button>
      </div>
    </form>

    <?php if (empty($logs)): ?>
      <div class="empty-state">No audit log entries recorded yet.</div>
    <?php else: ?>
    <table>
      <tr><th>Timestamp</th><th>Action</th><th>Actor</th><th>Entity</th><th>Details</th><th>IP Address</th></tr>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td style="white-space:nowrap; font-size:12.5px;"><?= date('d M Y, H:i:s', strtotime($l['created_at'])) ?></td>
          <td><span class="badge badge-matched" style="font-size:11px;"><?= htmlspecialchars($l['action']) ?></span></td>
          <td>
            <strong><?= htmlspecialchars($l['actor_name']) ?></strong><br>
            <span style="font-size:11.5px; color:var(--muted);"><?= htmlspecialchars($l['actor_id_number']) ?> (<?= htmlspecialchars($l['actor_role']) ?>)</span>
          </td>
          <td style="font-size:12.5px; color:var(--muted);"><?= htmlspecialchars($l['entity_type']) ?> #<?= (int)$l['entity_id'] ?></td>
          <td style="font-size:13px; max-width:280px;"><?= htmlspecialchars($l['details'] ?? 'N/A') ?></td>
          <td style="font-size:12px; color:var(--muted); font-family:monospace;"><?= htmlspecialchars($l['ip_address']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>

    <?= render_pagination($pagination['current_page'], $pagination['total_pages'], ['action', 'q']) ?>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

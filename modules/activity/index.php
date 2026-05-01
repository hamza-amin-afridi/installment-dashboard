<?php
/**
 * Activity Log Viewer
 * Shows a filterable, paginated log of all admin actions.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pdo = getDB();
$pageTitle  = 'Activity Log';
$activePage = 'activity';

// --- CSV Export ---
if (isset($_GET['export'])) {
    $rows = $pdo->query("
        SELECT al.created_at, al.user_name, al.action, al.target_type, al.target_id, al.ip_address
        FROM activity_logs al
        ORDER BY al.created_at DESC
    ")->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="activity_log_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Timestamp', 'User', 'Action', 'Target Type', 'Target ID', 'IP Address']);
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

// --- Filters ---
$search      = trim($_GET['q'] ?? '');
$filterUser  = trim($_GET['user'] ?? '');
$filterType  = trim($_GET['type'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 30;

$where   = [];
$params  = [];

if ($search) {
    $where[]  = "(al.action LIKE ? OR al.user_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterUser) {
    $where[]  = "al.user_name = ?";
    $params[] = $filterUser;
}
if ($filterType) {
    $where[]  = "al.target_type = ?";
    $params[] = $filterType;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al $whereSQL");
$total->execute($params);
$pagInfo = paginate((int)$total->fetchColumn(), $perPage, $page);

$stmt = $pdo->prepare("
    SELECT al.*
    FROM activity_logs al
    $whereSQL
    ORDER BY al.created_at DESC
    LIMIT $perPage OFFSET {$pagInfo['offset']}
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// For filter dropdowns
$users = $pdo->query("SELECT DISTINCT user_name FROM activity_logs ORDER BY user_name")->fetchAll(PDO::FETCH_COLUMN);
$types = $pdo->query("SELECT DISTINCT target_type FROM activity_logs WHERE target_type IS NOT NULL ORDER BY target_type")->fetchAll(PDO::FETCH_COLUMN);

// Recent activity summary
$todayCount  = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$weekCount   = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
$totalCount  = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">📝 Activity Log</h1>
    <p class="page-subtitle">Complete audit trail of all admin actions</p>
  </div>
  <a href="?export=1" class="btn btn-outline">⬇️ Export CSV</a>
</div>

<!-- Summary -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card accent">
    <div class="stat-icon">📅</div>
    <div class="stat-info">
      <div class="stat-value"><?= $todayCount ?></div>
      <div class="stat-label">Actions Today</div>
    </div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon">📆</div>
    <div class="stat-info">
      <div class="stat-value"><?= $weekCount ?></div>
      <div class="stat-label">Actions This Week</div>
    </div>
  </div>
  <div class="stat-card warning">
    <div class="stat-icon">📝</div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($totalCount) ?></div>
      <div class="stat-label">Total Log Entries</div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="filter-bar" style="margin-bottom:20px">
  <div class="search-input-wrap" style="flex:2">
    <span class="search-icon">🔍</span>
    <input type="text" id="logSearch" placeholder="Search actions or users…"
           value="<?= e($search) ?>"
           onchange="applyFilter()">
  </div>
  <select id="filterUser" onchange="applyFilter()" style="min-width:160px">
    <option value="">All Users</option>
    <?php foreach ($users as $u): ?>
    <option value="<?= e($u) ?>" <?= $filterUser === $u ? 'selected' : '' ?>><?= e($u) ?></option>
    <?php endforeach; ?>
  </select>
  <select id="filterType" onchange="applyFilter()" style="min-width:160px">
    <option value="">All Types</option>
    <?php foreach ($types as $t): ?>
    <option value="<?= e($t) ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= ucfirst(e($t)) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($search || $filterUser || $filterType): ?>
  <a href="?" class="btn btn-outline btn-sm">✕ Clear</a>
  <?php endif; ?>
</div>

<!-- Log Table -->
<div class="card">
  <?php if (empty($logs)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">📝</div>
      <h3>No activity logs found</h3>
      <p>Actions will appear here as you use the system.</p>
    </div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Timestamp</th>
          <th>User</th>
          <th>Action</th>
          <th>Target</th>
          <th>IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $i => $log):
          // Detect action category for color coding
          $actionLower = strtolower($log['action']);
          $actionClass = '';
          if (str_contains($actionLower, 'login'))   $actionClass = 'color:var(--success)';
          elseif (str_contains($actionLower, 'logout'))  $actionClass = 'color:var(--text-muted)';
          elseif (str_contains($actionLower, 'delete'))  $actionClass = 'color:var(--danger)';
          elseif (str_contains($actionLower, 'creat') || str_contains($actionLower, 'added'))  $actionClass = 'color:var(--accent)';
          elseif (str_contains($actionLower, 'payment') || str_contains($actionLower, 'record')) $actionClass = 'color:var(--success)';
          elseif (str_contains($actionLower, 'update') || str_contains($actionLower, 'edit'))   $actionClass = 'color:var(--warning)';
        ?>
        <tr>
          <td style="color:var(--text-faint)"><?= $pagInfo['offset'] + $i + 1 ?></td>
          <td>
            <div style="font-size:13px"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
            <div style="font-size:11px;color:var(--text-faint)"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:28px;height:28px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0">
                <?= strtoupper(substr($log['user_name'], 0, 1)) ?>
              </div>
              <span style="font-weight:600"><?= e($log['user_name']) ?></span>
            </div>
          </td>
          <td style="<?= $actionClass ?>">
            <strong><?= e($log['action']) ?></strong>
          </td>
          <td>
            <?php if ($log['target_type']): ?>
            <span class="badge badge-muted"><?= e($log['target_type']) ?></span>
            <?php if ($log['target_id']): ?>
            <span style="font-size:12px;color:var(--text-faint)">&nbsp;#<?= $log['target_id'] ?></span>
            <?php endif; ?>
            <?php else: ?>
            <span style="color:var(--text-faint)">—</span>
            <?php endif; ?>
          </td>
          <td>
            <code style="font-size:11px;color:var(--text-faint)"><?= e($log['ip_address'] ?? '—') ?></code>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
    $baseUrl = '?q=' . urlencode($search) . '&user=' . urlencode($filterUser) . '&type=' . urlencode($filterType);
    echo renderPagination($pagInfo, $baseUrl);
  ?>
  <?php endif; ?>
</div>

<script>
function applyFilter() {
  const q    = document.getElementById('logSearch').value;
  const user = document.getElementById('filterUser').value;
  const type = document.getElementById('filterType').value;
  window.location.href = '?q=' + encodeURIComponent(q) + '&user=' + encodeURIComponent(user) + '&type=' + encodeURIComponent(type);
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

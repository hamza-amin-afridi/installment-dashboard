<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pdo = getDB();
$pageTitle = 'Customers'; $activePage = 'customers';

$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$whereClauses = [];
$params = [];

if ($search) {
    $whereClauses[] = "(c.name LIKE ? OR c.cnic LIKE ? OR c.phone LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
if ($filter === 'overdue') {
    $whereClauses[] = "EXISTS (SELECT 1 FROM leases l WHERE l.customer_id=c.id AND l.status='active' AND l.next_due_date < CURDATE())";
}
if (in_array($filter, ['good','average','risky'])) {
    $whereClauses[] = "c.score = ?";
    $params[] = $filter;
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM customers c $whereSQL");
$totalStmt->execute($params);
$pagInfo = paginate((int)$totalStmt->fetchColumn(), $perPage, $page);

$stmt = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM leases l WHERE l.customer_id=c.id AND l.status='active') AS active_leases,
           (SELECT COALESCE(SUM(l.total_amount),0) FROM leases l WHERE l.customer_id=c.id) AS total_leased,
           (SELECT COALESCE(SUM(l.paid_amount),0) FROM leases l WHERE l.customer_id=c.id) AS total_paid
    FROM customers c
    $whereSQL
    ORDER BY c.created_at DESC
    LIMIT $perPage OFFSET {$pagInfo['offset']}
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">👥 Customers</h1><p class="page-subtitle">Manage all lease customers</p></div>
  <a href="<?= BASE_URL ?>/modules/customers/add.php" class="btn btn-primary">+ Add Customer</a>
</div>

<div class="filter-bar">
  <div class="search-input-wrap" style="flex:1;min-width:220px">
    <span class="search-icon">🔍</span>
    <input type="text" placeholder="Search name, CNIC, phone…" value="<?= e($search) ?>"
           onchange="window.location.href='?q='+encodeURIComponent(this.value)+'&filter=<?= urlencode($filter) ?>'">
  </div>
  <a href="?" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline' ?>">All</a>
  <a href="?filter=overdue&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter==='overdue' ? 'btn-danger' : 'btn-outline' ?>">🔴 Overdue</a>
  <a href="?filter=good&q=<?= urlencode($search) ?>"    class="btn btn-sm <?= $filter==='good'    ? 'btn-success' : 'btn-outline' ?>">✅ Good</a>
  <a href="?filter=average&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter==='average' ? 'btn-warning' : 'btn-outline' ?>">⚠️ Average</a>
  <a href="?filter=risky&q=<?= urlencode($search) ?>"   class="btn btn-sm <?= $filter==='risky'   ? 'btn-danger'  : 'btn-outline' ?>">❌ Risky</a>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>#</th><th>Name</th><th>CNIC</th><th>Phone</th><th>Score</th><th>Active Leases</th><th>Total Leased</th><th>Balance</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($customers)): ?>
          <tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon">👥</div><h3>No customers found</h3></div></td></tr>
        <?php else: foreach ($customers as $i => $c):
          $balance = $c['total_leased'] - $c['total_paid'];
        ?>
        <tr>
          <td><?= $pagInfo['offset'] + $i + 1 ?></td>
          <td><a href="<?= BASE_URL ?>/modules/customers/view.php?id=<?= $c['id'] ?>" style="color:var(--accent);font-weight:600;text-decoration:none"><?= e($c['name']) ?></a></td>
          <td><code><?= e($c['cnic']) ?></code></td>
          <td><?= e($c['phone']) ?></td>
          <td>
            <span class="badge score-<?= $c['score'] ?>"><?= ucfirst($c['score']) ?> (<?= $c['score_value'] ?>)</span>
          </td>
          <td><?= $c['active_leases'] ?: '—' ?></td>
          <td><?= formatCurrency($c['total_leased']) ?></td>
          <td>
            <?php if ($balance > 0): ?>
              <strong style="color:var(--warning)"><?= formatCurrency($balance) ?></strong>
            <?php else: ?>
              <span style="color:var(--success)">Paid ✅</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap">
            <a href="<?= BASE_URL ?>/modules/customers/view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">👁️</a>
            <a href="<?= BASE_URL ?>/modules/customers/edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">✏️</a>
            <a href="<?= BASE_URL ?>/modules/customers/delete.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger"
               data-confirm="Delete customer '<?= e($c['name']) ?>'? All related data will be removed.">🗑️</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($pagInfo, '?q=' . urlencode($search) . '&filter=' . urlencode($filter)) ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

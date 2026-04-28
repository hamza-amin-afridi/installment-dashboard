<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$pdo        = getDB();
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// ===== Stats =====
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$activeLeases   = $pdo->query("SELECT COUNT(*) FROM leases WHERE status='active'")->fetchColumn();
$overdueLeases  = $pdo->query("SELECT COUNT(*) FROM leases WHERE status='active' AND next_due_date < CURDATE()")->fetchColumn();
$totalRevenue   = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();

// ===== Monthly Income (last 6 months) =====
$incomeStmt = $pdo->query("
    SELECT DATE_FORMAT(payment_date,'%b %Y') AS month,
           MONTH(payment_date) AS m,
           YEAR(payment_date)  AS y,
           SUM(amount) AS total
    FROM payments
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY y, m
    ORDER BY y, m
");
$incomeData = $incomeStmt->fetchAll();

// ===== Customer Growth (last 6 months) =====
$growthStmt = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') AS month,
           COUNT(*) AS cnt
    FROM customers
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
");
$growthData = $growthStmt->fetchAll();

// ===== Overdue Customers =====
$overdueList = $pdo->query("
    SELECT c.name, c.phone, c.cnic,
           l.next_due_date, l.total_amount, l.paid_amount, l.id AS lease_id,
           DATEDIFF(CURDATE(), l.next_due_date) AS days_overdue,
           m.name AS moto_name
    FROM leases l
    JOIN customers c  ON l.customer_id  = c.id
    JOIN motorcycles m ON l.motorcycle_id = m.id
    WHERE l.status='active' AND l.next_due_date < CURDATE()
    ORDER BY days_overdue DESC
    LIMIT 10
")->fetchAll();

// ===== Recent Payments =====
$recentPayments = $pdo->query("
    SELECT p.amount, p.payment_date, p.receipt_no,
           c.name AS customer_name, m.name AS moto_name
    FROM payments p
    JOIN leases l    ON p.lease_id = l.id
    JOIN customers c ON l.customer_id = c.id
    JOIN motorcycles m ON l.motorcycle_id = m.id
    ORDER BY p.created_at DESC LIMIT 8
")->fetchAll();

// ===== Plan distribution =====
$planDist = $pdo->query("
    SELECT lp.name, COUNT(l.id) AS cnt
    FROM leases l
    JOIN leasing_plans lp ON l.plan_id = lp.id
    GROUP BY lp.id, lp.name
")->fetchAll();

$extraJs = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>'
         . '<script src="' . BASE_URL . '/assets/js/charts.js"></script>';

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">📊 Dashboard</h1>
    <p class="page-subtitle">Welcome back, <?= e(currentUser()['name']) ?>! Here's your business overview.</p>
  </div>
  <span class="badge badge-accent">Live Data</span>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="stats-grid">
  <div class="stat-card accent">
    <div class="stat-icon">👥</div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($totalCustomers) ?></div>
      <div class="stat-label">Total Customers</div>
    </div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon">📄</div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($activeLeases) ?></div>
      <div class="stat-label">Active Leases</div>
    </div>
  </div>
  <div class="stat-card danger">
    <div class="stat-icon">⚠️</div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($overdueLeases) ?></div>
      <div class="stat-label">Overdue Payments</div>
    </div>
  </div>
  <div class="stat-card warning">
    <div class="stat-icon">💰</div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:20px"><?= formatCurrency((float)$totalRevenue) ?></div>
      <div class="stat-label">Total Revenue</div>
    </div>
  </div>
</div>

<!-- ===== CHARTS ===== -->
<div class="charts-grid" style="margin-bottom:28px">
  <div class="chart-card">
    <div class="card-header">
      <div class="card-title">📈 Monthly Income</div>
      <span class="badge badge-success">Last 6 months</span>
    </div>
    <canvas id="incomeChart"></canvas>
  </div>
  <div class="chart-card">
    <div class="card-header">
      <div class="card-title">👥 Customer Growth</div>
      <span class="badge badge-accent">Last 6 months</span>
    </div>
    <canvas id="growthChart"></canvas>
  </div>
  <div class="chart-card">
    <div class="card-header">
      <div class="card-title">🥧 Plan Distribution</div>
    </div>
    <canvas id="planChart"></canvas>
  </div>
</div>

<!-- ===== OVERDUE TABLE ===== -->
<?php if (!empty($overdueList)): ?>
<div class="card section-gap">
  <div class="card-header">
    <div>
      <div class="card-title">🔴 Overdue Customers</div>
      <div class="card-subtitle">Customers with missed installment payments</div>
    </div>
    <a href="<?= BASE_URL ?>/modules/leases/index.php?filter=overdue" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Customer</th><th>CNIC</th><th>Phone</th><th>Motorcycle</th>
          <th>Due Date</th><th>Days Overdue</th><th>Remaining</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($overdueList as $row): ?>
        <tr class="overdue-row">
          <td><strong><?= e($row['name']) ?></strong></td>
          <td><?= e($row['cnic']) ?></td>
          <td><?= e($row['phone']) ?></td>
          <td><?= e($row['moto_name']) ?></td>
          <td><?= date('d M Y', strtotime($row['next_due_date'])) ?></td>
          <td><span class="badge badge-danger"><?= $row['days_overdue'] ?> days</span></td>
          <td><?= formatCurrency(remainingBalance($row['total_amount'], $row['paid_amount'])) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/leases/view.php?id=<?= $row['lease_id'] ?>" class="btn btn-sm btn-outline">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ===== RECENT PAYMENTS ===== -->
<div class="card">
  <div class="card-header">
    <div class="card-title">💳 Recent Payments</div>
    <a href="<?= BASE_URL ?>/modules/payments/index.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <?php if (empty($recentPayments)): ?>
    <div class="empty-state"><div class="empty-state-icon">💳</div><h3>No payments yet</h3></div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Receipt</th><th>Customer</th><th>Motorcycle</th><th>Amount</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentPayments as $p): ?>
        <tr>
          <td><code><?= e($p['receipt_no']) ?></code></td>
          <td><?= e($p['customer_name']) ?></td>
          <td><?= e($p['moto_name']) ?></td>
          <td><strong style="color:var(--success)"><?= formatCurrency($p['amount']) ?></strong></td>
          <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Pass chart data to JS -->
<script>
window.CHART_DATA = {
  income: {
    labels: <?= json_encode(array_column($incomeData, 'month')) ?>,
    values: <?= json_encode(array_column($incomeData, 'total')) ?>
  },
  growth: {
    labels: <?= json_encode(array_column($growthData, 'month')) ?>,
    values: <?= json_encode(array_column($growthData, 'cnt')) ?>
  },
  plans: {
    labels: <?= json_encode(array_column($planDist, 'name')) ?>,
    values: <?= json_encode(array_column($planDist, 'cnt')) ?>
  }
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

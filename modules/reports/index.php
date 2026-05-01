<?php
/**
 * Reports & Analytics
 * Summary stats, monthly revenue, overdue analysis, and CSV export.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pdo = getDB();
$pageTitle  = 'Reports';
$activePage = 'reports';

// --- CSV Export ---
if (isset($_GET['export'])) {
    $type = $_GET['export'];

    if ($type === 'payments') {
        $rows = $pdo->query("
            SELECT p.receipt_no, c.name AS customer, c.cnic, m.name AS motorcycle,
                   p.amount, p.payment_date, p.notes
            FROM payments p
            JOIN leases l    ON p.lease_id = l.id
            JOIN customers c ON l.customer_id = c.id
            JOIN motorcycles m ON l.motorcycle_id = m.id
            ORDER BY p.payment_date DESC
        ")->fetchAll();
        $filename = 'payments_' . date('Y-m-d') . '.csv';
        $headers  = ['Receipt No', 'Customer', 'CNIC', 'Motorcycle', 'Amount (PKR)', 'Date', 'Notes'];

    } elseif ($type === 'leases') {
        $rows = $pdo->query("
            SELECT l.id, c.name AS customer, c.cnic, c.phone,
                   m.name AS motorcycle, lp.name AS plan, lp.duration_months,
                   l.total_amount, l.paid_amount,
                   (l.total_amount - l.paid_amount) AS balance,
                   l.monthly_install, l.start_date, l.end_date, l.next_due_date, l.status
            FROM leases l
            JOIN customers c    ON l.customer_id = c.id
            JOIN motorcycles m  ON l.motorcycle_id = m.id
            JOIN leasing_plans lp ON l.plan_id = lp.id
            ORDER BY l.created_at DESC
        ")->fetchAll();
        $filename = 'leases_' . date('Y-m-d') . '.csv';
        $headers  = ['#', 'Customer', 'CNIC', 'Phone', 'Motorcycle', 'Plan', 'Months',
                     'Total (PKR)', 'Paid (PKR)', 'Balance (PKR)', 'Monthly (PKR)',
                     'Start', 'End', 'Next Due', 'Status'];

    } elseif ($type === 'customers') {
        $rows = $pdo->query("
            SELECT c.name, c.cnic, c.phone, c.address, c.score, c.score_value, c.created_at,
                   COUNT(l.id) AS total_leases,
                   COALESCE(SUM(l.paid_amount),0) AS total_paid
            FROM customers c
            LEFT JOIN leases l ON l.customer_id = c.id
            GROUP BY c.id
            ORDER BY c.name
        ")->fetchAll();
        $filename = 'customers_' . date('Y-m-d') . '.csv';
        $headers  = ['Name', 'CNIC', 'Phone', 'Address', 'Score', 'Score Value', 'Joined',
                     'Total Leases', 'Total Paid (PKR)'];

    } else {
        setFlash('error', 'Invalid export type.');
        header('Location: ' . BASE_URL . '/modules/reports/index.php');
        exit;
    }

    // Send CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

// ===== Summary Stats =====
$totalRevenue    = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$totalLeases     = (int)$pdo->query("SELECT COUNT(*) FROM leases")->fetchColumn();
$activeLeases    = (int)$pdo->query("SELECT COUNT(*) FROM leases WHERE status='active'")->fetchColumn();
$completedLeases = (int)$pdo->query("SELECT COUNT(*) FROM leases WHERE status='completed'")->fetchColumn();
$defaultedLeases = (int)$pdo->query("SELECT COUNT(*) FROM leases WHERE status='defaulted'")->fetchColumn();
$overdueLeases   = (int)$pdo->query("SELECT COUNT(*) FROM leases WHERE status='active' AND next_due_date < CURDATE()")->fetchColumn();
$totalCustomers  = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$riskyCustomers  = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE score='risky'")->fetchColumn();
$totalOutstanding = (float)$pdo->query("SELECT COALESCE(SUM(total_amount - paid_amount),0) FROM leases WHERE status='active'")->fetchColumn();
$totalMotorcycles = (int)$pdo->query("SELECT COUNT(*) FROM motorcycles")->fetchColumn();
$leasedMoto      = (int)$pdo->query("SELECT COUNT(*) FROM motorcycles WHERE status='leased'")->fetchColumn();

// ===== Monthly Revenue (last 12 months) =====
$monthlyRev = $pdo->query("
    SELECT DATE_FORMAT(payment_date,'%b %Y') AS month,
           MONTH(payment_date) AS m, YEAR(payment_date) AS y,
           SUM(amount) AS total, COUNT(*) AS cnt
    FROM payments
    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY y, m
    ORDER BY y, m
")->fetchAll();

// ===== Top Customers by payment =====
$topCustomers = $pdo->query("
    SELECT c.name, c.cnic, c.score, c.score_value,
           COUNT(p.id) AS payments,
           COALESCE(SUM(p.amount),0) AS total_paid
    FROM customers c
    LEFT JOIN leases l ON l.customer_id = c.id
    LEFT JOIN payments p ON p.lease_id = l.id
    GROUP BY c.id
    ORDER BY total_paid DESC
    LIMIT 10
")->fetchAll();

// ===== Overdue Summary =====
$overdueData = $pdo->query("
    SELECT c.name, c.phone, c.cnic, c.score,
           l.id AS lease_id, l.next_due_date,
           l.total_amount, l.paid_amount,
           (l.total_amount - l.paid_amount) AS balance,
           DATEDIFF(CURDATE(), l.next_due_date) AS days_overdue,
           m.name AS moto_name
    FROM leases l
    JOIN customers c  ON l.customer_id = c.id
    JOIN motorcycles m ON l.motorcycle_id = m.id
    WHERE l.status='active' AND l.next_due_date < CURDATE()
    ORDER BY days_overdue DESC
")->fetchAll();

// ===== Motorcycle Utilization =====
$motoUtil = $pdo->query("
    SELECT m.name, m.model, m.status, m.price,
           COUNT(l.id) AS total_leases,
           COALESCE(SUM(p.amount), 0) AS revenue
    FROM motorcycles m
    LEFT JOIN leases l ON l.motorcycle_id = m.id
    LEFT JOIN payments p ON p.lease_id = l.id
    GROUP BY m.id
    ORDER BY revenue DESC
    LIMIT 10
")->fetchAll();

$extraJs = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>'
         . '<script>
document.addEventListener("DOMContentLoaded",function(){
  const isDark = () => document.documentElement.getAttribute("data-theme") !== "light";
  const gc = () => isDark() ? "rgba(46,51,83,0.8)" : "rgba(200,205,230,0.8)";
  const tc = () => isDark() ? "#8892b0" : "#5a6480";
  Chart.defaults.font.family = "Poppins,sans-serif";

  // Monthly Revenue
  const mRevCtx = document.getElementById("monthlyRevChart");
  if(mRevCtx){
    new Chart(mRevCtx,{
      type:"bar",
      data:{
        labels:' . json_encode(array_column($monthlyRev, 'month')) . ',
        datasets:[{
          label:"Revenue (₨)",
          data:' . json_encode(array_column($monthlyRev, 'total')) . ',
          backgroundColor:"rgba(108,99,255,0.75)",
          borderColor:"#6c63ff",
          borderWidth:2,borderRadius:6
        },{
          label:"Payments",
          data:' . json_encode(array_column($monthlyRev, 'cnt')) . ',
          backgroundColor:"rgba(46,213,115,0.75)",
          borderColor:"#2ed573",
          borderWidth:2,borderRadius:6,
          yAxisID:"y2"
        }]
      },
      options:{
        responsive:true,maintainAspectRatio:true,
        plugins:{legend:{labels:{color:tc()}}},
        scales:{
          x:{grid:{color:gc()},ticks:{color:tc()}},
          y:{grid:{color:gc()},ticks:{color:tc(),callback:v=>"₨"+v.toLocaleString()},position:"left"},
          y2:{grid:{display:false},ticks:{color:tc(),stepSize:1},position:"right"}
        }
      }
    });
  }

  // Lease Status Doughnut
  const lsCtx = document.getElementById("leaseStatusChart");
  if(lsCtx){
    new Chart(lsCtx,{
      type:"doughnut",
      data:{
        labels:["Active","Completed","Defaulted"],
        datasets:[{
          data:[' . $activeLeases . ',' . $completedLeases . ',' . $defaultedLeases . '],
          backgroundColor:["rgba(108,99,255,0.8)","rgba(46,213,115,0.8)","rgba(255,71,87,0.8)"],
          borderColor:isDark()?"#21253a":"#fff",
          borderWidth:3
        }]
      },
      options:{
        cutout:"70%",
        plugins:{legend:{position:"bottom",labels:{color:tc(),padding:16}}}
      }
    });
  }
});
</script>';

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">📈 Reports & Analytics</h1>
    <p class="page-subtitle">Business intelligence and financial overview</p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="?export=payments" class="btn btn-outline btn-sm">⬇️ Export Payments</a>
    <a href="?export=leases"   class="btn btn-outline btn-sm">⬇️ Export Leases</a>
    <a href="?export=customers" class="btn btn-outline btn-sm">⬇️ Export Customers</a>
  </div>
</div>

<!-- ===== KPI CARDS ===== -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:28px">
  <div class="stat-card success">
    <div class="stat-icon">💰</div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:18px"><?= formatCurrency($totalRevenue) ?></div>
      <div class="stat-label">Total Revenue Collected</div>
    </div>
  </div>
  <div class="stat-card warning">
    <div class="stat-icon">⏳</div>
    <div class="stat-info">
      <div class="stat-value" style="font-size:18px"><?= formatCurrency($totalOutstanding) ?></div>
      <div class="stat-label">Outstanding Balance</div>
    </div>
  </div>
  <div class="stat-card accent">
    <div class="stat-icon">📄</div>
    <div class="stat-info">
      <div class="stat-value"><?= $activeLeases ?></div>
      <div class="stat-label">Active Leases</div>
    </div>
  </div>
  <div class="stat-card danger">
    <div class="stat-icon">⚠️</div>
    <div class="stat-info">
      <div class="stat-value"><?= $overdueLeases ?></div>
      <div class="stat-label">Overdue Leases</div>
    </div>
  </div>
  <div class="stat-card success">
    <div class="stat-icon">✅</div>
    <div class="stat-info">
      <div class="stat-value"><?= $completedLeases ?></div>
      <div class="stat-label">Completed Leases</div>
    </div>
  </div>
  <div class="stat-card danger">
    <div class="stat-icon">🛑</div>
    <div class="stat-info">
      <div class="stat-value"><?= $riskyCustomers ?></div>
      <div class="stat-label">Risky Customers</div>
    </div>
  </div>
</div>

<!-- ===== CHARTS ===== -->
<div class="charts-grid" style="grid-template-columns:2fr 1fr;margin-bottom:28px">
  <div class="chart-card">
    <div class="card-header">
      <div class="card-title">📊 Monthly Revenue & Payments (Last 12 Months)</div>
    </div>
    <?php if (!empty($monthlyRev)): ?>
    <canvas id="monthlyRevChart" style="max-height:260px"></canvas>
    <?php else: ?>
    <div class="empty-state" style="padding:40px"><div class="empty-state-icon">📊</div><h3>No payment data yet</h3></div>
    <?php endif; ?>
  </div>
  <div class="chart-card">
    <div class="card-header">
      <div class="card-title">🥧 Lease Status</div>
    </div>
    <canvas id="leaseStatusChart" style="max-height:220px"></canvas>
    <div style="text-align:center;margin-top:12px;font-size:13px;color:var(--text-muted)">
      Total: <?= $totalLeases ?> leases
    </div>
  </div>
</div>

<!-- ===== OVERDUE ANALYSIS ===== -->
<?php if (!empty($overdueData)): ?>
<div class="card section-gap">
  <div class="card-header">
    <div>
      <div class="card-title">🔴 Overdue Lease Analysis</div>
      <div class="card-subtitle"><?= count($overdueData) ?> customer(s) with missed payments</div>
    </div>
    <span class="badge badge-danger"><?= count($overdueData) ?> Overdue</span>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr>
        <th>Customer</th><th>CNIC</th><th>Phone</th><th>Motorcycle</th>
        <th>Due Date</th><th>Days Overdue</th><th>Balance</th><th>Score</th><th>Action</th>
      </tr></thead>
      <tbody>
        <?php foreach ($overdueData as $r): ?>
        <tr class="overdue-row">
          <td><strong><?= e($r['name']) ?></strong></td>
          <td><?= e($r['cnic']) ?></td>
          <td><?= e($r['phone']) ?></td>
          <td><?= e($r['moto_name']) ?></td>
          <td><?= date('d M Y', strtotime($r['next_due_date'])) ?></td>
          <td><span class="badge badge-danger"><?= $r['days_overdue'] ?> days</span></td>
          <td><strong style="color:var(--warning)"><?= formatCurrency($r['balance']) ?></strong></td>
          <td><span class="badge score-<?= $r['score'] ?>"><?= ucfirst($r['score']) ?></span></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/leases/view.php?id=<?= $r['lease_id'] ?>" class="btn btn-sm btn-outline">View</a>
            <a href="<?= BASE_URL ?>/modules/payments/add.php?lease_id=<?= $r['lease_id'] ?>" class="btn btn-sm btn-success">+ Pay</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ===== TOP CUSTOMERS ===== -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px">

<div class="card">
  <div class="card-header">
    <div class="card-title">🏆 Top Customers by Payment</div>
  </div>
  <?php if (empty($topCustomers)): ?>
  <div class="empty-state" style="padding:30px"><div class="empty-state-icon">🏆</div><h3>No data yet</h3></div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>#</th><th>Customer</th><th>Score</th><th>Total Paid</th></tr></thead>
      <tbody>
        <?php foreach ($topCustomers as $i => $c): ?>
        <tr>
          <td><strong><?= $i+1 ?></strong></td>
          <td>
            <div style="font-weight:600"><?= e($c['name']) ?></div>
            <div style="font-size:11px;color:var(--text-faint)"><?= e($c['cnic']) ?></div>
          </td>
          <td><span class="badge score-<?= $c['score'] ?>"><?= ucfirst($c['score']) ?></span></td>
          <td><strong style="color:var(--success)"><?= formatCurrency($c['total_paid']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ===== MOTORCYCLE UTILIZATION ===== -->
<div class="card">
  <div class="card-header">
    <div class="card-title">🏍️ Motorcycle Revenue Performance</div>
  </div>
  <?php if (empty($motoUtil)): ?>
  <div class="empty-state" style="padding:30px"><div class="empty-state-icon">🏍️</div><h3>No data yet</h3></div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>#</th><th>Motorcycle</th><th>Status</th><th>Revenue</th></tr></thead>
      <tbody>
        <?php
        $msBadges = ['available'=>'badge-success','leased'=>'badge-accent','retired'=>'badge-muted'];
        foreach ($motoUtil as $i => $m):
        ?>
        <tr>
          <td><strong><?= $i+1 ?></strong></td>
          <td>
            <div style="font-weight:600"><?= e($m['name']) ?></div>
            <div style="font-size:11px;color:var(--text-faint)"><?= e($m['model']) ?></div>
          </td>
          <td><span class="badge <?= $msBadges[$m['status']] ?? 'badge-muted' ?>"><?= ucfirst($m['status']) ?></span></td>
          <td><strong style="color:var(--success)"><?= formatCurrency($m['revenue']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

</div>

<!-- ===== SUMMARY TABLE ===== -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📋 Business Summary</div>
    <span class="badge badge-accent"><?= date('d M Y') ?></span>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
    <?php
    $summary = [
      ['label'=>'Total Revenue Collected',   'value'=>formatCurrency($totalRevenue),      'color'=>'var(--success)'],
      ['label'=>'Outstanding Receivables',   'value'=>formatCurrency($totalOutstanding),   'color'=>'var(--warning)'],
      ['label'=>'Total Customers',           'value'=>number_format($totalCustomers),      'color'=>'var(--accent)'],
      ['label'=>'Total Motorcycles',         'value'=>number_format($totalMotorcycles),    'color'=>'var(--text)'],
      ['label'=>'Currently Leased',          'value'=>number_format($leasedMoto),          'color'=>'var(--accent)'],
      ['label'=>'Total Leases Ever',         'value'=>number_format($totalLeases),         'color'=>'var(--text)'],
      ['label'=>'Active Leases',             'value'=>number_format($activeLeases),        'color'=>'var(--accent)'],
      ['label'=>'Completed Leases',          'value'=>number_format($completedLeases),     'color'=>'var(--success)'],
      ['label'=>'Defaulted Leases',          'value'=>number_format($defaultedLeases),     'color'=>'var(--danger)'],
      ['label'=>'Currently Overdue',         'value'=>number_format($overdueLeases),       'color'=>'var(--danger)'],
      ['label'=>'Risky Score Customers',     'value'=>number_format($riskyCustomers),      'color'=>'var(--danger)'],
    ];
    foreach ($summary as $s):
    ?>
    <div style="background:var(--bg);border-radius:var(--radius-sm);padding:16px;border:1px solid var(--border)">
      <div style="font-size:11px;font-weight:600;color:var(--text-faint);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px"><?= $s['label'] ?></div>
      <div style="font-size:20px;font-weight:700;color:<?= $s['color'] ?>"><?= $s['value'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

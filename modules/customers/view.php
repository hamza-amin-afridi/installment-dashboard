<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
$customer = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$customer->execute([$id]); $customer = $customer->fetch();
if (!$customer) { setFlash('error','Customer not found.'); header('Location:'.BASE_URL.'/modules/customers/index.php'); exit; }

// Guarantors
$guarantors = $pdo->prepare("SELECT * FROM guarantors WHERE customer_id=? ORDER BY id");
$guarantors->execute([$id]); $guarantors = $guarantors->fetchAll();

// Active leases
$leases = $pdo->prepare("
    SELECT l.*, m.name AS moto_name, lp.name AS plan_name, lp.duration_months
    FROM leases l
    JOIN motorcycles m  ON l.motorcycle_id = m.id
    JOIN leasing_plans lp ON l.plan_id = lp.id
    WHERE l.customer_id=? ORDER BY l.created_at DESC
");
$leases->execute([$id]); $leases = $leases->fetchAll();

// Payment history
$payments = $pdo->prepare("
    SELECT p.*, m.name AS moto_name
    FROM payments p
    JOIN leases l ON p.lease_id = l.id
    JOIN motorcycles m ON l.motorcycle_id = m.id
    WHERE l.customer_id=? ORDER BY p.payment_date DESC LIMIT 20
");
$payments->execute([$id]); $payments = $payments->fetchAll();

$pageTitle = e($customer['name']); $activePage = 'customers';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">👤 <?= e($customer['name']) ?></h1>
    <p class="page-subtitle">Customer Profile</p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/modules/leases/add.php?customer_id=<?= $id ?>" class="btn btn-primary">+ New Lease</a>
    <a href="<?= BASE_URL ?>/modules/customers/edit.php?id=<?= $id ?>" class="btn btn-outline">✏️ Edit</a>
    <a href="<?= BASE_URL ?>/modules/customers/index.php" class="btn btn-outline">← Back</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">

<!-- Customer Info -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📋 Personal Information</div>
    <span class="badge score-<?= $customer['score'] ?>"><?= ucfirst($customer['score']) ?> — <?= $customer['score_value'] ?>/100</span>
  </div>
  <div class="score-bar"><div class="score-fill <?= $customer['score'] ?>" style="width:<?= $customer['score_value'] ?>%"></div></div>
  <div class="divider"></div>
  <div class="info-grid">
    <div class="info-item"><span class="info-label">Full Name</span><span class="info-value"><?= e($customer['name']) ?></span></div>
    <div class="info-item"><span class="info-label">CNIC</span><span class="info-value"><code><?= e($customer['cnic']) ?></code></span></div>
    <div class="info-item"><span class="info-label">Phone</span><span class="info-value"><?= e($customer['phone']) ?></span></div>
    <div class="info-item"><span class="info-label">Member Since</span><span class="info-value"><?= date('d M Y', strtotime($customer['created_at'])) ?></span></div>
    <div class="info-item" style="grid-column:1/-1"><span class="info-label">Address</span><span class="info-value"><?= e($customer['address']) ?></span></div>
  </div>
</div>

<!-- Guarantors -->
<div class="card">
  <div class="card-title" style="margin-bottom:16px">🛡️ Guarantors</div>
  <?php if (empty($guarantors)): ?>
    <div class="empty-state" style="padding:20px"><div class="empty-state-icon">🛡️</div><h3>No guarantors</h3></div>
  <?php else: foreach ($guarantors as $i => $g): ?>
    <div style="margin-bottom:16px;<?= $i > 0 ? 'padding-top:16px;border-top:1px solid var(--border)' : '' ?>">
      <p style="font-weight:600;margin-bottom:8px;color:var(--accent)">Guarantor <?= $i+1 ?></p>
      <div class="info-grid">
        <div class="info-item"><span class="info-label">Name</span><span class="info-value"><?= e($g['name']) ?></span></div>
        <div class="info-item"><span class="info-label">CNIC</span><span class="info-value"><code><?= e($g['cnic']) ?></code></span></div>
        <div class="info-item"><span class="info-label">Phone</span><span class="info-value"><?= e($g['phone']) ?></span></div>
        <div class="info-item"><span class="info-label">Address</span><span class="info-value"><?= e($g['address']) ?></span></div>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
</div>

<!-- Leases -->
<div class="card section-gap">
  <div class="card-header">
    <div class="card-title">📄 Lease History</div>
    <a href="<?= BASE_URL ?>/modules/leases/add.php?customer_id=<?= $id ?>" class="btn btn-sm btn-primary">+ New Lease</a>
  </div>
  <?php if (empty($leases)): ?>
    <div class="empty-state" style="padding:30px"><div class="empty-state-icon">📄</div><h3>No leases yet</h3></div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Motorcycle</th><th>Plan</th><th>Total</th><th>Paid</th><th>Balance</th><th>Monthly</th><th>Next Due</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($leases as $l):
          $bal     = remainingBalance($l['total_amount'], $l['paid_amount']);
          $overdue = $l['status']==='active' && isOverdue($l['next_due_date']);
          $progress= $l['total_amount'] > 0 ? min(100, round($l['paid_amount']/$l['total_amount']*100)) : 0;
        ?>
        <tr class="<?= $overdue ? 'overdue-row' : '' ?>">
          <td><strong><?= e($l['moto_name']) ?></strong></td>
          <td><?= e($l['plan_name']) ?></td>
          <td><?= formatCurrency($l['total_amount']) ?></td>
          <td>
            <?= formatCurrency($l['paid_amount']) ?>
            <div class="progress-bar-wrap" style="margin-top:4px"><div class="progress-bar-fill" style="width:<?= $progress ?>%"></div></div>
          </td>
          <td><?= $bal > 0 ? '<strong style="color:var(--warning)">'.formatCurrency($bal).'</strong>' : '<span style="color:var(--success)">Cleared ✅</span>' ?></td>
          <td><?= formatCurrency($l['monthly_install']) ?></td>
          <td>
            <?php if ($overdue): ?>
              <span style="color:var(--danger)"><?= date('d M Y',strtotime($l['next_due_date'])) ?> ⚠️</span>
            <?php else: ?>
              <?= date('d M Y',strtotime($l['next_due_date'])) ?>
            <?php endif; ?>
          </td>
          <td>
            <?php
            $lbadge = ['active'=>'badge-accent','completed'=>'badge-success','defaulted'=>'badge-danger'];
            echo '<span class="badge '.($lbadge[$l['status']]??'badge-muted').'">'.ucfirst($l['status']).'</span>';
            ?>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/modules/leases/view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline">View</a>
            <a href="<?= BASE_URL ?>/modules/payments/add.php?lease_id=<?= $l['id'] ?>" class="btn btn-sm btn-success">+ Pay</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Payment History -->
<div class="card">
  <div class="card-header">
    <div class="card-title">💳 Payment History</div>
  </div>
  <?php if (empty($payments)): ?>
    <div class="empty-state" style="padding:30px"><div class="empty-state-icon">💳</div><h3>No payments yet</h3></div>
  <?php else: ?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Receipt</th><th>Motorcycle</th><th>Amount</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><code><?= e($p['receipt_no']) ?></code></td>
          <td><?= e($p['moto_name']) ?></td>
          <td><strong style="color:var(--success)"><?= formatCurrency($p['amount']) ?></strong></td>
          <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
          <td><a href="<?= BASE_URL ?>/modules/receipts/view.php?payment_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">🧾 Receipt</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

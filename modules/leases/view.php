<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
$lease = $pdo->prepare("
    SELECT l.*, c.name AS customer_name, c.cnic, c.phone, c.address, c.id AS customer_id,
           m.name AS moto_name, m.model, m.image_path,
           lp.name AS plan_name, lp.duration_months, lp.markup_percent
    FROM leases l
    JOIN customers c ON l.customer_id=c.id
    JOIN motorcycles m ON l.motorcycle_id=m.id
    JOIN leasing_plans lp ON l.plan_id=lp.id
    WHERE l.id=?
");
$lease->execute([$id]); $lease = $lease->fetch();
if (!$lease) { setFlash('error','Lease not found.'); header('Location:'.BASE_URL.'/modules/leases/index.php'); exit; }

$payments = $pdo->prepare("SELECT * FROM payments WHERE lease_id=? ORDER BY payment_date DESC");
$payments->execute([$id]); $payments = $payments->fetchAll();
$balance  = remainingBalance($lease['total_amount'], $lease['paid_amount']);
$progress = $lease['total_amount'] > 0 ? min(100, round($lease['paid_amount']/$lease['total_amount']*100)) : 0;
$overdue  = $lease['status']==='active' && isOverdue($lease['next_due_date']);

$pageTitle = 'Lease #'.$id; $activePage = 'leases';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">📄 Lease #<?= $id ?></h1><p class="page-subtitle"><?= e($lease['customer_name']) ?> — <?= e($lease['moto_name']) ?></p></div>
  <div style="display:flex;gap:10px">
    <?php if($lease['status']==='active'): ?>
    <a href="<?= BASE_URL ?>/modules/payments/add.php?lease_id=<?= $id ?>" class="btn btn-success">+ Record Payment</a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/modules/leases/index.php" class="btn btn-outline">← Back</a>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
<div class="card">
  <div class="card-header">
    <div class="card-title">📊 Lease Overview</div>
    <?php $badges=['active'=>'badge-accent','completed'=>'badge-success','defaulted'=>'badge-danger'];?>
    <span class="badge <?=$badges[$lease['status']]??'badge-muted'?>"><?=ucfirst($lease['status'])?></span>
  </div>
  <div class="info-grid">
    <div class="info-item"><span class="info-label">Customer</span><span class="info-value"><a href="<?=BASE_URL?>/modules/customers/view.php?id=<?=$lease['customer_id']?>" style="color:var(--accent)"><?=e($lease['customer_name'])?></a></span></div>
    <div class="info-item"><span class="info-label">Motorcycle</span><span class="info-value"><?=e($lease['moto_name'])?> (<?=e($lease['model'])?>)</span></div>
    <div class="info-item"><span class="info-label">Plan</span><span class="info-value"><?=e($lease['plan_name'])?></span></div>
    <div class="info-item"><span class="info-label">Start Date</span><span class="info-value"><?=date('d M Y',strtotime($lease['start_date']))?></span></div>
    <div class="info-item"><span class="info-label">End Date</span><span class="info-value"><?=date('d M Y',strtotime($lease['end_date']))?></span></div>
    <div class="info-item">
      <span class="info-label">Next Due</span>
      <span class="info-value" style="<?=$overdue?'color:var(--danger)':''?>">
        <?=date('d M Y',strtotime($lease['next_due_date']))?> <?=$overdue?'⚠️':''?>
      </span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title" style="margin-bottom:16px">💰 Payment Summary</div>
  <div class="receipt-row"><span>Total Payable</span><strong><?=formatCurrency($lease['total_amount'])?></strong></div>
  <div class="receipt-row"><span>Monthly Installment</span><strong><?=formatCurrency($lease['monthly_install'])?></strong></div>
  <div class="receipt-row"><span>Total Paid</span><strong style="color:var(--success)"><?=formatCurrency($lease['paid_amount'])?></strong></div>
  <div class="receipt-row receipt-total"><span>Remaining Balance</span><strong style="color:<?=$balance>0?'var(--warning)':'var(--success)'?>"><?=formatCurrency($balance)?></strong></div>
  <div style="margin-top:16px">
    <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted);margin-bottom:6px"><span>Payment Progress</span><span><?=$progress?>%</span></div>
    <div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:<?=$progress?>%"></div></div>
  </div>
</div>
</div>

<!-- Payment History -->
<div class="card">
  <div class="card-header">
    <div class="card-title">💳 Payment History</div>
  </div>
  <?php if(empty($payments)):?>
    <div class="empty-state" style="padding:30px"><div class="empty-state-icon">💳</div><h3>No payments recorded</h3></div>
  <?php else:?>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>#</th><th>Receipt No</th><th>Amount</th><th>Date</th><th>Notes</th><th>Receipt</th></tr></thead>
      <tbody>
        <?php foreach($payments as $i=>$p):?>
        <tr>
          <td><?=$i+1?></td>
          <td><code><?=e($p['receipt_no'])?></code></td>
          <td><strong style="color:var(--success)"><?=formatCurrency($p['amount'])?></strong></td>
          <td><?=date('d M Y',strtotime($p['payment_date']))?></td>
          <td><?=e($p['notes'])?:'—'?></td>
          <td><a href="<?=BASE_URL?>/modules/receipts/view.php?payment_id=<?=$p['id']?>" class="btn btn-sm btn-outline">🧾 Print</a></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif;?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

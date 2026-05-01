<?php
/**
 * Receipt View & Print
 * Displays a styled, printable payment receipt.
 * Supports ?payment_id=N  or  ?receipt_no=RCP-XXXX
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pdo = getDB();

// --- Resolve payment ---
if (!empty($_GET['payment_id'])) {
    $paymentId = (int)$_GET['payment_id'];
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);
} elseif (!empty($_GET['receipt_no'])) {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE receipt_no = ?");
    $stmt->execute([trim($_GET['receipt_no'])]);
} else {
    setFlash('error', 'No payment specified.');
    header('Location: ' . BASE_URL . '/modules/payments/index.php');
    exit;
}

$payment = $stmt->fetch();
if (!$payment) {
    setFlash('error', 'Receipt not found.');
    header('Location: ' . BASE_URL . '/modules/payments/index.php');
    exit;
}

// --- Fetch related data ---
$lease = $pdo->prepare("
    SELECT l.*,
           c.name AS customer_name, c.cnic, c.phone, c.address,
           m.name AS moto_name, m.model,
           lp.name AS plan_name, lp.duration_months
    FROM leases l
    JOIN customers c    ON l.customer_id  = c.id
    JOIN motorcycles m  ON l.motorcycle_id = m.id
    JOIN leasing_plans lp ON l.plan_id    = lp.id
    WHERE l.id = ?
");
$lease->execute([$payment['lease_id']]);
$lease = $lease->fetch();

if (!$lease) {
    setFlash('error', 'Lease data not found.');
    header('Location: ' . BASE_URL . '/modules/payments/index.php');
    exit;
}

// Recorded-by user name
$recorderName = 'System';
if ($payment['recorded_by']) {
    $rec = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $rec->execute([$payment['recorded_by']]);
    $recRow = $rec->fetch();
    if ($recRow) $recorderName = $recRow['name'];
}

$balance  = remainingBalance($lease['total_amount'], $lease['paid_amount']);
$progress = $lease['total_amount'] > 0
    ? min(100, round($lease['paid_amount'] / $lease['total_amount'] * 100))
    : 0;

$pageTitle  = 'Receipt ' . e($payment['receipt_no']);
$activePage = 'receipts';

// Inline print CSS injected via $extraCss
$extraCss = '<style>
@media print {
  .sidebar, .topbar, .no-print, .mobile-overlay { display: none !important; }
  .main-wrapper { margin-left: 0 !important; }
  .page-content  { padding: 0 !important; }
  body, html { background: #fff !important; color: #000 !important; }
  .receipt-print-card {
    box-shadow: none !important;
    border: 1px solid #ccc !important;
    max-width: 100% !important;
    margin: 0 !important;
  }
  .receipt-print-card * { color: #000 !important; background: transparent !important; border-color: #ccc !important; }
  .receipt-watermark { opacity: 0.07 !important; }
}
.receipt-print-card {
  max-width: 680px; margin: 0 auto;
  background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); overflow: hidden;
  box-shadow: 0 8px 40px rgba(0,0,0,0.35);
  position: relative;
}
.receipt-watermark {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 120px; opacity: 0.03; pointer-events: none;
  user-select: none; transform: rotate(-25deg);
  font-weight: 900; letter-spacing: 8px;
  color: var(--accent);
}
.receipt-top {
  background: linear-gradient(135deg, var(--accent) 0%, #4e46d5 100%);
  padding: 32px 36px 28px;
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 16px;
}
.receipt-brand { display: flex; align-items: center; gap: 14px; }
.receipt-brand-icon { font-size: 40px; }
.receipt-brand-name { font-size: 22px; font-weight: 800; color: #fff; }
.receipt-brand-sub  { font-size: 12px; color: rgba(255,255,255,0.75); }
.receipt-no-block { text-align: right; }
.receipt-no-label { font-size: 11px; color: rgba(255,255,255,0.7); text-transform: uppercase; letter-spacing: 1px; }
.receipt-no-value { font-size: 20px; font-weight: 700; color: #fff; font-family: monospace; }
.receipt-stamp {
  display: inline-block; padding: 4px 14px;
  border: 2px solid rgba(255,255,255,0.6);
  border-radius: 6px; font-size: 11px; color: rgba(255,255,255,0.85);
  margin-top: 6px; letter-spacing: 1px; text-transform: uppercase;
}
.receipt-body { padding: 32px 36px; }
.receipt-section-title {
  font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
  text-transform: uppercase; color: var(--text-faint);
  margin-bottom: 12px; padding-bottom: 6px;
  border-bottom: 1px solid var(--border);
}
.r-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 24px; margin-bottom: 24px; }
.r-item { display: flex; flex-direction: column; gap: 3px; }
.r-label { font-size: 11px; font-weight: 600; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.5px; }
.r-value { font-size: 14px; font-weight: 500; color: var(--text); }
.receipt-amount-box {
  background: var(--accent-dim); border: 1px solid rgba(108,99,255,0.25);
  border-radius: var(--radius-sm); padding: 20px 24px;
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
}
.receipt-amount-label { font-size: 13px; color: var(--text-muted); }
.receipt-amount-value { font-size: 32px; font-weight: 800; color: var(--accent); font-variant-numeric: tabular-nums; }
.progress-section { margin-bottom: 24px; }
.progress-meta { display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
.receipt-footer-bar {
  border-top: 1px dashed var(--border); padding: 20px 36px;
  display: flex; align-items: center; justify-content: space-between;
  font-size: 12px; color: var(--text-faint); flex-wrap: wrap; gap: 8px;
}
.receipt-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px; }
</style>';

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header no-print">
  <div>
    <h1 class="page-title">🧾 Receipt</h1>
    <p class="page-subtitle">Payment confirmation for <?= e($lease['customer_name']) ?></p>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/modules/payments/index.php" class="btn btn-outline no-print">← Payments</a>
    <a href="<?= BASE_URL ?>/modules/leases/view.php?id=<?= $payment['lease_id'] ?>" class="btn btn-outline no-print">📄 Lease</a>
    <button onclick="window.print()" class="btn btn-primary no-print">🖨️ Print Receipt</button>
  </div>
</div>

<!-- ===== RECEIPT CARD ===== -->
<div class="receipt-print-card">
  <!-- Watermark -->
  <div class="receipt-watermark">PAID</div>

  <!-- Header Banner -->
  <div class="receipt-top">
    <div class="receipt-brand">
      <div class="receipt-brand-icon">🏍️</div>
      <div>
        <div class="receipt-brand-name"><?= APP_NAME ?></div>
        <div class="receipt-brand-sub">Motorcycle Leasing Management Portal</div>
      </div>
    </div>
    <div class="receipt-no-block">
      <div class="receipt-no-label">Receipt No.</div>
      <div class="receipt-no-value"><?= e($payment['receipt_no']) ?></div>
      <div class="receipt-stamp">✅ PAYMENT RECEIVED</div>
    </div>
  </div>

  <!-- Body -->
  <div class="receipt-body">

    <!-- Amount Highlight -->
    <div class="receipt-amount-box">
      <div>
        <div class="receipt-amount-label">Amount Paid</div>
        <div class="receipt-amount-value"><?= formatCurrency($payment['amount']) ?></div>
      </div>
      <div style="text-align:right">
        <div class="receipt-amount-label">Payment Date</div>
        <div style="font-size:18px;font-weight:700;color:var(--text)"><?= date('d M Y', strtotime($payment['payment_date'])) ?></div>
        <div style="font-size:12px;color:var(--text-faint);margin-top:4px">Recorded: <?= date('d M Y H:i', strtotime($payment['created_at'])) ?></div>
      </div>
    </div>

    <!-- Customer Info -->
    <div class="receipt-section-title">👤 Customer Information</div>
    <div class="r-grid">
      <div class="r-item">
        <span class="r-label">Full Name</span>
        <span class="r-value" style="font-weight:700"><?= e($lease['customer_name']) ?></span>
      </div>
      <div class="r-item">
        <span class="r-label">CNIC</span>
        <span class="r-value"><code><?= e($lease['cnic']) ?></code></span>
      </div>
      <div class="r-item">
        <span class="r-label">Phone</span>
        <span class="r-value"><?= e($lease['phone']) ?></span>
      </div>
      <div class="r-item">
        <span class="r-label">Address</span>
        <span class="r-value"><?= e($lease['address']) ?></span>
      </div>
    </div>

    <!-- Lease Info -->
    <div class="receipt-section-title">🏍️ Lease Details</div>
    <div class="r-grid">
      <div class="r-item">
        <span class="r-label">Motorcycle</span>
        <span class="r-value" style="font-weight:700"><?= e($lease['moto_name']) ?> <?= e($lease['model']) ?></span>
      </div>
      <div class="r-item">
        <span class="r-label">Leasing Plan</span>
        <span class="r-value"><?= e($lease['plan_name']) ?> (<?= $lease['duration_months'] ?> months)</span>
      </div>
      <div class="r-item">
        <span class="r-label">Lease Start</span>
        <span class="r-value"><?= date('d M Y', strtotime($lease['start_date'])) ?></span>
      </div>
      <div class="r-item">
        <span class="r-label">Lease End</span>
        <span class="r-value"><?= date('d M Y', strtotime($lease['end_date'])) ?></span>
      </div>
      <div class="r-item">
        <span class="r-label">Total Payable</span>
        <span class="r-value" style="font-weight:700"><?= formatCurrency($lease['total_amount']) ?></span>
      </div>
      <div class="r-item">
        <span class="r-label">Monthly Installment</span>
        <span class="r-value"><?= formatCurrency($lease['monthly_install']) ?></span>
      </div>
    </div>

    <!-- Payment Summary -->
    <div class="receipt-section-title">💰 Payment Summary</div>
    <div style="margin-bottom:20px">
      <div class="receipt-row" style="padding:10px 0"><span>Total Payable</span><strong><?= formatCurrency($lease['total_amount']) ?></strong></div>
      <div class="receipt-row" style="padding:10px 0"><span>Total Paid (incl. this)</span><strong style="color:var(--success)"><?= formatCurrency($lease['paid_amount']) ?></strong></div>
      <div class="receipt-row receipt-total" style="padding:12px 0">
        <span>Remaining Balance</span>
        <strong style="color:<?= $balance > 0 ? 'var(--warning)' : 'var(--success)' ?>">
          <?= $balance > 0 ? formatCurrency($balance) : '✅ Fully Cleared' ?>
        </strong>
      </div>
    </div>

    <!-- Progress -->
    <div class="progress-section">
      <div class="progress-meta">
        <span>Payment Progress</span>
        <span style="font-weight:700;color:var(--accent)"><?= $progress ?>%</span>
      </div>
      <div class="progress-bar-wrap">
        <div class="progress-bar-fill" style="width:<?= $progress ?>%"></div>
      </div>
    </div>

    <?php if ($payment['notes']): ?>
    <!-- Notes -->
    <div class="receipt-section-title" style="margin-top:20px">📝 Notes</div>
    <div style="padding:12px 16px;background:var(--bg);border-radius:var(--radius-sm);font-size:13px;color:var(--text-muted);border:1px solid var(--border)">
      <?= e($payment['notes']) ?>
    </div>
    <?php endif; ?>

  </div>

  <!-- Footer -->
  <div class="receipt-footer-bar">
    <div>
      <strong><?= APP_NAME ?></strong> &nbsp;|&nbsp;
      Recorded by: <strong><?= e($recorderName) ?></strong> &nbsp;|&nbsp;
      <?= date('d M Y H:i', strtotime($payment['created_at'])) ?>
    </div>
    <div>
      Lease #<?= $payment['lease_id'] ?> &nbsp;|&nbsp; Receipt: <strong><?= e($payment['receipt_no']) ?></strong>
    </div>
  </div>
</div>

<!-- Action Buttons (below card) -->
<div style="max-width:680px;margin:20px auto" class="no-print">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <button onclick="window.print()" class="btn btn-primary">🖨️ Print / Save PDF</button>
    <a href="<?= BASE_URL ?>/modules/leases/view.php?id=<?= $payment['lease_id'] ?>" class="btn btn-outline">📄 View Lease</a>
    <a href="<?= BASE_URL ?>/modules/payments/add.php?lease_id=<?= $payment['lease_id'] ?>" class="btn btn-success">+ Record Next Payment</a>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

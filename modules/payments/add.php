<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB(); $errors = [];

$preLeaseId = (int)($_GET['lease_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $leaseId     = (int)($_POST['lease_id'] ?? 0);
    $amount      = (float)($_POST['amount'] ?? 0);
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $notes       = trim($_POST['notes'] ?? '');

    if (!$leaseId)  $errors[] = 'Select a lease.';
    if ($amount<=0) $errors[] = 'Payment amount must be greater than 0.';

    if (empty($errors)) {
        $lease = $pdo->prepare("SELECT * FROM leases WHERE id=? AND status='active'");
        $lease->execute([$leaseId]); $lease = $lease->fetch();
        if (!$lease) { $errors[] = 'Lease not found or not active.'; }
        else {
            $balance = remainingBalance($lease['total_amount'], $lease['paid_amount']);
            if ($amount > $balance + 0.01) $errors[] = sprintf('Payment (%s) exceeds remaining balance (%s).', formatCurrency($amount), formatCurrency($balance));
        }
    }

    if (empty($errors)) {
        $receiptNo = generateReceiptNo();
        $user      = currentUser();

        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO payments (lease_id,amount,payment_date,receipt_no,recorded_by,notes) VALUES (?,?,?,?,?,?)")
                ->execute([$leaseId,$amount,$paymentDate,$receiptNo,$user['id'],$notes]);
            $paymentId = (int)$pdo->lastInsertId();

            $pdo->prepare("UPDATE leases SET paid_amount=paid_amount+? WHERE id=?")->execute([$amount,$leaseId]);

            // Advance due date / mark complete
            advanceNextDueDate($leaseId);

            // Update customer score
            updateCustomerScore($lease['customer_id']);

            logActivity("Recorded payment $receiptNo for lease #$leaseId",'payment',$paymentId);
            $pdo->commit();

            setFlash('success',"Payment recorded! Receipt: $receiptNo");
            header('Location:'.BASE_URL.'/modules/receipts/view.php?payment_id='.$paymentId); exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to record payment. Please try again.';
            error_log($e->getMessage());
        }
    }
}

// Fetch leases for dropdown
$allLeases = $pdo->query("
    SELECT l.id, c.name AS customer_name, m.name AS moto_name,
           l.total_amount, l.paid_amount,
           (l.total_amount - l.paid_amount) AS balance
    FROM leases l
    JOIN customers c ON l.customer_id=c.id
    JOIN motorcycles m ON l.motorcycle_id=m.id
    WHERE l.status='active'
    ORDER BY c.name
")->fetchAll();

$pageTitle = 'Record Payment'; $activePage = 'payments';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">💳 Record Payment</h1></div>
  <a href="<?= BASE_URL ?>/modules/payments/index.php" class="btn btn-outline">← Back</a>
</div>

<?php if($errors):?><div class="flash flash-error" style="margin-bottom:20px">❌ <?=implode('<br>',array_map('e',$errors))?></div><?php endif;?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
<div class="card">
  <form method="POST">
    <?= csrfField() ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Active Lease *</label>
        <select name="lease_id" id="leaseSelect" required onchange="updateLeaseInfo()">
          <option value="">— Select Active Lease —</option>
          <?php foreach($allLeases as $l): ?>
          <option value="<?=$l['id']?>"
                  data-balance="<?=$l['balance']?>"
                  data-monthly="<?=round($l['balance']>0?min($l['balance'],($l['total_amount']/(max(1,1)))) : 0,2)?>"
                  <?=($preLeaseId==$l['id'])?'selected':''?>>
            <?=e($l['customer_name'])?> — <?=e($l['moto_name'])?> (Balance: <?=formatCurrency($l['balance'])?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Payment Amount (PKR) *</label>
        <input type="number" name="amount" id="payAmount" min="1" step="0.01" placeholder="0.00" value="<?=e($_POST['amount']??'')?>" required>
        <span class="form-hint" id="balanceHint"></span>
      </div>
      <div class="form-group">
        <label>Payment Date *</label>
        <input type="date" name="payment_date" value="<?=e($_POST['payment_date']??date('Y-m-d'))?>" required>
      </div>
      <div class="form-group full">
        <label>Notes (optional)</label>
        <textarea name="notes" rows="2" placeholder="e.g. Cash payment"><?=e($_POST['notes']??'')?></textarea>
      </div>
    </div>
    <div class="form-actions" style="margin-top:20px">
      <button type="submit" class="btn btn-success">✅ Record Payment & Print Receipt</button>
    </div>
  </form>
</div>

<!-- Info panel -->
<div class="card" style="height:fit-content">
  <div class="card-title" style="margin-bottom:16px">📋 Lease Info</div>
  <div id="leaseInfoPanel" style="color:var(--text-faint);text-align:center;padding:20px">Select a lease above</div>
</div>
</div>

<script>
const leases = <?= json_encode(array_column($allLeases, null, 'id')) ?>;
function fmt(n){return '₨ '+parseFloat(n).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2});}
function updateLeaseInfo(){
  const sel = document.getElementById('leaseSelect');
  const id  = parseInt(sel.value);
  const lease = leases[id];
  const panel = document.getElementById('leaseInfoPanel');
  const hint  = document.getElementById('balanceHint');
  const amtIn = document.getElementById('payAmount');
  if(!lease){panel.innerHTML='<span style="color:var(--text-faint)">Select a lease above</span>';hint.textContent='';return;}
  hint.textContent='Remaining balance: '+fmt(lease.balance);
  amtIn.max = lease.balance;
  panel.innerHTML=`
    <div class="receipt-row"><span>Customer</span><strong>${lease.customer_name}</strong></div>
    <div class="receipt-row"><span>Motorcycle</span><strong>${lease.moto_name}</strong></div>
    <div class="receipt-row"><span>Total Amount</span><strong>${fmt(lease.total_amount)}</strong></div>
    <div class="receipt-row"><span>Paid So Far</span><strong style="color:var(--success)">${fmt(lease.paid_amount)}</strong></div>
    <div class="receipt-row receipt-total"><span>Remaining</span><strong style="color:var(--warning)">${fmt(lease.balance)}</strong></div>
  `;
}
document.addEventListener('DOMContentLoaded',updateLeaseInfo);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

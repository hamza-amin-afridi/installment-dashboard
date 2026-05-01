<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB(); $errors = [];

// Pre-select customer if coming from customer profile
$preCustomer = (int)($_GET['customer_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $customerId   = (int)($_POST['customer_id']   ?? 0);
    $motorcycleId = (int)($_POST['motorcycle_id'] ?? 0);
    $planId       = (int)($_POST['plan_id']       ?? 0);
    $startDate    = $_POST['start_date'] ?? date('Y-m-d');
    $notes        = trim($_POST['notes'] ?? '');

    if (!$customerId)   $errors[] = 'Select a customer.';
    if (!$motorcycleId) $errors[] = 'Select a motorcycle.';
    if (!$planId)       $errors[] = 'Select a leasing plan.';

    if (empty($errors)) {
        // Fetch price and plan
        $moto = $pdo->prepare("SELECT * FROM motorcycles WHERE id=?"); $moto->execute([$motorcycleId]); $moto = $moto->fetch();
        $plan = $pdo->prepare("SELECT * FROM leasing_plans WHERE id=?"); $plan->execute([$planId]); $plan = $plan->fetch();

        if (!$moto || !$plan) { $errors[] = 'Invalid motorcycle or plan.'; }
        else {
            $totals  = calcLeaseTotals($moto['price'], $plan['markup_percent'], $plan['duration_months']);
            $endDate = date('Y-m-d', strtotime($startDate . ' +' . $plan['duration_months'] . ' months'));
            $nextDue = date('Y-m-d', strtotime($startDate . ' +1 month'));

            $pdo->prepare("
                INSERT INTO leases (customer_id,motorcycle_id,plan_id,total_amount,monthly_install,start_date,end_date,next_due_date,notes)
                VALUES (?,?,?,?,?,?,?,?,?)
            ")->execute([
                $customerId,$motorcycleId,$planId,
                $totals['total_amount'],$totals['monthly_install'],
                $startDate,$endDate,$nextDue,$notes
            ]);
            $leaseId = (int)$pdo->lastInsertId();

            // Mark motorcycle as leased
            $pdo->prepare("UPDATE motorcycles SET status='leased' WHERE id=?")->execute([$motorcycleId]);
            logActivity("Created lease #$leaseId",'lease',$leaseId);
            setFlash('success','Lease created successfully!');
            header('Location:'.BASE_URL.'/modules/leases/view.php?id='.$leaseId); exit;
        }
    }
}

$customers   = $pdo->query("SELECT id,name,cnic FROM customers ORDER BY name")->fetchAll();
$motorcycles = $pdo->query("SELECT id,name,model,price FROM motorcycles WHERE status='available' ORDER BY name")->fetchAll();
$plans       = $pdo->query("SELECT * FROM leasing_plans WHERE is_active=1 ORDER BY duration_months")->fetchAll();

$pageTitle = 'New Lease'; $activePage = 'leases';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">📄 Create New Lease</h1></div>
  <a href="<?= BASE_URL ?>/modules/leases/index.php" class="btn btn-outline">← Back</a>
</div>

<?php if($errors):?><div class="flash flash-error" style="margin-bottom:20px">❌ <?=implode('<br>',array_map('e',$errors))?></div><?php endif;?>

<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px">
<div>
  <form method="POST" id="leaseForm">
    <?= csrfField() ?>
    <div class="card section-gap">
      <div class="card-title" style="margin-bottom:20px">📋 Lease Details</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Customer *</label>
          <select name="customer_id" id="sel_customer" required>
            <option value="">— Select Customer —</option>
            <?php foreach($customers as $c): ?>
            <option value="<?=$c['id']?>" <?= (($_POST['customer_id']??$preCustomer)==$c['id'])?'selected':'' ?>>
              <?=e($c['name'])?> (<?=e($c['cnic'])?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Motorcycle *</label>
          <select name="motorcycle_id" id="sel_moto" required onchange="updateCalc()">
            <option value="">— Select Motorcycle —</option>
            <?php foreach($motorcycles as $m): ?>
            <option value="<?=$m['id']?>" data-price="<?=$m['price']?>" <?= (($_POST['motorcycle_id']??0)==$m['id'])?'selected':'' ?>>
              <?=e($m['name'])?> <?=e($m['model'])?> — <?=formatCurrency($m['price'])?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Leasing Plan *</label>
          <select name="plan_id" id="sel_plan" required onchange="updateCalc()">
            <option value="">— Select Plan —</option>
            <?php foreach($plans as $p): ?>
            <option value="<?=$p['id']?>" data-months="<?=$p['duration_months']?>" data-markup="<?=$p['markup_percent']?>" <?= (($_POST['plan_id']??0)==$p['id'])?'selected':'' ?>>
              <?=e($p['name'])?> — <?=$p['duration_months']?> months @ <?=$p['markup_percent']?>% markup
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Start Date *</label>
          <input type="date" name="start_date" value="<?=e($_POST['start_date']??date('Y-m-d'))?>" required onchange="updateCalc()">
        </div>
        <div class="form-group full">
          <label>Notes (optional)</label>
          <textarea name="notes" rows="2" placeholder="Any special notes…"><?=e($_POST['notes']??'')?></textarea>
        </div>
      </div>
      <div class="form-actions" style="margin-top:20px">
        <button type="submit" class="btn btn-primary">📄 Create Lease</button>
      </div>
    </div>
  </form>
</div>

<!-- Live Summary Card -->
<div class="card" style="height:fit-content;position:sticky;top:80px">
  <div class="card-title" style="margin-bottom:16px">🧮 Lease Summary</div>
  <div class="receipt-row"><span>Base Price</span><strong id="s_price">—</strong></div>
  <div class="receipt-row"><span>Markup</span><strong id="s_markup">—</strong></div>
  <div class="receipt-row receipt-total"><span>Total Payable</span><strong id="s_total" style="color:var(--accent)">—</strong></div>
  <div class="receipt-row"><span>Monthly Installment</span><strong id="s_monthly" style="color:var(--success)">—</strong></div>
  <div class="receipt-row"><span>Duration</span><strong id="s_months">—</strong></div>
  <div class="receipt-row"><span>End Date</span><strong id="s_enddate">—</strong></div>
  <div class="receipt-row"><span>First Due</span><strong id="s_due">—</strong></div>
</div>
</div>

<script>
function fmt(n){return '₨ '+parseFloat(n).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2});}
function updateCalc(){
  const motoSel = document.getElementById('sel_moto');
  const planSel = document.getElementById('sel_plan');
  const startIn = document.querySelector('[name="start_date"]');
  const mOpt = motoSel.options[motoSel.selectedIndex];
  const pOpt = planSel.options[planSel.selectedIndex];
  if(!mOpt||!mOpt.dataset.price||!pOpt||!pOpt.dataset.months) return;
  const price  = parseFloat(mOpt.dataset.price)||0;
  const markup = parseFloat(pOpt.dataset.markup)||0;
  const months = parseInt(pOpt.dataset.months)||1;
  const total  = price*(1+markup/100);
  const monthly= total/months;
  const start  = new Date(startIn.value||new Date());
  const endD   = new Date(start); endD.setMonth(endD.getMonth()+months);
  const dueD   = new Date(start); dueD.setMonth(dueD.getMonth()+1);
  const opts   = {day:'2-digit',month:'short',year:'numeric'};
  document.getElementById('s_price').textContent   = fmt(price);
  document.getElementById('s_markup').textContent  = fmt(total-price)+' ('+markup+'%)';
  document.getElementById('s_total').textContent   = fmt(total);
  document.getElementById('s_monthly').textContent = fmt(monthly);
  document.getElementById('s_months').textContent  = months+' months';
  document.getElementById('s_enddate').textContent = endD.toLocaleDateString('en-PK',opts);
  document.getElementById('s_due').textContent     = dueD.toLocaleDateString('en-PK',opts);
}
document.getElementById('sel_moto').addEventListener('change',updateCalc);
document.getElementById('sel_plan').addEventListener('change',updateCalc);
document.querySelector('[name="start_date"]').addEventListener('change',updateCalc);
updateCalc();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

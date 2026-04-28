<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB(); $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = trim($_POST['name']    ?? '');
    $months  = (int)($_POST['duration_months'] ?? 0);
    $markup  = (float)($_POST['markup_percent'] ?? 0);
    $active  = isset($_POST['is_active']) ? 1 : 0;

    if (!$name)    $errors[] = 'Plan name is required.';
    if ($months<1) $errors[] = 'Duration must be at least 1 month.';
    if ($markup<0) $errors[] = 'Markup cannot be negative.';

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO leasing_plans (name, duration_months, markup_percent, is_active) VALUES (?,?,?,?)")
            ->execute([$name, $months, $markup, $active]);
        logActivity("Added plan: $name", 'plan', (int)$pdo->lastInsertId());
        setFlash('success', "Plan '$name' created!");
        header('Location: '.BASE_URL.'/modules/plans/index.php'); exit;
    }
}

$pageTitle = 'Add Plan'; $activePage = 'plans';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">📋 Add Leasing Plan</h1></div>
  <a href="<?= BASE_URL ?>/modules/plans/index.php" class="btn btn-outline">← Back</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:900px">
<div class="card">
  <?php if ($errors): ?><div class="flash flash-error">❌ <?= implode('<br>',array_map('e',$errors)) ?></div><br><?php endif; ?>
  <form method="POST">
    <?= csrfField() ?>
    <div class="form-grid">
      <div class="form-group">
        <label>Plan Name *</label>
        <input type="text" name="name" placeholder="e.g. 12-Month Standard" value="<?= e($_POST['name']??'') ?>" required>
      </div>
      <div class="form-group">
        <label>Duration (Months) *</label>
        <input type="number" name="duration_months" id="months" min="1" max="60" placeholder="12" value="<?= e($_POST['duration_months']??'') ?>" required>
      </div>
      <div class="form-group">
        <label>Markup % *</label>
        <input type="number" name="markup_percent" id="markup" min="0" max="200" step="0.01" placeholder="40" value="<?= e($_POST['markup_percent']??'') ?>">
      </div>
      <div class="form-group" style="flex-direction:row;align-items:center;gap:10px;padding-top:24px">
        <input type="checkbox" name="is_active" id="is_active" value="1" <?= ($_POST['is_active']??1)?'checked':'' ?> style="width:auto">
        <label for="is_active" style="text-transform:none;letter-spacing:0">Active Plan</label>
      </div>
    </div>
    <div class="form-actions" style="margin-top:20px">
      <button type="submit" class="btn btn-primary">💾 Create Plan</button>
    </div>
  </form>
</div>

<!-- Live Calculator Preview -->
<div class="card">
  <div class="card-title" style="margin-bottom:16px">🧮 Live Calculator</div>
  <div class="form-group">
    <label>Motorcycle Price (PKR)</label>
    <input type="number" id="previewPrice" placeholder="185000" value="185000">
  </div>
  <div class="divider"></div>
  <div class="receipt-row"><span>Base Price</span><span id="previewBase">₨ 185,000.00</span></div>
  <div class="receipt-row"><span>Markup Amount</span><span id="previewMarkupAmt">₨ 0.00</span></div>
  <div class="receipt-row receipt-total"><span>Total Payable</span><span id="previewTotal">₨ 185,000.00</span></div>
  <div class="receipt-row"><span>Monthly Installment</span><span id="previewMonthly" style="color:var(--accent);font-weight:700">₨ 0.00</span></div>
</div>
</div>

<script>
function fmt(n){return '₨ '+parseFloat(n).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2});}
function updatePreview(){
  const price  = parseFloat(document.getElementById('previewPrice').value)||0;
  const markup = parseFloat(document.getElementById('markup').value)||0;
  const months = parseInt(document.getElementById('months').value)||1;
  const total  = price*(1+markup/100);
  const monthly= total/months;
  document.getElementById('previewBase').textContent      = fmt(price);
  document.getElementById('previewMarkupAmt').textContent = fmt(total-price);
  document.getElementById('previewTotal').textContent     = fmt(total);
  document.getElementById('previewMonthly').textContent   = fmt(monthly);
}
['markup','months','previewPrice'].forEach(id=>{
  const el = document.getElementById(id);
  if(el) el.addEventListener('input', updatePreview);
});
updatePreview();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

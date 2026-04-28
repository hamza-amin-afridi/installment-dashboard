<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
$plan = $pdo->prepare("SELECT * FROM leasing_plans WHERE id=?");
$plan->execute([$id]); $plan = $plan->fetch();
if (!$plan) { setFlash('error','Plan not found.'); header('Location:'.BASE_URL.'/modules/plans/index.php'); exit; }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name   = trim($_POST['name']   ?? '');
    $months = (int)($_POST['duration_months'] ?? 0);
    $markup = (float)($_POST['markup_percent'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if (!$name)   $errors[] = 'Name required.';
    if ($months<1)$errors[] = 'Duration must be at least 1.';
    if (empty($errors)) {
        $pdo->prepare("UPDATE leasing_plans SET name=?,duration_months=?,markup_percent=?,is_active=? WHERE id=?")
            ->execute([$name,$months,$markup,$active,$id]);
        logActivity("Updated plan: $name",'plan',$id);
        setFlash('success',"Plan updated!"); header('Location:'.BASE_URL.'/modules/plans/index.php'); exit;
    }
} else { $_POST = $plan; }

$pageTitle = 'Edit Plan'; $activePage = 'plans';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">✏️ Edit Leasing Plan</h1></div>
  <a href="<?= BASE_URL ?>/modules/plans/index.php" class="btn btn-outline">← Back</a>
</div>
<div class="card" style="max-width:500px">
  <?php if($errors):?><div class="flash flash-error">❌ <?=implode('<br>',array_map('e',$errors))?></div><br><?php endif;?>
  <form method="POST">
    <?= csrfField() ?>
    <div class="form-grid">
      <div class="form-group"><label>Plan Name *</label><input type="text" name="name" value="<?=e($_POST['name']??'')?>" required></div>
      <div class="form-group"><label>Duration (Months) *</label><input type="number" name="duration_months" min="1" value="<?=e($_POST['duration_months']??'')?>" required></div>
      <div class="form-group"><label>Markup %</label><input type="number" name="markup_percent" min="0" step="0.01" value="<?=e($_POST['markup_percent']??'')?>"></div>
      <div class="form-group" style="flex-direction:row;align-items:center;gap:10px;padding-top:24px">
        <input type="checkbox" name="is_active" value="1" <?=($_POST['is_active']??0)?'checked':''?> style="width:auto">
        <label style="text-transform:none;letter-spacing:0">Active</label>
      </div>
    </div>
    <div class="form-actions" style="margin-top:20px">
      <button type="submit" class="btn btn-primary">💾 Update Plan</button>
      <a href="<?= BASE_URL ?>/modules/plans/index.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

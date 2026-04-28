<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB(); $id = (int)($_GET['id'] ?? 0);
$customer = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$customer->execute([$id]); $customer = $customer->fetch();
if (!$customer) { setFlash('error','Customer not found.'); header('Location:'.BASE_URL.'/modules/customers/index.php'); exit; }

$guarantors = $pdo->prepare("SELECT * FROM guarantors WHERE customer_id=? ORDER BY id");
$guarantors->execute([$id]); $guarantors = $guarantors->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = trim($_POST['name']    ?? '');
    $cnic    = trim($_POST['cnic']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');
    if (!$name) $errors[] = 'Name required.';
    if (!$cnic) $errors[] = 'CNIC required.';
    // Check CNIC unique (exclude self)
    $dup = $pdo->prepare("SELECT id FROM customers WHERE cnic=? AND id!=?");
    $dup->execute([$cnic,$id]);
    if ($dup->fetch()) $errors[] = 'Another customer has this CNIC.';

    if (empty($errors)) {
        $pdo->prepare("UPDATE customers SET name=?,cnic=?,phone=?,address=? WHERE id=?")
            ->execute([$name,$cnic,$phone,$address,$id]);

        // Update guarantors
        $pdo->prepare("DELETE FROM guarantors WHERE customer_id=?")->execute([$id]);
        for ($g=1;$g<=2;$g++) {
            $gName=$_POST["g{$g}_name"]??''; $gCnic=$_POST["g{$g}_cnic"]??'';
            $gPhone=$_POST["g{$g}_phone"]??''; $gAddr=$_POST["g{$g}_address"]??'';
            if (trim($gName) && trim($gCnic)) {
                $pdo->prepare("INSERT INTO guarantors (customer_id,name,cnic,phone,address) VALUES (?,?,?,?,?)")
                    ->execute([$id,trim($gName),trim($gCnic),trim($gPhone),trim($gAddr)]);
            }
        }
        logActivity("Updated customer: $name",'customer',$id);
        setFlash('success',"Customer updated!"); header('Location:'.BASE_URL.'/modules/customers/view.php?id='.$id); exit;
    }
} else {
    $_POST = $customer;
}

$pageTitle = 'Edit Customer'; $activePage = 'customers';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">✏️ Edit Customer</h1></div>
  <a href="<?= BASE_URL ?>/modules/customers/view.php?id=<?= $id ?>" class="btn btn-outline">← Back</a>
</div>
<?php if($errors):?><div class="flash flash-error" style="margin-bottom:20px">❌ <?=implode('<br>',array_map('e',$errors))?></div><?php endif;?>
<form method="POST">
  <?= csrfField() ?>
  <div class="card section-gap">
    <div class="card-title" style="margin-bottom:20px">👤 Customer Information</div>
    <div class="form-grid form-grid-2">
      <div class="form-group"><label>Full Name *</label><input type="text" name="name" value="<?=e($_POST['name']??'')?>" required></div>
      <div class="form-group"><label>CNIC *</label><input type="text" name="cnic" value="<?=e($_POST['cnic']??'')?>" required></div>
      <div class="form-group"><label>Phone *</label><input type="tel" name="phone" value="<?=e($_POST['phone']??'')?>"></div>
      <div class="form-group full"><label>Address</label><textarea name="address" rows="2"><?=e($_POST['address']??'')?></textarea></div>
    </div>
  </div>
  <?php for($g=1;$g<=2;$g++): $gu=$guarantors[$g-1]??[]; ?>
  <div class="card section-gap">
    <div class="card-title" style="margin-bottom:20px">🛡️ Guarantor <?=$g?></div>
    <div class="form-grid form-grid-2">
      <div class="form-group"><label>Name</label><input type="text" name="g<?=$g?>_name" value="<?=e($_POST["g{$g}_name"]??$gu['name']??'')?>"></div>
      <div class="form-group"><label>CNIC</label><input type="text" name="g<?=$g?>_cnic" value="<?=e($_POST["g{$g}_cnic"]??$gu['cnic']??'')?>"></div>
      <div class="form-group"><label>Phone</label><input type="tel" name="g<?=$g?>_phone" value="<?=e($_POST["g{$g}_phone"]??$gu['phone']??'')?>"></div>
      <div class="form-group"><label>Address</label><input type="text" name="g<?=$g?>_address" value="<?=e($_POST["g{$g}_address"]??$gu['address']??'')?>"></div>
    </div>
  </div>
  <?php endfor; ?>
  <div class="form-actions"><button type="submit" class="btn btn-primary">💾 Update Customer</button><a href="<?=BASE_URL?>/modules/customers/view.php?id=<?=$id?>" class="btn btn-outline">Cancel</a></div>
</form>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

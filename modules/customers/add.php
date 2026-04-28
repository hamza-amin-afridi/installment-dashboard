<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB(); $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = trim($_POST['name']    ?? '');
    $cnic    = trim($_POST['cnic']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$name)    $errors[] = 'Customer name is required.';
    if (!$cnic)    $errors[] = 'CNIC is required.';
    if (!$phone)   $errors[] = 'Phone is required.';
    if (!$address) $errors[] = 'Address is required.';

    // Check CNIC unique
    if ($cnic) {
        $dup = $pdo->prepare("SELECT id FROM customers WHERE cnic=?");
        $dup->execute([$cnic]);
        if ($dup->fetch()) $errors[] = 'A customer with this CNIC already exists.';
    }

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO customers (name,cnic,phone,address) VALUES (?,?,?,?)")
            ->execute([$name,$cnic,$phone,$address]);
        $custId = (int)$pdo->lastInsertId();

        // Guarantors
        for ($g = 1; $g <= 2; $g++) {
            $gName    = trim($_POST["g{$g}_name"]    ?? '');
            $gCnic    = trim($_POST["g{$g}_cnic"]    ?? '');
            $gPhone   = trim($_POST["g{$g}_phone"]   ?? '');
            $gAddress = trim($_POST["g{$g}_address"] ?? '');
            if ($gName && $gCnic && $gPhone) {
                $pdo->prepare("INSERT INTO guarantors (customer_id,name,cnic,phone,address) VALUES (?,?,?,?,?)")
                    ->execute([$custId,$gName,$gCnic,$gPhone,$gAddress]);
            }
        }

        logActivity("Added customer: $name", 'customer', $custId);
        setFlash('success', "Customer '$name' added successfully!");
        header('Location: '.BASE_URL.'/modules/customers/view.php?id='.$custId); exit;
    }
}

$pageTitle = 'Add Customer'; $activePage = 'customers';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">👤 Add Customer</h1></div>
  <a href="<?= BASE_URL ?>/modules/customers/index.php" class="btn btn-outline">← Back</a>
</div>

<?php if ($errors): ?><div class="flash flash-error" style="margin-bottom:20px">❌ <?= implode('<br>',array_map('e',$errors)) ?></div><?php endif; ?>

<form method="POST">
  <?= csrfField() ?>
  <div class="card section-gap">
    <div class="card-title" style="margin-bottom:20px">👤 Customer Information</div>
    <div class="form-grid form-grid-2">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="name" placeholder="Muhammad Ali" value="<?= e($_POST['name']??'') ?>" required>
      </div>
      <div class="form-group">
        <label>CNIC *</label>
        <input type="text" name="cnic" placeholder="42101-1234567-8" maxlength="15" value="<?= e($_POST['cnic']??'') ?>" required>
      </div>
      <div class="form-group">
        <label>Phone *</label>
        <input type="tel" name="phone" placeholder="0300-1234567" value="<?= e($_POST['phone']??'') ?>" required>
      </div>
      <div class="form-group full">
        <label>Address *</label>
        <textarea name="address" rows="2" placeholder="Full postal address"><?= e($_POST['address']??'') ?></textarea>
      </div>
    </div>
  </div>

  <!-- Guarantors -->
  <?php for ($g = 1; $g <= 2; $g++): ?>
  <div class="card section-gap">
    <div class="card-title" style="margin-bottom:20px">🛡️ Guarantor <?= $g ?> <small style="color:var(--text-faint);font-weight:400">(Optional)</small></div>
    <div class="form-grid form-grid-2">
      <div class="form-group">
        <label>Guarantor <?= $g ?> Name</label>
        <input type="text" name="g<?= $g ?>_name" placeholder="Full Name" value="<?= e($_POST["g{$g}_name"]??'') ?>">
      </div>
      <div class="form-group">
        <label>CNIC</label>
        <input type="text" name="g<?= $g ?>_cnic" placeholder="42101-1234567-8" maxlength="15" value="<?= e($_POST["g{$g}_cnic"]??'') ?>">
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="tel" name="g<?= $g ?>_phone" placeholder="0300-1234567" value="<?= e($_POST["g{$g}_phone"]??'') ?>">
      </div>
      <div class="form-group">
        <label>Address</label>
        <input type="text" name="g<?= $g ?>_address" placeholder="Address" value="<?= e($_POST["g{$g}_address"]??'') ?>">
      </div>
    </div>
  </div>
  <?php endfor; ?>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">💾 Save Customer</button>
    <a href="<?= BASE_URL ?>/modules/customers/index.php" class="btn btn-outline">Cancel</a>
  </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
$moto = $pdo->prepare("SELECT * FROM motorcycles WHERE id=?");
$moto->execute([$id]); $moto = $moto->fetch();
if (!$moto) { setFlash('error','Motorcycle not found.'); header('Location: '.BASE_URL.'/modules/motorcycles/index.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name   = trim($_POST['name']   ?? '');
    $model  = trim($_POST['model']  ?? '');
    $price  = (float)($_POST['price'] ?? 0);
    $status = $_POST['status'] ?? 'available';

    if (!$name)  $errors[] = 'Name is required.';
    if (!$model) $errors[] = 'Model is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';

    $imagePath = $moto['image_path'];
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) { $errors[] = 'Invalid image type.'; }
        elseif ($_FILES['image']['size'] > 5*1024*1024) { $errors[] = 'Image too large.'; }
        else {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $filename = 'moto_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $filename)) {
                // Delete old image
                if ($moto['image_path'] && file_exists(UPLOAD_DIR . $moto['image_path']))
                    @unlink(UPLOAD_DIR . $moto['image_path']);
                $imagePath = $filename;
            }
        }
    }

    if (empty($errors)) {
        $pdo->prepare("UPDATE motorcycles SET name=?,model=?,price=?,image_path=?,status=? WHERE id=?")
            ->execute([$name,$model,$price,$imagePath,$status,$id]);
        logActivity("Updated motorcycle: $name", 'motorcycle', $id);
        setFlash('success', "Motorcycle updated successfully!");
        header('Location: ' . BASE_URL . '/modules/motorcycles/index.php'); exit;
    }
} else {
    $_POST = $moto; // Pre-fill form
}

$pageTitle = 'Edit Motorcycle'; $activePage = 'motorcycles';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">✏️ Edit Motorcycle</h1></div>
  <a href="<?= BASE_URL ?>/modules/motorcycles/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="card" style="max-width:640px">
  <?php if ($errors): ?><div class="flash flash-error">❌ <?= implode('<br>', array_map('e', $errors)) ?></div><br><?php endif; ?>
  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <div class="form-grid form-grid-2">
      <div class="form-group">
        <label>Motorcycle Name *</label>
        <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Model / Year *</label>
        <input type="text" name="model" value="<?= e($_POST['model'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Price (PKR) *</label>
        <input type="number" name="price" min="0" step="0.01" value="<?= e($_POST['price'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <?php foreach (['available','leased','retired'] as $s): ?>
          <option value="<?= $s ?>" <?= (($_POST['status']??'') === $s)?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group full">
        <label>Current Image</label>
        <?php if ($moto['image_path'] && file_exists(UPLOAD_DIR . $moto['image_path'])): ?>
          <img src="<?= BASE_URL ?>/<?= UPLOAD_URL . e($moto['image_path']) ?>" style="height:80px;border-radius:8px;border:1px solid var(--border)">
        <?php else: ?>
          <p style="color:var(--text-faint);font-size:13px">No image uploaded.</p>
        <?php endif; ?>
      </div>
      <div class="form-group full">
        <label>Replace Image (optional)</label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">💾 Update Motorcycle</button>
      <a href="<?= BASE_URL ?>/modules/motorcycles/index.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

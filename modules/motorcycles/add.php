<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pdo = getDB(); $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name   = trim($_POST['name']   ?? '');
    $model  = trim($_POST['model']  ?? '');
    $price  = (float)($_POST['price'] ?? 0);
    $status = $_POST['status'] ?? 'available';

    if (!$name)  $errors[] = 'Name is required.';
    if (!$model) $errors[] = 'Model is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';

    // Image upload
    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Image must be JPG, PNG, or WebP.';
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image must be under 5MB.';
        } else {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $filename  = 'moto_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $filename)) {
                $imagePath = $filename;
            } else {
                $errors[] = 'Failed to upload image.';
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO motorcycles (name, model, price, image_path, status) VALUES (?,?,?,?,?)");
        $stmt->execute([$name, $model, $price, $imagePath, $status]);
        logActivity("Added motorcycle: $name", 'motorcycle', (int)$pdo->lastInsertId());
        setFlash('success', "Motorcycle '$name' added successfully!");
        header('Location: ' . BASE_URL . '/modules/motorcycles/index.php');
        exit;
    }
}

$pageTitle = 'Add Motorcycle'; $activePage = 'motorcycles';
include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">🏍️ Add Motorcycle</h1></div>
  <a href="<?= BASE_URL ?>/modules/motorcycles/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="card" style="max-width:640px">
  <?php if ($errors): ?>
    <div class="flash flash-error">❌ <?= implode('<br>', array_map('e', $errors)) ?></div><br>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    <div class="form-grid form-grid-2">
      <div class="form-group">
        <label for="name">Motorcycle Name *</label>
        <input type="text" id="name" name="name" placeholder="e.g. Honda CD70" value="<?= e($_POST['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="model">Model / Year *</label>
        <input type="text" id="model" name="model" placeholder="e.g. 2024" value="<?= e($_POST['model'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="price">Price (PKR) *</label>
        <input type="number" id="price" name="price" min="0" step="0.01" placeholder="185000" value="<?= e($_POST['price'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="available" <?= ($_POST['status'] ?? '') === 'available' ? 'selected' : '' ?>>Available</option>
          <option value="leased"    <?= ($_POST['status'] ?? '') === 'leased'    ? 'selected' : '' ?>>Leased</option>
          <option value="retired"   <?= ($_POST['status'] ?? '') === 'retired'   ? 'selected' : '' ?>>Retired</option>
        </select>
      </div>
      <div class="form-group full">
        <label for="image">Image (JPG/PNG/WebP, max 5MB)</label>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-primary">💾 Save Motorcycle</button>
      <a href="<?= BASE_URL ?>/modules/motorcycles/index.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

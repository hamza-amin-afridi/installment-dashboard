<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pdo = getDB();
$pageTitle = 'Motorcycles'; $activePage = 'motorcycles';

// Search & pagination
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where  = $search ? "WHERE (name LIKE ? OR model LIKE ?)" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$total = $pdo->prepare("SELECT COUNT(*) FROM motorcycles $where");
$total->execute($params);
$pagInfo = paginate((int)$total->fetchColumn(), $perPage, $page);

$stmt = $pdo->prepare("SELECT * FROM motorcycles $where ORDER BY created_at DESC LIMIT $perPage OFFSET {$pagInfo['offset']}");
$stmt->execute($params);
$motos = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">🏍️ Motorcycles</h1><p class="page-subtitle">Manage your motorcycle inventory</p></div>
  <a href="<?= BASE_URL ?>/modules/motorcycles/add.php" class="btn btn-primary">+ Add Motorcycle</a>
</div>

<div class="filter-bar">
  <div class="search-input-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="searchInput" placeholder="Search by name or model…" value="<?= e($search) ?>"
           onchange="window.location.href='?q='+encodeURIComponent(this.value)">
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table id="motoTable">
      <thead><tr><th>#</th><th>Image</th><th>Name</th><th>Model</th><th>Price</th><th>Status</th><th>Added</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($motos)): ?>
          <tr><td colspan="8"><div class="empty-state"><div class="empty-state-icon">🏍️</div><h3>No motorcycles found</h3></div></td></tr>
        <?php else: foreach ($motos as $i => $m): ?>
        <tr>
          <td><?= $pagInfo['offset'] + $i + 1 ?></td>
          <td>
            <?php if ($m['image_path'] && file_exists(UPLOAD_DIR . basename($m['image_path']))): ?>
              <img src="<?= BASE_URL ?>/<?= UPLOAD_URL . e(basename($m['image_path'])) ?>" class="moto-img" alt="<?= e($m['name']) ?>">
            <?php else: ?>
              <div class="moto-img-placeholder">🏍️</div>
            <?php endif; ?>
          </td>
          <td><strong><?= e($m['name']) ?></strong></td>
          <td><?= e($m['model']) ?></td>
          <td><strong><?= formatCurrency($m['price']) ?></strong></td>
          <td>
            <?php
            $badge = ['available'=>'badge-success','leased'=>'badge-accent','retired'=>'badge-muted'];
            echo '<span class="badge '.($badge[$m['status']] ?? 'badge-muted').'">'.ucfirst(e($m['status'])).'</span>';
            ?>
          </td>
          <td><?= date('d M Y', strtotime($m['created_at'])) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/motorcycles/edit.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline">✏️ Edit</a>
            <a href="<?= BASE_URL ?>/modules/motorcycles/delete.php?id=<?= $m['id'] ?>"
               class="btn btn-sm btn-danger"
               data-confirm="Delete '<?= e($m['name']) ?>'? This cannot be undone.">🗑️</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($pagInfo, '?q=' . urlencode($search)) ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pdo = getDB();
$pageTitle = 'Leasing Plans'; $activePage = 'plans';
$plans = $pdo->query("SELECT * FROM leasing_plans ORDER BY duration_months ASC")->fetchAll();

include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">📋 Leasing Plans</h1><p class="page-subtitle">Manage installment durations and markup percentages</p></div>
  <a href="<?= BASE_URL ?>/modules/plans/add.php" class="btn btn-primary">+ Add Plan</a>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>#</th><th>Plan Name</th><th>Duration</th><th>Markup %</th><th>Example (₨185,000 bike)</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($plans)): ?>
          <tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">📋</div><h3>No plans yet</h3></div></td></tr>
        <?php else: foreach ($plans as $i => $p):
          $ex = calcLeaseTotals(185000, $p['markup_percent'], $p['duration_months']);
        ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><strong><?= e($p['name']) ?></strong></td>
          <td><span class="badge badge-accent"><?= $p['duration_months'] ?> months</span></td>
          <td><span class="badge badge-warning"><?= $p['markup_percent'] ?>%</span></td>
          <td>
            <small>Total: <strong><?= formatCurrency($ex['total_amount']) ?></strong> | Monthly: <strong><?= formatCurrency($ex['monthly_install']) ?></strong></small>
          </td>
          <td>
            <?php if ($p['is_active']): ?>
              <span class="badge badge-success">Active</span>
            <?php else: ?>
              <span class="badge badge-muted">Inactive</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/modules/plans/edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline">✏️ Edit</a>
            <a href="<?= BASE_URL ?>/modules/plans/delete.php?id=<?= $p['id'] ?>"
               class="btn btn-sm btn-danger"
               data-confirm="Delete plan '<?= e($p['name']) ?>'?">🗑️</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

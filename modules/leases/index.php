<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB();
$pageTitle = 'Leases'; $activePage = 'leases';

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$whereClauses = [];
$params = [];

if ($search) {
    $whereClauses[] = "(c.name LIKE ? OR c.cnic LIKE ? OR m.name LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
if ($filter === 'overdue') {
    $whereClauses[] = "l.status='active' AND l.next_due_date < CURDATE()";
} elseif ($filter === 'active') {
    $whereClauses[] = "l.status='active'";
} elseif ($filter === 'completed') {
    $whereClauses[] = "l.status='completed'";
}

$whereSQL = $whereClauses ? 'WHERE '.implode(' AND ',$whereClauses) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM leases l JOIN customers c ON l.customer_id=c.id JOIN motorcycles m ON l.motorcycle_id=m.id $whereSQL");
$total->execute($params);
$pagInfo = paginate((int)$total->fetchColumn(), $perPage, $page);

$stmt = $pdo->prepare("
    SELECT l.*, c.name AS customer_name, c.cnic, m.name AS moto_name, lp.name AS plan_name, lp.duration_months
    FROM leases l
    JOIN customers c  ON l.customer_id=c.id
    JOIN motorcycles m ON l.motorcycle_id=m.id
    JOIN leasing_plans lp ON l.plan_id=lp.id
    $whereSQL
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET {$pagInfo['offset']}
");
$stmt->execute($params); $leases = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">📄 Leases</h1><p class="page-subtitle">All motorcycle leasing agreements</p></div>
  <a href="<?= BASE_URL ?>/modules/leases/add.php" class="btn btn-primary">+ New Lease</a>
</div>

<div class="filter-bar">
  <div class="search-input-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" placeholder="Search customer, CNIC, motorcycle…" value="<?= e($search) ?>"
           onchange="window.location.href='?q='+encodeURIComponent(this.value)+'&filter=<?= urlencode($filter) ?>'">
  </div>
  <a href="?" class="btn btn-sm <?= !$filter?'btn-primary':'btn-outline' ?>">All</a>
  <a href="?filter=active"    class="btn btn-sm <?= $filter==='active'   ?'btn-accent':'btn-outline' ?>">🔵 Active</a>
  <a href="?filter=overdue"   class="btn btn-sm <?= $filter==='overdue'  ?'btn-danger':'btn-outline' ?>">🔴 Overdue</a>
  <a href="?filter=completed" class="btn btn-sm <?= $filter==='completed'?'btn-success':'btn-outline' ?>">✅ Completed</a>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>#</th><th>Customer</th><th>Motorcycle</th><th>Plan</th><th>Total</th><th>Paid</th><th>Balance</th><th>Next Due</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($leases)):?>
          <tr><td colspan="10"><div class="empty-state"><div class="empty-state-icon">📄</div><h3>No leases found</h3></div></td></tr>
        <?php else: foreach($leases as $i=>$l):
          $bal = remainingBalance($l['total_amount'],$l['paid_amount']);
          $overdue = $l['status']==='active' && isOverdue($l['next_due_date']);
          $badges=['active'=>'badge-accent','completed'=>'badge-success','defaulted'=>'badge-danger'];
        ?>
        <tr class="<?= $overdue?'overdue-row':'' ?>">
          <td><?= $pagInfo['offset']+$i+1 ?></td>
          <td><a href="<?=BASE_URL?>/modules/customers/view.php?id=<?=$l['customer_id']?>" style="color:var(--accent);font-weight:600;text-decoration:none"><?=e($l['customer_name'])?></a></td>
          <td><?=e($l['moto_name'])?></td>
          <td><span class="badge badge-muted"><?=e($l['plan_name'])?></span></td>
          <td><?=formatCurrency($l['total_amount'])?></td>
          <td><?=formatCurrency($l['paid_amount'])?></td>
          <td><?= $bal>0 ? '<strong style="color:var(--warning)">'.formatCurrency($bal).'</strong>' : '<span style="color:var(--success)">Cleared</span>' ?></td>
          <td><?= $overdue ? '<span style="color:var(--danger)">'.date('d M Y',strtotime($l['next_due_date'])).' ⚠️</span>' : date('d M Y',strtotime($l['next_due_date'])) ?></td>
          <td><span class="badge <?=$badges[$l['status']]??'badge-muted'?>"><?=ucfirst($l['status'])?></span></td>
          <td style="white-space:nowrap">
            <a href="<?=BASE_URL?>/modules/leases/view.php?id=<?=$l['id']?>" class="btn btn-sm btn-outline">View</a>
            <?php if($l['status']==='active'): ?>
            <a href="<?=BASE_URL?>/modules/payments/add.php?lease_id=<?=$l['id']?>" class="btn btn-sm btn-success">+ Pay</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($pagInfo,'?q='.urlencode($search).'&filter='.urlencode($filter)) ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

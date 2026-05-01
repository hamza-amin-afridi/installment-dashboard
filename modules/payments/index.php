<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB();
$pageTitle = 'Payments'; $activePage = 'payments';

$search = trim($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page']??1)); $perPage = 25;

$where  = $search ? "WHERE (c.name LIKE ? OR p.receipt_no LIKE ?)" : '';
$params = $search ? ["%$search%","%$search%"] : [];

$total = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN leases l ON p.lease_id=l.id JOIN customers c ON l.customer_id=c.id $where");
$total->execute($params);
$pagInfo = paginate((int)$total->fetchColumn(),$perPage,$page);

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS customer_name, c.id AS customer_id, m.name AS moto_name, l.id AS lease_id
    FROM payments p
    JOIN leases l ON p.lease_id=l.id
    JOIN customers c ON l.customer_id=c.id
    JOIN motorcycles m ON l.motorcycle_id=m.id
    $where
    ORDER BY p.payment_date DESC, p.created_at DESC
    LIMIT $perPage OFFSET {$pagInfo['offset']}
");
$stmt->execute($params); $payments = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">💰 Payments</h1><p class="page-subtitle">Full global payment history</p></div>
  <a href="<?= BASE_URL ?>/modules/payments/add.php" class="btn btn-primary">+ Record Payment</a>
</div>

<div class="filter-bar">
  <div class="search-input-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" placeholder="Search customer or receipt no…" value="<?= e($search) ?>"
           onchange="window.location.href='?q='+encodeURIComponent(this.value)">
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead><tr><th>#</th><th>Receipt</th><th>Customer</th><th>Motorcycle</th><th>Amount</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if(empty($payments)):?>
          <tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">💰</div><h3>No payments yet</h3></div></td></tr>
        <?php else: foreach($payments as $i=>$p):?>
        <tr>
          <td><?= $pagInfo['offset']+$i+1 ?></td>
          <td><code><?= e($p['receipt_no']) ?></code></td>
          <td><a href="<?=BASE_URL?>/modules/customers/view.php?id=<?=$p['customer_id']?>" style="color:var(--accent);font-weight:600;text-decoration:none"><?=e($p['customer_name'])?></a></td>
          <td><?=e($p['moto_name'])?></td>
          <td><strong style="color:var(--success);font-size:15px"><?=formatCurrency($p['amount'])?></strong></td>
          <td><?=date('d M Y',strtotime($p['payment_date']))?></td>
          <td>
            <a href="<?=BASE_URL?>/modules/receipts/view.php?payment_id=<?=$p['id']?>" class="btn btn-sm btn-outline">🧾 Receipt</a>
            <a href="<?=BASE_URL?>/modules/leases/view.php?id=<?=$p['lease_id']?>" class="btn btn-sm btn-outline">📄 Lease</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= renderPagination($pagInfo,'?q='.urlencode($search)) ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

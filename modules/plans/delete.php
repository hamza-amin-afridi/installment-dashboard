<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB(); $id = (int)($_GET['id'] ?? 0);
$plan = $pdo->prepare("SELECT * FROM leasing_plans WHERE id=?");
$plan->execute([$id]); $plan = $plan->fetch();
if (!$plan) { setFlash('error','Plan not found.'); header('Location:'.BASE_URL.'/modules/plans/index.php'); exit; }

$inUse = $pdo->prepare("SELECT COUNT(*) FROM leases WHERE plan_id=? AND status='active'");
$inUse->execute([$id]);
if ($inUse->fetchColumn() > 0) {
    setFlash('error','Cannot delete — plan is used by active leases.');
    header('Location:'.BASE_URL.'/modules/plans/index.php'); exit;
}
$pdo->prepare("DELETE FROM leasing_plans WHERE id=?")->execute([$id]);
logActivity("Deleted plan: {$plan['name']}",'plan',$id);
setFlash('success',"Plan deleted."); header('Location:'.BASE_URL.'/modules/plans/index.php'); exit;

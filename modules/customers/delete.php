<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
$pdo = getDB(); $id = (int)($_GET['id'] ?? 0);
$c = $pdo->prepare("SELECT * FROM customers WHERE id=?"); $c->execute([$id]); $c = $c->fetch();
if (!$c) { setFlash('error','Not found.'); header('Location:'.BASE_URL.'/modules/customers/index.php'); exit; }
// Cascade delete (guarantors auto-cascade via FK)
$pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);
logActivity("Deleted customer: {$c['name']}",'customer',$id);
setFlash('success',"Customer '{$c['name']}' deleted."); header('Location:'.BASE_URL.'/modules/customers/index.php'); exit;

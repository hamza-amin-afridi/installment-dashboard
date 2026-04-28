<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM motorcycles WHERE id=?");
$stmt->execute([$id]); $moto = $stmt->fetch();

if (!$moto) { setFlash('error','Motorcycle not found.'); header('Location:'.BASE_URL.'/modules/motorcycles/index.php'); exit; }

// Check if in use by active lease
$inUse = $pdo->prepare("SELECT COUNT(*) FROM leases WHERE motorcycle_id=? AND status='active'");
$inUse->execute([$id]);
if ($inUse->fetchColumn() > 0) {
    setFlash('error','Cannot delete — this motorcycle has an active lease.');
    header('Location:'.BASE_URL.'/modules/motorcycles/index.php'); exit;
}

// Delete image
if ($moto['image_path'] && file_exists(UPLOAD_DIR . $moto['image_path']))
    @unlink(UPLOAD_DIR . $moto['image_path']);

$pdo->prepare("DELETE FROM motorcycles WHERE id=?")->execute([$id]);
logActivity("Deleted motorcycle: {$moto['name']}", 'motorcycle', $id);
setFlash('success', "Motorcycle '{$moto['name']}' deleted.");
header('Location:'.BASE_URL.'/modules/motorcycles/index.php');
exit;

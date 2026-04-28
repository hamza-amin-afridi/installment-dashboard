<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (isLoggedIn()) {
    logActivity('Admin logged out', 'user', currentUser()['id']);
}
session_destroy();
header('Location: ' . BASE_URL . '/modules/auth/login.php');
exit;

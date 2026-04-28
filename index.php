<?php
/**
 * Entry Point — redirects to dashboard or login
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
}
exit;

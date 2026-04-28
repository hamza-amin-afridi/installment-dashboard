<?php
/**
 * Authentication Helpers
 * Manages sessions, CSRF tokens, and login guards.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 7200,      // 2 hours
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// -------------------------------------------------------
// Require login — redirect if not authenticated
// -------------------------------------------------------
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/modules/auth/login.php');
        exit;
    }
}

// -------------------------------------------------------
// Check if user is logged in
// -------------------------------------------------------
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

// -------------------------------------------------------
// Get current logged-in user info
// -------------------------------------------------------
function currentUser(): array {
    return [
        'id'   => $_SESSION['user_id']   ?? 0,
        'name' => $_SESSION['user_name'] ?? 'Guest',
        'role' => $_SESSION['user_role'] ?? 'staff',
    ];
}

// -------------------------------------------------------
// CSRF Token — generate & store in session
// -------------------------------------------------------
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('<p style="color:red;font-family:sans-serif;padding:2rem">⛔ CSRF token mismatch. Please go back and try again.</p>');
    }
}

// -------------------------------------------------------
// Flash messages
// -------------------------------------------------------
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function renderFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $icons = ['success' => '✅', 'error' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️'];
    $icon  = $icons[$flash['type']] ?? 'ℹ️';
    return sprintf(
        '<div class="flash flash-%s" id="flashMsg">%s %s</div>',
        htmlspecialchars($flash['type']),
        $icon,
        htmlspecialchars($flash['message'])
    );
}

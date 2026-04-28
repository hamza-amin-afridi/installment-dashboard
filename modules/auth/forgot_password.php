<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

if (isLoggedIn()) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$msg = ''; $msgType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please enter a valid email address.'; $msgType = 'error';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare("UPDATE users SET reset_token=?, reset_expiry=? WHERE id=?")
                ->execute([$token, $expiry, $user['id']]);

            $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $resetLink = $protocol . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/modules/auth/reset_password.php?token=' . $token;
            $body      = buildResetEmail($user['name'], $resetLink);
            sendMail($email, APP_NAME . ' — Password Reset', $body);
        }
        // Always show success to prevent email enumeration
        $msg = 'If that email exists in our system, a reset link has been sent.';
        $msgType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body>
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('ml_theme')||'dark');</script>
<div class="login-page">
  <div class="login-card fade-in">
    <div class="login-logo">
      <span class="logo-icon">🔑</span>
      <h1>Forgot Password</h1>
      <p>Enter your email to receive a reset link</p>
    </div>

    <?php if ($msg): ?>
      <div class="flash flash-<?= $msgType ?>"><?= e($msg) ?></div><br>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrfField() ?>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="admin@example.com" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:20px;">
        📧 Send Reset Link
      </button>
    </form>
    <div class="login-footer"><a href="<?= BASE_URL ?>/modules/auth/login.php">← Back to Login</a></div>
  </div>
</div>
</body>
</html>

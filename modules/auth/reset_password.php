<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$token  = trim($_GET['token'] ?? '');
$error  = ''; $success = false;
$pdo    = getDB();

// Validate token
$user = null;
if ($token) {
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE reset_token=? AND reset_expiry > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
}

if (!$user) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    verifyCsrf();
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    if (strlen($pass1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pass1, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash=?, reset_token=NULL, reset_expiry=NULL WHERE id=?")
            ->execute([$hash, $user['id']]);
        logActivity('Password reset', 'user', $user['id']);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body>
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('ml_theme')||'dark');</script>
<div class="login-page">
  <div class="login-card fade-in">
    <div class="login-logo">
      <span class="logo-icon">🔒</span>
      <h1>Reset Password</h1>
    </div>

    <?php if ($success): ?>
      <div class="flash flash-success">✅ Password updated successfully!</div>
      <br>
      <a href="<?= BASE_URL ?>/modules/auth/login.php" class="btn btn-primary" style="width:100%;justify-content:center;">Go to Login</a>
    <?php elseif ($error && !$user): ?>
      <div class="flash flash-error">❌ <?= e($error) ?></div>
      <br>
      <a href="<?= BASE_URL ?>/modules/auth/forgot_password.php" class="btn btn-outline" style="width:100%;justify-content:center;">Request New Link</a>
    <?php else: ?>
      <?php if ($error): ?><div class="flash flash-error">❌ <?= e($error) ?></div><br><?php endif; ?>
      <form method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-group">
          <label for="password">New Password</label>
          <input type="password" id="password" name="password" placeholder="Minimum 8 characters" required>
        </div>
        <div class="form-group" style="margin-top:16px">
          <label for="password_confirm">Confirm Password</label>
          <input type="password" id="password_confirm" name="password_confirm" placeholder="Repeat password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:20px;">🔒 Set New Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

<?php
/**
 * Login Page
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../../includes/auth.php';

if (isLoggedIn()) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter email and password.';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Update last login
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            // Log activity
            require_once __DIR__ . '/../../includes/functions.php';
            logActivity('Admin logged in', 'user', $user['id']);

            setFlash('success', 'Welcome back, ' . $user['name'] . '!');
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<style>
  .particles { position: fixed; inset: 0; pointer-events: none; overflow: hidden; }
  .particle { position: absolute; border-radius: 50%; background: rgba(108,99,255,0.12); animation: float linear infinite; }
  @keyframes float {
    0%   { transform: translateY(100vh) rotate(0deg); opacity: 0; }
    10%  { opacity: 1; }
    90%  { opacity: 1; }
    100% { transform: translateY(-100px) rotate(720deg); opacity: 0; }
  }
  .login-bg { position: fixed; inset: 0; background: radial-gradient(ellipse at 30% 50%, rgba(108,99,255,0.08) 0%, transparent 60%), radial-gradient(ellipse at 70% 20%, rgba(46,213,115,0.05) 0%, transparent 50%); }
</style>
</head>
<body>
<div class="login-bg"></div>
<div class="particles" id="particles"></div>

<div class="login-page">
  <div class="login-card fade-in">
    <div class="login-logo">
      <span class="logo-icon">🏍️</span>
      <h1><?= APP_NAME ?></h1>
      <p>Motorcycle Leasing Management Portal</p>
    </div>

    <?php if ($error): ?>
      <div class="flash flash-error">❌ <?= e($error) ?></div>
      <br>
    <?php endif; ?>

    <form method="POST" action="">
      <?= csrfField() ?>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="admin@example.com"
               value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
      </div>
      <div class="form-group" style="margin-top:16px">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
      </div>
      <div style="margin-top:8px; text-align:right; margin-bottom:24px;">
        <a href="<?= BASE_URL ?>/modules/auth/forgot_password.php" style="font-size:12px;color:var(--accent);text-decoration:none;">Forgot password?</a>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;">
        🔐 Sign In
      </button>
    </form>

    <div class="login-footer">
      <p><?= APP_NAME ?> &copy; <?= date('Y') ?></p>
    </div>
  </div>
</div>

<script>
  // Apply saved theme
  const t = localStorage.getItem('ml_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', t);

  // Particles
  const container = document.getElementById('particles');
  for (let i = 0; i < 18; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    const size = Math.random() * 40 + 10;
    p.style.cssText = `
      width:${size}px; height:${size}px;
      left:${Math.random()*100}%;
      animation-duration:${Math.random()*12+8}s;
      animation-delay:-${Math.random()*10}s;
    `;
    container.appendChild(p);
  }
</script>
</body>
</html>

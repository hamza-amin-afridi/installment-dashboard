<?php
/**
 * Login Page
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

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

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Poppins', sans-serif;
    background: #060d1a;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 20px;
  }

  .login-card {
    background: #0d1b35;
    border: 1px solid #1e3a5f;
    border-radius: 20px;
    padding: 44px 40px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.6);
  }

  .login-logo {
    text-align: center;
    margin-bottom: 32px;
  }
  .login-logo .logo-icon {
    font-size: 52px;
    display: block;
    margin-bottom: 10px;
  }
  .login-logo h1 {
    font-size: 26px;
    font-weight: 800;
    color: #3b82f6;
    margin-bottom: 4px;
  }
  .login-logo p {
    color: #94a3b8;
    font-size: 13px;
  }

  .flash-error {
    background: rgba(255,71,87,0.15);
    border: 1px solid rgba(255,71,87,0.4);
    color: #ff4757;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 13px;
    margin-bottom: 20px;
  }

  .form-group {
    margin-bottom: 20px;
  }

  label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #8892b0;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 8px;
  }

  input[type="email"],
  input[type="password"] {
    display: block;
    width: 100%;
    background: #030810;
    border: 1.5px solid #1e3a8a;
    border-radius: 8px;
    padding: 12px 16px;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    color: #e2e8f0;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  input[type="email"]:focus,
  input[type="password"]:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.2);
  }
  input::placeholder { color: #475569; }

  .forgot-row {
    text-align: right;
    margin-top: 6px;
    margin-bottom: 28px;
  }
  .forgot-row a {
    font-size: 12px;
    color: #3b82f6;
    text-decoration: none;
  }
  .forgot-row a:hover { text-decoration: underline; }

  .btn-signin {
    display: block;
    width: 100%;
    background: #1e3a8a;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    padding: 14px;
    font-family: 'Poppins', sans-serif;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
    letter-spacing: 0.3px;
  }
  .btn-signin:hover {
    background: #3b82f6;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(59,130,246,0.4);
  }
  .btn-signin:active { transform: translateY(0); }

  .login-footer {
    text-align: center;
    margin-top: 28px;
    font-size: 12px;
    color: #4a5280;
  }
</style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">
    <span class="logo-icon">🏍️</span>
    <h1><?= APP_NAME ?></h1>
    <p>Motorcycle Leasing Management Portal</p>
  </div>

  <?php if ($error): ?>
    <div class="flash-error">❌ <?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <?= csrfField() ?>

    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email"
             placeholder="admin@example.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
             required autocomplete="email">
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password"
             placeholder="••••••••"
             required autocomplete="current-password">
    </div>

    <div class="forgot-row">
      <a href="<?= BASE_URL ?>/modules/auth/forgot_password.php">Forgot password?</a>
    </div>

    <button type="submit" class="btn-signin">🔐 Sign In</button>
  </form>

  <div class="login-footer">
    <p><?= APP_NAME ?> &copy; <?= date('Y') ?></p>
  </div>
</div>
</body>
</html>

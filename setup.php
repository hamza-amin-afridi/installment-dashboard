<?php
/**
 * Quick setup helper — generates a bcrypt hash and optionally inserts a user.
 * DELETE THIS FILE after setup for security!
 *
 * Usage: http://yoursite/setup.php
 */

// Basic guard — change this token before using
define('SETUP_TOKEN', 'motolease_setup_2024');

if (($_GET['token'] ?? '') !== SETUP_TOKEN) {
    http_response_code(403);
    die('<pre style="color:red;padding:2rem;font-family:sans-serif">
⛔ Access denied.
Provide the correct token: ?token=motolease_setup_2024
⚠️  DELETE this file after setup!
</pre>');
}

require_once __DIR__ . '/config/db.php';

$message = '';
$hash    = '';

// Generate hash
if (isset($_POST['gen_hash'])) {
    $password = $_POST['password'] ?? '';
    if (strlen($password) < 6) {
        $message = '<span style="color:#ff4757">❌ Password must be at least 6 characters.</span>';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $message = '<span style="color:#2ed573">✅ Hash generated successfully!</span>';
    }
}

// Insert user
if (isset($_POST['create_user'])) {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] === 'staff' ? 'staff' : 'admin';

    if (!$name || !$email || !$password) {
        $message = '<span style="color:#ff4757">❌ All fields required.</span>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<span style="color:#ff4757">❌ Invalid email address.</span>';
    } else {
        $pdo = getDB();
        $existing = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $existing->execute([$email]);
        if ($existing->fetch()) {
            $message = '<span style="color:#ffa502">⚠️ A user with this email already exists.</span>';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?,?,?,?,1)")
                ->execute([$name, $email, $hash, $role]);
            $message = '<span style="color:#2ed573">✅ User <strong>' . htmlspecialchars($name) . '</strong> created successfully! You can now <a href="' . BASE_URL . '/modules/auth/login.php" style="color:#6c63ff">login here</a>.</span>';
        }
    }
}

// Check DB
$dbStatus = 'Unknown';
$tablesOk = false;
try {
    $pdo = getDB();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $required = ['users','motorcycles','leasing_plans','customers','guarantors','leases','payments','activity_logs'];
    $missing = array_diff($required, $tables);
    $tablesOk = empty($missing);
    $dbStatus = $tablesOk
        ? '✅ All ' . count($required) . ' tables found'
        : '❌ Missing tables: ' . implode(', ', $missing);
    $userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $dbStatus = '❌ Connection failed: ' . $e->getMessage();
    $userCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<style>
.setup-page { max-width: 700px; margin: 40px auto; padding: 0 20px; }
.setup-status { padding: 12px 16px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; }
.status-ok  { background: rgba(46,213,115,0.15); border: 1px solid rgba(46,213,115,0.3); color: #2ed573; }
.status-err { background: rgba(255,71,87,0.15);  border: 1px solid rgba(255,71,87,0.3);  color: #ff4757; }
.status-warn{ background: rgba(255,165,2,0.15);  border: 1px solid rgba(255,165,2,0.3);  color: #ffa502; }
</style>
</head>
<body>
<div class="setup-page">
  <div class="login-logo" style="text-align:center;margin-bottom:32px">
    <span class="logo-icon" style="font-size:48px;display:block">🏍️</span>
    <h1 style="font-size:24px;font-weight:800;color:var(--accent)"><?= APP_NAME ?></h1>
    <p style="color:var(--text-muted)">Setup & Configuration</p>
  </div>

  <?php if ($message): ?>
  <div class="flash flash-info" style="margin-bottom:20px"><?= $message ?></div>
  <?php endif; ?>

  <!-- DB Status -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-title" style="margin-bottom:16px">🔌 Database Status</div>
    <div class="setup-status <?= $tablesOk ? 'status-ok' : 'status-err' ?>"><?= $dbStatus ?></div>
    <?php if (!$tablesOk): ?>
    <p style="color:var(--text-muted);font-size:13px">
      Please import <code>database/schema.sql</code> in phpMyAdmin, then refresh this page.
    </p>
    <?php endif; ?>
    <div style="margin-top:12px;font-size:13px;color:var(--text-muted)">
      Existing admin users: <strong><?= $userCount ?></strong>
    </div>
  </div>

  <?php if ($tablesOk): ?>

  <!-- Create Admin User -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-title" style="margin-bottom:16px">👤 Create Admin User</div>
    <form method="POST">
      <input type="hidden" name="create_user" value="1">
      <div class="form-grid form-grid-2">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="name" placeholder="Administrator" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="admin@example.com" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Min 6 characters" required>
        </div>
        <div class="form-group">
          <label>Role</label>
          <select name="role">
            <option value="admin">Admin</option>
            <option value="staff">Staff</option>
          </select>
        </div>
      </div>
      <div style="margin-top:16px">
        <button type="submit" class="btn btn-primary">👤 Create User</button>
      </div>
    </form>
  </div>

  <!-- Hash Generator -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-title" style="margin-bottom:16px">🔑 Password Hash Generator</div>
    <form method="POST">
      <input type="hidden" name="gen_hash" value="1">
      <div style="display:flex;gap:12px;align-items:flex-end">
        <div class="form-group" style="flex:1">
          <label>Password to hash</label>
          <input type="text" name="password" placeholder="Enter password…">
        </div>
        <button type="submit" class="btn btn-outline">Generate Hash</button>
      </div>
    </form>
    <?php if ($hash): ?>
    <div style="margin-top:16px;padding:12px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
      <div style="font-size:11px;font-weight:600;color:var(--text-faint);text-transform:uppercase;margin-bottom:6px">Bcrypt Hash:</div>
      <code style="font-size:12px;word-break:break-all;color:var(--accent)"><?= htmlspecialchars($hash) ?></code>
    </div>
    <?php endif; ?>
  </div>

  <div style="text-align:center">
    <a href="<?= BASE_URL ?>/modules/auth/login.php" class="btn btn-primary">🔐 Go to Login</a>
  </div>

  <?php endif; ?>

  <div class="setup-status status-warn" style="margin-top:24px;text-align:center">
    ⚠️ <strong>IMPORTANT:</strong> Delete or rename <code>setup.php</code> after completing setup!
  </div>
</div>
</body>
</html>

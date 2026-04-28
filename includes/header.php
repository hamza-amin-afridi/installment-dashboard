<?php
/**
 * Shared Header / Sidebar Layout
 * Included on every authenticated page.
 *
 * Variables expected from including page:
 *   $pageTitle  — string, shown in <title> and top bar
 *   $activePage — string, matches sidebar link id
 */

if (!isset($pageTitle))  $pageTitle  = APP_NAME;
if (!isset($activePage)) $activePage = 'dashboard';

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
<meta name="description" content="<?= APP_NAME ?> — Motorcycle Leasing Management Portal">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
<?php if (isset($extraCss)) echo $extraCss; ?>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">🏍️</div>
    <div class="brand-text">
      <span class="brand-name"><?= APP_NAME ?></span>
      <span class="brand-version">v<?= APP_VERSION ?></span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>

    <a href="<?= BASE_URL ?>/dashboard.php"
       class="nav-link <?= $activePage==='dashboard'?'active':'' ?>" id="nav-dashboard">
      <span class="nav-icon">📊</span>
      <span class="nav-label">Dashboard</span>
    </a>

    <div class="nav-section-label">Catalog</div>

    <a href="<?= BASE_URL ?>/modules/motorcycles/index.php"
       class="nav-link <?= $activePage==='motorcycles'?'active':'' ?>" id="nav-motorcycles">
      <span class="nav-icon">🏍️</span>
      <span class="nav-label">Motorcycles</span>
    </a>

    <a href="<?= BASE_URL ?>/modules/plans/index.php"
       class="nav-link <?= $activePage==='plans'?'active':'' ?>" id="nav-plans">
      <span class="nav-icon">📋</span>
      <span class="nav-label">Leasing Plans</span>
    </a>

    <div class="nav-section-label">People</div>

    <a href="<?= BASE_URL ?>/modules/customers/index.php"
       class="nav-link <?= $activePage==='customers'?'active':'' ?>" id="nav-customers">
      <span class="nav-icon">👥</span>
      <span class="nav-label">Customers</span>
    </a>

    <a href="<?= BASE_URL ?>/modules/leases/index.php"
       class="nav-link <?= $activePage==='leases'?'active':'' ?>" id="nav-leases">
      <span class="nav-icon">📄</span>
      <span class="nav-label">Leases</span>
    </a>

    <div class="nav-section-label">Finance</div>

    <a href="<?= BASE_URL ?>/modules/payments/index.php"
       class="nav-link <?= $activePage==='payments'?'active':'' ?>" id="nav-payments">
      <span class="nav-icon">💰</span>
      <span class="nav-label">Payments</span>
    </a>

    <a href="<?= BASE_URL ?>/modules/receipts/view.php"
       class="nav-link <?= $activePage==='receipts'?'active':'' ?>" id="nav-receipts">
      <span class="nav-icon">🧾</span>
      <span class="nav-label">Receipts</span>
    </a>

    <div class="nav-section-label">Analytics</div>

    <a href="<?= BASE_URL ?>/modules/reports/index.php"
       class="nav-link <?= $activePage==='reports'?'active':'' ?>" id="nav-reports">
      <span class="nav-icon">📈</span>
      <span class="nav-label">Reports</span>
    </a>

    <a href="<?= BASE_URL ?>/modules/activity/index.php"
       class="nav-link <?= $activePage==='activity'?'active':'' ?>" id="nav-activity">
      <span class="nav-icon">📝</span>
      <span class="nav-label">Activity Log</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="user-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
      <div class="user-details">
        <span class="user-name"><?= e($user['name']) ?></span>
        <span class="user-role"><?= ucfirst(e($user['role'])) ?></span>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="logout-btn" title="Logout">⏻</a>
  </div>
</aside>

<!-- ===== MAIN WRAPPER ===== -->
<div class="main-wrapper" id="mainWrapper">

  <!-- Top Navigation Bar -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" id="sidebarToggle" title="Toggle sidebar">☰</button>
      <div class="page-breadcrumb">
        <span class="breadcrumb-root">MotoLease</span>
        <span class="breadcrumb-sep">›</span>
        <span class="breadcrumb-current"><?= e($pageTitle) ?></span>
      </div>
    </div>
    <div class="topbar-right">
      <button class="theme-toggle" id="themeToggle" title="Toggle theme">🌙</button>
      <div class="topbar-date"><?= date('d M Y') ?></div>
      <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="topbar-logout">Logout ⏻</a>
    </div>
  </header>

  <!-- Flash Message -->
  <?= renderFlash() ?>

  <!-- Page Content starts here -->
  <main class="page-content">

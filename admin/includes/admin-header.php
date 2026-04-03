<?php
/**
 * Admin Header
 * Expects $adminPageTitle to be set by the calling page.
 */
require_once __DIR__ . '/../../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($adminPageTitle ?? 'Admin', ENT_QUOTES, 'UTF-8') ?> - Admin Panel</title>
  <link rel="stylesheet" href="/assets/css/admin.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-body">
  <nav class="admin-sidebar">
    <div class="sidebar-brand">&#9917; Admin Panel</div>
    <ul class="sidebar-nav">
      <li>
        <a href="/admin/dashboard.php" class="<?= str_contains($_SERVER['REQUEST_URI'], 'dashboard') ? 'active' : '' ?>">
          <i class="fas fa-chart-bar"></i> Dashboard
        </a>
      </li>
      <li>
        <a href="/admin/import.php" class="<?= str_contains($_SERVER['REQUEST_URI'], 'import') ? 'active' : '' ?>">
          <i class="fas fa-download"></i> Import
        </a>
      </li>
      <li>
        <a href="/admin/matches.php" class="<?= str_contains($_SERVER['REQUEST_URI'], 'matches') ? 'active' : '' ?>">
          <i class="fas fa-list"></i> Matches
        </a>
      </li>
      <li>
        <a href="/admin/analytics.php" class="<?= str_contains($_SERVER['REQUEST_URI'], 'analytics') ? 'active' : '' ?>">
          <i class="fas fa-chart-line"></i> Analytics
        </a>
      </li>
      <li>
        <a href="/admin/logout.php">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </li>
    </ul>
  </nav>
  <div class="admin-main">
    <div class="admin-topbar">
      <span class="admin-topbar-title"><?= htmlspecialchars($adminPageTitle ?? '', ENT_QUOTES, 'UTF-8') ?></span>
      <span class="admin-user">
        <i class="fas fa-user"></i>
        <?= htmlspecialchars(getCurrentUser()['username'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>
    <div class="admin-content">

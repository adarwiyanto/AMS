<?php
require_once __DIR__ . '/../../helpers.php';
$settings = $settings ?? [];
$brand_title = $settings['brand_title'] ?? 'Praktek dr. Agus';
$badge = $settings['brand_badge'] ?? 'Adena Medical System';
$logo_url = $settings['logo_path'] ?? '';
$custom_css = $settings['custom_css'] ?? '';
$u = $u ?? null;
$is_dashboard = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php' && !str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/pacs/');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? $brand_title) ?></title>
  <link rel="icon" href="<?= e($logo_url ? url($logo_url) : url('/public/assets/img/ams-favicon.svg')) ?>">
  <link rel="stylesheet" href="<?= e(url('/public/assets/css/theme.css?v=20261113-preview-v2')) ?>">
  <style><?= $custom_css ?></style>
</head>
<body>
<div class="sidebar-backdrop no-print" id="sidebarBackdrop" onclick="closeSidebar()" aria-hidden="true"></div>
<div class="layout">
  <aside class="sidebar no-print" id="sidebar" aria-label="Menu utama">
    <div class="side-brand">
      <button class="icon-btn sidebar-close" type="button" onclick="closeSidebar()" aria-label="Tutup menu">×</button>
      <img src="<?= e($logo_url ? url($logo_url) : url('/public/assets/img/ams-favicon.svg')) ?>" class="logo" alt="Logo">
      <div class="side-text">
        <div class="side-title"><?= e($brand_title) ?></div>
        <div class="side-badge"><?= e($badge) ?></div>
      </div>
    </div>

    <nav class="side-nav">
      <a href="<?= e(url('/index.php')) ?>" class="<?= $is_dashboard ? 'active' : '' ?>"><span class="ams-nav-icon" aria-hidden="true">⌂</span>Dashboard</a>
      <a href="<?= e(url('/patients.php')) ?>"><span class="ams-nav-icon" aria-hidden="true">♟</span>Pasien</a>
      <a href="<?= e(url('/visits.php')) ?>"><span class="ams-nav-icon" aria-hidden="true">▣</span>Kunjungan</a>
      <a href="<?= e(url('/pacs/index.php')) ?>" target="_blank" rel="noopener noreferrer"><span class="ams-nav-icon" aria-hidden="true">▧</span>PACS</a>
      <a href="<?= e(url('/prescriptions.php')) ?>"><span class="ams-nav-icon" aria-hidden="true">✎</span>Resep</a>
      <a href="<?= e(url('/referrals.php')) ?>"><span class="ams-nav-icon" aria-hidden="true">↗</span>Rujukan</a>

      <?php if ($u && $u['role'] === 'admin'): ?>
        <div class="nav-group" data-nav-group="admin">
          <button class="nav-toggle" type="button" onclick="toggleNavGroup(this)" aria-expanded="false">
            <span>Admin</span><span class="nav-caret">▸</span>
          </button>
          <div class="nav-sub" hidden>
            <a href="<?= e(url('/users.php')) ?>">User & Role</a>
            <a href="<?= e(url('/settings.php')) ?>">Kop Surat & Theme</a>
            <a href="<?= e(url('/backup.php')) ?>">Backup DB</a>
            <a href="<?= e(url('/logs.php')) ?>">Log</a>
          </div>
        </div>
      <?php endif; ?>

      <div class="nav-group" data-nav-group="akun">
        <button class="nav-toggle" type="button" onclick="toggleNavGroup(this)" aria-expanded="false">
          <span>Akun</span><span class="nav-caret">▸</span>
        </button>
        <div class="nav-sub" hidden>
          <a href="<?= e(url('/profile.php')) ?>">Profile</a>
          <?php if ($u && $u['role'] === 'admin'): ?>
            <a href="<?= e(url('/pacs/settings.php')) ?>">Setting PACS</a>
          <?php endif; ?>
          <a href="<?= e(url('/logout.php')) ?>">Logout</a>
        </div>
      </div>
    </nav>

    <div class="side-footer">
      <button class="btn small sidebar-hide-btn" type="button" onclick="toggleSidebar()">Sembunyikan menu</button>
    </div>
  </aside>

  <main class="main">
    <header class="topbar no-print">
      <button class="icon-btn menu-toggle" type="button" onclick="toggleSidebar()" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">☰</button>
      <div class="topbar-title"><?= $is_dashboard ? 'Dashboard' : e($brand_title) ?></div>
      <div class="topbar-user"><span class="ams-user-avatar" aria-hidden="true">●</span><?= $u ? e($u['full_name']) : '' ?><span class="ams-user-caret" aria-hidden="true">⌄</span></div>
    </header>

    <div class="container">
      <?php if ($msg = flash_get('ok')): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($msg = flash_get('err')): ?><div class="alert err"><?= e($msg) ?></div><?php endif; ?>

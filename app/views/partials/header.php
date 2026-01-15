<?php
require_once __DIR__ . '/../../helpers.php';
$settings = $settings ?? [];
$brand_title = $settings['brand_title'] ?? 'Praktek dr. Agus';
$badge = $settings['brand_badge'] ?? 'Adena Medical System';
$logo_url = $settings['logo_path'] ?? '';
$custom_css = $settings['custom_css'] ?? '';
$u = $u ?? null;

$role = $u['role'] ?? '';
$is_admin = ($role === 'admin');
$is_sekretariat = ($role === 'sekretariat');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? $brand_title) ?></title>
  <link rel="stylesheet" href="<?= e(url('/public/assets/css/theme.css')) ?>">
  <style><?= $custom_css ?></style>
</head>
<body>
<div class="layout">
  <aside class="sidebar no-print" id="sidebar">
    <div class="side-brand">
      <?php if ($logo_url): ?>
        <img src="<?= e(url($logo_url)) ?>" class="logo" alt="Logo">
      <?php endif; ?>
      <div class="side-text">
        <div class="side-title"><?= e($brand_title) ?></div>
        <div class="side-badge"><?= e($badge) ?></div>
      </div>
    </div>

    <nav class="side-nav">
      <a href="<?= e(url('/index.php')) ?>">Dashboard</a>
      <a href="<?= e(url('/patients.php')) ?>">Pasien</a>
      <a href="<?= e(url('/schedule.php')) ?>">Jadwal</a>

      <?php if (!$is_sekretariat): ?>
        <a href="<?= e(url('/visits.php')) ?>">Kunjungan</a>
        <a href="<?= e(url('/prescriptions.php')) ?>">Resep</a>
      <?php endif; ?>

      <?php if ($is_admin): ?>
        <div class="nav-sep">Admin</div>
        <a href="<?= e(url('/users.php')) ?>">User & Role</a>
        <a href="<?= e(url('/settings.php')) ?>">Kop Surat & Theme</a>
        <a href="<?= e(url('/backup.php')) ?>">Backup DB</a>
        <a href="<?= e(url('/logs.php')) ?>">Log</a>
      <?php endif; ?>

      <div class="nav-sep">Akun</div>
      <a href="<?= e(url('/profile.php')) ?>">Profile</a>
      <a href="<?= e(url('/logout.php')) ?>">Logout</a>
    </nav>

    <div class="side-footer">
      <button class="btn small" type="button" onclick="toggleSidebar()">Sembunyikan menu</button>
    </div>
  </aside>

  <main class="main">
    <header class="topbar no-print">
      <button class="btn small" type="button" onclick="toggleSidebar()">☰</button>
      <div class="topbar-title"><?= e($brand_title) ?></div>
      <div class="topbar-user"><?= $u ? e($u['full_name']).' ('.e($u['role']).')' : '' ?></div>
    </header>

    <div class="container">
      <?php if ($msg = flash_get('ok')): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($msg = flash_get('err')): ?><div class="alert err"><?= e($msg) ?></div><?php endif; ?>

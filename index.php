<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();

$title = "Dashboard";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1" style="font-size:16px">Dashboard</div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn" href="<?= e(url('/patients.php')) ?>">+ Pasien</a>
    <a class="btn secondary" href="<?= e(url('/visits.php?new=1')) ?>">+ Kunjungan</a>
    <a class="btn secondary" href="<?= e(url('/backup.php')) ?>">Backup</a>
  </div>
</div>
<?php require __DIR__ . '/app/views/partials/footer.php'; ?>

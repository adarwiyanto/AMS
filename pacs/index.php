<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
$u = pacs_require_login();
$settings = get_settings();
$title = 'PACS Dashboard';

$stats = [
  'patients' => 0,
  'studies' => 0,
  'series' => 0,
  'instances' => 0,
];
$pacsReady = false;
$pacsError = '';
$pacsCfg = function_exists('pacs_config_local') ? pacs_config_local() : [];
$pacsDb = $pacsCfg['db'] ?? [];
$hasLocalConfig = file_exists(__DIR__ . '/../app/pacs_config.php');

try {
  $pdo = pacs_db();
  $stats = [
    'patients' => (int)$pdo->query('SELECT COUNT(*) FROM pacs_patients')->fetchColumn(),
    'studies' => (int)$pdo->query('SELECT COUNT(*) FROM pacs_studies')->fetchColumn(),
    'series' => (int)$pdo->query('SELECT COUNT(*) FROM pacs_series')->fetchColumn(),
    'instances' => (int)$pdo->query('SELECT COUNT(*) FROM pacs_instances')->fetchColumn(),
  ];
  $pacsReady = true;
} catch (Throwable $e) {
  $pacsError = $e->getMessage();
  log_app('error', 'PACS dashboard database not ready', [
    'err' => $pacsError,
    'db_name' => $pacsDb['name'] ?? '',
    'db_user' => $pacsDb['user'] ?? '',
    'has_local_config' => $hasLocalConfig,
  ]);
}

require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">PACS Dashboard</div>
  <div class="muted">Modul PACS dengan database terpisah, upload DICOM/ZIP, viewer internal, dan opsi bridge ke Native Adena Dicom Viewer.</div>
</div>

<?php if (!$pacsReady): ?>
  <div class="card">
    <div class="h1" style="font-size:18px">PACS belum siap</div>
    <p class="muted">Koneksi ke database PACS belum berhasil. Isi password dan test koneksi melalui menu Setting PACS.</p>
    <div class="pacs-status-grid">
      <div class="pacs-status-item"><b>Database</b><br><?= e((string)($pacsDb['name'] ?? 'adey8293_pacs')) ?></div>
      <div class="pacs-status-item"><b>User</b><br><?= e((string)($pacsDb['user'] ?? 'adey8293_adyto')) ?></div>
      <div class="pacs-status-item"><b>Config lokal</b><br><?= $hasLocalConfig ? 'Ada' : 'Belum ada' ?></div>
      <div class="pacs-status-item"><b>Password</b><br><?= (($pacsDb['pass'] ?? '') !== '') ? 'Sudah diisi' : 'Belum diisi' ?></div>
    </div>
    <?php if ($u && ($u['role'] ?? '') === 'admin'): ?>
      <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn" href="<?= e(url('/pacs/settings.php')) ?>">Buka Setting PACS</a>
        <a class="btn secondary" href="<?= e(url('/sql/adey8293_pacs_schema.sql')) ?>" target="_blank" rel="noopener noreferrer">Lihat Schema PACS</a>
      </div>
    <?php endif; ?>
    <div class="alert err" style="margin-top:14px;"><b>Error teknis:</b> <?= e($pacsError) ?></div>
  </div>
<?php else: ?>
  <div class="card">
    <div style="display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:10px;margin-top:12px;">
      <?php foreach ($stats as $label => $num): ?>
        <div class="card" style="margin:0">
          <div class="muted"><?= e(strtoupper($label)) ?></div>
          <div class="h1" style="font-size:24px"><?= e((string)$num) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card">
    <a class="btn" href="<?= e(url('/pacs/upload.php')) ?>">Upload DICOM / ZIP</a>
    <a class="btn secondary" href="<?= e(url('/pacs/studies.php')) ?>">Studies</a>
    <a class="btn secondary" href="<?= e(url('/pacs/patients.php')) ?>">Patients</a>
    <a class="btn secondary" href="<?= e(url('/pacs/studies.php')) ?>">Buka Viewer dari Study</a>
    <?php if ($u && ($u['role'] ?? '') === 'admin'): ?>
      <a class="btn secondary" href="<?= e(url('/pacs/settings.php')) ?>">Setting PACS</a>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../app/views/partials/footer.php';

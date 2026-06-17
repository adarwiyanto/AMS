<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
$u = pacs_require_login();
$settings = get_settings();

$stats = [
  'patients' => (int)pacs_db()->query('SELECT COUNT(*) FROM pacs_patients')->fetchColumn(),
  'studies' => (int)pacs_db()->query('SELECT COUNT(*) FROM pacs_studies')->fetchColumn(),
  'series' => (int)pacs_db()->query('SELECT COUNT(*) FROM pacs_series')->fetchColumn(),
  'instances' => (int)pacs_db()->query('SELECT COUNT(*) FROM pacs_instances')->fetchColumn(),
];

$title = 'PACS Dashboard';
require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">PACS Dashboard</div>
  <div class="muted">Modul PACS dengan database terpisah dan bridge ke Native Adena Dicom Viewer.</div>
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
  <a class="btn" href="<?= e(url('/pacs/upload.php')) ?>">Upload DICOM</a>
  <a class="btn secondary" href="<?= e(url('/pacs/studies.php')) ?>">Studies</a>
  <a class="btn secondary" href="<?= e(url('/pacs/patients.php')) ?>">Patients</a>
  <a class="btn secondary" href="<?= e(url('/pacs/studies.php')) ?>">Buka Native DicomViewer dari Study</a>
</div>
<?php require __DIR__ . '/../app/views/partials/footer.php';

<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
require_once __DIR__ . '/lib/pacs_audit.php';

$u = pacs_current_user_or_forbidden();
$studyUid = trim((string)($_GET['study_uid'] ?? ''));
if ($studyUid === '') {
  http_response_code(400);
  exit('StudyInstanceUID wajib diisi.');
}

$stmt = pacs_db()->prepare('SELECT s.*, p.patient_name FROM pacs_studies s LEFT JOIN pacs_patients p ON p.patient_id=s.patient_id WHERE s.study_uid = ? LIMIT 1');
$stmt->execute([$studyUid]);
$study = $stmt->fetch();
if (!$study) {
  http_response_code(404);
  exit('Study PACS tidak ditemukan.');
}

$linkStmt = pacs_db()->prepare('SELECT * FROM pacs_links WHERE study_uid = ? ORDER BY id DESC LIMIT 1');
$linkStmt->execute([$studyUid]);
$link = $linkStmt->fetch() ?: [];

pacs_audit_log((int)$u['id'], 'NATIVE_LAUNCH', $studyUid);
$nativeUrl = pacs_native_bridge_url($studyUid, [
  'patient_id' => (string)($study['patient_id'] ?? ''),
  'patient_name' => (string)($study['patient_name'] ?? ''),
  'ams_patient_id' => (string)($link['ams_patient_id'] ?? ''),
  'ams_visit_id' => (string)($link['ams_visit_id'] ?? ''),
  'pacs_api' => url('/pacs/api/studies.php'),
]);

$title = 'Buka Native DicomViewer';
$settings = get_settings();
require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Buka Native DicomViewer</div>
  <p class="muted">Browser akan mencoba membuka aplikasi desktop melalui protocol <code>adena-dicom://</code>.</p>
  <p><b>Study UID:</b> <code><?= e($studyUid) ?></code></p>
  <p><b>Pasien DICOM:</b> <?= e((string)($study['patient_name'] ?? '-')) ?> / <?= e((string)($study['patient_id'] ?? '-')) ?></p>
  <p>
    <a class="btn" href="<?= e($nativeUrl) ?>">Buka sekarang</a>
    <a class="btn secondary" href="<?= e(url('/pacs/report.php?study_uid=' . rawurlencode($studyUid))) ?>">Word Processing / Report</a>
    <a class="btn secondary" href="<?= e(url('/pacs/studies.php')) ?>">Kembali ke Studies</a>
  </p>
  <div class="alert ok">Jika aplikasi belum terbuka, pastikan Native DicomViewer sudah di-install dan protocol <code>adena-dicom://</code> sudah terdaftar di Windows.</div>
</div>
<script>
(function(){
  const url = <?= json_encode($nativeUrl, JSON_UNESCAPED_SLASHES) ?>;
  setTimeout(function(){ window.location.href = url; }, 350);
})();
</script>
<?php require __DIR__ . '/../app/views/partials/footer.php';

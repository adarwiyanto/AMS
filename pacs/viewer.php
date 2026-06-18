<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
$u = pacs_require_login();
$settings = get_settings();
$title = 'PACS DICOM Viewer';
$studyUid = trim((string)($_GET['study_uid'] ?? ''));
if ($studyUid === '') { http_response_code(400); exit('StudyInstanceUID wajib diisi.'); }
$stmt = pacs_db()->prepare('SELECT s.*, p.patient_name FROM pacs_studies s LEFT JOIN pacs_patients p ON p.patient_id=s.patient_id WHERE s.study_uid=? LIMIT 1');
$stmt->execute([$studyUid]);
$study = $stmt->fetch();
if (!$study) { http_response_code(404); exit('Study PACS tidak ditemukan.'); }
require __DIR__ . '/../app/views/partials/header.php';
?>
<?= pacs_back_button('/pacs/studies.php') ?>
<link rel="stylesheet" href="<?= e(url('/pacs/assets/pacs_viewer.css')) ?>">
<div class="pacs-viewer-shell" data-study-uid="<?= e($studyUid) ?>" data-api-url="<?= e(url('/pacs/api/viewer_study.php?study_uid=' . rawurlencode($studyUid))) ?>">
  <aside class="pacs-viewer-left">
    <div class="pacs-panel-title">Study</div>
    <div class="pacs-study-info">
      <b><?= e((string)($study['patient_name'] ?? '-')) ?></b><br>
      Patient ID: <?= e((string)($study['patient_id'] ?? '-')) ?><br>
      Date: <?= e((string)($study['study_date'] ?? '-')) ?><br>
      Modality: <?= e((string)($study['modalities'] ?? '-')) ?>
    </div>
    <div class="pacs-panel-title">Series</div>
    <div id="pacsSeriesList" class="pacs-series-list"><div class="muted">Memuat series...</div></div>
  </aside>

  <section class="pacs-viewer-main">
    <div class="pacs-toolbar no-print">
      <button class="btn small secondary" type="button" id="pacsPrevSlice">◀ Slice</button>
      <input type="range" id="pacsSliceRange" min="0" max="0" value="0">
      <button class="btn small secondary" type="button" id="pacsNextSlice">Slice ▶</button>
      <button class="btn small secondary" type="button" id="pacsFit">Fit</button>
      <button class="btn small secondary" type="button" id="pacsWlSoft">Soft</button>
      <button class="btn small secondary" type="button" id="pacsWlLung">Lung</button>
      <button class="btn small secondary" type="button" id="pacsInvert">Invert</button>
      <a class="btn small secondary" href="<?= e(url('/pacs/report.php?study_uid=' . rawurlencode($studyUid))) ?>">Report</a>
    </div>
    <div id="pacsStatus" class="pacs-status">Viewer web internal. Mendukung DICOM grayscale uncompressed little-endian.</div>
    <div class="pacs-canvas-wrap" id="pacsCanvasWrap">
      <canvas id="pacsCanvas" width="512" height="512"></canvas>
      <div class="pacs-overlay" id="pacsOverlay"></div>
    </div>
  </section>
</div>
<script src="<?= e(url('/pacs/assets/pacs_viewer.js')) ?>"></script>
<?php require __DIR__ . '/../app/views/partials/footer.php';

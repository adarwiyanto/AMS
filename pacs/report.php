<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
$u = pacs_require_login();
$settings = get_settings();
$studyUid = trim((string)($_GET['study_uid'] ?? $_POST['study_uid'] ?? ''));
if ($studyUid === '') { http_response_code(400); exit('StudyInstanceUID wajib diisi.'); }

$stmt = pacs_db()->prepare('SELECT s.*, p.patient_name FROM pacs_studies s LEFT JOIN pacs_patients p ON p.patient_id=s.patient_id WHERE s.study_uid=? LIMIT 1');
$stmt->execute([$studyUid]);
$study = $stmt->fetch();
if (!$study) { http_response_code(404); exit('Study PACS tidak ditemukan.'); }

if (is_post()) {
  csrf_validate();
  $body = (string)($_POST['report_body'] ?? '');
  $impression = (string)($_POST['impression'] ?? '');
  $status = (string)($_POST['status'] ?? 'draft');
  if (!in_array($status, ['draft','final'], true)) $status = 'draft';
  $existing = pacs_db()->prepare('SELECT id FROM pacs_reports WHERE study_uid=? ORDER BY updated_at DESC, id DESC LIMIT 1');
  $existing->execute([$studyUid]);
  $row = $existing->fetch();
  if ($row) {
    pacs_db()->prepare('UPDATE pacs_reports SET report_body=?, impression=?, status=?, updated_by=?, updated_at=? WHERE id=?')
      ->execute([$body, $impression, $status, (int)$u['id'], now_dt(), (int)$row['id']]);
  } else {
    pacs_db()->prepare('INSERT INTO pacs_reports(study_uid, report_title, report_body, impression, status, created_by, updated_by, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?)')
      ->execute([$studyUid, 'Laporan PACS', $body, $impression, $status, (int)$u['id'], (int)$u['id'], now_dt(), now_dt()]);
  }
  flash_set('ok', 'Report PACS tersimpan.');
  redirect('/pacs/report.php?study_uid=' . rawurlencode($studyUid));
}

$rstmt = pacs_db()->prepare('SELECT * FROM pacs_reports WHERE study_uid=? ORDER BY updated_at DESC, id DESC LIMIT 1');
$rstmt->execute([$studyUid]);
$report = $rstmt->fetch() ?: ['report_body'=>'', 'impression'=>'', 'status'=>'draft'];
$title = 'Word Processing PACS';
require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Word Processing PACS</div>
  <div class="muted">Study: <code><?= e($studyUid) ?></code></div>
  <div class="muted">Pasien: <?= e((string)($study['patient_name'] ?? '-')) ?> / <?= e((string)($study['patient_id'] ?? '-')) ?></div>
</div>
<div class="card">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="study_uid" value="<?= e($studyUid) ?>">
    <label class="label">Isi Laporan</label>
    <textarea class="input" name="report_body" rows="18" style="font-family:Calibri,Arial,sans-serif;line-height:1.45"><?= e((string)$report['report_body']) ?></textarea>
    <label class="label">KESIMPULAN / Impression</label>
    <textarea class="input" name="impression" rows="5" style="font-family:Calibri,Arial,sans-serif;line-height:1.45"><?= e((string)$report['impression']) ?></textarea>
    <label class="label">Status</label>
    <select class="input" name="status">
      <option value="draft" <?= ($report['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
      <option value="final" <?= ($report['status'] ?? '') === 'final' ? 'selected' : '' ?>>Final</option>
    </select>
    <p>
      <button class="btn" type="submit">Simpan Report</button>
      <a class="btn secondary" href="<?= e(url('/pacs/launch.php?study_uid=' . rawurlencode($studyUid))) ?>">Buka Native DicomViewer</a>
      <a class="btn secondary" href="<?= e(url('/pacs/studies.php')) ?>">Studies</a>
    </p>
  </form>
</div>
<?php require __DIR__ . '/../app/views/partials/footer.php';

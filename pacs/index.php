<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';

$u = pacs_current_user_or_forbidden();
$settings = get_settings();

$studyInput = trim((string)($_GET['study_uid'] ?? ''));

$st = db()->prepare('SELECT id, patient_name, patient_id, study_date, modality, study_uid, source, created_at FROM pacs_studies WHERE user_id = ? ORDER BY created_at DESC LIMIT 30');
$st->execute([(int)$u['id']]);
$rows = $st->fetchAll();

$title = 'PACS';
require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">PACS</div>
  <form method="get" action="<?= e(url('/pacs/launch.php')) ?>" target="_blank" rel="noopener noreferrer" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <div>
      <div class="label">StudyInstanceUID</div>
      <input class="input" type="text" name="study_uid" required placeholder="1.2.840...." value="<?= e($studyInput) ?>" style="min-width:380px">
    </div>
    <button class="btn" type="submit">Buka Viewer OHIF</button>
  </form>
</div>

<div class="card" id="upload">
  <div class="h1" style="font-size:16px">Upload DICOM</div>
  <form method="post" action="<?= e(url('/pacs/upload.php')) ?>" enctype="multipart/form-data" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div>
      <div class="label">File (.dcm / .zip)</div>
      <input class="input" type="file" name="dicom_file" accept=".dcm,.zip,application/dicom,application/zip" required>
    </div>
    <button class="btn secondary" type="submit">Upload ke Orthanc</button>
  </form>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Riwayat Studi Terakhir</div>
  <table class="table">
    <thead>
      <tr>
        <th>Tanggal</th>
        <th>PatientName</th>
        <th>PatientID</th>
        <th>Modality</th>
        <th>StudyUID</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e((string)$r['created_at']) ?></td>
        <td><?= e((string)$r['patient_name']) ?></td>
        <td><?= e((string)$r['patient_id']) ?></td>
        <td><?= e((string)$r['modality']) ?></td>
        <td style="max-width:260px;word-break:break-all"><?= e((string)$r['study_uid']) ?></td>
        <td>
          <a class="btn small" href="<?= e(url('/pacs/launch.php?study_uid=' . rawurlencode((string)$r['study_uid']))) ?>" target="_blank" rel="noopener noreferrer">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
      <tr><td colspan="6" class="muted">Belum ada data.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../app/views/partials/footer.php'; ?>

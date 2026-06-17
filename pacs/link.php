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
  $amsPatientId = (int)($_POST['ams_patient_id'] ?? 0);
  $amsVisitId = (int)($_POST['ams_visit_id'] ?? 0);
  if ($amsPatientId <= 0) {
    flash_set('err', 'Pasien AMS wajib dipilih.');
  } else {
    pacs_db()->prepare('INSERT INTO pacs_links(ams_patient_id, ams_visit_id, pacs_patient_id, study_uid, linked_by, linked_at) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE ams_patient_id=VALUES(ams_patient_id), ams_visit_id=VALUES(ams_visit_id), linked_by=VALUES(linked_by), linked_at=VALUES(linked_at)')
      ->execute([$amsPatientId, $amsVisitId ?: null, (string)$study['patient_id'], $studyUid, (int)$u['id'], now_dt()]);
    pacs_db()->prepare('UPDATE pacs_studies SET ams_patient_id=?, ams_visit_id=? WHERE study_uid=?')
      ->execute([$amsPatientId, $amsVisitId ?: null, $studyUid]);
    flash_set('ok', 'Study PACS sudah dihubungkan ke pasien/kunjungan AMS.');
    redirect('/pacs/studies.php');
  }
}

$q = trim((string)($_GET['q'] ?? ''));
$patients = [];
if ($q !== '') {
  $like = '%' . $q . '%';
  $ps = db()->prepare('SELECT id, mrn, full_name, dob, gender FROM patients WHERE full_name LIKE ? OR mrn LIKE ? ORDER BY full_name ASC LIMIT 30');
  $ps->execute([$like, $like]);
  $patients = $ps->fetchAll();
}
$current = pacs_db()->prepare('SELECT * FROM pacs_links WHERE study_uid=? ORDER BY id DESC LIMIT 1');
$current->execute([$studyUid]);
$link = $current->fetch() ?: [];
$title = 'Link PACS ke AMS';
require __DIR__ . '/../app/views/partials/header.php';
?>
<?= pacs_back_button('/pacs/studies.php') ?>
<div class="card">
  <div class="h1">Link Study PACS ke Pasien/Kunjungan AMS</div>
  <div class="muted">Study: <code><?= e($studyUid) ?></code></div>
  <div class="muted">Pasien DICOM: <?= e((string)($study['patient_name'] ?? '-')) ?> / <?= e((string)($study['patient_id'] ?? '-')) ?></div>
  <?php if ($link): ?><div class="alert ok">Terhubung ke AMS patient ID <?= e((string)$link['ams_patient_id']) ?><?= $link['ams_visit_id'] ? ', visit ID '.e((string)$link['ams_visit_id']) : '' ?>.</div><?php endif; ?>
</div>
<div class="card">
  <form method="get">
    <input type="hidden" name="study_uid" value="<?= e($studyUid) ?>">
    <label class="label">Cari pasien AMS</label>
    <input class="input" name="q" value="<?= e($q) ?>" placeholder="Nama pasien / MRN">
    <button class="btn" type="submit">Cari</button>
  </form>
</div>
<?php if ($q !== ''): ?>
<div class="card">
  <table class="table">
    <thead><tr><th>MRN</th><th>Nama</th><th>Tgl lahir</th><th>Gender</th><th>Link</th></tr></thead>
    <tbody>
    <?php foreach ($patients as $p):
      $vs = db()->prepare('SELECT id, visit_no, visit_date FROM visits WHERE patient_id=? ORDER BY visit_date DESC LIMIT 10');
      $vs->execute([(int)$p['id']]);
      $visits = $vs->fetchAll();
    ?>
      <tr>
        <td><?= e((string)$p['mrn']) ?></td>
        <td><?= e((string)$p['full_name']) ?></td>
        <td><?= e((string)$p['dob']) ?></td>
        <td><?= e((string)$p['gender']) ?></td>
        <td>
          <form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="study_uid" value="<?= e($studyUid) ?>">
            <input type="hidden" name="ams_patient_id" value="<?= e((string)$p['id']) ?>">
            <select class="input" name="ams_visit_id" style="min-width:220px">
              <option value="">Tanpa kunjungan</option>
              <?php foreach ($visits as $v): ?><option value="<?= e((string)$v['id']) ?>"><?= e((string)$v['visit_date']) ?> - <?= e((string)$v['visit_no']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn small" type="submit">Hubungkan</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$patients): ?><tr><td colspan="5">Pasien AMS tidak ditemukan.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../app/views/partials/footer.php';

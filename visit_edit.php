<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();
csrf_validate();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT v.*, p.full_name, p.mrn, p.dob, p.gender, p.address
                     FROM visits v JOIN patients p ON p.id=v.patient_id
                     WHERE v.id=?");
$st->execute([$id]);
$v = $st->fetch();
if (!$v) { http_response_code(404); echo "Not found"; exit; }

if (is_post()) {
  $anamnesis = trim($_POST['anamnesis'] ?? '');
  $physical = trim($_POST['physical_exam'] ?? '');
  $usg = trim($_POST['usg_report'] ?? '');
  $therapy = trim($_POST['therapy'] ?? '');
  db_exec("UPDATE visits SET anamnesis=?, physical_exam=?, usg_report=?, therapy=?, doctor_id=?, updated_at=? WHERE id=?",
    [$anamnesis,$physical,$usg,$therapy,$u['id'],now_dt(),$id]
  );
  flash_set('ok','Kunjungan diperbarui.');
  redirect('/visits.php?patient_id='.(int)$v['patient_id']);
}

$title = "Edit Kunjungan";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Edit Kunjungan</div>
  <div class="muted">No: <?= e($v['visit_no']) ?> | Pasien: <?= e($v['mrn'].' - '.$v['full_name']) ?></div>
</div>

<div class="card">
  <form method="post" class="grid">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="col-12">
      <div class="label">Anamnesa</div>
      <textarea class="input" name="anamnesis"><?= e($v['anamnesis'] ?? '') ?></textarea>
    </div>
    <div class="col-12">
      <div class="label">Pemeriksaan Fisik</div>
      <textarea class="input" name="physical_exam"><?= e($v['physical_exam'] ?? '') ?></textarea>
    </div>
    <div class="col-12">
      <div class="label">Laporan USG</div>
      <textarea class="input" name="usg_report"><?= e($v['usg_report'] ?? '') ?></textarea>
    </div>
    <div class="col-12">
      <div class="label">Terapi</div>
      <textarea class="input" name="therapy"><?= e($v['therapy'] ?? '') ?></textarea>
    </div>
    <div class="col-12" style="display:flex;justify-content:flex-end;gap:10px">
      <a class="btn secondary" href="<?= e(url('/visits.php?patient_id='.(int)$v['patient_id'])) ?>">Kembali</a>
      <button class="btn" type="submit">Simpan</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/app/views/partials/footer.php'; ?>

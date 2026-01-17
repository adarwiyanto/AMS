<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin','dokter','perawat']);
$u = auth_user();
$settings = get_settings();
csrf_validate();

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
  flash_set('err','Kunjungan tidak valid.');
  redirect('/visits.php');
}

// ===== Helper upload foto USG (sama seperti di visits.php) =====
function usg_upload_dir_abs(): string {
  // folder target: /storage/uploads/usg
  $base = realpath(__DIR__ . '/storage');
  return $base ? ($base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'usg') : '';
}
function usg_upload_url_rel(): string {
  return '/storage/uploads/usg';
}

function save_usg_images_for_visit(int $visitId, array $files, string $caption = ''): int {
  if ($visitId <= 0) return 0;
  if (empty($files) || empty($files['name'])) return 0;

  $dir = usg_upload_dir_abs();
  if (!$dir) throw new Exception('Folder storage tidak ditemukan.');
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  if (!is_dir($dir) || !is_writable($dir)) {
    throw new Exception('Folder upload USG tidak writable: ' . $dir);
  }

  $names = (array)$files['name'];
  $tmp   = (array)$files['tmp_name'];
  $errs  = (array)$files['error'];
  $sizes = (array)$files['size'];

  $saved = 0;
  for ($i=0; $i<count($names); $i++) {
    if (!isset($errs[$i]) || $errs[$i] === UPLOAD_ERR_NO_FILE) continue;
    if ($errs[$i] !== UPLOAD_ERR_OK) continue;

    $orig = (string)$names[$i];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png'], true)) continue;

    if (isset($sizes[$i]) && (int)$sizes[$i] > 8 * 1024 * 1024) continue; // max 8MB

    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($orig, PATHINFO_FILENAME));
    if (!$safe) $safe = 'usg';

    $fname = 'USG_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safe . '.' . $ext;
    $abs = $dir . DIRECTORY_SEPARATOR . $fname;

    if (!move_uploaded_file($tmp[$i], $abs)) continue;

    // Set permission aman untuk file upload (umumnya 0644 di hosting Linux)
    @chmod($abs, 0644);

    $rel = usg_upload_url_rel() . '/' . $fname;

    db_exec(
      "INSERT INTO usg_images(visit_id, file_path, caption, created_at) VALUES(?,?,?,?)",
      [$visitId, $rel, trim((string)$caption), now_dt()]
    );

    $saved++;
  }

  return $saved;
}

// ===== Load visit =====
$st = db()->prepare(
  "SELECT v.*, p.full_name, p.mrn, p.dob, p.gender, p.address, u.full_name AS doctor_name
   FROM visits v
   JOIN patients p ON p.id=v.patient_id
   LEFT JOIN users u ON u.id=v.doctor_id
   WHERE v.id=?"
);
$st->execute([$id]);
$v = $st->fetch();
if (!$v) {
  http_response_code(404);
  echo "Not found";
  exit;
}

// ===== Existing images =====
$imgList = [];
try {
  $st = db()->prepare("SELECT id, file_path, caption, created_at FROM usg_images WHERE visit_id=? ORDER BY id DESC");
  $st->execute([$id]);
  $imgList = $st->fetchAll();
} catch (Throwable $e) {
  $imgList = [];
}

if (is_post()) {
  $anamnesis = trim($_POST['anamnesis'] ?? '');
  $physical  = trim($_POST['physical_exam'] ?? '');
  $usg       = trim($_POST['usg_report'] ?? '');
  $therapy   = trim($_POST['therapy'] ?? '');

  db_exec(
    "UPDATE visits SET anamnesis=?, physical_exam=?, usg_report=?, therapy=?, doctor_id=?, updated_at=? WHERE id=?",
    [$anamnesis, $physical, $usg, $therapy, (int)$u['id'], now_dt(), $id]
  );

  // Upload foto USG tambahan (kalau ada)
  try {
    if (!empty($_FILES['usg_images']) && is_array($_FILES['usg_images'])) {
      $cap = trim((string)($_POST['usg_caption'] ?? ''));
      save_usg_images_for_visit($id, $_FILES['usg_images'], $cap);
    }
  } catch (Throwable $e) {
    log_app('error', 'USG upload failed on visit_edit', ['err'=>$e->getMessage(), 'visit_id'=>$id]);
    flash_set('err','Kunjungan diperbarui, tapi upload foto USG gagal: '.$e->getMessage());
    redirect('/visit_edit.php?id=' . $id);
  }

  flash_set('ok','Kunjungan diperbarui.');
  redirect('/visits.php?patient_id='.(int)$v['patient_id']);
}

$title = "Edit Kunjungan";
require __DIR__ . '/app/views/partials/header.php';
?>
<style>
  .usg-upload-row{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
  .file-hidden{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
  .usg-preview{margin-top:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px}
  .usg-thumb{border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:10px;background:rgba(255,255,255,.03)}
  .usg-thumb img{width:100%;height:120px;object-fit:cover;border-radius:10px;display:block}
  .usg-cap{margin-top:6px;font-size:12px;opacity:.85;word-break:break-word}
  .usg-actions{margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
</style>

<div class="card">
  <div class="h1">Edit Kunjungan</div>
  <div class="muted">No: <?= e($v['visit_no']) ?> | Pasien: <?= e($v['mrn'].' - '.$v['full_name']) ?></div>
</div>

<div class="card">
  <form method="post" class="grid" autocomplete="off" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

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

    <div class="col-12">
      <div class="h1" style="font-size:16px;margin-top:4px">Foto USG</div>
      <div class="muted">Upload foto hasil USG (JPG/PNG). Foto akan ikut tercetak pada "Print Hasil".</div>

      <div class="usg-upload-row" style="margin-top:10px">
        <input id="usg_images_edit" class="file-hidden" type="file" name="usg_images[]" accept=".jpg,.jpeg,.png" multiple>
        <label class="btn secondary" for="usg_images_edit">Pilih Foto</label>

        <input class="input" name="usg_caption" placeholder="Caption (opsional, mis: Abdomen, Ginjal kanan)" style="min-width:320px;flex:1">
        <div class="muted" style="align-self:center">Bisa multi. Caption akan dipakai untuk semua foto yang dipilih.</div>
      </div>

      <div id="usgPreviewEdit" class="usg-preview" style="display:none"></div>

      <?php if (!empty($imgList)): ?>
        <div class="h1" style="font-size:16px;margin-top:14px">Foto yang sudah tersimpan</div>
        <div class="usg-preview">
          <?php foreach ($imgList as $img): ?>
            <div class="usg-thumb">
              <a href="<?= e(url($img['file_path'])) ?>" target="_blank" title="Buka gambar">
                <img src="<?= e(url($img['file_path'])) ?>" alt="USG">
              </a>
              <div class="usg-cap"><?= e($img['caption'] ?? '') ?></div>
              <div class="usg-actions">
                <a class="btn small secondary" href="<?= e(url($img['file_path'])) ?>" target="_blank">Buka</a>
                <a class="btn small danger" href="<?= e(url('/usg_delete.php?id='.(int)$img['id'].'&visit_id='.(int)$id)) ?>" onclick="return confirm('Hapus foto USG ini?')">Hapus</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-12" style="display:flex;justify-content:flex-end;gap:10px">
      <a class="btn secondary" href="<?= e(url('/visits.php?patient_id='.(int)$v['patient_id'])) ?>">Kembali</a>
      <button class="btn" type="submit">Simpan</button>
    </div>
  </form>
</div>

<script>
  (function(){
    const input = document.getElementById('usg_images_edit');
    const wrap  = document.getElementById('usgPreviewEdit');
    if (!input || !wrap) return;

    input.addEventListener('change', function(){
      wrap.innerHTML = '';
      const files = Array.from(input.files || []);
      if (!files.length) {
        wrap.style.display = 'none';
        return;
      }
      wrap.style.display = 'grid';
      files.forEach(f => {
        const url = URL.createObjectURL(f);
        const box = document.createElement('div');
        box.className = 'usg-thumb';
        box.innerHTML = `<img src="${url}" alt="USG"><div class="usg-cap">${escapeHtml(f.name)}</div>`;
        wrap.appendChild(box);
      });
    });

    function escapeHtml(s){
      return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }
  })();
</script>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>

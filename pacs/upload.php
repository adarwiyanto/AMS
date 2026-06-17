<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
$u = pacs_require_login();
$settings = get_settings();
$title = 'Upload DICOM PACS';
$maxMb = (int)($settings['pacs_max_upload_mb'] ?? 512);
require __DIR__ . '/../app/views/partials/header.php';
?>
<?= pacs_back_button('/pacs/index.php') ?>
<div class="card">
  <div class="h1">Upload DICOM / ZIP ke PACS</div>
  <div class="muted">Upload file DICOM langsung atau ZIP berisi banyak DICOM. Metadata utama akan dibaca otomatis dan disimpan ke database PACS terpisah.</div>
</div>

<div class="card">
  <form id="pacsUploadForm" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label class="label">File DICOM / ZIP</label>
    <input class="input" type="file" name="dicom_files[]" id="dicomFiles" multiple accept=".dcm,.dicom,.ima,.zip,application/zip,application/dicom">
    <div class="muted" style="margin-top:8px">Batas upload mengikuti setting server/PACS: lk <?= e((string)$maxMb) ?> MB per file.</div>

    <div class="pacs-progress" style="margin-top:14px;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#f8fafc;height:24px;display:none" id="pacsProgressWrap">
      <div id="pacsProgressBar" style="height:100%;width:0%;background:var(--accent);color:white;text-align:center;font-size:12px;line-height:24px;transition:width .15s ease">0%</div>
    </div>
    <div id="pacsUploadStatus" class="muted" style="margin-top:10px"></div>

    <p style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
      <button class="btn" type="submit">Upload ke PACS</button>
      <a class="btn secondary" href="<?= e(url('/pacs/studies.php')) ?>">Lihat Studies</a>
    </p>
  </form>
</div>

<div class="card" id="pacsUploadResult" style="display:none">
  <div class="h1" style="font-size:18px">Hasil Upload</div>
  <div id="pacsUploadSummary"></div>
  <div id="pacsUploadErrors" style="margin-top:10px"></div>
</div>

<script>
(function(){
  const form = document.getElementById('pacsUploadForm');
  const files = document.getElementById('dicomFiles');
  const wrap = document.getElementById('pacsProgressWrap');
  const bar = document.getElementById('pacsProgressBar');
  const status = document.getElementById('pacsUploadStatus');
  const result = document.getElementById('pacsUploadResult');
  const summary = document.getElementById('pacsUploadSummary');
  const errors = document.getElementById('pacsUploadErrors');
  const csrfInput = form.querySelector('input[name="_csrf"]');

  function esc(s){ return String(s ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c])); }
  function setProgress(p){
    p = Math.max(0, Math.min(100, Math.round(p)));
    bar.style.width = p + '%';
    bar.textContent = p + '%';
  }

  form.addEventListener('submit', function(ev){
    ev.preventDefault();
    if (!files.files || files.files.length === 0) {
      status.innerHTML = '<span style="color:#991b1b">Pilih file dulu.</span>';
      return;
    }
    const fd = new FormData(form);
    wrap.style.display = 'block';
    result.style.display = 'none';
    summary.innerHTML = '';
    errors.innerHTML = '';
    setProgress(0);
    status.textContent = 'Mengupload ' + files.files.length + ' file...';

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '<?= e(url('/pacs/api/upload_handler.php')) ?>', true);
    if (csrfInput && csrfInput.value) {
      xhr.setRequestHeader('X-CSRF-Token', csrfInput.value);
    }
    xhr.upload.onprogress = function(e){
      if (e.lengthComputable) setProgress((e.loaded / e.total) * 100);
    };
    xhr.onerror = function(){
      status.innerHTML = '<span style="color:#991b1b">Upload gagal karena koneksi/server.</span>';
    };
    xhr.onload = function(){
      setProgress(100);
      let data = null;
      try { data = JSON.parse(xhr.responseText || '{}'); } catch(e) {}
      result.style.display = 'block';
      if (!data || data.ok !== true) {
        status.innerHTML = '<span style="color:#991b1b">Upload gagal.</span>';
        summary.innerHTML = '<div class="alert err">' + esc((data && data.error) ? data.error : xhr.responseText) + '</div>';
        return;
      }
      status.innerHTML = '<span style="color:#166534">Upload selesai.</span>';
      summary.innerHTML = '<div class="alert ok">Tersimpan: <b>' + esc(data.saved) + '</b> | Duplikat/skipped: <b>' + esc(data.skipped) + '</b> | Diproses: <b>' + esc(data.processed) + '</b></div>';
      if (Array.isArray(data.errors) && data.errors.length) {
        errors.innerHTML = '<div class="alert err"><b>Catatan/error:</b><ul style="margin:8px 0 0 18px">' + data.errors.map(x => '<li>' + esc(x) + '</li>').join('') + '</ul></div>';
      } else {
        errors.innerHTML = '<div class="muted">Tidak ada error. Tumor jinak progress bar: kecil tapi bikin tenang.</div>';
      }
    };
    xhr.send(fd);
  });
})();
</script>
<?php require __DIR__ . '/../app/views/partials/footer.php';

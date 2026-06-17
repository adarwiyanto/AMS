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
  <div class="muted">Upload file DICOM langsung atau ZIP berisi banyak DICOM. File besar dikirim bertahap/chunk agar tetap bisa melewati batas upload shared hosting.</div>
</div>

<div class="card">
  <form id="pacsUploadForm" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label class="label">File DICOM / ZIP</label>
    <input class="input" type="file" name="dicom_files[]" id="dicomFiles" multiple accept=".dcm,.dicom,.ima,.zip,application/zip,application/dicom">
    <div class="muted" style="margin-top:8px">Batas PACS: lk <?= e((string)$maxMb) ?> MB per file. Upload dikirim dalam chunk 5 MB supaya aman pada shared hosting dengan batas 10 MB/request.</div>

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
  const CHUNK_SIZE = 5 * 1024 * 1024;
  const uploadChunkUrl = '<?= e(url('/pacs/api/upload_chunk.php')) ?>';
  const finalizeUrl = '<?= e(url('/pacs/api/finalize_upload.php')) ?>';

  function esc(s){ return String(s ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c])); }
  function setProgress(p){
    p = Math.max(0, Math.min(100, Math.round(p)));
    bar.style.width = p + '%';
    bar.textContent = p + '%';
  }
  function makeUploadId(file, index){
    const rand = (window.crypto && crypto.getRandomValues) ? Array.from(crypto.getRandomValues(new Uint32Array(2))).map(x => x.toString(36)).join('') : Math.random().toString(36).slice(2);
    return 'up_' + Date.now().toString(36) + '_' + index + '_' + rand;
  }
  function postForm(url, fd){
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', url, true);
      if (csrfInput && csrfInput.value) xhr.setRequestHeader('X-CSRF-Token', csrfInput.value);
      xhr.onerror = () => reject(new Error('Koneksi/server gagal'));
      xhr.onload = () => {
        let data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch(e) {}
        if (!data || data.ok !== true) {
          reject(new Error((data && data.error) ? data.error : (xhr.responseText || 'Upload gagal')));
          return;
        }
        resolve(data);
      };
      xhr.send(fd);
    });
  }
  async function uploadOneFile(file, fileIndex, totalFiles, globalState){
    const uploadId = makeUploadId(file, fileIndex);
    const totalChunks = Math.max(1, Math.ceil(file.size / CHUNK_SIZE));
    for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
      const start = chunkIndex * CHUNK_SIZE;
      const end = Math.min(file.size, start + CHUNK_SIZE);
      const blob = file.slice(start, end);
      const fd = new FormData();
      fd.append('_csrf', csrfInput ? csrfInput.value : '');
      fd.append('upload_id', uploadId);
      fd.append('file_name', file.name);
      fd.append('file_size', String(file.size));
      fd.append('chunk_index', String(chunkIndex));
      fd.append('total_chunks', String(totalChunks));
      fd.append('chunk', blob, file.name + '.part' + chunkIndex);
      status.textContent = 'File ' + (fileIndex + 1) + '/' + totalFiles + ': mengirim chunk ' + (chunkIndex + 1) + '/' + totalChunks + ' (' + file.name + ')...';
      await postForm(uploadChunkUrl, fd);
      globalState.doneChunks++;
      setProgress((globalState.doneChunks / globalState.totalChunks) * 85);
    }

    status.textContent = 'File ' + (fileIndex + 1) + '/' + totalFiles + ': menggabungkan dan memproses ' + file.name + '...';
    const fdFinal = new FormData();
    fdFinal.append('_csrf', csrfInput ? csrfInput.value : '');
    fdFinal.append('upload_id', uploadId);
    const one = await postForm(finalizeUrl, fdFinal);
    setProgress(85 + (((fileIndex + 1) / totalFiles) * 15));
    return one;
  }

  form.addEventListener('submit', async function(ev){
    ev.preventDefault();
    if (!files.files || files.files.length === 0) {
      status.innerHTML = '<span style="color:#991b1b">Pilih file dulu.</span>';
      return;
    }

    wrap.style.display = 'block';
    result.style.display = 'none';
    summary.innerHTML = '';
    errors.innerHTML = '';
    setProgress(0);

    const fileList = Array.from(files.files);
    const globalState = {
      totalChunks: fileList.reduce((n, f) => n + Math.max(1, Math.ceil(f.size / CHUNK_SIZE)), 0),
      doneChunks: 0
    };
    const totals = {saved:0, skipped:0, restored:0, ignored:0, processed:0, errors:[]};

    try {
      for (let i = 0; i < fileList.length; i++) {
        const one = await uploadOneFile(fileList[i], i, fileList.length, globalState);
        totals.saved += Number(one.saved || 0);
        totals.skipped += Number(one.skipped || 0);
        totals.restored += Number(one.restored || 0);
        totals.ignored += Number(one.ignored || 0);
        totals.processed += Number(one.processed || 0);
        if (Array.isArray(one.errors)) totals.errors.push(...one.errors);
      }
      setProgress(100);
      result.style.display = 'block';
      status.innerHTML = '<span style="color:#166534">Upload selesai.</span>';
      summary.innerHTML = '<div class="alert ok">Tersimpan: <b>' + esc(totals.saved) + '</b> | Duplikat/skipped: <b>' + esc(totals.skipped) + '</b> | Dipulihkan: <b>' + esc(totals.restored) + '</b> | Non-DICOM dilewati: <b>' + esc(totals.ignored) + '</b> | DICOM diproses: <b>' + esc(totals.processed) + '</b></div>';
      if (totals.errors.length) {
        errors.innerHTML = '<div class="alert err"><b>Catatan/error:</b><ul style="margin:8px 0 0 18px">' + totals.errors.map(x => '<li>' + esc(x) + '</li>').join('') + '</ul></div>';
      } else {
        errors.innerHTML = '<div class="muted">Tidak ada error.</div>';
      }
    } catch(e) {
      result.style.display = 'block';
      status.innerHTML = '<span style="color:#991b1b">Upload gagal.</span>';
      summary.innerHTML = '<div class="alert err">' + esc(e.message || e) + '</div>';
    }
  });
})();
</script>
<?php require __DIR__ . '/../app/views/partials/footer.php';

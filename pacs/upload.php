<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
$u = pacs_require_login();
$settings = get_settings();
$title = 'PACS - Upload & Studies';
$maxMb = (int)($settings['pacs_max_upload_mb'] ?? 512);
$csrf = csrf_token();
require __DIR__ . '/../app/views/partials/header.php';
?>
<?= pacs_back_button('/pacs/index.php') ?>
<style>
.pacs-page{display:grid;gap:14px}.pacs-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.pacs-card-head .h1{margin:0}.pacs-upload-row{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end}.pacs-actions{display:flex;gap:6px;flex-wrap:wrap}.pacs-study-tools{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;margin:10px 0}.pacs-table-wrap{overflow:auto;border:1px solid var(--line);border-radius:14px}.pacs-table-wrap table{margin:0}.pacs-uid{max-width:270px;word-break:break-all;font-size:12px}.pacs-progress{margin-top:12px;border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#f8fafc;height:24px;display:none}.pacs-progress>div{height:100%;width:0%;background:var(--accent);color:white;text-align:center;font-size:12px;line-height:24px;transition:width .15s ease}.pacs-mobile-list{display:none}.pacs-study-card{border:1px solid var(--line);border-radius:14px;padding:12px;background:#fff;margin:8px 0}.pacs-study-card .meta{display:grid;grid-template-columns:110px 1fr;gap:4px;font-size:13px}.pacs-danger{background:#991b1b!important;border-color:#991b1b!important;color:#fff!important}.pacs-muted-small{font-size:12px;color:var(--muted)}
@media(max-width:760px){.pacs-upload-row,.pacs-study-tools{grid-template-columns:1fr}.pacs-card-head{display:block}.pacs-table-wrap{display:none}.pacs-mobile-list{display:block}.pacs-uid{max-width:none}.pacs-actions .btn{flex:1 1 auto;text-align:center}.container{padding-left:12px!important;padding-right:12px!important}.card{border-radius:16px!important}}
</style>

<div class="pacs-page">
  <div class="card">
    <div class="pacs-card-head">
      <div>
        <div class="h1">PACS Upload & Studies</div>
        <div class="muted">Upload DICOM/ZIP dan daftar studies dibuat satu halaman. Upload dikirim dalam chunk 5 MB agar aman pada shared hosting dengan batas lk 10 MB/request.</div>
      </div>
      <a class="btn secondary" href="<?= e(url('/pacs/index.php')) ?>">PACS Home</a>
    </div>
  </div>

  <div class="card" id="uploadPanel">
    <form id="pacsUploadForm" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
      <div class="pacs-upload-row">
        <div>
          <label class="label">File DICOM / ZIP</label>
          <input class="input" type="file" name="dicom_files[]" id="dicomFiles" multiple accept=".dcm,.dicom,.ima,.zip,application/zip,application/dicom">
          <div class="muted" style="margin-top:8px">Batas PACS: lk <?= e((string)$maxMb) ?> MB per file. File besar otomatis dipecah menjadi chunk 5 MB.</div>
        </div>
        <button class="btn" type="submit" id="uploadBtn">Upload ke PACS</button>
      </div>

      <div class="pacs-progress" id="pacsProgressWrap"><div id="pacsProgressBar">0%</div></div>
      <div id="pacsUploadStatus" class="muted" style="margin-top:10px"></div>
    </form>
  </div>

  <div class="card" id="pacsUploadResult" style="display:none">
    <div class="h1" style="font-size:18px">Hasil Upload</div>
    <div id="pacsUploadSummary"></div>
    <div id="pacsUploadErrors" style="margin-top:10px"></div>
  </div>

  <div class="card" id="studies">
    <div class="pacs-card-head">
      <div>
        <div class="h1">Studies</div>
        <div class="muted">Cari, buka viewer, atau hapus study yang sudah diupload.</div>
      </div>
    </div>
    <div class="pacs-study-tools">
      <input class="input" id="studySearch" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="Cari Patient ID / nama pasien / Study UID / deskripsi">
      <button class="btn secondary" id="refreshStudies" type="button">Refresh</button>
    </div>
    <div id="studiesStatus" class="muted"></div>
    <div class="pacs-table-wrap">
      <table class="table">
        <thead><tr><th>Study UID</th><th>Patient</th><th>Date</th><th>Modalities</th><th>Series</th><th>Instances</th><th>Aksi</th></tr></thead>
        <tbody id="studiesTbody"><tr><td colspan="7">Memuat studies...</td></tr></tbody>
      </table>
    </div>
    <div class="pacs-mobile-list" id="studiesMobile"></div>
  </div>
</div>

<script>
(function(){
  const csrf = <?= json_encode($csrf) ?>;
  const chunkSize = 5 * 1024 * 1024;
  const form = document.getElementById('pacsUploadForm');
  const filesInput = document.getElementById('dicomFiles');
  const wrap = document.getElementById('pacsProgressWrap');
  const bar = document.getElementById('pacsProgressBar');
  const status = document.getElementById('pacsUploadStatus');
  const result = document.getElementById('pacsUploadResult');
  const summary = document.getElementById('pacsUploadSummary');
  const errors = document.getElementById('pacsUploadErrors');
  const uploadBtn = document.getElementById('uploadBtn');
  const search = document.getElementById('studySearch');
  const refresh = document.getElementById('refreshStudies');
  const tbody = document.getElementById('studiesTbody');
  const mobile = document.getElementById('studiesMobile');
  const studiesStatus = document.getElementById('studiesStatus');

  function esc(s){ return String(s ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c])); }
  function setProgress(p){ p=Math.max(0,Math.min(100,Math.round(p))); bar.style.width=p+'%'; bar.textContent=p+'%'; }
  function uid(){ return (window.crypto && crypto.randomUUID) ? crypto.randomUUID().replace(/-/g,'') : (Date.now().toString(36)+Math.random().toString(36).slice(2)); }
  function postForm(url, fd){
    return fetch(url, {method:'POST', body:fd, headers:{'X-CSRF-Token':csrf}, credentials:'same-origin'}).then(async r=>{
      const txt = await r.text(); let data = null; try{ data = JSON.parse(txt || '{}'); }catch(e){}
      if(!r.ok || !data || data.ok !== true){ throw new Error((data && data.error) ? data.error : (txt || 'Request gagal')); }
      return data;
    });
  }
  function addTotals(total, data){ ['saved','skipped','restored','ignored','processed'].forEach(k=> total[k]=(total[k]||0)+(Number(data[k]||0))); if(Array.isArray(data.errors)) total.errors = total.errors.concat(data.errors); }
  function renderUploadResult(total){
    result.style.display = 'block';
    summary.innerHTML = '<div class="alert ok">Tersimpan: <b>'+esc(total.saved)+'</b> | Duplikat/skipped: <b>'+esc(total.skipped)+'</b> | Dipulihkan: <b>'+esc(total.restored)+'</b> | Non-DICOM dilewati: <b>'+esc(total.ignored)+'</b> | DICOM diproses: <b>'+esc(total.processed)+'</b></div>';
    if(total.errors && total.errors.length){
      errors.innerHTML = '<div class="alert err"><b>Catatan/error:</b><ul style="margin:8px 0 0 18px">'+total.errors.slice(0,80).map(x=>'<li>'+esc(x)+'</li>').join('')+(total.errors.length>80?'<li>... '+esc(total.errors.length-80)+' error lain disingkat</li>':'')+'</ul></div>';
    } else {
      errors.innerHTML = '<div class="muted">Tidak ada error.</div>';
    }
  }

  async function uploadOneFile(file, fileNo, fileTotal, totals){
    const uploadId = uid();
    const chunks = Math.max(1, Math.ceil(file.size / chunkSize));
    for(let i=0;i<chunks;i++){
      const start = i * chunkSize;
      const blob = file.slice(start, Math.min(file.size, start + chunkSize));
      const fd = new FormData();
      fd.append('_csrf', csrf);
      fd.append('upload_id', uploadId);
      fd.append('file_name', file.name);
      fd.append('chunk_index', String(i));
      fd.append('total_chunks', String(chunks));
      fd.append('total_size', String(file.size));
      fd.append('chunk', blob, file.name + '.part' + i);
      status.textContent = 'Upload '+file.name+' — chunk '+(i+1)+'/'+chunks;
      await postForm('<?= e(url('/pacs/api/upload_chunk.php')) ?>', fd);
      const doneUnits = (fileNo - 1) + ((i + 1) / chunks) * 0.82;
      setProgress((doneUnits / fileTotal) * 100);
    }
    const fd = new FormData();
    fd.append('_csrf', csrf);
    fd.append('upload_id', uploadId);
    fd.append('file_name', file.name);
    fd.append('total_chunks', String(chunks));
    status.textContent = 'Menggabungkan dan memproses '+file.name+'...';
    const data = await postForm('<?= e(url('/pacs/api/finalize_upload.php')) ?>', fd);
    addTotals(totals, data);
    setProgress(((fileNo) / fileTotal) * 100);
  }

  form.addEventListener('submit', async function(ev){
    ev.preventDefault();
    const files = Array.from(filesInput.files || []);
    if(!files.length){ status.innerHTML='<span style="color:#991b1b">Pilih file dulu.</span>'; return; }
    wrap.style.display='block'; result.style.display='none'; summary.innerHTML=''; errors.innerHTML=''; setProgress(0); uploadBtn.disabled=true;
    const totals = {saved:0, skipped:0, restored:0, ignored:0, processed:0, errors:[]};
    try{
      for(let idx=0; idx<files.length; idx++) await uploadOneFile(files[idx], idx+1, files.length, totals);
      status.innerHTML='<span style="color:#166534">Upload selesai.</span>';
      setProgress(100); renderUploadResult(totals); await loadStudies();
    }catch(e){
      status.innerHTML='<span style="color:#991b1b">Upload gagal: '+esc(e.message)+'</span>';
      renderUploadResult(totals);
    }finally{ uploadBtn.disabled=false; }
  });

  function actionHtml(studyUid){
    const u = encodeURIComponent(studyUid);
    return '<div class="pacs-actions">'
      + '<a class="btn small" href="<?= e(url('/pacs/viewer.php')) ?>?study_uid='+u+'">Viewer</a>'
      + '<a class="btn small secondary" href="<?= e(url('/pacs/launch.php')) ?>?study_uid='+u+'">Native</a>'
      + '<a class="btn small secondary" href="<?= e(url('/pacs/report.php')) ?>?study_uid='+u+'">Report</a>'
      + '<a class="btn small secondary" href="<?= e(url('/pacs/link.php')) ?>?study_uid='+u+'">Link AMS</a>'
      + '<button class="btn small pacs-danger" type="button" data-delete-study="'+esc(studyUid)+'">Hapus</button>'
      + '</div>';
  }
  function renderStudies(items){
    if(!items.length){ tbody.innerHTML='<tr><td colspan="7">Belum ada study.</td></tr>'; mobile.innerHTML='<div class="muted">Belum ada study.</div>'; return; }
    tbody.innerHTML = items.map(r => '<tr>'
      + '<td class="pacs-uid">'+esc(r.study_uid)+'</td>'
      + '<td>'+esc(r.patient_id || '')+'<div class="pacs-muted-small">'+esc(r.patient_name || '')+'</div></td>'
      + '<td>'+esc(r.study_date || '')+'</td>'
      + '<td>'+esc(r.modalities || '')+'</td>'
      + '<td>'+esc(r.num_series || 0)+'</td>'
      + '<td>'+esc(r.num_instances || 0)+'</td>'
      + '<td>'+actionHtml(r.study_uid)+'</td>'
      + '</tr>').join('');
    mobile.innerHTML = items.map(r => '<div class="pacs-study-card">'
      + '<div class="pacs-uid"><b>'+esc(r.study_uid)+'</b></div>'
      + '<div class="meta"><span>Patient</span><b>'+esc(r.patient_id || '-')+'</b><span>Nama</span><span>'+esc(r.patient_name || '-')+'</span><span>Tanggal</span><span>'+esc(r.study_date || '-')+'</span><span>Modality</span><span>'+esc(r.modalities || '-')+'</span><span>Series/Inst</span><span>'+esc(r.num_series || 0)+' / '+esc(r.num_instances || 0)+'</span></div>'
      + '<div style="margin-top:10px">'+actionHtml(r.study_uid)+'</div></div>').join('');
  }
  async function loadStudies(){
    studiesStatus.textContent='Memuat studies...';
    const q = encodeURIComponent(search.value || '');
    const res = await fetch('<?= e(url('/pacs/api/studies.php')) ?>?limit=100&q='+q, {credentials:'same-origin'});
    const data = await res.json();
    if(!data.ok) throw new Error(data.error || 'Gagal memuat studies');
    renderStudies(data.items || []);
    studiesStatus.textContent = (data.items || []).length + ' study ditampilkan.';
  }
  async function deleteStudy(studyUid){
    if(!confirm('Hapus study ini dari database PACS dan storage file DICOM?\n\n'+studyUid)) return;
    const fd = new FormData(); fd.append('_csrf', csrf); fd.append('study_uid', studyUid);
    studiesStatus.textContent = 'Menghapus study...';
    try{
      const data = await postForm('<?= e(url('/pacs/api/delete_study.php')) ?>', fd);
      studiesStatus.textContent = 'Study dihapus. File terhapus: '+(data.deleted_files || 0);
      await loadStudies();
    }catch(e){ studiesStatus.innerHTML = '<span style="color:#991b1b">Gagal hapus: '+esc(e.message)+'</span>'; }
  }
  document.addEventListener('click', function(e){ const btn = e.target.closest('[data-delete-study]'); if(btn) deleteStudy(btn.getAttribute('data-delete-study') || ''); });
  refresh.addEventListener('click', loadStudies);
  let t=null; search.addEventListener('input', function(){ clearTimeout(t); t=setTimeout(loadStudies, 350); });
  loadStudies().catch(e => { studiesStatus.innerHTML='<span style="color:#991b1b">'+esc(e.message)+'</span>'; });
})();
</script>
<?php require __DIR__ . '/../app/views/partials/footer.php';

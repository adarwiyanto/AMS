<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
$u = pacs_require_login();
$settings = get_settings();
$title = 'Upload DICOM';
$maxMb = (int)($settings['pacs_max_upload_mb'] ?? 512);
require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Upload DICOM</div>
  <div class="muted">Upload multi file .dcm atau .zip, metadata diparse di browser.</div>
  <form id="dicomUploadForm" method="post" enctype="multipart/form-data" action="<?= e(url('/pacs/api/upload_handler.php')) ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input class="input" type="file" name="dicom_files[]" id="dicomFiles" accept=".dcm,.zip,application/dicom,application/zip" multiple required>
    <div class="muted" style="margin:8px 0">Maksimum upload: <?= e((string)$maxMb) ?> MB.</div>
    <button class="btn" type="submit">Upload</button>
  </form>
  <pre id="uploadLog" style="background:#111;color:#8f8;padding:10px;min-height:100px;white-space:pre-wrap"></pre>
</div>
<script src="<?= e(url('/pacs/assets/dicomParser.min.js')) ?>"></script>
<script>
const form = document.getElementById('dicomUploadForm');
const logEl = document.getElementById('uploadLog');
const filesEl = document.getElementById('dicomFiles');
const tagMap = {
  patientName: 'x00100010',
  patientId: 'x00100020',
  studyUid: 'x0020000d',
  seriesUid: 'x0020000e',
  sopUid: 'x00080018',
  modality: 'x00080060',
  studyDate: 'x00080020',
};

const printLog = msg => logEl.textContent += msg + "\n";

async function parseDicomMeta(file) {
  const buf = await file.arrayBuffer();
  const byteArray = new Uint8Array(buf);
  const ds = window.dicomParser.parseDicom(byteArray);
  return {
    PatientName: ds.string(tagMap.patientName) || '',
    PatientID: ds.string(tagMap.patientId) || '',
    StudyInstanceUID: ds.string(tagMap.studyUid) || '',
    SeriesInstanceUID: ds.string(tagMap.seriesUid) || '',
    SOPInstanceUID: ds.string(tagMap.sopUid) || '',
    Modality: ds.string(tagMap.modality) || '',
    StudyDate: ds.string(tagMap.studyDate) || '',
  };
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  logEl.textContent = '';
  const files = Array.from(filesEl.files || []);
  if (!files.length) return;
  const fd = new FormData();
  fd.append('_csrf', form.querySelector('[name="_csrf"]').value);
  const metadata = {};

  for (const file of files) {
    fd.append('dicom_files[]', file, file.name);
    if (file.name.toLowerCase().endsWith('.dcm')) {
      try {
        metadata[file.name] = await parseDicomMeta(file);
        printLog(`OK parse metadata: ${file.name}`);
      } catch (_) {
        printLog(`Skip parse metadata (invalid DICOM): ${file.name}`);
      }
    }
  }

  fd.append('metadata_json', JSON.stringify(metadata));
  const res = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' });
  const json = await res.json();
  printLog(JSON.stringify(json, null, 2));
  if (json.ok) {
    window.location.href = '<?= e(url('/pacs/studies.php')) ?>';
  }
});
</script>
<?php require __DIR__ . '/../app/views/partials/footer.php';

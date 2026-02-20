<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';

$u = pacs_current_user_or_forbidden();
auth_require_role(['admin']);
$settings = get_settings();
csrf_validate();

if (is_post()) {
  set_setting('pacs_ohif_base_url', trim((string)($_POST['pacs_ohif_base_url'] ?? '')));
  set_setting('pacs_orthanc_url', trim((string)($_POST['pacs_orthanc_url'] ?? '')));
  set_setting('pacs_orthanc_user', trim((string)($_POST['pacs_orthanc_user'] ?? '')));
  set_setting('pacs_orthanc_pass', trim((string)($_POST['pacs_orthanc_pass'] ?? '')));
  set_setting('pacs_dicomweb_path', trim((string)($_POST['pacs_dicomweb_path'] ?? '')));

  flash_set('ok', 'Setting PACS tersimpan.');
  redirect('/pacs/settings.php');
}

$pacsCfg = pacs_config();
$title = 'Setting PACS';
require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Setting PACS</div>
  <div class="muted">Konfigurasi endpoint OHIF & Orthanc agar integrasi PACS sesuai environment AMS.</div>
</div>

<div class="card">
  <form method="post" class="grid">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <div class="col-12">
      <div class="label">OHIF Base URL</div>
      <input class="input" name="pacs_ohif_base_url" placeholder="http://localhost:3000" value="<?= e($settings['pacs_ohif_base_url'] ?? ($pacsCfg['ohif_base_url'] ?? '')) ?>">
      <div class="muted" style="margin-top:6px">Contoh: <code>http://localhost:3000</code> atau URL OHIF production.</div>
    </div>

    <div class="col-12">
      <div class="label">Orthanc URL</div>
      <input class="input" name="pacs_orthanc_url" placeholder="http://127.0.0.1:8042" value="<?= e($settings['pacs_orthanc_url'] ?? ($pacsCfg['orthanc_url'] ?? '')) ?>">
    </div>

    <div class="col-6">
      <div class="label">Orthanc Username</div>
      <input class="input" name="pacs_orthanc_user" value="<?= e($settings['pacs_orthanc_user'] ?? ($pacsCfg['orthanc_user'] ?? '')) ?>">
    </div>

    <div class="col-6">
      <div class="label">Orthanc Password</div>
      <input class="input" type="password" name="pacs_orthanc_pass" value="<?= e($settings['pacs_orthanc_pass'] ?? ($pacsCfg['orthanc_pass'] ?? '')) ?>">
    </div>

    <div class="col-12">
      <div class="label">Orthanc DICOMweb Path</div>
      <input class="input" name="pacs_dicomweb_path" placeholder="/dicom-web" value="<?= e($settings['pacs_dicomweb_path'] ?? ($pacsCfg['dicomweb_path'] ?? '/dicom-web')) ?>">
    </div>

    <div class="col-12" style="display:flex;justify-content:flex-end">
      <button class="btn" type="submit">Simpan Setting PACS</button>
    </div>
  </form>
</div>

<?php require __DIR__ . '/../app/views/partials/footer.php'; ?>

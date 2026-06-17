<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
$u = pacs_require_login();
if (($u['role'] ?? '') !== 'admin') {
  http_response_code(403);
  exit('Forbidden');
}
if (is_post()) {
  csrf_validate();
  set_setting('pacs_max_upload_mb', (string)max(1, (int)($_POST['pacs_max_upload_mb'] ?? 512)));
  flash_set('ok', 'Setting PACS tersimpan.');
  redirect('/pacs/settings.php');
}
$settings = get_settings();
$title = 'Settings PACS';
require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Settings PACS</div>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="label">Max Upload (MB)</div>
    <input class="input" type="number" name="pacs_max_upload_mb" value="<?= e((string)($settings['pacs_max_upload_mb'] ?? 512)) ?>" min="1" max="512">
    <button class="btn" type="submit">Simpan</button>
  </form>
  <div class="muted" style="margin-top:8px">Storage aktif: <code><?= e(PACS_STORAGE) ?></code></div>
</div>
<?php require __DIR__ . '/../app/views/partials/footer.php';

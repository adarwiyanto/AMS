<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
auth_require_role(['admin']);
$u = auth_user();
$settings = get_settings();
$title = 'Setting PACS';

$configFile = realpath(__DIR__ . '/../app') . DIRECTORY_SEPARATOR . 'pacs_config.php';
$sampleFile = realpath(__DIR__ . '/../app') . DIRECTORY_SEPARATOR . 'pacs_config.sample.php';
$hasLocalConfig = file_exists($configFile);
$current = function_exists('pacs_config_local') ? pacs_config_local() : (file_exists($sampleFile) ? require $sampleFile : []);
$currentDb = $current['db'] ?? [];
$currentBridge = $current['native_bridge'] ?? [];
$currentStorage = $current['storage'] ?? [];
$testResult = null;
$manualConfig = '';

function pacs_settings_bool($value): bool {
  return in_array((string)$value, ['1', 'true', 'yes', 'on'], true);
}

function pacs_settings_build_config(array $old, array $post): array {
  $oldDb = $old['db'] ?? [];
  $oldBridge = $old['native_bridge'] ?? [];
  $oldStorage = $old['storage'] ?? [];
  $pass = (string)($post['pass'] ?? '');
  if ($pass === '' && array_key_exists('pass', $oldDb)) {
    $pass = (string)$oldDb['pass'];
  }
  return [
    'db' => [
      'host' => trim((string)($post['host'] ?? ($oldDb['host'] ?? '127.0.0.1'))) ?: '127.0.0.1',
      'port' => max(1, (int)($post['port'] ?? ($oldDb['port'] ?? 3306))),
      'name' => trim((string)($post['name'] ?? ($oldDb['name'] ?? 'adey8293_pacs'))) ?: 'adey8293_pacs',
      'user' => trim((string)($post['user'] ?? ($oldDb['user'] ?? 'adey8293_adyto'))) ?: 'adey8293_adyto',
      'pass' => $pass,
      'charset' => trim((string)($post['charset'] ?? ($oldDb['charset'] ?? 'utf8mb4'))) ?: 'utf8mb4',
    ],
    'storage' => [
      'path' => rtrim(trim((string)($post['storage_path'] ?? ($oldStorage['path'] ?? '/home/adey8293/private_uploads/ams_pacs'))), '/\\') ?: '/home/adey8293/private_uploads/ams_pacs',
    ],
    'native_bridge' => [
      'enabled' => isset($post['bridge_enabled']),
      'protocol' => rtrim(trim((string)($post['bridge_protocol'] ?? ($oldBridge['protocol'] ?? 'adena-dicom://open'))), '?&') ?: 'adena-dicom://open',
    ],
  ];
}

function pacs_settings_export_php(array $config): string {
  return "<?php\nreturn " . var_export($config, true) . ";\n";
}

function pacs_settings_test_connection(array $config): array {
  $db = $config['db'] ?? [];
  if (($db['pass'] ?? '') === '') {
    return ['ok' => false, 'message' => 'Password database PACS masih kosong.'];
  }
  $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'] ?? '127.0.0.1', (int)($db['port'] ?? 3306), $db['name'] ?? 'adey8293_pacs', $db['charset'] ?? 'utf8mb4'
  );
  try {
    $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->query('SELECT 1')->fetchColumn();
    return ['ok' => true, 'message' => 'Koneksi database PACS berhasil.'];
  } catch (Throwable $e) {
    return ['ok' => false, 'message' => 'Koneksi gagal: ' . $e->getMessage()];
  }
}

if (is_post()) {
  csrf_validate();
  $newConfig = pacs_settings_build_config($current, $_POST);
  $manualConfig = pacs_settings_export_php($newConfig);
  $action = (string)($_POST['action'] ?? 'save');
  if ($action === 'test') {
    $testResult = pacs_settings_test_connection($newConfig);
    $current = $newConfig;
    $currentDb = $current['db'] ?? [];
    $currentBridge = $current['native_bridge'] ?? [];
    $currentStorage = $current['storage'] ?? [];
  } else {
    $dir = dirname($configFile);
    if (!is_dir($dir) || !is_writable($dir)) {
      $testResult = [
        'ok' => false,
        'message' => 'Folder app tidak writable. Salin konfigurasi manual di bawah ini ke app/pacs_config.php melalui cPanel File Manager.',
      ];
    } else {
      $ok = @file_put_contents($configFile, $manualConfig, LOCK_EX);
      if ($ok === false) {
        $testResult = [
          'ok' => false,
          'message' => 'Gagal menulis app/pacs_config.php. Salin konfigurasi manual di bawah ini melalui cPanel File Manager.',
        ];
      } else {
        @chmod($configFile, 0640);
        log_app('info', 'PACS settings saved', ['user_id' => $u['id'] ?? null, 'db' => $newConfig['db']['name'] ?? '']);
        flash_set('ok', 'Setting PACS berhasil disimpan.');
        redirect('/pacs/settings.php');
      }
    }
    $current = $newConfig;
    $currentDb = $current['db'] ?? [];
    $currentBridge = $current['native_bridge'] ?? [];
    $currentStorage = $current['storage'] ?? [];
  }
}

require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Setting PACS</div>
  <div class="muted">Isi koneksi database PACS dari aplikasi. Password akan disimpan di <code>app/pacs_config.php</code>, bukan di database AMS.</div>
</div>

<?php if ($testResult): ?>
  <div class="alert <?= $testResult['ok'] ? 'ok' : 'err' ?>"><?= e($testResult['message']) ?></div>
<?php endif; ?>

<div class="card">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="grid">
      <div class="col-6">
        <div class="label">Host database PACS</div>
        <input class="input" name="host" value="<?= e((string)($currentDb['host'] ?? '127.0.0.1')) ?>" autocomplete="off">
      </div>
      <div class="col-6">
        <div class="label">Port</div>
        <input class="input" name="port" type="number" value="<?= e((string)($currentDb['port'] ?? 3306)) ?>" autocomplete="off">
      </div>
      <div class="col-6">
        <div class="label">Nama database</div>
        <input class="input" name="name" value="<?= e((string)($currentDb['name'] ?? 'adey8293_pacs')) ?>" autocomplete="off">
      </div>
      <div class="col-6">
        <div class="label">User database</div>
        <input class="input" name="user" value="<?= e((string)($currentDb['user'] ?? 'adey8293_adyto')) ?>" autocomplete="off">
      </div>
      <div class="col-6">
        <div class="label">Password database</div>
        <input class="input" name="pass" type="password" value="" placeholder="<?= $hasLocalConfig ? 'Kosongkan bila tidak ingin mengubah password' : 'Masukkan password database PACS' ?>" autocomplete="new-password">
      </div>
      <div class="col-6">
        <div class="label">Charset</div>
        <input class="input" name="charset" value="<?= e((string)($currentDb['charset'] ?? 'utf8mb4')) ?>" autocomplete="off">
      </div>
      <div class="col-12">
        <div class="label">Storage PACS</div>
        <input class="input" name="storage_path" value="<?= e((string)($currentStorage['path'] ?? '/home/adey8293/private_uploads/ams_pacs')) ?>" autocomplete="off">
      </div>
      <div class="col-6">
        <div class="label">Protocol native viewer</div>
        <input class="input" name="bridge_protocol" value="<?= e((string)($currentBridge['protocol'] ?? 'adena-dicom://open')) ?>" autocomplete="off">
      </div>
      <div class="col-6">
        <div class="label">Native bridge</div>
        <label style="display:flex;gap:8px;align-items:center;margin-top:10px;">
          <input type="checkbox" name="bridge_enabled" value="1" <?= !empty($currentBridge['enabled']) ? 'checked' : '' ?>> Aktifkan tombol buka Native DicomViewer
        </label>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
      <button class="btn" type="submit" name="action" value="save">Simpan Setting PACS</button>
      <button class="btn secondary" type="submit" name="action" value="test">Test Koneksi PACS</button>
      <a class="btn secondary" href="<?= e(url('/pacs/index.php')) ?>">Kembali ke PACS</a>
    </div>
  </form>
</div>

<div class="card">
  <div class="h1" style="font-size:17px">Status konfigurasi</div>
  <div class="pacs-status-grid">
    <div class="pacs-status-item"><b>File config</b><br><?= $hasLocalConfig ? 'Ada: app/pacs_config.php' : 'Belum ada, masih memakai sample' ?></div>
    <div class="pacs-status-item"><b>Database</b><br><?= e((string)($currentDb['name'] ?? 'adey8293_pacs')) ?></div>
    <div class="pacs-status-item"><b>User</b><br><?= e((string)($currentDb['user'] ?? 'adey8293_adyto')) ?></div>
    <div class="pacs-status-item"><b>Password</b><br><?= (($currentDb['pass'] ?? '') !== '') ? 'Sudah tersimpan' : 'Belum diisi' ?></div>
  </div>
</div>

<?php if ($manualConfig): ?>
  <div class="card">
    <div class="h1" style="font-size:17px">Konfigurasi manual</div>
    <div class="muted">Bila server tidak mengizinkan aplikasi menulis file, copy isi ini ke <code>app/pacs_config.php</code>.</div>
    <textarea class="input" style="min-height:260px;font-family:ui-monospace,Consolas,monospace" readonly><?= e($manualConfig) ?></textarea>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../app/views/partials/footer.php';

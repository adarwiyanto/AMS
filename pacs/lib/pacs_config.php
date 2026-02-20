<?php

require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../../app/db.php';

function pacs_env(string $key, $default = null) {
  $value = getenv($key);
  if ($value === false || $value === '') {
    return $default;
  }
  return $value;
}

function pacs_setting(string $key, string $default = ''): string {
  static $settings = null;
  if ($settings === null) {
    $rows = db()->query('SELECT `key`,`value` FROM settings')->fetchAll();
    $settings = [];
    foreach ($rows as $r) {
      $settings[$r['key']] = (string)$r['value'];
    }
  }
  $value = $settings[$key] ?? '';
  return $value !== '' ? $value : $default;
}

function pacs_config(): array {
  static $cfg = null;
  if ($cfg !== null) {
    return $cfg;
  }

  $appCfg = config();
  $fromApp = $appCfg['pacs'] ?? [];

  $allowed = pacs_env('PACS_ALLOWED_ROLES', $fromApp['allowed_roles'] ?? 'admin,dokter');
  $allowedRoles = array_values(array_filter(array_map('trim', explode(',', (string)$allowed))));

  $orthancDefault = (string)($fromApp['orthanc_url'] ?? 'http://127.0.0.1:8042');
  $ohifDefault = (string)($fromApp['ohif_base_url'] ?? '');
  $dicomwebDefault = (string)($fromApp['dicomweb_path'] ?? '/dicom-web');

  $cfg = [
    'orthanc_url' => rtrim((string)pacs_env('ORTHANC_URL', pacs_setting('pacs_orthanc_url', $orthancDefault)), '/'),
    'orthanc_user' => (string)pacs_env('ORTHANC_USER', pacs_setting('pacs_orthanc_user', (string)($fromApp['orthanc_user'] ?? ''))),
    'orthanc_pass' => (string)pacs_env('ORTHANC_PASS', pacs_setting('pacs_orthanc_pass', (string)($fromApp['orthanc_pass'] ?? ''))),
    'ohif_base_url' => (string)pacs_env('OHIF_BASE_URL', pacs_setting('pacs_ohif_base_url', $ohifDefault)),
    'token_secret' => (string)pacs_env('PACS_TOKEN_SECRET', $fromApp['token_secret'] ?? ''),
    'token_ttl' => max(30, (int)pacs_env('PACS_TOKEN_TTL_SECONDS', $fromApp['token_ttl'] ?? 120)),
    'allowed_roles' => $allowedRoles ?: ['admin', 'dokter'],
    'max_upload_mb' => max(1, (int)pacs_env('PACS_MAX_UPLOAD_MB', $fromApp['max_upload_mb'] ?? 200)),
    'dicomweb_path' => (string)pacs_env('ORTHANC_DICOMWEB_PATH', pacs_setting('pacs_dicomweb_path', $dicomwebDefault)),
    'issuer' => (string)pacs_env('PACS_TOKEN_ISSUER', $fromApp['issuer'] ?? 'ams'),
  ];

  return $cfg;
}

function pacs_require_role(): void {
  $cfg = pacs_config();
  auth_require_role($cfg['allowed_roles']);
}

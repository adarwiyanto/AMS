<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/controllers/common.php';

if (!defined('PACS_STORAGE')) {
  define('PACS_STORAGE', '/home/adey8293/private_uploads/ams_pacs');
}

if (!function_exists('require_login')) {
  function require_login(): void {
    auth_require();
  }
}

function pacs_require_login(): array {
  require_login();
  $u = auth_user();
  if (!$u) {
    http_response_code(403);
    exit('Forbidden');
  }
  return $u;
}

function pacs_storage_init(): void {
  if (!is_dir(PACS_STORAGE)) {
    @mkdir(PACS_STORAGE, 0755, true);
  }
  if (!is_dir(PACS_STORAGE) || !is_writable(PACS_STORAGE)) {
    throw new RuntimeException('PACS storage path tidak tersedia atau tidak writable: ' . PACS_STORAGE);
  }
}

function pacs_max_upload_bytes(): int {
  $settings = get_settings();
  $maxMb = (int)($settings['pacs_max_upload_mb'] ?? 512);
  if ($maxMb <= 0) {
    $maxMb = 512;
  }
  return $maxMb * 1024 * 1024;
}

function pacs_json_response($data, int $status = 200, string $contentType = 'application/json'): void {
  http_response_code($status);
  header('Content-Type: ' . $contentType);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function pacs_dicom_json_attr($vr, array $value): array {
  return ['vr' => $vr, 'Value' => $value];
}

function pacs_safe_rel_path(string $studyUid, string $seriesUid, string $sopUid): string {
  $cleanStudy = preg_replace('/[^0-9.]/', '_', $studyUid) ?: 'study';
  $cleanSeries = preg_replace('/[^0-9.]/', '_', $seriesUid) ?: 'series';
  $cleanSop = preg_replace('/[^0-9.]/', '_', $sopUid) ?: bin2hex(random_bytes(8));
  return $cleanStudy . '/' . $cleanSeries . '/' . $cleanSop . '.dcm';
}

function pacs_is_probable_dicom(string $path): bool {
  $fh = @fopen($path, 'rb');
  if (!$fh) {
    return false;
  }
  $head = (string)fread($fh, 132);
  fclose($fh);
  if (strlen($head) >= 132 && substr($head, 128, 4) === 'DICM') {
    return true;
  }
  return strtolower((string)pathinfo($path, PATHINFO_EXTENSION)) === 'dcm';
}

function pacs_current_user_or_forbidden(): array {
  return pacs_require_login();
}

function pacs_native_bridge_url(string $studyUid, array $extra = []): string {
  $cfgAll = function_exists('pacs_config_local') ? pacs_config_local() : [];
  $bridge = $cfgAll['native_bridge'] ?? [];
  $protocol = rtrim((string)($bridge['protocol'] ?? 'adena-dicom://open'), '?&');
  $query = array_merge(['study_uid' => $studyUid], $extra);
  return $protocol . ((strpos($protocol, '?') !== false) ? '&' : '?') . http_build_query($query);
}


<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/controllers/common.php';

if (!defined('PACS_STORAGE')) {
  $pacsCfg = function_exists('pacs_config_local') ? pacs_config_local() : [];
  $storagePath = $pacsCfg['storage']['path'] ?? '/home/adey8293/private_uploads/ams_pacs';
  define('PACS_STORAGE', rtrim((string)$storagePath, '/\\'));
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
  $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
  return in_array($ext, ['dcm', 'dicom', 'ima'], true);
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

function pacs_back_href(string $fallback = '/pacs/index.php'): string {
  $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
  if ($ref !== '') {
    return $ref;
  }
  return url($fallback);
}

function pacs_back_button(string $fallback = '/pacs/index.php', string $label = '← Kembali'): string {
  $href = pacs_back_href($fallback);
  return '<div class="pacs-back-wrap no-print" style="margin:0 0 10px"><a class="btn secondary" href="' . e($href) . '">' . e($label) . '</a></div>';
}

function pacs_u16le(string $s, int $o): int {
  if ($o + 2 > strlen($s)) return 0;
  $v = unpack('v', substr($s, $o, 2));
  return (int)($v[1] ?? 0);
}

function pacs_u32le(string $s, int $o): int {
  if ($o + 4 > strlen($s)) return 0;
  $v = unpack('V', substr($s, $o, 4));
  return (int)($v[1] ?? 0);
}

function pacs_clean_dicom_value(string $v): string {
  $v = str_replace("\0", '', $v);
  $v = str_replace('^', ' ', $v);
  return trim($v);
}

function pacs_dicom_is_plausible_uid(string $v): bool {
  return $v !== '' && strlen($v) <= 128 && preg_match('/^[0-9]+(\.[0-9]+)+$/', $v) === 1;
}

function pacs_dicom_scan_value(string $blob, string $tagBytes, string $vrHint = ''): ?string {
  $len = strlen($blob);
  $pos = 0;
  while (($pos = strpos($blob, $tagBytes, $pos)) !== false) {
    // Explicit VR: tag + VR + 16-bit VL
    if ($pos + 8 <= $len) {
      $vr = substr($blob, $pos + 4, 2);
      $vl = pacs_u16le($blob, $pos + 6);
      if (preg_match('/^[A-Z]{2}$/', $vr) && $vl > 0 && $vl < 4096 && $pos + 8 + $vl <= $len) {
        if ($vrHint === '' || $vr === $vrHint || in_array($vrHint, ['UI','LO','SH','PN','DA','CS','DS','IS'], true)) {
          $v = pacs_clean_dicom_value(substr($blob, $pos + 8, $vl));
          if ($v !== '') return $v;
        }
      }
    }
    // Implicit VR: tag + 32-bit VL
    if ($pos + 8 <= $len) {
      $vl = pacs_u32le($blob, $pos + 4);
      if ($vl > 0 && $vl < 4096 && $pos + 8 + $vl <= $len) {
        $v = pacs_clean_dicom_value(substr($blob, $pos + 8, $vl));
        if ($v !== '') return $v;
      }
    }
    $pos += 4;
  }
  return null;
}

function pacs_dicom_scan_u16_value(string $blob, string $tagBytes): ?string {
  $len = strlen($blob);
  $pos = 0;
  while (($pos = strpos($blob, $tagBytes, $pos)) !== false) {
    // Explicit VR US/SS with 16-bit VL.
    if ($pos + 10 <= $len) {
      $vr = substr($blob, $pos + 4, 2);
      $vl = pacs_u16le($blob, $pos + 6);
      if (preg_match('/^[A-Z]{2}$/', $vr) && $vl >= 2 && $vl <= 4 && $pos + 8 + $vl <= $len) {
        return (string)pacs_u16le($blob, $pos + 8);
      }
    }
    // Implicit VR with 32-bit VL.
    if ($pos + 10 <= $len) {
      $vl = pacs_u32le($blob, $pos + 4);
      if ($vl >= 2 && $vl <= 4 && $pos + 8 + $vl <= $len) {
        return (string)pacs_u16le($blob, $pos + 8);
      }
    }
    $pos += 4;
  }
  return null;
}

function pacs_read_dicom_metadata(string $path): array {
  $meta = [];
  $blob = @file_get_contents($path, false, null, 0, 4 * 1024 * 1024);
  if (!is_string($blob) || $blob === '') {
    return $meta;
  }
  $len = strlen($blob);
  $off = ($len >= 132 && substr($blob, 128, 4) === 'DICM') ? 132 : 0;
  $wanted = [
    '0008,0016' => ['SOPClassUID','UI'],
    '0008,0018' => ['SOPInstanceUID','UI'],
    '0008,0020' => ['StudyDate','DA'],
    '0008,0050' => ['AccessionNumber','SH'],
    '0008,0060' => ['Modality','CS'],
    '0008,1030' => ['StudyDescription','LO'],
    '0008,103E' => ['SeriesDescription','LO'],
    '0010,0010' => ['PatientName','PN'],
    '0010,0020' => ['PatientID','LO'],
    '0010,0030' => ['PatientBirthDate','DA'],
    '0010,0040' => ['PatientSex','CS'],
    '0020,000D' => ['StudyInstanceUID','UI'],
    '0020,000E' => ['SeriesInstanceUID','UI'],
    '0020,0011' => ['SeriesNumber','IS'],
    '0020,0013' => ['InstanceNumber','IS'],
    '0028,0002' => ['SamplesPerPixel','US'],
    '0028,0004' => ['PhotometricInterpretation','CS'],
    '0028,0008' => ['NumberOfFrames','IS'],
    '0028,0010' => ['Rows','US'],
    '0028,0011' => ['Columns','US'],
    '0028,0100' => ['BitsAllocated','US'],
    '0028,0101' => ['BitsStored','US'],
    '0028,0103' => ['PixelRepresentation','US'],
    '0028,1050' => ['WindowCenter','DS'],
    '0028,1051' => ['WindowWidth','DS'],
    '0028,1052' => ['RescaleIntercept','DS'],
    '0028,1053' => ['RescaleSlope','DS'],
  ];
  $longVr = ['OB'=>true,'OD'=>true,'OF'=>true,'OL'=>true,'OW'=>true,'SQ'=>true,'UC'=>true,'UR'=>true,'UT'=>true,'UN'=>true];
  $iterations = 0;
  while ($off + 8 <= $len && $iterations++ < 60000) {
    $group = pacs_u16le($blob, $off);
    $elem = pacs_u16le($blob, $off + 2);
    if ($group === 0x7FE0 && $elem === 0x0010) break;
    if ($group === 0 && $elem === 0) { $off++; continue; }
    $tag = sprintf('%04X,%04X', $group, $elem);
    $vr = substr($blob, $off + 4, 2);
    $header = 8;
    $vl = 0;
    if (preg_match('/^[A-Z]{2}$/', $vr)) {
      if (isset($longVr[$vr])) {
        if ($off + 12 > $len) break;
        $vl = pacs_u32le($blob, $off + 8);
        $header = 12;
      } else {
        $vl = pacs_u16le($blob, $off + 6);
        $header = 8;
      }
    } else {
      $vr = '';
      $vl = pacs_u32le($blob, $off + 4);
      $header = 8;
    }
    if ($vl < 0 || $vl > 10000000) { $off++; continue; }
    $valueOff = $off + $header;
    if ($valueOff > $len) break;
    if (isset($wanted[$tag]) && $vl > 0 && $valueOff + min($vl, 4096) <= $len) {
      [$key, $expectedVr] = $wanted[$tag];
      $raw = substr($blob, $valueOff, min($vl, 4096));
      if (in_array($expectedVr, ['US','SS'], true) && $vl <= 4) {
        $meta[$key] = (string)pacs_u16le($raw . "\0\0", 0);
      } else {
        $meta[$key] = pacs_clean_dicom_value($raw);
      }
    }
    $step = $header + $vl + ($vl % 2);
    if ($step <= 0) { $off++; continue; }
    $off += $step;
  }

  // Fallback scanner for vendor files / no preamble / parser alignment issues.
  $scanMap = [
    "\x08\x00\x18\x00" => ['SOPInstanceUID','UI'],
    "\x20\x00\x0D\x00" => ['StudyInstanceUID','UI'],
    "\x20\x00\x0E\x00" => ['SeriesInstanceUID','UI'],
    "\x10\x00\x20\x00" => ['PatientID','LO'],
    "\x10\x00\x10\x00" => ['PatientName','PN'],
    "\x08\x00\x20\x00" => ['StudyDate','DA'],
    "\x08\x00\x60\x00" => ['Modality','CS'],
    "\x08\x00\x30\x10" => ['StudyDescription','LO'],
    "\x08\x00\x3E\x10" => ['SeriesDescription','LO'],
    "\x20\x00\x11\x00" => ['SeriesNumber','IS'],
    "\x20\x00\x13\x00" => ['InstanceNumber','IS'],
    "\x28\x00\x08\x00" => ['NumberOfFrames','IS'],
  ];
  foreach ($scanMap as $tagBytes => [$key, $vr]) {
    if (!empty($meta[$key])) continue;
    $v = pacs_dicom_scan_value($blob, $tagBytes, $vr);
    if ($v !== null) $meta[$key] = $v;
  }
  $u16Map = [
    "\x28\x00\x10\x00" => 'Rows',
    "\x28\x00\x11\x00" => 'Columns',
    "\x28\x00\x00\x01" => 'BitsAllocated',
    "\x28\x00\x01\x01" => 'BitsStored',
    "\x28\x00\x03\x01" => 'PixelRepresentation',
    "\x28\x00\x02\x00" => 'SamplesPerPixel',
  ];
  foreach ($u16Map as $tagBytes => $key) {
    if (!empty($meta[$key])) continue;
    $v = pacs_dicom_scan_u16_value($blob, $tagBytes);
    if ($v !== null) $meta[$key] = $v;
  }

  foreach (['SOPInstanceUID','StudyInstanceUID','SeriesInstanceUID'] as $k) {
    if (!empty($meta[$k])) {
      $meta[$k] = trim((string)$meta[$k], " \t\r\n\0");
    }
  }
  return $meta;
}

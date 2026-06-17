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
  $head = (string)fread($fh, 1024);
  fclose($fh);
  if (strlen($head) >= 132 && substr($head, 128, 4) === 'DICM') {
    return true;
  }
  $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
  if (in_array($ext, ['dcm', 'dicom', 'ima'], true)) {
    return true;
  }
  // Some valid DICOM files do not include the 128-byte preamble + DICM marker.
  // Detect them conservatively from the first data element so ZIP entries without
  // .dcm extensions are not discarded before metadata parsing.
  if (strlen($head) >= 8) {
    $group = pacs_u16le($head, 0);
    $elem = pacs_u16le($head, 2);
    $vr = substr($head, 4, 2);
    $explicit = (bool)preg_match('/^[A-Z]{2}$/', $vr);
    $implicitVl = pacs_u32le($head, 4);
    if (in_array($group, [0x0002, 0x0008, 0x0010, 0x0018, 0x0020, 0x0028], true) && $elem <= 0xFFFF) {
      if ($explicit) {
        return true;
      }
      if ($implicitVl > 0 && $implicitVl < 1048576) {
        return true;
      }
    }
  }
  return false;
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

function pacs_dicom_read_element(string $blob, int $off, bool $explicit, int $len): ?array {
  if ($off + 8 > $len) return null;
  $group = pacs_u16le($blob, $off);
  $elem = pacs_u16le($blob, $off + 2);
  $tag = sprintf('%04X,%04X', $group, $elem);
  $longVr = ['OB'=>true,'OD'=>true,'OF'=>true,'OL'=>true,'OW'=>true,'SQ'=>true,'UC'=>true,'UR'=>true,'UT'=>true,'UN'=>true];
  $vr = '';
  $header = 8;
  $vl = 0;
  if ($explicit) {
    $vr = substr($blob, $off + 4, 2);
    if (!preg_match('/^[A-Z]{2}$/', $vr)) {
      return null;
    }
    if (isset($longVr[$vr])) {
      if ($off + 12 > $len) return null;
      $vl = pacs_u32le($blob, $off + 8);
      $header = 12;
    } else {
      $vl = pacs_u16le($blob, $off + 6);
      $header = 8;
    }
  } else {
    $vl = pacs_u32le($blob, $off + 4);
    $header = 8;
  }
  return ['group'=>$group, 'elem'=>$elem, 'tag'=>$tag, 'vr'=>$vr, 'header'=>$header, 'vl'=>$vl, 'value_off'=>$off + $header];
}

function pacs_dicom_value_to_string(string $raw): string {
  return pacs_clean_dicom_value($raw);
}

function pacs_dicom_value_to_int(string $raw, string $vr = ''): int {
  $raw = str_pad($raw, 4, "\0");
  if ($vr === 'SS') {
    $v = unpack('s', substr($raw, 0, 2));
    return (int)($v[1] ?? 0);
  }
  $text = pacs_clean_dicom_value($raw);
  if ($text !== '' && preg_match('/^-?\d+/', $text, $m)) {
    return (int)$m[0];
  }
  return pacs_u16le($raw, 0);
}

function pacs_read_dicom_metadata(string $path): array {
  $meta = [];
  $blob = @file_get_contents($path, false, null, 0, 8388608);
  if (!is_string($blob) || $blob === '') {
    return $meta;
  }
  $len = strlen($blob);
  $off = ($len >= 132 && substr($blob, 128, 4) === 'DICM') ? 132 : 0;
  $wanted = [
    '0002,0010' => 'TransferSyntaxUID',
    '0008,0016' => 'SOPClassUID',
    '0008,0018' => 'SOPInstanceUID',
    '0008,0020' => 'StudyDate',
    '0008,0050' => 'AccessionNumber',
    '0008,0060' => 'Modality',
    '0008,1030' => 'StudyDescription',
    '0008,103E' => 'SeriesDescription',
    '0010,0010' => 'PatientName',
    '0010,0020' => 'PatientID',
    '0010,0030' => 'PatientBirthDate',
    '0010,0040' => 'PatientSex',
    '0020,000D' => 'StudyInstanceUID',
    '0020,000E' => 'SeriesInstanceUID',
    '0020,0011' => 'SeriesNumber',
    '0020,0013' => 'InstanceNumber',
    '0028,0002' => 'SamplesPerPixel',
    '0028,0004' => 'PhotometricInterpretation',
    '0028,0008' => 'NumberOfFrames',
    '0028,0010' => 'Rows',
    '0028,0011' => 'Columns',
    '0028,0100' => 'BitsAllocated',
    '0028,0101' => 'BitsStored',
    '0028,0103' => 'PixelRepresentation',
    '0028,1050' => 'WindowCenter',
    '0028,1051' => 'WindowWidth',
    '0028,1052' => 'RescaleIntercept',
    '0028,1053' => 'RescaleSlope',
  ];
  $intTags = ['0028,0010'=>true,'0028,0011'=>true,'0028,0100'=>true,'0028,0101'=>true,'0028,0103'=>true,'0020,0011'=>true,'0020,0013'=>true,'0028,0002'=>true];
  $ts = '';
  $datasetExplicit = true;
  $iterations = 0;

  while ($off + 8 <= $len && $iterations++ < 200000) {
    // Group 0002 File Meta Information is always Explicit VR Little Endian.
    $peekGroup = pacs_u16le($blob, $off);
    $explicit = ($peekGroup === 0x0002) ? true : $datasetExplicit;
    $el = pacs_dicom_read_element($blob, $off, $explicit, $len);

    // If a dataset is actually implicit and we guessed explicit, retry as implicit.
    if (!$el && $peekGroup !== 0x0002 && $datasetExplicit) {
      $datasetExplicit = false;
      $el = pacs_dicom_read_element($blob, $off, false, $len);
    }
    if (!$el) break;

    $tag = $el['tag'];
    $vl = (int)$el['vl'];
    $valueOff = (int)$el['value_off'];
    if ($el['group'] === 0x7FE0 && $el['elem'] === 0x0010) break;
    if ($el['group'] === 0 && $el['elem'] === 0) break;
    if ($vl === 0xFFFFFFFF) break;
    if ($vl < 0 || $vl > 100000000) break;
    if ($valueOff > $len) break;

    if (isset($wanted[$tag]) && $vl > 0 && $valueOff + min($vl, 4096) <= $len) {
      $raw = substr($blob, $valueOff, min($vl, 4096));
      if (isset($intTags[$tag]) && $vl <= 16) {
        $meta[$wanted[$tag]] = (string)pacs_dicom_value_to_int($raw, (string)$el['vr']);
      } else {
        $meta[$wanted[$tag]] = pacs_dicom_value_to_string($raw);
      }
      if ($tag === '0002,0010') {
        $ts = trim((string)$meta['TransferSyntaxUID']);
      }
    }

    $step = (int)$el['header'] + $vl + ($vl % 2);
    if ($step <= 0) break;
    $off += $step;

    if ($peekGroup === 0x0002 && $off + 4 <= $len && pacs_u16le($blob, $off) !== 0x0002) {
      // Switch transfer syntax after File Meta Information.
      // 1.2.840.10008.1.2 = Implicit VR Little Endian.
      // 1.2.840.10008.1.2.1 = Explicit VR Little Endian.
      // Encapsulated transfer syntaxes may still have readable metadata, but pixel
      // rendering is intentionally blocked in the browser viewer.
      $datasetExplicit = ($ts !== '1.2.840.10008.1.2');
    }
  }

  if (!empty($meta['WindowCenter'])) $meta['WindowCenter'] = explode('\\', (string)$meta['WindowCenter'])[0];
  if (!empty($meta['WindowWidth'])) $meta['WindowWidth'] = explode('\\', (string)$meta['WindowWidth'])[0];
  if (!empty($meta['NumberOfFrames'])) $meta['NumberOfFrames'] = explode('\\', (string)$meta['NumberOfFrames'])[0];
  return $meta;
}

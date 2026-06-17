<?php
require_once __DIR__ . '/../lib/pacs_bootstrap.php';

$u = pacs_require_login();
if (!is_post()) {
  pacs_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}
csrf_validate();

try {
  pacs_storage_init();
} catch (Throwable $e) {
  pacs_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}

$metaMap = json_decode((string)($_POST['metadata_json'] ?? '{}'), true);
if (!is_array($metaMap)) {
  $metaMap = [];
}

$files = $_FILES['dicom_files'] ?? null;
if (!$files || !is_array($files['name'] ?? null)) {
  pacs_json_response(['ok' => false, 'error' => 'Tidak ada file upload'], 400);
}

$maxBytes = pacs_max_upload_bytes();
$saved = 0;
$skipped = 0;
$errors = [];
$processed = 0;

$count = count($files['name']);
for ($i = 0; $i < $count; $i++) {
  $name = (string)$files['name'][$i];
  $tmp = (string)$files['tmp_name'][$i];
  $size = (int)$files['size'][$i];
  $err = (int)$files['error'][$i];

  if ($err !== UPLOAD_ERR_OK) {
    $errors[] = $name . ': error upload ' . $err;
    continue;
  }
  if ($size > $maxBytes) {
    $errors[] = $name . ': ukuran melebihi batas';
    continue;
  }

  $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
  if ($ext === 'zip') {
    if (!class_exists('ZipArchive')) {
      $errors[] = $name . ': ekstensi PHP ZipArchive belum aktif di server';
      continue;
    }
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
      $errors[] = $name . ': zip invalid';
      continue;
    }
    for ($z = 0; $z < $zip->numFiles; $z++) {
      $entry = (string)$zip->getNameIndex($z);
      if ($entry === '' || substr($entry, -1) === '/' || preg_match('#(^|/)\.|__MACOSX#i', $entry)) {
        continue;
      }
      $stat = $zip->statIndex($z);
      $entrySize = (int)($stat['size'] ?? 0);
      if ($entrySize > $maxBytes) {
        $errors[] = basename($entry) . ': ukuran dalam ZIP melebihi batas';
        continue;
      }
      $stream = $zip->getStream($entry);
      if (!$stream) {
        $errors[] = basename($entry) . ': gagal baca entry ZIP';
        continue;
      }
      $tmpDicom = tempnam(sys_get_temp_dir(), 'pacs_');
      $out = @fopen($tmpDicom, 'wb');
      if (!$out) {
        fclose($stream);
        $errors[] = basename($entry) . ': gagal membuat temporary file';
        continue;
      }
      stream_copy_to_stream($stream, $out);
      fclose($stream);
      fclose($out);
      [$saved, $skipped] = pacs_store_one_file($tmpDicom, basename($entry), $metaMap, $saved, $skipped, $errors);
      $processed++;
      @unlink($tmpDicom);
    }
    $zip->close();
    continue;
  }

  [$saved, $skipped] = pacs_store_one_file($tmp, $name, $metaMap, $saved, $skipped, $errors);
  $processed++;
}

pacs_json_response([
  'ok' => true,
  'saved' => $saved,
  'skipped' => $skipped,
  'processed' => $processed,
  'errors' => $errors,
]);

function pacs_store_one_file(string $tmpPath, string $originalName, array $metaMap, int $saved, int $skipped, array &$errors): array {
  if (!is_file($tmpPath) || filesize($tmpPath) <= 0) {
    $errors[] = $originalName . ': file kosong';
    return [$saved, $skipped];
  }
  if (!pacs_is_probable_dicom($tmpPath)) {
    $errors[] = $originalName . ': bukan DICOM';
    return [$saved, $skipped];
  }

  $parsed = pacs_read_dicom_metadata($tmpPath);
  $meta = array_merge($parsed, is_array($metaMap[$originalName] ?? null) ? $metaMap[$originalName] : []);

  $patientId = trim((string)($meta['PatientID'] ?? 'UNKNOWN'));
  $patientName = trim((string)($meta['PatientName'] ?? ''));
  $birthDate = trim((string)($meta['PatientBirthDate'] ?? ''));
  $sex = trim((string)($meta['PatientSex'] ?? ''));
  $studyUid = trim((string)($meta['StudyInstanceUID'] ?? ''));
  $seriesUid = trim((string)($meta['SeriesInstanceUID'] ?? ''));
  $sopUid = trim((string)($meta['SOPInstanceUID'] ?? ''));
  $modality = trim((string)($meta['Modality'] ?? 'OT')) ?: 'OT';
  $studyDate = trim((string)($meta['StudyDate'] ?? ''));
  $accession = trim((string)($meta['AccessionNumber'] ?? ''));
  $studyDesc = trim((string)($meta['StudyDescription'] ?? ''));
  $seriesDesc = trim((string)($meta['SeriesDescription'] ?? ''));
  $seriesNumber = (int)($meta['SeriesNumber'] ?? 0);
  $instanceNumber = (int)($meta['InstanceNumber'] ?? 0);
  $rows = (int)($meta['Rows'] ?? 0);
  $cols = (int)($meta['Columns'] ?? 0);
  $frames = max(1, (int)($meta['NumberOfFrames'] ?? 1));

  if ($studyUid === '' || $seriesUid === '' || $sopUid === '') {
    $errors[] = $originalName . ': metadata UID tidak dapat dibaca';
    return [$saved, $skipped];
  }

  $pdo = pacs_db();
  $dup = $pdo->prepare('SELECT id FROM pacs_instances WHERE sop_uid = ? LIMIT 1');
  $dup->execute([$sopUid]);
  if ($dup->fetch()) {
    return [$saved, $skipped + 1];
  }

  $relPath = pacs_safe_rel_path($studyUid, $seriesUid, $sopUid);
  $absPath = rtrim(PACS_STORAGE, '/\\') . '/' . $relPath;
  $dir = dirname($absPath);
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }

  if (!@copy($tmpPath, $absPath)) {
    $errors[] = $originalName . ': gagal simpan file';
    return [$saved, $skipped];
  }

  $sha = hash_file('sha256', $absPath) ?: '';
  $fileSize = filesize($absPath) ?: 0;

  $pdo->beginTransaction();
  try {
    $pdo->prepare('INSERT INTO pacs_patients (patient_id, patient_name, birth_date, sex, created_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE patient_name=VALUES(patient_name), birth_date=VALUES(birth_date), sex=VALUES(sex)')
      ->execute([$patientId, $patientName, $birthDate, $sex, now_dt()]);

    $pdo->prepare('INSERT INTO pacs_studies (study_uid, patient_id, study_date, accession, study_desc, modalities, num_series, num_instances, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE patient_id=VALUES(patient_id), study_date=VALUES(study_date), accession=VALUES(accession), study_desc=VALUES(study_desc), modalities=VALUES(modalities), updated_at=VALUES(updated_at)')
      ->execute([$studyUid, $patientId, $studyDate, $accession, $studyDesc, $modality, 0, 0, now_dt(), now_dt()]);

    $pdo->prepare('INSERT INTO pacs_series (series_uid, study_uid, modality, series_number, series_desc, body_part, num_instances) VALUES (?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE modality=VALUES(modality), series_number=VALUES(series_number), series_desc=VALUES(series_desc)')
      ->execute([$seriesUid, $studyUid, $modality, $seriesNumber, $seriesDesc, '', 0]);

    $pdo->prepare('INSERT INTO pacs_instances (sop_uid, series_uid, study_uid, patient_id, instance_number, image_rows, image_cols, frames) VALUES (?,?,?,?,?,?,?,?)')
      ->execute([$sopUid, $seriesUid, $studyUid, $patientId, $instanceNumber, $rows, $cols, $frames]);

    $pdo->prepare('INSERT INTO pacs_files (sop_uid, rel_path, file_size, sha256, created_at) VALUES (?,?,?,?,?)')
      ->execute([$sopUid, $relPath, $fileSize, $sha, now_dt()]);

    $pdo->prepare('UPDATE pacs_series SET num_instances = (SELECT COUNT(*) FROM pacs_instances WHERE series_uid = ?) WHERE series_uid = ?')
      ->execute([$seriesUid, $seriesUid]);
    $pdo->prepare("UPDATE pacs_studies SET num_series = (SELECT COUNT(*) FROM pacs_series WHERE study_uid = ?), num_instances = (SELECT COUNT(*) FROM pacs_instances WHERE study_uid = ?), modalities = (SELECT GROUP_CONCAT(DISTINCT modality ORDER BY modality SEPARATOR '+') FROM pacs_series WHERE study_uid = ?) WHERE study_uid = ?")
      ->execute([$studyUid, $studyUid, $studyUid, $studyUid]);
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    @unlink($absPath);
    $errors[] = $originalName . ': gagal simpan metadata - ' . $e->getMessage();
    return [$saved, $skipped];
  }

  return [$saved + 1, $skipped];
}

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
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
      $errors[] = $name . ': zip invalid';
      continue;
    }
    for ($z = 0; $z < $zip->numFiles; $z++) {
      $entry = (string)$zip->getNameIndex($z);
      if ($entry === '' || substr($entry, -1) === '/') {
        continue;
      }
      $stream = $zip->getStream($entry);
      if (!$stream) {
        continue;
      }
      $blob = stream_get_contents($stream);
      fclose($stream);
      if (!is_string($blob) || $blob === '') {
        continue;
      }
      $tmpDicom = tempnam(sys_get_temp_dir(), 'pacs_');
      file_put_contents($tmpDicom, $blob);
      [$saved, $skipped] = pacs_store_one_file($tmpDicom, basename($entry), $metaMap, $saved, $skipped, $errors);
      @unlink($tmpDicom);
    }
    $zip->close();
    continue;
  }

  [$saved, $skipped] = pacs_store_one_file($tmp, $name, $metaMap, $saved, $skipped, $errors);
}

pacs_json_response([
  'ok' => true,
  'saved' => $saved,
  'skipped' => $skipped,
  'errors' => $errors,
]);

function pacs_store_one_file(string $tmpPath, string $originalName, array $metaMap, int $saved, int $skipped, array &$errors): array {
  if (!pacs_is_probable_dicom($tmpPath)) {
    $errors[] = $originalName . ': bukan DICOM';
    return [$saved, $skipped];
  }

  $meta = $metaMap[$originalName] ?? [];
  $patientId = trim((string)($meta['PatientID'] ?? ''));
  $patientName = trim((string)($meta['PatientName'] ?? ''));
  $studyUid = trim((string)($meta['StudyInstanceUID'] ?? ''));
  $seriesUid = trim((string)($meta['SeriesInstanceUID'] ?? ''));
  $sopUid = trim((string)($meta['SOPInstanceUID'] ?? ''));
  $modality = trim((string)($meta['Modality'] ?? 'OT'));
  $studyDate = trim((string)($meta['StudyDate'] ?? ''));

  if ($studyUid === '' || $seriesUid === '' || $sopUid === '') {
    $errors[] = $originalName . ': metadata UID wajib kosong';
    return [$saved, $skipped];
  }

  $dup = db()->prepare('SELECT id FROM pacs_instances WHERE sop_uid = ? LIMIT 1');
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

  db()->prepare('INSERT INTO pacs_patients (patient_id, patient_name, birth_date, sex, created_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE patient_name=VALUES(patient_name)')
    ->execute([$patientId, $patientName, '', '', now_dt()]);

  db()->prepare('INSERT INTO pacs_studies (study_uid, patient_id, study_date, accession, study_desc, modalities, num_series, num_instances, created_at) VALUES (?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE patient_id=VALUES(patient_id), study_date=VALUES(study_date), modalities=VALUES(modalities), num_instances=num_instances+1')
    ->execute([$studyUid, $patientId, $studyDate, '', '', $modality, 0, 1, now_dt()]);

  db()->prepare('INSERT INTO pacs_series (series_uid, study_uid, modality, series_number, series_desc, body_part, num_instances) VALUES (?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE modality=VALUES(modality), num_instances=num_instances+1')
    ->execute([$seriesUid, $studyUid, $modality, 0, '', '', 1]);

  db()->prepare('UPDATE pacs_studies SET num_series = (SELECT COUNT(*) FROM pacs_series WHERE study_uid = ?), num_instances = (SELECT COUNT(*) FROM pacs_instances WHERE study_uid = ?) + 1 WHERE study_uid = ?')
    ->execute([$studyUid, $studyUid, $studyUid]);

  db()->prepare('INSERT INTO pacs_instances (sop_uid, series_uid, study_uid, patient_id, instance_number, rows, cols, frames) VALUES (?,?,?,?,?,?,?,?)')
    ->execute([$sopUid, $seriesUid, $studyUid, $patientId, 0, 0, 0, 1]);

  db()->prepare('INSERT INTO pacs_files (sop_uid, rel_path, file_size, sha256, created_at) VALUES (?,?,?,?,?)')
    ->execute([$sopUid, $relPath, $fileSize, $sha, now_dt()]);

  return [$saved + 1, $skipped];
}

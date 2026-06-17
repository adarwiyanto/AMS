<?php

require_once __DIR__ . '/pacs_bootstrap.php';

function pacs_process_uploaded_file(string $path, string $originalName, array $metaMap = []): array {
  $maxBytes = pacs_max_upload_bytes();
  $saved = 0;
  $skipped = 0;
  $restored = 0;
  $ignored = 0;
  $errors = [];
  $processed = 0;

  if (!is_file($path) || filesize($path) <= 0) {
    return ['saved' => 0, 'skipped' => 0, 'restored' => 0, 'ignored' => 0, 'processed' => 0, 'errors' => [$originalName . ': file kosong']];
  }

  $size = (int)filesize($path);
  if ($size > $maxBytes) {
    return ['saved' => 0, 'skipped' => 0, 'restored' => 0, 'ignored' => 0, 'processed' => 0, 'errors' => [$originalName . ': ukuran melebihi batas PACS']];
  }

  $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
  if ($ext === 'zip') {
    if (!class_exists('ZipArchive')) {
      return ['saved' => 0, 'skipped' => 0, 'restored' => 0, 'ignored' => 0, 'processed' => 0, 'errors' => [$originalName . ': ekstensi PHP ZipArchive belum aktif di server']];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
      return ['saved' => 0, 'skipped' => 0, 'restored' => 0, 'ignored' => 0, 'processed' => 0, 'errors' => [$originalName . ': zip invalid']];
    }

    for ($z = 0; $z < $zip->numFiles; $z++) {
      $entry = (string)$zip->getNameIndex($z);
      if ($entry === '' || substr($entry, -1) === '/' || preg_match('#(^|/)\.|__MACOSX#i', $entry)) {
        continue;
      }
      $stat = $zip->statIndex($z);
      $entrySize = (int)($stat['size'] ?? 0);
      if ($entrySize > $maxBytes) {
        $errors[] = basename($entry) . ': ukuran dalam ZIP melebihi batas PACS';
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
      [$saved, $skipped, $restored, $ignored, $didProcess] = pacs_store_one_file($tmpDicom, basename($entry), $metaMap, $saved, $skipped, $restored, $ignored, $errors, true);
      if ($didProcess) $processed++;
      @unlink($tmpDicom);
    }
    $zip->close();

    return ['saved' => $saved, 'skipped' => $skipped, 'restored' => $restored, 'ignored' => $ignored, 'processed' => $processed, 'errors' => $errors];
  }

  [$saved, $skipped, $restored, $ignored, $didProcess] = pacs_store_one_file($path, $originalName, $metaMap, $saved, $skipped, $restored, $ignored, $errors, false);
  if ($didProcess) $processed++;

  return ['saved' => $saved, 'skipped' => $skipped, 'restored' => $restored, 'ignored' => $ignored, 'processed' => $processed, 'errors' => $errors];
}

function pacs_merge_upload_results(array $a, array $b): array {
  return [
    'saved' => (int)($a['saved'] ?? 0) + (int)($b['saved'] ?? 0),
    'skipped' => (int)($a['skipped'] ?? 0) + (int)($b['skipped'] ?? 0),
    'restored' => (int)($a['restored'] ?? 0) + (int)($b['restored'] ?? 0),
    'ignored' => (int)($a['ignored'] ?? 0) + (int)($b['ignored'] ?? 0),
    'processed' => (int)($a['processed'] ?? 0) + (int)($b['processed'] ?? 0),
    'errors' => array_merge((array)($a['errors'] ?? []), (array)($b['errors'] ?? [])),
  ];
}

function pacs_store_one_file(string $tmpPath, string $originalName, array $metaMap, int $saved, int $skipped, int $restored, int $ignored, array &$errors, bool $quietNonDicom = false): array {
  if (!is_file($tmpPath) || filesize($tmpPath) <= 0) {
    $errors[] = $originalName . ': file kosong';
    return [$saved, $skipped, $restored, $ignored, false];
  }
  if (!pacs_is_probable_dicom($tmpPath)) {
    if ($quietNonDicom || pacs_is_common_non_dicom_name($originalName)) {
      return [$saved, $skipped, $restored, $ignored + 1, false];
    }
    $errors[] = $originalName . ': bukan DICOM';
    return [$saved, $skipped, $restored, $ignored, false];
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
    return [$saved, $skipped, $restored, $ignored, true];
  }

  $pdo = pacs_db();
  $dup = $pdo->prepare('SELECT i.id, f.rel_path FROM pacs_instances i LEFT JOIN pacs_files f ON f.sop_uid = i.sop_uid WHERE i.sop_uid = ? LIMIT 1');
  $dup->execute([$sopUid]);
  $dupRow = $dup->fetch();
  if ($dupRow) {
    $relPathExisting = trim((string)($dupRow['rel_path'] ?? ''));
    $relPathForRestore = pacs_sanitize_rel_path($relPathExisting) ?: pacs_safe_rel_path($studyUid, $seriesUid, $sopUid);
    $absExisting = rtrim(PACS_STORAGE, '/\\') . '/' . $relPathForRestore;
    if (!is_file($absExisting) || filesize($absExisting) <= 0) {
      $dirExisting = dirname($absExisting);
      if (!is_dir($dirExisting)) {
        @mkdir($dirExisting, 0755, true);
      }
      if (!@copy($tmpPath, $absExisting)) {
        $errors[] = $originalName . ': duplikat ditemukan tetapi file fisik hilang dan gagal dipulihkan';
        return [$saved, $skipped + 1, $restored, $ignored, true];
      }
      $shaExisting = hash_file('sha256', $absExisting) ?: '';
      $sizeExisting = filesize($absExisting) ?: 0;
      $pdo->prepare('INSERT INTO pacs_files (sop_uid, rel_path, file_size, sha256, created_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE rel_path=VALUES(rel_path), file_size=VALUES(file_size), sha256=VALUES(sha256)')
        ->execute([$sopUid, $relPathForRestore, $sizeExisting, $shaExisting, now_dt()]);
      return [$saved, $skipped, $restored + 1, $ignored, true];
    }
    return [$saved, $skipped + 1, $restored, $ignored, true];
  }

  $relPath = pacs_safe_rel_path($studyUid, $seriesUid, $sopUid);
  $absPath = rtrim(PACS_STORAGE, '/\\') . '/' . $relPath;
  $dir = dirname($absPath);
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }

  if (!@copy($tmpPath, $absPath)) {
    $errors[] = $originalName . ': gagal simpan file';
    return [$saved, $skipped, $restored, $ignored, true];
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
    return [$saved, $skipped, $restored, $ignored, true];
  }

  return [$saved + 1, $skipped, $restored, $ignored, true];
}


function pacs_is_common_non_dicom_name(string $name): bool {
  $base = strtolower(basename($name));
  $ext = strtolower((string)pathinfo($base, PATHINFO_EXTENSION));
  if (in_array($ext, ['txt','html','htm','js','css','json','map','xml','md','jpg','jpeg','png','gif','svg','ico','pdf'], true)) {
    return true;
  }
  return in_array($base, ['readme', 'readme.txt', 'license', 'license.txt'], true);
}

function pacs_sanitize_rel_path(string $relPath): string {
  $relPath = trim($relPath);
  $relPath = str_replace(chr(92), '/', $relPath);
  $relPath = ltrim($relPath, '/');
  if ($relPath === '' || strpos($relPath, '..') !== false || preg_match('#(^|/)\.#', $relPath)) {
    return '';
  }
  if (!preg_match('#^[a-zA-Z0-9._/-]+$#', $relPath)) {
    return '';
  }
  return $relPath;
}

function pacs_ini_bytes(string $value): int {
  $value = trim($value);
  if ($value === '') return 0;
  $last = strtolower($value[strlen($value) - 1]);
  $number = (float)$value;
  switch ($last) {
    case 'g': return (int)($number * 1024 * 1024 * 1024);
    case 'm': return (int)($number * 1024 * 1024);
    case 'k': return (int)($number * 1024);
    default: return (int)$number;
  }
}

function pacs_format_bytes(int $bytes): string {
  if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
  if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
  if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
  return $bytes . ' bytes';
}

function pacs_chunk_root(): string {
  return rtrim(PACS_STORAGE, '/\\') . '/_tmp_chunks';
}

function pacs_chunk_user_key(array $user): string {
  $id = (string)($user['id'] ?? $user['username'] ?? $user['email'] ?? session_id() ?: 'user');
  return preg_replace('/[^a-zA-Z0-9_-]/', '_', $id) ?: 'user';
}

function pacs_clean_upload_id(string $uploadId): string {
  $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $uploadId) ?: '';
  if ($uploadId === '' || strlen($uploadId) > 80) {
    throw new RuntimeException('Upload ID tidak valid');
  }
  return $uploadId;
}

function pacs_chunk_dir(array $user, string $uploadId): string {
  $safeId = pacs_clean_upload_id($uploadId);
  return pacs_chunk_root() . '/' . pacs_chunk_user_key($user) . '/' . $safeId;
}

function pacs_rm_dir(string $dir): void {
  if (!is_dir($dir)) return;
  $items = scandir($dir);
  if (!is_array($items)) return;
  foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    $path = $dir . '/' . $item;
    if (is_dir($path)) pacs_rm_dir($path);
    else @unlink($path);
  }
  @rmdir($dir);
}

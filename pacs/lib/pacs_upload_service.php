<?php
require_once __DIR__ . '/pacs_bootstrap.php';

function pacs_upload_empty_result(): array {
  return [
    'saved' => 0,
    'skipped' => 0,
    'restored' => 0,
    'ignored' => 0,
    'processed' => 0,
    'errors' => [],
  ];
}

function pacs_upload_merge_result(array &$target, array $source): void {
  foreach (['saved','skipped','restored','ignored','processed'] as $k) {
    $target[$k] = (int)($target[$k] ?? 0) + (int)($source[$k] ?? 0);
  }
  if (!empty($source['errors']) && is_array($source['errors'])) {
    $target['errors'] = array_merge($target['errors'] ?? [], $source['errors']);
  }
}

function pacs_process_uploaded_file(string $path, string $originalName, array $metaMap = []): array {
  $result = pacs_upload_empty_result();
  if (!is_file($path) || filesize($path) <= 0) {
    $result['errors'][] = $originalName . ': file kosong';
    return $result;
  }

  $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
  if ($ext === 'zip') {
    if (!class_exists('ZipArchive')) {
      $result['errors'][] = $originalName . ': ekstensi PHP ZipArchive belum aktif di server';
      return $result;
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
      $result['errors'][] = $originalName . ': zip invalid';
      return $result;
    }
    $maxBytes = pacs_max_upload_bytes();
    for ($z = 0; $z < $zip->numFiles; $z++) {
      $entry = (string)$zip->getNameIndex($z);
      if ($entry === '' || substr($entry, -1) === '/' || preg_match('#(^|/)(\.|__MACOSX)#i', $entry)) {
        continue;
      }
      $stat = $zip->statIndex($z);
      $entrySize = (int)($stat['size'] ?? 0);
      if ($entrySize <= 0) {
        $result['ignored']++;
        continue;
      }
      if ($entrySize > $maxBytes) {
        $result['errors'][] = basename($entry) . ': ukuran dalam ZIP melebihi batas PACS';
        continue;
      }
      $stream = $zip->getStream($entry);
      if (!$stream) {
        $result['errors'][] = basename($entry) . ': gagal baca entry ZIP';
        continue;
      }
      $tmpDicom = tempnam(sys_get_temp_dir(), 'pacs_');
      $out = @fopen($tmpDicom, 'wb');
      if (!$out) {
        fclose($stream);
        $result['errors'][] = basename($entry) . ': gagal membuat temporary file';
        continue;
      }
      stream_copy_to_stream($stream, $out);
      fclose($stream);
      fclose($out);
      $one = pacs_store_one_dicom_file($tmpDicom, basename($entry), $metaMap);
      pacs_upload_merge_result($result, $one);
      @unlink($tmpDicom);
    }
    $zip->close();
    return $result;
  }

  $one = pacs_store_one_dicom_file($path, $originalName, $metaMap);
  pacs_upload_merge_result($result, $one);
  return $result;
}

function pacs_store_one_dicom_file(string $tmpPath, string $originalName, array $metaMap = []): array {
  $result = pacs_upload_empty_result();
  if (!is_file($tmpPath) || filesize($tmpPath) <= 0) {
    $result['errors'][] = $originalName . ': file kosong';
    return $result;
  }
  if (!pacs_is_probable_dicom($tmpPath)) {
    $result['ignored']++;
    return $result;
  }

  $parsed = pacs_read_dicom_metadata($tmpPath);
  $meta = array_merge($parsed, is_array($metaMap[$originalName] ?? null) ? $metaMap[$originalName] : []);

  $patientId = trim((string)($meta['PatientID'] ?? 'UNKNOWN')) ?: 'UNKNOWN';
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
    $result['errors'][] = $originalName . ': metadata UID tidak dapat dibaca';
    $result['processed']++;
    return $result;
  }

  $pdo = pacs_db();
  $dup = $pdo->prepare('SELECT i.id, f.rel_path FROM pacs_instances i LEFT JOIN pacs_files f ON f.sop_uid=i.sop_uid WHERE i.sop_uid = ? LIMIT 1');
  $dup->execute([$sopUid]);
  $dupRow = $dup->fetch();
  if ($dupRow) {
    $relExisting = trim((string)($dupRow['rel_path'] ?? ''));
    if ($relExisting !== '') {
      $existingAbs = rtrim(PACS_STORAGE, '/\\') . '/' . ltrim($relExisting, '/\\');
      if (is_file($existingAbs) && filesize($existingAbs) > 0) {
        $result['skipped']++;
        $result['processed']++;
        return $result;
      }
    }
    $relPath = pacs_safe_rel_path($studyUid, $seriesUid, $sopUid);
    $absPath = rtrim(PACS_STORAGE, '/\\') . '/' . $relPath;
    if (!is_dir(dirname($absPath))) {
      @mkdir(dirname($absPath), 0755, true);
    }
    if (!@copy($tmpPath, $absPath)) {
      $result['errors'][] = $originalName . ': duplikat ada di database, tetapi file fisik gagal dipulihkan';
      $result['processed']++;
      return $result;
    }
    $sha = hash_file('sha256', $absPath) ?: '';
    $fileSize = filesize($absPath) ?: 0;
    $pdo->prepare('INSERT INTO pacs_files (sop_uid, rel_path, file_size, sha256, created_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE rel_path=VALUES(rel_path), file_size=VALUES(file_size), sha256=VALUES(sha256)')
      ->execute([$sopUid, $relPath, $fileSize, $sha, now_dt()]);
    $result['restored']++;
    $result['processed']++;
    return $result;
  }

  $relPath = pacs_safe_rel_path($studyUid, $seriesUid, $sopUid);
  $absPath = rtrim(PACS_STORAGE, '/\\') . '/' . $relPath;
  $dir = dirname($absPath);
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }

  if (!@copy($tmpPath, $absPath)) {
    $result['errors'][] = $originalName . ': gagal simpan file';
    $result['processed']++;
    return $result;
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

    pacs_recount_study($studyUid, $seriesUid);
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    @unlink($absPath);
    $result['errors'][] = $originalName . ': gagal simpan metadata - ' . $e->getMessage();
    $result['processed']++;
    return $result;
  }

  $result['saved']++;
  $result['processed']++;
  return $result;
}

function pacs_recount_study(string $studyUid, ?string $seriesUid = null): void {
  $pdo = pacs_db();
  if ($seriesUid !== null && $seriesUid !== '') {
    $pdo->prepare('UPDATE pacs_series SET num_instances = (SELECT COUNT(*) FROM pacs_instances WHERE series_uid = ?) WHERE series_uid = ?')
      ->execute([$seriesUid, $seriesUid]);
  }
  $pdo->prepare("UPDATE pacs_studies SET num_series = (SELECT COUNT(*) FROM pacs_series WHERE study_uid = ?), num_instances = (SELECT COUNT(*) FROM pacs_instances WHERE study_uid = ?), modalities = (SELECT GROUP_CONCAT(DISTINCT modality ORDER BY modality SEPARATOR '+') FROM pacs_series WHERE study_uid = ?), updated_at = ? WHERE study_uid = ?")
    ->execute([$studyUid, $studyUid, $studyUid, now_dt(), $studyUid]);
}

function pacs_delete_study_complete(string $studyUid): array {
  $studyUid = trim($studyUid);
  if ($studyUid === '') {
    return ['ok' => false, 'error' => 'Study UID kosong'];
  }
  $pdo = pacs_db();
  $st = $pdo->prepare('SELECT study_uid FROM pacs_studies WHERE study_uid=? LIMIT 1');
  $st->execute([$studyUid]);
  if (!$st->fetch()) {
    return ['ok' => false, 'error' => 'Study tidak ditemukan'];
  }

  $fs = $pdo->prepare('SELECT f.rel_path FROM pacs_files f INNER JOIN pacs_instances i ON i.sop_uid=f.sop_uid WHERE i.study_uid=?');
  $fs->execute([$studyUid]);
  $paths = [];
  foreach ($fs->fetchAll() as $r) {
    $rel = trim((string)($r['rel_path'] ?? ''));
    if ($rel !== '') $paths[] = $rel;
  }

  $pdo->beginTransaction();
  try {
    $pdo->prepare('DELETE f FROM pacs_files f INNER JOIN pacs_instances i ON i.sop_uid=f.sop_uid WHERE i.study_uid=?')->execute([$studyUid]);
    $pdo->prepare('DELETE FROM pacs_instances WHERE study_uid=?')->execute([$studyUid]);
    $pdo->prepare('DELETE FROM pacs_series WHERE study_uid=?')->execute([$studyUid]);
    $pdo->prepare('DELETE FROM pacs_measurements WHERE study_uid=?')->execute([$studyUid]);
    $pdo->prepare('DELETE FROM pacs_reports WHERE study_uid=?')->execute([$studyUid]);
    $pdo->prepare('DELETE FROM pacs_links WHERE study_uid=?')->execute([$studyUid]);
    $pdo->prepare('DELETE FROM pacs_studies WHERE study_uid=?')->execute([$studyUid]);
    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    return ['ok' => false, 'error' => 'Gagal hapus metadata: ' . $e->getMessage()];
  }

  $deletedFiles = 0;
  $base = realpath(PACS_STORAGE) ?: rtrim(PACS_STORAGE, '/\\');
  foreach ($paths as $rel) {
    $abs = rtrim(PACS_STORAGE, '/\\') . '/' . ltrim($rel, '/\\');
    $real = realpath($abs);
    if ($real && strpos($real, $base) === 0 && is_file($real) && @unlink($real)) {
      $deletedFiles++;
    }
  }
  return ['ok' => true, 'deleted_files' => $deletedFiles];
}

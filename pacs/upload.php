<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';

$u = pacs_current_user_or_forbidden();
csrf_validate();

if (!is_post()) {
  redirect('/pacs/index.php');
}

if (empty($_FILES['dicom_file']) || !is_array($_FILES['dicom_file'])) {
  flash_set('err', 'File tidak ditemukan.');
  redirect('/pacs/index.php');
}

$file = $_FILES['dicom_file'];
if ((int)$file['error'] !== UPLOAD_ERR_OK) {
  flash_set('err', 'Upload gagal dengan kode error ' . (int)$file['error']);
  redirect('/pacs/index.php');
}

$cfg = pacs_config();
$maxBytes = (int)$cfg['max_upload_mb'] * 1024 * 1024;
if ((int)$file['size'] > $maxBytes) {
  flash_set('err', 'Ukuran file melebihi batas ' . (int)$cfg['max_upload_mb'] . ' MB.');
  redirect('/pacs/index.php');
}

$filename = (string)$file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ['dcm', 'zip'], true)) {
  flash_set('err', 'Hanya file .dcm atau .zip yang diizinkan.');
  redirect('/pacs/index.php');
}

$uploaded = [];
$tempRoot = sys_get_temp_dir() . '/ams_pacs_' . bin2hex(random_bytes(6));
@mkdir($tempRoot, 0700, true);

try {
  if ($ext === 'dcm') {
    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
      throw new RuntimeException('Gagal membaca file DICOM.');
    }
    $uploaded[] = pacs_orthanc_upload_instance($content);
  } else {
    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
      throw new RuntimeException('Gagal membuka ZIP.');
    }

    $extractDir = $tempRoot . '/extract';
    @mkdir($extractDir, 0700, true);

    for ($i = 0; $i < $zip->numFiles; $i++) {
      $entry = $zip->getNameIndex($i);
      if ($entry === false || substr($entry, -1) === '/') {
        continue;
      }
      if (strpos($entry, '..') !== false) {
        continue;
      }

      $stream = $zip->getStream($entry);
      if (!$stream) {
        continue;
      }
      $content = stream_get_contents($stream);
      fclose($stream);
      if ($content === false || $content === '') {
        continue;
      }

      try {
        $uploaded[] = pacs_orthanc_upload_instance($content);
      } catch (Throwable $inner) {
        continue;
      }
    }

    $zip->close();
  }

  if (!$uploaded) {
    throw new RuntimeException('Tidak ada instance DICOM valid yang berhasil di-upload.');
  }

  $saved = 0;
  foreach ($uploaded as $item) {
    $instanceId = (string)($item['ID'] ?? '');
    $parentStudy = (string)($item['ParentStudy'] ?? '');
    if ($instanceId === '') {
      continue;
    }

    $instanceTags = pacs_orthanc_instance_tags($instanceId);
    $studyTags = $parentStudy !== '' ? pacs_orthanc_study_main_tags($parentStudy) : [];
    $meta = pacs_build_study_meta($instanceTags, $studyTags);
    if (($meta['study_uid'] ?? '') === '') {
      continue;
    }

    db_exec(
      'INSERT INTO pacs_studies (user_id, patient_name, patient_id, study_date, modality, study_uid, orthanc_id, source, created_at) VALUES (?,?,?,?,?,?,?,?,?)',
      [
        (int)$u['id'],
        $meta['patient_name'],
        $meta['patient_id'],
        $meta['study_date'],
        $meta['modality'],
        $meta['study_uid'],
        $parentStudy,
        'upload',
        now_dt(),
      ]
    );

    pacs_audit_log((int)$u['id'], 'UPLOAD', $meta['study_uid']);
    $saved++;
  }

  if ($saved <= 0) {
    throw new RuntimeException('Upload berhasil, namun metadata studi tidak ditemukan.');
  }

  flash_set('ok', 'Upload berhasil. Studi tersimpan: ' . $saved);
} catch (Throwable $e) {
  log_app('error', 'PACS upload failed', ['err' => $e->getMessage()]);
  flash_set('err', 'Upload PACS gagal: ' . $e->getMessage());
}

if (is_dir($tempRoot)) {
  @exec('rm -rf ' . escapeshellarg($tempRoot));
}

redirect('/pacs/index.php');

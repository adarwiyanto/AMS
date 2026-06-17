<?php
require_once __DIR__ . '/../lib/pacs_upload_service.php';

$u = pacs_require_login();
if (!is_post()) {
  pacs_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

csrf_validate();

try {
  pacs_storage_init();
  $uploadId = pacs_clean_upload_id((string)($_POST['upload_id'] ?? ''));
  $dir = pacs_chunk_dir($u, $uploadId);
  $manifestPath = $dir . '/manifest.json';
  if (!is_file($manifestPath)) {
    pacs_json_response(['ok' => false, 'error' => 'Manifest upload tidak ditemukan'], 404);
  }

  $manifest = json_decode((string)file_get_contents($manifestPath), true);
  if (!is_array($manifest)) {
    pacs_json_response(['ok' => false, 'error' => 'Manifest upload invalid'], 400);
  }

  $fileName = basename((string)($manifest['file_name'] ?? 'upload.bin'));
  $fileSize = (int)($manifest['file_size'] ?? 0);
  $totalChunks = (int)($manifest['total_chunks'] ?? 0);
  if ($fileName === '' || $fileSize <= 0 || $totalChunks <= 0 || $totalChunks > 10000) {
    pacs_json_response(['ok' => false, 'error' => 'Data manifest tidak lengkap'], 400);
  }
  if ($fileSize > pacs_max_upload_bytes()) {
    pacs_json_response(['ok' => false, 'error' => 'Ukuran file melebihi batas PACS'], 413);
  }

  for ($i = 0; $i < $totalChunks; $i++) {
    $part = $dir . '/' . sprintf('%06d.part', $i);
    if (!is_file($part)) {
      pacs_json_response(['ok' => false, 'error' => 'Chunk belum lengkap: part ' . ($i + 1) . '/' . $totalChunks], 400);
    }
  }

  $completePath = $dir . '/complete_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
  $out = @fopen($completePath, 'wb');
  if (!$out) {
    throw new RuntimeException('Gagal membuat file gabungan');
  }

  for ($i = 0; $i < $totalChunks; $i++) {
    $part = $dir . '/' . sprintf('%06d.part', $i);
    $in = @fopen($part, 'rb');
    if (!$in) {
      fclose($out);
      throw new RuntimeException('Gagal membaca chunk part ' . ($i + 1));
    }
    stream_copy_to_stream($in, $out);
    fclose($in);
  }
  fclose($out);

  clearstatcache(true, $completePath);
  if ((int)filesize($completePath) !== $fileSize) {
    throw new RuntimeException('Ukuran file gabungan tidak sesuai');
  }

  $metaMap = json_decode((string)($_POST['metadata_json'] ?? '{}'), true);
  if (!is_array($metaMap)) {
    $metaMap = [];
  }

  $result = pacs_process_uploaded_file($completePath, $fileName, $metaMap);
  pacs_rm_dir($dir);

  pacs_json_response([
    'ok' => true,
    'saved' => $result['saved'],
    'skipped' => $result['skipped'],
    'processed' => $result['processed'],
    'errors' => $result['errors'],
  ]);
} catch (Throwable $e) {
  pacs_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}

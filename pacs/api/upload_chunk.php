<?php
require_once __DIR__ . '/../lib/pacs_upload_service.php';

$u = pacs_require_login();
if (!is_post()) {
  pacs_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
$postMaxBytes = pacs_ini_bytes((string)ini_get('post_max_size'));
if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
  pacs_json_response([
    'ok' => false,
    'error' => 'Ukuran chunk melebihi batas server post_max_size (' . pacs_format_bytes($postMaxBytes) . ').'
  ], 413);
}

csrf_validate();

try {
  pacs_storage_init();
  $uploadId = pacs_clean_upload_id((string)($_POST['upload_id'] ?? ''));
  $fileName = basename((string)($_POST['file_name'] ?? 'upload.bin'));
  $chunkIndex = (int)($_POST['chunk_index'] ?? -1);
  $totalChunks = (int)($_POST['total_chunks'] ?? 0);
  $fileSize = (int)($_POST['file_size'] ?? 0);

  if ($fileName === '' || $chunkIndex < 0 || $totalChunks <= 0 || $chunkIndex >= $totalChunks || $totalChunks > 10000 || $fileSize <= 0) {
    pacs_json_response(['ok' => false, 'error' => 'Parameter chunk tidak valid'], 400);
  }

  $maxChunkBytes = 9 * 1024 * 1024;
  $chunk = $_FILES['chunk'] ?? null;
  if (!$chunk || !isset($chunk['tmp_name'])) {
    pacs_json_response(['ok' => false, 'error' => 'Chunk tidak diterima'], 400);
  }
  if ((int)($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    pacs_json_response(['ok' => false, 'error' => 'Error upload chunk: ' . (int)$chunk['error']], 400);
  }
  $chunkSize = (int)($chunk['size'] ?? 0);
  if ($chunkSize <= 0 || $chunkSize > $maxChunkBytes) {
    pacs_json_response(['ok' => false, 'error' => 'Ukuran chunk tidak valid atau lebih dari 9 MB'], 400);
  }

  if ($fileSize > pacs_max_upload_bytes()) {
    pacs_json_response(['ok' => false, 'error' => 'Ukuran file melebihi batas PACS'], 413);
  }

  $dir = pacs_chunk_dir($u, $uploadId);
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  if (!is_dir($dir) || !is_writable($dir)) {
    throw new RuntimeException('Folder temporary chunk tidak writable');
  }

  $manifest = [
    'upload_id' => $uploadId,
    'file_name' => $fileName,
    'file_size' => $fileSize,
    'total_chunks' => $totalChunks,
    'created_at' => time(),
    'updated_at' => time(),
  ];
  $manifestPath = $dir . '/manifest.json';
  if (is_file($manifestPath)) {
    $old = json_decode((string)file_get_contents($manifestPath), true);
    if (is_array($old)) {
      $manifest = array_merge($old, $manifest);
      $manifest['updated_at'] = time();
    }
  }

  $partPath = $dir . '/' . sprintf('%06d.part', $chunkIndex);
  if (!@move_uploaded_file((string)$chunk['tmp_name'], $partPath)) {
    throw new RuntimeException('Gagal menyimpan chunk');
  }
  @file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

  pacs_json_response([
    'ok' => true,
    'upload_id' => $uploadId,
    'chunk_index' => $chunkIndex,
    'total_chunks' => $totalChunks,
  ]);
} catch (Throwable $e) {
  pacs_json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}

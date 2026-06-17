<?php
require_once __DIR__ . '/../lib/pacs_upload_service.php';

$u = pacs_require_login();
if (!is_post()) pacs_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
csrf_validate();

try { pacs_storage_init(); } catch (Throwable $e) { pacs_json_response(['ok' => false, 'error' => $e->getMessage()], 500); }

$uploadId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_POST['upload_id'] ?? ''));
$fileName = basename((string)($_POST['file_name'] ?? 'upload.bin'));
$chunkIndex = (int)($_POST['chunk_index'] ?? -1);
$totalChunks = (int)($_POST['total_chunks'] ?? 0);
$totalSize = (int)($_POST['total_size'] ?? 0);

if ($uploadId === '' || $chunkIndex < 0 || $totalChunks <= 0 || $chunkIndex >= $totalChunks) {
  pacs_json_response(['ok' => false, 'error' => 'Parameter chunk tidak lengkap'], 400);
}
if ($totalSize > pacs_max_upload_bytes()) {
  pacs_json_response(['ok' => false, 'error' => 'Ukuran file melebihi batas PACS'], 400);
}

$chunk = $_FILES['chunk'] ?? null;
if (!$chunk || (int)($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
  pacs_json_response(['ok' => false, 'error' => 'Chunk upload gagal'], 400);
}
if ((int)($chunk['size'] ?? 0) > 8 * 1024 * 1024) {
  pacs_json_response(['ok' => false, 'error' => 'Chunk terlalu besar. Gunakan chunk < 8 MB.'], 400);
}

$uid = (int)($u['id'] ?? 0);
$dir = rtrim(PACS_STORAGE, '/\\') . '/_chunks/' . $uid . '_' . $uploadId;
if (!is_dir($dir)) @mkdir($dir, 0755, true);
if (!is_dir($dir) || !is_writable($dir)) {
  pacs_json_response(['ok' => false, 'error' => 'Folder temporary chunk tidak writable'], 500);
}

$part = $dir . '/' . sprintf('%06d.part', $chunkIndex);
if (!@move_uploaded_file((string)$chunk['tmp_name'], $part)) {
  pacs_json_response(['ok' => false, 'error' => 'Gagal menyimpan chunk'], 500);
}
@file_put_contents($dir . '/meta.json', json_encode([
  'file_name' => $fileName,
  'total_chunks' => $totalChunks,
  'total_size' => $totalSize,
  'user_id' => $uid,
  'created_at' => time(),
], JSON_UNESCAPED_SLASHES));

pacs_json_response(['ok' => true, 'chunk_index' => $chunkIndex]);

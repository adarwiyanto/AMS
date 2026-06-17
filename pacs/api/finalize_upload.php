<?php
require_once __DIR__ . '/../lib/pacs_upload_service.php';

$u = pacs_require_login();
if (!is_post()) pacs_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
csrf_validate();

try { pacs_storage_init(); } catch (Throwable $e) { pacs_json_response(['ok' => false, 'error' => $e->getMessage()], 500); }

$uploadId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_POST['upload_id'] ?? ''));
$fileName = basename((string)($_POST['file_name'] ?? 'upload.bin'));
$totalChunks = (int)($_POST['total_chunks'] ?? 0);
if ($uploadId === '' || $totalChunks <= 0) {
  pacs_json_response(['ok' => false, 'error' => 'Parameter finalize tidak lengkap'], 400);
}

$uid = (int)($u['id'] ?? 0);
$dir = rtrim(PACS_STORAGE, '/\\') . '/_chunks/' . $uid . '_' . $uploadId;
if (!is_dir($dir)) pacs_json_response(['ok' => false, 'error' => 'Folder chunk tidak ditemukan'], 404);

$assembled = $dir . '/assembled_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $fileName);
$out = @fopen($assembled, 'wb');
if (!$out) pacs_json_response(['ok' => false, 'error' => 'Gagal membuat file gabungan'], 500);
for ($i = 0; $i < $totalChunks; $i++) {
  $part = $dir . '/' . sprintf('%06d.part', $i);
  if (!is_file($part)) {
    fclose($out);
    @unlink($assembled);
    pacs_json_response(['ok' => false, 'error' => 'Chunk belum lengkap: ' . ($i + 1) . '/' . $totalChunks], 400);
  }
  $in = @fopen($part, 'rb');
  if (!$in) {
    fclose($out);
    @unlink($assembled);
    pacs_json_response(['ok' => false, 'error' => 'Gagal membaca chunk ' . ($i + 1)], 500);
  }
  stream_copy_to_stream($in, $out);
  fclose($in);
}
fclose($out);

$result = pacs_process_uploaded_file($assembled, $fileName, []);

foreach (glob($dir . '/*') ?: [] as $f) {
  if (is_file($f)) @unlink($f);
}
@rmdir($dir);

pacs_json_response(array_merge(['ok' => true], $result));

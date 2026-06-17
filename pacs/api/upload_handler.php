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
    'error' => 'Ukuran upload melebihi batas server post_max_size (' . pacs_format_bytes($postMaxBytes) . '). Gunakan upload chunk atau naikkan post_max_size di hosting.'
  ], 413);
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

$result = ['saved' => 0, 'skipped' => 0, 'processed' => 0, 'errors' => []];
$count = count($files['name']);
for ($i = 0; $i < $count; $i++) {
  $name = (string)$files['name'][$i];
  $tmp = (string)$files['tmp_name'][$i];
  $err = (int)$files['error'][$i];

  if ($err !== UPLOAD_ERR_OK) {
    $result['errors'][] = $name . ': error upload ' . $err;
    continue;
  }

  $one = pacs_process_uploaded_file($tmp, $name, $metaMap);
  $result = pacs_merge_upload_results($result, $one);
}

pacs_json_response([
  'ok' => true,
  'saved' => $result['saved'],
  'skipped' => $result['skipped'],
  'processed' => $result['processed'],
  'errors' => $result['errors'],
]);

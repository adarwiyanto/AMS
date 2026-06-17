<?php
require_once __DIR__ . '/../lib/pacs_upload_service.php';

pacs_require_login();
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
if (!is_array($metaMap)) $metaMap = [];

$files = $_FILES['dicom_files'] ?? null;
if (!$files || !is_array($files['name'] ?? null)) {
  pacs_json_response(['ok' => false, 'error' => 'Tidak ada file upload'], 400);
}

$result = pacs_upload_empty_result();
$maxBytes = pacs_max_upload_bytes();
$count = count($files['name']);
for ($i = 0; $i < $count; $i++) {
  $name = (string)$files['name'][$i];
  $tmp = (string)$files['tmp_name'][$i];
  $size = (int)$files['size'][$i];
  $err = (int)$files['error'][$i];
  if ($err !== UPLOAD_ERR_OK) {
    $result['errors'][] = $name . ': error upload ' . $err;
    continue;
  }
  if ($size > $maxBytes) {
    $result['errors'][] = $name . ': ukuran melebihi batas PACS';
    continue;
  }
  $one = pacs_process_uploaded_file($tmp, $name, $metaMap);
  pacs_upload_merge_result($result, $one);
}

pacs_json_response(array_merge(['ok' => true], $result));

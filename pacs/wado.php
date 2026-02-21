<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
pacs_require_login();
pacs_storage_init();

$requestType = strtoupper(trim((string)($_GET['requestType'] ?? '')));
$objectUid = trim((string)($_GET['objectUID'] ?? $_GET['sopInstanceUID'] ?? ''));

if ($requestType !== 'WADO' || $objectUid === '') {
  http_response_code(400);
  exit('Bad request');
}

$stmt = db()->prepare('SELECT rel_path FROM pacs_files WHERE sop_uid = ? LIMIT 1');
$stmt->execute([$objectUid]);
$relPath = (string)$stmt->fetchColumn();
if ($relPath === '') {
  http_response_code(404);
  exit('Not found');
}

$full = realpath(rtrim(PACS_STORAGE, '/\\') . '/' . $relPath);
$root = realpath(PACS_STORAGE);
if (!$full || !$root || strpos($full, $root) !== 0 || !is_file($full)) {
  http_response_code(404);
  exit('Not found');
}

header('Content-Type: application/dicom');
header('Content-Length: ' . filesize($full));
readfile($full);

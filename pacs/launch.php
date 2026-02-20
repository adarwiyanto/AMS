<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';

$u = pacs_current_user_or_forbidden();
$studyUid = trim((string)($_GET['study_uid'] ?? ''));
if ($studyUid === '') {
  http_response_code(400);
  exit('StudyInstanceUID wajib diisi.');
}

$cfg = pacs_config();
if ($cfg['ohif_base_url'] === '') {
  http_response_code(500);
  exit('OHIF_BASE_URL belum dikonfigurasi.');
}

$token = pacs_generate_study_token((int)$u['id'], $studyUid, 'orthanc');
pacs_audit_log((int)$u['id'], 'VIEW', $studyUid);

$query = http_build_query([
  'StudyInstanceUIDs' => $studyUid,
  'token' => $token,
  'source' => 'orthanc',
  'dicomWebBase' => url('/pacs/api/dicomweb_proxy.php'),
]);

$target = rtrim($cfg['ohif_base_url'], '?') . (strpos($cfg['ohif_base_url'], '?') === false ? '?' : '&') . $query;
header('Location: ' . $target, true, 302);
exit;

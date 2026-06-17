<?php
require_once __DIR__ . '/../lib/pacs_upload_service.php';

pacs_require_login();
if (!is_post()) pacs_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
csrf_validate();

$studyUid = trim((string)($_POST['study_uid'] ?? ''));
$res = pacs_delete_study_complete($studyUid);
if (empty($res['ok'])) {
  pacs_json_response($res, 400);
}
pacs_json_response($res);

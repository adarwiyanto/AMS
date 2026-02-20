<?php

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/controllers/common.php';
require_once __DIR__ . '/pacs_config.php';
require_once __DIR__ . '/pacs_token.php';
require_once __DIR__ . '/pacs_orthanc.php';
require_once __DIR__ . '/pacs_dicommeta.php';
require_once __DIR__ . '/pacs_audit.php';

function pacs_current_user_or_forbidden(): array {
  auth_require();
  pacs_require_role();
  $u = auth_user();
  if (!$u) {
    http_response_code(403);
    exit('Forbidden');
  }
  return $u;
}

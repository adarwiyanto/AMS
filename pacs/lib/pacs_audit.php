<?php

require_once __DIR__ . '/../../app/db.php';

function pacs_audit_log(int $userId, string $action, string $studyUid = ''): void {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

  pacs_db_exec(
    'INSERT INTO pacs_audit (user_id, action, study_uid, ip, user_agent, created_at) VALUES (?,?,?,?,?,?)',
    [$userId, $action, $studyUid, $ip, $ua, now_dt()]
  );
}

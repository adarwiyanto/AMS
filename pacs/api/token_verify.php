<?php
require_once __DIR__ . '/../lib/pacs_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$token = trim((string)($_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? ''));
if (stripos($token, 'Bearer ') === 0) {
  $token = trim(substr($token, 7));
}

if ($token === '') {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'token_missing']);
  exit;
}

try {
  $payload = pacs_verify_token($token);
  echo json_encode(['ok' => true, 'payload' => $payload], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

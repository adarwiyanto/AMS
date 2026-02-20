<?php
require_once __DIR__ . '/../lib/pacs_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = trim((string)($_GET['path'] ?? ''));
$token = trim((string)($_GET['token'] ?? ''));

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($token === '' && stripos($authHeader, 'Bearer ') === 0) {
  $token = trim(substr($authHeader, 7));
}

if ($token === '') {
  http_response_code(401);
  exit('Missing token');
}

try {
  $claims = pacs_verify_token($token);
} catch (Throwable $e) {
  http_response_code(401);
  exit('Invalid token: ' . $e->getMessage());
}

if ($path === '') {
  $path = trim((string)($_SERVER['PATH_INFO'] ?? ''), '/');
}
if ($path === '') {
  http_response_code(400);
  exit('Missing path');
}

$studyUid = (string)($claims['study_uid'] ?? '');
$cleanPath = '/' . ltrim($path, '/');
if (strpos($cleanPath, '..') !== false) {
  http_response_code(400);
  exit('Invalid path');
}

$allowedPrefixes = ['/studies', '/series', '/instances', '/rendered', '/metadata', '/qido-rs', '/wado-rs'];
$allowed = false;
foreach ($allowedPrefixes as $prefix) {
  if (strpos($cleanPath, $prefix) === 0 || strpos($cleanPath, '/studies') !== false) {
    $allowed = true;
    break;
  }
}
if (!$allowed) {
  http_response_code(403);
  exit('Path not allowed');
}

if (strpos($cleanPath, '/studies') !== false && strpos($cleanPath, rawurlencode($studyUid)) === false && strpos($cleanPath, $studyUid) === false) {
  http_response_code(403);
  exit('Study access denied');
}

$forwardHeaders = [];
foreach (getallheaders() as $k => $v) {
  $lk = strtolower($k);
  if (in_array($lk, ['accept', 'content-type', 'range'], true)) {
    $forwardHeaders[] = $k . ': ' . $v;
  }
}

$body = file_get_contents('php://input');
if ($body === false) {
  $body = null;
}

try {
  $resp = pacs_orthanc_dicomweb_forward($method, $cleanPath, $body, $forwardHeaders);
  http_response_code((int)$resp['status']);

  $contentType = $resp['headers']['content-type'] ?? 'application/octet-stream';
  header('Content-Type: ' . $contentType);
  if (!empty($resp['headers']['content-length'])) {
    header('Content-Length: ' . $resp['headers']['content-length']);
  }

  echo $resp['body'];
} catch (Throwable $e) {
  http_response_code(502);
  exit('Proxy error: ' . $e->getMessage());
}

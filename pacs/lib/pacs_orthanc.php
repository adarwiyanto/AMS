<?php

require_once __DIR__ . '/pacs_config.php';

function pacs_orthanc_request(string $method, string $path, ?string $body = null, array $headers = []): array {
  $cfg = pacs_config();
  $url = $cfg['orthanc_url'] . '/' . ltrim($path, '/');

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
  curl_setopt($ch, CURLOPT_TIMEOUT, 60);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  if ($cfg['orthanc_user'] !== '' || $cfg['orthanc_pass'] !== '') {
    curl_setopt($ch, CURLOPT_USERPWD, $cfg['orthanc_user'] . ':' . $cfg['orthanc_pass']);
  }

  if ($body !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  }

  $responseHeaders = [];
  curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, $headerLine) use (&$responseHeaders) {
    $len = strlen($headerLine);
    $parts = explode(':', $headerLine, 2);
    if (count($parts) === 2) {
      $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
    }
    return $len;
  });

  $resp = curl_exec($ch);
  $err = curl_error($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($resp === false) {
    throw new RuntimeException('Orthanc request failed: ' . $err);
  }

  return ['status' => $status, 'body' => $resp, 'headers' => $responseHeaders];
}

function pacs_orthanc_upload_instance(string $fileContent): array {
  $resp = pacs_orthanc_request('POST', '/instances', $fileContent, ['Content-Type: application/dicom']);
  if ($resp['status'] < 200 || $resp['status'] >= 300) {
    throw new RuntimeException('Upload to Orthanc failed with status ' . $resp['status']);
  }
  $decoded = json_decode($resp['body'], true);
  if (!is_array($decoded)) {
    throw new RuntimeException('Unexpected Orthanc upload response.');
  }
  return $decoded;
}

function pacs_orthanc_instance_tags(string $instanceId): array {
  $resp = pacs_orthanc_request('GET', '/instances/' . rawurlencode($instanceId) . '/simplified-tags');
  if ($resp['status'] >= 200 && $resp['status'] < 300) {
    $decoded = json_decode($resp['body'], true);
    return is_array($decoded) ? $decoded : [];
  }
  return [];
}

function pacs_orthanc_study_main_tags(string $studyId): array {
  $resp = pacs_orthanc_request('GET', '/studies/' . rawurlencode($studyId));
  if ($resp['status'] < 200 || $resp['status'] >= 300) {
    return [];
  }
  $decoded = json_decode($resp['body'], true);
  if (!is_array($decoded)) {
    return [];
  }
  $main = $decoded['MainDicomTags'] ?? [];
  return is_array($main) ? $main : [];
}

function pacs_orthanc_dicomweb_forward(string $method, string $path, ?string $body, array $headers): array {
  $cfg = pacs_config();
  $cleanBasePath = '/' . trim($cfg['dicomweb_path'], '/');
  $forwardPath = $cleanBasePath . '/' . ltrim($path, '/');

  return pacs_orthanc_request($method, $forwardPath, $body, $headers);
}

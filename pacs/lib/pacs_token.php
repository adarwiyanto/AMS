<?php

require_once __DIR__ . '/pacs_config.php';

function pacs_base64url_encode(string $data): string {
  return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function pacs_base64url_decode(string $data): string {
  $pad = strlen($data) % 4;
  if ($pad > 0) {
    $data .= str_repeat('=', 4 - $pad);
  }
  return base64_decode(strtr($data, '-_', '+/')) ?: '';
}

function pacs_sign_token(array $payload): string {
  $cfg = pacs_config();
  if (empty($cfg['token_secret'])) {
    throw new RuntimeException('PACS token secret is not configured.');
  }

  $header = ['alg' => 'HS256', 'typ' => 'JWT'];
  $encHeader = pacs_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
  $encPayload = pacs_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
  $data = $encHeader . '.' . $encPayload;
  $sig = hash_hmac('sha256', $data, $cfg['token_secret'], true);
  return $data . '.' . pacs_base64url_encode($sig);
}

function pacs_generate_study_token(int $userId, string $studyUid, string $source = 'orthanc'): string {
  $cfg = pacs_config();
  $now = time();
  $payload = [
    'iss' => $cfg['issuer'],
    'sub' => $userId,
    'study_uid' => $studyUid,
    'source' => $source,
    'iat' => $now,
    'exp' => $now + (int)$cfg['token_ttl'],
    'nonce' => bin2hex(random_bytes(8)),
  ];
  return pacs_sign_token($payload);
}

function pacs_verify_token(string $token): array {
  $cfg = pacs_config();
  if (empty($cfg['token_secret'])) {
    throw new RuntimeException('PACS token secret is not configured.');
  }

  $parts = explode('.', $token);
  if (count($parts) !== 3) {
    throw new RuntimeException('Malformed token.');
  }

  [$encHeader, $encPayload, $encSig] = $parts;
  $data = $encHeader . '.' . $encPayload;
  $rawSig = pacs_base64url_decode($encSig);
  $calcSig = hash_hmac('sha256', $data, $cfg['token_secret'], true);
  if (!hash_equals($calcSig, $rawSig)) {
    throw new RuntimeException('Invalid signature.');
  }

  $header = json_decode(pacs_base64url_decode($encHeader), true);
  $payload = json_decode(pacs_base64url_decode($encPayload), true);
  if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'HS256') {
    throw new RuntimeException('Invalid token payload.');
  }

  $now = time();
  $exp = (int)($payload['exp'] ?? 0);
  $iat = (int)($payload['iat'] ?? 0);
  if ($exp <= $now) {
    throw new RuntimeException('Token expired.');
  }
  if ($iat <= 0 || $iat > ($now + 30)) {
    throw new RuntimeException('Invalid issued-at.');
  }
  if (($payload['iss'] ?? '') !== $cfg['issuer']) {
    throw new RuntimeException('Invalid issuer.');
  }

  if (empty($payload['sub']) || empty($payload['study_uid']) || empty($payload['source'])) {
    throw new RuntimeException('Required claims missing.');
  }

  return $payload;
}

<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

const AUTH_REMEMBER_COOKIE = 'AMS_REMEMBER';
const AUTH_REMEMBER_LIFETIME = 315360000; // 10 tahun

function session_boot(): void {
  $cfg = config();
  if (session_status() === PHP_SESSION_NONE) {
    session_name($cfg['security']['session_name'] ?? 'AMSSESSID');
    session_start();
  }
}

function auth_cookie_path(): string {
  $path = base_path();
  return $path !== '' ? rtrim($path, '/') . '/' : '/';
}

function auth_request_is_https(): bool {
  if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
  if (($_SERVER['SERVER_PORT'] ?? null) == 443) return true;
  return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function auth_set_remember_cookie(string $value, int $expires): void {
  setcookie(AUTH_REMEMBER_COOKIE, $value, [
    'expires' => $expires,
    'path' => auth_cookie_path(),
    'secure' => auth_request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  if ($value === '') {
    unset($_COOKIE[AUTH_REMEMBER_COOKIE]);
  } else {
    $_COOKIE[AUTH_REMEMBER_COOKIE] = $value;
  }
}

function auth_clear_remember_cookie(): void {
  auth_set_remember_cookie('', time() - 42000);
}

function auth_store_session_user(array $u): void {
  session_regenerate_id(true);
  $_SESSION['user'] = [
    'id' => (int)$u['id'],
    'username' => $u['username'],
    'role' => $u['role'],
    'full_name' => $u['full_name'] ?: $u['username'],
  ];
}

function auth_remember_create(int $userId): bool {
  try {
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);
    $expiresTs = time() + AUTH_REMEMBER_LIFETIME;
    $expiresAt = date('Y-m-d H:i:s', $expiresTs);
    $uaHash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

    db_exec(
      "INSERT INTO auth_remember_tokens (user_id, selector, validator_hash, expires_at, created_at, last_used_at, user_agent_hash) VALUES (?,?,?,?,?,?,?)",
      [$userId, $selector, $validatorHash, $expiresAt, now_dt(), now_dt(), $uaHash]
    );

    auth_set_remember_cookie($selector . ':' . $validator, $expiresTs);
    return true;
  } catch (Throwable $e) {
    log_app('error', 'Remember token creation failed', ['user_id'=>$userId, 'error'=>$e->getMessage()]);
    auth_clear_remember_cookie();
    return false;
  }
}

function auth_remember_revoke_current(): void {
  $raw = (string)($_COOKIE[AUTH_REMEMBER_COOKIE] ?? '');
  if ($raw !== '') {
    $parts = explode(':', $raw, 2);
    $selector = $parts[0] ?? '';
    if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
      try {
        db_exec("DELETE FROM auth_remember_tokens WHERE selector = ?", [$selector]);
      } catch (Throwable $e) {
        log_app('error', 'Remember token revocation failed', ['error'=>$e->getMessage()]);
      }
    }
  }
  auth_clear_remember_cookie();
}

function auth_restore_from_remember_cookie(): ?array {
  $raw = (string)($_COOKIE[AUTH_REMEMBER_COOKIE] ?? '');
  if ($raw === '') return null;

  $parts = explode(':', $raw, 2);
  $selector = $parts[0] ?? '';
  $validator = $parts[1] ?? '';
  if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
    auth_clear_remember_cookie();
    return null;
  }

  try {
    $stmt = db()->prepare(
      "SELECT t.id AS token_id, t.user_id, t.validator_hash, t.expires_at,
              u.id, u.username, u.role, u.full_name
       FROM auth_remember_tokens t
       INNER JOIN users u ON u.id = t.user_id
       WHERE t.selector = ? AND u.is_active = 1
       LIMIT 1"
    );
    $stmt->execute([$selector]);
    $row = $stmt->fetch();

    if (!$row || strtotime((string)$row['expires_at']) <= time() ||
        !hash_equals((string)$row['validator_hash'], hash('sha256', $validator))) {
      // Token salah tidak boleh menghapus token sah pada perangkat lain.
      auth_clear_remember_cookie();
      return null;
    }

    // Token tetap stabil: permintaan paralel/tab lain tidak saling mencabut token.
    // Validator hanya disimpan dalam bentuk hash di database.
    auth_store_session_user($row);
    try {
      db_exec("UPDATE auth_remember_tokens SET last_used_at = ? WHERE id = ?", [now_dt(), (int)$row['token_id']]);
    } catch (Throwable $e) {
      log_app('error', 'Remember token last-used update failed', ['error'=>$e->getMessage()]);
    }
    try {
      db_exec(
        "INSERT INTO audit_logs(created_at, user_id, action, meta) VALUES(?,?,?,?)",
        [now_dt(), (int)$row['user_id'], 'login_remembered', json_encode(['ip'=>$_SERVER['REMOTE_ADDR'] ?? ''], JSON_UNESCAPED_UNICODE)]
      );
    } catch (Throwable $e) {
      log_app('error', 'Remember audit failed', ['error'=>$e->getMessage()]);
    }
    return $_SESSION['user'];
  } catch (Throwable $e) {
    // Gangguan database sementara tidak boleh menghapus cookie login permanen.
    log_app('error', 'Remember token restore failed', ['error'=>$e->getMessage()]);
    return null;
  }
}

function auth_user(): ?array {
  session_boot();
  if (!empty($_SESSION['user'])) return $_SESSION['user'];
  return auth_restore_from_remember_cookie();
}

function auth_require(): void {
  if (!auth_user()) redirect('/login.php');
}

function auth_require_role(array $roles): void {
  auth_require();
  $u = auth_user();
  if (!$u || !in_array($u['role'], $roles, true)) {
    http_response_code(403);
    echo "Forbidden.";
    exit;
  }
}

function auth_login(string $username, string $password, bool $remember = false): bool {
  session_boot();
  $stmt = db()->prepare("SELECT id, username, password_hash, role, full_name FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
  $stmt->execute([$username]);
  $u = $stmt->fetch();
  if (!$u) return false;
  if (!password_verify($password, $u['password_hash'])) return false;

  auth_store_session_user($u);

  // Hapus token perangkat lama sebelum membuat token baru untuk login ini.
  auth_remember_revoke_current();
  if ($remember) auth_remember_create((int)$u['id']);

  db_exec("INSERT INTO audit_logs(created_at, user_id, action, meta) VALUES(?,?,?,?)", [now_dt(), $u['id'], 'login', json_encode(['ip'=>$_SERVER['REMOTE_ADDR'] ?? '', 'remember_me'=>$remember], JSON_UNESCAPED_UNICODE)]);
  return true;
}

function auth_logout(): void {
  session_boot();
  $u = $_SESSION['user']['id'] ?? null;
  if ($u) db_exec("INSERT INTO audit_logs(created_at, user_id, action, meta) VALUES(?,?,?,?)", [now_dt(), $u, 'logout', '{}']);

  auth_remember_revoke_current();
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();
}

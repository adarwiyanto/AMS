<?php
require_once __DIR__ . '/helpers.php';

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $cfg = config()['db'];
  $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $cfg['host'], (int)$cfg['port'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4'
  );

  $opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];

  try {
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $opt);
  } catch (Throwable $e) {
    log_app('error', 'DB connection failed', ['err' => $e->getMessage()]);
    throw $e;
  }
  return $pdo;
}

function db_exec(string $sql, array $params = []): void {
  $stmt = db()->prepare($sql);
  $stmt->execute($params);
}


function pacs_config_local(): array {
  $sample = __DIR__ . '/pacs_config.sample.php';
  $file = __DIR__ . '/pacs_config.php';
  if (file_exists($file)) {
    return require $file;
  }
  return require $sample;
}

function pacs_db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $cfgAll = pacs_config_local();
  $cfg = $cfgAll['db'] ?? [];
  $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $cfg['host'] ?? '127.0.0.1', (int)($cfg['port'] ?? 3306), $cfg['name'] ?? 'adey8293_pacs', $cfg['charset'] ?? 'utf8mb4'
  );

  $opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ];

  try {
    $pdo = new PDO($dsn, $cfg['user'] ?? '', $cfg['pass'] ?? '', $opt);
  } catch (Throwable $e) {
    log_app('error', 'PACS DB connection failed', ['err' => $e->getMessage()]);
    throw $e;
  }
  return $pdo;
}

function pacs_db_exec(string $sql, array $params = []): void {
  $stmt = pacs_db()->prepare($sql);
  $stmt->execute($params);
}

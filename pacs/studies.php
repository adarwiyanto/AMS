<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
pacs_require_login();
$q = trim((string)($_GET['q'] ?? ''));
$target = '/pacs/upload.php#studies';
if ($q !== '') $target = '/pacs/upload.php?q=' . rawurlencode($q) . '#studies';
redirect($target);

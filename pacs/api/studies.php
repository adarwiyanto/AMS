<?php
require_once __DIR__ . '/../lib/pacs_bootstrap.php';

$u = pacs_current_user_or_forbidden();
header('Content-Type: application/json; charset=utf-8');

$limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$st = db()->prepare('SELECT patient_name, patient_id, study_date, modality, study_uid, source, created_at FROM pacs_studies WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . $limit);
$st->execute([(int)$u['id']]);
$rows = $st->fetchAll();

echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

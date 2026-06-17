<?php
require_once __DIR__ . '/../lib/pacs_bootstrap.php';

pacs_current_user_or_forbidden();
header('Content-Type: application/json; charset=utf-8');

$limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$st = pacs_db()->query('SELECT s.study_uid, s.patient_id, p.patient_name, s.study_date, s.modalities, s.study_desc, s.num_series, s.num_instances, s.created_at FROM pacs_studies s LEFT JOIN pacs_patients p ON p.patient_id=s.patient_id ORDER BY s.created_at DESC LIMIT ' . $limit);
$rows = $st->fetchAll();

echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

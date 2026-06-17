<?php
require_once __DIR__ . '/../lib/pacs_bootstrap.php';

pacs_current_user_or_forbidden();
header('Content-Type: application/json; charset=utf-8');

$limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
  $like = '%' . $q . '%';
  $st = pacs_db()->prepare('SELECT s.study_uid, s.patient_id, p.patient_name, s.study_date, s.modalities, s.study_desc, s.num_series, s.num_instances, s.created_at FROM pacs_studies s LEFT JOIN pacs_patients p ON p.patient_id=s.patient_id WHERE s.patient_id LIKE ? OR p.patient_name LIKE ? OR s.study_uid LIKE ? OR s.study_desc LIKE ? ORDER BY s.created_at DESC LIMIT ' . $limit);
  $st->execute([$like, $like, $like, $like]);
} else {
  $st = pacs_db()->query('SELECT s.study_uid, s.patient_id, p.patient_name, s.study_date, s.modalities, s.study_desc, s.num_series, s.num_instances, s.created_at FROM pacs_studies s LEFT JOIN pacs_patients p ON p.patient_id=s.patient_id ORDER BY s.created_at DESC LIMIT ' . $limit);
}
$rows = $st->fetchAll();

echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

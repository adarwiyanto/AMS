<?php
require_once __DIR__ . '/../lib/pacs_bootstrap.php';
pacs_require_login();
$studyUid = trim((string)($_GET['study_uid'] ?? ''));
if ($studyUid === '') {
  pacs_json_response(['ok' => false, 'error' => 'StudyInstanceUID wajib diisi'], 400);
}
$stmt = pacs_db()->prepare('SELECT s.*, p.patient_name, p.birth_date, p.sex FROM pacs_studies s LEFT JOIN pacs_patients p ON p.patient_id=s.patient_id WHERE s.study_uid=? LIMIT 1');
$stmt->execute([$studyUid]);
$study = $stmt->fetch();
if (!$study) {
  pacs_json_response(['ok' => false, 'error' => 'Study tidak ditemukan'], 404);
}
$ss = pacs_db()->prepare('SELECT * FROM pacs_series WHERE study_uid=? ORDER BY series_number ASC, id ASC');
$ss->execute([$studyUid]);
$seriesRows = $ss->fetchAll();
$series = [];
foreach ($seriesRows as $sr) {
  $is = pacs_db()->prepare('SELECT i.*, f.file_size FROM pacs_instances i LEFT JOIN pacs_files f ON f.sop_uid=i.sop_uid WHERE i.series_uid=? ORDER BY COALESCE(i.instance_number,0) ASC, i.id ASC');
  $is->execute([(string)$sr['series_uid']]);
  $instances = [];
  foreach ($is->fetchAll() as $ir) {
    $instances[] = [
      'sop_uid' => (string)$ir['sop_uid'],
      'instance_number' => (int)($ir['instance_number'] ?? 0),
      'rows' => (int)($ir['image_rows'] ?? 0),
      'cols' => (int)($ir['image_cols'] ?? 0),
      'frames' => (int)($ir['frames'] ?? 1),
      'file_size' => (int)($ir['file_size'] ?? 0),
      'wado_url' => url('/pacs/wado.php?requestType=WADO&objectUID=' . rawurlencode((string)$ir['sop_uid'])),
    ];
  }
  $series[] = [
    'series_uid' => (string)$sr['series_uid'],
    'modality' => (string)($sr['modality'] ?? ''),
    'series_number' => (int)($sr['series_number'] ?? 0),
    'series_desc' => (string)($sr['series_desc'] ?? ''),
    'num_instances' => (int)($sr['num_instances'] ?? count($instances)),
    'instances' => $instances,
  ];
}
pacs_json_response([
  'ok' => true,
  'study' => [
    'study_uid' => (string)$study['study_uid'],
    'patient_id' => (string)($study['patient_id'] ?? ''),
    'patient_name' => (string)($study['patient_name'] ?? ''),
    'birth_date' => (string)($study['birth_date'] ?? ''),
    'sex' => (string)($study['sex'] ?? ''),
    'study_date' => (string)($study['study_date'] ?? ''),
    'study_desc' => (string)($study['study_desc'] ?? ''),
    'modalities' => (string)($study['modalities'] ?? ''),
  ],
  'series' => $series,
]);

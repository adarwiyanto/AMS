<?php
require_once __DIR__ . '/../lib/pacs_bootstrap.php';

pacs_require_login();

$pathInfo = (string)($_SERVER['PATH_INFO'] ?? '');
$uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
if ($pathInfo === '' && preg_match('#/pacs/dicomweb(?:/index\.php)?(.*)$#', $uriPath, $m)) {
  $pathInfo = $m[1] ?: '';
}
$pathInfo = '/' . ltrim($pathInfo, '/');
$parts = array_values(array_filter(explode('/', trim($pathInfo, '/')), 'strlen'));

header('Content-Type: application/dicom+json');

if (!$parts || $parts[0] !== 'studies') {
  echo '[]';
  exit;
}

if (count($parts) === 1) {
  $where = [];
  $params = [];
  $patientName = trim((string)($_GET['PatientName'] ?? ''));
  $patientId = trim((string)($_GET['PatientID'] ?? ''));
  if ($patientName !== '') {
    $where[] = 'p.patient_name LIKE ?';
    $params[] = '%' . $patientName . '%';
  }
  if ($patientId !== '') {
    $where[] = 's.patient_id LIKE ?';
    $params[] = '%' . $patientId . '%';
  }
  $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
  $offset = max(0, (int)($_GET['offset'] ?? 0));

  $sql = 'SELECT s.*, p.patient_name FROM pacs_studies s LEFT JOIN pacs_patients p ON p.patient_id=s.patient_id';
  if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  $sql .= ' ORDER BY s.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();

  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      '0020000D' => pacs_dicom_json_attr('UI', [(string)$r['study_uid']]),
      '00100020' => pacs_dicom_json_attr('LO', [(string)$r['patient_id']]),
      '00100010' => pacs_dicom_json_attr('PN', [['Alphabetic' => (string)($r['patient_name'] ?? '')]]),
      '00080020' => pacs_dicom_json_attr('DA', [(string)$r['study_date']]),
      '00080061' => pacs_dicom_json_attr('CS', [(string)$r['modalities']]),
      '00201206' => pacs_dicom_json_attr('IS', [(string)$r['num_series']]),
      '00201208' => pacs_dicom_json_attr('IS', [(string)$r['num_instances']]),
    ];
  }
  echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

$studyUid = rawurldecode($parts[1] ?? '');
if (count($parts) === 3 && $parts[2] === 'series') {
  $stmt = db()->prepare('SELECT * FROM pacs_series WHERE study_uid = ? ORDER BY series_number ASC, id ASC');
  $stmt->execute([$studyUid]);
  $rows = $stmt->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      '0020000E' => pacs_dicom_json_attr('UI', [(string)$r['series_uid']]),
      '00080060' => pacs_dicom_json_attr('CS', [(string)$r['modality']]),
      '00200011' => pacs_dicom_json_attr('IS', [(string)$r['series_number']]),
      '0008103E' => pacs_dicom_json_attr('LO', [(string)$r['series_desc']]),
    ];
  }
  echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

if (count($parts) === 5 && $parts[2] === 'series' && $parts[4] === 'instances') {
  $seriesUid = rawurldecode($parts[3]);
  $stmt = db()->prepare('SELECT * FROM pacs_instances WHERE study_uid = ? AND series_uid = ? ORDER BY instance_number ASC, id ASC');
  $stmt->execute([$studyUid, $seriesUid]);
  $rows = $stmt->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      '00080018' => pacs_dicom_json_attr('UI', [(string)$r['sop_uid']]),
      '00200013' => pacs_dicom_json_attr('IS', [(string)$r['instance_number']]),
    ];
  }
  echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

echo '[]';

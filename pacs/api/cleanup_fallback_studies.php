<?php
require_once __DIR__ . '/../lib/pacs_bootstrap.php';
pacs_require_login();
pacs_storage_init();

$confirm = trim((string)($_POST['confirm'] ?? $_GET['confirm'] ?? ''));
if ($confirm !== '1') {
  pacs_json_response([
    'ok' => false,
    'error' => 'Tambahkan confirm=1 untuk membersihkan study fallback PACS yang salah terbaca.',
    'usage' => url('/pacs/api/cleanup_fallback_studies.php?confirm=1'),
  ], 400);
}

$pdo = pacs_db();
$pdo->beginTransaction();
try {
  $st = $pdo->query("SELECT study_uid FROM pacs_studies WHERE patient_id='UNKNOWN' AND study_uid LIKE '2.25.%'");
  $studyUids = array_map(static fn($r) => (string)$r['study_uid'], $st->fetchAll());
  $deletedFiles = 0;
  $deletedInstances = 0;
  $deletedSeries = 0;
  $deletedStudies = 0;
  $physicalDeleted = 0;

  foreach ($studyUids as $studyUid) {
    $sopStmt = $pdo->prepare('SELECT i.sop_uid, f.rel_path FROM pacs_instances i LEFT JOIN pacs_files f ON f.sop_uid=i.sop_uid WHERE i.study_uid=?');
    $sopStmt->execute([$studyUid]);
    $sopRows = $sopStmt->fetchAll();
    $sops = [];
    foreach ($sopRows as $row) {
      $sop = (string)$row['sop_uid'];
      if ($sop !== '') $sops[] = $sop;
      $rel = trim((string)($row['rel_path'] ?? ''));
      if ($rel !== '') {
        $full = realpath(rtrim(PACS_STORAGE, '/\\') . '/' . $rel);
        $root = realpath(PACS_STORAGE);
        if ($full && $root && strpos($full, $root) === 0 && is_file($full)) {
          if (@unlink($full)) $physicalDeleted++;
        }
      }
    }
    if ($sops) {
      $ph = implode(',', array_fill(0, count($sops), '?'));
      $delFiles = $pdo->prepare("DELETE FROM pacs_files WHERE sop_uid IN ($ph)");
      $delFiles->execute($sops);
      $deletedFiles += $delFiles->rowCount();
    }
    $delI = $pdo->prepare('DELETE FROM pacs_instances WHERE study_uid=?');
    $delI->execute([$studyUid]);
    $deletedInstances += $delI->rowCount();
    $delS = $pdo->prepare('DELETE FROM pacs_series WHERE study_uid=?');
    $delS->execute([$studyUid]);
    $deletedSeries += $delS->rowCount();
    $delSt = $pdo->prepare('DELETE FROM pacs_studies WHERE study_uid=?');
    $delSt->execute([$studyUid]);
    $deletedStudies += $delSt->rowCount();
  }
  $pdo->commit();
  pacs_json_response([
    'ok' => true,
    'message' => 'Cleanup fallback PACS selesai. Upload ulang ZIP agar StudyInstanceUID asli terbaca.',
    'deleted_studies' => $deletedStudies,
    'deleted_series' => $deletedSeries,
    'deleted_instances' => $deletedInstances,
    'deleted_file_rows' => $deletedFiles,
    'deleted_physical_files' => $physicalDeleted,
  ]);
} catch (Throwable $e) {
  $pdo->rollBack();
  pacs_json_response(['ok' => false, 'error' => 'Cleanup gagal: ' . $e->getMessage()], 500);
}

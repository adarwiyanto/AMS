<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/helpers.php';
auth_require_role(['admin','dokter','perawat']);
header('Cache-Control: private, no-store');
header('Content-Type: text/html; charset=utf-8');
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID pasien tidak valid.'); }
$st = db()->prepare('SELECT id, mrn, full_name FROM patients WHERE id=? LIMIT 1');
$st->execute([$id]); $patient = $st->fetch();
if (!$patient) { http_response_code(404); exit('Pasien tidak ditemukan.'); }
$st = db()->prepare('SELECT v.id,v.visit_no,v.visit_date,u.full_name AS doctor_name FROM visits v LEFT JOIN users u ON u.id=v.doctor_id WHERE v.patient_id=? ORDER BY v.visit_date DESC,v.id DESC LIMIT 100');
$st->execute([$id]); $rows=$st->fetchAll();
?>
<div class="ams-history-patient"><strong><?= e($patient['full_name']) ?></strong><span>No. RM: <?= e($patient['mrn']) ?></span></div>
<?php if (!$rows): ?><p class="muted">Belum ada riwayat kunjungan.</p><?php else: ?>
<div class="ams-history-workspace">
 <div class="ams-history-picker" role="listbox" aria-label="Pilih kunjungan">
 <?php foreach ($rows as $n=>$v): ?>
  <button type="button" class="ams-history-choice<?= $n===0?' selected':'' ?>" data-history-visit="<?= (int)$v['id'] ?>" aria-selected="<?= $n===0?'true':'false' ?>">
   <strong><?= e(date('d/m/Y H:i',strtotime($v['visit_date']))) ?></strong>
   <small><?= e($v['visit_no']) ?> · <?= e($v['doctor_name'] ?: '-') ?></small>
  </button>
 <?php endforeach; ?>
 </div>
 <div class="ams-history-document">
  <div class="ams-history-toolbar">
   <span id="amsHistoryStatus" class="muted">Preview dokumen</span>
   <div class="ams-history-toolbar-actions">
    <a id="amsHistoryEdit" class="btn small secondary" href="#">Edit</a>
    <a id="amsHistoryPrint" class="btn small secondary" target="_blank" rel="noopener" href="#">Print A4</a>
    <a id="amsHistoryPdf" class="btn small" target="_blank" rel="noopener" href="#">PDF Kontinu</a>
   </div>
  </div>
  <iframe id="amsHistoryFrame" title="Preview hasil pemeriksaan" loading="lazy"></iframe>
 </div>
</div>
<?php if(count($rows)===100): ?><p class="muted">Menampilkan 100 kunjungan terbaru.</p><?php endif; ?>
<?php endif; ?>

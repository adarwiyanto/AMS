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
$st->execute([$id]);
$patient = $st->fetch();
if (!$patient) { http_response_code(404); exit('Pasien tidak ditemukan.'); }
$st = db()->prepare('SELECT v.id, v.visit_no, v.visit_date, v.anamnesis, v.physical_exam, v.usg_report, v.diagnosis, v.therapy, u.full_name AS doctor_name FROM visits v LEFT JOIN users u ON u.id=v.doctor_id WHERE v.patient_id=? ORDER BY v.visit_date DESC, v.id DESC LIMIT 100');
$st->execute([$id]);
$rows = $st->fetchAll();
?>
<div class="ams-history-patient"><strong><?= e($patient['full_name']) ?></strong><span>No. RM: <?= e($patient['mrn']) ?></span></div>
<?php if (!$rows): ?><p class="muted">Belum ada riwayat kunjungan.</p><?php else: ?>
<div class="ams-history-list">
<?php foreach ($rows as $v): ?>
  <details class="ams-history-item">
    <summary><span><strong><?= e(date('d/m/Y', strtotime($v['visit_date']))) ?></strong><small><?= e($v['visit_no']) ?> · <?= e($v['doctor_name'] ?: '-') ?></small></span><span class="ams-history-hint">Lihat detail ▾</span></summary>
    <div class="ams-history-detail">
      <?php foreach (['Anamnesis'=>'anamnesis','Pemeriksaan Fisik'=>'physical_exam','Hasil USG'=>'usg_report','Diagnosis'=>'diagnosis','Terapi'=>'therapy'] as $label=>$field): ?>
      <div><b><?= e($label) ?></b><p><?= nl2br(e((string)($v[$field] ?: '-'))) ?></p></div>
      <?php endforeach; ?>
      <div class="ams-history-actions"><a class="btn small secondary" href="<?= e(url('/visit_edit.php?id='.(int)$v['id'])) ?>">Detail / Edit</a><a class="btn small" target="_blank" rel="noopener" href="<?= e(url('/print_visit.php?id='.(int)$v['id'])) ?>">Cetak Hasil</a></div>
    </div>
  </details>
<?php endforeach; ?>
</div>
<?php if (count($rows)===100): ?><p class="muted">Menampilkan 100 kunjungan terbaru.</p><?php endif; ?>
<?php endif; ?>

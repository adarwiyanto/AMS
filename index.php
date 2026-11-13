<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$nextMonth = date('Y-m-01', strtotime('first day of next month'));
$dashboard = [
  'patients' => (int)db()->query('SELECT COUNT(*) FROM patients')->fetchColumn(),
  'visits_today' => 0,
  'queue_today' => 0,
  'visits_month' => 0,
];
$st=db()->prepare('SELECT COUNT(*) FROM visits WHERE visit_date>=? AND visit_date<?');
$st->execute([$today, date('Y-m-d',strtotime('+1 day'))]);
$dashboard['visits_today']=(int)$st->fetchColumn();
$st=db()->prepare('SELECT COUNT(*) FROM visit_queue WHERE queue_date=?');
$st->execute([$today]);$dashboard['queue_today']=(int)$st->fetchColumn();
$st=db()->prepare('SELECT COUNT(*) FROM visits WHERE visit_date>=? AND visit_date<?');
$st->execute([$monthStart,$nextMonth]);$dashboard['visits_month']=(int)$st->fetchColumn();
$recent = db()->query('SELECT v.id,v.visit_date,v.visit_no,p.full_name,p.mrn FROM visits v JOIN patients p ON p.id=v.patient_id ORDER BY v.visit_date DESC,v.id DESC LIMIT 8')->fetchAll();
$title = "Dashboard";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="ams-dashboard-heading"><div><h1>Dashboard</h1><p class="muted">Ringkasan aktivitas praktik · <?= e(date('d/m/Y')) ?></p></div><div class="ams-dashboard-actions"><a class="btn small" href="<?= e(url('/patients.php')) ?>">+ Pasien</a><a class="btn small secondary" href="<?= e(url('/visits.php?new=1')) ?>">+ Kunjungan</a></div></div>
<div class="ams-metrics">
<?php foreach ([['Total Pasien','patients','♙'],['Kunjungan Hari Ini','visits_today','✚'],['Pendaftaran Hari Ini','queue_today','▦'],['Kunjungan Bulan Ini','visits_month','▤']] as $metric): ?>
  <div class="ams-metric"><span class="ams-metric-icon" aria-hidden="true"><?= $metric[2] ?></span><div class="ams-metric-label"><?= e($metric[0]) ?></div><div class="ams-metric-value"><?= number_format($dashboard[$metric[1]],0,',','.') ?></div></div>
<?php endforeach; ?>
</div>
<div class="card"><div class="ams-section-title"><div class="h1" style="font-size:16px">Kunjungan Terbaru</div><a class="btn small secondary" href="<?= e(url('/visits.php')) ?>">Lihat Kunjungan</a></div>
<div class="ams-table-scroll"><table class="table"><thead><tr><th>Tanggal</th><th>Nama Pasien</th><th>No. RM</th><th>No. Kunjungan</th></tr></thead><tbody>
<?php foreach($recent as $r): ?><tr><td><?= e(date('d/m/Y',strtotime($r['visit_date']))) ?></td><td><?= e($r['full_name']) ?></td><td><?= e($r['mrn']) ?></td><td><?= e($r['visit_no']) ?></td></tr><?php endforeach; ?>
<?php if(!$recent): ?><tr><td colspan="4" class="muted">Belum ada kunjungan.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/app/views/partials/footer.php'; ?>

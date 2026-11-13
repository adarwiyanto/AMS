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
$title = "Dashboard";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="ams-dashboard-preview">
  <div class="ams-welcome"><h1>Selamat Datang, Dokter</h1><p>Sistem Manajemen Pasien dan Pemeriksaan Radiologi</p></div>
  <div class="ams-preview-metrics">
  <?php foreach ([
    ['Total Pasien','patients','blue','patients'],
    ['Kunjungan Hari Ini','visits_today','green','calendar'],
    ['Pendaftaran Hari Ini','queue_today','orange','newpatient'],
    ['Kunjungan Bulan Ini','visits_month','purple','month']
  ] as $metric): ?>
    <div class="ams-preview-metric ams-metric-<?= e($metric[2]) ?>">
      <div class="ams-preview-metric-title"><span class="ams-preview-metric-symbol ams-symbol-<?= e($metric[3]) ?>" aria-hidden="true"></span><span><?= e($metric[0]) ?></span></div>
      <div class="ams-preview-metric-number"><?= number_format($dashboard[$metric[1]],0,',','.') ?></div>
      <span class="ams-metric-watermark ams-symbol-<?= e($metric[3]) ?>" aria-hidden="true"></span>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/app/views/partials/footer.php'; ?>

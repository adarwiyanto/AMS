<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT v.*, p.mrn, p.full_name, p.dob, p.gender, p.address,
                            u.full_name AS doctor_name, u.signature_path AS doctor_signature_path
                     FROM visits v
                     JOIN patients p ON p.id=v.patient_id
                     LEFT JOIN users u ON u.id=v.doctor_id
                     WHERE v.id=?");
$st->execute([$id]);
$v = $st->fetch();
if (!$v) { http_response_code(404); echo "Not found"; exit; }

$clinicName = $settings['clinic_name'] ?? ($settings['brand_title'] ?? 'Praktek dr. Agus');
$clinicAddr = $settings['clinic_address'] ?? '';
$clinicSip  = $settings['clinic_sip'] ?? '';
$logo = $settings['logo_path'] ?? '';

$sig_global = $settings['signature_path'] ?? '';
$sig_doctor = $v['doctor_signature_path'] ?? '';
$sig_visit  = $v['signature_path'] ?? '';
$sig = $sig_visit ?: ($sig_doctor ?: $sig_global);

$age = age_from_dob($v['dob']);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Print - <?= e($v['visit_no']) ?></title>
  <link rel="stylesheet" href="<?= e(url('/public/assets/css/theme.css')) ?>">
  <style>
    body{background:#fff;color:#000}
    .paper{max-width:820px;margin:0 auto;padding:20px}
    .kop{display:flex;gap:12px;align-items:center;border-bottom:1px solid #ddd;padding-bottom:12px;margin-bottom:12px}
    .kop img{width:64px;height:64px;object-fit:contain}
    .kop .t1{font-size:18px;font-weight:800}
    .kop .t2{font-size:12px;color:#333}
    .block{margin-top:10px}
    .label2{font-size:12px;color:#333;font-weight:700;margin-bottom:4px}
    pre{white-space:pre-wrap;font-family:inherit;margin:0}
    .sign{margin-top:24px;display:flex;justify-content:flex-end}
    .signbox{text-align:center}
    .signbox img{width:180px;height:auto}
  </style>
</head>
<body onload="window.print()">
  <div class="paper">
    <div class="kop">
      <?php if ($logo): ?><img src="<?= e(url($logo)) ?>" alt="Logo"><?php endif; ?>
      <div>
        <div class="t1"><?= e($clinicName) ?></div>
        <?php if ($clinicAddr): ?><div class="t2"><?= e($clinicAddr) ?></div><?php endif; ?>
        <?php if ($clinicSip): ?><div class="t2">SIP: <?= e($clinicSip) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="block">
      <div class="label2">Identitas</div>
      <div>MRN: <?= e($v['mrn']) ?> | Nama: <?= e($v['full_name']) ?> | Usia: <?= e((string)($age ?? '-')) ?> | JK: <?= e($v['gender']) ?></div>
      <div>Alamat: <?= e($v['address'] ?? '') ?></div>
      <div>No Kunjungan: <?= e($v['visit_no']) ?> | Tanggal: <?= e($v['visit_date']) ?></div>
    </div>

    <div class="block">
      <div class="label2">Anamnesa</div>
      <pre><?= e($v['anamnesis'] ?? '') ?></pre>
    </div>

    <div class="block">
      <div class="label2">Pemeriksaan Fisik</div>
      <pre><?= e($v['physical_exam'] ?? '') ?></pre>
    </div>

    <div class="block">
      <div class="label2">Laporan USG</div>
      <pre><?= e($v['usg_report'] ?? '') ?></pre>
    </div>

    <div class="block">
      <div class="label2">Terapi</div>
      <pre><?= e($v['therapy'] ?? '') ?></pre>
    </div>

    <div class="sign">
      <div class="signbox">
        <div style="height:16px"></div>
        <?php if ($sig): ?><img src="<?= e(url($sig)) ?>" alt="Tanda tangan"><?php endif; ?>
        <div style="margin-top:6px"><?= e($v['doctor_name'] ?? $u['full_name']) ?></div>
      </div>
    </div>
  </div>
</body>
</html>

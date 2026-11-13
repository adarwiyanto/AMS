<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require();
$u = auth_user();
$settings = get_settings();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare("SELECT id, mrn, full_name, dob, gender, address FROM patients WHERE id=?");
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { http_response_code(404); echo "Not found"; exit; }

function fmt_dob($dob): string {
  if (!$dob) return '-';
  // expect YYYY-MM-DD
  if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$dob, $m)) {
    return $m[3] . '-' . $m[2] . '-' . $m[1];
  }
  return (string)$dob;
}

$clinicName = $settings['clinic_name'] ?? ($settings['brand_title'] ?? 'Adena Medical System');
$footer = $settings['footer_text'] ?? '';
$logo = $settings['logo_path'] ?? '';

$cardCss = (string)($settings['card_css'] ?? '');
$auto = (int)($_GET['autoprint'] ?? 0);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kartu Berobat - <?= e($p['mrn']) ?></title>
  <link rel="icon" href="<?= e($logo ? url($logo) : url('/public/assets/img/ams-favicon.svg')) ?>">
  <link rel="stylesheet" href="<?= e(url('/public/assets/css/theme.css')) ?>">
  <style>
    :root{--card-w:85.6mm;--card-h:53.98mm;--ink:#0b1e42;--muted:#485568}
    *{box-sizing:border-box}
    html,body{margin:0}
    body{background:#edf1f6;color:var(--ink);padding:20px;font-family:Arial,Helvetica,sans-serif}
    .topbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;width:var(--card-w);max-width:100%;margin:0 auto 14px}
    .wrap{width:var(--card-w);max-width:100%;margin:0 auto}
    .card-ktp{width:var(--card-w);height:var(--card-h);position:relative;overflow:hidden;background:#fff;border:1px solid #dde4ee;border-radius:3mm;box-shadow:0 8px 26px rgba(15,23,42,.18);color:var(--ink)}
    .stripe{position:absolute;top:0;bottom:0;left:0;width:7mm;background:linear-gradient(180deg,#22c55e 0%,#0fa8ba 54%,#1680ef 100%)}
    .inner{position:relative;margin-left:7mm;padding:3.5mm 3.3mm 3mm 4.2mm;height:100%;display:flex;flex-direction:column;min-width:0}
    .head{display:flex;align-items:center;gap:2.6mm;min-height:12mm;min-width:0}
    .logo{width:12mm;height:12mm;flex:0 0 12mm;display:flex;align-items:center;justify-content:center}
    .logo img{display:block;max-height:100%;max-width:100%;width:100%;height:100%;object-fit:contain}
    .logo .fallback{font-weight:900;color:#0f528f;font-size:12px}
    .clinic-name{font-weight:800;font-size:11.5px;line-height:1.19;overflow-wrap:anywhere;word-break:normal;min-width:0;flex:1}
    .patient{margin-top:3.2mm;min-width:0}
    .name{font-size:19px;font-weight:900;line-height:1.12;overflow-wrap:anywhere;text-transform:uppercase;letter-spacing:.01em}
    .patient-mrn{margin-top:.8mm;color:var(--muted);font-size:11px;font-weight:700;letter-spacing:.005em;overflow-wrap:anywhere}
    .grid{display:grid;grid-template-columns:1fr 1fr;column-gap:4mm;row-gap:1.4mm;margin-top:3.2mm;min-width:0}
    .row{min-width:0}
    .row .k{color:#596579;font-size:9px;line-height:1.2;font-weight:800;letter-spacing:.02em}
    .row .v{font-size:11px;font-weight:800;line-height:1.25;color:#111827;overflow-wrap:anywhere}
    .row.full{grid-column:1/-1}
    .row.full .v{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;line-height:1.15;font-size:10px;max-height:2.3em}
    .foot{margin-top:auto;padding-top:1.2mm}
    .note{font-size:9px;font-style:italic;color:#374151}
    @page{size:85.6mm 53.98mm;margin:0}
    @media print{
      html,body{width:85.6mm;height:53.98mm;padding:0;margin:0;background:white}
      body{-webkit-print-color-adjust:exact;print-color-adjust:exact}
      .topbar{display:none!important}
      .wrap{width:85.6mm;max-width:none;margin:0}
      .card-ktp{border:0;box-shadow:none;border-radius:0;break-inside:avoid;page-break-inside:avoid}
    }
    /* Penyesuaian CSS kartu dari admin (jika ada). */
    <?= $cardCss ?>
  </style>
</head>
<body <?= $auto ? 'onload="window.print()"' : '' ?>>
  <div class="topbar">
    <button class="btn" onclick="window.print()">Cetak / Simpan PDF</button>
    <a class="btn secondary" href="<?= e(url('/patients.php')) ?>">Kembali</a>
  </div>

  <div class="wrap">
    <div class="card-ktp" aria-label="Kartu Berobat">
      <div class="stripe"></div>
      <div class="inner">
        <div class="head">
          <div class="logo">
            <?php if ($logo): ?>
              <img src="<?= e(url($logo)) ?>" alt="Logo praktik">
            <?php else: ?>
              <span class="fallback">AMS</span>
            <?php endif; ?>
          </div>
          <div class="clinic-name"><?= e($clinicName ?: ($settings['brand_title'] ?? 'Praktik Dokter')) ?></div>
        </div>

        <div class="patient">
          <div class="name" style="<?= strlen((string)$p['full_name']) > 31 ? 'font-size:13px' : (strlen((string)$p['full_name']) > 20 ? 'font-size:16px' : '') ?>"><?= e($p['full_name']) ?></div>
          <div class="patient-mrn">No. RM : <?= e($p['mrn']) ?></div>
        </div>

        <div class="grid">
          <div class="row">
            <div class="k">TGL LAHIR</div>
            <div class="v"><?= e(fmt_dob($p['dob'])) ?></div>
          </div>
          <div class="row">
            <div class="k">JENIS KELAMIN</div>
            <div class="v"><?= e(($p['gender'] ?? '') === 'P' ? 'Perempuan' : (($p['gender'] ?? '') === 'L' ? 'Laki-laki' : '-')) ?></div>
          </div>
          <div class="row full">
            <div class="k">ALAMAT</div>
            <div class="v"><?= e($p['address'] ?: '-') ?></div>
          </div>
        </div>

        <div class="foot"><div class="note">Bawa kartu ini saat berobat</div></div>
      </div>
    </div>
  </div>
</body>
</html>

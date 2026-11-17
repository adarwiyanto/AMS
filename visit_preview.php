<?php
// Preview mandiri: template cetak lama tidak dimodifikasi.
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
auth_require_role(['admin','dokter','perawat']);
header('Cache-Control: private, no-store');
ob_start();
require __DIR__ . '/print_visit.php';
$html = ob_get_clean();
// Nonaktifkan print pagination khusus pada preview; layout cetak A4 tetap utuh.
$html = preg_replace('~<link rel="stylesheet" href="[^"]*/public/assets/css/print_layout\.css"\s*>~', '', $html);
$html = preg_replace('~<script src="[^"]*/public/assets/js/print_layout\.js"></script>~', '', $html);
$html = str_replace('</head>', '<style>html,body{margin:0;background:#f1f5f9!important}.paper{background:#fff;max-width:794px!important;min-height:600px;box-shadow:0 4px 20px #0001;padding:30px!important;margin:18px auto!important}@media(max-width:680px){.paper{margin:0!important;padding:14px!important}.usggrid{grid-template-columns:1fr}}</style></head>', $html);
echo $html;

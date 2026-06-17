<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
$u = pacs_require_login();
$settings = get_settings();
$title = 'Pasien DICOM';
$rows = pacs_db()->query('SELECT p.*, MAX(l.ams_patient_id) AS ams_patient_id FROM pacs_patients p LEFT JOIN pacs_links l ON l.pacs_patient_id = p.patient_id GROUP BY p.id ORDER BY p.created_at DESC LIMIT 200')->fetchAll();
require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Pasien DICOM</div>
  <table class="table">
    <thead><tr><th>PatientID</th><th>Nama</th><th>Lahir</th><th>Sex</th><th>Link AMS</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e((string)$r['patient_id']) ?></td>
        <td><?= e((string)$r['patient_name']) ?></td>
        <td><?= e((string)$r['birth_date']) ?></td>
        <td><?= e((string)$r['sex']) ?></td>
        <td><?= e((string)($r['ams_patient_id'] ?? '-')) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5">Belum ada pasien DICOM.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../app/views/partials/footer.php';

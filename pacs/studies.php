<?php
require_once __DIR__ . '/lib/pacs_bootstrap.php';
$u = pacs_require_login();
$settings = get_settings();
$title = 'Studies DICOM';
$q = trim((string)($_GET['q'] ?? ''));
$limit = 100;
if ($q !== '') {
  $stmt = db()->prepare('SELECT * FROM pacs_studies WHERE patient_id LIKE ? OR study_uid LIKE ? OR study_desc LIKE ? ORDER BY created_at DESC LIMIT ' . $limit);
  $like = '%' . $q . '%';
  $stmt->execute([$like, $like, $like]);
} else {
  $stmt = db()->query('SELECT * FROM pacs_studies ORDER BY created_at DESC LIMIT ' . $limit);
}
$rows = $stmt->fetchAll();
require __DIR__ . '/../app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">Studies</div>
  <form>
    <input class="input" name="q" value="<?= e($q) ?>" placeholder="Cari PatientID / StudyUID / deskripsi">
  </form>
  <table class="table">
    <thead><tr><th>Study UID</th><th>Patient ID</th><th>Date</th><th>Modalities</th><th>Series</th><th>Instances</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td style="max-width:220px;word-break:break-all"><?= e((string)$r['study_uid']) ?></td>
        <td><?= e((string)$r['patient_id']) ?></td>
        <td><?= e((string)$r['study_date']) ?></td>
        <td><?= e((string)$r['modalities']) ?></td>
        <td><?= e((string)$r['num_series']) ?></td>
        <td><?= e((string)$r['num_instances']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6">Belum ada study.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../app/views/partials/footer.php';

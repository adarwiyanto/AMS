<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';

auth_require_role(['admin']);
$u = auth_user();
$settings = get_settings();
csrf_validate();

$action = $_POST['action'] ?? '';

if ($action === 'create_user') {
  $username = trim($_POST['username'] ?? '');
  $full = trim($_POST['full_name'] ?? '');
  $role = $_POST['role'] ?? 'dokter';
  $pass1 = (string)($_POST['pass1'] ?? '');
  $pass2 = (string)($_POST['pass2'] ?? '');

  if ($username === '') { flash_set('err','Username wajib.'); redirect('/users.php'); }
  if (strlen($pass1) < 8) { flash_set('err','Password minimal 8 karakter.'); redirect('/users.php'); }
  if ($pass1 !== $pass2) { flash_set('err','Password tidak sama.'); redirect('/users.php'); }

  $hash = password_hash($pass1, PASSWORD_DEFAULT);
  try {
    db_exec("INSERT INTO users(username,password_hash,role,full_name,is_active,created_at) VALUES(?,?,?,?,1,?)",
      [$username,$hash,$role,$full ?: $username, now_dt()]
    );
    db_exec("INSERT INTO audit_logs(created_at,user_id,action,meta) VALUES(?,?,?,?)",
      [now_dt(), $u['id'], 'create_user', json_encode(['username'=>$username,'role'=>$role], JSON_UNESCAPED_UNICODE)]
    );
    flash_set('ok','User dibuat.');
  } catch (Throwable $e) {
    flash_set('err','Gagal membuat user: ' . $e->getMessage());
  }
  redirect('/users.php');
}

if ($action === 'reset_password') {
  $id = (int)($_POST['id'] ?? 0);
  $pass1 = (string)($_POST['pass1'] ?? '');
  $pass2 = (string)($_POST['pass2'] ?? '');
  if (strlen($pass1) < 8) { flash_set('err','Password minimal 8 karakter.'); redirect('/users.php'); }
  if ($pass1 !== $pass2) { flash_set('err','Password tidak sama.'); redirect('/users.php'); }
  $hash = password_hash($pass1, PASSWORD_DEFAULT);
  db_exec("UPDATE users SET password_hash=?, updated_at=? WHERE id=?", [$hash, now_dt(), $id]);
  db_exec("INSERT INTO audit_logs(created_at,user_id,action,meta) VALUES(?,?,?,?)",
    [now_dt(), $u['id'], 'reset_password', json_encode(['target_id'=>$id], JSON_UNESCAPED_UNICODE)]
  );
  flash_set('ok','Password diperbarui.');
  redirect('/users.php');
}

if ($action === 'toggle_active') {
  $id = (int)($_POST['id'] ?? 0);
  $cur = db()->prepare("SELECT is_active FROM users WHERE id=?");
  $cur->execute([$id]);
  $row = $cur->fetch();
  if ($row) {
    $new = (int)!((int)$row['is_active']);
    db_exec("UPDATE users SET is_active=?, updated_at=? WHERE id=?", [$new, now_dt(), $id]);
    flash_set('ok','Status user diubah.');
  }
  redirect('/users.php');
}

$rows = db()->query("SELECT id,username,role,full_name,is_active,created_at FROM users ORDER BY created_at DESC")->fetchAll();

$title = "User & Role";
require __DIR__ . '/app/views/partials/header.php';
?>
<div class="card">
  <div class="h1">User & Role</div>
  <div class="muted">Buat user/role dan reset password tanpa buka MySQL manual.</div>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Tambah User</div>
  <form method="post" class="grid" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create_user">
    <div class="col-6">
      <div class="label">Username</div>
      <input class="input" name="username" required>
    </div>
    <div class="col-6">
      <div class="label">Nama Lengkap</div>
      <input class="input" name="full_name">
    </div>
    <div class="col-6">
      <div class="label">Role</div>
      <select class="input" name="role">
        <option value="dokter">dokter</option>
        <option value="perawat">perawat</option>
        <option value="sekretariat">sekretariat</option>
        <option value="admin">admin</option>
      </select>
    </div>
    <div class="col-6"></div>
    <div class="col-6">
      <div class="label">Password</div>
      <input class="input" type="password" name="pass1" required>
    </div>
    <div class="col-6">
      <div class="label">Ulangi Password</div>
      <input class="input" type="password" name="pass2" required>
    </div>
    <div class="col-12" style="display:flex;justify-content:flex-end">
      <button class="btn" type="submit">Simpan</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="h1" style="font-size:16px">Daftar User</div>
  <table class="table">
    <thead><tr><th>Username</th><th>Nama</th><th>Role</th><th>Aktif</th><th>Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['username']) ?></td>
          <td><?= e($r['full_name'] ?? '') ?></td>
          <td><?= e($r['role']) ?></td>
          <td><?= ((int)$r['is_active']===1) ? 'Ya' : 'Tidak' ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn small secondary" type="submit"><?= ((int)$r['is_active']===1) ? 'Nonaktifkan' : 'Aktifkan' ?></button>
            </form>
            <details style="display:inline-block;margin-left:6px">
              <summary class="btn small secondary" style="list-style:none;display:inline-flex;cursor:pointer">Reset Password</summary>
              <form method="post" style="margin-top:8px">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input class="input" type="password" name="pass1" placeholder="Password baru" required>
                <div style="height:8px"></div>
                <input class="input" type="password" name="pass2" placeholder="Ulangi password" required>
                <div style="height:8px"></div>
                <button class="btn small" type="submit">Simpan</button>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="muted">Belum ada data.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/app/views/partials/footer.php'; ?>

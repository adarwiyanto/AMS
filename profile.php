<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';
auth_require();
$u=auth_user();$settings=get_settings();csrf_validate();
$meSt=db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');$meSt->execute([(int)$u['id']]);$me=$meSt->fetch();
if(!$me){http_response_code(404);exit('Pengguna tidak ditemukan.');}
function ams_profile_upload(string $field,string $folder): ?string {
  if(!isset($_FILES[$field]) || ($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
  if($_FILES[$field]['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Upload '.$field.' gagal.');
  if((int)$_FILES[$field]['size']>3*1024*1024)throw new RuntimeException('Foto maksimal 3 MB.');
  $tmp=$_FILES[$field]['tmp_name'];
  if(!is_uploaded_file($tmp))throw new RuntimeException('File upload tidak valid.');
  $info=@getimagesize($tmp);
  $types=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'];
  if(!$info || !isset($types[$info[2]]))throw new RuntimeException('Gunakan foto JPG, PNG, atau WebP.');
  $dir=__DIR__.'/storage/uploads/'.$folder;
  if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Folder foto tidak dapat dibuat.');
  $fn=$folder.'_'.bin2hex(random_bytes(12)).'.'.$types[$info[2]];
  if(!move_uploaded_file($tmp,$dir.'/'.$fn))throw new RuntimeException('Foto gagal disimpan.');
  @chmod($dir.'/'.$fn,0644);
  return '/storage/uploads/'.$folder.'/'.$fn;
}
if(is_post()){
  try {
    $name=trim((string)($_POST['full_name']??''));
    if($name==='')throw new RuntimeException('Nama lengkap wajib diisi.');
    $avatar=ams_profile_upload('avatar','avatars');
    $sig=ams_profile_upload('signature_user','signature/users');
    $fields=['full_name=?','sip=?','phone=?','email=?','updated_at=?'];
    $params=[$name,trim((string)($_POST['sip']??'')),trim((string)($_POST['phone']??'')),trim((string)($_POST['email']??'')),now_dt()];
    if($avatar){$fields[]='avatar_path=?';$params[]=$avatar;}
    if($sig){$fields[]='signature_path=?';$params[]=$sig;}
    if(isset($_POST['remove_avatar']) && $_POST['remove_avatar']==='1'){$fields[]='avatar_path=?';$params[]=null;}
    $params[]=(int)$u['id'];
    db_exec('UPDATE users SET '.implode(',',$fields).' WHERE id=?',$params);
    $_SESSION['user']['full_name']=$name;
    flash_set('ok','Profil berhasil diperbarui.');redirect('/profile.php');
  }catch(Throwable $e){flash_set('err',$e->getMessage());redirect('/profile.php');}
}
$title='Edit Profil';require __DIR__.'/app/views/partials/header.php';
$avatar=(string)($me['avatar_path']??'');$sign=(string)($me['signature_path']??'');
?>
<div class="card"><div class="h1">Edit Profil</div><div class="muted">Atur identitas akun, foto profil, dan tanda tangan pemeriksaan.</div></div>
<div class="card ams-profile-card">
 <form method="post" enctype="multipart/form-data" id="amsProfileForm" class="ams-profile-layout">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="remove_avatar" id="removeAvatar" value="0">
  <aside class="ams-profile-photo">
   <div class="ams-profile-preview" id="profilePreview"><?php if($avatar): ?><img src="<?= e(url($avatar)) ?>" alt="Foto profil"><?php else: ?><span><?= e(substr((string)($me['full_name']?:$me['username']),0,1)) ?></span><?php endif; ?></div>
   <h3><?= e($me['full_name']?:$me['username']) ?></h3>
   <p class="muted">Foto akun, bukan logo praktik.</p>
   <label class="btn secondary ams-profile-file">Ubah Foto<input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" hidden></label>
   <button class="btn small secondary" id="removeAvatarButton" type="button">Hapus Foto</button>
   <p class="muted">JPG/PNG/WebP, maksimal 3 MB. Foto dapat diposisikan sebelum disimpan.</p>
  </aside>
  <div class="ams-profile-fields">
   <div class="h1" style="font-size:18px">Informasi Pengguna</div>
   <div class="grid">
    <div class="col-6"><label class="label" for="fullname">Nama Lengkap</label><input class="input" id="fullname" name="full_name" required maxlength="120" value="<?= e($me['full_name']??'') ?>"></div>
    <div class="col-6"><label class="label">Username</label><input class="input" value="<?= e($me['username']) ?>" disabled><div class="muted">Username dikelola oleh administrator.</div></div>
    <div class="col-6"><label class="label" for="profileEmail">Email</label><input class="input" type="email" id="profileEmail" name="email" maxlength="120" value="<?= e($me['email']??'') ?>"></div>
    <div class="col-6"><label class="label" for="profilePhone">Nomor HP</label><input class="input" id="profilePhone" name="phone" maxlength="50" value="<?= e($me['phone']??'') ?>"></div>
    <div class="col-12"><label class="label" for="profileSip">Nomor SIP</label><input class="input" id="profileSip" name="sip" maxlength="80" value="<?= e($me['sip']??'') ?>"></div>
    <div class="col-12"><label class="label" for="profileSign">Tanda Tangan Dokter</label><input class="input" type="file" name="signature_user" id="profileSign" accept="image/png,image/jpeg,image/webp"><div class="ams-profile-sign" id="signPreview"><?php if($sign): ?><img src="<?= e(url($sign)) ?>" alt="Tanda tangan tersimpan"><?php else: ?><span class="muted">Belum ada tanda tangan.</span><?php endif; ?></div></div>
   </div>
   <div class="ams-profile-actions"><button type="submit" class="btn">Simpan Perubahan</button></div>
  </div>
 </form>
</div>
<div id="avatarEditor" class="ams-avatar-editor" hidden role="dialog" aria-modal="true" aria-label="Atur foto profil">
 <div class="ams-avatar-editor-card"><h2>Atur Foto Profil</h2><p class="muted">Geser foto dan atur ukuran agar pas di dalam lingkaran.</p><canvas id="avatarCanvas" width="320" height="320"></canvas><label class="label" for="avatarZoom">Perbesar / Perkecil</label><input type="range" id="avatarZoom" min="1" max="3" step="0.01" value="1"><div class="ams-profile-actions"><button type="button" class="btn secondary" id="cancelAvatar">Batal</button><button type="button" class="btn" id="applyAvatar">Gunakan Foto</button></div></div>
</div>
<script src="<?= e(url('/public/assets/js/profile_editor.js')) ?>"></script>
<?php require __DIR__.'/app/views/partials/footer.php'; ?>

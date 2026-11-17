<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/controllers/common.php';
require_once __DIR__ . '/app/continuous_pdf.php';
auth_require_role(['admin','dokter','perawat']);
$settings=get_settings();
$id=(int)($_GET['id']??0);
$st=db()->prepare('SELECT v.*,p.mrn,p.full_name,p.dob,p.gender,p.address,u.full_name AS doctor_name,u.signature_path AS doctor_signature_path FROM visits v JOIN patients p ON p.id=v.patient_id LEFT JOIN users u ON u.id=v.doctor_id WHERE v.id=? LIMIT 1');
$st->execute([$id]);$v=$st->fetch();
if(!$v){http_response_code(404);exit('Kunjungan tidak ditemukan.');}
function pdf_local_image_path(string $rel): ?string {
  if(!preg_match('~^/storage/uploads/[a-zA-Z0-9_./-]+$~',$rel) || str_contains($rel,'..'))return null;
  $p=realpath(__DIR__.$rel);$base=realpath(__DIR__.'/storage/uploads');
  return ($p && $base && str_starts_with($p,$base.DIRECTORY_SEPARATOR))?$p:null;
}
try {
  $pdf=new AMSContinuousPDF();
  $pdf->text((string)($settings['clinic_name']??($settings['brand_title']??'Praktek Dokter')),17,true);
  if(!empty($settings['clinic_address']))$pdf->text($settings['clinic_address'],9);
  if(!empty($settings['clinic_sip']))$pdf->text('SIP: '.$settings['clinic_sip'],9);
  $pdf->rule();
  $pdf->heading('IDENTITAS PASIEN');
  $pdf->text('No. RM: '.$v['mrn'].'     Nama: '.$v['full_name']);
  $pdf->text('Usia: '.(string)(age_from_dob($v['dob'])??'-').'     JK: '.$v['gender']);
  $pdf->text('Alamat: '.(string)($v['address']??''));
  $pdf->text('No. Kunjungan: '.$v['visit_no'].'     Tanggal: '.$v['visit_date']);
  foreach(['ANAMNESA'=>'anamnesis','PEMERIKSAAN FISIK'=>'physical_exam','LAPORAN USG'=>'usg_report','DIAGNOSA AKHIR'=>'diagnosis'] as $label=>$key){
    $pdf->heading($label);$pdf->text((string)($v[$key]??''));
  }
  if(!empty($v['diagnosis_icd10']))$pdf->text('ICD-10: '.$v['diagnosis_icd10'],9);
  if(!empty($v['usg_icd9']))$pdf->text('ICD-9 USG: '.$v['usg_icd9'],9);
  try{
    $im=db()->prepare('SELECT file_path,caption FROM usg_images WHERE visit_id=? ORDER BY sort_order ASC,id ASC');
    $im->execute([$id]);
    foreach($im->fetchAll() as $row){
      if($path=pdf_local_image_path($row['file_path'])){$pdf->heading('Foto USG');$pdf->image($path);if($row['caption'])$pdf->text($row['caption'],9);}
    }
  }catch(Throwable $e){ /* Tabel foto opsional */ }
  $pdf->heading('TERAPI');$pdf->text((string)($v['therapy']??''));
  $pdf->heading('Dokter Pemeriksa');
  $sig=$v['signature_path']?:($v['doctor_signature_path']?:($settings['signature_path']??''));
  if($sig && ($path=pdf_local_image_path($sig)))$pdf->image($path,160,85);
  $pdf->text((string)($v['doctor_name']??''),11,true);
  $pdf->output('AMS_'.$v['visit_no'].'.pdf');
}catch(Throwable $e){http_response_code(422);header('Content-Type: text/plain; charset=utf-8');echo 'PDF tidak dapat dibuat: '.$e->getMessage();}

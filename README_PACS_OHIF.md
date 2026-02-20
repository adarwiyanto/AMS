# PACS + OHIF Integration (Opsi 3: Token SSO sementara)

Dokumen ini menjelaskan instalasi dan konfigurasi modul PACS di AMS dengan alur:
1. User login AMS.
2. Upload DICOM dari AMS ke Orthanc (opsional).
3. AMS generate token short-lived (HMAC SHA256) untuk 1 study.
4. AMS redirect ke OHIF di tab baru.
5. OHIF mengambil data DICOMWeb lewat proxy AMS (`/pacs/api/dicomweb_proxy.php`) dengan token.

## 1) File yang ditambahkan
- `/pacs/index.php` (UI PACS)
- `/pacs/launch.php` (generate token + redirect ke OHIF)
- `/pacs/upload.php` (upload DICOM/ZIP ke Orthanc)
- `/pacs/api/token_verify.php`
- `/pacs/api/studies.php`
- `/pacs/api/dicomweb_proxy.php`
- `/pacs/lib/*.php` (config, token, orthanc client, metadata, audit)
- `/sql/pacs.sql` (tabel `pacs_studies` + `pacs_audit`)

## 2) Konfigurasi
Gunakan `app/config.php` (jangan commit secret). Tambahkan blok `pacs` berikut:

```php
'pacs' => [
  'orthanc_url' => 'http://127.0.0.1:8042',
  'orthanc_user' => 'orthanc_user',
  'orthanc_pass' => 'orthanc_pass',
  'ohif_base_url' => 'https://domain.tld/ohif/',
  'token_secret' => 'ISI_SECRET_RANDOM_PANJANG',
  'token_ttl' => 120,
  'allowed_roles' => ['superadmin','admin','dokter'],
  'max_upload_mb' => 200,
  'dicomweb_path' => '/dicom-web',
  'issuer' => 'ams',
],
```

Alternatif: gunakan ENV (`ORTHANC_URL`, `ORTHANC_USER`, dst.) sesuai nama variabel di `pacs/lib/pacs_config.php`.

## 3) SQL migration
Jalankan SQL berikut ke database AMS:
- `sql/pacs.sql`

## 4) Konfigurasi Orthanc
Contoh poin penting:
- Aktifkan REST API dan DICOMWeb plugin.
- Pastikan endpoint tersedia:
  - `POST /instances`
  - DICOMWeb path (default modul: `/dicom-web`).
- Jika pakai basic auth, isi `ORTHANC_USER` dan `ORTHANC_PASS`.

## 5) Konfigurasi OHIF
Prinsip integrasi:
- OHIF dibuka dari `launch.php` dengan parameter:
  - `StudyInstanceUIDs`
  - `token`
  - `dicomWebBase` (mengarah ke `/pacs/api/dicomweb_proxy.php`)
- OHIF sebaiknya mengirim token sebagai query (`?token=`) atau header `Authorization: Bearer ...`.
- Proxy AMS memverifikasi token dan membatasi akses study sesuai claim `study_uid`.

## 6) Security Notes
- Token signed HMAC SHA256.
- Token short-lived (`token_ttl`, default 120 detik).
- Claim minimal: `sub`, `study_uid`, `source`, `iat`, `exp`, `nonce`, `iss`.
- Jangan simpan secret di repo.
- Link tab baru menggunakan `rel="noopener noreferrer"`.
- Upload dilindungi CSRF token AMS.

## 7) Test end-to-end manual
1. Setup Orthanc + plugin dicom-web aktif.
2. Deploy OHIF dan set datasource ke Orthanc DICOMWeb atau AMS proxy.
3. Login AMS sebagai role allowed (`admin/dokter/superadmin`).
4. Klik menu **PACS** (terbuka tab baru).
5. Upload 1 file DICOM (`.dcm`) atau ZIP DICOM.
6. Pastikan studi muncul di list PACS (`PatientName`, `StudyUID`, dll).
7. Klik **View** → OHIF terbuka dan menampilkan studi.
8. Coba ulang URL viewer setelah >TTL token → akses harus ditolak (401/403/error viewer).

## 8) Catatan kompatibilitas
- Modul memakai PHP native + cURL + ZipArchive.
- Ditujukan untuk PHP 7.4+.
- Perubahan ke AMS bersifat additive, hanya menambah menu PACS di sidebar.

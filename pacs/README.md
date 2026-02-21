# AMS PACS Lite (Shared Hosting)

Modul PACS berbasis PHP native + MySQL untuk LiteSpeed/Apache tanpa Docker/Orthanc.

## Struktur
- `/pacs/upload.php` upload multi DICOM/ZIP
- `/pacs/api/upload_handler.php` handler upload + dedup SOP UID
- `/pacs/dicomweb/index.php` endpoint QIDO-RS minimal
- `/pacs/wado.php` endpoint WADO-URI
- Storage privat: `/home/adey8293/private_uploads/ams_pacs`

## Install
1. Import `pacs.sql` ke database AMS.
2. Pastikan folder `/home/adey8293/private_uploads/ams_pacs` writable PHP.
3. Pastikan `mod_rewrite` aktif dan `.htaccess` PACS terbaca.
4. Deploy OHIF static ke `/ohif/` lalu sesuaikan `ohif/app-config.js`.

## Uji cepat
- `GET /pacs/dicomweb/studies` harus merespons `application/dicom+json`.
- Upload 1 file DICOM dari `/pacs/upload.php`.
- Buka `/ohif/` dan pastikan source "AMS PACS" tampil.

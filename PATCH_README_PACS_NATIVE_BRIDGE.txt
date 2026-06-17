PATCH AMS PACS NATIVE BRIDGE v1
================================

Tujuan:
- Menambahkan menu PACS di sidebar AMS.
- Klik menu PACS langsung membuka tab baru.
- PACS menggunakan database terpisah: adey8293_pacs.
- User database PACS: adey8293_adyto.
- Password database PACS tidak di-hardcode; isi sendiri di app/pacs_config.php.
- Viewer utama diarahkan ke Native Adena Dicom Viewer melalui custom protocol adena-dicom://open.
- Tidak memakai Cornerstone.
- Tidak mengubah modul AMS lain yang tidak diminta.

File penting yang ditambahkan/diubah:
- app/db.php
  Menambah fungsi pacs_db() dan pacs_db_exec(). db() AMS utama tetap tidak berubah.

- app/pacs_config.sample.php
  Template koneksi database PACS. Copy menjadi app/pacs_config.php lalu isi password.

- app/views/partials/header.php
  Menambah menu PACS dengan target="_blank".

- pacs/index.php
  Dashboard PACS diarahkan ke Native DicomViewer Bridge.

- pacs/studies.php
  Menambah tombol Buka Native, Report, dan Link AMS.

- pacs/launch.php
  Membuka Native DicomViewer melalui protocol adena-dicom://open.

- pacs/report.php
  Word processing/report editor berbasis PACS DB.

- pacs/link.php
  Menghubungkan study PACS ke pasien dan kunjungan AMS.

- sql/adey8293_pacs_schema.sql
  Schema database PACS terpisah.

Langkah pemasangan:
1. Upload file patch sesuai struktur folder AMS.
2. Buat database MySQL:
   adey8293_pacs
3. Pastikan user DB tersedia:
   adey8293_adyto
4. Import:
   sql/adey8293_pacs_schema.sql
5. Copy:
   app/pacs_config.sample.php
   menjadi:
   app/pacs_config.php
6. Isi password database PACS di app/pacs_config.php.
7. Login AMS, klik menu PACS. Menu harus terbuka di tab baru.
8. Upload DICOM/ZIP ke PACS.
9. Buka Studies -> klik Buka Native.
10. Pastikan Native DicomViewer sudah mendaftarkan protocol adena-dicom://open di Windows.

Catatan Native DicomViewer:
- Patch AMS sudah mengirim parameter study_uid, patient_id, patient_name, ams_patient_id, ams_visit_id, dan pacs_api.
- Aplikasi Native DicomViewer perlu membaca URL protocol seperti:
  adena-dicom://open?study_uid=...&patient_id=...&ams_patient_id=...&ams_visit_id=...
- Jika protocol belum didaftarkan, browser akan menampilkan prompt/error dan aplikasi tidak terbuka otomatis.

Backtest ringkas:
- Agent 1 UI: menu PACS open new tab, tidak mengubah menu lain.
- Agent 2 DB: AMS tetap db(), PACS memakai pacs_db().
- Agent 3 PACS flow: upload/list/study/report/link memakai pacs_db().
- Agent 4 Native bridge: launch membuat protocol URL, bukan Cornerstone/OHIF.
- Agent 5 Regression: lint PHP modul aktif lolos; file backup lama tetap tidak disentuh.

Batasan:
- Bridge native membutuhkan update/registrasi protocol pada aplikasi desktop DicomViewer.
- Word processing di AMS PACS sudah tersedia sebagai editor report; sinkron penuh dua arah dengan aplikasi native memerlukan endpoint/API tambahan di DicomViewer atau patch lanjutan di aplikasi native.

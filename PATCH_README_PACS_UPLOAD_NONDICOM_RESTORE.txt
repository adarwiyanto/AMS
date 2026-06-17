PATCH PACS UPLOAD HOTFIX - FILTER NON-DICOM + RESTORE DUPLIKAT

Tujuan:
- ZIP DICOM yang berisi file viewer/HTML/JS/CSS tidak lagi menampilkan error merah "bukan DICOM" untuk file non-DICOM.
- Bila SOPInstanceUID sudah ada di database tetapi file fisik DICOM hilang di storage, upload ulang akan memulihkan/copy ulang file tersebut.
- Hasil upload sekarang menampilkan jumlah:
  * Tersimpan
  * Duplikat/skipped
  * Dipulihkan
  * Non-DICOM dilewati
  * DICOM diproses
- Viewer internal memberi pesan lebih jelas bila file fisik hilang atau DICOM compressed/encapsulated belum didukung.

File berubah:
- pacs/lib/pacs_upload_service.php
- pacs/api/upload_handler.php
- pacs/api/finalize_upload.php
- pacs/api/viewer_study.php
- pacs/assets/pacs_viewer.js
- pacs/upload.php

Tidak ada perubahan database.
Tidak mengubah menu, AMS non-PACS, atau struktur data utama.

Catatan:
- Setelah patch terpasang, upload ulang ZIP yang sama. Jika database sudah berisi instance tetapi file fisik hilang, kolom "Dipulihkan" akan bertambah.
- Jika viewer internal tetap menampilkan pesan compressed/encapsulated, DICOM tersebut perlu dibuka melalui native viewer/WADO karena viewer internal belum memiliki codec JPEG/JPEG2000 DICOM.

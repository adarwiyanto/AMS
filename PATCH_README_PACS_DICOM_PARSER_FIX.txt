PATCH PACS DICOM PARSER FIX + CLEANUP FALLBACK STUDY

Tujuan:
- Memperbaiki parser metadata PACS agar membaca StudyInstanceUID/SeriesInstanceUID/SOPInstanceUID asli dari DICOM.
- Mendukung metadata Explicit VR Little Endian dan Implicit VR Little Endian.
- Memperbaiki viewer internal agar bisa membaca pixel data uncompressed Explicit VR dan Implicit VR Little Endian.
- Menghapus perilaku fallback otomatis UID 2.25.* yang menyebabkan study UNKNOWN palsu.
- Menambahkan endpoint cleanup untuk study fallback yang sudah terlanjur masuk.

File berubah/baru:
- pacs/lib/pacs_bootstrap.php
- pacs/assets/pacs_viewer.js
- pacs/api/cleanup_fallback_studies.php
- PATCH_README_PACS_DICOM_PARSER_FIX.txt

Cara pakai setelah patch dipasang:
1. Bersihkan study fallback lama yang salah terbaca:
   /pacs/api/cleanup_fallback_studies.php?confirm=1

   Endpoint ini hanya menghapus study PACS dengan:
   - patient_id = UNKNOWN
   - study_uid LIKE 2.25.%

2. Upload ulang ZIP DICOM yang sama.
3. Buka PACS > Studies.
4. Study UID dan Patient ID seharusnya terbaca dari metadata asli DICOM.

Catatan:
- Viewer internal tetap hanya untuk DICOM uncompressed grayscale.
- Jika DICOM memakai compressed/encapsulated transfer syntax seperti JPEG/JPEG2000, metadata tetap bisa terbaca, tetapi viewer internal akan memberi pesan bahwa pixel compressed belum didukung.
- Tidak ada perubahan database dan tidak mengubah fitur AMS non-PACS.

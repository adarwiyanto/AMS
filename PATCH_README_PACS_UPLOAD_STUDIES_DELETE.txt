Patch PACS Upload + Studies + Delete Study + UID Parser Fallback

Tujuan:
1. Menggabungkan halaman Upload dan Studies PACS dalam satu halaman /pacs/upload.php.
2. Menambahkan upload chunk 5 MB agar aman untuk shared hosting dengan batas sekitar 10 MB/request.
3. Menambahkan tombol hapus study yang menghapus metadata PACS dan file DICOM fisik terkait.
4. Membuat UI desktop lebih compact dan UI mobile menjadi card-list responsif.
5. Memperbaiki parser UID DICOM dengan fallback scanner untuk tag wajib:
   - StudyInstanceUID (0020,000D)
   - SeriesInstanceUID (0020,000E)
   - SOPInstanceUID (0008,0018)

File berubah/baru:
- app/helpers.php
- pacs/lib/pacs_bootstrap.php
- pacs/lib/pacs_upload_service.php
- pacs/upload.php
- pacs/studies.php
- pacs/api/studies.php
- pacs/api/upload_handler.php
- pacs/api/upload_chunk.php
- pacs/api/finalize_upload.php
- pacs/api/delete_study.php

Catatan:
- Tidak ada perubahan database.
- Tidak mengubah menu AMS lain.
- DICOM asli tetap disimpan tanpa kompresi lossy.
- Jika ada study lama yang salah/UNKNOWN, hapus dari tombol Hapus lalu upload ulang ZIP.

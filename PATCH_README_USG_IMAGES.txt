AMS Patch Pack - Upload Foto USG (Add-on)

Tujuan
- Upload foto hasil USG pada tiap Kunjungan.
- Foto ikut tercetak saat Print Hasil Pemeriksaan (kertas A4).
- Tidak mengubah tabel lama: hanya menambah tabel baru.

File dalam patch
- migrations/003_add_usg_images.sql
- visit_edit.php
- print_visit.php
- usg_upload.php
- usg_delete.php
- storage/uploads/usg/.keep

Cara pasang (XAMPP / hosting)
1) Copy/overwrite file patch ke folder AMS (sejajar dengan visits.php, patients.php, dsb).
2) Pastikan folder berikut ada dan bisa ditulis (writable): storage/uploads/usg
   - Jika di hosting: permission biasanya 755/775; jika perlu 777.
3) Jalankan SQL: migrations/003_add_usg_images.sql melalui phpMyAdmin (database AMS).
4) Tes:
   - Buka Kunjungan -> Edit (visit_edit.php)
   - Upload 1-3 foto USG
   - Print Hasil (print_visit.php) dan pastikan foto muncul.

Catatan
- Patch ini tidak memengaruhi "Print Resep". Hanya Print Hasil Pemeriksaan.
- Jika tabel usg_images belum dibuat, halaman tetap aman (fitur foto tidak aktif).

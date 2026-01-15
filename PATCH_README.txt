AMS Patch dari versi 1.1 -> 1.2 (tambahan tanpa mengganggu fitur lama)
===============================================================

Patch ini berisi (hanya menambah/merapikan, tidak merusak alur lama):
1) Fix akses uploads (logo/tanda tangan tampil):
   - storage/.htaccess diubah agar uploads bisa diakses.
   - storage/logs/.htaccess dan storage/backups/.htaccess ditambahkan untuk tetap menutup log & backup.
2) Tanda tangan per-user dokter:
   - Tambah kolom users.signature_path
   - Profile: upload tanda tangan per user
   - Print hasil & resep: pakai signature kunjungan (jika ada) -> signature dokter -> signature global settings
3) Tambah role "sekretariat":
   - Sekretariat hanya bisa tambah pasien dan edit NAMA pasien.
   - Tidak bisa hapus pasien.
   - Tidak bisa akses kunjungan & resep (tidak bisa menulis status/terapi/resep).
4) Tambahan "Jadwal" pasien berobat:
   - Filter: Hari ini, Kemarin, 7 hari terakhir, dan rentang custom.
   - Menampilkan daftar kunjungan (tanggal/jam, MRN, nama, no kunjungan, dokter).
   - Sekretariat boleh melihat Jadwal, tapi tidak bisa membuka detail kunjungan/resep.

Cara Apply Patch
----------------
A) Backup dulu folder aplikasi Anda (mis. C:\xampp\htdocs\AMS)
B) Copy/Replace file-file dari patch ini ke folder aplikasi AMS Anda (struktur sama).
   - Pastikan overwrite file yang namanya sama.

C) Jalankan SQL migrasi di phpMyAdmin (database AMS Anda):
   1) buka tab SQL
   2) copy-paste isi file: migrations/001_add_signature_and_sekretariat.sql
   3) Execute

D) Logout/Login ulang.

Catatan:
- Patch ini tidak mengubah alur instalasi lama yang sudah stabil.
- Jika Anda instal baru, sebaiknya nanti saya buatkan full package 1.2; patch ini fokus untuk update dari 1.1.


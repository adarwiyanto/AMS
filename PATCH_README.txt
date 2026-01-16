AMS Addon Patch: Fitur Rujukan (tidak mengganggu alur lama)
==========================================================

Yang ditambahkan:
- Tabel baru: referrals
- Halaman baru: referrals.php (mirip Resep; dibuat dari kunjungan via visit_id)
- Halaman print: print_referral.php
- Menu sidebar: Rujukan (untuk admin/dokter/perawat)

Cara pasang:
1) Backup folder aplikasi AMS Anda.
2) Copy/overwrite file-file dari patch ini ke folder AMS Anda.
3) Jalankan SQL migrasi:
   - phpMyAdmin -> pilih database -> tab SQL
   - jalankan file: migrations/002_add_referrals.sql
4) Logout/Login ulang.

Cara pakai:
- Buka Kunjungan -> pilih pasien -> pilih kunjungan -> buka menu "Rujukan"
  lalu isi tujuan dokter + spesialis + diagnosa.
- Atau langsung akses:
  /referrals.php?visit_id=ID_KUNJUNGAN

Catatan:
- Sekretariat tidak bisa membuat rujukan (sesuai pembatasan akses).

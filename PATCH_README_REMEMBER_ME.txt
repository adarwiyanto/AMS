PATCH REMEMBER ME - AMS
Tanggal: 14 Juli 2026

Perubahan:
1. Menambahkan pilihan "Ingat saya" pada halaman login.
2. Login persisten menggunakan token acak; password tidak pernah disimpan di cookie.
3. Cookie menggunakan HttpOnly, SameSite=Lax, dan Secure bila situs memakai HTTPS.
4. Masa berlaku token 10 tahun dan dirotasi saat digunakan.
5. Logout mencabut token perangkat aktif dan menghapus cookie.
6. Bila migrasi SQL belum dijalankan, login biasa tetap dapat digunakan dan aplikasi tidak dibuat gagal.

LANGKAH INSTALASI:
1. Buka phpMyAdmin dari cPanel.
2. Pilih database utama AMS (BUKAN database PACS).
3. Buka tab Import.
4. Pilih file:
   DB/2026-07-14_add_auth_remember_tokens.sql
5. Jalankan Import.
6. Upload/replace seluruh file patch ke instalasi AMS.
7. Uji login dengan mencentang "Ingat saya", tutup browser, lalu buka kembali AMS.
8. Uji tombol Logout; setelah logout, AMS harus meminta login kembali.

Catatan:
- Secara teknis cookie tidak dapat benar-benar abadi. Cookie dapat hilang bila data browser dibersihkan, browser/perangkat diganti, atau token dicabut.
- Jangan mengimpor SQL ini ke database PACS.

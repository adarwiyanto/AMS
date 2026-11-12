PATCH AMS – Remember Me, Dashboard, UI, dan Print (13 Nov 2026)

Instalasi:
1. Backup file situs dan database terlebih dahulu.
2. Ekstrak patch ke direktori induk AMS di hosting sehingga folder ams/ menimpa file dengan path yang sama.
   Jika document root hosting sudah berada DI DALAM folder ams, unggah ISI folder ams/ dari ZIP.
3. Pastikan tabel auth_remember_tokens sudah ada. Jika belum, jalankan SQL yang SUDAH ADA pada paket sebelumnya: DB/2026-07-14_add_auth_remember_tokens.sql melalui phpMyAdmin.
4. Logout lalu login dengan mencentang "Ingat saya" untuk memperoleh token baru (terutama bila token lama sempat terhapus oleh versi sebelumnya).
5. Refresh halaman dengan Ctrl+F5. Cetak dari dialog browser menggunakan ukuran kertas yang sesuai: A4 untuk pemeriksaan/surat; 10 x 15 cm untuk resep. Nonaktifkan header/footer browser bila diperlukan.

Perubahan:
- Token Remember Me stabil (tidak dirotasi setiap restore). Cookie tidak dicabut ketika database hanya mengalami gangguan sementara; login.php otomatis meneruskan sesi yang valid ke dashboard.
- Dashboard tanpa statistik jumlah pasien/kunjungan/resep dan tanpa caption bercanda; keterangan bercanda pada login dihapus.
- Semua 5 halaman cetak memakai print trigger setelah gambar/font siap, perlindungan blok tanda tangan, kontrol pemotongan halaman, dan compact ringan untuk dokumen A4 yang hampir satu halaman. Resep tetap ukuran 10x15cm.

Batasan:
- Browser/printer tetap menentukan hasil pagination akhir. Dokumen terlalu panjang dipecah ke halaman berikutnya agar keterbacaan terjaga.
- Tidak ada migrasi baru. Jika tabel token memang belum ada, fitur Ingat Saya membutuhkan SQL lama tersebut.
- Pengujian: lint PHP seluruh file berubah (lulus). Pengujian end-to-end dengan database dan printer aktual perlu dilakukan di hosting.

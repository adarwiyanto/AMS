PATCH AMS — Dashboard, Riwayat Kunjungan Modal, Input Tanggal Otomatis
Sumber: ams(3).zip | 13 November 2026

CARA PASANG
1. Backup file website dan database sebelum update.
2. Ekstrak ams_patch_dashboard_riwayat_tanggal.zip ke folder root website sehingga file di folder ams/ menggantikan file lama dengan path yang sama. Jika root situs Anda langsung berisi index.php dan patients.php, salin ISI folder ams/ ke root situs.
3. Refresh browser secara penuh (Ctrl+F5) untuk memuat JS/CSS baru.
4. Tidak perlu perubahan database / phpMyAdmin.

PERUBAHAN
- Dashboard dengan 4 kartu statistik ringkas dan daftar 8 kunjungan terbaru.
- Pasien: tombol Riwayat Kunjungan membuka modal tanpa pindah halaman; menampilkan 100 riwayat terbaru dan detail setiap kunjungan.
- Input tanggal aktif pada pasien, edit pasien, antrean, filter kunjungan, resep, rujukan, surat sakit, persetujuan dan jadwal: DD/MM/YYYY, garis miring otomatis, cukup mengetik 8 angka. Server tetap menerima YYYY-MM-DD sesuai kompatibilitas sistem.
- Data tanggal di database tidak dimigrasi.

PENGUJIAN
- PHP lint untuk seluruh file PHP yang diubah: lolos.
- Node syntax check date_inputs.js: lolos.
- Koneksi dan skema database produksi tidak tersedia untuk pengujian fungsional langsung; mohon uji di staging sebelum produksi.

CATATAN
- File backup historis (patients_backup.php dll), modul PACS, file cetak tidak diubah.

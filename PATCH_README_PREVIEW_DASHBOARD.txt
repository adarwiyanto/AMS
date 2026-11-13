PATCH AMS — Dashboard sesuai preview + Sidebar Gelap + Modal Riwayat
Sumber: ams(4).zip

Pasang: backup file situs, lalu salin isi direktori ams/ pada patch ke root instalasi AMS, pertahankan struktur direktori.
File patch:
- index.php: dashboard empat kartu berwarna; panel Kunjungan Terbaru dihapus.
- app/views/partials/header.php: sidebar gelap dengan ikon, tampilan header; logo dan favicon tetap mengikuti pengaturan aplikasi (fallback favicon bawaan jika tidak disetel); CSS cache-busting.
- public/assets/css/theme.css: warna dan layout sesuai preview; responsive; modal overlay yang jelas.
- public/assets/img/metric-*.svg: ikon statistik.

TIDAK ADA migrasi SQL. Data pasien tidak diubah. Tombol/menu lama tetap dipertahankan untuk mencegah fungsi yang ada hilang. Tampilan visual mengikuti preview pada dashboard dan sidebar dengan label menu aktual AMS tetap dipertahankan.

PENGUJIAN: PHP lint pada index.php dan header.php lulus. Belum dites pada browser/server produksi.

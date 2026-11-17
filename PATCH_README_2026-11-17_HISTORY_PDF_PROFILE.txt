PATCH AMS – 17 NOVEMBER 2026
============================================
Sumber: ams(5).zip. Patch hanya berisi berkas baru dan berkas yang berubah.

INSTALASI
1. Backup file aplikasi dan database terlebih dahulu.
2. Ekstrak isi ZIP patch ke folder aplikasi AMS (pertahankan struktur direktori), timpa file yang bernama sama.
3. Pada database AMS yang SUDAH TERPASANG, jalankan DB/2026-11-17_add_user_avatar.sql SATU KALI melalui phpMyAdmin.
   Untuk instalasi baru, install/schema.sql telah mencakup kolom avatar_path; jangan menjalankan ALTER dua kali.
4. Pastikan storage/uploads/ writable oleh PHP untuk foto profil dan tanda tangan.
5. Hard refresh browser (Ctrl+F5) untuk memuat CSS/JS terbaru.

PERUBAHAN
- Riwayat Kunjungan: pilih tanggal -> preview dokumen dalam iframe, tombol Edit / Print A4 / PDF Kontinu.
- Cetak lama: print_visit.php, print_layout.css, dan print_layout.js SAMA SEKALI TIDAK DIUBAH.
- Simpan & PDF: menyimpan terlebih dahulu, lalu membuka PDF dalam tab/alur browser setelah redirect.
- PDF kontinu: PDF asli satu halaman panjang, lebar A4 (595pt), tinggi berdasarkan isi.
- Profil: klik nama/avatar di topbar, Edit Profil dan Logout; menu Profile hilang dari sidebar.
- Edit foto: crop bulat, geser, zoom; JPG/PNG/WebP max 3 MB; tanda tangan preview.

BATASAN YANG PERLU DIKETAHUI
- Preview HTML dan PDF dibuat dari data kunjungan tersimpan, tidak memakai ulang pagination cetak A4.
- PDF berformat teks+gambar native (bukan screenshot). Halaman PDF tunggal dibatasi 14.000pt (~4,9m)
  oleh renderer demi kompatibilitas pembaca PDF. Bila terlampaui akan tampil pesan jelas, tidak terpotong diam-diam.
- Foto PNG/WebP untuk PDF perlu ekstensi PHP GD pada hosting. JPG dapat dimasukkan tanpa GD.
- Font PDF menggunakan Helvetica bawaan dengan transliterasi karakter yang tidak didukung WinAnsi.
- PDF dibuka inline oleh browser sehingga pengguna bisa menyimpan dari viewer; bukan auto-download paksa.
- SQL harus dijalankan sebelum menggunakan edit foto profil.

PENGUJIAN YANG DILAKUKAN
- PHP syntax lint untuk seluruh berkas PHP yang berubah.
- PDF renderer uji dummy -> pdfinfo menampilkan 1 halaman dengan tinggi dinamis.
- Integrasi database, browser live, print fisik dan upload tidak dapat dijalankan tanpa lingkungan hosting AMS.

Adena Medical System (AMS) - Praktek Mandiri - ver 1.1
=====================================================

Cara Install (XAMPP):
1) Copy folder "AMS" ke: C:\xampp\htdocs\AMS  (nama folder bisa Anda ubah sesuai keinginan, mis. "praktek_mandiri")
2) Jalankan Apache & MySQL di XAMPP.
3) Buka di browser:
   http://localhost/AMS/install/

4) Ikuti wizard:
   - Tentukan "Nama Folder/URL Path" (mis. AMS atau praktek_mandiri) untuk base URL aplikasi.
   - Isi kredensial MySQL (host, port, user, password)
   - Masukkan nama database (akan dibuat otomatis)
   - Buat user admin pertama (username + password 2x)
   Wizard TIDAK butuh database sebelumnya.

Catatan penting tentang "Nama Folder":
- Aplikasi TIDAK bisa mengganti nama folder fisik otomatis (batasan permission OS).
- Jika Anda ingin URL menjadi /praktek_mandiri, silakan rename folder di htdocs menjadi praktek_mandiri,
  lalu saat instalasi isi "Nama Folder/URL Path" = praktek_mandiri.

Setelah instalasi sukses:
- Halaman install otomatis terkunci (install/locked.txt dibuat).
- Anda akan diarahkan ke halaman login.

Fitur inti:
- Login + Role (admin/dokter/perawat)
- Profile untuk semua role
- Manajemen user/role via UI + proteksi password (password_hash)
- Pendaftaran pasien (MRN otomatis) atau cari pasien lama
- Kunjungan: anamnesa, PF, laporan USG, terapi default bisa diubah
- Cetak 1 lembar hasil pemeriksaan + kop surat (custom)
- Resep: nomor resep otomatis + cetak resep + tanda tangan digital
- Custom kop surat: nama tempat, alamat, SIP, logo, tanda tangan
- Custom theme: CSS custom via halaman setting (tanpa edit file)
- Backup database: export SQL; opsi upload Google Drive (butuh credential)
- Logging error & audit sederhana ke storage/logs

Default header: "Praktek dr. Agus"
Default footer: © 2026 Adena Medical System ver 1.1


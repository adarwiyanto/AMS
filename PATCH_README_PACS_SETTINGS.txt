PATCH PACS SETTINGS + COLLAPSIBLE SIDEBAR

Isi patch:
- Tambah submenu collapsible/minimized untuk Admin dan Akun.
- Tambah menu Akun > Setting PACS untuk admin.
- Setting PACS bisa mengisi password database dari UI.
- Setting PACS bisa test koneksi database PACS.
- PACS Dashboard tidak blank/error generik saat password belum diisi; diarahkan ke Setting PACS.
- PACS storage path bisa diatur dari app/pacs_config.php.
- PACS tetap memakai database terpisah adey8293_pacs dengan user adey8293_adyto.
- Menu PACS tetap membuka tab baru.

Cara pakai:
1. Upload isi folder ams/ ke root aplikasi AMS, replace file yang sama.
2. Login sebagai admin.
3. Buka Akun > Setting PACS.
4. Isi password database PACS, lalu klik Test Koneksi PACS.
5. Jika berhasil, klik Simpan Setting PACS.
6. Buka menu PACS.

Catatan:
- Password disimpan ke app/pacs_config.php, bukan ke database AMS.
- Jika folder app tidak writable, halaman Setting PACS akan menampilkan konfigurasi manual untuk dicopy ke app/pacs_config.php via cPanel File Manager.

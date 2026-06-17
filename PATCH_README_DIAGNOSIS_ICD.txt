PATCH: Diagnosa Akhir + ICD pada Kunjungan

Perubahan:
1. visits.php
   - Tambah field Diagnosa Akhir di bawah Laporan USG.
   - Tambah field ICD-10 Diagnosa dan ICD-9 Hasil USG.
   - Pemeriksaan Fisik default berisi template TD/Nadi/Respirasi/Suhu/Pemeriksaan lainnya.
   - Query simpan/update kunjungan disesuaikan.

2. visit_edit.php
   - Field Diagnosa Akhir, ICD-10 Diagnosa, ICD-9 Hasil USG ditambahkan pada form edit.
   - Bila Pemeriksaan Fisik kosong, template default ditampilkan.
   - Query update kunjungan disesuaikan.

3. print_visit.php
   - Diagnosa Akhir, ICD-10, ICD-9 USG dicetak di bawah Laporan USG.

4. install/schema.sql
   - Schema fresh install ditambahkan kolom baru.

5. migrations/004_add_visit_diagnosis_icd.sql
   - Jalankan SQL ini di database existing sebelum/bersamaan deploy patch.

SQL migration:
ALTER TABLE visits ADD COLUMN diagnosis MEDIUMTEXT NULL AFTER usg_report;
ALTER TABLE visits ADD COLUMN diagnosis_icd10 VARCHAR(50) NULL AFTER diagnosis;
ALTER TABLE visits ADD COLUMN usg_icd9 VARCHAR(50) NULL AFTER diagnosis_icd10;

Catatan:
- Backup database dan file aplikasi dulu sebelum deploy.
- Patch ini tidak mengubah menu, sidebar, header, theme, PACS, resep, rujukan, atau modul lain.

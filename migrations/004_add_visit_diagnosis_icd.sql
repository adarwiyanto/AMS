-- AMS add-on: Diagnosa akhir dan kode ICD pada kunjungan
-- Jalankan setelah backup database. Jika kolom sudah ada, abaikan error duplicate column.

ALTER TABLE visits
  ADD COLUMN diagnosis MEDIUMTEXT NULL AFTER usg_report;

ALTER TABLE visits
  ADD COLUMN diagnosis_icd10 VARCHAR(50) NULL AFTER diagnosis;

ALTER TABLE visits
  ADD COLUMN usg_icd9 VARCHAR(50) NULL AFTER diagnosis_icd10;

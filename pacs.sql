CREATE TABLE IF NOT EXISTS pacs_patients (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  patient_id VARCHAR(64) NOT NULL,
  patient_name VARCHAR(255) NULL,
  birth_date VARCHAR(16) NULL,
  sex VARCHAR(8) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_pacs_patients_patient_id (patient_id),
  KEY idx_pacs_patients_patient_id (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_studies (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  study_uid VARCHAR(128) NOT NULL,
  patient_id VARCHAR(64) NULL,
  study_date VARCHAR(16) NULL,
  accession VARCHAR(64) NULL,
  study_desc TEXT NULL,
  modalities VARCHAR(64) NULL,
  num_series INT NOT NULL DEFAULT 0,
  num_instances INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_pacs_studies_study_uid (study_uid),
  KEY idx_pacs_studies_patient_id (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_series (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  series_uid VARCHAR(128) NOT NULL,
  study_uid VARCHAR(128) NOT NULL,
  modality VARCHAR(16) NULL,
  series_number INT NULL,
  series_desc TEXT NULL,
  body_part VARCHAR(64) NULL,
  num_instances INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_pacs_series_series_uid (series_uid),
  KEY idx_pacs_series_study_uid (study_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_instances (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sop_uid VARCHAR(128) NOT NULL,
  series_uid VARCHAR(128) NOT NULL,
  study_uid VARCHAR(128) NOT NULL,
  patient_id VARCHAR(64) NULL,
  instance_number INT NULL,
  rows INT NULL,
  cols INT NULL,
  frames INT NULL,
  UNIQUE KEY uq_pacs_instances_sop_uid (sop_uid),
  KEY idx_pacs_instances_series_uid (series_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_files (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sop_uid VARCHAR(128) NOT NULL,
  rel_path TEXT NOT NULL,
  file_size BIGINT NOT NULL DEFAULT 0,
  sha256 VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_pacs_files_sop_uid (sop_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_link_ams_patient (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  dicom_patient_id VARCHAR(64) NOT NULL,
  ams_patient_id INT NOT NULL,
  linked_at DATETIME NOT NULL,
  KEY idx_pacs_link_dicom_patient (dicom_patient_id),
  KEY idx_pacs_link_ams_patient (ams_patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

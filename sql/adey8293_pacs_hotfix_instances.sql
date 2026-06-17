-- Hotfix untuk error import karena nama kolom reserved word ROWS/COLS di MariaDB/MySQL.
-- Jalankan di database adey8293_pacs. Aman untuk database yang sudah ter-import sebagian.

USE `adey8293_pacs`;

CREATE TABLE IF NOT EXISTS pacs_instances (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sop_uid VARCHAR(128) NOT NULL,
  series_uid VARCHAR(128) NOT NULL,
  study_uid VARCHAR(128) NOT NULL,
  patient_id VARCHAR(64) NULL,
  instance_number INT NULL,
  image_rows INT NULL,
  image_cols INT NULL,
  frames INT NULL,
  UNIQUE KEY uq_pacs_instances_sop_uid (sop_uid),
  KEY idx_pacs_instances_series_uid (series_uid),
  KEY idx_pacs_instances_study_uid (study_uid),
  KEY idx_pacs_instances_instance_number (instance_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_files (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sop_uid VARCHAR(128) NOT NULL,
  rel_path TEXT NOT NULL,
  file_size BIGINT NOT NULL DEFAULT 0,
  sha256 VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_pacs_files_sop_uid (sop_uid),
  KEY idx_pacs_files_sha256 (sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_links (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ams_patient_id BIGINT NOT NULL,
  ams_visit_id BIGINT NULL,
  pacs_patient_id VARCHAR(64) NOT NULL,
  study_uid VARCHAR(128) NOT NULL,
  linked_by BIGINT NULL,
  linked_at DATETIME NOT NULL,
  UNIQUE KEY uq_pacs_links_study_uid (study_uid),
  KEY idx_pacs_links_ams_patient (ams_patient_id),
  KEY idx_pacs_links_ams_visit (ams_visit_id),
  KEY idx_pacs_links_pacs_patient (pacs_patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_reports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  study_uid VARCHAR(128) NOT NULL,
  report_title VARCHAR(255) NULL,
  report_body MEDIUMTEXT NULL,
  impression MEDIUMTEXT NULL,
  status ENUM('draft','final') NOT NULL DEFAULT 'draft',
  created_by BIGINT NULL,
  updated_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_pacs_reports_study_uid (study_uid),
  KEY idx_pacs_reports_status (status),
  KEY idx_pacs_reports_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_measurements (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  study_uid VARCHAR(128) NOT NULL,
  series_uid VARCHAR(128) NULL,
  sop_uid VARCHAR(128) NULL,
  tool_type VARCHAR(40) NOT NULL,
  label VARCHAR(120) NULL,
  value DOUBLE NULL,
  unit VARCHAR(20) NULL,
  coordinates_json LONGTEXT NULL,
  created_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_pacs_measurements_study_uid (study_uid),
  KEY idx_pacs_measurements_series_uid (series_uid),
  KEY idx_pacs_measurements_sop_uid (sop_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_autotext_entries (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  content MEDIUMTEXT NULL,
  created_by BIGINT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_pacs_autotext_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pacs_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NULL,
  action VARCHAR(60) NOT NULL,
  study_uid VARCHAR(128) NULL,
  ip VARCHAR(80) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  KEY idx_pacs_audit_user_id (user_id),
  KEY idx_pacs_audit_study_uid (study_uid),
  KEY idx_pacs_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

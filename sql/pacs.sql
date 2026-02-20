CREATE TABLE IF NOT EXISTS pacs_studies (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  patient_name VARCHAR(255) NULL,
  patient_id VARCHAR(128) NULL,
  study_date VARCHAR(32) NULL,
  modality VARCHAR(32) NULL,
  study_uid VARCHAR(128) NOT NULL,
  orthanc_id VARCHAR(128) NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'upload',
  created_at DATETIME NOT NULL,
  INDEX idx_pacs_studies_user_id (user_id),
  INDEX idx_pacs_studies_study_uid (study_uid),
  INDEX idx_pacs_studies_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pacs_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(16) NOT NULL,
  study_uid VARCHAR(128) NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_pacs_audit_user_id (user_id),
  INDEX idx_pacs_audit_study_uid (study_uid),
  INDEX idx_pacs_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

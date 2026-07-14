-- AMS: fitur "Ingat saya" / persistent login
-- Jalankan satu kali pada database utama AMS melalui phpMyAdmin.

CREATE TABLE IF NOT EXISTS `auth_remember_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `selector` CHAR(24) NOT NULL,
  `validator_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  `last_used_at` DATETIME NOT NULL,
  `user_agent_hash` CHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_remember_selector` (`selector`),
  KEY `idx_auth_remember_user_id` (`user_id`),
  KEY `idx_auth_remember_expires_at` (`expires_at`),
  CONSTRAINT `fk_auth_remember_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opsional: membersihkan token kedaluwarsa bila file ini dijalankan kembali.
DELETE FROM `auth_remember_tokens` WHERE `expires_at` < NOW();

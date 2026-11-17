-- Jalankan sekali melalui phpMyAdmin pada database AMS.
ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL DEFAULT NULL;

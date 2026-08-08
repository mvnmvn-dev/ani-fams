-- Patch for existing MySQL/MariaDB installs.
-- Run once against facilities_admin_db. Ignores "duplicate column" errors if already applied.

USE facilities_admin_db;

ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE reservations ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE visitors ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE visits ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE documents ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE legal_cases ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE contracts ADD COLUMN deleted_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email VARCHAR(150) NOT NULL PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at DATETIME NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    INDEX sessions_user_id_index (user_id),
    INDEX sessions_last_activity_index (last_activity)
);

-- Advanced features patch (safe to re-run where CREATE IF NOT EXISTS / ADD COLUMN checks apply)
-- Apply against existing MySQL fams database.

-- Reservations: recurrence, maintenance, reminders
SET @db := DATABASE();

-- Helper pattern: add column if missing
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='reservations' AND COLUMN_NAME='series_id')=0,
  'ALTER TABLE reservations ADD COLUMN series_id VARCHAR(36) NULL AFTER approved_at, ADD COLUMN recurrence_rule ENUM(''none'',''daily'',''weekly'',''monthly'') DEFAULT ''none'' AFTER series_id, ADD COLUMN recurrence_until DATE NULL AFTER recurrence_rule, ADD COLUMN is_maintenance TINYINT(1) DEFAULT 0 AFTER recurrence_until, ADD COLUMN reminder_sent_at DATETIME NULL AFTER is_maintenance, ADD INDEX idx_series (series_id)',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS equipment_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NULL,
    quantity_available INT UNSIGNED DEFAULT 1,
    status ENUM('available','maintenance','retired') DEFAULT 'available',
    notes VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reservation_equipment (
    reservation_id BIGINT UNSIGNED NOT NULL,
    equipment_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED DEFAULT 1,
    PRIMARY KEY (reservation_id, equipment_id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (equipment_id) REFERENCES equipment_items(id) ON DELETE CASCADE
);

INSERT INTO equipment_items (name, category, quantity_available, status)
SELECT * FROM (
    SELECT 'Projector Kit' AS name, 'AV' AS category, 4 AS quantity_available, 'available' AS status
    UNION ALL SELECT 'Laptop Cart', 'IT', 2, 'available'
    UNION ALL SELECT 'Safety Briefing Whiteboard', 'Safety', 6, 'available'
    UNION ALL SELECT 'Conference Phone', 'AV', 3, 'available'
    UNION ALL SELECT 'Refractory Sample Display Kit', 'Technical', 2, 'available'
    UNION ALL SELECT 'Admixture Sampling Kit', 'Lab', 3, 'available'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM equipment_items LIMIT 1);

-- Legal: budget + milestone reminders + vendors
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='legal_cases' AND COLUMN_NAME='budget_amount')=0,
  'ALTER TABLE legal_cases ADD COLUMN budget_amount DECIMAL(14,2) NULL AFTER risk_level',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='legal_case_milestones' AND COLUMN_NAME='reminder_sent_at')=0,
  'ALTER TABLE legal_case_milestones ADD COLUMN reminder_sent_at DATETIME NULL AFTER is_completed',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS legal_vendors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    vendor_type ENUM('counsel','vendor','expert','other') DEFAULT 'counsel',
    contact_name VARCHAR(150) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    firm VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS legal_case_vendors (
    case_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    role_note VARCHAR(255) NULL,
    PRIMARY KEY (case_id, vendor_id),
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES legal_vendors(id) ON DELETE CASCADE
);

-- Contracts: templates, approval chains, e-sign
CREATE TABLE IF NOT EXISTS contract_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contract_type_id INT UNSIGNED NULL,
    body_text TEXT NOT NULL,
    default_value DECIMAL(14,2) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_type_id) REFERENCES contract_types(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS contract_approval_chains (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contract_type_id INT UNSIGNED NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_type_id) REFERENCES contract_types(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS contract_approval_chain_steps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chain_id INT UNSIGNED NOT NULL,
    approver_id INT UNSIGNED NOT NULL,
    sequence_order INT UNSIGNED NOT NULL,
    FOREIGN KEY (chain_id) REFERENCES contract_approval_chains(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
);

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='contracts' AND COLUMN_NAME='template_id')=0,
  'ALTER TABLE contracts ADD COLUMN template_id INT UNSIGNED NULL AFTER contract_type_id, ADD COLUMN signature_token VARCHAR(64) NULL AFTER related_case_id, ADD COLUMN signed_at DATETIME NULL AFTER signature_token, ADD COLUMN signed_by_name VARCHAR(150) NULL AFTER signed_at, ADD COLUMN signed_ip VARCHAR(45) NULL AFTER signed_by_name, ADD INDEX idx_signature_token (signature_token)',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Documents: searchable extracted text
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='document_versions' AND COLUMN_NAME='content_text')=0,
  'ALTER TABLE document_versions ADD COLUMN content_text LONGTEXT NULL AFTER checksum',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Contract drawn signature image path
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='contracts' AND COLUMN_NAME='signature_path')=0,
  'ALTER TABLE contracts ADD COLUMN signature_path VARCHAR(500) NULL AFTER signed_ip',
  'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

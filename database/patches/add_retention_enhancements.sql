-- Retention & Compliance Enhancements
-- Disposal certificate columns, custodian notifications, and policy templates

-- Enhance disposal logs with certificate details (ignore errors if columns already exist)
ALTER TABLE disposal_logs
    ADD COLUMN IF NOT EXISTS certificate_generated BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS certificate_generated_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS certificate_generated_by INT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS retention_policy_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    regulation_code VARCHAR(50) NOT NULL,
    description TEXT NULL,
    retention_period_months INT UNSIGNED NOT NULL,
    trigger_event ENUM('creation_date','last_activity','contract_end','fiscal_year_end') DEFAULT 'creation_date',
    disposal_action ENUM('review','archive','destroy') DEFAULT 'review',
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legal_hold_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_hold_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    notified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notification_type ENUM('placed','released') DEFAULT 'placed',
    read_at DATETIME NULL,
    INDEX idx_legal_hold (legal_hold_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO retention_policy_templates (name, regulation_code, description, retention_period_months, trigger_event, disposal_action)
SELECT * FROM (
    SELECT 'BIR Tax Records - 7 Years' AS name, 'BIR' AS regulation_code,
           'BIR Revenue Regulations No. 18-2012 - 7 year retention for tax documents' AS description,
           84 AS retention_period_months, 'fiscal_year_end' AS trigger_event, 'review' AS disposal_action
) AS t
WHERE NOT EXISTS (SELECT 1 FROM retention_policy_templates WHERE name = 'BIR Tax Records - 7 Years');

INSERT INTO retention_policy_templates (name, regulation_code, description, retention_period_months, trigger_event, disposal_action)
SELECT * FROM (
    SELECT 'SEC Corporate Records - 10 Years', 'SEC',
           'SEC requirements for corporate record retention',
           120, 'creation_date', 'archive'
) AS t
WHERE NOT EXISTS (SELECT 1 FROM retention_policy_templates WHERE name = 'SEC Corporate Records - 10 Years');

INSERT INTO retention_policy_templates (name, regulation_code, description, retention_period_months, trigger_event, disposal_action)
SELECT * FROM (
    SELECT 'Data Privacy Act - 3 Years', 'DPA',
           'National Privacy Commission requirements for personal data',
           36, 'last_activity', 'destroy'
) AS t
WHERE NOT EXISTS (SELECT 1 FROM retention_policy_templates WHERE name = 'Data Privacy Act - 3 Years');

INSERT INTO retention_policy_templates (name, regulation_code, description, retention_period_months, trigger_event, disposal_action)
SELECT * FROM (
    SELECT 'Labor Records - 3 Years', 'DOLE',
           'Department of Labor and Employment requirements',
           36, 'creation_date', 'review'
) AS t
WHERE NOT EXISTS (SELECT 1 FROM retention_policy_templates WHERE name = 'Labor Records - 3 Years');

-- Missing enhancement tables for Legal / Contract / Retention
-- Matches app Models/Repositories (safe to re-run)

CREATE TABLE IF NOT EXISTS legal_case_outcomes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    outcome_type ENUM('won','lost','settled','dismissed','appealed','other') NOT NULL DEFAULT 'other',
    outcome_date DATE NULL,
    description TEXT NULL,
    settlement_amount DECIMAL(14,2) NULL,
    settlement_terms TEXT NULL,
    settlement_date DATE NULL,
    judgment_summary TEXT NULL,
    awarded_amount DECIMAL(14,2) NULL,
    is_appealed TINYINT(1) NOT NULL DEFAULT 0,
    appeal_reason TEXT NULL,
    appeal_deadline DATE NULL,
    recorded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX (case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legal_case_party_conflicts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    party_a_id INT UNSIGNED NOT NULL,
    party_b_id INT UNSIGNED NOT NULL,
    conflict_type ENUM('direct_conflict','prior_representation','family_relation','financial_interest','other') NOT NULL DEFAULT 'other',
    description TEXT NULL,
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    conflict_flagged TINYINT(1) NOT NULL DEFAULT 1,
    flagged_by INT UNSIGNED NULL,
    flagged_at TIMESTAMP NULL,
    resolution_notes TEXT NULL,
    resolution_status ENUM('pending','reviewed','waived','mitigated','unresolved') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (case_id),
    UNIQUE KEY uniq_party_pair (case_id, party_a_id, party_b_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legal_case_counsel_conflicts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    counsel_id INT UNSIGNED NULL,
    external_party VARCHAR(255) NULL,
    conflict_type ENUM('prior_representation','financial_interest','family_relation','business_relation','other') NOT NULL DEFAULT 'other',
    description TEXT NULL,
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    conflict_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_by INT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    mitigation_plan TEXT NULL,
    status ENUM('under_review','confirmed','waived','resolved','dismissed') NOT NULL DEFAULT 'under_review',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contract_obligations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    responsible_party VARCHAR(255) NULL,
    due_date DATE NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_at TIMESTAMP NULL,
    completed_by INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contract_vendor_scores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id INT UNSIGNED NOT NULL,
    quality_score TINYINT UNSIGNED NOT NULL,
    timeliness_score TINYINT UNSIGNED NOT NULL,
    communication_score TINYINT UNSIGNED NOT NULL,
    value_score TINYINT UNSIGNED NOT NULL,
    overall_score DECIMAL(4,2) NOT NULL,
    notes TEXT NULL,
    scored_at DATE NULL,
    scored_by INT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS retention_policy_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    regulation_code VARCHAR(50) NOT NULL,
    description TEXT NULL,
    retention_period_months INT UNSIGNED NOT NULL,
    trigger_event ENUM('creation_date','last_activity','contract_end','fiscal_year_end') DEFAULT 'creation_date',
    disposal_action ENUM('review','archive','destroy') DEFAULT 'review',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
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
SELECT 'BIR Tax Records - 7 Years', 'BIR', 'BIR Revenue Regulations No. 18-2012 - 7 year retention for tax documents', 84, 'fiscal_year_end', 'review'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM retention_policy_templates WHERE name = 'BIR Tax Records - 7 Years');

INSERT INTO retention_policy_templates (name, regulation_code, description, retention_period_months, trigger_event, disposal_action)
SELECT 'SEC Corporate Records - 10 Years', 'SEC', 'SEC requirements for corporate record retention', 120, 'creation_date', 'archive'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM retention_policy_templates WHERE name = 'SEC Corporate Records - 10 Years');

INSERT INTO retention_policy_templates (name, regulation_code, description, retention_period_months, trigger_event, disposal_action)
SELECT 'Data Privacy Act - 3 Years', 'DPA', 'National Privacy Commission requirements for personal data', 36, 'last_activity', 'destroy'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM retention_policy_templates WHERE name = 'Data Privacy Act - 3 Years');

INSERT INTO retention_policy_templates (name, regulation_code, description, retention_period_months, trigger_event, disposal_action)
SELECT 'Labor Records - 3 Years', 'DOLE', 'Department of Labor and Employment requirements', 36, 'creation_date', 'review'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM retention_policy_templates WHERE name = 'Labor Records - 3 Years');

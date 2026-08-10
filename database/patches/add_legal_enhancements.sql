-- Legal Case Management Enhancements
-- Add deadline tracking, timeline visualization, conflict check, and outcome tracking

-- Add statute of limitations/deadline tracking to legal cases
ALTER TABLE legal_cases
ADD COLUMN statute_of_limitations_date DATE NULL,
ADD COLUMN deadline_escalation_level ENUM('none','30_days','7_days','1_day','overdue') DEFAULT 'none',
ADD COLUMN deadline_last_notified_at DATETIME NULL;

-- Add outcome/settlement tracking
ALTER TABLE legal_cases
ADD COLUMN outcome_type ENUM('settled','judgment','dismissed','won','lost','ongoing') NULL,
ADD COLUMN settlement_amount DECIMAL(14,2) NULL,
ADD COLUMN settlement_currency VARCHAR(10) DEFAULT 'PHP',
ADD COLUMN outcome_date DATE NULL,
ADD COLUMN outcome_notes TEXT NULL;

-- Add conflict-of-interest tracking
CREATE TABLE IF NOT EXISTS legal_conflict_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    party_name VARCHAR(255) NOT NULL,
    party_type ENUM('plaintiff','defendant','witness','counsel','third_party') NOT NULL,
    matched_against_type ENUM('party','vendor') NOT NULL,
    matched_against_id INT UNSIGNED NOT NULL,
    matched_against_name VARCHAR(255) NOT NULL,
    conflict_level ENUM('high','medium','low','none') DEFAULT 'none',
    notes TEXT NULL,
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    checked_by INT UNSIGNED NOT NULL,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_case (case_id),
    INDEX idx_party_name (party_name)
);

-- Add timeline event tracking for visualization
CREATE TABLE IF NOT EXISTS legal_timeline_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_date DATETIME NOT NULL,
    description TEXT NULL,
    related_milestone_id BIGINT UNSIGNED NULL,
    related_cost_id BIGINT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    INDEX idx_case_date (case_id, event_date)
);

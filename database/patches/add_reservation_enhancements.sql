-- Reservations Module Enhancements
-- Add waitlist, cost tracking, no-show tracking, and feedback features

-- Add cost tracking to reservations
ALTER TABLE reservations 
ADD COLUMN cost_amount DECIMAL(10,2) NULL,
ADD COLUMN cost_currency VARCHAR(10) DEFAULT 'PHP',
ADD COLUMN chargeback_department_id INT UNSIGNED NULL,
ADD COLUMN cost_note VARCHAR(255) NULL;

-- Add no-show/cancellation tracking
ALTER TABLE reservations
ADD COLUMN no_show BOOLEAN DEFAULT FALSE,
ADD COLUMN no_show_reported_by INT UNSIGNED NULL,
ADD COLUMN no_show_reported_at DATETIME NULL,
ADD COLUMN cancellation_reason VARCHAR(255) NULL,
ADD COLUMN cancelled_by INT UNSIGNED NULL,
ADD COLUMN cancelled_at DATETIME NULL;

-- Add feedback tracking
ALTER TABLE reservations
ADD COLUMN feedback_rating INT UNSIGNED NULL,
ADD COLUMN feedback_comment TEXT NULL,
ADD COLUMN feedback_submitted_at DATETIME NULL;

-- Add waitlist table
CREATE TABLE IF NOT EXISTS reservation_waitlist (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facility_id INT UNSIGNED NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    preferred_start_datetime DATETIME NOT NULL,
    preferred_end_datetime DATETIME NOT NULL,
    status ENUM('pending','offered','declined','expired') DEFAULT 'pending',
    offered_reservation_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (offered_reservation_id) REFERENCES reservations(id) ON DELETE SET NULL,
    INDEX idx_facility_status (facility_id, status),
    INDEX idx_requested_by (requested_by)
);

-- Add foreign key for chargeback department
ALTER TABLE reservations
ADD CONSTRAINT fk_reservations_chargeback_dept 
FOREIGN KEY (chargeback_department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- Add foreign keys for no-show and cancellation tracking
ALTER TABLE reservations
ADD CONSTRAINT fk_reservations_no_show_reporter 
FOREIGN KEY (no_show_reported_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_reservations_cancelled_by 
FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL;

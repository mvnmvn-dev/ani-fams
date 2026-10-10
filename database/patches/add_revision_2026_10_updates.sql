-- Apply once to an existing MySQL/MariaDB installation after pulling the revision.
-- Fresh installations receive these structures from database/schema.sql.

ALTER TABLE reservations
    ADD COLUMN attendee_count INT UNSIGNED NULL AFTER recurrence_until,
    ADD COLUMN in_charge_user_id INT UNSIGNED NULL AFTER approved_at,
    ADD CONSTRAINT fk_reservations_in_charge_user
        FOREIGN KEY (in_charge_user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE documents
    MODIFY COLUMN status ENUM('draft','active','archived','disposed') DEFAULT 'active',
    ADD COLUMN archived_at DATETIME NULL AFTER status,
    ADD COLUMN archive_reason VARCHAR(255) NULL AFTER archived_at;

CREATE TABLE IF NOT EXISTS legal_employee_departures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_name VARCHAR(150) NOT NULL,
    departure_type ENUM('resigned','terminated') NOT NULL,
    effective_date DATE NOT NULL,
    source_document_id BIGINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    recorded_by INT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (source_document_id) REFERENCES documents(id) ON DELETE RESTRICT,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_legal_departure_date (effective_date),
    INDEX idx_legal_departure_name (employee_name)
);

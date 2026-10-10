-- Additive, idempotent PostgreSQL upgrade for revision 2026-10.
-- Run against an existing database before deploying the application changes.

ALTER TABLE reservations ADD COLUMN IF NOT EXISTS attendee_count INTEGER NULL;
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS in_charge_user_id INTEGER NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_reservations_in_charge_user'
          AND conrelid = 'reservations'::regclass
    ) THEN
        ALTER TABLE reservations
            ADD CONSTRAINT fk_reservations_in_charge_user
            FOREIGN KEY (in_charge_user_id) REFERENCES users(id) ON DELETE SET NULL;
    END IF;
END $$;

ALTER TABLE documents ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS archive_reason VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS legal_employee_departures (
    id BIGSERIAL PRIMARY KEY,
    employee_name VARCHAR(150) NOT NULL,
    departure_type TEXT NOT NULL CHECK (departure_type IN ('resigned', 'terminated')),
    effective_date DATE NOT NULL,
    source_document_id BIGINT NOT NULL REFERENCES documents(id) ON DELETE RESTRICT,
    notes TEXT NULL,
    recorded_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_legal_departure_date ON legal_employee_departures(effective_date);
CREATE INDEX IF NOT EXISTS idx_legal_departure_name ON legal_employee_departures(employee_name);

-- Visitor Management Enhancements
-- Add watchlist improvements, vehicle logging, and NDA e-signature features

-- Enhance watchlist with reason and expiry
ALTER TABLE visitors 
ADD COLUMN watchlist_reason VARCHAR(255) NULL,
ADD COLUMN watchlist_expiry_date DATE NULL,
ADD COLUMN watchlist_added_by INT UNSIGNED NULL,
ADD COLUMN watchlist_added_at DATETIME NULL;

-- Add vehicle/plate logging
CREATE TABLE IF NOT EXISTS visitor_vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT UNSIGNED NOT NULL,
    visit_id BIGINT UNSIGNED NULL,
    plate_number VARCHAR(50) NOT NULL,
    vehicle_make VARCHAR(100) NULL,
    vehicle_model VARCHAR(100) NULL,
    vehicle_color VARCHAR(50) NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL,
    INDEX idx_visitor_id (visitor_id),
    INDEX idx_visit_id (visit_id)
);

-- Add NDA agreement tracking
ALTER TABLE visits
ADD COLUMN nda_required BOOLEAN DEFAULT FALSE,
ADD COLUMN nda_signed BOOLEAN DEFAULT FALSE,
ADD COLUMN nda_signature_path VARCHAR(500) NULL,
ADD COLUMN nda_signed_at DATETIME NULL,
ADD COLUMN nda_ip_address VARCHAR(45) NULL;

-- Add foreign keys for watchlist enhancements
ALTER TABLE visitors
ADD CONSTRAINT fk_visitors_watchlist_added_by 
FOREIGN KEY (watchlist_added_by) REFERENCES users(id) ON DELETE SET NULL;

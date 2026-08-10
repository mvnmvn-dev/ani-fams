-- Document Management Enhancements
-- Add expiration reminders, check-out/lock, OCR support, bulk upload tracking, and e-signature requests

-- Add review date and reminder tracking
ALTER TABLE documents
ADD COLUMN review_by_date DATE NULL,
ADD COLUMN review_reminder_sent_at DATETIME NULL,
ADD COLUMN last_reviewed_at DATETIME NULL,
ADD COLUMN last_reviewed_by INT UNSIGNED NULL;

-- Add check-out/lock functionality
ALTER TABLE documents
ADD COLUMN checked_out_by INT UNSIGNED NULL,
ADD COLUMN checked_out_at DATETIME NULL,
ADD COLUMN checked_out_notes VARCHAR(255) NULL;

-- Add OCR processing flag
ALTER TABLE document_versions
ADD COLUMN ocr_processed BOOLEAN DEFAULT FALSE,
ADD COLUMN ocr_processed_at DATETIME NULL,
ADD COLUMN ocr_confidence DECIMAL(5,2) NULL;

-- Add bulk upload tracking
CREATE TABLE IF NOT EXISTS bulk_uploads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uploaded_by INT UNSIGNED NOT NULL,
    module VARCHAR(50) NOT NULL,
    file_count INT UNSIGNED NOT NULL,
    status ENUM('processing','completed','failed') DEFAULT 'processing',
    error_message TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Add e-signature request tracking
CREATE TABLE IF NOT EXISTS document_signature_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    signer_email VARCHAR(150) NOT NULL,
    signer_name VARCHAR(150) NOT NULL,
    message TEXT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('pending','signed','declined','expired') DEFAULT 'pending',
    signed_at DATETIME NULL,
    signature_path VARCHAR(500) NULL,
    signature_ip VARCHAR(45) NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_document (document_id)
);

-- Add foreign keys for document enhancements
ALTER TABLE documents
ADD CONSTRAINT fk_documents_last_reviewed_by 
FOREIGN KEY (last_reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_documents_checked_out_by 
FOREIGN KEY (checked_out_by) REFERENCES users(id) ON DELETE SET NULL;

-- =========================================================
-- FAMS — Facilities & Administrative Management System
-- COMPLETE schema + RBAC seed (Admin / Staff / Employee)
-- Engine: MySQL / MariaDB (source file)
--
-- PostgreSQL installs: run `php database/convert_schema_to_postgres.php`
-- and import `database/schema.postgres.sql` instead of this file.
--
-- CLEAN REIMPORT (phpMyAdmin or CLI):
--   1. DROP DATABASE IF EXISTS facilities_admin_db;
--   2. Import: database/schema.sql
--   3. Default logins (password for all: Password123!):
--        admin@facilitiesadmin.local
--        staff@facilitiesadmin.local
--        employee@facilitiesadmin.local
--
-- Includes ALL modules + enhancements:
--   core (users/2FA, sessions, cache, audit, notifications)
--   reservations (recurring, equipment, waitlist, cost, feedback)
--   visitors (watchlist detail, vehicles, NDA e-sign)
--   documents (OCR, review/checkout, bulk upload, signature requests)
--   retention (templates, hold notifications, disposal certificates)
--   legal (deadlines, outcomes, conflicts, vendors, budget)
--   contracts (templates, chains, e-sign, obligations, vendor scores)
--   queue jobs
-- =========================================================

CREATE DATABASE IF NOT EXISTS facilities_admin_db;
USE facilities_admin_db;

-- ===================== CORE / SHARED =====================

CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    parent_department_id INT UNSIGNED NULL,
    FOREIGN KEY (parent_department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,        -- admin | staff | employee
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,       -- e.g. reservation.approve, contract.manage
    description VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    otp_email VARCHAR(150) NULL,
    password_hash VARCHAR(255) NOT NULL,
    two_factor_secret TEXT NULL,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    role_id INT UNSIGNED NOT NULL,            -- one primary role per user (admin/staff/employee)
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    photo_path VARCHAR(500) NULL,
    remember_token VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);

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

CREATE TABLE IF NOT EXISTS cache (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    value MEDIUMTEXT NOT NULL,
    expiration INT NOT NULL,
    INDEX cache_expiration_index (expiration)
);

CREATE TABLE IF NOT EXISTS cache_locks (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration INT NOT NULL,
    INDEX cache_locks_expiration_index (expiration)
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id VARCHAR(50) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    module VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100),
    file_size INT UNSIGNED,
    uploaded_by INT UNSIGNED NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ===================== SEED: ROLES =====================

INSERT INTO roles (code, name, description) VALUES
('admin',    'Administrator', 'Full access to every module and user management'),
('staff',    'Staff',         'Operational access: process, approve, and manage day-to-day records'),
('employee', 'Employee',      'Self-service access: submit requests and view own/allowed records')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ===================== SEED: PERMISSIONS =====================
-- Naming convention: module.action

INSERT INTO permissions (code, description) VALUES
-- Facilities Reservation
('reservation.view',     'View facility reservations'),
('reservation.create',   'Create a facility reservation request'),
('reservation.approve',  'Approve or reject reservation requests'),
('reservation.manage',   'Manage facilities (create/edit/retire facility records)'),
-- Visitor Management
('visitor.view',         'View visitor logs'),
('visitor.register',     'Pre-register / log a visitor'),
('visitor.checkin',      'Check visitors in and out'),
('visitor.manage',       'Manage watchlist and visit areas'),
-- Document Management
('document.view',        'View documents'),
('document.upload',      'Upload new documents / new versions'),
('document.manage',      'Manage categories, tags, and access control'),
-- Records Retention & Compliance
('retention.view',       'View retention schedules and status'),
('retention.manage',     'Define retention policies and process disposals'),
-- Legal Management
('legal.view',           'View legal cases'),
('legal.manage',         'Create/update legal cases and milestones'),
-- Contract Management
('contract.view',        'View contracts'),
('contract.create',      'Draft new contracts'),
('contract.approve',     'Approve contracts in the approval chain'),
('contract.manage',      'Full contract lifecycle management'),
-- Admin / Users
('users.manage',         'Manage user accounts and role assignments'),
('audit.view',           'View system audit log history')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ===================== SEED: ROLE-PERMISSION MAPPING =====================

-- ADMIN: gets every permission that exists
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.code = 'admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- STAFF: operational, day-to-day processing + approvals, no policy/legal ownership, no user management
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.code = 'staff' AND p.code IN (
    'reservation.view','reservation.create','reservation.approve','reservation.manage',
    'visitor.view','visitor.register','visitor.checkin','visitor.manage',
    'document.view','document.upload','document.manage',
    'retention.view',
    'legal.view',
    'contract.view','contract.create','contract.approve'
)
ON DUPLICATE KEY UPDATE role_id = role_id;

-- EMPLOYEE: self-service only - submit requests, view what's allowed, no approvals/management
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.code = 'employee' AND p.code IN (
    'reservation.view','reservation.create',
    'visitor.view','visitor.register',
    'document.view',
    'contract.view'
)
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ===================== SEED: DEPARTMENT + DEMO USERS =====================
-- Demo password for all 3 accounts below is:  Password123!
-- (bcrypt hash generated with PHP password_hash(); change immediately in a real deployment)

INSERT INTO departments (name) VALUES ('Head Office Administration')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO users (department_id, full_name, email, password_hash, role_id, status)
SELECT d.id, 'System Administrator', 'admin@facilitiesadmin.local',
       '$2b$10$hZKzeKDRqM3tEoDxyubENe//aA0lipM6wk7nfH02mpJtdO.oDIeea', r.id, 'active'
FROM departments d, roles r WHERE d.name = 'Head Office Administration' AND r.code = 'admin'
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO users (department_id, full_name, email, password_hash, role_id, status)
SELECT d.id, 'Facilities Staff', 'staff@facilitiesadmin.local',
       '$2b$10$hZKzeKDRqM3tEoDxyubENe//aA0lipM6wk7nfH02mpJtdO.oDIeea', r.id, 'active'
FROM departments d, roles r WHERE d.name = 'Head Office Administration' AND r.code = 'staff'
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO users (department_id, full_name, email, password_hash, role_id, status)
SELECT d.id, 'Sample Employee', 'employee@facilitiesadmin.local',
       '$2b$10$hZKzeKDRqM3tEoDxyubENe//aA0lipM6wk7nfH02mpJtdO.oDIeea', r.id, 'active'
FROM departments d, roles r WHERE d.name = 'Head Office Administration' AND r.code = 'employee'
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- ===================== MODULE TABLES =====================

CREATE TABLE IF NOT EXISTS facilities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('room','equipment','vehicle','venue','other') DEFAULT 'room',
    location VARCHAR(255),
    capacity INT UNSIGNED NULL,
    status ENUM('available','maintenance','retired') DEFAULT 'available',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reservations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facility_id INT UNSIGNED NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    purpose VARCHAR(255),
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    status ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    series_id VARCHAR(36) NULL,
    recurrence_rule ENUM('none','daily','weekly','monthly') DEFAULT 'none',
    recurrence_until DATE NULL,
    is_maintenance TINYINT(1) DEFAULT 0,
    reminder_sent_at DATETIME NULL,
    -- Cost / chargeback
    cost_amount DECIMAL(10,2) NULL,
    cost_currency VARCHAR(10) DEFAULT 'PHP',
    chargeback_department_id INT UNSIGNED NULL,
    cost_note VARCHAR(255) NULL,
    -- No-show / cancellation
    no_show BOOLEAN DEFAULT FALSE,
    no_show_reported_by INT UNSIGNED NULL,
    no_show_reported_at DATETIME NULL,
    cancellation_reason VARCHAR(255) NULL,
    cancelled_by INT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    -- Feedback
    feedback_rating INT UNSIGNED NULL,
    feedback_comment TEXT NULL,
    feedback_submitted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (chargeback_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (no_show_reported_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_facility_time (facility_id, start_datetime, end_datetime),
    INDEX idx_series (series_id)
);

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

INSERT INTO facilities (name, type, location, capacity, status) VALUES
('HQ Engineering Conference Room', 'room', 'Marikina City Headquarters — 2nd Floor', 12, 'available'),
('Sales & Technical Briefing Room', 'room', 'Marikina City Headquarters — 2nd Floor', 8, 'available'),
('Blending Plant Coordination Bay', 'room', 'Production & Blending Facility', 10, 'available'),
('Warehouse Staging Area', 'venue', 'Warehouse & Loading Dock', 25, 'available'),
('Training & Safety Hall', 'venue', 'Marikina City Headquarters — Ground Floor', 80, 'available'),
('Field Service Van', 'vehicle', 'Site Operations / Motor Pool', 8, 'available')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO equipment_items (name, category, quantity_available, status) VALUES
('Projector Kit', 'AV', 4, 'available'),
('Laptop Cart', 'IT', 2, 'available'),
('Safety Briefing Whiteboard', 'Safety', 6, 'available'),
('Conference Phone', 'AV', 3, 'available'),
('Refractory Sample Display Kit', 'Technical', 2, 'available'),
('Admixture Sampling Kit', 'Lab', 3, 'available')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ===================== EMPLOYEES =====================
CREATE TABLE IF NOT EXISTS employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    full_name VARCHAR(150) NOT NULL,
    position VARCHAR(150),
    email VARCHAR(150),
    phone VARCHAR(30),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- Seed employee records corresponding to user accounts
INSERT INTO employees (user_id, department_id, full_name, position, email, phone)
SELECT u.id, u.department_id, u.full_name, 
       CASE 
           WHEN r.code = 'admin' THEN 'System Administrator'
           WHEN r.code = 'staff' THEN 'Plant & Facilities Manager'
           ELSE 'Staff Employee'
       END,
       u.email, '555-010'
FROM users u
JOIN roles r ON r.id = u.role_id
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- ===================== MODULE 2: VISITOR MANAGEMENT =====================
CREATE TABLE IF NOT EXISTS visitors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    id_number VARCHAR(100) NULL,
    company VARCHAR(150) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    photo_path VARCHAR(500) NULL,
    is_watchlisted BOOLEAN DEFAULT FALSE,
    watchlist_reason VARCHAR(255) NULL,
    watchlist_expiry_date DATE NULL,
    watchlist_added_by INT UNSIGNED NULL,
    watchlist_added_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (watchlist_added_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS visits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT UNSIGNED NOT NULL,
    host_employee_id INT UNSIGNED NOT NULL,
    purpose VARCHAR(255),
    scheduled_at DATETIME NULL,
    check_in_time DATETIME NULL,
    check_out_time DATETIME NULL,
    badge_code VARCHAR(50) NULL,
    status ENUM('scheduled','checked_in','checked_out','no_show','denied') DEFAULT 'scheduled',
    notes TEXT NULL,
    nda_required BOOLEAN DEFAULT FALSE,
    nda_signed BOOLEAN DEFAULT FALSE,
    nda_signature_path VARCHAR(500) NULL,
    nda_signed_at DATETIME NULL,
    nda_ip_address VARCHAR(45) NULL,
    deleted_at DATETIME NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (host_employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

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

CREATE TABLE IF NOT EXISTS visit_areas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS visit_area_access (
    visit_id BIGINT UNSIGNED NOT NULL,
    visit_area_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (visit_id, visit_area_id),
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (visit_area_id) REFERENCES visit_areas(id) ON DELETE CASCADE
);

-- Seed visit areas (ANI Marikina HQ, plant, and warehouse)
INSERT INTO visit_areas (name) VALUES 
('HQ Lobby / Reception'),
('Head Office Support Area'),
('Sales & Technical Offices'),
('Blending Plant Floor'),
('Warehouse & Loading Dock'),
('Executive Suite')
ON DUPLICATE KEY UPDATE name = name;

-- ===================== MODULE 3: DOCUMENT MANAGEMENT =====================
CREATE TABLE IF NOT EXISTS document_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    parent_id INT UNSIGNED NULL,
    FOREIGN KEY (parent_id) REFERENCES document_categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    document_number VARCHAR(100) NULL UNIQUE,
    current_version_id BIGINT UNSIGNED NULL,
    owner_id INT UNSIGNED NOT NULL,
    confidentiality ENUM('public','internal','confidential','restricted') DEFAULT 'internal',
    status ENUM('draft','active','archived','disposed') DEFAULT 'active',
    review_by_date DATE NULL,
    review_reminder_sent_at DATETIME NULL,
    last_reviewed_at DATETIME NULL,
    last_reviewed_by INT UNSIGNED NULL,
    checked_out_by INT UNSIGNED NULL,
    checked_out_at DATETIME NULL,
    checked_out_notes VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (last_reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_out_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS document_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    checksum VARCHAR(64) NULL,
    content_text LONGTEXT NULL,
    ocr_processed BOOLEAN DEFAULT FALSE,
    ocr_processed_at DATETIME NULL,
    ocr_confidence DECIMAL(5,2) NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    change_notes VARCHAR(255) NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE documents ADD CONSTRAINT fk_documents_current_version 
FOREIGN KEY (current_version_id) REFERENCES document_versions(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS document_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS document_tag_map (
    document_id BIGINT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (document_id, tag_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES document_tags(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS document_access (
    document_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL DEFAULT 0,   -- 0 = role-based row (no specific user)
    role_id INT UNSIGNED NOT NULL DEFAULT 0,   -- 0 = user-based row (no specific role)
    access_level ENUM('view','edit','manage') DEFAULT 'view',
    PRIMARY KEY (document_id, user_id, role_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
    -- Note: user_id/role_id are not FK-constrained so 0 can be used as a sentinel
);

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

-- Seed document categories
INSERT INTO document_categories (name) VALUES 
('Legal Records'), ('Financial Invoices'), ('HR Documents'), ('Standard Operating Procedures'), ('Contracts')
ON DUPLICATE KEY UPDATE name = name;

-- ===================== MODULE 4: RECORDS RETENTION =====================
CREATE TABLE IF NOT EXISTS retention_policies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    record_category VARCHAR(150) NOT NULL,
    retention_period_months INT UNSIGNED NOT NULL,
    trigger_event ENUM('creation_date','last_activity','contract_end','fiscal_year_end') DEFAULT 'creation_date',
    disposal_action ENUM('review','archive','destroy') DEFAULT 'review',
    legal_basis VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS retention_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    policy_id INT UNSIGNED NOT NULL,
    retention_start_date DATE NOT NULL,
    calculated_disposal_date DATE NOT NULL,
    status ENUM('active','due_for_review','on_legal_hold','disposed') DEFAULT 'active',
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (policy_id) REFERENCES retention_policies(id) ON DELETE CASCADE,
    INDEX idx_disposal_date (calculated_disposal_date)
);

CREATE TABLE IF NOT EXISTS legal_cases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    case_type ENUM('litigation','regulatory','labor','ip','corporate','other') DEFAULT 'other',
    status ENUM('open','in_progress','on_hold','closed','won','lost','settled') DEFAULT 'open',
    filed_date DATE NULL,
    court_or_agency VARCHAR(255) NULL,
    opposing_party VARCHAR(255) NULL,
    assigned_counsel_id INT UNSIGNED NULL,
    external_counsel_name VARCHAR(255) NULL,
    risk_level ENUM('low','medium','high','critical') DEFAULT 'medium',
    budget_amount DECIMAL(14,2) NULL,
    statute_of_limitations_date DATE NULL,
    deadline_escalation_level ENUM('none','30_days','7_days','1_day','overdue') DEFAULT 'none',
    deadline_last_notified_at DATETIME NULL,
    description TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (assigned_counsel_id) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS legal_holds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reason VARCHAR(255) NOT NULL,
    related_case_id INT UNSIGNED NULL,
    placed_by INT UNSIGNED NOT NULL,
    placed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    released_at DATETIME NULL,
    FOREIGN KEY (placed_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_case_id) REFERENCES legal_cases(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS legal_hold_documents (
    legal_hold_id INT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (legal_hold_id, document_id),
    FOREIGN KEY (legal_hold_id) REFERENCES legal_holds(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS disposal_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    retention_assignment_id BIGINT UNSIGNED NOT NULL,
    action_taken ENUM('reviewed','archived','destroyed','hold_extended') NOT NULL,
    performed_by INT UNSIGNED NOT NULL,
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    certificate_path VARCHAR(500) NULL,
    certificate_generated TINYINT(1) NOT NULL DEFAULT 0,
    certificate_generated_at DATETIME NULL,
    certificate_generated_by INT UNSIGNED NULL,
    FOREIGN KEY (retention_assignment_id) REFERENCES retention_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_hold_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legal_hold_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    notified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notification_type ENUM('placed','released') DEFAULT 'placed',
    read_at DATETIME NULL,
    INDEX idx_legal_hold (legal_hold_id),
    INDEX idx_user (user_id)
);

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
);

-- Seed retention policies
INSERT INTO retention_policies (name, record_category, retention_period_months, trigger_event, disposal_action, legal_basis) VALUES
('Standard HR Records - 5 Years', 'HR Files', 60, 'creation_date', 'destroy', 'National HR Regulations'),
('Tax Documents - 7 Years', 'Financial Invoices', 84, 'fiscal_year_end', 'review', 'Internal Revenue Service'),
('Active Contracts - 10 Years', 'Contracts', 120, 'contract_end', 'archive', 'Corporate Governance Policy')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO retention_policy_templates (name, regulation_code, description, retention_period_months, trigger_event, disposal_action) VALUES
('BIR Tax Records - 7 Years', 'BIR', 'BIR Revenue Regulations No. 18-2012 - 7 year retention for tax documents', 84, 'fiscal_year_end', 'review'),
('SEC Corporate Records - 10 Years', 'SEC', 'SEC requirements for corporate record retention', 120, 'creation_date', 'archive'),
('Data Privacy Act - 3 Years', 'DPA', 'National Privacy Commission requirements for personal data', 36, 'last_activity', 'destroy'),
('Labor Records - 3 Years', 'DOLE', 'Department of Labor and Employment requirements', 36, 'creation_date', 'review')
ON DUPLICATE KEY UPDATE name = name;

-- ===================== MODULE 5: LEGAL MANAGEMENT =====================
CREATE TABLE IF NOT EXISTS legal_case_parties (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    party_name VARCHAR(255) NOT NULL,
    role ENUM('plaintiff','defendant','witness','third_party','counsel') NOT NULL,
    contact_info VARCHAR(255) NULL,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_case_milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    milestone_type ENUM('hearing','filing_deadline','discovery','settlement_talk','other') NOT NULL,
    milestone_date DATETIME NOT NULL,
    description VARCHAR(255) NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    reminder_sent_at DATETIME NULL,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    INDEX idx_milestone_date (milestone_date)
);

CREATE TABLE IF NOT EXISTS legal_case_documents (
    case_id INT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (case_id, document_id),
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_case_costs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    incurred_date DATE NOT NULL,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE
);

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

CREATE TABLE IF NOT EXISTS legal_case_deadlines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    description TEXT NULL,
    is_completed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    INDEX (case_id)
);

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
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (case_id)
);

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
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (party_a_id) REFERENCES legal_case_parties(id) ON DELETE CASCADE,
    FOREIGN KEY (party_b_id) REFERENCES legal_case_parties(id) ON DELETE CASCADE,
    FOREIGN KEY (flagged_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_party_pair (case_id, party_a_id, party_b_id),
    INDEX (case_id)
);

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
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (counsel_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (case_id)
);

-- ===================== MODULE 6: CONTRACT MANAGEMENT =====================
CREATE TABLE IF NOT EXISTS contract_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL
);

-- Seed contract types
INSERT INTO contract_types (name) VALUES 
('Vendor Agreement'), ('NDA'), ('Lease'), ('Partnership Agreement'), ('Employment Contract')
ON DUPLICATE KEY UPDATE name = name;

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

CREATE TABLE IF NOT EXISTS contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_number VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    contract_type_id INT UNSIGNED NULL,
    template_id INT UNSIGNED NULL,
    counterparty_name VARCHAR(255) NOT NULL,
    counterparty_contact VARCHAR(255) NULL,
    owner_id INT UNSIGNED NOT NULL,
    value_amount DECIMAL(14,2) NULL,
    currency VARCHAR(10) DEFAULT 'PHP',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    auto_renew BOOLEAN DEFAULT FALSE,
    renewal_notice_days INT UNSIGNED DEFAULT 30,
    status ENUM('draft','in_review','pending_signature','active','expired','terminated','renewed') DEFAULT 'draft',
    related_case_id INT UNSIGNED NULL,
    signature_token VARCHAR(64) NULL,
    signed_at DATETIME NULL,
    signed_by_name VARCHAR(150) NULL,
    signed_ip VARCHAR(45) NULL,
    signature_path VARCHAR(500) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (contract_type_id) REFERENCES contract_types(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_case_id) REFERENCES legal_cases(id) ON DELETE SET NULL,
    INDEX idx_end_date (end_date),
    INDEX idx_signature_token (signature_token)
);

CREATE TABLE IF NOT EXISTS contract_approvals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    approver_id INT UNSIGNED NOT NULL,
    sequence_order INT UNSIGNED NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    decided_at DATETIME NULL,
    comments VARCHAR(255) NULL,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_documents (
    contract_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (contract_id, document_id),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_amendments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    amendment_number INT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    effective_date DATE NOT NULL,
    document_id BIGINT UNSIGNED NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_obligations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
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
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (contract_id)
);

CREATE TABLE IF NOT EXISTS contract_vendor_scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
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
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (scored_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (contract_id)
);

-- Seed a starter contract template (optional demo content)
INSERT INTO contract_templates (name, contract_type_id, body_text, default_value, is_active, created_by)
SELECT 'Standard NDA Template', ct.id,
       'This Non-Disclosure Agreement ("Agreement") is entered into between Archon Nell Incorporated ("the Company") and the Counterparty.\n\n1. Confidential Information\n2. Obligations of Receiving Party\n3. Term and Termination\n4. Governing Law',
       NULL, 1, u.id
FROM contract_types ct
CROSS JOIN users u
JOIN roles r ON r.id = u.role_id AND r.code = 'admin'
WHERE ct.name = 'NDA'
LIMIT 1;

-- ===================== QUEUE JOBS =====================
CREATE TABLE IF NOT EXISTS jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INT UNSIGNED NULL,
    available_at INT UNSIGNED NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    INDEX jobs_queue_index (queue)
);

CREATE TABLE IF NOT EXISTS job_batches (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INT NOT NULL,
    pending_jobs INT NOT NULL,
    failed_jobs INT NOT NULL,
    failed_job_ids LONGTEXT NOT NULL,
    options MEDIUMTEXT NULL,
    cancelled_at INT NULL,
    created_at INT NOT NULL,
    finished_at INT NULL
);

CREATE TABLE IF NOT EXISTS failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

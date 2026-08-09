-- =========================================================
-- FAMS — Facilities & Administrative Management System
-- Full schema + RBAC seed (Admin / Staff / Employee)
-- Engine: MySQL / MariaDB
--
-- CLEAN REIMPORT (phpMyAdmin or CLI):
--   1. Drop the old database (optional but recommended):
--        DROP DATABASE IF EXISTS facilities_admin_db;
--   2. Import THIS file only (do not run patches afterward):
--        database/schema.sql
--   3. Default logins (password for all: password)
--        admin@facilitiesadmin.local
--        staff@facilitiesadmin.local
--        employee@facilitiesadmin.local
--
-- Includes: reservations (recurring/maintenance/equipment),
-- visitors, documents (content_text search), retention, legal
-- (budget/vendors/milestones), contracts (templates/chains/
-- e-sign signature_path), notifications, attachments, sessions,
-- password reset, queue tables.
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
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,            -- one primary role per user (admin/staff/employee)
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    photo_path VARCHAR(500) NULL,
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

INSERT INTO departments (name) VALUES ('General Administration')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO users (department_id, full_name, email, password_hash, role_id, status)
SELECT d.id, 'System Administrator', 'admin@facilitiesadmin.local',
       '$2b$10$hZKzeKDRqM3tEoDxyubENe//aA0lipM6wk7nfH02mpJtdO.oDIeea', r.id, 'active'
FROM departments d, roles r WHERE d.name = 'General Administration' AND r.code = 'admin'
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO users (department_id, full_name, email, password_hash, role_id, status)
SELECT d.id, 'Facilities Staff', 'staff@facilitiesadmin.local',
       '$2b$10$hZKzeKDRqM3tEoDxyubENe//aA0lipM6wk7nfH02mpJtdO.oDIeea', r.id, 'active'
FROM departments d, roles r WHERE d.name = 'General Administration' AND r.code = 'staff'
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO users (department_id, full_name, email, password_hash, role_id, status)
SELECT d.id, 'Sample Employee', 'employee@facilitiesadmin.local',
       '$2b$10$hZKzeKDRqM3tEoDxyubENe//aA0lipM6wk7nfH02mpJtdO.oDIeea', r.id, 'active'
FROM departments d, roles r WHERE d.name = 'General Administration' AND r.code = 'employee'
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
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

INSERT INTO facilities (name, type, location, capacity, status) VALUES
('Conference Room A', 'room', '2nd Floor - Main Building', 12, 'available'),
('Conference Room B', 'room', '2nd Floor - Main Building', 6, 'available'),
('Company Van 1', 'vehicle', 'Motor Pool', 8, 'available'),
('Auditorium', 'venue', 'Ground Floor', 150, 'available')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO equipment_items (name, category, quantity_available, status) VALUES
('Projector Kit', 'AV', 4, 'available'),
('Laptop Cart', 'IT', 2, 'available'),
('Whiteboard Set', 'Office', 6, 'available'),
('Conference Phone', 'AV', 3, 'available')
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
           WHEN r.code = 'staff' THEN 'Facilities Manager'
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
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
    deleted_at DATETIME NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (host_employee_id) REFERENCES employees(id) ON DELETE CASCADE
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

-- Seed visit areas
INSERT INTO visit_areas (name) VALUES 
('Lobby'), ('1st Floor Offices'), ('2nd Floor Conference Rooms'), ('Warehouse'), ('Executive Suite')
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS document_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    checksum VARCHAR(64) NULL,
    content_text LONGTEXT NULL,
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
    FOREIGN KEY (retention_assignment_id) REFERENCES retention_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed retention policies
INSERT INTO retention_policies (name, record_category, retention_period_months, trigger_event, disposal_action, legal_basis) VALUES
('Standard HR Records - 5 Years', 'HR Files', 60, 'creation_date', 'destroy', 'National HR Regulations'),
('Tax Documents - 7 Years', 'Financial Invoices', 84, 'fiscal_year_end', 'review', 'Internal Revenue Service'),
('Active Contracts - 10 Years', 'Contracts', 120, 'contract_end', 'archive', 'Corporate Governance Policy')
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

-- Seed a starter contract template (optional demo content)
INSERT INTO contract_templates (name, contract_type_id, body_text, default_value, is_active, created_by)
SELECT 'Standard NDA Template', ct.id,
       'This Non-Disclosure Agreement ("Agreement") is entered into between the Company and the Counterparty.\n\n1. Confidential Information\n2. Obligations of Receiving Party\n3. Term and Termination\n4. Governing Law',
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

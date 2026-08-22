-- =========================================================
-- FAMS — Facilities & Administrative Management System
-- COMPLETE schema + RBAC seed (Admin / Staff / Employee)
-- Engine: PostgreSQL (generated from database/schema.sql)
--
-- Regenerate after editing schema.sql:
--   php database/convert_schema_to_postgres.php
--
-- Clean reimport:
--   1. dropdb facilities_admin_db && createdb facilities_admin_db
--   2. psql -U postgres -d facilities_admin_db -f database/schema.postgres.sql
--   3. Default logins (password for all: Password123!):
--        admin@facilitiesadmin.local
--        staff@facilitiesadmin.local
--        employee@facilitiesadmin.local
--
-- ===================== CORE / SHARED =====================

CREATE TABLE IF NOT EXISTS departments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    parent_department_id INTEGER NULL,
    FOREIGN KEY (parent_department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS roles (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,        -- admin | staff | employee
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS permissions (
    id SERIAL PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,       -- e.g. reservation.approve, contract.manage
    description VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    department_id INTEGER NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    two_factor_secret TEXT NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    role_id INTEGER NOT NULL,            -- one primary role per user (admin/staff/employee)
    status TEXT DEFAULT 'active',
    photo_path VARCHAR(500) NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email VARCHAR(150) NOT NULL PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    user_id INTEGER NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload TEXT NOT NULL,
    last_activity INT NOT NULL
);

CREATE TABLE IF NOT EXISTS cache (
    "key" VARCHAR(255) NOT NULL PRIMARY KEY,
    value TEXT NOT NULL,
    expiration INT NOT NULL
);

CREATE TABLE IF NOT EXISTS cache_locks (
    "key" VARCHAR(255) NOT NULL PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration INT NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER NULL,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id VARCHAR(50) NOT NULL,
    old_values JSONB NULL,
    new_values JSONB NULL,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    module VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    link VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attachments (
    id BIGSERIAL PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100),
    file_size INTEGER,
    uploaded_by INTEGER NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ===================== SEED: ROLES =====================

INSERT INTO roles (code, name, description) VALUES
('admin',    'Administrator', 'Full access to every module and user management'),
('staff',    'Staff',         'Operational access: process, approve, and manage day-to-day records'),
('employee', 'Employee',      'Self-service access: submit requests and view own/allowed records');

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
('audit.view',           'View system audit log history');

-- ===================== SEED: ROLE-PERMISSION MAPPING =====================

-- ADMIN: gets every permission that exists
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.code = 'admin';

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
);

-- EMPLOYEE: self-service only - submit requests, view what's allowed, no approvals/management
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.code = 'employee' AND p.code IN (
    'reservation.view','reservation.create',
    'visitor.view','visitor.register',
    'document.view',
    'contract.view'
);

-- ===================== SEED: DEPARTMENT + DEMO USERS =====================
-- Demo password for all 3 accounts below is:  Password123!
-- (bcrypt hash generated with PHP password_hash(); change immediately in a real deployment)

INSERT INTO departments (name) VALUES ('General Administration');

INSERT INTO users (department_id, full_name, email, password_hash, role_id, status)
SELECT d.id, 'System Administrator', 'admin@facilitiesadmin.local',
       '$2b$10$hZKzeKDRqM3tEoDxyubENe//aA0lipM6wk7nfH02mpJtdO.oDIeea', r.id, 'active'
FROM departments d, roles r WHERE d.name = 'General Administration' AND r.code = 'admin';

INSERT INTO users (department_id, full_name, email, password_hash, role_id, status)
SELECT d.id, 'Facilities Staff', 'staff@facilitiesadmin.local',
       '$2b$10$hZKzeKDRqM3tEoDxyubENe//aA0lipM6wk7nfH02mpJtdO.oDIeea', r.id, 'active'
FROM departments d, roles r WHERE d.name = 'General Administration' AND r.code = 'staff';

INSERT INTO users (department_id, full_name, email, password_hash, role_id, status)
SELECT d.id, 'Sample Employee', 'employee@facilitiesadmin.local',
       '$2b$10$hZKzeKDRqM3tEoDxyubENe//aA0lipM6wk7nfH02mpJtdO.oDIeea', r.id, 'active'
FROM departments d, roles r WHERE d.name = 'General Administration' AND r.code = 'employee';

-- ===================== MODULE TABLES =====================

CREATE TABLE IF NOT EXISTS facilities (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type TEXT DEFAULT 'room',
    location VARCHAR(255),
    capacity INTEGER NULL,
    status TEXT DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reservations (
    id BIGSERIAL PRIMARY KEY,
    facility_id INTEGER NOT NULL,
    requested_by INTEGER NOT NULL,
    purpose VARCHAR(255),
    start_datetime TIMESTAMP NOT NULL,
    end_datetime TIMESTAMP NOT NULL,
    status TEXT DEFAULT 'pending',
    approved_by INTEGER NULL,
    approved_at TIMESTAMP NULL,
    series_id VARCHAR(36) NULL,
    recurrence_rule TEXT DEFAULT 'none',
    recurrence_until DATE NULL,
    is_maintenance BOOLEAN DEFAULT FALSE,
    reminder_sent_at TIMESTAMP NULL,
    -- Cost / chargeback
    cost_amount DECIMAL(10,2) NULL,
    cost_currency VARCHAR(10) DEFAULT 'PHP',
    chargeback_department_id INTEGER NULL,
    cost_note VARCHAR(255) NULL,
    -- No-show / cancellation
    no_show BOOLEAN DEFAULT FALSE,
    no_show_reported_by INTEGER NULL,
    no_show_reported_at TIMESTAMP NULL,
    cancellation_reason VARCHAR(255) NULL,
    cancelled_by INTEGER NULL,
    cancelled_at TIMESTAMP NULL,
    -- Feedback
    feedback_rating INTEGER NULL,
    feedback_comment TEXT NULL,
    feedback_submitted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (chargeback_department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (no_show_reported_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS equipment_items (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NULL,
    quantity_available INTEGER DEFAULT 1,
    status TEXT DEFAULT 'available',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reservation_equipment (
    reservation_id BIGINT NOT NULL,
    equipment_id INTEGER NOT NULL,
    quantity INTEGER DEFAULT 1,
    PRIMARY KEY (reservation_id, equipment_id),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (equipment_id) REFERENCES equipment_items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reservation_waitlist (
    id BIGSERIAL PRIMARY KEY,
    facility_id INTEGER NOT NULL,
    requested_by INTEGER NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    preferred_start_datetime TIMESTAMP NOT NULL,
    preferred_end_datetime TIMESTAMP NOT NULL,
    status TEXT DEFAULT 'pending',
    offered_reservation_id BIGINT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (offered_reservation_id) REFERENCES reservations(id) ON DELETE SET NULL
);

INSERT INTO facilities (name, type, location, capacity, status) VALUES
('Conference Room A', 'room', '2nd Floor - Main Building', 12, 'available'),
('Conference Room B', 'room', '2nd Floor - Main Building', 6, 'available'),
('Company Van 1', 'vehicle', 'Motor Pool', 8, 'available'),
('Auditorium', 'venue', 'Ground Floor', 150, 'available');

INSERT INTO equipment_items (name, category, quantity_available, status) VALUES
('Projector Kit', 'AV', 4, 'available'),
('Laptop Cart', 'IT', 2, 'available'),
('Whiteboard Set', 'Office', 6, 'available'),
('Conference Phone', 'AV', 3, 'available');

-- ===================== EMPLOYEES =====================
CREATE TABLE IF NOT EXISTS employees (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NULL,
    department_id INTEGER NULL,
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
JOIN roles r ON r.id = u.role_id;

-- ===================== MODULE 2: VISITOR MANAGEMENT =====================
CREATE TABLE IF NOT EXISTS visitors (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    id_number VARCHAR(100) NULL,
    company VARCHAR(150) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(30) NULL,
    photo_path VARCHAR(500) NULL,
    is_watchlisted BOOLEAN DEFAULT FALSE,
    watchlist_reason VARCHAR(255) NULL,
    watchlist_expiry_date DATE NULL,
    watchlist_added_by INTEGER NULL,
    watchlist_added_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (watchlist_added_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS visits (
    id BIGSERIAL PRIMARY KEY,
    visitor_id INTEGER NOT NULL,
    host_employee_id INTEGER NOT NULL,
    purpose VARCHAR(255),
    scheduled_at TIMESTAMP NULL,
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    badge_code VARCHAR(50) NULL,
    status TEXT DEFAULT 'scheduled',
    notes TEXT NULL,
    nda_required BOOLEAN DEFAULT FALSE,
    nda_signed BOOLEAN DEFAULT FALSE,
    nda_signature_path VARCHAR(500) NULL,
    nda_signed_at TIMESTAMP NULL,
    nda_ip_address VARCHAR(45) NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (host_employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS visitor_vehicles (
    id SERIAL PRIMARY KEY,
    visitor_id INTEGER NOT NULL,
    visit_id BIGINT NULL,
    plate_number VARCHAR(50) NOT NULL,
    vehicle_make VARCHAR(100) NULL,
    vehicle_model VARCHAR(100) NULL,
    vehicle_color VARCHAR(50) NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS visit_areas (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL
);

CREATE TABLE IF NOT EXISTS visit_area_access (
    visit_id BIGINT NOT NULL,
    visit_area_id INTEGER NOT NULL,
    PRIMARY KEY (visit_id, visit_area_id),
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (visit_area_id) REFERENCES visit_areas(id) ON DELETE CASCADE
);

-- Seed visit areas
INSERT INTO visit_areas (name) VALUES 
('Lobby'), ('1st Floor Offices'), ('2nd Floor Conference Rooms'), ('Warehouse'), ('Executive Suite');

-- ===================== MODULE 3: DOCUMENT MANAGEMENT =====================
CREATE TABLE IF NOT EXISTS document_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    parent_id INTEGER NULL,
    FOREIGN KEY (parent_id) REFERENCES document_categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS documents (
    id BIGSERIAL PRIMARY KEY,
    category_id INTEGER NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    document_number VARCHAR(100) NULL UNIQUE,
    current_version_id BIGINT NULL,
    owner_id INTEGER NOT NULL,
    confidentiality TEXT DEFAULT 'internal',
    status TEXT DEFAULT 'active',
    review_by_date DATE NULL,
    review_reminder_sent_at TIMESTAMP NULL,
    last_reviewed_at TIMESTAMP NULL,
    last_reviewed_by INTEGER NULL,
    checked_out_by INTEGER NULL,
    checked_out_at TIMESTAMP NULL,
    checked_out_notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (last_reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (checked_out_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS document_versions (
    id BIGSERIAL PRIMARY KEY,
    document_id BIGINT NOT NULL,
    version_no INTEGER NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    checksum VARCHAR(64) NULL,
    content_text TEXT NULL,
    ocr_processed BOOLEAN DEFAULT FALSE,
    ocr_processed_at TIMESTAMP NULL,
    ocr_confidence DECIMAL(5,2) NULL,
    uploaded_by INTEGER NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_notes VARCHAR(255) NULL,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE documents ADD CONSTRAINT fk_documents_current_version 
FOREIGN KEY (current_version_id) REFERENCES document_versions(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS document_tags (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS document_tag_map (
    document_id BIGINT NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (document_id, tag_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES document_tags(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS document_access (
    document_id BIGINT NOT NULL,
    user_id INTEGER NOT NULL DEFAULT 0,   -- 0 = role-based row (no specific user)
    role_id INTEGER NOT NULL DEFAULT 0,   -- 0 = user-based row (no specific role)
    access_level TEXT DEFAULT 'view',
    PRIMARY KEY (document_id, user_id, role_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
    -- Note: user_id/role_id are not FK-constrained so 0 can be used as a sentinel
);

CREATE TABLE IF NOT EXISTS bulk_uploads (
    id BIGSERIAL PRIMARY KEY,
    uploaded_by INTEGER NOT NULL,
    module VARCHAR(50) NOT NULL,
    file_count INTEGER NOT NULL,
    status TEXT DEFAULT 'processing',
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS document_signature_requests (
    id BIGSERIAL PRIMARY KEY,
    document_id BIGINT NOT NULL,
    requested_by INTEGER NOT NULL,
    signer_email VARCHAR(150) NOT NULL,
    signer_name VARCHAR(150) NOT NULL,
    message TEXT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    status TEXT DEFAULT 'pending',
    signed_at TIMESTAMP NULL,
    signature_path VARCHAR(500) NULL,
    signature_ip VARCHAR(45) NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed document categories
INSERT INTO document_categories (name) VALUES 
('Legal Records'), ('Financial Invoices'), ('HR Documents'), ('Standard Operating Procedures'), ('Contracts');

-- ===================== MODULE 4: RECORDS RETENTION =====================
CREATE TABLE IF NOT EXISTS retention_policies (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    record_category VARCHAR(150) NOT NULL,
    retention_period_months INTEGER NOT NULL,
    trigger_event TEXT DEFAULT 'creation_date',
    disposal_action TEXT DEFAULT 'review',
    legal_basis VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS retention_assignments (
    id BIGSERIAL PRIMARY KEY,
    document_id BIGINT NOT NULL,
    policy_id INTEGER NOT NULL,
    retention_start_date DATE NOT NULL,
    calculated_disposal_date DATE NOT NULL,
    status TEXT DEFAULT 'active',
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (policy_id) REFERENCES retention_policies(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_cases (
    id SERIAL PRIMARY KEY,
    case_number VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    case_type TEXT DEFAULT 'other',
    status TEXT DEFAULT 'open',
    filed_date DATE NULL,
    court_or_agency VARCHAR(255) NULL,
    opposing_party VARCHAR(255) NULL,
    assigned_counsel_id INTEGER NULL,
    external_counsel_name VARCHAR(255) NULL,
    risk_level TEXT DEFAULT 'medium',
    budget_amount DECIMAL(14,2) NULL,
    statute_of_limitations_date DATE NULL,
    deadline_escalation_level TEXT DEFAULT 'none',
    deadline_last_notified_at TIMESTAMP NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (assigned_counsel_id) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS legal_holds (
    id SERIAL PRIMARY KEY,
    reason VARCHAR(255) NOT NULL,
    related_case_id INTEGER NULL,
    placed_by INTEGER NOT NULL,
    placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL,
    FOREIGN KEY (placed_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_case_id) REFERENCES legal_cases(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS legal_hold_documents (
    legal_hold_id INTEGER NOT NULL,
    document_id BIGINT NOT NULL,
    PRIMARY KEY (legal_hold_id, document_id),
    FOREIGN KEY (legal_hold_id) REFERENCES legal_holds(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS disposal_logs (
    id BIGSERIAL PRIMARY KEY,
    retention_assignment_id BIGINT NOT NULL,
    action_taken TEXT NOT NULL,
    performed_by INTEGER NOT NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    certificate_path VARCHAR(500) NULL,
    certificate_generated BOOLEAN NOT NULL DEFAULT FALSE,
    certificate_generated_at TIMESTAMP NULL,
    certificate_generated_by INTEGER NULL,
    FOREIGN KEY (retention_assignment_id) REFERENCES retention_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_hold_notifications (
    id BIGSERIAL PRIMARY KEY,
    legal_hold_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notification_type TEXT DEFAULT 'placed',
    read_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS retention_policy_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    regulation_code VARCHAR(50) NOT NULL,
    description TEXT NULL,
    retention_period_months INTEGER NOT NULL,
    trigger_event TEXT DEFAULT 'creation_date',
    disposal_action TEXT DEFAULT 'review',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed retention policies
INSERT INTO retention_policies (name, record_category, retention_period_months, trigger_event, disposal_action, legal_basis) VALUES
('Standard HR Records - 5 Years', 'HR Files', 60, 'creation_date', 'destroy', 'National HR Regulations'),
('Tax Documents - 7 Years', 'Financial Invoices', 84, 'fiscal_year_end', 'review', 'Internal Revenue Service'),
('Active Contracts - 10 Years', 'Contracts', 120, 'contract_end', 'archive', 'Corporate Governance Policy');

INSERT INTO retention_policy_templates (name, regulation_code, description, retention_period_months, trigger_event, disposal_action) VALUES
('BIR Tax Records - 7 Years', 'BIR', 'BIR Revenue Regulations No. 18-2012 - 7 year retention for tax documents', 84, 'fiscal_year_end', 'review'),
('SEC Corporate Records - 10 Years', 'SEC', 'SEC requirements for corporate record retention', 120, 'creation_date', 'archive'),
('Data Privacy Act - 3 Years', 'DPA', 'National Privacy Commission requirements for personal data', 36, 'last_activity', 'destroy'),
('Labor Records - 3 Years', 'DOLE', 'Department of Labor and Employment requirements', 36, 'creation_date', 'review');

-- ===================== MODULE 5: LEGAL MANAGEMENT =====================
CREATE TABLE IF NOT EXISTS legal_case_parties (
    id SERIAL PRIMARY KEY,
    case_id INTEGER NOT NULL,
    party_name VARCHAR(255) NOT NULL,
    role TEXT NOT NULL,
    contact_info VARCHAR(255) NULL,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_case_milestones (
    id BIGSERIAL PRIMARY KEY,
    case_id INTEGER NOT NULL,
    milestone_type TEXT NOT NULL,
    milestone_date TIMESTAMP NOT NULL,
    description VARCHAR(255) NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    reminder_sent_at TIMESTAMP NULL,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_case_documents (
    case_id INTEGER NOT NULL,
    document_id BIGINT NOT NULL,
    PRIMARY KEY (case_id, document_id),
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_case_costs (
    id BIGSERIAL PRIMARY KEY,
    case_id INTEGER NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    incurred_date DATE NOT NULL,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_vendors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    vendor_type TEXT DEFAULT 'counsel',
    contact_name VARCHAR(150) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    firm VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS legal_case_vendors (
    case_id INTEGER NOT NULL,
    vendor_id INTEGER NOT NULL,
    role_note VARCHAR(255) NULL,
    PRIMARY KEY (case_id, vendor_id),
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES legal_vendors(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_case_deadlines (
    id BIGSERIAL PRIMARY KEY,
    case_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    due_date DATE NOT NULL,
    description TEXT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS legal_case_outcomes (
    id BIGSERIAL PRIMARY KEY,
    case_id INTEGER NOT NULL,
    outcome_type TEXT NOT NULL DEFAULT 'other',
    outcome_date DATE NULL,
    description TEXT NULL,
    settlement_amount DECIMAL(14,2) NULL,
    settlement_terms TEXT NULL,
    settlement_date DATE NULL,
    judgment_summary TEXT NULL,
    awarded_amount DECIMAL(14,2) NULL,
    is_appealed BOOLEAN NOT NULL DEFAULT FALSE,
    appeal_reason TEXT NULL,
    appeal_deadline DATE NULL,
    recorded_by INTEGER NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS legal_case_party_conflicts (
    id BIGSERIAL PRIMARY KEY,
    case_id INTEGER NOT NULL,
    party_a_id INTEGER NOT NULL,
    party_b_id INTEGER NOT NULL,
    conflict_type TEXT NOT NULL DEFAULT 'other',
    description TEXT NULL,
    severity TEXT NOT NULL DEFAULT 'medium',
    conflict_flagged BOOLEAN NOT NULL DEFAULT TRUE,
    flagged_by INTEGER NULL,
    flagged_at TIMESTAMP NULL,
    resolution_notes TEXT NULL,
    resolution_status TEXT NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (party_a_id) REFERENCES legal_case_parties(id) ON DELETE CASCADE,
    FOREIGN KEY (party_b_id) REFERENCES legal_case_parties(id) ON DELETE CASCADE,
    FOREIGN KEY (flagged_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT uniq_party_pair UNIQUE (case_id, party_a_id, party_b_id)
);

CREATE TABLE IF NOT EXISTS legal_case_counsel_conflicts (
    id BIGSERIAL PRIMARY KEY,
    case_id INTEGER NOT NULL,
    counsel_id INTEGER NULL,
    external_party VARCHAR(255) NULL,
    conflict_type TEXT NOT NULL DEFAULT 'other',
    description TEXT NULL,
    severity TEXT NOT NULL DEFAULT 'medium',
    conflict_verified BOOLEAN NOT NULL DEFAULT FALSE,
    verified_by INTEGER NULL,
    verified_at TIMESTAMP NULL,
    mitigation_plan TEXT NULL,
    status TEXT NOT NULL DEFAULT 'under_review',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES legal_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (counsel_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ===================== MODULE 6: CONTRACT MANAGEMENT =====================
CREATE TABLE IF NOT EXISTS contract_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL
);

-- Seed contract types
INSERT INTO contract_types (name) VALUES 
('Vendor Agreement'), ('NDA'), ('Lease'), ('Partnership Agreement'), ('Employment Contract');

CREATE TABLE IF NOT EXISTS contract_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contract_type_id INTEGER NULL,
    body_text TEXT NOT NULL,
    default_value DECIMAL(14,2) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INTEGER NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_type_id) REFERENCES contract_types(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS contract_approval_chains (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contract_type_id INTEGER NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_type_id) REFERENCES contract_types(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS contract_approval_chain_steps (
    id SERIAL PRIMARY KEY,
    chain_id INTEGER NOT NULL,
    approver_id INTEGER NOT NULL,
    sequence_order INTEGER NOT NULL,
    FOREIGN KEY (chain_id) REFERENCES contract_approval_chains(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contracts (
    id BIGSERIAL PRIMARY KEY,
    contract_number VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    contract_type_id INTEGER NULL,
    template_id INTEGER NULL,
    counterparty_name VARCHAR(255) NOT NULL,
    counterparty_contact VARCHAR(255) NULL,
    owner_id INTEGER NOT NULL,
    value_amount DECIMAL(14,2) NULL,
    currency VARCHAR(10) DEFAULT 'PHP',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    auto_renew BOOLEAN DEFAULT FALSE,
    renewal_notice_days INTEGER DEFAULT 30,
    status TEXT DEFAULT 'draft',
    related_case_id INTEGER NULL,
    signature_token VARCHAR(64) NULL,
    signed_at TIMESTAMP NULL,
    signed_by_name VARCHAR(150) NULL,
    signed_ip VARCHAR(45) NULL,
    signature_path VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (contract_type_id) REFERENCES contract_types(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_case_id) REFERENCES legal_cases(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS contract_approvals (
    id BIGSERIAL PRIMARY KEY,
    contract_id BIGINT NOT NULL,
    approver_id INTEGER NOT NULL,
    sequence_order INTEGER NOT NULL,
    status TEXT DEFAULT 'pending',
    decided_at TIMESTAMP NULL,
    comments VARCHAR(255) NULL,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_documents (
    contract_id BIGINT NOT NULL,
    document_id BIGINT NOT NULL,
    PRIMARY KEY (contract_id, document_id),
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_amendments (
    id BIGSERIAL PRIMARY KEY,
    contract_id BIGINT NOT NULL,
    amendment_number INTEGER NOT NULL,
    description TEXT NOT NULL,
    effective_date DATE NOT NULL,
    document_id BIGINT NULL,
    created_by INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contract_obligations (
    id BIGSERIAL PRIMARY KEY,
    contract_id BIGINT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    responsible_party VARCHAR(255) NULL,
    due_date DATE NULL,
    is_completed BOOLEAN NOT NULL DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    completed_by INTEGER NULL,
    created_by INTEGER NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS contract_vendor_scores (
    id BIGSERIAL PRIMARY KEY,
    contract_id BIGINT NOT NULL,
    quality_score INTEGER  NOT NULL,
    timeliness_score INTEGER  NOT NULL,
    communication_score INTEGER  NOT NULL,
    value_score INTEGER  NOT NULL,
    overall_score DECIMAL(4,2) NOT NULL,
    notes TEXT NULL,
    scored_at DATE NULL,
    scored_by INTEGER NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (scored_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Seed a starter contract template (optional demo content)
INSERT INTO contract_templates (name, contract_type_id, body_text, default_value, is_active, created_by)
SELECT 'Standard NDA Template', ct.id,
       'This Non-Disclosure Agreement ("Agreement") is entered into between the Company and the Counterparty.\n\n1. Confidential Information\n2. Obligations of Receiving Party\n3. Term and Termination\n4. Governing Law',
       NULL, TRUE, u.id
FROM contract_types ct
CROSS JOIN users u
JOIN roles r ON r.id = u.role_id AND r.code = 'admin'
WHERE ct.name = 'NDA'
LIMIT 1;

-- ===================== QUEUE JOBS =====================
CREATE TABLE IF NOT EXISTS jobs (
    id BIGSERIAL PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts INTEGER  NOT NULL,
    reserved_at INTEGER NULL,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS job_batches (
    id VARCHAR(255) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INT NOT NULL,
    pending_jobs INT NOT NULL,
    failed_jobs INT NOT NULL,
    failed_job_ids TEXT NOT NULL,
    options TEXT NULL,
    cancelled_at INT NULL,
    created_at INT NOT NULL,
    finished_at INT NULL
);

CREATE TABLE IF NOT EXISTS failed_jobs (
    id BIGSERIAL PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

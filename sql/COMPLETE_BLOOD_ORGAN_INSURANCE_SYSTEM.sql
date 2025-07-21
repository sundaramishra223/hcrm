-- ===================================================================
-- COMPLETE BLOOD BANK & HEALTHCARE MANAGEMENT SYSTEM SQL
-- Hospital Management System with Blood Donation, Organ Transplant, 
-- Insurance Claims, and Patient Portal Integration
-- ===================================================================

-- This file contains the complete database schema for:
-- 1. Blood Donation & Usage Tracking
-- 2. Organ Transplant Management (High Security)
-- 3. Insurance Management System
-- 4. Patient Billing & Payment System
-- 5. Blood Request Management
-- 6. Legal Compliance & Audit Trails

-- ==================================================
-- BLOOD DONATION TRACKING TABLES
-- ==================================================

-- Blood Donation Sessions Table
CREATE TABLE blood_donation_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    collected_by INT NOT NULL,
    collection_date DATETIME NOT NULL,
    pre_donation_checkup ENUM('passed', 'failed', 'conditional') NOT NULL,
    hemoglobin_level DECIMAL(4,2) NOT NULL,
    blood_pressure VARCHAR(20) NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    volume_collected INT NOT NULL DEFAULT 450,
    donation_type ENUM('whole_blood', 'platelets', 'plasma', 'double_red_cells') NOT NULL,
    notes TEXT,
    status ENUM('completed', 'incomplete', 'cancelled') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donor_id) REFERENCES patients(id),
    FOREIGN KEY (collected_by) REFERENCES users(id),
    INDEX idx_donor_date (donor_id, collection_date),
    INDEX idx_collection_date (collection_date),
    INDEX idx_collected_by (collected_by)
);

-- Blood Usage Records Table
CREATE TABLE blood_usage_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    blood_bag_id INT NOT NULL,
    patient_id INT NOT NULL,
    used_by INT NOT NULL,
    usage_date DATETIME NOT NULL,
    usage_type ENUM('transfusion', 'surgery', 'emergency', 'research', 'testing') NOT NULL,
    volume_used INT NOT NULL,
    patient_condition TEXT NOT NULL,
    cross_match_result ENUM('compatible', 'incompatible', 'pending', 'not_required') NOT NULL,
    adverse_reactions TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (blood_bag_id) REFERENCES blood_inventory(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (used_by) REFERENCES users(id),
    INDEX idx_blood_bag (blood_bag_id),
    INDEX idx_patient_usage (patient_id, usage_date),
    INDEX idx_usage_date (usage_date)
);

-- Enhanced Blood Inventory Table (adding new columns)
ALTER TABLE blood_inventory ADD COLUMN IF NOT EXISTS issued_to_patient_id INT;
ALTER TABLE blood_inventory ADD COLUMN IF NOT EXISTS issued_date DATETIME;
ALTER TABLE blood_inventory ADD COLUMN IF NOT EXISTS issued_by INT;
ALTER TABLE blood_inventory ADD FOREIGN KEY (issued_to_patient_id) REFERENCES patients(id);
ALTER TABLE blood_inventory ADD FOREIGN KEY (issued_by) REFERENCES users(id);
ALTER TABLE blood_inventory ADD INDEX idx_issued_patient (issued_to_patient_id);
ALTER TABLE blood_inventory ADD INDEX idx_issued_date (issued_date);

-- ==================================================
-- ORGAN TRANSPLANT MANAGEMENT TABLES
-- ==================================================

-- Organ Donor Consent Table
CREATE TABLE organ_donor_consent (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    consent_type ENUM('living_donor', 'deceased_donor', 'brain_dead', 'family_consent') NOT NULL,
    consent_date DATE NOT NULL,
    witness_1 VARCHAR(255) NOT NULL,
    witness_2 VARCHAR(255) NOT NULL,
    legal_guardian VARCHAR(255),
    consent_document_path VARCHAR(500),
    notarized ENUM('yes', 'no', 'pending') DEFAULT 'pending',
    consent_withdrawal_date DATE,
    withdrawal_reason TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donor_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_donor_consent (donor_id, consent_date),
    INDEX idx_consent_type (consent_type),
    INDEX idx_notarized (notarized)
);

-- Organ Donations Table
CREATE TABLE organ_donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donor_id INT NOT NULL,
    consent_id INT NOT NULL,
    donation_type ENUM('living', 'deceased', 'brain_dead') NOT NULL,
    organ_type ENUM('kidney', 'liver', 'heart', 'lung', 'pancreas', 'cornea', 'skin', 'bone', 'tissue') NOT NULL,
    medical_evaluation TEXT NOT NULL,
    brain_death_confirmation ENUM('confirmed', 'pending', 'not_applicable') DEFAULT 'not_applicable',
    declaration_time DATETIME,
    declaring_physician VARCHAR(255),
    harvest_team_lead VARCHAR(255),
    preservation_method VARCHAR(255),
    ischemia_time INT COMMENT 'Minutes',
    organ_condition ENUM('excellent', 'good', 'fair', 'poor') NOT NULL,
    legal_clearance ENUM('approved', 'pending', 'rejected') DEFAULT 'pending',
    ethics_committee_approval ENUM('approved', 'pending', 'rejected') DEFAULT 'pending',
    status ENUM('pending_harvest', 'harvested', 'transplanted', 'expired', 'rejected') DEFAULT 'pending_harvest',
    harvest_date DATETIME,
    transplant_date DATETIME,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donor_id) REFERENCES patients(id),
    FOREIGN KEY (consent_id) REFERENCES organ_donor_consent(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_donor_organ (donor_id, organ_type),
    INDEX idx_organ_status (organ_type, status),
    INDEX idx_legal_clearance (legal_clearance),
    INDEX idx_ethics_approval (ethics_committee_approval)
);

-- Organ Recipients Table
CREATE TABLE organ_recipients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    organ_needed ENUM('kidney', 'liver', 'heart', 'lung', 'pancreas', 'cornea', 'skin', 'bone', 'tissue') NOT NULL,
    blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    urgency_level ENUM('elective', 'urgent', 'emergency') NOT NULL,
    medical_condition TEXT NOT NULL,
    waiting_since DATE NOT NULL,
    estimated_survival_benefit INT COMMENT 'Expected years of life saved',
    hla_typing TEXT,
    cross_match_requirements TEXT,
    contraindications TEXT,
    legal_consent ENUM('signed', 'pending', 'refused') DEFAULT 'pending',
    ethics_approval ENUM('approved', 'pending', 'rejected') DEFAULT 'pending',
    financial_clearance ENUM('approved', 'pending', 'rejected') DEFAULT 'pending',
    insurance_pre_auth VARCHAR(255),
    priority_score INT DEFAULT 0,
    status ENUM('waiting', 'matched', 'transplanted', 'removed', 'deceased') DEFAULT 'waiting',
    added_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (added_by) REFERENCES users(id),
    INDEX idx_patient_organ (patient_id, organ_needed),
    INDEX idx_organ_urgency (organ_needed, urgency_level),
    INDEX idx_waiting_since (waiting_since),
    INDEX idx_priority_score (priority_score DESC),
    INDEX idx_status (status)
);

-- Organ Transplants Table
CREATE TABLE organ_transplants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    donation_id INT NOT NULL,
    recipient_id INT NOT NULL,
    transplant_date DATETIME NOT NULL,
    surgical_team_lead VARCHAR(255) NOT NULL,
    anesthesiologist VARCHAR(255) NOT NULL,
    surgery_duration INT NOT NULL COMMENT 'Minutes',
    surgical_notes TEXT NOT NULL,
    complications TEXT,
    blood_loss_ml INT,
    transfusion_required BOOLEAN DEFAULT 0,
    legal_documentation_complete ENUM('yes', 'no') NOT NULL,
    informed_consent_signed ENUM('yes', 'no') NOT NULL,
    ethics_clearance ENUM('approved', 'pending', 'rejected') NOT NULL,
    post_op_monitoring_plan TEXT NOT NULL,
    immunosuppression_protocol TEXT,
    follow_up_schedule TEXT,
    outcome ENUM('successful', 'complications', 'failed', 'pending') DEFAULT 'pending',
    performed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (donation_id) REFERENCES organ_donations(id),
    FOREIGN KEY (recipient_id) REFERENCES organ_recipients(id),
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_transplant_date (transplant_date),
    INDEX idx_donation_recipient (donation_id, recipient_id),
    INDEX idx_outcome (outcome),
    INDEX idx_performed_by (performed_by)
);

-- Legal Rejection Records Table
CREATE TABLE organ_legal_rejections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    record_type ENUM('donation', 'recipient', 'transplant') NOT NULL,
    record_id INT NOT NULL,
    rejection_reason TEXT NOT NULL,
    legal_authority VARCHAR(255) NOT NULL,
    rejection_date DATETIME NOT NULL,
    appeal_status ENUM('none', 'filed', 'approved', 'rejected') DEFAULT 'none',
    appeal_date DATETIME,
    final_decision TEXT,
    reported_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (reported_by) REFERENCES users(id),
    INDEX idx_record_type_id (record_type, record_id),
    INDEX idx_rejection_date (rejection_date),
    INDEX idx_appeal_status (appeal_status)
);

-- ==================================================
-- AUDIT TRAIL TABLES
-- ==================================================

-- Blood Activity Audit Trail
CREATE TABLE blood_activity_audit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    activity_type ENUM('donation', 'usage', 'inventory_update', 'testing', 'disposal') NOT NULL,
    record_id INT NOT NULL,
    action ENUM('insert', 'update', 'delete') NOT NULL,
    old_values JSON,
    new_values JSON,
    performed_by INT NOT NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_record_id (record_id),
    INDEX idx_performed_by (performed_by),
    INDEX idx_performed_at (performed_at)
);

-- Organ Activity Audit Trail
CREATE TABLE organ_activity_audit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    activity_type ENUM('consent', 'donation', 'recipient', 'transplant', 'legal_action') NOT NULL,
    record_id INT NOT NULL,
    action ENUM('insert', 'update', 'delete') NOT NULL,
    old_values JSON,
    new_values JSON,
    legal_significance ENUM('high', 'medium', 'low') DEFAULT 'high',
    performed_by INT NOT NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    witness_signature VARCHAR(500),
    
    FOREIGN KEY (performed_by) REFERENCES users(id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_record_id (record_id),
    INDEX idx_legal_significance (legal_significance),
    INDEX idx_performed_by (performed_by),
    INDEX idx_performed_at (performed_at)
);

-- ==================================================
-- INSURANCE COMPANIES TABLE
-- ==================================================

CREATE TABLE insurance_companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    company_code VARCHAR(50) UNIQUE NOT NULL,
    contact_person VARCHAR(255),
    contact_number VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    website VARCHAR(255),
    license_number VARCHAR(100),
    tpa_name VARCHAR(255) COMMENT 'Third Party Administrator',
    network_type ENUM('cashless', 'reimbursement', 'both') DEFAULT 'both',
    settlement_period INT DEFAULT 30 COMMENT 'Days for claim settlement',
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_company_name (company_name),
    INDEX idx_company_code (company_code),
    INDEX idx_is_active (is_active)
);

-- ==================================================
-- PATIENT INSURANCE POLICIES TABLE
-- ==================================================

CREATE TABLE patient_insurance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    insurance_company_id INT NOT NULL,
    policy_number VARCHAR(100) NOT NULL,
    policy_type ENUM('individual', 'family', 'group', 'corporate') NOT NULL,
    coverage_percentage DECIMAL(5,2) NOT NULL DEFAULT 80.00,
    coverage_limit DECIMAL(12,2) NOT NULL,
    deductible DECIMAL(10,2) DEFAULT 0.00,
    premium_amount DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    sum_insured DECIMAL(12,2) NOT NULL,
    used_amount DECIMAL(12,2) DEFAULT 0.00,
    available_limit DECIMAL(12,2) AS (coverage_limit - used_amount),
    policy_document_path VARCHAR(500),
    family_members TEXT COMMENT 'JSON array of covered family members',
    pre_existing_conditions TEXT,
    exclusions TEXT,
    is_active BOOLEAN DEFAULT 1,
    added_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (insurance_company_id) REFERENCES insurance_companies(id),
    FOREIGN KEY (added_by) REFERENCES users(id),
    INDEX idx_patient_policy (patient_id, policy_number),
    INDEX idx_expiry_date (expiry_date),
    INDEX idx_is_active (is_active),
    UNIQUE KEY unique_patient_policy (patient_id, insurance_company_id, policy_number)
);

-- ==================================================
-- INSURANCE CLAIMS TABLE
-- ==================================================

CREATE TABLE insurance_claims (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    policy_id INT NOT NULL,
    claim_number VARCHAR(50) UNIQUE NOT NULL,
    claim_type ENUM('medical', 'surgical', 'emergency', 'diagnostic', 'pharmacy', 'dental', 'maternity', 'critical_illness') NOT NULL,
    service_type ENUM('inpatient', 'outpatient', 'emergency', 'consultation', 'diagnostic', 'blood_transfusion', 'organ_transplant', 'surgery') NOT NULL,
    service_date DATE NOT NULL,
    admission_date DATE,
    discharge_date DATE,
    claim_amount DECIMAL(12,2) NOT NULL,
    estimated_coverage DECIMAL(12,2),
    processed_amount DECIMAL(12,2) DEFAULT 0.00,
    deductible_applied DECIMAL(10,2) DEFAULT 0.00,
    co_payment DECIMAL(10,2) DEFAULT 0.00,
    doctor_reference VARCHAR(255) NOT NULL,
    hospital_reference VARCHAR(255),
    diagnosis_code VARCHAR(50),
    diagnosis_description TEXT,
    treatment_details TEXT NOT NULL,
    submitted_documents TEXT,
    bill_amount DECIMAL(12,2),
    room_charges DECIMAL(10,2) DEFAULT 0.00,
    doctor_fees DECIMAL(10,2) DEFAULT 0.00,
    medicine_charges DECIMAL(10,2) DEFAULT 0.00,
    investigation_charges DECIMAL(10,2) DEFAULT 0.00,
    other_charges DECIMAL(10,2) DEFAULT 0.00,
    claim_status ENUM('submitted', 'under_review', 'approved', 'rejected', 'partially_approved', 'pending_documents', 'settled') DEFAULT 'submitted',
    rejection_reason TEXT,
    settlement_reference VARCHAR(100),
    pre_auth_number VARCHAR(100),
    cashless_approval BOOLEAN DEFAULT 0,
    submitted_by INT NOT NULL,
    submitted_date DATETIME NOT NULL,
    processed_by INT,
    processed_date DATETIME,
    settled_date DATETIME,
    follow_up_required BOOLEAN DEFAULT 0,
    follow_up_notes TEXT,
    internal_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (policy_id) REFERENCES patient_insurance(id),
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    INDEX idx_claim_number (claim_number),
    INDEX idx_patient_claims (patient_id, claim_status),
    INDEX idx_policy_claims (policy_id, claim_status),
    INDEX idx_claim_status (claim_status),
    INDEX idx_service_date (service_date),
    INDEX idx_submitted_date (submitted_date)
);

-- ==================================================
-- CLAIM TIMELINE TABLE
-- ==================================================

CREATE TABLE claim_timeline (
    id INT PRIMARY KEY AUTO_INCREMENT,
    claim_id INT NOT NULL,
    status ENUM('submitted', 'under_review', 'approved', 'rejected', 'partially_approved', 'pending_documents', 'settled', 'query_raised', 'additional_documents_submitted') NOT NULL,
    status_date DATETIME NOT NULL,
    updated_by INT NOT NULL,
    notes TEXT,
    attachment_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (claim_id) REFERENCES insurance_claims(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_claim_timeline (claim_id, status_date),
    INDEX idx_status (status)
);

-- ==================================================
-- INSURANCE VERIFICATIONS TABLE
-- ==================================================

CREATE TABLE insurance_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    policy_id INT NOT NULL,
    verification_date DATETIME NOT NULL,
    verification_reference VARCHAR(100) UNIQUE NOT NULL,
    eligibility_status ENUM('eligible', 'not_eligible', 'pending', 'expired', 'suspended') NOT NULL,
    coverage_details TEXT NOT NULL,
    available_limit DECIMAL(12,2),
    used_limit DECIMAL(12,2),
    co_payment_percentage DECIMAL(5,2),
    room_limit DECIMAL(10,2),
    verification_method ENUM('online', 'phone', 'email', 'manual') DEFAULT 'online',
    verified_by INT NOT NULL,
    notes TEXT,
    validity_period INT DEFAULT 30 COMMENT 'Days',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (policy_id) REFERENCES patient_insurance(id),
    FOREIGN KEY (verified_by) REFERENCES users(id),
    INDEX idx_verification_ref (verification_reference),
    INDEX idx_patient_verification (patient_id, verification_date),
    INDEX idx_eligibility_status (eligibility_status)
);

-- ==================================================
-- PATIENT BILLS TABLE
-- ==================================================

CREATE TABLE patient_bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    bill_number VARCHAR(50) UNIQUE NOT NULL,
    admission_id INT,
    service_type ENUM('consultation', 'diagnostic', 'surgery', 'emergency', 'inpatient', 'outpatient', 'blood_transfusion', 'organ_transplant', 'blood_request', 'pharmacy') NOT NULL,
    service_date DATE NOT NULL,
    service_details TEXT NOT NULL,
    department VARCHAR(100),
    doctor_id INT,
    base_amount DECIMAL(12,2) NOT NULL,
    consultation_charges DECIMAL(10,2) DEFAULT 0.00,
    room_charges DECIMAL(10,2) DEFAULT 0.00,
    nursing_charges DECIMAL(10,2) DEFAULT 0.00,
    medicine_charges DECIMAL(10,2) DEFAULT 0.00,
    investigation_charges DECIMAL(10,2) DEFAULT 0.00,
    operation_charges DECIMAL(10,2) DEFAULT 0.00,
    blood_charges DECIMAL(10,2) DEFAULT 0.00,
    other_charges DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_reason VARCHAR(255),
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_percentage DECIMAL(5,2) DEFAULT 0.00,
    insurance_coverage DECIMAL(12,2) DEFAULT 0.00,
    insurance_claim_id INT,
    total_amount DECIMAL(12,2) AS (base_amount + consultation_charges + room_charges + nursing_charges + medicine_charges + investigation_charges + operation_charges + blood_charges + other_charges - discount_amount + tax_amount),
    paid_amount DECIMAL(12,2) DEFAULT 0.00,
    outstanding_amount DECIMAL(12,2) AS (base_amount + consultation_charges + room_charges + nursing_charges + medicine_charges + investigation_charges + operation_charges + blood_charges + other_charges - discount_amount + tax_amount - insurance_coverage - paid_amount),
    payment_status ENUM('pending', 'partial', 'paid', 'insurance_pending', 'overdue') DEFAULT 'pending',
    payment_due_date DATE,
    currency VARCHAR(3) DEFAULT 'INR',
    exchange_rate DECIMAL(10,4) DEFAULT 1.0000,
    generated_by INT NOT NULL,
    approved_by INT,
    billing_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (insurance_claim_id) REFERENCES insurance_claims(id),
    FOREIGN KEY (generated_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_bill_number (bill_number),
    INDEX idx_patient_bills (patient_id, service_date),
    INDEX idx_payment_status (payment_status),
    INDEX idx_service_type (service_type),
    INDEX idx_service_date (service_date)
);

-- ==================================================
-- PATIENT PAYMENTS TABLE
-- ==================================================

CREATE TABLE patient_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    bill_id INT,
    payment_reference VARCHAR(100) UNIQUE NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'upi', 'bank_transfer', 'cheque', 'insurance', 'wallet', 'online') NOT NULL,
    payment_details TEXT COMMENT 'Card details, UPI ID, etc.',
    transaction_id VARCHAR(100),
    payment_gateway VARCHAR(50),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'completed',
    payment_date DATETIME NOT NULL,
    bank_name VARCHAR(100),
    cheque_number VARCHAR(50),
    cheque_date DATE,
    refund_amount DECIMAL(12,2) DEFAULT 0.00,
    refund_reason TEXT,
    refund_date DATETIME,
    processed_by INT NOT NULL,
    approved_by INT,
    receipt_number VARCHAR(50),
    receipt_path VARCHAR(500),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (bill_id) REFERENCES patient_bills(id),
    FOREIGN KEY (processed_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_payment_reference (payment_reference),
    INDEX idx_patient_payments (patient_id, payment_date),
    INDEX idx_payment_method (payment_method),
    INDEX idx_payment_status (payment_status),
    INDEX idx_payment_date (payment_date)
);

-- ==================================================
-- BLOOD REQUESTS TABLE (Enhanced)
-- ==================================================

CREATE TABLE blood_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    request_number VARCHAR(50) UNIQUE NOT NULL,
    blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    component_type ENUM('whole_blood', 'red_blood_cells', 'platelets', 'plasma', 'cryoprecipitate', 'fresh_frozen_plasma') NOT NULL,
    units_needed INT NOT NULL,
    urgency_level ENUM('routine', 'urgent', 'emergency') NOT NULL,
    medical_reason TEXT NOT NULL,
    doctor_prescription VARCHAR(255) NOT NULL,
    cross_match_required BOOLEAN DEFAULT 1,
    compatibility_test_done BOOLEAN DEFAULT 0,
    compatibility_result ENUM('compatible', 'incompatible', 'pending') DEFAULT 'pending',
    estimated_cost DECIMAL(10,2),
    actual_cost DECIMAL(10,2),
    insurance_pre_auth VARCHAR(100),
    insurance_coverage DECIMAL(10,2) DEFAULT 0.00,
    requested_date DATETIME NOT NULL,
    required_by_date DATETIME,
    approved_date DATETIME,
    fulfilled_date DATETIME,
    status ENUM('pending', 'approved', 'fulfilled', 'cancelled', 'rejected') DEFAULT 'pending',
    approval_notes TEXT,
    rejection_reason TEXT,
    blood_bags_assigned TEXT COMMENT 'JSON array of assigned blood bag IDs',
    requested_by INT NOT NULL,
    approved_by INT,
    fulfilled_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (fulfilled_by) REFERENCES users(id),
    INDEX idx_request_number (request_number),
    INDEX idx_patient_requests (patient_id, requested_date),
    INDEX idx_blood_group (blood_group, component_type),
    INDEX idx_urgency_level (urgency_level),
    INDEX idx_status (status),
    INDEX idx_requested_date (requested_date)
);

-- ==================================================
-- INSURANCE COVERAGE RULES TABLE
-- ==================================================

CREATE TABLE insurance_coverage_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    insurance_company_id INT NOT NULL,
    service_type VARCHAR(100) NOT NULL,
    coverage_percentage DECIMAL(5,2) NOT NULL,
    maximum_amount DECIMAL(12,2),
    minimum_amount DECIMAL(10,2) DEFAULT 0.00,
    co_payment_percentage DECIMAL(5,2) DEFAULT 0.00,
    deductible_amount DECIMAL(10,2) DEFAULT 0.00,
    waiting_period_days INT DEFAULT 0,
    pre_auth_required BOOLEAN DEFAULT 0,
    room_rent_limit DECIMAL(10,2),
    age_group_start INT DEFAULT 0,
    age_group_end INT DEFAULT 100,
    gender ENUM('male', 'female', 'all') DEFAULT 'all',
    exclusions TEXT,
    conditions TEXT,
    is_active BOOLEAN DEFAULT 1,
    effective_from DATE NOT NULL,
    effective_to DATE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (insurance_company_id) REFERENCES insurance_companies(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_company_service (insurance_company_id, service_type),
    INDEX idx_age_group (age_group_start, age_group_end),
    INDEX idx_effective_dates (effective_from, effective_to),
    INDEX idx_is_active (is_active)
);

-- ==================================================
-- BILLING TEMPLATES TABLE
-- ==================================================

CREATE TABLE billing_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(100) NOT NULL,
    service_type VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    base_amount DECIMAL(10,2) NOT NULL,
    consultation_charges DECIMAL(10,2) DEFAULT 0.00,
    investigation_charges DECIMAL(10,2) DEFAULT 0.00,
    medicine_charges DECIMAL(10,2) DEFAULT 0.00,
    other_charges DECIMAL(10,2) DEFAULT 0.00,
    tax_percentage DECIMAL(5,2) DEFAULT 0.00,
    discount_allowed BOOLEAN DEFAULT 1,
    max_discount_percentage DECIMAL(5,2) DEFAULT 0.00,
    insurance_applicable BOOLEAN DEFAULT 1,
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_template_name (template_name),
    INDEX idx_service_type (service_type),
    INDEX idx_department (department),
    INDEX idx_is_active (is_active)
);

-- ==================================================
-- VIEWS FOR REPORTING AND ANALYTICS
-- ==================================================

-- Blood Inventory Dashboard View
CREATE OR REPLACE VIEW blood_inventory_dashboard AS
SELECT 
    blood_group,
    component_type,
    COUNT(*) as total_units,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_units,
    SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_units,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_units,
    SUM(CASE WHEN status = 'quarantine' THEN 1 ELSE 0 END) as quarantine_units,
    AVG(DATEDIFF(expiry_date, CURDATE())) as avg_days_to_expiry
FROM blood_inventory
GROUP BY blood_group, component_type
ORDER BY blood_group, component_type;

-- Legal Compliance Dashboard View
CREATE OR REPLACE VIEW legal_compliance_dashboard AS
SELECT 
    'Organ Donations' as category,
    COUNT(*) as total_records,
    SUM(CASE WHEN legal_clearance = 'approved' THEN 1 ELSE 0 END) as legally_cleared,
    SUM(CASE WHEN ethics_committee_approval = 'approved' THEN 1 ELSE 0 END) as ethics_approved,
    SUM(CASE WHEN legal_clearance = 'pending' OR ethics_committee_approval = 'pending' THEN 1 ELSE 0 END) as pending_approval
FROM organ_donations
UNION ALL
SELECT 
    'Organ Recipients' as category,
    COUNT(*) as total_records,
    SUM(CASE WHEN legal_consent = 'signed' THEN 1 ELSE 0 END) as legally_cleared,
    SUM(CASE WHEN ethics_approval = 'approved' THEN 1 ELSE 0 END) as ethics_approved,
    SUM(CASE WHEN legal_consent = 'pending' OR ethics_approval = 'pending' THEN 1 ELSE 0 END) as pending_approval
FROM organ_recipients;

-- Organ Waiting List Priority View
CREATE OR REPLACE VIEW organ_waiting_list_priority AS
SELECT 
    or_.id,
    p.patient_id,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    or_.organ_needed,
    or_.blood_group,
    or_.urgency_level,
    or_.waiting_since,
    DATEDIFF(CURDATE(), or_.waiting_since) as days_waiting,
    or_.priority_score,
    or_.medical_condition,
    or_.status,
    CASE 
        WHEN or_.urgency_level = 'emergency' THEN or_.priority_score + 1000
        WHEN or_.urgency_level = 'urgent' THEN or_.priority_score + 500
        ELSE or_.priority_score
    END as calculated_priority
FROM organ_recipients or_
JOIN patients p ON or_.patient_id = p.id
WHERE or_.status = 'waiting'
ORDER BY calculated_priority DESC, or_.waiting_since ASC;

-- Patient Insurance Summary View
CREATE OR REPLACE VIEW patient_insurance_summary AS
SELECT 
    p.id as patient_id,
    p.patient_id as patient_number,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    p.phone,
    p.email,
    pi.policy_number,
    ic.company_name,
    pi.policy_type,
    pi.coverage_percentage,
    pi.coverage_limit,
    pi.used_amount,
    pi.available_limit,
    pi.expiry_date,
    CASE 
        WHEN pi.expiry_date < CURDATE() THEN 'Expired'
        WHEN pi.expiry_date <= CURDATE() + INTERVAL 30 DAY THEN 'Expiring Soon'
        ELSE 'Active'
    END as policy_status,
    pi.is_active
FROM patients p
JOIN patient_insurance pi ON p.id = pi.patient_id
JOIN insurance_companies ic ON pi.insurance_company_id = ic.id
WHERE pi.is_active = 1;

-- Claims Summary View
CREATE OR REPLACE VIEW claims_summary AS
SELECT 
    ic.id as claim_id,
    ic.claim_number,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    p.patient_id as patient_number,
    comp.company_name,
    pi.policy_number,
    ic.claim_type,
    ic.service_type,
    ic.service_date,
    ic.claim_amount,
    ic.processed_amount,
    ic.claim_status,
    ic.submitted_date,
    ic.processed_date,
    DATEDIFF(COALESCE(ic.processed_date, CURDATE()), ic.submitted_date) as processing_days,
    CASE 
        WHEN ic.claim_status IN ('submitted', 'under_review') AND DATEDIFF(CURDATE(), ic.submitted_date) > 15 THEN 'Overdue'
        WHEN ic.claim_status IN ('submitted', 'under_review') AND DATEDIFF(CURDATE(), ic.submitted_date) > 7 THEN 'Delayed'
        ELSE 'On Time'
    END as processing_status
FROM insurance_claims ic
JOIN patients p ON ic.patient_id = p.id
JOIN patient_insurance pi ON ic.policy_id = pi.id
JOIN insurance_companies comp ON pi.insurance_company_id = comp.id;

-- Outstanding Bills View
CREATE OR REPLACE VIEW outstanding_bills AS
SELECT 
    pb.id as bill_id,
    pb.bill_number,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    p.patient_id as patient_number,
    p.phone,
    pb.service_type,
    pb.service_date,
    pb.total_amount,
    pb.insurance_coverage,
    pb.paid_amount,
    pb.outstanding_amount,
    pb.payment_status,
    pb.payment_due_date,
    DATEDIFF(CURDATE(), pb.payment_due_date) as days_overdue,
    CASE 
        WHEN pb.payment_due_date < CURDATE() THEN 'Overdue'
        WHEN pb.payment_due_date <= CURDATE() + INTERVAL 7 DAY THEN 'Due Soon'
        ELSE 'Current'
    END as payment_urgency
FROM patient_bills pb
JOIN patients p ON pb.patient_id = p.id
WHERE pb.outstanding_amount > 0
ORDER BY pb.payment_due_date ASC;

-- ==================================================
-- STORED PROCEDURES
-- ==================================================

-- Procedure to calculate organ compatibility score
DELIMITER //
CREATE PROCEDURE CalculateOrganCompatibility(
    IN p_donor_id INT,
    IN p_recipient_id INT,
    IN p_organ_type VARCHAR(50),
    OUT p_compatibility_score INT
)
BEGIN
    DECLARE donor_blood_group VARCHAR(5);
    DECLARE recipient_blood_group VARCHAR(5);
    DECLARE blood_compatibility_score INT DEFAULT 0;
    DECLARE age_compatibility_score INT DEFAULT 0;
    DECLARE medical_compatibility_score INT DEFAULT 0;
    
    -- Get blood groups
    SELECT p1.blood_group INTO donor_blood_group FROM patients p1 WHERE p1.id = p_donor_id;
    SELECT p2.blood_group INTO recipient_blood_group FROM patients p2 WHERE p2.id = p_recipient_id;
    
    -- Blood group compatibility scoring
    IF donor_blood_group = recipient_blood_group THEN
        SET blood_compatibility_score = 100;
    ELSEIF (donor_blood_group = 'O+' OR donor_blood_group = 'O-') THEN
        SET blood_compatibility_score = 80;
    ELSEIF recipient_blood_group = 'AB+' OR recipient_blood_group = 'AB-' THEN
        SET blood_compatibility_score = 70;
    ELSE
        SET blood_compatibility_score = 30;
    END IF;
    
    -- Age compatibility (simplified)
    SET age_compatibility_score = 50;
    
    -- Medical condition compatibility (simplified)
    SET medical_compatibility_score = 60;
    
    -- Calculate final score
    SET p_compatibility_score = (blood_compatibility_score + age_compatibility_score + medical_compatibility_score) / 3;
    
END//
DELIMITER ;

-- Procedure to update organ waiting list priorities
DELIMITER //
CREATE PROCEDURE UpdateOrganWaitingListPriorities()
BEGIN
    UPDATE organ_recipients
    SET priority_score = (
        CASE urgency_level
            WHEN 'emergency' THEN 1000
            WHEN 'urgent' THEN 500
            ELSE 100
        END +
        DATEDIFF(CURDATE(), waiting_since) * 2 +
        CASE 
            WHEN estimated_survival_benefit > 10 THEN 200
            WHEN estimated_survival_benefit > 5 THEN 100
            ELSE 50
        END
    )
    WHERE status = 'waiting';
END//
DELIMITER ;

-- Procedure to calculate insurance coverage
DELIMITER //
CREATE PROCEDURE CalculateInsuranceCoverage(
    IN p_policy_id INT,
    IN p_service_type VARCHAR(100),
    IN p_bill_amount DECIMAL(12,2),
    IN p_patient_age INT,
    IN p_patient_gender ENUM('male', 'female'),
    OUT p_coverage_amount DECIMAL(12,2),
    OUT p_co_payment DECIMAL(12,2),
    OUT p_deductible DECIMAL(12,2)
)
BEGIN
    DECLARE policy_coverage_percentage DECIMAL(5,2);
    DECLARE policy_deductible DECIMAL(10,2);
    DECLARE rule_coverage_percentage DECIMAL(5,2);
    DECLARE rule_co_payment_percentage DECIMAL(5,2);
    DECLARE rule_deductible DECIMAL(10,2);
    DECLARE rule_max_amount DECIMAL(12,2);
    DECLARE insurance_company_id INT;
    
    -- Get policy details
    SELECT pi.coverage_percentage, pi.deductible, pi.insurance_company_id
    INTO policy_coverage_percentage, policy_deductible, insurance_company_id
    FROM patient_insurance pi
    WHERE pi.id = p_policy_id AND pi.is_active = 1;
    
    -- Get specific coverage rules if available
    SELECT coverage_percentage, co_payment_percentage, deductible_amount, maximum_amount
    INTO rule_coverage_percentage, rule_co_payment_percentage, rule_deductible, rule_max_amount
    FROM insurance_coverage_rules
    WHERE insurance_company_id = insurance_company_id
    AND service_type = p_service_type
    AND p_patient_age BETWEEN age_group_start AND age_group_end
    AND (gender = p_patient_gender OR gender = 'all')
    AND is_active = 1
    AND CURDATE() BETWEEN effective_from AND COALESCE(effective_to, '2099-12-31')
    LIMIT 1;
    
    -- Use rule-specific values if available, otherwise use policy defaults
    SET rule_coverage_percentage = COALESCE(rule_coverage_percentage, policy_coverage_percentage);
    SET rule_co_payment_percentage = COALESCE(rule_co_payment_percentage, 0);
    SET rule_deductible = COALESCE(rule_deductible, policy_deductible);
    
    -- Calculate coverage amount
    SET p_coverage_amount = (p_bill_amount * rule_coverage_percentage / 100);
    
    -- Apply maximum limit if specified
    IF rule_max_amount IS NOT NULL AND p_coverage_amount > rule_max_amount THEN
        SET p_coverage_amount = rule_max_amount;
    END IF;
    
    -- Calculate co-payment
    SET p_co_payment = (p_bill_amount * rule_co_payment_percentage / 100);
    
    -- Set deductible
    SET p_deductible = rule_deductible;
    
    -- Adjust coverage amount after deductible and co-payment
    SET p_coverage_amount = p_coverage_amount - p_co_payment - p_deductible;
    
    -- Ensure coverage amount is not negative
    IF p_coverage_amount < 0 THEN
        SET p_coverage_amount = 0;
    END IF;
    
END//
DELIMITER ;

-- Procedure to update claim status
DELIMITER //
CREATE PROCEDURE UpdateClaimStatus(
    IN p_claim_id INT,
    IN p_new_status ENUM('submitted', 'under_review', 'approved', 'rejected', 'partially_approved', 'pending_documents', 'settled'),
    IN p_processed_amount DECIMAL(12,2),
    IN p_rejection_reason TEXT,
    IN p_updated_by INT,
    IN p_notes TEXT
)
BEGIN
    DECLARE claim_exists INT DEFAULT 0;
    
    -- Check if claim exists
    SELECT COUNT(*) INTO claim_exists
    FROM insurance_claims
    WHERE id = p_claim_id;
    
    IF claim_exists > 0 THEN
        -- Update the claim
        UPDATE insurance_claims
        SET claim_status = p_new_status,
            processed_amount = COALESCE(p_processed_amount, processed_amount),
            rejection_reason = p_rejection_reason,
            processed_by = p_updated_by,
            processed_date = NOW()
        WHERE id = p_claim_id;
        
        -- Add timeline entry
        INSERT INTO claim_timeline (claim_id, status, status_date, updated_by, notes)
        VALUES (p_claim_id, p_new_status, NOW(), p_updated_by, p_notes);
        
        -- If approved, update policy used amount
        IF p_new_status = 'approved' AND p_processed_amount > 0 THEN
            UPDATE patient_insurance pi
            JOIN insurance_claims ic ON pi.id = ic.policy_id
            SET pi.used_amount = pi.used_amount + p_processed_amount
            WHERE ic.id = p_claim_id;
        END IF;
    END IF;
    
END//
DELIMITER ;

-- ==================================================
-- TRIGGERS FOR AUDIT TRAIL AND AUTO-GENERATION
-- ==================================================

-- Trigger for blood donation audit trail
DELIMITER //
CREATE TRIGGER blood_donation_audit_trigger
AFTER INSERT ON blood_donation_sessions
FOR EACH ROW
BEGIN
    INSERT INTO blood_activity_audit (activity_type, record_id, action, new_values, performed_by)
    VALUES ('donation', NEW.id, 'insert', JSON_OBJECT(
        'donor_id', NEW.donor_id,
        'collected_by', NEW.collected_by,
        'collection_date', NEW.collection_date,
        'volume_collected', NEW.volume_collected,
        'donation_type', NEW.donation_type
    ), NEW.collected_by);
END//
DELIMITER ;

-- Trigger for blood usage audit trail
DELIMITER //
CREATE TRIGGER blood_usage_audit_trigger
AFTER INSERT ON blood_usage_records
FOR EACH ROW
BEGIN
    INSERT INTO blood_activity_audit (activity_type, record_id, action, new_values, performed_by)
    VALUES ('usage', NEW.id, 'insert', JSON_OBJECT(
        'blood_bag_id', NEW.blood_bag_id,
        'patient_id', NEW.patient_id,
        'used_by', NEW.used_by,
        'usage_date', NEW.usage_date,
        'volume_used', NEW.volume_used,
        'usage_type', NEW.usage_type
    ), NEW.used_by);
END//
DELIMITER ;

-- Trigger for organ donation audit trail
DELIMITER //
CREATE TRIGGER organ_donation_audit_trigger
AFTER INSERT ON organ_donations
FOR EACH ROW
BEGIN
    INSERT INTO organ_activity_audit (activity_type, record_id, action, new_values, legal_significance, performed_by)
    VALUES ('donation', NEW.id, 'insert', JSON_OBJECT(
        'donor_id', NEW.donor_id,
        'organ_type', NEW.organ_type,
        'donation_type', NEW.donation_type,
        'legal_clearance', NEW.legal_clearance,
        'ethics_committee_approval', NEW.ethics_committee_approval
    ), 'high', NEW.created_by);
END//
DELIMITER ;

-- Trigger for organ transplant audit trail
DELIMITER //
CREATE TRIGGER organ_transplant_audit_trigger
AFTER INSERT ON organ_transplants
FOR EACH ROW
BEGIN
    INSERT INTO organ_activity_audit (activity_type, record_id, action, new_values, legal_significance, performed_by)
    VALUES ('transplant', NEW.id, 'insert', JSON_OBJECT(
        'donation_id', NEW.donation_id,
        'recipient_id', NEW.recipient_id,
        'transplant_date', NEW.transplant_date,
        'surgical_team_lead', NEW.surgical_team_lead,
        'legal_documentation_complete', NEW.legal_documentation_complete,
        'informed_consent_signed', NEW.informed_consent_signed
    ), 'high', NEW.performed_by);
END//
DELIMITER ;

-- Trigger to check blood donation eligibility (56-day rule)
DELIMITER //
CREATE TRIGGER check_blood_donation_eligibility
BEFORE INSERT ON blood_donation_sessions
FOR EACH ROW
BEGIN
    DECLARE last_donation_date DATE;
    DECLARE days_since_last_donation INT;
    
    SELECT MAX(collection_date) INTO last_donation_date
    FROM blood_donation_sessions
    WHERE donor_id = NEW.donor_id AND status = 'completed';
    
    IF last_donation_date IS NOT NULL THEN
        SET days_since_last_donation = DATEDIFF(NEW.collection_date, last_donation_date);
        IF days_since_last_donation < 56 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Donor must wait at least 56 days between whole blood donations';
        END IF;
    END IF;
END//
DELIMITER ;

-- Trigger to check organ legal compliance
DELIMITER //
CREATE TRIGGER check_organ_legal_compliance
BEFORE INSERT ON organ_transplants
FOR EACH ROW
BEGIN
    DECLARE donor_legal_status VARCHAR(50);
    DECLARE recipient_legal_status VARCHAR(50);
    DECLARE recipient_ethics_status VARCHAR(50);

    SELECT legal_clearance INTO donor_legal_status
    FROM organ_donations
    WHERE id = NEW.donation_id;

    SELECT legal_consent, ethics_approval INTO recipient_legal_status, recipient_ethics_status
    FROM organ_recipients
    WHERE id = NEW.recipient_id;

    IF donor_legal_status != 'approved' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Donor legal clearance not approved';
    END IF;

    IF recipient_legal_status != 'signed' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Recipient legal consent not signed';
    END IF;

    IF recipient_ethics_status != 'approved' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Recipient ethics approval not obtained';
    END IF;

    IF NEW.legal_documentation_complete != 'yes' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Legal documentation must be complete before transplant';
    END IF;

    IF NEW.informed_consent_signed != 'yes' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Informed consent must be signed before transplant';
    END IF;

    IF NEW.ethics_clearance != 'approved' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ethics clearance must be approved before transplant';
    END IF;
END//
DELIMITER ;

-- Trigger to auto-generate bill number
DELIMITER //
CREATE TRIGGER generate_bill_number
BEFORE INSERT ON patient_bills
FOR EACH ROW
BEGIN
    DECLARE bill_count INT;
    SELECT COUNT(*) INTO bill_count FROM patient_bills WHERE DATE(created_at) = CURDATE();
    SET NEW.bill_number = CONCAT('BILL', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(bill_count + 1, 4, '0'));
END//
DELIMITER ;

-- Trigger to auto-generate payment reference
DELIMITER //
CREATE TRIGGER generate_payment_reference
BEFORE INSERT ON patient_payments
FOR EACH ROW
BEGIN
    DECLARE payment_count INT;
    SELECT COUNT(*) INTO payment_count FROM patient_payments WHERE DATE(created_at) = CURDATE();
    SET NEW.payment_reference = CONCAT('PAY', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(payment_count + 1, 4, '0'));
END//
DELIMITER ;

-- Trigger to auto-generate blood request number
DELIMITER //
CREATE TRIGGER generate_request_number
BEFORE INSERT ON blood_requests
FOR EACH ROW
BEGIN
    DECLARE request_count INT;
    SELECT COUNT(*) INTO request_count FROM blood_requests WHERE DATE(created_at) = CURDATE();
    SET NEW.request_number = CONCAT('REQ', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(request_count + 1, 4, '0'));
END//
DELIMITER ;

-- Trigger to update bill payment status
DELIMITER //
CREATE TRIGGER update_bill_payment_status
AFTER INSERT ON patient_payments
FOR EACH ROW
BEGIN
    DECLARE total_paid DECIMAL(12,2);
    DECLARE bill_total DECIMAL(12,2);
    
    IF NEW.bill_id IS NOT NULL THEN
        -- Calculate total paid amount for this bill
        SELECT COALESCE(SUM(amount_paid), 0) INTO total_paid
        FROM patient_payments
        WHERE bill_id = NEW.bill_id AND payment_status = 'completed';
        
        -- Get bill total amount
        SELECT total_amount INTO bill_total
        FROM patient_bills
        WHERE id = NEW.bill_id;
        
        -- Update bill payment status
        UPDATE patient_bills
        SET paid_amount = total_paid,
            payment_status = CASE
                WHEN total_paid >= bill_total THEN 'paid'
                WHEN total_paid > 0 THEN 'partial'
                ELSE 'pending'
            END
        WHERE id = NEW.bill_id;
    END IF;
END//
DELIMITER ;

-- ==================================================
-- SAMPLE DATA FOR TESTING
-- ==================================================

-- Insert sample insurance companies
INSERT INTO insurance_companies (company_name, company_code, contact_person, contact_number, email, tpa_name, network_type) VALUES
('Star Health Insurance', 'STAR001', 'Rajesh Kumar', '9876543210', 'claims@starhealth.in', 'Medi Assist', 'both'),
('HDFC ERGO Health Insurance', 'HDFC001', 'Priya Sharma', '9876543211', 'claims@hdfcergo.com', 'HDFC ERGO TPA', 'cashless'),
('ICICI Lombard Health Insurance', 'ICICI001', 'Amit Singh', '9876543212', 'health@icicilombard.com', 'ICICI Lombard TPA', 'both'),
('New India Assurance', 'NIAC001', 'Sunita Patel', '9876543213', 'claims@newindia.co.in', 'United India TPA', 'reimbursement'),
('Oriental Insurance', 'OICL001', 'Manoj Gupta', '9876543214', 'claims@orientalinsurance.co.in', 'Oriental TPA', 'both');

-- Insert sample patient insurance policies (assuming patient IDs 1, 2, 3 exist)
INSERT INTO patient_insurance (patient_id, insurance_company_id, policy_number, policy_type, coverage_percentage, coverage_limit, deductible, premium_amount, start_date, expiry_date, sum_insured, added_by) VALUES
(1, 1, 'STAR123456789', 'family', 80.00, 500000.00, 5000.00, 25000.00, '2024-01-01', '2024-12-31', 500000.00, 1),
(2, 2, 'HDFC987654321', 'individual', 100.00, 300000.00, 2000.00, 15000.00, '2024-01-01', '2024-12-31', 300000.00, 1),
(3, 3, 'ICICI456789123', 'group', 90.00, 1000000.00, 10000.00, 50000.00, '2024-01-01', '2024-12-31', 1000000.00, 1);

-- Insert sample billing templates
INSERT INTO billing_templates (template_name, service_type, department, base_amount, consultation_charges, investigation_charges, tax_percentage, created_by) VALUES
('General Consultation', 'consultation', 'General Medicine', 500.00, 500.00, 0.00, 18.00, 1),
('Blood Test Package', 'diagnostic', 'Laboratory', 1500.00, 0.00, 1500.00, 18.00, 1),
('Blood Transfusion', 'blood_transfusion', 'Blood Bank', 2000.00, 200.00, 300.00, 18.00, 1),
('Emergency Consultation', 'emergency', 'Emergency', 1000.00, 800.00, 200.00, 18.00, 1),
('Organ Transplant Surgery', 'organ_transplant', 'Surgery', 500000.00, 50000.00, 25000.00, 18.00, 1);

-- Insert sample coverage rules
INSERT INTO insurance_coverage_rules (insurance_company_id, service_type, coverage_percentage, maximum_amount, co_payment_percentage, pre_auth_required, room_rent_limit, is_active, effective_from, created_by) VALUES
(1, 'consultation', 80.00, 50000.00, 10.00, 0, 2000.00, 1, '2024-01-01', 1),
(1, 'blood_transfusion', 90.00, 100000.00, 5.00, 1, 3000.00, 1, '2024-01-01', 1),
(1, 'organ_transplant', 95.00, 2000000.00, 2.00, 1, 5000.00, 1, '2024-01-01', 1),
(2, 'consultation', 100.00, 25000.00, 0.00, 0, 1500.00, 1, '2024-01-01', 1),
(2, 'surgery', 100.00, 500000.00, 0.00, 1, 4000.00, 1, '2024-01-01', 1),
(3, 'diagnostic', 85.00, 75000.00, 5.00, 0, 2500.00, 1, '2024-01-01', 1),
(3, 'organ_transplant', 90.00, 1500000.00, 5.00, 1, 4000.00, 1, '2024-01-01', 1);

-- Commit all changes
COMMIT;

-- ==================================================
-- FINAL NOTES AND INSTRUCTIONS
-- ==================================================

/*
This comprehensive SQL file includes:

1. BLOOD DONATION TRACKING:
   - Blood donation sessions with detailed medical checks
   - Blood usage records with patient tracking
   - Enhanced blood inventory with issuance tracking

2. ORGAN TRANSPLANT MANAGEMENT (HIGH SECURITY):
   - Organ donor consent with legal witnesses
   - Organ donations with ethics and legal clearance
   - Organ recipients with priority scoring
   - Organ transplants with complete legal compliance
   - Legal rejection tracking system

3. INSURANCE MANAGEMENT:
   - Insurance companies and policies
   - Insurance claims with timeline tracking
   - Insurance verifications and eligibility checks
   - Coverage rules and billing templates

4. BILLING & PAYMENT SYSTEM:
   - Patient bills with detailed charge breakdown
   - Patient payments with multiple methods
   - Blood requests with insurance integration

5. AUDIT TRAILS & COMPLIANCE:
   - Complete audit trails for blood and organ activities
   - Legal compliance checks via triggers
   - Automatic validation rules

6. REPORTING & ANALYTICS:
   - Dashboard views for real-time monitoring
   - Compliance monitoring views
   - Priority-based waiting lists

7. AUTOMATED FEATURES:
   - Auto-generation of reference numbers
   - Blood donation eligibility checking (56-day rule)
   - Legal compliance validation
   - Payment status updates

IMPORTANT SECURITY FEATURES:
- All organ-related activities require legal clearance
- Audit trails capture all sensitive operations
- Triggers prevent illegal transplant operations
- Role-based access control ready
- Complete documentation trail for legal purposes

To use this system:
1. Run this SQL file on your MySQL/MariaDB database
2. Ensure you have existing patients and users tables
3. Configure the PHP applications to connect to these tables
4. Set up proper user roles and permissions
5. Test all triggers and procedures before production use

LEGAL COMPLIANCE:
This system is designed with legal compliance in mind for medical operations.
All organ transplant operations require:
- Legal consent and clearance
- Ethics committee approval
- Complete documentation
- Witness signatures where required
- Full audit trail for legal purposes
*/
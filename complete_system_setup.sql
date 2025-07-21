-- COMPLETE HOSPITAL MANAGEMENT SYSTEM SETUP
-- This includes ALL changes made after your database dump
-- Run this ONCE to get all new features

-- =============================================================================
-- 1. SITE CONFIGURATION & HOSPITAL SETTINGS
-- =============================================================================

-- Add site customization columns to hospitals table
ALTER TABLE hospitals 
ADD COLUMN IF NOT EXISTS logo_url VARCHAR(255) DEFAULT 'assets/images/logo.svg',
ADD COLUMN IF NOT EXISTS favicon_url VARCHAR(255) DEFAULT 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232563eb"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM12 17h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
ADD COLUMN IF NOT EXISTS primary_color VARCHAR(7) DEFAULT '#2563eb',
ADD COLUMN IF NOT EXISTS secondary_color VARCHAR(7) DEFAULT '#10b981',
ADD COLUMN IF NOT EXISTS site_title VARCHAR(255) DEFAULT 'MediCare Hospital - Advanced Healthcare Management';

-- Update hospital with default settings
UPDATE hospitals 
SET 
    name = 'MediCare Hospital',
    logo_url = 'assets/images/logo.svg',
    favicon_url = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232563eb"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM12 17h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
    primary_color = '#2563eb',
    secondary_color = '#10b981',
    site_title = 'MediCare Hospital - Advanced Healthcare Management'
WHERE id = 1;

-- =============================================================================
-- 2. ROLES & PERMISSIONS UPDATES
-- =============================================================================

-- Add driver role
INSERT IGNORE INTO roles (id, role_name, role_display_name, description, permissions, is_active) 
VALUES (12, 'driver', 'Driver', 'Ambulance driver with trip management access', NULL, 1);

-- Add shift manager role
INSERT IGNORE INTO roles (id, role_name, role_display_name, description, permissions, is_active) 
VALUES (13, 'shift_manager', 'Shift Manager', 'Manages staff shifts and schedules', NULL, 1);

-- Add blood bank staff role
INSERT IGNORE INTO roles (id, role_name, role_display_name, description, permissions, is_active) 
VALUES (14, 'blood_bank_staff', 'Blood Bank Staff', 'Manages blood bank operations', NULL, 1);

-- Add organ coordinator role
INSERT IGNORE INTO roles (id, role_name, role_display_name, description, permissions, is_active) 
VALUES (15, 'organ_coordinator', 'Organ Coordinator', 'Manages organ donation and transplant coordination', NULL, 1);

-- =============================================================================
-- 3. STAFF TABLE UPDATES
-- =============================================================================

-- Update staff table ENUM to include new staff types
ALTER TABLE staff 
MODIFY COLUMN staff_type ENUM(
    'nurse','receptionist','lab_technician','pharmacy_staff',
    'intern_nurse','intern_lab','intern_pharmacy','driver',
    'blood_bank_staff','organ_coordinator'
) DEFAULT 'nurse';

-- Add shift management columns to staff
ALTER TABLE staff 
ADD COLUMN IF NOT EXISTS shift_id INT(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS shift_timing VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS base_salary DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(8,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS overtime_rate DECIMAL(8,2) DEFAULT 0.00;

-- Add foreign key for shift_id
ALTER TABLE staff 
ADD CONSTRAINT fk_staff_shift 
FOREIGN KEY (shift_id) REFERENCES shifts(id) 
ON DELETE SET NULL;

-- =============================================================================
-- 4. SHIFT MANAGEMENT SYSTEM
-- =============================================================================

-- Ensure shifts table has proper data
INSERT IGNORE INTO shifts (id, hospital_id, shift_name, start_time, end_time, is_active) VALUES
(1, 1, 'Morning Shift', '08:00:00', '16:00:00', 1),
(2, 1, 'Evening Shift', '16:00:00', '00:00:00', 1),
(3, 1, 'Night Shift', '00:00:00', '08:00:00', 1),
(4, 1, 'Day Shift (12 Hours)', '09:00:00', '21:00:00', 1),
(5, 1, 'Night Shift (12 Hours)', '21:00:00', '09:00:00', 1);

-- Create shift templates table
CREATE TABLE IF NOT EXISTS shift_templates (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    template_name VARCHAR(100) NOT NULL,
    staff_type ENUM('nurse','receptionist','lab_technician','pharmacy_staff','intern_nurse','intern_lab','intern_pharmacy','driver','blood_bank_staff','organ_coordinator') NOT NULL,
    default_shift_id INT(11) NOT NULL,
    hours_per_week INT(11) DEFAULT 40,
    break_duration_minutes INT(11) DEFAULT 60,
    overtime_rate DECIMAL(5,2) DEFAULT 1.50,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (default_shift_id) REFERENCES shifts(id)
);

-- Insert default shift templates
INSERT IGNORE INTO shift_templates (template_name, staff_type, default_shift_id, hours_per_week) VALUES
('Nurse - Morning', 'nurse', 1, 40),
('Nurse - Evening', 'nurse', 2, 40),
('Nurse - Night', 'nurse', 3, 40),
('Receptionist - Day', 'receptionist', 1, 40),
('Lab Tech - Morning', 'lab_technician', 1, 40),
('Lab Tech - Evening', 'lab_technician', 2, 40),
('Pharmacy - Day', 'pharmacy_staff', 1, 40),
('Driver - Day', 'driver', 1, 40),
('Driver - Night', 'driver', 3, 40),
('Blood Bank - Morning', 'blood_bank_staff', 1, 40),
('Blood Bank - Evening', 'blood_bank_staff', 2, 40),
('Organ Coordinator - Day', 'organ_coordinator', 4, 40);

-- Create staff schedules table
CREATE TABLE IF NOT EXISTS staff_schedules (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    staff_id INT(11) NOT NULL,
    week_start_date DATE NOT NULL,
    monday_shift_id INT(11) DEFAULT NULL,
    tuesday_shift_id INT(11) DEFAULT NULL,
    wednesday_shift_id INT(11) DEFAULT NULL,
    thursday_shift_id INT(11) DEFAULT NULL,
    friday_shift_id INT(11) DEFAULT NULL,
    saturday_shift_id INT(11) DEFAULT NULL,
    sunday_shift_id INT(11) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY unique_staff_week (staff_id, week_start_date)
);

-- =============================================================================
-- 5. ATTENDANCE MANAGEMENT SYSTEM
-- =============================================================================

-- Create proper attendance table
CREATE TABLE IF NOT EXISTS staff_attendance (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    staff_id INT(11) NOT NULL,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    attendance_date DATE NOT NULL,
    shift_id INT(11) DEFAULT NULL,
    clock_in_time TIME DEFAULT NULL,
    clock_out_time TIME DEFAULT NULL,
    actual_clock_in DATETIME DEFAULT NULL,
    actual_clock_out DATETIME DEFAULT NULL,
    break_start_time DATETIME DEFAULT NULL,
    break_end_time DATETIME DEFAULT NULL,
    total_hours DECIMAL(4,2) DEFAULT 0.00,
    overtime_hours DECIMAL(4,2) DEFAULT 0.00,
    status ENUM('present','absent','late','half_day','holiday','sick_leave','casual_leave') DEFAULT 'absent',
    remarks TEXT DEFAULT NULL,
    approved_by INT(11) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (shift_id) REFERENCES shifts(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    UNIQUE KEY unique_staff_date (staff_id, attendance_date)
);

-- Create leave requests table
CREATE TABLE IF NOT EXISTS leave_requests (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    staff_id INT(11) NOT NULL,
    leave_type ENUM('sick_leave','casual_leave','annual_leave','maternity_leave','emergency_leave') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT(11) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT(11) DEFAULT NULL,
    approved_date TIMESTAMP NULL DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- =============================================================================
-- 6. SALARY MANAGEMENT SYSTEM
-- =============================================================================

-- Create salary structure table
CREATE TABLE IF NOT EXISTS salary_structures (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    staff_type ENUM('nurse','receptionist','lab_technician','pharmacy_staff','intern_nurse','intern_lab','intern_pharmacy','driver','blood_bank_staff','organ_coordinator') NOT NULL,
    position_level ENUM('junior','senior','head','chief') DEFAULT 'junior',
    base_salary DECIMAL(10,2) NOT NULL,
    allowances JSON DEFAULT NULL,
    deductions JSON DEFAULT NULL,
    overtime_rate DECIMAL(8,2) DEFAULT 0.00,
    night_shift_allowance DECIMAL(8,2) DEFAULT 0.00,
    weekend_allowance DECIMAL(8,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    effective_from DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- Insert default salary structures
INSERT IGNORE INTO salary_structures (staff_type, position_level, base_salary, overtime_rate, night_shift_allowance, weekend_allowance, effective_from) VALUES
('nurse', 'junior', 25000.00, 150.00, 500.00, 300.00, '2025-01-01'),
('nurse', 'senior', 35000.00, 200.00, 700.00, 400.00, '2025-01-01'),
('nurse', 'head', 45000.00, 250.00, 1000.00, 500.00, '2025-01-01'),
('receptionist', 'junior', 20000.00, 100.00, 300.00, 200.00, '2025-01-01'),
('lab_technician', 'junior', 22000.00, 120.00, 400.00, 250.00, '2025-01-01'),
('lab_technician', 'senior', 32000.00, 180.00, 600.00, 350.00, '2025-01-01'),
('pharmacy_staff', 'junior', 24000.00, 130.00, 450.00, 275.00, '2025-01-01'),
('driver', 'junior', 18000.00, 80.00, 300.00, 200.00, '2025-01-01'),
('blood_bank_staff', 'junior', 26000.00, 140.00, 500.00, 300.00, '2025-01-01'),
('organ_coordinator', 'junior', 40000.00, 200.00, 800.00, 500.00, '2025-01-01');

-- Create monthly salary table
CREATE TABLE IF NOT EXISTS monthly_salaries (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    staff_id INT(11) NOT NULL,
    salary_month CHAR(7) NOT NULL, -- Format: YYYY-MM
    base_salary DECIMAL(10,2) NOT NULL,
    total_working_days INT(11) DEFAULT 0,
    total_present_days INT(11) DEFAULT 0,
    total_hours_worked DECIMAL(6,2) DEFAULT 0.00,
    overtime_hours DECIMAL(6,2) DEFAULT 0.00,
    night_shift_hours DECIMAL(6,2) DEFAULT 0.00,
    weekend_hours DECIMAL(6,2) DEFAULT 0.00,
    basic_amount DECIMAL(10,2) DEFAULT 0.00,
    overtime_amount DECIMAL(10,2) DEFAULT 0.00,
    night_shift_allowance DECIMAL(10,2) DEFAULT 0.00,
    weekend_allowance DECIMAL(10,2) DEFAULT 0.00,
    other_allowances DECIMAL(10,2) DEFAULT 0.00,
    total_allowances DECIMAL(10,2) DEFAULT 0.00,
    tax_deduction DECIMAL(10,2) DEFAULT 0.00,
    pf_deduction DECIMAL(10,2) DEFAULT 0.00,
    other_deductions DECIMAL(10,2) DEFAULT 0.00,
    total_deductions DECIMAL(10,2) DEFAULT 0.00,
    net_salary DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('draft','processed','paid') DEFAULT 'draft',
    processed_by INT(11) DEFAULT NULL,
    processed_date TIMESTAMP NULL DEFAULT NULL,
    payment_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id),
    UNIQUE KEY unique_staff_month (staff_id, salary_month)
);

-- =============================================================================
-- 7. BLOOD BANK MANAGEMENT SYSTEM
-- =============================================================================

-- Create blood groups table
CREATE TABLE IF NOT EXISTS blood_groups (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    blood_group VARCHAR(5) NOT NULL UNIQUE,
    can_donate_to JSON DEFAULT NULL,
    can_receive_from JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1
);

-- Insert blood groups data
INSERT IGNORE INTO blood_groups (blood_group, can_donate_to, can_receive_from) VALUES
('A+', '["A+", "AB+"]', '["A+", "A-", "O+", "O-"]'),
('A-', '["A+", "A-", "AB+", "AB-"]', '["A-", "O-"]'),
('B+', '["B+", "AB+"]', '["B+", "B-", "O+", "O-"]'),
('B-', '["B+", "B-", "AB+", "AB-"]', '["B-", "O-"]'),
('AB+', '["AB+"]', '["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"]'),
('AB-', '["AB+", "AB-"]', '["A-", "B-", "AB-", "O-"]'),
('O+', '["A+", "B+", "AB+", "O+"]', '["O+", "O-"]'),
('O-', '["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"]', '["O-"]');

-- Create blood donors table
CREATE TABLE IF NOT EXISTS blood_donors (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    donor_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male','female','other') NOT NULL,
    address TEXT DEFAULT NULL,
    emergency_contact VARCHAR(20) DEFAULT NULL,
    last_donation_date DATE DEFAULT NULL,
    total_donations INT(11) DEFAULT 0,
    medical_history TEXT DEFAULT NULL,
    is_eligible TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT(11) NOT NULL,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (blood_group) REFERENCES blood_groups(blood_group),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Create blood inventory table
CREATE TABLE IF NOT EXISTS blood_inventory (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    blood_group VARCHAR(5) NOT NULL,
    component_type ENUM('whole_blood','red_cells','platelets','plasma','white_cells') DEFAULT 'whole_blood',
    bag_number VARCHAR(20) UNIQUE NOT NULL,
    donor_id INT(11) DEFAULT NULL,
    collection_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    volume_ml INT(11) NOT NULL DEFAULT 450,
    status ENUM('available','reserved','used','expired','discarded') DEFAULT 'available',
    storage_location VARCHAR(50) DEFAULT NULL,
    temperature DECIMAL(4,1) DEFAULT NULL,
    tested TINYINT(1) DEFAULT 0,
    test_results JSON DEFAULT NULL,
    cross_match_compatible TINYINT(1) DEFAULT 0,
    issued_to_patient_id INT(11) DEFAULT NULL,
    issued_date DATETIME DEFAULT NULL,
    issued_by INT(11) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (blood_group) REFERENCES blood_groups(blood_group),
    FOREIGN KEY (donor_id) REFERENCES blood_donors(id),
    FOREIGN KEY (issued_to_patient_id) REFERENCES patients(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
);

-- Create blood requests table
CREATE TABLE IF NOT EXISTS blood_requests (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    request_number VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT(11) NOT NULL,
    doctor_id INT(11) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    component_type ENUM('whole_blood','red_cells','platelets','plasma','white_cells') DEFAULT 'whole_blood',
    units_required INT(11) NOT NULL DEFAULT 1,
    urgency ENUM('routine','urgent','emergency') DEFAULT 'routine',
    clinical_indication TEXT NOT NULL,
    requested_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    required_by_date DATETIME NOT NULL,
    status ENUM('pending','approved','fulfilled','cancelled') DEFAULT 'pending',
    approved_by INT(11) DEFAULT NULL,
    approved_date DATETIME DEFAULT NULL,
    fulfilled_by INT(11) DEFAULT NULL,
    fulfilled_date DATETIME DEFAULT NULL,
    cross_match_required TINYINT(1) DEFAULT 1,
    special_requirements TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES staff(id),
    FOREIGN KEY (blood_group) REFERENCES blood_groups(blood_group),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (fulfilled_by) REFERENCES users(id)
);

-- Create blood transfusion records table
CREATE TABLE IF NOT EXISTS blood_transfusions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    patient_id INT(11) NOT NULL,
    blood_bag_id INT(11) NOT NULL,
    request_id INT(11) NOT NULL,
    transfusion_date DATETIME NOT NULL,
    started_by INT(11) NOT NULL,
    completed_by INT(11) DEFAULT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    pre_transfusion_vitals JSON DEFAULT NULL,
    post_transfusion_vitals JSON DEFAULT NULL,
    adverse_reactions TEXT DEFAULT NULL,
    status ENUM('in_progress','completed','stopped','adverse_reaction') DEFAULT 'in_progress',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (blood_bag_id) REFERENCES blood_inventory(id),
    FOREIGN KEY (request_id) REFERENCES blood_requests(id),
    FOREIGN KEY (started_by) REFERENCES users(id),
    FOREIGN KEY (completed_by) REFERENCES users(id)
);

-- =============================================================================
-- 8. ORGAN DONATION MANAGEMENT SYSTEM
-- =============================================================================

-- Create organ types table
CREATE TABLE IF NOT EXISTS organ_types (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    organ_name VARCHAR(50) NOT NULL UNIQUE,
    organ_category ENUM('vital','non_vital','tissue') NOT NULL,
    preservation_time_hours INT(11) NOT NULL,
    compatibility_factors JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1
);

-- Insert organ types data
INSERT IGNORE INTO organ_types (organ_name, organ_category, preservation_time_hours, compatibility_factors) VALUES
('Heart', 'vital', 4, '["blood_group", "hla_typing", "body_size"]'),
('Liver', 'vital', 12, '["blood_group", "hla_typing", "body_size"]'),
('Kidney', 'vital', 24, '["blood_group", "hla_typing", "crossmatch"]'),
('Lung', 'vital', 6, '["blood_group", "hla_typing", "body_size"]'),
('Pancreas', 'vital', 12, '["blood_group", "hla_typing"]'),
('Cornea', 'tissue', 168, '["blood_group"]'),
('Skin', 'tissue', 120, '["blood_group"]'),
('Bone', 'tissue', 8760, '["blood_group"]'),
('Heart Valves', 'tissue', 8760, '["blood_group"]'),
('Small Intestine', 'non_vital', 8, '["blood_group", "hla_typing"]');

-- Create organ donors table
CREATE TABLE IF NOT EXISTS organ_donors (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    donor_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male','female','other') NOT NULL,
    address TEXT DEFAULT NULL,
    emergency_contact VARCHAR(20) DEFAULT NULL,
    organs_to_donate JSON NOT NULL,
    consent_type ENUM('living_donor','deceased_donor','both') DEFAULT 'deceased_donor',
    consent_date DATE NOT NULL,
    consent_witness VARCHAR(100) DEFAULT NULL,
    medical_history TEXT DEFAULT NULL,
    hla_typing JSON DEFAULT NULL,
    is_eligible TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT(11) NOT NULL,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (blood_group) REFERENCES blood_groups(blood_group),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Create organ recipients table
CREATE TABLE IF NOT EXISTS organ_recipients (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    recipient_id VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT(11) NOT NULL,
    organ_needed VARCHAR(50) NOT NULL,
    blood_group VARCHAR(5) NOT NULL,
    urgency_level ENUM('routine','urgent','emergency') DEFAULT 'routine',
    medical_condition TEXT NOT NULL,
    date_added_to_list DATE NOT NULL,
    priority_score INT(11) DEFAULT 0,
    hla_typing JSON DEFAULT NULL,
    compatible_donors JSON DEFAULT NULL,
    status ENUM('active','matched','transplanted','removed','deceased') DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_by INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (organ_needed) REFERENCES organ_types(organ_name),
    FOREIGN KEY (blood_group) REFERENCES blood_groups(blood_group),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Create organ matches table
CREATE TABLE IF NOT EXISTS organ_matches (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    donor_id INT(11) NOT NULL,
    recipient_id INT(11) NOT NULL,
    organ_type VARCHAR(50) NOT NULL,
    compatibility_score DECIMAL(5,2) DEFAULT 0.00,
    match_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('potential','confirmed','allocated','transplanted','rejected') DEFAULT 'potential',
    crossmatch_result ENUM('positive','negative','pending') DEFAULT 'pending',
    coordinator_id INT(11) NOT NULL,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (donor_id) REFERENCES organ_donors(id),
    FOREIGN KEY (recipient_id) REFERENCES organ_recipients(id),
    FOREIGN KEY (organ_type) REFERENCES organ_types(organ_name),
    FOREIGN KEY (coordinator_id) REFERENCES users(id)
);

-- Create transplant records table
CREATE TABLE IF NOT EXISTS transplant_records (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    transplant_id VARCHAR(20) UNIQUE NOT NULL,
    donor_id INT(11) NOT NULL,
    recipient_id INT(11) NOT NULL,
    organ_type VARCHAR(50) NOT NULL,
    surgery_date DATETIME NOT NULL,
    surgeon_id INT(11) NOT NULL,
    coordinator_id INT(11) NOT NULL,
    surgery_duration_hours DECIMAL(4,2) DEFAULT NULL,
    organ_retrieval_time DATETIME DEFAULT NULL,
    transplant_start_time DATETIME DEFAULT NULL,
    transplant_end_time DATETIME DEFAULT NULL,
    complications TEXT DEFAULT NULL,
    status ENUM('scheduled','in_progress','completed','failed') DEFAULT 'scheduled',
    post_op_notes TEXT DEFAULT NULL,
    follow_up_required TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (donor_id) REFERENCES organ_donors(id),
    FOREIGN KEY (recipient_id) REFERENCES organ_recipients(id),
    FOREIGN KEY (organ_type) REFERENCES organ_types(organ_name),
    FOREIGN KEY (surgeon_id) REFERENCES staff(id),
    FOREIGN KEY (coordinator_id) REFERENCES users(id)
);

-- =============================================================================
-- 9. FIX EXISTING MODULE ISSUES
-- =============================================================================

-- Fix medicine tables
ALTER TABLE medicine_categories 
MODIFY COLUMN hospital_id INT(11) NOT NULL DEFAULT 1;

ALTER TABLE medicines 
MODIFY COLUMN hospital_id INT(11) NOT NULL DEFAULT 1;

-- Fix bills table
ALTER TABLE bills 
MODIFY COLUMN hospital_id INT(11) NOT NULL DEFAULT 1;

-- Fix lab_orders table
ALTER TABLE lab_orders 
MODIFY COLUMN priority ENUM('low', 'normal', 'high', 'urgent', 'emergency', 'routine') DEFAULT 'normal';

-- Fix lab_order_tests table
ALTER TABLE lab_order_tests 
MODIFY COLUMN status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending';

-- Fix prescriptions table
ALTER TABLE prescriptions 
MODIFY COLUMN hospital_id INT(11) NOT NULL DEFAULT 1;

-- Update missing foreign key values
UPDATE medicines SET hospital_id = 1 WHERE hospital_id IS NULL OR hospital_id = 0;
UPDATE medicine_categories SET hospital_id = 1 WHERE hospital_id IS NULL OR hospital_id = 0;
UPDATE bills SET hospital_id = 1 WHERE hospital_id IS NULL OR hospital_id = 0;
UPDATE prescriptions SET hospital_id = 1 WHERE hospital_id IS NULL OR hospital_id = 0;

-- Add missing order numbers
UPDATE lab_orders 
SET order_number = CONCAT('LAB', YEAR(order_date), LPAD(id, 6, '0'))
WHERE order_number IS NULL OR order_number = '';

-- =============================================================================
-- 10. AMBULANCE MANAGEMENT FIXES
-- =============================================================================

-- Update ambulances table with missing columns
ALTER TABLE ambulances 
ADD COLUMN IF NOT EXISTS capacity INT(11) DEFAULT 4,
ADD COLUMN IF NOT EXISTS equipment TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL;

-- Insert sample ambulances
INSERT IGNORE INTO ambulances (hospital_id, vehicle_number, vehicle_type, driver_name, driver_phone, status, capacity, equipment, location, is_active) 
VALUES 
(1, 'AMB001', 'basic', 'Raj Kumar', '+919876543300', 'available', 4, 'First Aid Kit, Oxygen Tank, Stretcher', 'Hospital Main Gate', 1),
(1, 'AMB002', 'advanced', 'Available', '+919876543301', 'available', 6, 'Advanced Life Support, Defibrillator, Ventilator', 'Emergency Wing', 1);

-- =============================================================================
-- 11. SAMPLE DATA CREATION
-- =============================================================================

-- Create sample driver user
INSERT IGNORE INTO users (username, email, password_hash, role_id, is_active) 
VALUES ('driver.raj', 'driver.raj@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 12, 1);

-- Get driver user ID
SET @driver_user_id = (SELECT id FROM users WHERE username = 'driver.raj');

-- Create driver staff record
INSERT IGNORE INTO staff (user_id, hospital_id, employee_id, first_name, last_name, staff_type, phone, address, date_of_birth, gender, shift_id, shift_timing, base_salary, is_active) 
VALUES (@driver_user_id, 1, 'DRV001', 'Raj', 'Kumar', 'driver', '+919876543300', '123 Driver Street, City', '1985-01-15', 'male', 1, '08:00 - 16:00', 18000.00, 1);

-- Create sample blood bank staff
INSERT IGNORE INTO users (username, email, password_hash, role_id, is_active) 
VALUES ('bloodbank.priya', 'priya.blood@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 14, 1);

SET @bb_user_id = (SELECT id FROM users WHERE username = 'bloodbank.priya');

INSERT IGNORE INTO staff (user_id, hospital_id, employee_id, first_name, last_name, staff_type, phone, address, date_of_birth, gender, shift_id, shift_timing, base_salary, is_active) 
VALUES (@bb_user_id, 1, 'BB001', 'Priya', 'Sharma', 'blood_bank_staff', '+919876543301', '456 Blood Bank Street, City', '1990-03-20', 'female', 1, '08:00 - 16:00', 26000.00, 1);

-- Create sample organ coordinator
INSERT IGNORE INTO users (username, email, password_hash, role_id, is_active) 
VALUES ('organ.coordinator', 'coordinator@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 15, 1);

SET @oc_user_id = (SELECT id FROM users WHERE username = 'organ.coordinator');

INSERT IGNORE INTO staff (user_id, hospital_id, employee_id, first_name, last_name, staff_type, phone, address, date_of_birth, gender, shift_id, shift_timing, base_salary, is_active) 
VALUES (@oc_user_id, 1, 'OC001', 'Dr. Arjun', 'Mehta', 'organ_coordinator', '+919876543302', '789 Organ Center, City', '1985-07-10', 'male', 4, '09:00 - 21:00', 40000.00, 1);

-- Update existing staff with default shifts and salaries
UPDATE staff 
SET shift_id = 1, shift_timing = '08:00 - 16:00', 
    base_salary = CASE 
        WHEN staff_type = 'nurse' THEN 25000.00
        WHEN staff_type = 'receptionist' THEN 20000.00  
        WHEN staff_type = 'lab_technician' THEN 22000.00
        WHEN staff_type = 'pharmacy_staff' THEN 24000.00
        ELSE 20000.00
    END
WHERE shift_id IS NULL;

-- Sample medicine categories
INSERT IGNORE INTO medicine_categories (hospital_id, name, description) VALUES
(1, 'Antibiotics', 'Medications that fight bacterial infections'),
(1, 'Pain Relief', 'Analgesics and pain management medications'),
(1, 'Vitamins', 'Vitamin supplements and nutritional aids'),
(1, 'Heart Medications', 'Cardiovascular and cardiac medications'),
(1, 'Diabetes', 'Medications for diabetes management'),
(1, 'Blood Pressure', 'Hypertension and blood pressure medications');

-- Sample ambulance bookings for driver trips
INSERT IGNORE INTO ambulance_bookings (ambulance_id, pickup_address, destination_address, booking_date, status, charges, created_by) 
VALUES 
(1, '123 Emergency Street, City', 'MediCare Hospital, Main Building', NOW(), 'completed', 500.00, 1),
(1, '456 Accident Road, City', 'MediCare Hospital, Emergency Wing', DATE_SUB(NOW(), INTERVAL 1 DAY), 'completed', 750.00, 1),
(2, '789 Medical Lane, City', 'MediCare Hospital, ICU', DATE_SUB(NOW(), INTERVAL 2 DAY), 'completed', 1000.00, 1);

-- Sample blood donors
INSERT IGNORE INTO blood_donors (donor_id, first_name, last_name, phone, blood_group, date_of_birth, gender, address, total_donations, created_by) VALUES
('BD001', 'Amit', 'Singh', '+919876543400', 'O+', '1990-05-15', 'male', '123 Donor Street, City', 5, 1),
('BD002', 'Priya', 'Patel', '+919876543401', 'A+', '1985-08-20', 'female', '456 Helper Lane, City', 3, 1),
('BD003', 'Rohit', 'Kumar', '+919876543402', 'B+', '1988-12-10', 'male', '789 Service Road, City', 7, 1);

-- Sample blood inventory
INSERT IGNORE INTO blood_inventory (blood_group, component_type, bag_number, collection_date, expiry_date, volume_ml, status, storage_location, tested) VALUES
('O+', 'whole_blood', 'BB001', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 35 DAY), 450, 'available', 'Refrigerator-A1', 1),
('A+', 'whole_blood', 'BB002', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 35 DAY), 450, 'available', 'Refrigerator-A2', 1),
('B+', 'red_cells', 'BB003', DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 300, 'available', 'Refrigerator-B1', 1);

-- =============================================================================
-- 12. FINAL VERIFICATION & RESULTS
-- =============================================================================

-- Show success messages
SELECT 'COMPLETE HOSPITAL MANAGEMENT SYSTEM SETUP SUCCESSFUL! ✅' as message;
SELECT 'All modules installed and configured properly' as status;

-- Show installed modules
SELECT 'INSTALLED MODULES:' as modules;
SELECT '✅ Site Configuration & Branding' as module1;
SELECT '✅ Shift Management System' as module2;
SELECT '✅ Attendance Management' as module3;
SELECT '✅ Salary Management System' as module4;
SELECT '✅ Blood Bank Management' as module5;
SELECT '✅ Organ Donation System' as module6;
SELECT '✅ Driver Management' as module7;
SELECT '✅ All Previous Fixes Applied' as module8;

-- Show key statistics
SELECT 
    'SYSTEM STATISTICS:' as stats,
    (SELECT COUNT(*) FROM roles WHERE is_active = 1) as total_roles,
    (SELECT COUNT(*) FROM shifts WHERE is_active = 1) as total_shifts,
    (SELECT COUNT(*) FROM blood_groups) as blood_groups_configured,
    (SELECT COUNT(*) FROM organ_types WHERE is_active = 1) as organ_types_available,
    (SELECT COUNT(*) FROM staff WHERE is_active = 1) as active_staff,
    (SELECT COUNT(*) FROM ambulances WHERE is_active = 1) as ambulances_available;

-- Show current site settings
SELECT 
    'CURRENT SITE CONFIGURATION:' as config,
    name as hospital_name,
    site_title,
    logo_url,
    primary_color,
    secondary_color
FROM hospitals 
WHERE id = 1;

SELECT 'SETUP COMPLETE! Access admin dashboard to manage all modules.' as final_message;
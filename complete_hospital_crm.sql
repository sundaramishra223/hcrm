-- =============================================
-- HOSPITAL CRM - COMPLETE DATABASE SETUP
-- =============================================

-- Drop database if exists and create new
DROP DATABASE IF EXISTS hospital_crm;
CREATE DATABASE hospital_crm;
USE hospital_crm;

-- =============================================
-- CORE TABLES
-- =============================================

-- Roles table
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    role_display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Hospitals table
CREATE TABLE hospitals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    website VARCHAR(200),
    logo_url VARCHAR(255),
    favicon_url VARCHAR(255),
    primary_color VARCHAR(7) DEFAULT '#004685',
    secondary_color VARCHAR(7) DEFAULT '#0066cc',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Departments table
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20),
    description TEXT,
    head_doctor_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- =============================================
-- STAFF MANAGEMENT
-- =============================================

-- Doctors table
CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    hospital_id INT NOT NULL,
    department_id INT,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    specialization VARCHAR(100),
    qualification TEXT,
    experience_years INT,
    registration_number VARCHAR(50),
    phone VARCHAR(20),
    emergency_contact VARCHAR(20),
    address TEXT,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    blood_group VARCHAR(5),
    consultation_fee DECIMAL(10,2),
    joined_date DATE,
    is_available BOOLEAN DEFAULT TRUE,
    is_intern BOOLEAN DEFAULT FALSE,
    senior_doctor_id INT,
    certificates JSON,
    awards JSON,
    vitals JSON,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (senior_doctor_id) REFERENCES doctors(id)
);

-- Staff table
CREATE TABLE staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    hospital_id INT NOT NULL,
    department_id INT,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    staff_type ENUM('nurse', 'receptionist', 'lab_technician', 'pharmacy_staff', 'intern_nurse', 'intern_lab', 'intern_pharmacy') NOT NULL,
    phone VARCHAR(20),
    emergency_contact VARCHAR(20),
    address TEXT,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    blood_group VARCHAR(5),
    date_of_joining DATE,
    salary DECIMAL(10,2),
    qualification TEXT,
    is_intern BOOLEAN DEFAULT FALSE,
    senior_staff_id INT,
    vitals JSON,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (senior_staff_id) REFERENCES staff(id)
);

-- =============================================
-- PATIENT MANAGEMENT
-- =============================================

-- Patients table
CREATE TABLE patients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE,
    hospital_id INT NOT NULL,
    patient_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    emergency_contact VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    blood_group VARCHAR(5),
    marital_status ENUM('single', 'married', 'divorced', 'widowed'),
    occupation VARCHAR(100),
    medical_history TEXT,
    allergies TEXT,
    assigned_doctor_id INT,
    patient_type ENUM('outpatient', 'inpatient') DEFAULT 'outpatient',
    insurance_provider VARCHAR(100),
    insurance_number VARCHAR(50),
    emergency_contact_name VARCHAR(100),
    emergency_contact_relation VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (assigned_doctor_id) REFERENCES doctors(id)
);

-- Patient Vitals table
CREATE TABLE patient_vitals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    recorded_by INT NOT NULL,
    recorded_by_type ENUM('doctor', 'nurse', 'lab_tech') NOT NULL,
    temperature DECIMAL(4,1),
    blood_pressure VARCHAR(20),
    heart_rate INT,
    respiratory_rate INT,
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    bmi DECIMAL(4,2),
    oxygen_saturation DECIMAL(4,1),
    notes TEXT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);

-- Patient Visits table
CREATE TABLE patient_visits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    visit_type ENUM('consultation', 'emergency', 'follow_up', 'routine') NOT NULL,
    visit_reason TEXT NOT NULL,
    attendant_name VARCHAR(100),
    attendant_phone VARCHAR(20),
    assigned_doctor_id INT,
    assigned_nurse_id INT,
    visit_date DATE NOT NULL,
    visit_time TIME,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (assigned_doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (assigned_nurse_id) REFERENCES staff(id)
);

-- =============================================
-- APPOINTMENTS & SCHEDULING
-- =============================================

-- Appointments table
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    appointment_type ENUM('consultation', 'follow_up', 'emergency', 'routine') DEFAULT 'consultation',
    status ENUM('scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    reason TEXT,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =============================================
-- BED & EQUIPMENT MANAGEMENT
-- =============================================

-- Beds table
CREATE TABLE beds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    bed_number VARCHAR(20) UNIQUE NOT NULL,
    bed_type ENUM('general', 'icu', 'private', 'semi_private') DEFAULT 'general',
    room_number VARCHAR(20),
    floor_number INT,
    department_id INT,
    status ENUM('available', 'occupied', 'maintenance', 'reserved') DEFAULT 'available',
    current_patient_id INT,
    daily_rate DECIMAL(10,2),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (current_patient_id) REFERENCES patients(id)
);

-- Bed Assignments table
CREATE TABLE bed_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bed_id INT NOT NULL,
    patient_id INT NOT NULL,
    assigned_date DATETIME NOT NULL,
    discharge_date DATETIME,
    status ENUM('active', 'discharged', 'transferred') DEFAULT 'active',
    notes TEXT,
    assigned_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bed_id) REFERENCES beds(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

-- Equipment table
CREATE TABLE equipment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100) UNIQUE,
    manufacturer VARCHAR(200),
    purchase_date DATE,
    warranty_expiry DATE,
    cost DECIMAL(12,2),
    location VARCHAR(200),
    status ENUM('operational', 'maintenance', 'out_of_service', 'retired') DEFAULT 'operational',
    maintenance_schedule TEXT,
    specifications JSON,
    notes TEXT,
    last_maintenance DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- Equipment Maintenance table
CREATE TABLE equipment_maintenance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    maintenance_type ENUM('routine', 'repair', 'calibration', 'inspection') NOT NULL,
    maintenance_date DATE NOT NULL,
    next_maintenance_date DATE,
    cost DECIMAL(10,2),
    performed_by VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id)
);

-- =============================================
-- PHARMACY MANAGEMENT
-- =============================================

-- Medicines table
CREATE TABLE medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200),
    manufacturer VARCHAR(200),
    category VARCHAR(100),
    dosage_form VARCHAR(100),
    strength VARCHAR(100),
    unit_price DECIMAL(10,2) NOT NULL,
    pack_size VARCHAR(50),
    batch_number VARCHAR(100),
    expiry_date DATE,
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 10,
    prescription_required BOOLEAN DEFAULT FALSE,
    side_effects TEXT,
    contraindications TEXT,
    storage_conditions TEXT,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- Medicine Stock Movements table
CREATE TABLE medicine_stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT NOT NULL,
    movement_type ENUM('add', 'subtract', 'adjust', 'expired', 'damaged') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT NOT NULL,
    new_stock INT NOT NULL,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =============================================
-- LABORATORY MANAGEMENT
-- =============================================

-- Lab Tests table
CREATE TABLE lab_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    test_code VARCHAR(50) UNIQUE,
    category VARCHAR(100),
    cost DECIMAL(10,2) NOT NULL,
    description TEXT,
    preparation_instructions TEXT,
    normal_range TEXT,
    unit VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Lab Orders table
CREATE TABLE lab_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    order_date DATE NOT NULL,
    expected_date DATE,
    priority ENUM('routine', 'urgent', 'emergency') DEFAULT 'routine',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    total_cost DECIMAL(10,2),
    clinical_notes TEXT,
    created_by INT NOT NULL,
    started_at DATETIME,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Lab Order Tests table
CREATE TABLE lab_order_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lab_order_id INT NOT NULL,
    lab_test_id INT NOT NULL,
    test_name VARCHAR(200) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    result_value VARCHAR(200),
    result_unit VARCHAR(50),
    normal_range VARCHAR(100),
    result_status ENUM('normal', 'abnormal', 'critical') DEFAULT 'normal',
    is_abnormal BOOLEAN DEFAULT FALSE,
    technician_notes TEXT,
    completed_by INT,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_order_id) REFERENCES lab_orders(id),
    FOREIGN KEY (lab_test_id) REFERENCES lab_tests(id),
    FOREIGN KEY (completed_by) REFERENCES users(id)
);

-- =============================================
-- BILLING & PAYMENTS
-- =============================================

-- Bills table
CREATE TABLE bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    patient_id INT NOT NULL,
    visit_id INT,
    bill_number VARCHAR(50) UNIQUE NOT NULL,
    bill_date DATE NOT NULL,
    bill_type ENUM('consultation', 'lab', 'pharmacy', 'equipment', 'bed', 'comprehensive') NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) DEFAULT 0,
    balance_amount DECIMAL(12,2) NOT NULL,
    payment_status ENUM('pending', 'partial', 'paid', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('cash', 'card', 'online', 'insurance') DEFAULT 'cash',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (visit_id) REFERENCES patient_visits(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Bill Items table
CREATE TABLE bill_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT NOT NULL,
    item_type ENUM('consultation', 'medicine', 'lab_test', 'equipment', 'bed', 'other') NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    item_code VARCHAR(50),
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    final_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id)
);

-- =============================================
-- PRESCRIPTIONS
-- =============================================

-- Prescriptions table
CREATE TABLE prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    prescription_date DATE NOT NULL,
    diagnosis TEXT,
    notes TEXT,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    dispensed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Prescription Medicines table
CREATE TABLE prescription_medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(100),
    instructions TEXT,
    dispensed_quantity INT DEFAULT 0,
    dispensed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

-- =============================================
-- ATTENDANCE & SALARY
-- =============================================

-- Staff Attendance table
CREATE TABLE staff_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'half_day', 'leave', 'holiday') NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    notes TEXT,
    marked_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (marked_by) REFERENCES users(id)
);

-- Staff Salary table
CREATE TABLE staff_salary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    base_salary DECIMAL(12,2) NOT NULL,
    present_days INT DEFAULT 0,
    absent_days INT DEFAULT 0,
    half_days INT DEFAULT 0,
    leave_days INT DEFAULT 0,
    total_salary DECIMAL(12,2) NOT NULL,
    deductions DECIMAL(12,2) DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL,
    calculated_by INT NOT NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (calculated_by) REFERENCES users(id)
);

-- =============================================
-- SHIFTS & SCHEDULING
-- =============================================

-- Shifts table
CREATE TABLE shifts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    shift_name VARCHAR(100) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- Shift Assignments table
CREATE TABLE shift_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    shift_id INT NOT NULL,
    assignment_date DATE NOT NULL,
    status ENUM('assigned', 'completed', 'cancelled') DEFAULT 'assigned',
    notes TEXT,
    assigned_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id),
    FOREIGN KEY (shift_id) REFERENCES shifts(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

-- =============================================
-- INSURANCE & CLAIMS
-- =============================================

-- Insurance Claims table
CREATE TABLE insurance_claims (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    bill_id INT NOT NULL,
    insurance_provider VARCHAR(100) NOT NULL,
    policy_number VARCHAR(100) NOT NULL,
    claim_amount DECIMAL(12,2) NOT NULL,
    approved_amount DECIMAL(12,2),
    approved_by INT,
    approved_at DATETIME,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (bill_id) REFERENCES bills(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- =============================================
-- AMBULANCE MANAGEMENT
-- =============================================

-- Ambulances table
CREATE TABLE ambulances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    vehicle_number VARCHAR(20) UNIQUE NOT NULL,
    vehicle_type ENUM('basic', 'advanced', 'icu') DEFAULT 'basic',
    driver_name VARCHAR(100),
    driver_phone VARCHAR(20),
    status ENUM('available', 'in_use', 'maintenance') DEFAULT 'available',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- Ambulance Bookings table
CREATE TABLE ambulance_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ambulance_id INT NOT NULL,
    patient_id INT,
    pickup_address TEXT NOT NULL,
    destination_address TEXT NOT NULL,
    booking_date DATETIME NOT NULL,
    pickup_time DATETIME,
    return_time DATETIME,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    charges DECIMAL(10,2),
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ambulance_id) REFERENCES ambulances(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =============================================
-- FEEDBACK & COMMUNICATION
-- =============================================

-- Feedback table
CREATE TABLE feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT,
    user_id INT,
    feedback_type ENUM('patient', 'staff', 'general') NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(200),
    message TEXT NOT NULL,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    response TEXT,
    responded_by INT,
    responded_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (responded_by) REFERENCES users(id)
);

-- Email Templates table
CREATE TABLE email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    variables JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- Email Logs table
CREATE TABLE email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT,
    recipient_email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    sent_at DATETIME,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES email_templates(id)
);

-- =============================================
-- SYSTEM SETTINGS & CUSTOMIZATION
-- =============================================

-- System Settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    UNIQUE KEY unique_setting (hospital_id, setting_key)
);

-- =============================================
-- AUDIT LOGS & ACTION TRAILS
-- =============================================

-- Audit Logs table
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- =============================================
-- INITIAL DATA
-- =============================================

-- Insert default roles
INSERT INTO roles (role_name, role_display_name, description) VALUES
('admin', 'Administrator', 'Full system access'),
('doctor', 'Doctor', 'Doctor access with patient management'),
('nurse', 'Nurse', 'Nurse access with patient care'),
('patient', 'Patient', 'Patient access to own records'),
('receptionist', 'Receptionist', 'Reception and patient registration'),
('lab_technician', 'Lab Technician', 'Laboratory test management'),
('pharmacy_staff', 'Pharmacy Staff', 'Pharmacy and medicine management'),
('intern_doctor', 'Intern Doctor', 'Junior doctor with limited access'),
('intern_nurse', 'Intern Nurse', 'Junior nurse with limited access'),
('intern_lab', 'Intern Lab Tech', 'Junior lab technician with limited access'),
('intern_pharmacy', 'Intern Pharmacy', 'Junior pharmacy staff with limited access');

-- Insert default hospital
INSERT INTO hospitals (name, code, address, phone, email, website) VALUES
('General Hospital', 'GH001', '123 Main Street, City, State', '+1234567890', 'info@generalhospital.com', 'www.generalhospital.com');

-- Insert default departments
INSERT INTO departments (hospital_id, name, code) VALUES
(1, 'Cardiology', 'CARD'),
(1, 'Neurology', 'NEURO'),
(1, 'Orthopedics', 'ORTHO'),
(1, 'Pediatrics', 'PED'),
(1, 'Emergency Medicine', 'EMERG'),
(1, 'General Medicine', 'GEN'),
(1, 'Surgery', 'SURG'),
(1, 'Laboratory', 'LAB'),
(1, 'Pharmacy', 'PHARM'),
(1, 'Radiology', 'RAD');

-- Insert default shifts
INSERT INTO shifts (hospital_id, shift_name, start_time, end_time) VALUES
(1, 'Morning Shift', '08:00:00', '16:00:00'),
(1, 'Evening Shift', '16:00:00', '00:00:00'),
(1, 'Night Shift', '00:00:00', '08:00:00');

-- Insert default system settings
INSERT INTO system_settings (hospital_id, setting_key, setting_value, setting_type, description) VALUES
(1, 'site_title', 'Hospital CRM', 'string', 'Website title'),
(1, 'site_logo', '', 'string', 'Logo URL'),
(1, 'site_favicon', '', 'string', 'Favicon URL'),
(1, 'primary_color', '#004685', 'string', 'Primary color'),
(1, 'secondary_color', '#0066cc', 'string', 'Secondary color'),
(1, 'enable_dark_mode', 'false', 'boolean', 'Enable dark mode'),
(1, 'enable_email_notifications', 'true', 'boolean', 'Enable email notifications'),
(1, 'enable_sms_notifications', 'false', 'boolean', 'Enable SMS notifications'),
(1, 'enable_intern_system', 'true', 'boolean', 'Enable intern system'),
(1, 'enable_attendance_system', 'true', 'boolean', 'Enable attendance system'),
(1, 'enable_multi_hospital', 'false', 'boolean', 'Enable multi-hospital system'),
(1, 'enable_insurance_claims', 'true', 'boolean', 'Enable insurance claims'),
(1, 'enable_ambulance_management', 'true', 'boolean', 'Enable ambulance management'),
(1, 'enable_feedback_system', 'true', 'boolean', 'Enable feedback system'),
(1, 'enable_home_visits', 'true', 'boolean', 'Enable home visits'),
(1, 'enable_video_consultation', 'true', 'boolean', 'Enable video consultation'),
(1, 'enable_backup_system', 'true', 'boolean', 'Enable auto backup system'),
(1, 'enable_audit_logging', 'true', 'boolean', 'Enable audit logging'),
(1, 'enable_two_factor_auth', 'false', 'boolean', 'Enable two-factor authentication'),
(1, 'min_password_length', '8', 'number', 'Minimum password length'),
(1, 'require_uppercase', 'true', 'boolean', 'Require uppercase letters in password'),
(1, 'require_numbers', 'true', 'boolean', 'Require numbers in password'),
(1, 'require_special_chars', 'false', 'boolean', 'Require special characters in password'),
(1, 'session_timeout', '30', 'number', 'Session timeout in minutes'),
(1, 'force_logout_on_password_change', 'true', 'boolean', 'Force logout on password change'),
(1, 'prevent_concurrent_logins', 'false', 'boolean', 'Prevent concurrent logins'),
(1, 'max_login_attempts', '5', 'number', 'Maximum login attempts'),
(1, 'lockout_duration', '15', 'number', 'Lockout duration in minutes'),
(1, 'enable_captcha', 'false', 'boolean', 'Enable CAPTCHA on login'),
(1, 'enable_data_encryption', 'true', 'boolean', 'Enable data encryption'),
(1, 'enable_ssl_redirect', 'false', 'boolean', 'Force HTTPS redirect'),
(1, 'enable_csrf_protection', 'true', 'boolean', 'Enable CSRF protection');

-- Insert default email templates
INSERT INTO email_templates (hospital_id, template_name, subject, body, variables) VALUES
(1, 'appointment_reminder', 'Appointment Reminder', 'Dear {patient_name},\n\nThis is a reminder for your appointment on {appointment_date} at {appointment_time} with Dr. {doctor_name}.\n\nPlease arrive 15 minutes before your scheduled time.\n\nBest regards,\nHospital Team', '["patient_name", "appointment_date", "appointment_time", "doctor_name"]'),
(1, 'bill_notification', 'Bill Notification', 'Dear {patient_name},\n\nYour bill for {bill_amount} has been generated. Please visit the hospital to make the payment.\n\nBill Number: {bill_number}\nDue Date: {due_date}\n\nBest regards,\nHospital Team', '["patient_name", "bill_amount", "bill_number", "due_date"]'),
(1, 'emergency_notification', 'Emergency Notification', 'Dear {recipient_name},\n\nThis is an emergency notification regarding {patient_name}.\n\nPlease contact the hospital immediately.\n\nBest regards,\nHospital Team', '["recipient_name", "patient_name"]');

-- Insert demo users with password 'password'
INSERT INTO users (username, email, password_hash, role_id) VALUES
('admin', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('dr.sharma', 'dr.sharma@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2),
('john.doe', 'john.doe@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4),
('priya.nurse', 'priya.nurse@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3),
('reception', 'reception@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5),
('lab.tech', 'lab.tech@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6),
('pharmacy.staff', 'pharmacy.staff@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 7),
('intern.doctor', 'intern.doctor@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 8),
('intern.nurse', 'intern.nurse@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 9),
('intern.lab', 'intern.lab@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 10),
('intern.pharmacy', 'intern.pharmacy@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 11);

-- Insert demo doctors
INSERT INTO doctors (user_id, hospital_id, department_id, employee_id, first_name, last_name, specialization, qualification, experience_years, registration_number, phone, emergency_contact, address, date_of_birth, gender, blood_group, consultation_fee, joined_date) VALUES
(2, 1, 1, 'DOC001', 'Dr. Rajesh', 'Sharma', 'Cardiologist', 'MBBS, MD (Cardiology)', 15, 'CARD001', '+919876543210', '+919876543211', '123 Doctor Lane, City', '1980-05-15', 'male', 'B+', 1500.00, '2020-01-15'),
(8, 1, 2, 'DOC002', 'Dr. Priya', 'Intern', 'Neurologist', 'MBBS, MD (Neurology)', 2, 'NEURO001', '+919876543212', '+919876543213', '456 Intern Street, City', '1995-08-20', 'female', 'O+', 800.00, '2023-06-01');

-- Insert demo staff
INSERT INTO staff (user_id, hospital_id, department_id, employee_id, first_name, last_name, staff_type, phone, emergency_contact, address, date_of_birth, gender, blood_group, date_of_joining, salary, qualification) VALUES
(4, 1, 1, 'NUR001', 'Priya', 'Nurse', 'nurse', '+919876543214', '+919876543215', '789 Nurse Road, City', '1990-03-10', 'female', 'A+', '2021-02-01', 35000.00, 'BSc Nursing'),
(5, 1, 1, 'REC001', 'Reception', 'Staff', 'receptionist', '+919876543216', '+919876543217', '321 Reception Ave, City', '1988-12-05', 'female', 'AB+', '2020-03-01', 25000.00, 'BA'),
(6, 1, 8, 'LAB001', 'Lab', 'Technician', 'lab_technician', '+919876543218', '+919876543219', '654 Lab Street, City', '1992-07-15', 'male', 'B-', '2021-04-01', 30000.00, 'BSc MLT'),
(7, 1, 9, 'PHARM001', 'Pharmacy', 'Staff', 'pharmacy_staff', '+919876543220', '+919876543221', '987 Pharmacy Lane, City', '1993-11-20', 'male', 'O-', '2021-05-01', 28000.00, 'BPharm'),
(9, 1, 1, 'NUR002', 'Intern', 'Nurse', 'intern_nurse', '+919876543222', '+919876543223', '147 Intern Road, City', '1998-04-12', 'female', 'A-', '2023-07-01', 20000.00, 'BSc Nursing'),
(10, 1, 8, 'LAB002', 'Intern', 'Lab Tech', 'intern_lab', '+919876543224', '+919876543225', '258 Intern Lab, City', '1999-09-08', 'male', 'B+', '2023-08-01', 18000.00, 'BSc MLT'),
(11, 1, 9, 'PHARM002', 'Intern', 'Pharmacy', 'intern_pharmacy', '+919876543226', '+919876543227', '369 Intern Pharm, City', '2000-01-25', 'female', 'AB-', '2023-09-01', 17000.00, 'BPharm');

-- Insert demo patients
INSERT INTO patients (user_id, hospital_id, patient_id, first_name, last_name, phone, emergency_contact, email, address, date_of_birth, gender, blood_group, marital_status, occupation, medical_history, allergies, assigned_doctor_id) VALUES
(3, 1, 'PAT001', 'John', 'Doe', '+919876543228', '+919876543229', 'john.doe@email.com', '741 Patient Street, City', '1985-06-18', 'male', 'O+', 'married', 'Software Engineer', 'Hypertension', 'Penicillin', 1);

-- Insert demo beds
INSERT INTO beds (hospital_id, bed_number, bed_type, room_number, floor_number, department_id, status, daily_rate) VALUES
(1, 'BED001', 'general', '101', 1, 1, 'available', 2000.00),
(1, 'BED002', 'general', '102', 1, 1, 'available', 2000.00),
(1, 'BED003', 'icu', 'ICU01', 2, 5, 'available', 5000.00),
(1, 'BED004', 'private', '201', 2, 1, 'available', 3000.00);

-- Insert demo medicines
INSERT INTO medicines (hospital_id, name, generic_name, manufacturer, category, dosage_form, strength, unit_price, pack_size, batch_number, expiry_date, stock_quantity, min_stock_level, prescription_required, side_effects, contraindications, storage_conditions) VALUES
(1, 'Paracetamol', 'Acetaminophen', 'ABC Pharma', 'Analgesic', 'Tablet', '500mg', 5.00, '10 tablets', 'BATCH001', '2025-12-31', 100, 20, FALSE, 'Nausea, stomach upset', 'Liver disease', 'Store in cool, dry place'),
(1, 'Amoxicillin', 'Amoxicillin', 'XYZ Pharma', 'Antibiotic', 'Capsule', '250mg', 15.00, '10 capsules', 'BATCH002', '2024-12-31', 50, 10, TRUE, 'Diarrhea, rash', 'Penicillin allergy', 'Store in refrigerator'),
(1, 'Omeprazole', 'Omeprazole', 'DEF Pharma', 'Antacid', 'Capsule', '20mg', 25.00, '10 capsules', 'BATCH003', '2025-06-30', 75, 15, TRUE, 'Headache, nausea', 'Pregnancy', 'Store at room temperature');

-- Insert demo lab tests
INSERT INTO lab_tests (name, test_code, category, cost, description, preparation_instructions, normal_range, unit) VALUES
('Complete Blood Count', 'CBC001', 'Hematology', 500.00, 'Complete blood count test', 'Fasting required for 8 hours', '4.5-11.0', 'cells/μL'),
('Blood Glucose', 'GLU001', 'Biochemistry', 300.00, 'Blood glucose level test', 'Fasting required for 12 hours', '70-100', 'mg/dL'),
('Lipid Profile', 'LIP001', 'Biochemistry', 800.00, 'Complete lipid profile test', 'Fasting required for 12 hours', 'Total: <200', 'mg/dL');

-- Update department head doctors
UPDATE departments SET head_doctor_id = 1 WHERE id = 1;
UPDATE departments SET head_doctor_id = 2 WHERE id = 2;

-- Update doctor senior relationships
UPDATE doctors SET senior_doctor_id = 1 WHERE id = 2;

-- Update staff senior relationships
UPDATE staff SET senior_staff_id = 4 WHERE id IN (9, 10, 11);

-- =============================================
-- COMPLETE SETUP FINISHED
-- =============================================

-- Show completion message
SELECT 'Hospital CRM Database Setup Complete!' as status;
SELECT 'Database: hospital_crm' as database_name;
SELECT 'Total Tables: 32' as table_count;
SELECT 'Demo Users Created: 11' as user_count;
SELECT 'Demo Data Loaded Successfully!' as data_status;
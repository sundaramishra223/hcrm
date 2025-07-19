-- Hospital CRM Database Schema
-- Enhanced version with all missing features

-- =============================================
-- CORE TABLES (Enhanced)
-- =============================================

-- Users table (Enhanced)
CREATE TABLE IF NOT EXISTS users (
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

-- Roles table (Enhanced)
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    role_display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Hospitals table (Multi-hospital support)
CREATE TABLE IF NOT EXISTS hospitals (
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

-- Departments table (Enhanced)
CREATE TABLE IF NOT EXISTS departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20),
    description TEXT,
    head_doctor_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (head_doctor_id) REFERENCES doctors(id)
);

-- =============================================
-- STAFF MANAGEMENT (Enhanced)
-- =============================================

-- Doctors table (Enhanced)
CREATE TABLE IF NOT EXISTS doctors (
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

-- Staff table (Enhanced)
CREATE TABLE IF NOT EXISTS staff (
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
-- PATIENT MANAGEMENT (Enhanced)
-- =============================================

-- Patients table (Enhanced)
CREATE TABLE IF NOT EXISTS patients (
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
CREATE TABLE IF NOT EXISTS patient_vitals (
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
CREATE TABLE IF NOT EXISTS patient_visits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    visit_type ENUM('consultation', 'emergency', 'follow_up', 'routine') NOT NULL,
    visit_reason TEXT NOT NULL,
    attendant_name VARCHAR(100),
    attendant_relation VARCHAR(50),
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
-- APPOINTMENT SYSTEM (Enhanced)
-- =============================================

-- Appointments table (Enhanced)
CREATE TABLE IF NOT EXISTS appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_number VARCHAR(20) UNIQUE NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration_minutes INT DEFAULT 30,
    type ENUM('consultation', 'follow_up', 'emergency', 'video_consult', 'home_visit') DEFAULT 'consultation',
    chief_complaint TEXT,
    notes TEXT,
    consultation_fee DECIMAL(10,2),
    status ENUM('scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- =============================================
-- BED MANAGEMENT (Enhanced)
-- =============================================

-- Beds table (Enhanced)
CREATE TABLE IF NOT EXISTS beds (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    ward_id INT,
    bed_number VARCHAR(20) NOT NULL,
    bed_type ENUM('general', 'semi_private', 'private', 'icu', 'emergency') DEFAULT 'general',
    status ENUM('available', 'occupied', 'maintenance', 'reserved') DEFAULT 'available',
    daily_rate DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- Bed Assignments table (Enhanced)
CREATE TABLE IF NOT EXISTS bed_assignments (
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

-- =============================================
-- EQUIPMENT MANAGEMENT (Enhanced)
-- =============================================

-- Equipment table (Enhanced)
CREATE TABLE IF NOT EXISTS equipment (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    manufacturer VARCHAR(100),
    purchase_date DATE,
    warranty_expiry DATE,
    cost DECIMAL(12,2),
    location VARCHAR(200),
    status ENUM('available', 'in_use', 'maintenance', 'out_of_order') DEFAULT 'available',
    maintenance_schedule VARCHAR(100),
    specifications JSON,
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- Equipment Maintenance table (Enhanced)
CREATE TABLE IF NOT EXISTS equipment_maintenance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equipment_id INT NOT NULL,
    maintenance_type ENUM('routine', 'repair', 'calibration', 'inspection') NOT NULL,
    maintenance_date DATETIME NOT NULL,
    next_maintenance_date DATE,
    cost DECIMAL(10,2),
    performed_by INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- =============================================
-- PHARMACY MANAGEMENT (Enhanced)
-- =============================================

-- Medicines table (Enhanced)
CREATE TABLE IF NOT EXISTS medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200),
    manufacturer VARCHAR(200),
    category VARCHAR(100),
    dosage_form VARCHAR(50),
    strength VARCHAR(50),
    unit_price DECIMAL(10,2) NOT NULL,
    pack_size VARCHAR(50),
    batch_number VARCHAR(50),
    expiry_date DATE,
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 10,
    prescription_required BOOLEAN DEFAULT TRUE,
    sku_code VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- Medicine Stock Movements table (Enhanced)
CREATE TABLE IF NOT EXISTS medicine_stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medicine_id INT NOT NULL,
    movement_type ENUM('add', 'subtract', 'adjust', 'expiry', 'damage') NOT NULL,
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
-- LABORATORY MANAGEMENT (Enhanced)
-- =============================================

-- Lab Tests table (Enhanced)
CREATE TABLE IF NOT EXISTS lab_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    department_id INT,
    test_name VARCHAR(200) NOT NULL,
    test_code VARCHAR(50),
    description TEXT,
    cost DECIMAL(10,2) NOT NULL,
    preparation_instructions TEXT,
    normal_range TEXT,
    unit VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Lab Orders table (Enhanced)
CREATE TABLE IF NOT EXISTS lab_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    order_date DATETIME NOT NULL,
    expected_date DATE,
    priority ENUM('routine', 'urgent', 'emergency') DEFAULT 'routine',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    total_cost DECIMAL(10,2),
    clinical_notes TEXT,
    created_by INT NOT NULL,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Lab Order Tests table (Enhanced)
CREATE TABLE IF NOT EXISTS lab_order_tests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lab_order_id INT NOT NULL,
    lab_test_id INT NOT NULL,
    test_name VARCHAR(200) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    result_value VARCHAR(200),
    result_unit VARCHAR(50),
    normal_range VARCHAR(100),
    is_abnormal BOOLEAN DEFAULT FALSE,
    result_notes TEXT,
    completed_by INT,
    completed_at DATETIME,
    result_file_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_order_id) REFERENCES lab_orders(id),
    FOREIGN KEY (lab_test_id) REFERENCES lab_tests(id),
    FOREIGN KEY (completed_by) REFERENCES users(id)
);

-- =============================================
-- BILLING SYSTEM (Enhanced)
-- =============================================

-- Bills table (Enhanced)
CREATE TABLE IF NOT EXISTS bills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    patient_id INT NOT NULL,
    visit_id INT,
    bill_number VARCHAR(20) UNIQUE NOT NULL,
    bill_date DATE NOT NULL,
    bill_type ENUM('consultation', 'admission', 'lab', 'pharmacy', 'equipment', 'comprehensive') NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) DEFAULT 0,
    balance_amount DECIMAL(12,2) NOT NULL,
    payment_status ENUM('pending', 'partial', 'paid', 'cancelled') DEFAULT 'pending',
    payment_method ENUM('cash', 'card', 'insurance', 'online') DEFAULT 'cash',
    insurance_claim_id INT,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (visit_id) REFERENCES patient_visits(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Bill Items table (Enhanced)
CREATE TABLE IF NOT EXISTS bill_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT NOT NULL,
    item_type ENUM('consultation', 'medicine', 'lab_test', 'equipment', 'bed', 'procedure') NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    item_code VARCHAR(50),
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    final_price DECIMAL(10,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id)
);

-- =============================================
-- PRESCRIPTION SYSTEM (Enhanced)
-- =============================================

-- Prescriptions table (Enhanced)
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    visit_id INT,
    prescription_number VARCHAR(20) UNIQUE NOT NULL,
    diagnosis TEXT,
    instructions TEXT,
    follow_up_date DATE,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (visit_id) REFERENCES patient_visits(id)
);

-- Prescription Medicines table (Enhanced)
CREATE TABLE IF NOT EXISTS prescription_medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    prescription_id INT NOT NULL,
    medicine_id INT NOT NULL,
    dosage VARCHAR(100) NOT NULL,
    frequency VARCHAR(100) NOT NULL,
    duration VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    instructions TEXT,
    dispensed_quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

-- =============================================
-- ATTENDANCE & SALARY MANAGEMENT
-- =============================================

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_time DATETIME,
    check_out_time DATETIME,
    status ENUM('present', 'absent', 'late', 'half_day', 'leave') DEFAULT 'absent',
    shift_type ENUM('morning', 'evening', 'night') DEFAULT 'morning',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_attendance (user_id, attendance_date)
);

-- Salary table
CREATE TABLE IF NOT EXISTS salary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    allowances DECIMAL(10,2) DEFAULT 0,
    deductions DECIMAL(10,2) DEFAULT 0,
    net_salary DECIMAL(10,2) NOT NULL,
    payment_date DATE,
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_salary (user_id, month, year)
);

-- =============================================
-- SHIFT MANAGEMENT
-- =============================================

-- Shifts table
CREATE TABLE IF NOT EXISTS shifts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    shift_name VARCHAR(50) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id)
);

-- Shift Assignments table
CREATE TABLE IF NOT EXISTS shift_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    shift_id INT NOT NULL,
    assignment_date DATE NOT NULL,
    status ENUM('assigned', 'completed', 'cancelled') DEFAULT 'assigned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (shift_id) REFERENCES shifts(id)
);

-- =============================================
-- INSURANCE & CLAIMS
-- =============================================

-- Insurance Claims table
CREATE TABLE IF NOT EXISTS insurance_claims (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    bill_id INT NOT NULL,
    insurance_provider VARCHAR(100) NOT NULL,
    insurance_number VARCHAR(50) NOT NULL,
    claim_amount DECIMAL(12,2) NOT NULL,
    claim_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
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
CREATE TABLE IF NOT EXISTS ambulances (
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
CREATE TABLE IF NOT EXISTS ambulance_bookings (
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
CREATE TABLE IF NOT EXISTS feedback (
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
CREATE TABLE IF NOT EXISTS email_templates (
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
CREATE TABLE IF NOT EXISTS email_logs (
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
CREATE TABLE IF NOT EXISTS system_settings (
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
CREATE TABLE IF NOT EXISTS audit_logs (
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
(1, 'enable_intern_system', 'false', 'boolean', 'Enable intern system'),
(1, 'enable_attendance_system', 'false', 'boolean', 'Enable attendance system'),
(1, 'enable_multi_hospital', 'false', 'boolean', 'Enable multi-hospital system'),
(1, 'enable_insurance_claims', 'false', 'boolean', 'Enable insurance claims'),
(1, 'enable_ambulance_management', 'false', 'boolean', 'Enable ambulance management'),
(1, 'enable_feedback_system', 'false', 'boolean', 'Enable feedback system'),
(1, 'enable_home_visits', 'false', 'boolean', 'Enable home visits'),
(1, 'enable_video_consultation', 'false', 'boolean', 'Enable video consultation');

-- Insert default email templates
INSERT INTO email_templates (hospital_id, template_name, subject, body, variables) VALUES
(1, 'appointment_reminder', 'Appointment Reminder', 'Dear {patient_name},\n\nThis is a reminder for your appointment on {appointment_date} at {appointment_time} with Dr. {doctor_name}.\n\nPlease arrive 15 minutes before your scheduled time.\n\nBest regards,\nHospital Team', '["patient_name", "appointment_date", "appointment_time", "doctor_name"]'),
(1, 'bill_notification', 'Bill Notification', 'Dear {patient_name},\n\nYour bill for {bill_amount} has been generated. Please visit the hospital to make the payment.\n\nBill Number: {bill_number}\nDue Date: {due_date}\n\nBest regards,\nHospital Team', '["patient_name", "bill_amount", "bill_number", "due_date"]'),
(1, 'emergency_notification', 'Emergency Notification', 'Dear {recipient_name},\n\nThis is an emergency notification regarding {patient_name}.\n\nPlease contact the hospital immediately.\n\nBest regards,\nHospital Team', '["recipient_name", "patient_name"]');
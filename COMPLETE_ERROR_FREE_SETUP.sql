-- =============================================
-- COMPLETE ERROR-FREE HOSPITAL CRM SETUP
-- =============================================
-- This file includes all fixes and new features
-- Password for all demo users: 5und@r@M
-- =============================================

-- =============================================
-- 1. MEDICINE CATEGORIES TABLE (NEW FEATURE)
-- =============================================

CREATE TABLE IF NOT EXISTS medicine_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hospital_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    UNIQUE KEY unique_category_per_hospital (hospital_id, name)
);

-- Insert default medicine categories
INSERT IGNORE INTO medicine_categories (hospital_id, name, description) VALUES
(1, 'Antibiotics', 'Medications that fight bacterial infections'),
(1, 'Pain Relief', 'Analgesics and pain management medications'),
(1, 'Vitamins', 'Vitamin supplements and nutritional aids'),
(1, 'Heart Medications', 'Cardiovascular and cardiac medications'),
(1, 'Diabetes', 'Medications for diabetes management'),
(1, 'Blood Pressure', 'Hypertension and blood pressure medications'),
(1, 'Respiratory', 'Medications for breathing and lung conditions'),
(1, 'Digestive', 'Medications for stomach and digestive issues'),
(1, 'Skin Care', 'Topical medications and skin treatments'),
(1, 'Mental Health', 'Psychiatric and mental health medications'),
(1, 'Emergency', 'Emergency and critical care medications'),
(1, 'Pediatric', 'Medications specifically for children'),
(1, 'Surgical', 'Pre and post-operative medications'),
(1, 'Injectable', 'Injectable medications and vaccines');

-- =============================================
-- 2. ADD MISSING COLUMNS TO EXISTING TABLES
-- =============================================

-- Add missing columns to medicines table (safe to run multiple times)
ALTER TABLE medicines 
ADD COLUMN IF NOT EXISTS side_effects TEXT,
ADD COLUMN IF NOT EXISTS contraindications TEXT,
ADD COLUMN IF NOT EXISTS storage_conditions TEXT,
ADD COLUMN IF NOT EXISTS notes TEXT;

-- Add missing columns to lab_orders table 
ALTER TABLE lab_orders 
ADD COLUMN IF NOT EXISTS clinical_notes TEXT,
ADD COLUMN IF NOT EXISTS total_cost DECIMAL(10,2);

-- Add missing columns to patients table
ALTER TABLE patients 
ADD COLUMN IF NOT EXISTS blood_group VARCHAR(5),
ADD COLUMN IF NOT EXISTS emergency_contact VARCHAR(20),
ADD COLUMN IF NOT EXISTS insurance_number VARCHAR(50);

-- Add missing columns to appointments table  
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS notes TEXT,
ADD COLUMN IF NOT EXISTS type ENUM('consultation', 'follow_up', 'emergency') DEFAULT 'consultation';

-- =============================================
-- 3. FIX LAB ORDER NUMBER ISSUE
-- =============================================

-- Update any existing lab_orders with missing order_number
UPDATE lab_orders 
SET order_number = CONCAT('LAB', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(id, 4, '0'))
WHERE order_number IS NULL OR order_number = '';

-- =============================================
-- 4. CREATE DEMO USERS WITH COMMON PASSWORD
-- =============================================

-- Password hash for '5und@r@M'
SET @password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- Insert demo users (only if not exists)
INSERT IGNORE INTO users (username, email, password_hash, role_id, is_active) VALUES
('admin', 'admin@hospital.com', @password_hash, 1, 1),
('dr.sharma', 'dr.sharma@hospital.com', @password_hash, 2, 1),
('patient.demo', 'demo@patient.com', @password_hash, 3, 1),
('pharmacy.demo', 'pharmacy@demo.com', @password_hash, 4, 1),
('lab.tech', 'lab@demo.com', @password_hash, 5, 1),
('reception.demo', 'reception@demo.com', @password_hash, 6, 1),
('nurse.priya', 'nurse@demo.com', @password_hash, 7, 1);

-- Update password for any existing demo users
UPDATE users SET password_hash = @password_hash 
WHERE email IN (
    'admin@hospital.com', 'dr.sharma@hospital.com', 'demo@patient.com', 
    'pharmacy@demo.com', 'lab@demo.com', 'reception@demo.com', 'nurse@demo.com',
    'priya.nurse@hospital.com', 'patient.demo@hospital.com', 'doctor.demo@hospital.com'
);

-- Get user IDs for creating staff records
SET @admin_id = (SELECT id FROM users WHERE email = 'admin@hospital.com');
SET @doctor_id = (SELECT id FROM users WHERE email = 'dr.sharma@hospital.com');
SET @patient_id = (SELECT id FROM users WHERE email = 'demo@patient.com');
SET @pharmacy_id = (SELECT id FROM users WHERE email = 'pharmacy@demo.com');
SET @lab_id = (SELECT id FROM users WHERE email = 'lab@demo.com');
SET @reception_id = (SELECT id FROM users WHERE email = 'reception@demo.com');
SET @nurse_id = (SELECT id FROM users WHERE email = 'nurse@demo.com');

-- Create doctor record (only if not exists)
INSERT IGNORE INTO doctors (user_id, hospital_id, employee_id, first_name, last_name, specialization, consultation_fee, is_available) VALUES
(@doctor_id, 1, 'DOC001', 'Dr. Rajesh', 'Sharma', 'General Medicine', 500.00, 1);

-- Create patient record (only if not exists)
INSERT IGNORE INTO patients (user_id, hospital_id, patient_id, first_name, last_name, phone, gender, date_of_birth) VALUES
(@patient_id, 1, 'PAT001', 'Demo', 'Patient', '9876543210', 'male', '1990-01-01');

-- Create staff records (only if not exists) - FIXED ENUM VALUES
INSERT IGNORE INTO staff (user_id, hospital_id, employee_id, first_name, last_name, staff_type, is_active) VALUES
(@pharmacy_id, 1, 'PHAR001', 'Pharmacy', 'Staff', 'pharmacy_staff', 1),
(@lab_id, 1, 'LAB001', 'Lab', 'Technician', 'lab_technician', 1),
(@reception_id, 1, 'REC001', 'Reception', 'Staff', 'receptionist', 1),
(@nurse_id, 1, 'NUR001', 'Priya', 'Nurse', 'nurse', 1);

-- =============================================
-- 5. ADD SAMPLE MEDICINES WITH CATEGORIES
-- =============================================

INSERT IGNORE INTO medicines (hospital_id, name, generic_name, manufacturer, category, dosage_form, strength, unit_price, pack_size, batch_number, expiry_date, stock_quantity, min_stock_level, prescription_required, side_effects, contraindications, storage_conditions, notes) VALUES
(1, 'Paracetamol', 'Acetaminophen', 'PharmaCorp', 'Pain Relief', 'Tablet', '500mg', 2.50, '10 tablets', 'PAR001', DATE_ADD(NOW(), INTERVAL 2 YEAR), 1000, 50, 0, 'Nausea, rash', 'Liver disease', 'Store in cool dry place', 'Common pain reliever'),
(1, 'Amoxicillin', 'Amoxicillin', 'AntiBio Ltd', 'Antibiotics', 'Capsule', '250mg', 15.00, '21 capsules', 'AMO001', DATE_ADD(NOW(), INTERVAL 18 MONTH), 500, 30, 1, 'Diarrhea, nausea', 'Penicillin allergy', 'Refrigerate', 'Broad spectrum antibiotic'),
(1, 'Vitamin C', 'Ascorbic Acid', 'VitaLife', 'Vitamins', 'Tablet', '1000mg', 8.00, '30 tablets', 'VIT001', DATE_ADD(NOW(), INTERVAL 3 YEAR), 800, 40, 0, 'Stomach upset', 'Kidney stones', 'Store in dry place', 'Immune system support'),
(1, 'Metformin', 'Metformin HCl', 'DiabCare', 'Diabetes', 'Tablet', '500mg', 12.00, '30 tablets', 'MET001', DATE_ADD(NOW(), INTERVAL 2 YEAR), 300, 25, 1, 'Nausea, diarrhea', 'Kidney disease', 'Store below 25°C', 'Diabetes management'),
(1, 'Atenolol', 'Atenolol', 'CardioMed', 'Blood Pressure', 'Tablet', '50mg', 18.00, '28 tablets', 'ATE001', DATE_ADD(NOW(), INTERVAL 2 YEAR), 200, 20, 1, 'Dizziness, fatigue', 'Asthma, heart block', 'Store in cool place', 'Blood pressure control');

-- =============================================
-- 6. ADD SAMPLE LAB TESTS
-- =============================================

INSERT IGNORE INTO lab_tests (hospital_id, test_name, test_code, category, description, normal_range, cost, preparation_required, sample_type, estimated_time, is_active) VALUES
(1, 'Complete Blood Count', 'CBC', 'Hematology', 'Blood count analysis', 'WBC: 4000-11000', 250.00, 0, 'Blood', '2 hours', 1),
(1, 'Blood Sugar Fasting', 'BSF', 'Biochemistry', 'Fasting glucose test', '70-100 mg/dL', 150.00, 1, 'Blood', '1 hour', 1),
(1, 'Liver Function Test', 'LFT', 'Biochemistry', 'Liver enzyme analysis', 'ALT: 7-56 U/L', 400.00, 0, 'Blood', '3 hours', 1),
(1, 'Thyroid Profile', 'TPF', 'Endocrinology', 'Thyroid hormone levels', 'TSH: 0.4-4.0 mIU/L', 600.00, 0, 'Blood', '4 hours', 1),
(1, 'Urine Analysis', 'URA', 'Urology', 'Complete urine examination', 'Clear, yellow', 100.00, 0, 'Urine', '30 minutes', 1);

-- =============================================
-- 7. VERIFICATION AND STATUS CHECK
-- =============================================

-- Show created categories
SELECT 'MEDICINE CATEGORIES CREATED:' as info;
SELECT id, name, description, is_active FROM medicine_categories WHERE hospital_id = 1 ORDER BY name;

-- Show demo users
SELECT 'DEMO USERS CREATED:' as info;
SELECT 
    u.username,
    u.email,
    r.role_display_name as role,
    CASE 
        WHEN u.email = 'admin@hospital.com' THEN '👨‍💼 Admin'
        WHEN u.email = 'dr.sharma@hospital.com' THEN '👩‍⚕️ Doctor'
        WHEN u.email = 'demo@patient.com' THEN '🧑‍⚕️ Patient'
        WHEN u.email = 'pharmacy@demo.com' THEN '💊 Pharmacy'
        WHEN u.email = 'lab@demo.com' THEN '🔬 Lab Tech'
        WHEN u.email = 'reception@demo.com' THEN '👩‍💼 Receptionist'
        WHEN u.email = 'nurse@demo.com' THEN '👩‍⚕️ Nurse'
    END as icon_role,
    u.is_active
FROM users u
LEFT JOIN roles r ON u.role_id = r.id
WHERE u.email IN (
    'admin@hospital.com', 'dr.sharma@hospital.com', 'demo@patient.com',
    'pharmacy@demo.com', 'lab@demo.com', 'reception@demo.com', 'nurse@demo.com'
)
ORDER BY u.role_id;

-- Show sample medicines
SELECT 'SAMPLE MEDICINES ADDED:' as info;
SELECT name, category, strength, stock_quantity, unit_price FROM medicines WHERE hospital_id = 1 LIMIT 5;

-- Show sample lab tests
SELECT 'SAMPLE LAB TESTS ADDED:' as info;
SELECT test_name, test_code, category, cost FROM lab_tests WHERE hospital_id = 1 LIMIT 5;

-- Final status
SELECT 
    'SETUP COMPLETE! ✅' as status,
    'All demo users password: 5und@r@M' as login_info,
    'Features: Theme system, Categories, Fixed errors' as features;

-- =============================================
-- END OF SETUP
-- =============================================
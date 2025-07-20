-- Create All Demo Users with Password: 5und@r@M
-- Run this script to create demo users for testing

-- Note: Password hash for '5und@r@M' 
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

-- Update password for existing users
UPDATE users SET password_hash = @password_hash 
WHERE email IN ('admin@hospital.com', 'dr.sharma@hospital.com', 'demo@patient.com', 
               'pharmacy@demo.com', 'lab@demo.com', 'reception@demo.com', 'nurse@demo.com');

-- Get user IDs for reference
SET @admin_id = (SELECT id FROM users WHERE email = 'admin@hospital.com');
SET @doctor_id = (SELECT id FROM users WHERE email = 'dr.sharma@hospital.com');
SET @patient_id = (SELECT id FROM users WHERE email = 'demo@patient.com');
SET @pharmacy_id = (SELECT id FROM users WHERE email = 'pharmacy@demo.com');
SET @lab_id = (SELECT id FROM users WHERE email = 'lab@demo.com');
SET @reception_id = (SELECT id FROM users WHERE email = 'reception@demo.com');
SET @nurse_id = (SELECT id FROM users WHERE email = 'nurse@demo.com');

-- Create corresponding staff records (only if not exists)
INSERT IGNORE INTO doctors (user_id, hospital_id, employee_id, first_name, last_name, specialization, consultation_fee, is_available) VALUES
(@doctor_id, 1, 'DOC001', 'Dr. Rajesh', 'Sharma', 'General Medicine', 500.00, 1);

INSERT IGNORE INTO patients (user_id, hospital_id, patient_id, first_name, last_name, phone, gender, date_of_birth) VALUES
(@patient_id, 1, 'PAT001', 'Demo', 'Patient', '9876543210', 'male', '1990-01-01');

INSERT IGNORE INTO staff (user_id, hospital_id, employee_id, first_name, last_name, staff_type, is_active) VALUES
(@pharmacy_id, 1, 'PHAR001', 'Pharmacy', 'Staff', 'pharmacy_staff', 1),
(@lab_id, 1, 'LAB001', 'Lab', 'Technician', 'lab_technician', 1),
(@reception_id, 1, 'REC001', 'Reception', 'Staff', 'receptionist', 1),
(@nurse_id, 1, 'NUR001', 'Priya', 'Nurse', 'nurse', 1);

-- Show created users
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
JOIN roles r ON u.role_id = r.id
WHERE u.email IN (
    'admin@hospital.com',
    'dr.sharma@hospital.com', 
    'demo@patient.com',
    'pharmacy@demo.com',
    'lab@demo.com',
    'reception@demo.com',
    'nurse@demo.com'
)
ORDER BY u.role_id;

-- Display login information
SELECT 
    '🎮 DEMO LOGIN CREDENTIALS' as info,
    'All passwords: 5und@r@M' as password_info;
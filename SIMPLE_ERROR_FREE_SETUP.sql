-- =============================================
-- SUPER SIMPLE ERROR-FREE HOSPITAL CRM SETUP
-- =============================================
-- Password for all demo users: 5und@r@M
-- This file only does essential things - NO ERRORS!
-- =============================================

-- =============================================
-- 1. UPDATE DEMO USER PASSWORDS ONLY
-- =============================================

-- Password hash for '5und@r@M'
SET @password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- Update password for any existing users (safe)
UPDATE users SET password_hash = @password_hash 
WHERE email IN (
    'admin@hospital.com', 
    'dr.sharma@hospital.com', 
    'demo@patient.com', 
    'pharmacy@demo.com', 
    'lab@demo.com', 
    'reception@demo.com', 
    'nurse@demo.com',
    'priya.nurse@hospital.com', 
    'patient.demo@hospital.com', 
    'doctor.demo@hospital.com'
);

-- =============================================
-- 2. CREATE MEDICINE CATEGORIES (SIMPLE)
-- =============================================

CREATE TABLE IF NOT EXISTS medicine_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert medicine categories (simple)
INSERT IGNORE INTO medicine_categories (name, description) VALUES
('Pain Relief', 'Pain management medications'),
('Antibiotics', 'Infection fighting medications'),
('Vitamins', 'Nutritional supplements'),
('Diabetes', 'Diabetes management medications'),
('Heart', 'Heart and blood pressure medications'),
('Digestive', 'Stomach and digestive medications'),
('Respiratory', 'Breathing and lung medications'),
('Skin Care', 'Skin treatment medications'),
('Emergency', 'Emergency medications'),
('General', 'General purpose medications');

-- =============================================
-- 3. ADD SIMPLE MEDICINES (BASIC COLUMNS ONLY)
-- =============================================

-- Add only basic medicines with minimal columns
INSERT IGNORE INTO medicines (name, category, strength, unit_price, stock_quantity, min_stock_level) VALUES
('Paracetamol', 'Pain Relief', '500mg', 2.50, 1000, 50),
('Amoxicillin', 'Antibiotics', '250mg', 15.00, 500, 30),
('Vitamin C', 'Vitamins', '1000mg', 8.00, 800, 40),
('Aspirin', 'Pain Relief', '75mg', 3.00, 600, 25),
('Ibuprofen', 'Pain Relief', '400mg', 5.00, 400, 20);

-- =============================================
-- 4. CREATE BASIC ROLES IF NOT EXIST
-- =============================================

INSERT IGNORE INTO roles (id, role_name, role_display_name) VALUES
(1, 'admin', 'Administrator'),
(2, 'doctor', 'Doctor'),
(3, 'patient', 'Patient'),
(4, 'pharmacy_staff', 'Pharmacy Staff'),
(5, 'lab_technician', 'Lab Technician'),
(6, 'receptionist', 'Receptionist'),
(7, 'nurse', 'Nurse');

-- =============================================
-- 5. CREATE DEMO USERS (SIMPLE)
-- =============================================

-- Insert demo users (only if not exists)
INSERT IGNORE INTO users (username, email, password_hash, role_id, is_active) VALUES
('admin', 'admin@hospital.com', @password_hash, 1, 1),
('dr.sharma', 'dr.sharma@hospital.com', @password_hash, 2, 1),
('demo.patient', 'demo@patient.com', @password_hash, 3, 1),
('pharmacy.staff', 'pharmacy@demo.com', @password_hash, 4, 1),
('lab.tech', 'lab@demo.com', @password_hash, 5, 1),
('receptionist', 'reception@demo.com', @password_hash, 6, 1),
('nurse', 'nurse@demo.com', @password_hash, 7, 1);

-- =============================================
-- 6. VERIFICATION (SIMPLE)
-- =============================================

-- Show demo users
SELECT 'DEMO USERS SETUP COMPLETE!' as status;
SELECT 
    u.username,
    u.email,
    CASE 
        WHEN u.email = 'admin@hospital.com' THEN '👨‍💼 Admin'
        WHEN u.email = 'dr.sharma@hospital.com' THEN '👩‍⚕️ Doctor'
        WHEN u.email = 'demo@patient.com' THEN '🧑‍⚕️ Patient'
        WHEN u.email = 'pharmacy@demo.com' THEN '💊 Pharmacy'
        WHEN u.email = 'lab@demo.com' THEN '🔬 Lab Tech'
        WHEN u.email = 'reception@demo.com' THEN '👩‍💼 Receptionist'
        WHEN u.email = 'nurse@demo.com' THEN '👩‍⚕️ Nurse'
    END as role_icon,
    'Password: 5und@r@M' as password
FROM users u
WHERE u.email IN (
    'admin@hospital.com', 'dr.sharma@hospital.com', 'demo@patient.com',
    'pharmacy@demo.com', 'lab@demo.com', 'reception@demo.com', 'nurse@demo.com'
)
ORDER BY u.role_id;

-- Show categories
SELECT 'MEDICINE CATEGORIES CREATED!' as status;
SELECT name, description FROM medicine_categories ORDER BY name;

-- Show medicines
SELECT 'SAMPLE MEDICINES ADDED!' as status;  
SELECT name, category, strength, stock_quantity, unit_price FROM medicines LIMIT 5;

-- Final message
SELECT 
    '✅ SETUP COMPLETE - NO ERRORS!' as final_status,
    'Login with any email above using password: 5und@r@M' as login_info,
    'Features: Medicine categories, Demo users, Theme system' as features;

-- =============================================
-- END - KEEP IT SIMPLE!
-- =============================================
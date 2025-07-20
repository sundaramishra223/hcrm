-- =============================================
-- CREATE DEMO PHARMACY USER
-- =============================================

USE hospital_crm;

-- Insert pharmacy staff role (if not exists)
INSERT IGNORE INTO roles (id, role_name, role_display_name, description, permissions, is_active) VALUES
(7, 'pharmacy_staff', 'Pharmacy Staff', 'Pharmacy and medicine management', '[]', 1);

-- Create demo pharmacy user
INSERT IGNORE INTO users (username, email, password_hash, role_id, is_active) VALUES
('pharmacy_demo', 'pharmacy@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 7, 1);

-- Get the user ID
SET @pharmacy_user_id = (SELECT id FROM users WHERE username = 'pharmacy_demo');

-- Create staff record for pharmacy user
INSERT IGNORE INTO staff (user_id, hospital_id, role_id, employee_id, first_name, last_name, staff_type, phone, emergency_contact, address, date_of_birth, gender, blood_group, joined_date, salary, qualifications) VALUES
(@pharmacy_user_id, 1, 7, 'PHARM001', 'Demo', 'Pharmacist', 'pharmacy_staff', '+91-9876543210', '+91-9876543211', '123 Pharmacy Street', '1990-01-01', 'male', 'O+', CURDATE(), 25000.00, 'B.Pharm');

-- Verification
SELECT 'Demo pharmacy user created!' as message;
SELECT u.username, u.email, r.role_display_name, s.first_name, s.last_name, s.staff_type
FROM users u 
JOIN roles r ON u.role_id = r.id 
LEFT JOIN staff s ON u.id = s.user_id 
WHERE u.username = 'pharmacy_demo';

-- Login credentials:
-- Username: pharmacy_demo
-- Password: password
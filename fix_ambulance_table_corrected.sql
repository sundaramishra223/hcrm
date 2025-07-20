-- Fix Ambulance Table - Corrected SQL Syntax
-- Run this to fix ambulance add error + add driver role

-- Add missing columns to ambulances table
ALTER TABLE ambulances 
ADD COLUMN IF NOT EXISTS capacity INT DEFAULT 4,
ADD COLUMN IF NOT EXISTS equipment TEXT,
ADD COLUMN IF NOT EXISTS location VARCHAR(255);

-- Update existing ambulances with default values (separate statements)
UPDATE ambulances SET capacity = 4 WHERE capacity IS NULL;
UPDATE ambulances SET equipment = 'Basic Life Support' WHERE equipment IS NULL OR equipment = '';
UPDATE ambulances SET location = 'Hospital' WHERE location IS NULL OR location = '';

-- =============================================
-- ADD DRIVER ROLE AND DASHBOARD ACCESS
-- =============================================

-- Add driver role
INSERT IGNORE INTO roles (id, role_name, role_display_name, description) VALUES
(8, 'driver', 'Driver', 'Ambulance driver with salary monitoring access');

-- Create driver user for demo
SET @password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

INSERT IGNORE INTO users (username, email, password_hash, role_id, is_active) VALUES
('driver.demo', 'driver@demo.com', @password_hash, 8, 1);

-- Get driver user ID
SET @driver_id = (SELECT id FROM users WHERE email = 'driver@demo.com');

-- Add driver to staff_type ENUM if staff table exists
ALTER TABLE staff MODIFY COLUMN staff_type ENUM('nurse', 'receptionist', 'lab_technician', 'pharmacy_staff', 'intern_nurse', 'intern_lab', 'intern_pharmacy', 'driver') NOT NULL;

-- Create staff record for driver (if staff table exists)
INSERT IGNORE INTO staff (user_id, employee_id, first_name, last_name, staff_type, is_active) VALUES
(@driver_id, 'DRV001', 'Demo', 'Driver', 'driver', 1);

-- Show results
SELECT 'AMBULANCE TABLE FIXED!' as status;
DESCRIBE ambulances;

SELECT 'DRIVER ROLE ADDED!' as status;
SELECT 
    u.username,
    u.email,
    r.role_display_name as role,
    '🚗 Driver - Can monitor salary & ambulance assignments' as description,
    'Password: 5und@r@M' as password
FROM users u
JOIN roles r ON u.role_id = r.id
WHERE u.email = 'driver@demo.com';

SELECT 'Now drivers can login and access their dashboard!' as final_message;
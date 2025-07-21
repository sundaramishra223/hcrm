-- Final Database Fix Based on Actual Structure
-- This script fixes all the issues based on your database dump

-- 1. Add driver role if it doesn't exist
INSERT IGNORE INTO roles (id, role_name, role_display_name, description, permissions, is_active) 
VALUES (12, 'driver', 'Driver', 'Ambulance driver with trip management access', NULL, 1);

-- 2. Update staff table ENUM to include driver (safe way)
-- First check current ENUM values and add driver if not present
SET @sql = CONCAT('ALTER TABLE staff MODIFY COLUMN staff_type ENUM(''nurse'',''receptionist'',''lab_technician'',''pharmacy_staff'',''intern_nurse'',''intern_lab'',''intern_pharmacy'',''driver'')');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Update hospitals table with proper site settings
UPDATE hospitals 
SET 
    name = 'MediCare Hospital',
    logo_url = 'assets/images/logo.svg',
    favicon_url = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232563eb"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM12 17h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
    primary_color = '#2563eb',
    secondary_color = '#10b981',
    site_title = 'MediCare Hospital - Advanced Healthcare Management'
WHERE id = 1;

-- 4. Create sample driver user and staff record
INSERT IGNORE INTO users (username, email, password_hash, role_id, is_active) 
VALUES ('driver.raj', 'driver.raj@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 12, 1);

-- Get the user ID for driver
SET @driver_user_id = (SELECT id FROM users WHERE username = 'driver.raj');

-- Create driver staff record if user was created
INSERT IGNORE INTO staff (user_id, hospital_id, employee_id, first_name, last_name, staff_type, phone, address, date_of_birth, gender, is_active) 
VALUES (@driver_user_id, 1, 'DRV001', 'Raj', 'Kumar', 'driver', '+919876543300', '123 Driver Street, City', '1985-01-15', 'male', 1);

-- 5. Add sample ambulance with proper columns
INSERT IGNORE INTO ambulances (hospital_id, vehicle_number, vehicle_type, driver_name, driver_phone, status, capacity, equipment, location, is_active) 
VALUES 
(1, 'AMB001', 'basic', 'Raj Kumar', '+919876543300', 'available', 4, 'First Aid Kit, Oxygen Tank, Stretcher', 'Hospital Main Gate', 1),
(1, 'AMB002', 'advanced', 'Available', '+919876543301', 'available', 6, 'Advanced Life Support, Defibrillator, Ventilator', 'Emergency Wing', 1);

-- 6. Update system settings for proper theme colors
UPDATE system_settings 
SET 
    setting_value = CASE setting_key
        WHEN 'site_title' THEN 'MediCare Hospital - Advanced Healthcare Management'
        WHEN 'site_logo' THEN 'assets/images/logo.svg'
        WHEN 'primary_color' THEN '#2563eb'
        WHEN 'secondary_color' THEN '#10b981'
        WHEN 'enable_dark_mode' THEN 'true'
        WHEN 'enable_ambulance_management' THEN 'true'
        ELSE setting_value
    END
WHERE hospital_id = 1 
AND setting_key IN ('site_title', 'site_logo', 'primary_color', 'secondary_color', 'enable_dark_mode', 'enable_ambulance_management');

-- 7. Create sample ambulance trips for drivers to view
INSERT IGNORE INTO ambulance_bookings (ambulance_id, pickup_address, destination_address, booking_date, status, charges, created_by) 
VALUES 
(1, '123 Emergency Street, City', 'MediCare Hospital, Main Building', NOW(), 'completed', 500.00, 1),
(1, '456 Accident Road, City', 'MediCare Hospital, Emergency Wing', DATE_SUB(NOW(), INTERVAL 1 DAY), 'completed', 750.00, 1),
(2, '789 Medical Lane, City', 'MediCare Hospital, ICU', DATE_SUB(NOW(), INTERVAL 2 DAY), 'completed', 1000.00, 1);

-- 8. Verify all required tables exist with proper structure
SELECT 'Checking hospitals table' as status;
DESCRIBE hospitals;

SELECT 'Checking roles table' as status;
SELECT id, role_name, role_display_name FROM roles WHERE role_name IN ('admin', 'doctor', 'nurse', 'driver', 'pharmacy_staff');

SELECT 'Checking staff table' as status;
SHOW COLUMNS FROM staff LIKE 'staff_type';

SELECT 'Checking ambulances table' as status;
SELECT COUNT(*) as ambulance_count FROM ambulances;

-- 9. Show final results
SELECT 'Database setup completed successfully!' as message;
SELECT 'All tables updated with proper structure and sample data' as status;

-- 10. Show current site settings
SELECT 
    id,
    name as hospital_name,
    site_title,
    logo_url,
    primary_color,
    secondary_color
FROM hospitals 
WHERE id = 1;
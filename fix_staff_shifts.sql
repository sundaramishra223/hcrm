-- Fix Staff Shift Timing Issues
-- Add proper shift management system

-- 1. Add shift_id column to staff table if missing
ALTER TABLE staff 
ADD COLUMN IF NOT EXISTS shift_id INT(11) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS shift_timing VARCHAR(50) DEFAULT NULL;

-- 2. Add foreign key constraint for shift_id
ALTER TABLE staff 
ADD CONSTRAINT fk_staff_shift 
FOREIGN KEY (shift_id) REFERENCES shifts(id) 
ON DELETE SET NULL;

-- 3. Ensure shifts table has proper data
INSERT IGNORE INTO shifts (id, hospital_id, shift_name, start_time, end_time, is_active) VALUES
(1, 1, 'Morning Shift', '08:00:00', '16:00:00', 1),
(2, 1, 'Evening Shift', '16:00:00', '00:00:00', 1),
(3, 1, 'Night Shift', '00:00:00', '08:00:00', 1),
(4, 1, 'Day Shift (12 Hours)', '09:00:00', '21:00:00', 1),
(5, 1, 'Night Shift (12 Hours)', '21:00:00', '09:00:00', 1);

-- 4. Update existing staff with default shift
UPDATE staff 
SET shift_id = 1, shift_timing = '08:00 - 16:00' 
WHERE shift_id IS NULL;

-- 5. Add shift management permissions
INSERT IGNORE INTO roles (id, role_name, role_display_name, description) VALUES
(13, 'shift_manager', 'Shift Manager', 'Manages staff shifts and schedules');

-- 6. Create shift templates table for different staff types
CREATE TABLE IF NOT EXISTS shift_templates (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT(11) NOT NULL DEFAULT 1,
    template_name VARCHAR(100) NOT NULL,
    staff_type ENUM('nurse','receptionist','lab_technician','pharmacy_staff','intern_nurse','intern_lab','intern_pharmacy','driver','doctor') NOT NULL,
    default_shift_id INT(11) NOT NULL,
    hours_per_week INT(11) DEFAULT 40,
    break_duration_minutes INT(11) DEFAULT 60,
    overtime_rate DECIMAL(5,2) DEFAULT 1.50,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id),
    FOREIGN KEY (default_shift_id) REFERENCES shifts(id)
);

-- 7. Insert default shift templates
INSERT IGNORE INTO shift_templates (template_name, staff_type, default_shift_id, hours_per_week) VALUES
('Nurse - Morning', 'nurse', 1, 40),
('Nurse - Evening', 'nurse', 2, 40),
('Nurse - Night', 'nurse', 3, 40),
('Receptionist - Day', 'receptionist', 1, 40),
('Lab Tech - Morning', 'lab_technician', 1, 40),
('Lab Tech - Evening', 'lab_technician', 2, 40),
('Pharmacy - Day', 'pharmacy_staff', 1, 40),
('Driver - Day', 'driver', 1, 40),
('Driver - Night', 'driver', 3, 40);

-- 8. Create staff schedule table for weekly schedules
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

-- 9. Show current structure
SELECT 'Staff shift management tables created successfully!' as message;
DESCRIBE staff;
DESCRIBE shifts;
DESCRIBE shift_templates;
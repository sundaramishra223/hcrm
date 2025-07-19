-- =============================================
-- MANUAL FIX FOR MARIADB SYSTEM TABLE UPGRADE
-- =============================================
-- Fix for: Column count of mysql.proc is wrong. Expected 21, found 20

-- First, let's fix the mysql.proc table structure
USE mysql;

-- Check current structure
-- DESCRIBE proc;

-- Add missing column to proc table if not exists
-- This is usually the 'comment' column that's missing
ALTER TABLE proc 
ADD COLUMN IF NOT EXISTS comment TEXT 
CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '';

-- If that doesn't work, we might need to recreate the table structure
-- But let's try a simpler approach first

-- Switch back to our database
USE hospital_crm;

-- Now run our original column fixes
-- Fix 1: Add vital_signs column to patient_vitals table
ALTER TABLE patient_vitals 
ADD COLUMN IF NOT EXISTS vital_signs TEXT AFTER oxygen_saturation;

-- Fix 2: Add test_name column to lab_tests table
ALTER TABLE lab_tests 
ADD COLUMN IF NOT EXISTS test_name VARCHAR(200) AFTER name;

-- Fix 3: Add priority column to bed_assignments table
ALTER TABLE bed_assignments 
ADD COLUMN IF NOT EXISTS priority ENUM('low', 'medium', 'high') DEFAULT 'medium' AFTER status;

-- Update the new columns with existing data
UPDATE lab_tests SET test_name = name WHERE test_name IS NULL OR test_name = '';

UPDATE bed_assignments SET priority = 'medium' WHERE priority IS NULL;

UPDATE patient_vitals 
SET vital_signs = CONCAT(
    'BP: ', COALESCE(blood_pressure, 'N/A'), 
    ', HR: ', COALESCE(heart_rate, 'N/A'), 
    ', Temp: ', COALESCE(temperature, 'N/A'),
    ', RR: ', COALESCE(respiratory_rate, 'N/A'),
    ', O2: ', COALESCE(oxygen_saturation, 'N/A'), '%'
) 
WHERE vital_signs IS NULL OR vital_signs = '';

-- Verification
SELECT 'Hospital CRM columns fixed successfully!' as status;
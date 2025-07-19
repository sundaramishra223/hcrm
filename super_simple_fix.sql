-- =============================================
-- SUPER SIMPLE COLUMN FIX - NO SYSTEM TABLES
-- =============================================
-- This uses basic SQL only, no permission issues

USE hospital_crm;

-- Method: Try to add columns, ignore errors if they exist
-- MySQL will give error if column exists, but won't break

-- Fix 1: Add vital_signs column
-- If column exists, this will give error but continue
ALTER TABLE patient_vitals ADD COLUMN vital_signs TEXT;

-- Fix 2: Add test_name column  
-- If column exists, this will give error but continue
ALTER TABLE lab_tests ADD COLUMN test_name VARCHAR(200);

-- Fix 3: Add priority column
-- If column exists, this will give error but continue  
ALTER TABLE bed_assignments ADD COLUMN priority ENUM('low', 'medium', 'high') DEFAULT 'medium';

-- Now update data (these won't fail even if columns existed)
-- Copy name to test_name
UPDATE lab_tests SET test_name = name WHERE test_name IS NULL OR test_name = '';

-- Set default priority
UPDATE bed_assignments SET priority = 'medium' WHERE priority IS NULL OR priority = '';

-- Create vital signs summary
UPDATE patient_vitals 
SET vital_signs = CONCAT(
    'BP: ', COALESCE(blood_pressure, 'N/A'), 
    ', HR: ', COALESCE(heart_rate, 'N/A'), 
    ', Temp: ', COALESCE(temperature, 'N/A')
)
WHERE vital_signs IS NULL OR vital_signs = '';

-- Show success message
SELECT 'Column fix completed! Some errors above are normal if columns already existed.' as message;
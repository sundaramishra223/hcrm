-- =============================================
-- FIX MISSING COLUMNS ONLY
-- =============================================
-- Run this to fix only the column errors (no data insert)

USE hospital_crm;

-- Fix 1: Add vital_signs column to patient_vitals table (for backward compatibility)
-- This will store JSON format vitals or computed vitals string
ALTER TABLE patient_vitals 
ADD COLUMN vital_signs TEXT AFTER oxygen_saturation;

-- Fix 2: Add test_name column to lab_tests table (for backward compatibility) 
-- This will be same as name column
ALTER TABLE lab_tests 
ADD COLUMN test_name VARCHAR(200) AFTER name;

-- Fix 3: Add priority column to bed_assignments table
ALTER TABLE bed_assignments 
ADD COLUMN priority ENUM('low', 'medium', 'high') DEFAULT 'medium' AFTER status;

-- Update the new columns with existing data
-- Copy name to test_name in lab_tests
UPDATE lab_tests SET test_name = name WHERE test_name IS NULL;

-- Set default priority for existing bed assignments
UPDATE bed_assignments SET priority = 'medium' WHERE priority IS NULL;

-- Create computed vital_signs for existing records
UPDATE patient_vitals 
SET vital_signs = CONCAT(
    'BP: ', COALESCE(blood_pressure, 'N/A'), 
    ', HR: ', COALESCE(heart_rate, 'N/A'), 
    ', Temp: ', COALESCE(temperature, 'N/A'),
    ', RR: ', COALESCE(respiratory_rate, 'N/A'),
    ', O2: ', COALESCE(oxygen_saturation, 'N/A'), '%'
) 
WHERE vital_signs IS NULL;

-- Show tables structure to verify
DESCRIBE patient_vitals;
DESCRIBE lab_tests;
DESCRIBE bed_assignments;

-- Verification
SELECT 'Missing columns fixed!' as status;
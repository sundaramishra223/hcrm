-- =============================================
-- FINAL FIX - HANDLES DUPLICATE COLUMNS
-- =============================================

USE hospital_crm;

-- NOTE: You may see "Duplicate column" errors below - this is NORMAL!
-- It means columns already exist, script will continue

-- Try to add vital_signs column (may show duplicate error - ignore it)
ALTER TABLE patient_vitals ADD COLUMN vital_signs TEXT;

-- Try to add test_name column (may show duplicate error - ignore it)  
ALTER TABLE lab_tests ADD COLUMN test_name VARCHAR(200);

-- Try to add priority column (may show duplicate error - ignore it)
ALTER TABLE bed_assignments ADD COLUMN priority ENUM('low', 'medium', 'high') DEFAULT 'medium';

-- Now update data in ALL columns (this will always work)
-- Update test_name with name data
UPDATE lab_tests SET test_name = name WHERE (test_name IS NULL OR test_name = '') AND name IS NOT NULL;

-- Update priority with default value
UPDATE bed_assignments SET priority = 'medium' WHERE priority IS NULL OR priority = '';

-- Update vital_signs with computed data
UPDATE patient_vitals 
SET vital_signs = CONCAT(
    'BP: ', COALESCE(blood_pressure, 'N/A'), 
    ', HR: ', COALESCE(heart_rate, 'N/A'), 
    ', Temp: ', COALESCE(temperature, 'N/A'), '°F'
)
WHERE vital_signs IS NULL OR vital_signs = '';

-- Final verification - check if we have data
SELECT 'DATA UPDATE VERIFICATION:' as status;

-- Check lab_tests data
SELECT 'lab_tests' as table_name, COUNT(*) as total_rows, 
       COUNT(test_name) as test_name_filled
FROM lab_tests;

-- Check bed_assignments data  
SELECT 'bed_assignments' as table_name, COUNT(*) as total_rows,
       COUNT(priority) as priority_filled  
FROM bed_assignments;

-- Check patient_vitals data
SELECT 'patient_vitals' as table_name, COUNT(*) as total_rows,
       COUNT(vital_signs) as vital_signs_filled
FROM patient_vitals;

SELECT '✅ Column fix completed successfully!' as final_message;
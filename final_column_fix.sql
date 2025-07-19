-- =============================================
-- FINAL SIMPLE COLUMN FIX - HOSPITAL CRM ONLY
-- =============================================
-- This ONLY touches hospital_crm database, no system tables

USE hospital_crm;

-- Check what columns we actually need to add
SELECT 
    'Before Fix - Current Columns' as status,
    TABLE_NAME, 
    COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'hospital_crm' 
AND TABLE_NAME IN ('patient_vitals', 'lab_tests', 'bed_assignments')
AND COLUMN_NAME IN ('vital_signs', 'test_name', 'priority');

-- Add vital_signs column if missing
-- Method: Try to add, ignore error if exists
SET @sql = CONCAT('ALTER TABLE patient_vitals ADD COLUMN vital_signs TEXT');
SET @error = 0;

-- Check if vital_signs column exists
SELECT COUNT(*) INTO @col_count 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'hospital_crm' 
AND TABLE_NAME = 'patient_vitals' 
AND COLUMN_NAME = 'vital_signs';

-- Add column only if it doesn't exist
SET @sql = IF(@col_count = 0, 
    'ALTER TABLE patient_vitals ADD COLUMN vital_signs TEXT', 
    'SELECT "vital_signs already exists" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add test_name column if missing
SELECT COUNT(*) INTO @col_count 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'hospital_crm' 
AND TABLE_NAME = 'lab_tests' 
AND COLUMN_NAME = 'test_name';

SET @sql = IF(@col_count = 0, 
    'ALTER TABLE lab_tests ADD COLUMN test_name VARCHAR(200)', 
    'SELECT "test_name already exists" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add priority column if missing
SELECT COUNT(*) INTO @col_count 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'hospital_crm' 
AND TABLE_NAME = 'bed_assignments' 
AND COLUMN_NAME = 'priority';

SET @sql = IF(@col_count = 0, 
    'ALTER TABLE bed_assignments ADD COLUMN priority ENUM("low", "medium", "high") DEFAULT "medium"', 
    'SELECT "priority already exists" as message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update data in new columns
UPDATE lab_tests SET test_name = name WHERE test_name IS NULL OR test_name = '';

UPDATE bed_assignments SET priority = 'medium' WHERE priority IS NULL OR priority = '';

UPDATE patient_vitals 
SET vital_signs = CONCAT(
    'BP: ', COALESCE(blood_pressure, 'N/A'), 
    ', HR: ', COALESCE(heart_rate, 'N/A'), 
    ', Temp: ', COALESCE(temperature, 'N/A')
)
WHERE vital_signs IS NULL OR vital_signs = '';

-- Final verification
SELECT 
    'After Fix - Added Columns' as status,
    TABLE_NAME, 
    COLUMN_NAME,
    DATA_TYPE
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'hospital_crm' 
AND COLUMN_NAME IN ('vital_signs', 'test_name', 'priority')
ORDER BY TABLE_NAME, COLUMN_NAME;

SELECT 'All columns fixed successfully!' as final_status;
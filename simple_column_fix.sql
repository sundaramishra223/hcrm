-- =============================================
-- SIMPLE COLUMN FIX (AVOID SYSTEM TABLE ISSUES)
-- =============================================
-- This approach skips the system table upgrade and just fixes our app

USE hospital_crm;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Fix missing columns one by one with error handling
-- Fix 1: vital_signs column
SET @sql = 'ALTER TABLE patient_vitals ADD COLUMN vital_signs TEXT';
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'hospital_crm' 
                   AND TABLE_NAME = 'patient_vitals' 
                   AND COLUMN_NAME = 'vital_signs');

SET @sql = IF(@col_exists = 0, @sql, 'SELECT "vital_signs column already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fix 2: test_name column  
SET @sql = 'ALTER TABLE lab_tests ADD COLUMN test_name VARCHAR(200)';
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'hospital_crm' 
                   AND TABLE_NAME = 'lab_tests' 
                   AND COLUMN_NAME = 'test_name');

SET @sql = IF(@col_exists = 0, @sql, 'SELECT "test_name column already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fix 3: priority column
SET @sql = 'ALTER TABLE bed_assignments ADD COLUMN priority ENUM("low", "medium", "high") DEFAULT "medium"';
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = 'hospital_crm' 
                   AND TABLE_NAME = 'bed_assignments' 
                   AND COLUMN_NAME = 'priority');

SET @sql = IF(@col_exists = 0, @sql, 'SELECT "priority column already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Update data only if columns exist and are empty
UPDATE lab_tests SET test_name = name WHERE test_name IS NULL OR test_name = '';
UPDATE bed_assignments SET priority = 'medium' WHERE priority IS NULL;
UPDATE patient_vitals 
SET vital_signs = CONCAT('BP: ', COALESCE(blood_pressure, 'N/A'), ', HR: ', COALESCE(heart_rate, 'N/A'), ', Temp: ', COALESCE(temperature, 'N/A'))
WHERE vital_signs IS NULL OR vital_signs = '';

-- Final verification
SELECT 
    TABLE_NAME, 
    COLUMN_NAME, 
    'Column exists!' as status 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'hospital_crm' 
AND COLUMN_NAME IN ('vital_signs', 'test_name', 'priority')
ORDER BY TABLE_NAME, COLUMN_NAME;
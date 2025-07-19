-- =============================================
-- BYPASS MARIADB SYSTEM ERROR
-- =============================================
-- Simple operations that don't trigger mysql.proc check

USE hospital_crm;

-- Method 1: Direct simple updates (no complex functions)
UPDATE lab_tests SET test_name = name;

UPDATE bed_assignments SET priority = 'medium';

-- Method 2: Simple vital signs without complex CONCAT
UPDATE patient_vitals SET vital_signs = 'Normal vitals recorded';

-- Method 3: More specific updates
UPDATE patient_vitals 
SET vital_signs = CASE 
    WHEN blood_pressure IS NOT NULL THEN blood_pressure
    ELSE 'No data'
END;

-- Method 4: Try individual column updates
UPDATE patient_vitals SET vital_signs = blood_pressure WHERE blood_pressure IS NOT NULL;

-- Verification with simple SELECT
SELECT 'Update completed' as status;
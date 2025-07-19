-- =============================================
-- SIMPLE APPOINTMENT TABLE FIX
-- =============================================

USE hospital_crm;

-- Try to add columns (ignore errors if they exist)
-- appointment_number column
ALTER TABLE appointments ADD COLUMN appointment_number VARCHAR(50) UNIQUE;

-- duration_minutes column  
ALTER TABLE appointments ADD COLUMN duration_minutes INT DEFAULT 30;

-- chief_complaint column
ALTER TABLE appointments ADD COLUMN chief_complaint TEXT;

-- consultation_fee column
ALTER TABLE appointments ADD COLUMN consultation_fee DECIMAL(10,2) DEFAULT 0;

-- Update existing appointments with generated numbers (only if empty)
UPDATE appointments 
SET appointment_number = CONCAT('APT', DATE_FORMAT(COALESCE(appointment_date, CURDATE()), '%Y%m%d'), LPAD(id, 4, '0'))
WHERE appointment_number IS NULL OR appointment_number = '';

-- Update existing appointments with default values
UPDATE appointments SET duration_minutes = 30 WHERE duration_minutes IS NULL;
UPDATE appointments SET consultation_fee = 500 WHERE consultation_fee IS NULL OR consultation_fee = 0;

-- Update chief_complaint with default
UPDATE appointments SET chief_complaint = 'General consultation' WHERE chief_complaint IS NULL OR chief_complaint = '';

SELECT 'Appointment table fixed! Some duplicate column errors above are normal.' as message;
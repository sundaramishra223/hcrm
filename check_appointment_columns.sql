-- =============================================
-- CHECK APPOINTMENT TABLE COLUMNS
-- =============================================

USE hospital_crm;

-- Show current appointments table structure
DESCRIBE appointments;

-- Show a sample appointment record to see current data
SELECT * FROM appointments LIMIT 1;

-- Count total appointments
SELECT COUNT(*) as total_appointments FROM appointments;
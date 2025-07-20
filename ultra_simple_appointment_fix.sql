USE hospital_crm;

ALTER TABLE appointments ADD COLUMN appointment_number VARCHAR(50);
ALTER TABLE appointments ADD COLUMN duration_minutes INT DEFAULT 30;
ALTER TABLE appointments ADD COLUMN chief_complaint TEXT;
ALTER TABLE appointments ADD COLUMN consultation_fee DECIMAL(10,2) DEFAULT 0;

UPDATE appointments SET appointment_number = CONCAT('APT', id) WHERE appointment_number IS NULL;
UPDATE appointments SET duration_minutes = 30 WHERE duration_minutes IS NULL;
UPDATE appointments SET consultation_fee = 500 WHERE consultation_fee = 0;

SELECT 'Done!' as result;
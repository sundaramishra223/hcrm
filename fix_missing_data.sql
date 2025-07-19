-- =============================================
-- ESSENTIAL MISSING DATA FOR HOSPITAL CRM
-- =============================================
-- Run this SQL to add essential data only

USE hospital_crm;

-- Insert sample departments if empty
INSERT IGNORE INTO departments (id, hospital_id, name, code, description, is_active) VALUES
(1, 1, 'General', 'GEN', 'General Medicine Department', 1),
(2, 1, 'Emergency', 'EMR', 'Emergency Department', 1),
(3, 1, 'ICU', 'ICU', 'Intensive Care Unit', 1),
(4, 1, 'Pediatrics', 'PED', 'Children Healthcare', 1),
(5, 1, 'Cardiology', 'CAR', 'Heart Care Department', 1),
(6, 1, 'Orthopedics', 'ORT', 'Bone and Joint Care', 1),
(7, 1, 'Laboratory', 'LAB', 'Medical Laboratory', 1);

-- Insert sample hospital if empty
INSERT IGNORE INTO hospitals (id, name, code, address, phone, email, primary_color, secondary_color, is_active) VALUES
(1, 'City General Hospital', 'CGH', '123 Main Street, City', '+1-234-567-8900', 'info@cityhospital.com', '#004685', '#0066cc', 1);

-- Insert sample beds if empty
INSERT IGNORE INTO beds (hospital_id, bed_number, bed_type, room_number, floor_number, department_id, status, daily_rate) VALUES
(1, 'B001', 'general', 'R101', 1, 1, 'available', 500.00),
(2, 'B002', 'general', 'R102', 1, 1, 'available', 500.00),
(3, 'B003', 'icu', 'ICU01', 2, 3, 'available', 2000.00),
(4, 'B004', 'icu', 'ICU02', 2, 3, 'available', 2000.00),
(5, 'B005', 'private', 'R201', 2, 1, 'available', 1500.00);

-- Insert basic lab tests if empty
INSERT IGNORE INTO lab_tests (name, category, cost, normal_range, unit, preparation_instructions) VALUES
('Complete Blood Count', 'Hematology', 300.00, '4.5-11.0 x10^9/L', 'cells/L', 'No special preparation required'),
('Blood Sugar (Fasting)', 'Biochemistry', 150.00, '70-100 mg/dL', 'mg/dL', 'Fasting for 8-12 hours required'),
('Lipid Profile', 'Biochemistry', 500.00, 'Total: <200 mg/dL', 'mg/dL', 'Fasting for 12 hours required'),
('Kidney Function', 'Biochemistry', 400.00, 'Creatinine: 0.6-1.2 mg/dL', 'mg/dL', 'No special preparation'),
('Liver Function', 'Biochemistry', 450.00, 'ALT: 7-56 U/L', 'U/L', 'No special preparation');

-- Insert basic equipment if empty
INSERT IGNORE INTO equipment (hospital_id, name, category, model, serial_number, manufacturer, purchase_date, status, location, cost) VALUES
(1, 'MRI Machine', 'Imaging', 'Siemens MAGNETOM', 'MRI001', 'Siemens', '2023-01-15', 'operational', 'Radiology Department', 2500000.00),
(2, 'CT Scanner', 'Imaging', 'GE Revolution', 'CT001', 'General Electric', '2022-06-10', 'operational', 'Radiology Department', 1800000.00),
(3, 'Ventilator', 'Life Support', 'Medtronic PB980', 'VENT001', 'Medtronic', '2023-03-20', 'operational', 'ICU', 150000.00),
(4, 'X-Ray Machine', 'Imaging', 'Carestream DRX', 'XRAY001', 'Carestream', '2022-09-15', 'operational', 'Radiology Department', 450000.00);

-- Add sample patient vitals for testing
INSERT IGNORE INTO patient_vitals (patient_id, recorded_by, recorded_by_type, temperature, blood_pressure, heart_rate, respiratory_rate, weight, height, oxygen_saturation, notes) VALUES
(1, 1, 'nurse', 98.6, '120/80', 72, 16, 70.5, 175.0, 98.5, 'Normal vitals recorded during routine check'),
(1, 1, 'nurse', 99.1, '125/85', 78, 18, 70.2, 175.0, 97.8, 'Slightly elevated temperature');

-- Verification query
SELECT 'Departments' as table_name, COUNT(*) as count FROM departments
UNION ALL
SELECT 'Hospitals' as table_name, COUNT(*) as count FROM hospitals  
UNION ALL
SELECT 'Beds' as table_name, COUNT(*) as count FROM beds
UNION ALL
SELECT 'Lab Tests' as table_name, COUNT(*) as count FROM lab_tests
UNION ALL
SELECT 'Equipment' as table_name, COUNT(*) as count FROM equipment
UNION ALL
SELECT 'Patient Vitals' as table_name, COUNT(*) as count FROM patient_vitals;
-- Check Existing Demo Users
-- Run this before creating new users

SELECT 'EXISTING DEMO USERS:' as info;

SELECT 
    u.id,
    u.username,
    u.email,
    r.role_display_name as role,
    u.is_active,
    u.created_at
FROM users u
LEFT JOIN roles r ON u.role_id = r.id
WHERE u.email IN (
    'admin@hospital.com',
    'dr.sharma@hospital.com', 
    'demo@patient.com',
    'pharmacy@demo.com',
    'lab@demo.com',
    'reception@demo.com',
    'nurse@demo.com',
    'priya.nurse@hospital.com',
    'patient.demo@hospital.com',
    'doctor.demo@hospital.com'
)
ORDER BY u.id;

-- Check staff records
SELECT 'EXISTING STAFF RECORDS:' as info;

SELECT 
    s.id,
    s.employee_id,
    CONCAT(s.first_name, ' ', s.last_name) as name,
    s.staff_type,
    u.email
FROM staff s
LEFT JOIN users u ON s.user_id = u.id
WHERE u.email IN (
    'pharmacy@demo.com',
    'lab@demo.com',
    'reception@demo.com',
    'nurse@demo.com'
);

-- Check doctors
SELECT 'EXISTING DOCTORS:' as info;

SELECT 
    d.id,
    d.employee_id,
    CONCAT(d.first_name, ' ', d.last_name) as name,
    d.specialization,
    u.email
FROM doctors d
LEFT JOIN users u ON d.user_id = u.id
WHERE u.email = 'dr.sharma@hospital.com';

-- Check patients  
SELECT 'EXISTING PATIENTS:' as info;

SELECT 
    p.id,
    p.patient_id,
    CONCAT(p.first_name, ' ', p.last_name) as name,
    u.email
FROM patients p
LEFT JOIN users u ON p.user_id = u.id
WHERE u.email = 'demo@patient.com';
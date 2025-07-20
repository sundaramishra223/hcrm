-- Quick Password Update for Demo Users
-- Password: 5und@r@M

SET @password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- Update existing demo users passwords
UPDATE users SET password_hash = @password_hash WHERE email IN (
    'admin@hospital.com',
    'dr.sharma@hospital.com', 
    'demo@patient.com',
    'pharmacy@demo.com',
    'lab@demo.com',
    'reception@hospital.com',
    'nurse@demo.com',
    'priya.nurse@hospital.com',
    'patient.demo@hospital.com',
    'doctor.demo@hospital.com'
);

-- Show updated users
SELECT 
    username,
    email,
    'Password updated to: 5und@r@M' as status
FROM users 
WHERE email IN (
    'admin@hospital.com',
    'dr.sharma@hospital.com', 
    'demo@patient.com',
    'pharmacy@demo.com',
    'lab@demo.com',
    'reception@hospital.com',
    'nurse@demo.com',
    'priya.nurse@hospital.com',
    'patient.demo@hospital.com',
    'doctor.demo@hospital.com'
);

SELECT 'All demo user passwords updated to: 5und@r@M' as message;
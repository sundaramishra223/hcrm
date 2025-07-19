-- =============================================
-- ADD MISSING COLUMNS TO USERS TABLE
-- =============================================

USE hospital_crm;

-- Add missing name columns to users table
ALTER TABLE users ADD COLUMN first_name VARCHAR(50) AFTER email;
ALTER TABLE users ADD COLUMN last_name VARCHAR(50) AFTER first_name;

-- Update existing users with default names based on username
UPDATE users 
SET first_name = SUBSTRING_INDEX(username, '_', 1),
    last_name = CASE 
        WHEN username LIKE '%_%' THEN SUBSTRING_INDEX(username, '_', -1)
        ELSE 'User'
    END
WHERE first_name IS NULL OR first_name = '';

-- For admin user, set proper name
UPDATE users SET first_name = 'Admin', last_name = 'User' WHERE username = 'admin';

-- For demo_patient, set proper name  
UPDATE users SET first_name = 'Demo', last_name = 'Patient' WHERE username = 'demo_patient';

-- Verification
SELECT id, username, first_name, last_name, email FROM users LIMIT 5;

SELECT 'Users table updated with name columns!' as message;
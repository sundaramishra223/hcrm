-- Add password column to patients table
ALTER TABLE patients ADD COLUMN password VARCHAR(255) AFTER emergency_contact_relation;

-- Update existing patients with a default password (they can change it later)
UPDATE patients SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE password IS NULL;
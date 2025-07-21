-- Most Basic Hospital Settings Setup
-- Copy-paste these commands one by one if needed

-- Step 1: Add columns
ALTER TABLE hospitals ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL;
ALTER TABLE hospitals ADD COLUMN favicon_url VARCHAR(255) DEFAULT NULL;
ALTER TABLE hospitals ADD COLUMN primary_color VARCHAR(7) DEFAULT '#2563eb';
ALTER TABLE hospitals ADD COLUMN secondary_color VARCHAR(7) DEFAULT '#10b981';
ALTER TABLE hospitals ADD COLUMN site_title VARCHAR(255) DEFAULT NULL;

-- Step 2: Check if hospital record exists
SELECT * FROM hospitals WHERE id = 1;

-- Step 3: Insert if no record exists
INSERT IGNORE INTO hospitals (id, name, primary_color, secondary_color, site_title) 
VALUES (1, 'MediCare Hospital', '#2563eb', '#10b981', 'MediCare Hospital - Advanced Healthcare Management');

-- Step 4: Update settings
UPDATE hospitals 
SET 
    logo_url = 'assets/images/logo.svg',
    primary_color = '#2563eb',
    secondary_color = '#10b981',
    site_title = 'MediCare Hospital - Advanced Healthcare Management'
WHERE id = 1;

-- Step 5: Verify
SELECT id, name, logo_url, primary_color, secondary_color, site_title FROM hospitals WHERE id = 1;
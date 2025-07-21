-- Add site customization settings to hospitals table
-- Fixed version for MariaDB/MySQL compatibility

-- Add logo_url column if it doesn't exist
SET @sql = (SELECT IF(
    EXISTS(
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'hospitals' 
        AND COLUMN_NAME = 'logo_url'
    ),
    'SELECT "logo_url column already exists" as status',
    'ALTER TABLE hospitals ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add favicon_url column if it doesn't exist
SET @sql = (SELECT IF(
    EXISTS(
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'hospitals' 
        AND COLUMN_NAME = 'favicon_url'
    ),
    'SELECT "favicon_url column already exists" as status',
    'ALTER TABLE hospitals ADD COLUMN favicon_url VARCHAR(255) DEFAULT NULL'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add primary_color column if it doesn't exist
SET @sql = (SELECT IF(
    EXISTS(
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'hospitals' 
        AND COLUMN_NAME = 'primary_color'
    ),
    'SELECT "primary_color column already exists" as status',
    'ALTER TABLE hospitals ADD COLUMN primary_color VARCHAR(7) DEFAULT "#2563eb"'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add secondary_color column if it doesn't exist
SET @sql = (SELECT IF(
    EXISTS(
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'hospitals' 
        AND COLUMN_NAME = 'secondary_color'
    ),
    'SELECT "secondary_color column already exists" as status',
    'ALTER TABLE hospitals ADD COLUMN secondary_color VARCHAR(7) DEFAULT "#10b981"'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add site_title column if it doesn't exist
SET @sql = (SELECT IF(
    EXISTS(
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'hospitals' 
        AND COLUMN_NAME = 'site_title'
    ),
    'SELECT "site_title column already exists" as status',
    'ALTER TABLE hospitals ADD COLUMN site_title VARCHAR(255) DEFAULT NULL'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update default hospital with sample settings
UPDATE hospitals 
SET 
    logo_url = 'assets/images/logo.svg',
    favicon_url = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232563eb"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM12 17h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
    primary_color = '#2563eb',
    secondary_color = '#10b981',
    site_title = 'MediCare Hospital - Advanced Healthcare Management'
WHERE id = 1;

-- Insert default hospital if it doesn't exist
INSERT IGNORE INTO hospitals (id, name, logo_url, favicon_url, primary_color, secondary_color, site_title, created_at, updated_at) 
VALUES (
    1, 
    'MediCare Hospital',
    'assets/images/logo.svg',
    'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232563eb"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM12 17h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
    '#2563eb',
    '#10b981',
    'MediCare Hospital - Advanced Healthcare Management',
    NOW(),
    NOW()
);

SELECT 'Hospital settings columns added and configured successfully! ✅' as completion_message;
SELECT 'You can now customize your site from Admin > Site Settings' as next_step;
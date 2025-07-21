-- Add site customization settings to hospitals table
-- This allows admin to customize site title, logo, colors, etc.

-- Check if columns exist and add them safely
SET @sql = '';

-- Add logo_url column
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'hospitals' 
AND COLUMN_NAME = 'logo_url';

IF @col_exists = 0 THEN
    SET @sql = CONCAT(@sql, 'ALTER TABLE hospitals ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL; ');
END IF;

-- Add favicon_url column
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'hospitals' 
AND COLUMN_NAME = 'favicon_url';

IF @col_exists = 0 THEN
    SET @sql = CONCAT(@sql, 'ALTER TABLE hospitals ADD COLUMN favicon_url VARCHAR(255) DEFAULT NULL; ');
END IF;

-- Add primary_color column
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'hospitals' 
AND COLUMN_NAME = 'primary_color';

IF @col_exists = 0 THEN
    SET @sql = CONCAT(@sql, 'ALTER TABLE hospitals ADD COLUMN primary_color VARCHAR(7) DEFAULT "#2563eb"; ');
END IF;

-- Add secondary_color column
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'hospitals' 
AND COLUMN_NAME = 'secondary_color';

IF @col_exists = 0 THEN
    SET @sql = CONCAT(@sql, 'ALTER TABLE hospitals ADD COLUMN secondary_color VARCHAR(7) DEFAULT "#10b981"; ');
END IF;

-- Add site_title column
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'hospitals' 
AND COLUMN_NAME = 'site_title';

IF @col_exists = 0 THEN
    SET @sql = CONCAT(@sql, 'ALTER TABLE hospitals ADD COLUMN site_title VARCHAR(255) DEFAULT NULL; ');
END IF;

-- Execute the SQL if any columns need to be added
IF LENGTH(@sql) > 0 THEN
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    SELECT 'Hospital settings columns added successfully!' as message;
ELSE
    SELECT 'All hospital settings columns already exist!' as message;
END IF;

-- Update default hospital with sample settings
UPDATE hospitals 
SET 
    logo_url = 'assets/images/logo.svg',
    favicon_url = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232563eb"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM12 17h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
    primary_color = '#2563eb',
    secondary_color = '#10b981',
    site_title = 'MediCare Hospital - Advanced Healthcare Management'
WHERE id = 1;

-- Create site settings management view for admin
CREATE OR REPLACE VIEW site_settings AS
SELECT 
    id,
    name as hospital_name,
    site_title,
    logo_url,
    favicon_url,
    primary_color,
    secondary_color,
    phone,
    email,
    address,
    created_at,
    updated_at
FROM hospitals
WHERE id = 1;

SELECT 'Site settings updated successfully! Logo, favicon, and colors are now customizable.' as completion_message;
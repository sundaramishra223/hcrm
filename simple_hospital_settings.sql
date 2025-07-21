-- Simple Hospital Settings Setup (MariaDB/MySQL Compatible)

-- Add columns (ignore errors if they already exist)
ALTER TABLE hospitals ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL;
ALTER TABLE hospitals ADD COLUMN favicon_url VARCHAR(255) DEFAULT NULL;
ALTER TABLE hospitals ADD COLUMN primary_color VARCHAR(7) DEFAULT '#2563eb';
ALTER TABLE hospitals ADD COLUMN secondary_color VARCHAR(7) DEFAULT '#10b981';
ALTER TABLE hospitals ADD COLUMN site_title VARCHAR(255) DEFAULT NULL;

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

-- Update existing hospital settings
UPDATE hospitals 
SET 
    logo_url = 'assets/images/logo.svg',
    favicon_url = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232563eb"><path d="M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM12 17h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>',
    primary_color = '#2563eb',
    secondary_color = '#10b981',
    site_title = 'MediCare Hospital - Advanced Healthcare Management'
WHERE id = 1;

-- Show success message
SELECT 'Hospital settings configured successfully! ✅' as message;
SELECT 'Go to Admin > Site Settings to customize your site' as next_step;
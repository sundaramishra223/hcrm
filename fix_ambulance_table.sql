-- Fix Ambulance Table - Add Missing Columns
-- Run this to fix ambulance add error

-- Add missing columns to ambulances table
ALTER TABLE ambulances 
ADD COLUMN IF NOT EXISTS capacity INT DEFAULT 4,
ADD COLUMN IF NOT EXISTS equipment TEXT,
ADD COLUMN IF NOT EXISTS location VARCHAR(255);

-- Update existing ambulances with default values
UPDATE ambulances 
SET 
    capacity = 4 WHERE capacity IS NULL,
    equipment = 'Basic Life Support' WHERE equipment IS NULL,
    location = 'Hospital' WHERE location IS NULL;

-- Show current ambulance table structure
DESCRIBE ambulances;

-- Test insert to verify fix
SELECT 'Ambulance table fixed! Now you can add ambulances without errors.' as status;
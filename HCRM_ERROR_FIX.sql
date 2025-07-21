-- ===================================================================
-- HCRM ERROR FIX SQL - सिर्फ Missing Tables और Columns
-- Run this on your existing database to fix all CRUD operation errors
-- ===================================================================

USE `hospital_crm`;

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================================
-- 1. CREATE MISSING ROLES TABLE (CRITICAL ERROR FIX)
-- ===================================================================
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `role_display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert missing roles data
INSERT IGNORE INTO `roles` (`id`, `role_name`, `role_display_name`, `description`) VALUES
(1, 'admin', 'Administrator', 'Full system access'),
(2, 'doctor', 'Doctor', 'Medical practitioner'),
(3, 'nurse', 'Nurse', 'Nursing staff'),
(4, 'patient', 'Patient', 'Hospital patient'),
(5, 'receptionist', 'Receptionist', 'Front desk staff'),
(6, 'pharmacy_staff', 'Pharmacy Staff', 'Pharmacy personnel'),
(7, 'lab_technician', 'Lab Technician', 'Laboratory personnel'),
(8, 'staff', 'General Staff', 'General hospital staff'),
(9, 'intern_doctor', 'Intern Doctor', 'Medical intern'),
(10, 'intern_nurse', 'Intern Nurse', 'Nursing intern'),
(11, 'intern_lab', 'Lab Intern', 'Laboratory intern'),
(12, 'intern_pharmacy', 'Pharmacy Intern', 'Pharmacy intern'),
(13, 'driver', 'Driver', 'Ambulance driver'),
(14, 'transplant_coordinator', 'Transplant Coordinator', 'Organ transplant coordinator'),
(15, 'surgeon', 'Surgeon', 'Surgical specialist');

-- ===================================================================
-- 2. ADD MISSING COLUMNS TO USERS TABLE (CRITICAL ERROR FIX)
-- ===================================================================
-- Add role_id column if it doesn't exist
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `role_id` int(11) DEFAULT NULL AFTER `role`,
ADD COLUMN IF NOT EXISTS `password_hash` varchar(255) DEFAULT NULL AFTER `password`,
ADD KEY IF NOT EXISTS `role_id` (`role_id`);

-- Copy existing role data to role_id
UPDATE `users` SET `role_id` = (
  CASE `role`
    WHEN 'admin' THEN 1
    WHEN 'doctor' THEN 2
    WHEN 'nurse' THEN 3
    WHEN 'patient' THEN 4
    WHEN 'receptionist' THEN 5
    WHEN 'pharmacy_staff' THEN 6
    WHEN 'lab_technician' THEN 7
    WHEN 'staff' THEN 8
    WHEN 'intern_doctor' THEN 9
    WHEN 'intern_nurse' THEN 10
    WHEN 'intern_lab' THEN 11
    WHEN 'intern_pharmacy' THEN 12
    WHEN 'driver' THEN 13
    ELSE 8
  END
) WHERE `role_id` IS NULL;

-- Copy password to password_hash if needed
UPDATE `users` SET `password_hash` = `password` WHERE `password_hash` IS NULL AND `password` IS NOT NULL;

-- ===================================================================
-- 3. CREATE MISSING BLOOD_GROUPS TABLE (BLOOD BANK ERROR FIX)
-- ===================================================================
CREATE TABLE IF NOT EXISTS `blood_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blood_group` varchar(10) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `compatibility` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `blood_group` (`blood_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert blood groups data
INSERT IGNORE INTO `blood_groups` (`blood_group`, `description`) VALUES
('A+', 'A Positive'),
('A-', 'A Negative'),
('B+', 'B Positive'),
('B-', 'B Negative'),
('AB+', 'AB Positive'),
('AB-', 'AB Negative'),
('O+', 'O Positive'),
('O-', 'O Negative');

-- ===================================================================
-- 4. ADD MISSING COLUMNS TO BLOOD_DONORS TABLE (BLOOD BANK ERROR FIX)
-- ===================================================================
ALTER TABLE `blood_donors` 
ADD COLUMN IF NOT EXISTS `registered_date` date DEFAULT NULL AFTER `last_donation_date`;

-- Set default registered_date
UPDATE `blood_donors` SET `registered_date` = DATE(`created_at`) WHERE `registered_date` IS NULL;

-- ===================================================================
-- 5. CREATE MISSING ORGAN_TYPES TABLE (ORGAN MANAGEMENT ERROR FIX)
-- ===================================================================
CREATE TABLE IF NOT EXISTS `organ_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organ_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `preservation_time_hours` int(11) DEFAULT NULL,
  `compatibility_criteria` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `organ_name` (`organ_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert organ types data
INSERT IGNORE INTO `organ_types` (`organ_name`, `description`, `preservation_time_hours`) VALUES
('kidney', 'Kidney', 24),
('liver', 'Liver', 12),
('heart', 'Heart', 6),
('lung', 'Lung', 8),
('pancreas', 'Pancreas', 12),
('intestine', 'Small Intestine', 10),
('cornea', 'Cornea', 168),
('skin', 'Skin Tissue', 336),
('bone', 'Bone Tissue', 8760),
('tissue', 'General Tissue', 24);

-- ===================================================================
-- 6. CREATE MISSING ORGAN_DONORS TABLE (ORGAN MANAGEMENT ERROR FIX)
-- ===================================================================
CREATE TABLE IF NOT EXISTS `organ_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `donor_id` varchar(50) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `organ_types_consented` json DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `is_eligible` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `registered_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_id` (`donor_id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- 7. CREATE MISSING ORGAN_MATCHES TABLE (ORGAN MANAGEMENT ERROR FIX)
-- ===================================================================
CREATE TABLE IF NOT EXISTS `organ_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donor_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `organ_type` varchar(100) NOT NULL,
  `compatibility_score` decimal(5,2) DEFAULT NULL,
  `status` enum('potential','confirmed','rejected','completed') DEFAULT 'potential',
  `match_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donor_id` (`donor_id`),
  KEY `recipient_id` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- 8. CREATE MISSING ORGAN_AUDIT_TRAIL TABLE (ORGAN MONITORING ERROR FIX)
-- ===================================================================
CREATE TABLE IF NOT EXISTS `organ_audit_trail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `related_table` varchar(100) NOT NULL,
  `related_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `legal_significance` enum('normal','important','violation','critical') DEFAULT 'normal',
  `details` json DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `related_table` (`related_table`),
  KEY `legal_significance` (`legal_significance`),
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- 9. ADD MISSING COLUMNS TO MEDICINES TABLE (PHARMACY ERROR FIX)
-- ===================================================================
ALTER TABLE `medicines` 
ADD COLUMN IF NOT EXISTS `min_stock_level` int(11) DEFAULT 10 AFTER `reorder_level`,
ADD COLUMN IF NOT EXISTS `category` varchar(100) DEFAULT NULL AFTER `category_id`,
ADD COLUMN IF NOT EXISTS `side_effects` text DEFAULT NULL AFTER `storage_condition`,
ADD COLUMN IF NOT EXISTS `contraindications` text DEFAULT NULL AFTER `side_effects`,
ADD COLUMN IF NOT EXISTS `storage_conditions` varchar(255) DEFAULT NULL AFTER `contraindications`,
ADD COLUMN IF NOT EXISTS `notes` text DEFAULT NULL AFTER `storage_conditions`;

-- ===================================================================
-- 10. ADD MISSING COLUMNS TO BLOOD_INVENTORY TABLE (BLOOD BANK ERROR FIX)
-- ===================================================================
ALTER TABLE `blood_inventory` 
ADD COLUMN IF NOT EXISTS `donor_id` int(11) DEFAULT NULL AFTER `donation_session_id`,
ADD KEY IF NOT EXISTS `donor_id` (`donor_id`);

-- ===================================================================
-- 11. CREATE MISSING BLOOD INVENTORY DASHBOARD VIEW (BLOOD BANK ERROR FIX)
-- ===================================================================
CREATE OR REPLACE VIEW `blood_inventory_dashboard` AS
SELECT 
    `blood_group`,
    `component_type`,
    COUNT(*) as `total_units`,
    SUM(CASE WHEN `status` = 'available' THEN 1 ELSE 0 END) as `available_units`,
    SUM(CASE WHEN `status` = 'reserved' THEN 1 ELSE 0 END) as `reserved_units`,
    SUM(CASE WHEN `status` = 'expired' THEN 1 ELSE 0 END) as `expired_units`,
    SUM(CASE WHEN `expiry_date` <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND `status` = 'available' THEN 1 ELSE 0 END) as `expiring_soon`,
    MIN(`expiry_date`) as `earliest_expiry`,
    MAX(`collection_date`) as `latest_collection`
FROM `blood_inventory`
WHERE `status` IN ('available', 'reserved', 'expired')
GROUP BY `blood_group`, `component_type`
ORDER BY `blood_group`, `component_type`;

-- ===================================================================
-- 12. CREATE INDEXES FOR BETTER PERFORMANCE (PERFORMANCE FIX)
-- ===================================================================
-- Users table indexes
CREATE INDEX IF NOT EXISTS `idx_users_role_id` ON `users` (`role_id`);
CREATE INDEX IF NOT EXISTS `idx_users_email` ON `users` (`email`);
CREATE INDEX IF NOT EXISTS `idx_users_username` ON `users` (`username`);

-- Medicines table indexes
CREATE INDEX IF NOT EXISTS `idx_medicines_stock_level` ON `medicines` (`stock_quantity`, `min_stock_level`);
CREATE INDEX IF NOT EXISTS `idx_medicines_category` ON `medicines` (`category`);
CREATE INDEX IF NOT EXISTS `idx_medicines_expiry` ON `medicines` (`expiry_date`);

-- Blood bank indexes
CREATE INDEX IF NOT EXISTS `idx_blood_donors_registered` ON `blood_donors` (`registered_date`);
CREATE INDEX IF NOT EXISTS `idx_blood_inventory_status` ON `blood_inventory` (`status`, `blood_group`);
CREATE INDEX IF NOT EXISTS `idx_blood_inventory_expiry` ON `blood_inventory` (`expiry_date`, `status`);

-- Organ management indexes
CREATE INDEX IF NOT EXISTS `idx_organ_donors_registered` ON `organ_donors` (`registered_date`);
CREATE INDEX IF NOT EXISTS `idx_organ_matches_status` ON `organ_matches` (`status`);
CREATE INDEX IF NOT EXISTS `idx_organ_audit_timestamp` ON `organ_audit_trail` (`timestamp`);

-- ===================================================================
-- 13. UPDATE DEFAULT VALUES FOR EXISTING DATA (DATA CONSISTENCY FIX)
-- ===================================================================
-- Update medicines with default min_stock_level
UPDATE `medicines` SET `min_stock_level` = 10 WHERE `min_stock_level` IS NULL;

-- Update medicines category from category_id
UPDATE `medicines` m 
LEFT JOIN `medicine_categories` mc ON m.`category_id` = mc.`id` 
SET m.`category` = mc.`category_name` 
WHERE m.`category` IS NULL AND mc.`category_name` IS NOT NULL;

-- ===================================================================
-- 14. ADD FOREIGN KEY CONSTRAINTS (DATA INTEGRITY FIX)
-- ===================================================================
-- Users -> Roles foreign key
ALTER TABLE `users` 
ADD CONSTRAINT `fk_users_role_id` 
FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Blood donors -> Patients foreign key  
ALTER TABLE `blood_donors` 
ADD CONSTRAINT `fk_blood_donors_patient_id` 
FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- Organ donors -> Patients foreign key
ALTER TABLE `organ_donors` 
ADD CONSTRAINT `fk_organ_donors_patient_id` 
FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- ===================================================================
-- 15. FINAL CLEANUP AND VERIFICATION
-- ===================================================================
-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Commit all changes
COMMIT;

-- ===================================================================
-- SUCCESS MESSAGE
-- ===================================================================
SELECT 
    '🎉 HCRM Error Fix Complete!' as Status,
    'All CRUD operations should now work without errors' as Message,
    NOW() as Fixed_At;

-- Show missing tables that were created
SELECT 
    'Created Missing Tables:' as Info,
    'roles, blood_groups, organ_types, organ_donors, organ_matches, organ_audit_trail' as Tables;

-- Show missing columns that were added  
SELECT 
    'Added Missing Columns:' as Info,
    'users.role_id, users.password_hash, medicines.min_stock_level, blood_donors.registered_date' as Columns;

-- ===================================================================
-- END OF ERROR FIX SQL
-- ===================================================================
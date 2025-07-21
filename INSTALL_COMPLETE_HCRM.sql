-- ===================================================================
-- MASTER INSTALLATION SCRIPT FOR COMPLETE HCRM SYSTEM
-- Run this single file to set up the entire Hospital CRM database
-- ===================================================================

-- Create and use database
CREATE DATABASE IF NOT EXISTS `hospital_crm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hospital_crm`;

-- Database configuration
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================================
-- SOURCE ALL PARTS IN CORRECT ORDER
-- ===================================================================

-- Part 1: Core System Tables
SOURCE COMPLETE_HCRM_DATABASE.sql;

-- Part 2: Laboratory, Pharmacy, Blood Bank, Organ Management  
SOURCE COMPLETE_HCRM_DATABASE_PART2.sql;

-- Part 3: Insurance, Equipment, Bed Management
SOURCE COMPLETE_HCRM_DATABASE_PART3.sql;

-- Part 4: Ambulance, Security, Audit, Final Setup
SOURCE COMPLETE_HCRM_DATABASE_FINAL.sql;

-- ===================================================================
-- VERIFY INSTALLATION
-- ===================================================================

-- Show all created tables
SELECT 
    'Installation Complete!' as Status,
    COUNT(*) as Total_Tables,
    GROUP_CONCAT(table_name ORDER BY table_name SEPARATOR ', ') as Created_Tables
FROM information_schema.tables 
WHERE table_schema = 'hospital_crm';

-- Show table sizes
SELECT 
    table_name as Table_Name,
    table_rows as Estimated_Rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as Size_MB
FROM information_schema.tables 
WHERE table_schema = 'hospital_crm'
ORDER BY table_name;

-- Final transaction commit
COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

SELECT '✅ HCRM Database Installation Complete!' as Final_Status;
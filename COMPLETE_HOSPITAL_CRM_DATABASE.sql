-- ===================================================================
-- COMPLETE HOSPITAL CRM (HCRM) DATABASE - ALL IN ONE FILE
-- Error-free MariaDB/MySQL compatible
-- Includes: All modules, Security, Audit, Log Cleanup, Default Data
-- ===================================================================

-- Database setup
CREATE DATABASE IF NOT EXISTS `hospital_crm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hospital_crm`;

-- Configuration
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ===================================================================
-- CORE HOSPITAL SYSTEM TABLES
-- ===================================================================

-- Hospitals table
CREATE TABLE IF NOT EXISTS `hospitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `established_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_number` (`license_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Departments table
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_doctor_id` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `head_doctor_id` (`head_doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','nurse','receptionist','pharmacy_staff','lab_technician','patient','staff','intern_doctor','intern_nurse','intern_lab','intern_pharmacy','driver') NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `hospital_id` (`hospital_id`),
  KEY `role` (`role`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Patients table
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `patient_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `insurance_provider` varchar(100) DEFAULT NULL,
  `insurance_policy_number` varchar(100) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `assigned_doctor_id` int(11) DEFAULT NULL,
  `status` enum('inpatient','outpatient','discharged','emergency') DEFAULT 'outpatient',
  `last_status_update` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `password` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_id` (`patient_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `assigned_doctor_id` (`assigned_doctor_id`),
  KEY `created_by` (`created_by`),
  KEY `blood_group` (`blood_group`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Doctors table
CREATE TABLE IF NOT EXISTS `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `doctor_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT NULL,
  `available_days` varchar(50) DEFAULT NULL,
  `available_time_start` time DEFAULT NULL,
  `available_time_end` time DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `joining_date` date DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `doctor_id` (`doctor_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `license_number` (`license_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `department_id` (`department_id`),
  KEY `specialization` (`specialization`),
  KEY `is_available` (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Staff table
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `staff_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `staff_type` enum('nurse','receptionist','pharmacy_staff','lab_technician','admin_staff','security','maintenance','accountant','surgeon','transplant_surgeon','anesthesiologist') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `shift` enum('morning','evening','night','rotating') DEFAULT 'morning',
  `room_number` varchar(20) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `department_id` (`department_id`),
  KEY `supervisor_id` (`supervisor_id`),
  KEY `staff_type` (`staff_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- APPOINTMENT SYSTEM
-- ===================================================================

-- Appointments table
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `appointment_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `appointment_type` enum('consultation','follow_up','emergency','routine','surgery','checkup') DEFAULT 'consultation',
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show','rescheduled') DEFAULT 'scheduled',
  `reason` text DEFAULT NULL,
  `chief_complaint` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `department_id` int(11) DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `reminded` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_number` (`appointment_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_date` (`appointment_date`),
  KEY `status` (`status`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- BILLING SYSTEM
-- ===================================================================

-- Bills table
CREATE TABLE IF NOT EXISTS `bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `bill_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `bill_type` enum('consultation','lab','pharmacy','procedure','surgery','emergency','admission','discharge','ambulance','equipment') DEFAULT 'consultation',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance_amount` decimal(10,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
  `payment_status` enum('pending','partial','paid','cancelled','refunded') DEFAULT 'pending',
  `payment_method` enum('cash','credit_card','debit_card','bank_transfer','insurance','cheque','online') DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `payment_status` (`payment_status`),
  KEY `due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bill Items table
CREATE TABLE IF NOT EXISTS `bill_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `item_type` enum('consultation','test','medicine','procedure','room','equipment','service') NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_percent` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  KEY `item_type` (`item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bill Payments table
CREATE TABLE IF NOT EXISTS `bill_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','credit_card','debit_card','bank_transfer','insurance','cheque','online') NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_id` varchar(100) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_status` enum('successful','failed','pending','cancelled') DEFAULT 'successful',
  `notes` text DEFAULT NULL,
  `received_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  KEY `payment_method` (`payment_method`),
  KEY `payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- LABORATORY SYSTEM
-- ===================================================================

-- Lab Tests table
CREATE TABLE IF NOT EXISTS `lab_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `test_code` varchar(50) NOT NULL,
  `test_name` varchar(255) NOT NULL,
  `test_category` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `normal_range` varchar(255) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `sample_type` varchar(100) DEFAULT NULL,
  `preparation_required` text DEFAULT NULL,
  `reporting_time` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `test_code` (`test_code`),
  KEY `hospital_id` (`hospital_id`),
  KEY `test_category` (`test_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Lab Orders table
CREATE TABLE IF NOT EXISTS `lab_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `order_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `sample_collection_date` timestamp NULL DEFAULT NULL,
  `reporting_date` timestamp NULL DEFAULT NULL,
  `priority` enum('routine','urgent','stat') DEFAULT 'routine',
  `status` enum('ordered','sample_collected','in_progress','completed','cancelled') DEFAULT 'ordered',
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `collected_by` int(11) DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Lab Order Tests table
CREATE TABLE IF NOT EXISTS `lab_order_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_order_id` int(11) NOT NULL,
  `lab_test_id` int(11) NOT NULL,
  `test_name` varchar(255) NOT NULL,
  `result_value` varchar(255) DEFAULT NULL,
  `result_unit` varchar(50) DEFAULT NULL,
  `normal_range` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','abnormal') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `tested_by` int(11) DEFAULT NULL,
  `tested_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lab_order_id` (`lab_order_id`),
  KEY `lab_test_id` (`lab_test_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- PHARMACY SYSTEM
-- ===================================================================

-- Medicine Categories table
CREATE TABLE IF NOT EXISTS `medicine_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Medicines table
CREATE TABLE IF NOT EXISTS `medicines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `medicine_code` varchar(50) NOT NULL,
  `medicine_name` varchar(255) NOT NULL,
  `generic_name` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `dosage_form` varchar(100) DEFAULT NULL,
  `strength` varchar(100) DEFAULT NULL,
  `unit_of_measurement` varchar(50) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `storage_condition` varchar(255) DEFAULT NULL,
  `prescription_required` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_code` (`medicine_code`),
  KEY `hospital_id` (`hospital_id`),
  KEY `category_id` (`category_id`),
  KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Prescriptions table
CREATE TABLE IF NOT EXISTS `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `prescription_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `prescription_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `diagnosis` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `dispensed` tinyint(1) DEFAULT 0,
  `dispensed_by` int(11) DEFAULT NULL,
  `dispensed_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_number` (`prescription_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Prescription Medicines table
CREATE TABLE IF NOT EXISTS `prescription_medicines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `medicine_name` varchar(255) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `instructions` text DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `dispensed_quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `medicine_id` (`medicine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- BLOOD BANK SYSTEM
-- ===================================================================

-- Blood Donors table
CREATE TABLE IF NOT EXISTS `blood_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donor_id` varchar(50) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medications` text DEFAULT NULL,
  `last_donation_date` date DEFAULT NULL,
  `total_donations` int(11) DEFAULT 0,
  `is_eligible` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_id` (`donor_id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `blood_group` (`blood_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blood Donation Sessions table
CREATE TABLE IF NOT EXISTS `blood_donation_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donation_id` varchar(50) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `collection_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `collection_location` varchar(255) DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_collected` decimal(5,2) DEFAULT NULL,
  `hemoglobin_level` decimal(4,2) DEFAULT NULL,
  `blood_pressure_systolic` int(11) DEFAULT NULL,
  `blood_pressure_diastolic` int(11) DEFAULT NULL,
  `pulse_rate` int(11) DEFAULT NULL,
  `temperature` decimal(4,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `medical_screening_passed` tinyint(1) DEFAULT 1,
  `screening_notes` text DEFAULT NULL,
  `collected_by` int(11) NOT NULL,
  `status` enum('collected','tested','processed','stored','expired','used') DEFAULT 'collected',
  `expiry_date` date DEFAULT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `donation_id` (`donation_id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donor_id` (`donor_id`),
  KEY `blood_group` (`blood_group`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blood Inventory table
CREATE TABLE IF NOT EXISTS `blood_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `bag_number` varchar(50) NOT NULL,
  `donation_session_id` int(11) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `component_type` enum('whole_blood','red_cells','plasma','platelets','white_cells') DEFAULT 'whole_blood',
  `volume_ml` decimal(6,2) DEFAULT NULL,
  `collection_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('available','reserved','used','expired','quarantined','discarded') DEFAULT 'available',
  `storage_location` varchar(100) DEFAULT NULL,
  `temperature` decimal(4,2) DEFAULT NULL,
  `screening_tests_passed` tinyint(1) DEFAULT 0,
  `cross_match_compatible` varchar(255) DEFAULT NULL,
  `reserved_for_patient_id` int(11) DEFAULT NULL,
  `used_date` timestamp NULL DEFAULT NULL,
  `used_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bag_number` (`bag_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donation_session_id` (`donation_session_id`),
  KEY `blood_group` (`blood_group`),
  KEY `component_type` (`component_type`),
  KEY `status` (`status`),
  KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blood Requests table
CREATE TABLE IF NOT EXISTS `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `request_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `component_type` enum('whole_blood','red_cells','plasma','platelets','white_cells') DEFAULT 'whole_blood',
  `units_requested` int(11) NOT NULL,
  `urgency` enum('routine','urgent','emergency') DEFAULT 'routine',
  `required_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `status` enum('pending','approved','fulfilled','cancelled','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` timestamp NULL DEFAULT NULL,
  `fulfilled_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_number` (`request_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `blood_group` (`blood_group`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blood Usage Records table
CREATE TABLE IF NOT EXISTS `blood_usage_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `blood_inventory_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `blood_request_id` int(11) DEFAULT NULL,
  `usage_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `usage_type` enum('transfusion','surgery','emergency','research') DEFAULT 'transfusion',
  `units_used` decimal(5,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `cross_match_result` enum('compatible','incompatible','not_tested') DEFAULT 'compatible',
  `adverse_reactions` text DEFAULT NULL,
  `administered_by` int(11) NOT NULL,
  `monitored_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `blood_inventory_id` (`blood_inventory_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `blood_request_id` (`blood_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- ORGAN TRANSPLANT SYSTEM
-- ===================================================================

-- Organ Donor Consent table
CREATE TABLE IF NOT EXISTS `organ_donor_consent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donor_id` int(11) NOT NULL,
  `consent_type` enum('full_body','specific_organs','research_only') NOT NULL,
  `consent_date` date NOT NULL,
  `witness_1` varchar(255) DEFAULT NULL,
  `witness_2` varchar(255) DEFAULT NULL,
  `legal_guardian` varchar(255) DEFAULT NULL,
  `consent_document_path` varchar(500) DEFAULT NULL,
  `notarized` tinyint(1) DEFAULT 0,
  `organs_consented` text DEFAULT NULL,
  `restrictions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `revoked_date` date DEFAULT NULL,
  `revocation_reason` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donor_id` (`donor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Organ Donations table
CREATE TABLE IF NOT EXISTS `organ_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donor_id` int(11) NOT NULL,
  `consent_id` int(11) NOT NULL,
  `donation_type` enum('living_donor','deceased_donor','brain_dead') NOT NULL,
  `organ_type` enum('kidney','liver','heart','lung','pancreas','intestine','cornea','skin','bone','tissue') NOT NULL,
  `medical_evaluation` text DEFAULT NULL,
  `brain_death_confirmation` tinyint(1) DEFAULT 0,
  `declaration_time` timestamp NULL DEFAULT NULL,
  `declaring_physician` int(11) DEFAULT NULL,
  `harvest_team_lead` int(11) DEFAULT NULL,
  `preservation_method` varchar(255) DEFAULT NULL,
  `preservation_time` timestamp NULL DEFAULT NULL,
  `viability_assessment` text DEFAULT NULL,
  `transplant_id` int(11) DEFAULT NULL,
  `status` enum('evaluated','harvested','preserved','transplanted','expired','rejected') DEFAULT 'evaluated',
  `legal_clearance` enum('pending','approved','rejected') DEFAULT 'pending',
  `ethics_committee_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donor_id` (`donor_id`),
  KEY `consent_id` (`consent_id`),
  KEY `organ_type` (`organ_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Organ Recipients table
CREATE TABLE IF NOT EXISTS `organ_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `organ_needed` enum('kidney','liver','heart','lung','pancreas','intestine','cornea','skin','bone','tissue') NOT NULL,
  `urgency_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `waiting_list_date` date NOT NULL,
  `priority_score` int(11) DEFAULT 0,
  `medical_compatibility` text DEFAULT NULL,
  `blood_type_compatibility` varchar(100) DEFAULT NULL,
  `tissue_type` varchar(255) DEFAULT NULL,
  `antibody_levels` varchar(255) DEFAULT NULL,
  `insurance_verification` tinyint(1) DEFAULT 0,
  `legal_consent` tinyint(1) DEFAULT 0,
  `guardian_consent` tinyint(1) DEFAULT 0,
  `ethics_approval` tinyint(1) DEFAULT 0,
  `transplant_date` date DEFAULT NULL,
  `status` enum('waiting','matched','transplanted','removed','deceased') DEFAULT 'waiting',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `organ_needed` (`organ_needed`),
  KEY `urgency_level` (`urgency_level`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Organ Transplants table
CREATE TABLE IF NOT EXISTS `organ_transplants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donation_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `surgery_date` timestamp NOT NULL,
  `lead_surgeon` int(11) NOT NULL,
  `surgical_team` text DEFAULT NULL,
  `operation_duration` int(11) DEFAULT NULL,
  `cross_match_result` enum('compatible','incompatible') NOT NULL,
  `immunosuppression_protocol` text DEFAULT NULL,
  `immediate_complications` text DEFAULT NULL,
  `post_operative_care` text DEFAULT NULL,
  `follow_up_schedule` text DEFAULT NULL,
  `success_rate` enum('excellent','good','fair','poor','failed') DEFAULT NULL,
  `rejection_episodes` int(11) DEFAULT 0,
  `legal_completion` tinyint(1) DEFAULT 0,
  `legal_documentation` varchar(500) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','failed','complications') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donation_id` (`donation_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `lead_surgeon` (`lead_surgeon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Organ Legal Rejections table
CREATE TABLE IF NOT EXISTS `organ_legal_rejections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donation_id` int(11) DEFAULT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `transplant_id` int(11) DEFAULT NULL,
  `rejection_type` enum('consent_withdrawal','medical_incompatibility','legal_violation','ethics_violation','family_objection') NOT NULL,
  `rejection_reason` text NOT NULL,
  `legal_basis` text DEFAULT NULL,
  `rejecting_authority` varchar(255) NOT NULL,
  `rejection_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `documentation_path` varchar(500) DEFAULT NULL,
  `appeal_possible` tinyint(1) DEFAULT 1,
  `appeal_deadline` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donation_id` (`donation_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `transplant_id` (`transplant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- INSURANCE SYSTEM
-- ===================================================================

-- Insurance Companies table
CREATE TABLE IF NOT EXISTS `insurance_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `company_name` varchar(255) NOT NULL,
  `company_code` varchar(50) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `policy_types` text DEFAULT NULL,
  `coverage_percentage` decimal(5,2) DEFAULT NULL,
  `max_coverage_amount` decimal(12,2) DEFAULT NULL,
  `claim_processing_time` varchar(100) DEFAULT NULL,
  `preferred_hospitals` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`),
  KEY `hospital_id` (`hospital_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Patient Insurance table
CREATE TABLE IF NOT EXISTS `patient_insurance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `insurance_company_id` int(11) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `policy_type` enum('individual','family','group','corporate') DEFAULT 'individual',
  `policy_holder_name` varchar(255) NOT NULL,
  `relationship_to_patient` varchar(100) DEFAULT NULL,
  `policy_start_date` date NOT NULL,
  `policy_end_date` date NOT NULL,
  `coverage_amount` decimal(12,2) DEFAULT NULL,
  `deductible_amount` decimal(10,2) DEFAULT NULL,
  `copay_percentage` decimal(5,2) DEFAULT NULL,
  `pre_authorization_required` tinyint(1) DEFAULT 0,
  `coverage_details` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `claim_limit_per_year` decimal(12,2) DEFAULT NULL,
  `claims_used_this_year` decimal(12,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `verification_status` enum('pending','verified','rejected','expired') DEFAULT 'pending',
  `verification_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `policy_number` (`policy_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `insurance_company_id` (`insurance_company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insurance Claims table
CREATE TABLE IF NOT EXISTS `insurance_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `claim_number` varchar(50) NOT NULL,
  `patient_insurance_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `claim_type` enum('hospitalization','outpatient','emergency','surgery','diagnostic','pharmacy') DEFAULT 'outpatient',
  `claim_amount` decimal(12,2) NOT NULL,
  `approved_amount` decimal(12,2) DEFAULT NULL,
  `deductible_applied` decimal(10,2) DEFAULT NULL,
  `copay_amount` decimal(10,2) DEFAULT NULL,
  `claim_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `submission_date` timestamp NULL DEFAULT NULL,
  `processing_date` timestamp NULL DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `settlement_date` timestamp NULL DEFAULT NULL,
  `status` enum('draft','submitted','under_review','approved','rejected','settled','cancelled') DEFAULT 'draft',
  `rejection_reason` text DEFAULT NULL,
  `diagnosis_codes` varchar(500) DEFAULT NULL,
  `procedure_codes` varchar(500) DEFAULT NULL,
  `supporting_documents` text DEFAULT NULL,
  `claim_adjuster` varchar(255) DEFAULT NULL,
  `settlement_amount` decimal(12,2) DEFAULT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `claim_number` (`claim_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_insurance_id` (`patient_insurance_id`),
  KEY `patient_id` (`patient_id`),
  KEY `bill_id` (`bill_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- EQUIPMENT MANAGEMENT
-- ===================================================================

-- Equipment table
CREATE TABLE IF NOT EXISTS `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `equipment_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `cost` decimal(12,2) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `status` enum('operational','maintenance','out_of_order','retired') DEFAULT 'operational',
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `maintenance_schedule` varchar(255) DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `operating_instructions` text DEFAULT NULL,
  `safety_guidelines` text DEFAULT NULL,
  `calibration_required` tinyint(1) DEFAULT 0,
  `last_calibration` date DEFAULT NULL,
  `next_calibration` date DEFAULT NULL,
  `responsible_person` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_code` (`equipment_code`),
  KEY `hospital_id` (`hospital_id`),
  KEY `department_id` (`department_id`),
  KEY `status` (`status`),
  KEY `responsible_person` (`responsible_person`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Equipment Maintenance table
CREATE TABLE IF NOT EXISTS `equipment_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `equipment_id` int(11) NOT NULL,
  `maintenance_type` enum('preventive','corrective','calibration','inspection') NOT NULL,
  `maintenance_date` date NOT NULL,
  `performed_by` varchar(255) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `vendor_company` varchar(255) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `parts_replaced` text DEFAULT NULL,
  `work_performed` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `warranty_claim` tinyint(1) DEFAULT 0,
  `downtime_hours` decimal(5,2) DEFAULT NULL,
  `maintenance_completion_status` enum('completed','pending','cancelled') DEFAULT 'completed',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `maintenance_date` (`maintenance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- BED MANAGEMENT
-- ===================================================================

-- Beds table
CREATE TABLE IF NOT EXISTS `beds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `bed_number` varchar(50) NOT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `wing` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `bed_type` enum('general','icu','emergency','pediatric','maternity','isolation','vip') DEFAULT 'general',
  `bed_category` enum('regular','oxygen','cardiac_monitor','ventilator') DEFAULT 'regular',
  `patient_id` int(11) DEFAULT NULL,
  `status` enum('available','occupied','maintenance','reserved','out_of_service') DEFAULT 'available',
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `equipment_attached` text DEFAULT NULL,
  `last_cleaned` timestamp NULL DEFAULT NULL,
  `cleaned_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bed_number` (`bed_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `department_id` (`department_id`),
  KEY `patient_id` (`patient_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bed Assignments table
CREATE TABLE IF NOT EXISTS `bed_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `bed_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `admission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `discharge_date` timestamp NULL DEFAULT NULL,
  `admission_type` enum('emergency','scheduled','transfer','observation') DEFAULT 'scheduled',
  `admission_reason` text DEFAULT NULL,
  `assigned_by` int(11) NOT NULL,
  `attending_doctor` int(11) DEFAULT NULL,
  `nursing_station` varchar(100) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `status` enum('active','discharged','transferred') DEFAULT 'active',
  `discharge_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `bed_id` (`bed_id`),
  KEY `patient_id` (`patient_id`),
  KEY `attending_doctor` (`attending_doctor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- AMBULANCE MANAGEMENT
-- ===================================================================

-- Ambulances table
CREATE TABLE IF NOT EXISTS `ambulances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `vehicle_number` varchar(20) NOT NULL,
  `vehicle_type` enum('basic','advanced','icu','air_ambulance') DEFAULT 'basic',
  `driver_name` varchar(100) DEFAULT NULL,
  `driver_phone` varchar(20) DEFAULT NULL,
  `driver_license` varchar(100) DEFAULT NULL,
  `paramedic_name` varchar(100) DEFAULT NULL,
  `paramedic_certification` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT 4,
  `equipment` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('available','in_use','maintenance','out_of_service') DEFAULT 'available',
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `registration_expiry` date DEFAULT NULL,
  `fuel_type` enum('petrol','diesel','electric','hybrid') DEFAULT 'diesel',
  `mileage` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_number` (`vehicle_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ambulance Bookings table
CREATE TABLE IF NOT EXISTS `ambulance_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `booking_number` varchar(50) NOT NULL,
  `ambulance_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `patient_phone` varchar(20) DEFAULT NULL,
  `pickup_address` text NOT NULL,
  `destination_address` text NOT NULL,
  `pickup_coordinates` varchar(100) DEFAULT NULL,
  `destination_coordinates` varchar(100) DEFAULT NULL,
  `booking_date` datetime NOT NULL,
  `pickup_time` datetime DEFAULT NULL,
  `arrival_time` datetime DEFAULT NULL,
  `completion_time` datetime DEFAULT NULL,
  `distance_km` decimal(6,2) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `emergency_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `medical_condition` text DEFAULT NULL,
  `special_requirements` text DEFAULT NULL,
  `charges` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `status` enum('scheduled','dispatched','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `driver_id` int(11) DEFAULT NULL,
  `paramedic_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_number` (`booking_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `ambulance_id` (`ambulance_id`),
  KEY `patient_id` (`patient_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- PATIENT VITALS & MONITORING
-- ===================================================================

-- Patient Vitals table
CREATE TABLE IF NOT EXISTS `patient_vitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `recorded_by` int(11) NOT NULL,
  `temperature` decimal(4,2) DEFAULT NULL,
  `blood_pressure_systolic` int(11) DEFAULT NULL,
  `blood_pressure_diastolic` int(11) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `oxygen_saturation` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(5,2) GENERATED ALWAYS AS (CASE WHEN `height` > 0 THEN `weight` / ((`height`/100) * (`height`/100)) ELSE NULL END) STORED,
  `blood_glucose` decimal(5,2) DEFAULT NULL,
  `pain_level` int(11) DEFAULT NULL,
  `consciousness_level` enum('alert','drowsy','confused','unconscious') DEFAULT 'alert',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `recorded_by` (`recorded_by`),
  KEY `recorded_at` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Patient Visits table
CREATE TABLE IF NOT EXISTS `patient_visits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time DEFAULT NULL,
  `visit_type` enum('routine','emergency','follow_up','consultation','procedure') DEFAULT 'routine',
  `chief_complaint` text DEFAULT NULL,
  `symptoms` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_given` text DEFAULT NULL,
  `medications_prescribed` text DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT 0,
  `follow_up_date` date DEFAULT NULL,
  `assigned_nurse_id` int(11) DEFAULT NULL,
  `attending_doctor_id` int(11) DEFAULT NULL,
  `visit_status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `visit_date` (`visit_date`),
  KEY `assigned_nurse_id` (`assigned_nurse_id`),
  KEY `attending_doctor_id` (`attending_doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Patient Status Logs table
CREATE TABLE IF NOT EXISTS `patient_status_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `status` enum('inpatient','outpatient','discharged','emergency','transferred','deceased') NOT NULL,
  `previous_status` enum('inpatient','outpatient','discharged','emergency','transferred','deceased') DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `updated_by` (`updated_by`),
  KEY `updated_at` (`updated_at`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- SECURITY & AUDIT SYSTEM
-- ===================================================================

-- Security Logs table
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `event_description` text DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `status` enum('active','resolved','ignored') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `user_id` (`user_id`),
  KEY `event_type` (`event_type`),
  KEY `created_at` (`created_at`),
  KEY `severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Login Attempts table
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `failure_reason` varchar(255) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `username` (`username`),
  KEY `ip_address` (`ip_address`),
  KEY `success` (`success`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Audit Logs table
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `table_name` (`table_name`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- COMMUNICATION SYSTEM
-- ===================================================================

-- Email Templates table
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `variables` json DEFAULT NULL,
  `template_type` enum('appointment','billing','notification','marketing','system') DEFAULT 'notification',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `template_type` (`template_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Email Logs table
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `template_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `status` enum('sent','failed','pending','cancelled') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `attempts` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `template_id` (`template_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- SYSTEM SETTINGS
-- ===================================================================

-- System Settings table
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json','email','url') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'general',
  `is_editable` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting` (`hospital_id`,`setting_key`),
  KEY `hospital_id` (`hospital_id`),
  KEY `category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- ATTENDANCE & SHIFT MANAGEMENT
-- ===================================================================

-- Staff Shifts table
CREATE TABLE IF NOT EXISTS `staff_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `staff_id` int(11) NOT NULL,
  `shift_date` date NOT NULL,
  `shift_type` enum('morning','evening','night','rotating') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `break_duration` int(11) DEFAULT 60,
  `department_id` int(11) DEFAULT NULL,
  `status` enum('scheduled','active','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `staff_id` (`staff_id`),
  KEY `shift_date` (`shift_date`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Staff Attendance table
CREATE TABLE IF NOT EXISTS `staff_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `staff_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `clock_in_time` timestamp NULL DEFAULT NULL,
  `clock_out_time` timestamp NULL DEFAULT NULL,
  `break_start_time` timestamp NULL DEFAULT NULL,
  `break_end_time` timestamp NULL DEFAULT NULL,
  `total_hours` decimal(4,2) GENERATED ALWAYS AS (CASE WHEN `clock_out_time` IS NOT NULL AND `clock_in_time` IS NOT NULL THEN ROUND(TIMESTAMPDIFF(MINUTE, `clock_in_time`, `clock_out_time`) / 60, 2) ELSE NULL END) STORED,
  `overtime_hours` decimal(4,2) DEFAULT 0.00,
  `status` enum('present','absent','late','half_day','sick_leave','vacation') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`staff_id`,`attendance_date`),
  KEY `hospital_id` (`hospital_id`),
  KEY `staff_id` (`staff_id`),
  KEY `attendance_date` (`attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- USEFUL VIEWS
-- ===================================================================

-- Blood Inventory Dashboard View
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
-- INDEXES FOR PERFORMANCE
-- ===================================================================

-- Additional performance indexes
CREATE INDEX IF NOT EXISTS `idx_security_logs_cleanup` ON `security_logs` (`created_at`, `event_type`);
CREATE INDEX IF NOT EXISTS `idx_login_attempts_cleanup` ON `login_attempts` (`created_at`, `success`);
CREATE INDEX IF NOT EXISTS `idx_audit_logs_cleanup` ON `audit_logs` (`created_at`, `table_name`);
CREATE INDEX IF NOT EXISTS `idx_email_logs_cleanup` ON `email_logs` (`created_at`, `status`);
CREATE INDEX IF NOT EXISTS `idx_patient_status_logs_cleanup` ON `patient_status_logs` (`updated_at`, `status`);

-- ===================================================================
-- DEFAULT DATA
-- ===================================================================

-- Insert default hospital
INSERT IGNORE INTO `hospitals` (`id`, `name`, `address`, `city`, `state`, `phone`, `email`, `license_number`) 
VALUES (1, 'Default Hospital', '123 Hospital Street', 'Hospital City', 'Hospital State', '+1234567890', 'admin@hospital.com', 'LIC001');

-- Insert default admin user (password: 'password')
INSERT IGNORE INTO `users` (`id`, `hospital_id`, `username`, `email`, `password`, `role`, `first_name`, `last_name`, `is_active`) 
VALUES (1, 1, 'admin', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator', 1);

-- Insert default departments
INSERT IGNORE INTO `departments` (`id`, `hospital_id`, `name`, `description`) VALUES
(1, 1, 'Emergency', 'Emergency Department'),
(2, 1, 'Cardiology', 'Heart and Cardiovascular Department'),
(3, 1, 'Orthopedics', 'Bone and Joint Department'),
(4, 1, 'Pediatrics', 'Children Healthcare Department'),
(5, 1, 'General Medicine', 'General Medical Department'),
(6, 1, 'Surgery', 'Surgical Department'),
(7, 1, 'ICU', 'Intensive Care Unit'),
(8, 1, 'Laboratory', 'Laboratory Department'),
(9, 1, 'Pharmacy', 'Pharmacy Department'),
(10, 1, 'Radiology', 'Radiology Department');

-- Insert default medicine categories
INSERT IGNORE INTO `medicine_categories` (`id`, `hospital_id`, `category_name`, `description`) VALUES
(1, 1, 'Antibiotics', 'Antibiotic medications'),
(2, 1, 'Pain Relief', 'Pain management medications'),
(3, 1, 'Cardiovascular', 'Heart and blood pressure medications'),
(4, 1, 'Diabetes', 'Diabetes management medications'),
(5, 1, 'Respiratory', 'Respiratory system medications'),
(6, 1, 'Neurological', 'Neurological medications'),
(7, 1, 'Gastrointestinal', 'Digestive system medications'),
(8, 1, 'Vitamins & Supplements', 'Vitamins and nutritional supplements'),
(9, 1, 'Emergency', 'Emergency medications'),
(10, 1, 'Surgical', 'Surgical medications');

-- Insert default email templates
INSERT IGNORE INTO `email_templates` (`hospital_id`, `template_name`, `subject`, `body`, `variables`, `template_type`, `created_by`) VALUES
(1, 'appointment_reminder', 'Appointment Reminder', 'Dear {patient_name},\n\nThis is a reminder for your appointment on {appointment_date} at {appointment_time} with Dr. {doctor_name}.\n\nPlease arrive 15 minutes before your scheduled time.\n\nBest regards,\nHospital Team', '["patient_name", "appointment_date", "appointment_time", "doctor_name"]', 'appointment', 1),
(1, 'bill_notification', 'Bill Notification', 'Dear {patient_name},\n\nYour bill for {bill_amount} has been generated. Please visit the hospital to make the payment.\n\nBill Number: {bill_number}\nDue Date: {due_date}\n\nBest regards,\nHospital Team', '["patient_name", "bill_amount", "bill_number", "due_date"]', 'billing', 1),
(1, 'lab_results_ready', 'Lab Results Ready', 'Dear {patient_name},\n\nYour lab test results are now ready. Please visit the hospital or log into your patient portal to view the results.\n\nTest Date: {test_date}\nOrder Number: {order_number}\n\nBest regards,\nLaboratory Department', '["patient_name", "test_date", "order_number"]', 'notification', 1),
(1, 'prescription_ready', 'Prescription Ready', 'Dear {patient_name},\n\nYour prescription is ready for pickup at our pharmacy.\n\nPrescription Number: {prescription_number}\nTotal Amount: {total_amount}\n\nPharmacy Hours: 9 AM - 9 PM\n\nBest regards,\nPharmacy Department', '["patient_name", "prescription_number", "total_amount"]', 'notification', 1);

-- Insert default system settings
INSERT IGNORE INTO `system_settings` (`hospital_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `category`) VALUES
(1, 'hospital_name', 'Default Hospital', 'string', 'Hospital name', 'general'),
(1, 'hospital_phone', '+1234567890', 'string', 'Hospital phone number', 'general'),
(1, 'hospital_email', 'admin@hospital.com', 'email', 'Hospital email address', 'general'),
(1, 'hospital_address', '123 Hospital Street, Hospital City, Hospital State', 'string', 'Hospital address', 'general'),
(1, 'appointment_slot_duration', '30', 'number', 'Default appointment duration in minutes', 'appointments'),
(1, 'max_appointments_per_day', '50', 'number', 'Maximum appointments per doctor per day', 'appointments'),
(1, 'advance_booking_days', '30', 'number', 'How many days in advance appointments can be booked', 'appointments'),
(1, 'max_login_attempts', '5', 'number', 'Maximum login attempts before lockout', 'security'),
(1, 'lockout_duration', '15', 'number', 'Lockout duration in minutes', 'security'),
(1, 'session_timeout', '3600', 'number', 'Session timeout in seconds', 'security'),
(1, 'password_min_length', '8', 'number', 'Minimum password length', 'security'),
(1, 'auto_log_cleanup_enabled', 'true', 'boolean', 'Enable automatic log cleanup', 'maintenance'),
(1, 'log_retention_security_logs', '90', 'number', 'Security logs retention period in days', 'maintenance'),
(1, 'log_retention_login_attempts', '30', 'number', 'Login attempts retention period in days', 'maintenance'),
(1, 'log_retention_audit_logs', '180', 'number', 'Audit logs retention period in days', 'maintenance'),
(1, 'log_retention_email_logs', '60', 'number', 'Email logs retention period in days', 'maintenance'),
(1, 'log_retention_patient_status_logs', '365', 'number', 'Patient status logs retention period in days', 'maintenance'),
(1, 'currency_symbol', '$', 'string', 'Currency symbol for billing', 'billing'),
(1, 'tax_rate', '10.0', 'number', 'Default tax rate percentage', 'billing'),
(1, 'bill_due_days', '30', 'number', 'Default bill due period in days', 'billing'),
(1, 'enable_email_notifications', 'true', 'boolean', 'Enable email notifications', 'notifications'),
(1, 'enable_sms_notifications', 'false', 'boolean', 'Enable SMS notifications', 'notifications'),
(1, 'blood_donation_min_age', '18', 'number', 'Minimum age for blood donation', 'blood_bank'),
(1, 'blood_donation_max_age', '65', 'number', 'Maximum age for blood donation', 'blood_bank'),
(1, 'blood_donation_min_weight', '50', 'number', 'Minimum weight for blood donation in kg', 'blood_bank'),
(1, 'blood_storage_days_whole_blood', '35', 'number', 'Whole blood storage days', 'blood_bank'),
(1, 'blood_storage_days_plasma', '365', 'number', 'Plasma storage days', 'blood_bank'),
(1, 'blood_storage_days_platelets', '5', 'number', 'Platelets storage days', 'blood_bank');

-- Insert sample lab tests
INSERT IGNORE INTO `lab_tests` (`id`, `hospital_id`, `test_code`, `test_name`, `test_category`, `department`, `normal_range`, `unit`, `cost`, `sample_type`, `preparation_required`, `reporting_time`) VALUES
(1, 1, 'CBC001', 'Complete Blood Count', 'Hematology', 'Laboratory', '4.5-11.0', '10^3/uL', 25.00, 'Blood', 'No special preparation required', '2-4 hours'),
(2, 1, 'LFT001', 'Liver Function Test', 'Biochemistry', 'Laboratory', 'Varies by parameter', 'Various', 45.00, 'Blood', '8-12 hours fasting required', '4-6 hours'),
(3, 1, 'KFT001', 'Kidney Function Test', 'Biochemistry', 'Laboratory', 'Varies by parameter', 'Various', 40.00, 'Blood', 'No special preparation required', '4-6 hours'),
(4, 1, 'TSH001', 'Thyroid Stimulating Hormone', 'Endocrinology', 'Laboratory', '0.4-4.0', 'mIU/L', 30.00, 'Blood', 'No special preparation required', '24 hours'),
(5, 1, 'HBA1C', 'Hemoglobin A1c', 'Biochemistry', 'Laboratory', '<7.0', '%', 35.00, 'Blood', 'No fasting required', '24 hours'),
(6, 1, 'LIPID', 'Lipid Profile', 'Biochemistry', 'Laboratory', 'Varies by parameter', 'mg/dL', 50.00, 'Blood', '12 hours fasting required', '4-6 hours'),
(7, 1, 'URINE', 'Urine Complete Analysis', 'Clinical Pathology', 'Laboratory', 'Normal ranges vary', 'Various', 20.00, 'Urine', 'Clean catch mid-stream sample', '2 hours'),
(8, 1, 'ECG001', 'Electrocardiogram', 'Cardiology', 'Cardiology', 'Normal sinus rhythm', 'N/A', 25.00, 'N/A', 'No special preparation required', '30 minutes'),
(9, 1, 'XRAY', 'Chest X-Ray', 'Radiology', 'Radiology', 'Normal chest anatomy', 'N/A', 30.00, 'N/A', 'Remove metal objects', '1 hour'),
(10, 1, 'COVID', 'COVID-19 RT-PCR', 'Microbiology', 'Laboratory', 'Negative', 'N/A', 75.00, 'Nasopharyngeal swab', 'No special preparation required', '24-48 hours');

-- ===================================================================
-- FINAL SETUP
-- ===================================================================

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Commit transaction
COMMIT;

-- Restore settings
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ===================================================================
-- INSTALLATION COMPLETE MESSAGE
-- ===================================================================

SELECT 
    '🎉 HCRM Database Installation Complete!' as Status,
    COUNT(*) as Total_Tables,
    'hospital_crm' as Database_Name,
    'admin / password' as Default_Login,
    'All modules ready: Blood Bank, Organ Transplant, Insurance, Equipment, etc.' as Features
FROM information_schema.tables 
WHERE table_schema = 'hospital_crm';

-- Show table summary
SELECT 
    table_name as Table_Name,
    table_rows as Est_Rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) as Size_MB
FROM information_schema.tables 
WHERE table_schema = 'hospital_crm'
ORDER BY table_name;

-- ===================================================================
-- END OF COMPLETE HOSPITAL CRM DATABASE
-- ===================================================================
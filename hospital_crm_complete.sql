-- ============================================================================
-- HOSPITAL CRM - COMPLETE DATABASE SCHEMA
-- ============================================================================
-- Version: 3.2 (Complete Blood Bank & Organ Donation Management Systems)
-- Created: 2024
-- Features: Patient Management, Doctor Management, Appointments, Billing, 
--          Insurance, Pharmacy, Laboratory, Prescriptions, Equipment, Staff,
--          Automatic Service Aggregation Billing, Blood Bank Management,
--          Organ Donation Management with Matching System
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================================
-- DATABASE CREATION
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `hospital_crm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hospital_crm`;

-- ============================================================================
-- CORE TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `roles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `role_display` varchar(100) NOT NULL,
  `permissions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `role_display` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `patients`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `patients`;
CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_id` (`patient_id`),
  KEY `email` (`email`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `doctors`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `doctors`;
CREATE TABLE `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `doctor_name` varchar(100) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `qualification` varchar(200) NOT NULL,
  `experience_years` int(11) DEFAULT 0,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `schedule` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `specialization` (`specialization`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `appointments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `appointment_type` enum('consultation','follow_up','emergency','routine_checkup') DEFAULT 'consultation',
  `status` enum('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_date` (`appointment_date`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `pharmacy`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `pharmacy`;
CREATE TABLE `pharmacy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `medicine_name` varchar(200) NOT NULL,
  `generic_name` varchar(200) DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) DEFAULT 10,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `medicine_name` (`medicine_name`),
  KEY `category` (`category`),
  KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `pharmacy_sales`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `pharmacy_sales`;
CREATE TABLE `pharmacy_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` varchar(20) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `sale_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','paid','partial') DEFAULT 'pending',
  `payment_method` enum('cash','card','upi','insurance') DEFAULT NULL,
  `sold_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_id` (`sale_id`),
  KEY `patient_id` (`patient_id`),
  KEY `sale_date` (`sale_date`),
  KEY `sold_by` (`sold_by`),
  CONSTRAINT `pharmacy_sales_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pharmacy_sales_ibfk_2` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `pharmacy_sale_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `pharmacy_sale_items`;
CREATE TABLE `pharmacy_sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `medicine_id` (`medicine_id`),
  CONSTRAINT `pharmacy_sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `pharmacy_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pharmacy_sale_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `pharmacy` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `laboratory`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `laboratory`;
CREATE TABLE `laboratory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_name` varchar(200) NOT NULL,
  `test_category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `normal_range` varchar(100) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sample_type` varchar(50) DEFAULT NULL,
  `preparation_instructions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `test_category` (`test_category`),
  KEY `test_name` (`test_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `lab_tests`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `lab_tests`;
CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_id` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `test_date` date NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `priority` enum('normal','high','urgent') DEFAULT 'normal',
  `notes` text DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `test_id` (`test_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `test_date` (`test_date`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `lab_tests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `lab_tests_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lab_tests_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `laboratory_results`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `laboratory_results`;
CREATE TABLE `laboratory_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `result_value` varchar(200) NOT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `test_date` date NOT NULL,
  `status` enum('pending','completed','reviewed') DEFAULT 'pending',
  `conducted_by` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `test_id` (`test_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `conducted_by` (`conducted_by`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `laboratory_results_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `lab_tests` (`id`),
  CONSTRAINT `laboratory_results_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `laboratory_results_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `laboratory_results_ibfk_4` FOREIGN KEY (`conducted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `laboratory_results_ibfk_5` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `prescriptions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `prescription_date` date NOT NULL,
  `status` enum('pending','dispensed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `prescribed_by` int(11) NOT NULL,
  `dispensed_by` int(11) DEFAULT NULL,
  `dispensed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_id` (`prescription_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `prescribed_by` (`prescribed_by`),
  KEY `dispensed_by` (`dispensed_by`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_ibfk_4` FOREIGN KEY (`prescribed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `prescriptions_ibfk_5` FOREIGN KEY (`dispensed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `prescription_details`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `prescription_details`;
CREATE TABLE `prescription_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `instructions` text DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `medicine_id` (`medicine_id`),
  CONSTRAINT `prescription_details_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescription_details_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `pharmacy` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `staff`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `staff_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  KEY `user_id` (`user_id`),
  KEY `department` (`department`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `equipment`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `equipment`;
CREATE TABLE `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_name` varchar(200) NOT NULL,
  `equipment_id` varchar(50) NOT NULL,
  `category` varchar(100) NOT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(10,2) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `status` enum('active','maintenance','repair','retired') DEFAULT 'active',
  `location` varchar(200) DEFAULT NULL,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_id` (`equipment_id`),
  KEY `category` (`category`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ENHANCED BILLING SYSTEM TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `billing` (Updated)
-- --------------------------------------------------------

DROP TABLE IF EXISTS `billing`;
CREATE TABLE `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `pharmacy_sale_id` int(11) DEFAULT NULL,
  `lab_test_id` int(11) DEFAULT NULL,
  `bill_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','overdue') DEFAULT 'pending',
  `payment_method` enum('cash','card','upi','bank_transfer','cheque') DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_id` (`bill_id`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `pharmacy_sale_id` (`pharmacy_sale_id`),
  KEY `lab_test_id` (`lab_test_id`),
  KEY `created_by` (`created_by`),
  KEY `payment_status` (`payment_status`),
  KEY `bill_date` (`bill_date`),
  KEY `due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `bill_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `bill_items`;
CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `service_type` enum('consultation','pharmacy','lab_test','procedure','room_charge','other') NOT NULL,
  `description` varchar(500) NOT NULL,
  `service_date` date NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `amount` decimal(10,2) NOT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'Reference to appointment_id, pharmacy_sale_id, lab_test_id etc',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  KEY `service_type` (`service_type`),
  KEY `service_date` (`service_date`),
  CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `payment_transactions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `payment_transactions`;
CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','upi','bank_transfer','cheque') NOT NULL,
  `payment_date` date NOT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  KEY `payment_method` (`payment_method`),
  KEY `payment_date` (`payment_date`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_transactions_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraints for billing table after all tables are created
ALTER TABLE `billing` 
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `billing_ibfk_3` FOREIGN KEY (`pharmacy_sale_id`) REFERENCES `pharmacy_sales` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `billing_ibfk_4` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `billing_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

-- ============================================================================
-- BLOOD BANK MANAGEMENT TABLES
-- ============================================================================



-- --------------------------------------------------------
-- Table structure for table `blood_donations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_donations`;
CREATE TABLE `blood_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_name` varchar(100) NOT NULL,
  `donor_phone` varchar(20) NOT NULL,
  `donor_email` varchar(100) DEFAULT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `donation_date` date NOT NULL,
  `volume_ml` int(11) NOT NULL DEFAULT 450,
  `hemoglobin_level` decimal(3,1) NOT NULL,
  `status` enum('available','used','expired') DEFAULT 'available',
  `used_date` date DEFAULT NULL,
  `used_for_request` int(11) DEFAULT NULL,
  `collected_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `blood_type` (`blood_type`),
  KEY `donation_date` (`donation_date`),
  KEY `status` (`status`),
  KEY `collected_by` (`collected_by`),
  CONSTRAINT `blood_donations_ibfk_1` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- --------------------------------------------------------
-- Table structure for table `blood_requests`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_requests`;
CREATE TABLE `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_needed` int(11) NOT NULL DEFAULT 1,
  `urgency_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `request_date` date NOT NULL,
  `required_by` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','fulfilled','cancelled') DEFAULT 'pending',
  `fulfilled_by` int(11) DEFAULT NULL,
  `fulfilled_date` datetime DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `blood_type` (`blood_type`),
  KEY `status` (`status`),
  KEY `urgency_level` (`urgency_level`),
  KEY `requested_by` (`requested_by`),
  KEY `fulfilled_by` (`fulfilled_by`),
  CONSTRAINT `blood_requests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `blood_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `blood_requests_ibfk_3` FOREIGN KEY (`fulfilled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BLOOD BANK MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `blood_donors`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_donors`;
CREATE TABLE `blood_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `last_donation_date` date DEFAULT NULL,
  `eligibility_status` enum('eligible','temporarily_ineligible','permanently_ineligible') DEFAULT 'eligible',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_id` (`donor_id`),
  KEY `blood_group` (`blood_group`),
  KEY `eligibility_status` (`eligibility_status`),
  KEY `status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `blood_donors_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `blood_donations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_donations`;
CREATE TABLE `blood_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donation_id` varchar(20) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_collected` decimal(3,1) NOT NULL,
  `donation_date` date NOT NULL,
  `collection_site` varchar(100) DEFAULT NULL,
  `staff_id` int(11) NOT NULL,
  `hemoglobin_level` decimal(4,1) DEFAULT NULL,
  `blood_pressure` varchar(10) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `weight` decimal(5,1) DEFAULT NULL,
  `donation_status` enum('completed','pending','rejected') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `donation_id` (`donation_id`),
  KEY `donor_id` (`donor_id`),
  KEY `blood_group` (`blood_group`),
  KEY `donation_date` (`donation_date`),
  KEY `donation_status` (`donation_status`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `blood_donations_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `blood_donors` (`id`),
  CONSTRAINT `blood_donations_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `blood_inventory`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_inventory`;
CREATE TABLE `blood_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_available` decimal(5,1) NOT NULL DEFAULT 0.0,
  `expiry_date` date NOT NULL,
  `collection_date` date NOT NULL,
  `source_donation_id` varchar(20) DEFAULT NULL,
  `status` enum('available','expired','used') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `blood_group` (`blood_group`),
  KEY `expiry_date` (`expiry_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `blood_requests`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_requests`;
CREATE TABLE `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_requested` decimal(3,1) NOT NULL,
  `urgency_level` enum('critical','urgent','normal') NOT NULL,
  `requesting_doctor` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `required_date` date NOT NULL,
  `request_status` enum('pending','approved','rejected','fulfilled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_id` (`request_id`),
  KEY `patient_id` (`patient_id`),
  KEY `blood_group` (`blood_group`),
  KEY `urgency_level` (`urgency_level`),
  KEY `request_status` (`request_status`),
  KEY `requesting_doctor` (`requesting_doctor`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `blood_requests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `blood_requests_ibfk_2` FOREIGN KEY (`requesting_doctor`) REFERENCES `doctors` (`id`),
  CONSTRAINT `blood_requests_ibfk_3` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ORGAN DONATION MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `organ_donors`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_donors`;
CREATE TABLE `organ_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `organs_to_donate` text NOT NULL,
  `medical_history` text DEFAULT NULL,
  `consent_date` date NOT NULL,
  `donor_status` enum('active','inactive','deceased','donated') DEFAULT 'active',
  `registered_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_id` (`donor_id`),
  KEY `blood_group` (`blood_group`),
  KEY `donor_status` (`donor_status`),
  KEY `consent_date` (`consent_date`),
  KEY `registered_by` (`registered_by`),
  CONSTRAINT `organ_donors_ibfk_1` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- --------------------------------------------------------
-- Table structure for table `organ_recipients`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_recipients`;
CREATE TABLE `organ_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `organ_needed` enum('heart','liver','kidney','lung','pancreas','cornea') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `urgency_level` enum('critical','urgent','normal') NOT NULL,
  `medical_condition` text NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `registration_date` date NOT NULL,
  `priority_score` int(3) NOT NULL,
  `recipient_status` enum('waiting','matched','transplanted','cancelled') DEFAULT 'waiting',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `recipient_id` (`recipient_id`),
  KEY `patient_id` (`patient_id`),
  KEY `organ_needed` (`organ_needed`),
  KEY `blood_group` (`blood_group`),
  KEY `urgency_level` (`urgency_level`),
  KEY `recipient_status` (`recipient_status`),
  KEY `priority_score` (`priority_score`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `organ_recipients_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `organ_recipients_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `organ_transplants`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_transplants`;
CREATE TABLE `organ_transplants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transplant_id` varchar(20) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `organ_type` enum('heart','liver','kidney','lung','pancreas','cornea') NOT NULL,
  `transplant_date` date NOT NULL,
  `surgeon_id` int(11) NOT NULL,
  `hospital_unit` varchar(100) DEFAULT NULL,
  `surgery_duration` decimal(4,1) DEFAULT NULL,
  `transplant_status` enum('successful','failed','pending') DEFAULT 'successful',
  `complications` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transplant_id` (`transplant_id`),
  KEY `donor_id` (`donor_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `organ_type` (`organ_type`),
  KEY `transplant_date` (`transplant_date`),
  KEY `surgeon_id` (`surgeon_id`),
  KEY `transplant_status` (`transplant_status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `organ_transplants_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `organ_donors` (`id`),
  CONSTRAINT `organ_transplants_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `organ_recipients` (`id`),
  CONSTRAINT `organ_transplants_ibfk_3` FOREIGN KEY (`surgeon_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `organ_transplants_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;





-- ============================================================================
-- INSURANCE MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `insurance_companies`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `insurance_companies`;
CREATE TABLE `insurance_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(200) NOT NULL,
  `company_code` varchar(10) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `policy_types` text DEFAULT NULL,
  `coverage_details` text DEFAULT NULL,
  `claim_process` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `insurance_companies_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `patient_insurance_policies`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `patient_insurance_policies`;
CREATE TABLE `patient_insurance_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `insurance_company_id` int(11) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `policy_holder_name` varchar(100) NOT NULL,
  `relationship` enum('self','spouse','parent','child','other') DEFAULT 'self',
  `policy_type` varchar(100) DEFAULT NULL,
  `coverage_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deductible_amount` decimal(10,2) DEFAULT 0.00,
  `co_payment_percentage` decimal(5,2) DEFAULT 0.00,
  `start_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `premium_amount` decimal(10,2) DEFAULT 0.00,
  `premium_frequency` enum('monthly','quarterly','half_yearly','yearly') DEFAULT 'yearly',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `policy_number` (`policy_number`),
  KEY `patient_id` (`patient_id`),
  KEY `insurance_company_id` (`insurance_company_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `patient_insurance_policies_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `patient_insurance_policies_ibfk_2` FOREIGN KEY (`insurance_company_id`) REFERENCES `insurance_companies` (`id`),
  CONSTRAINT `patient_insurance_policies_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `insurance_claims`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `insurance_claims`;
CREATE TABLE `insurance_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `claim_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `insurance_company_id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `claim_date` date NOT NULL,
  `service_date` date NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_details` text DEFAULT NULL,
  `claimed_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `approved_amount` decimal(10,2) DEFAULT 0.00,
  `deductible_amount` decimal(10,2) DEFAULT 0.00,
  `co_payment_amount` decimal(10,2) DEFAULT 0.00,
  `settlement_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','under_review','approved','rejected','settled') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `settlement_date` date DEFAULT NULL,
  `documents_submitted` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `claim_number` (`claim_number`),
  KEY `patient_id` (`patient_id`),
  KEY `insurance_company_id` (`insurance_company_id`),
  KEY `policy_id` (`policy_id`),
  KEY `bill_id` (`bill_id`),
  KEY `created_by` (`created_by`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `insurance_claims_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `insurance_claims_ibfk_2` FOREIGN KEY (`insurance_company_id`) REFERENCES `insurance_companies` (`id`),
  CONSTRAINT `insurance_claims_ibfk_3` FOREIGN KEY (`policy_id`) REFERENCES `patient_insurance_policies` (`id`),
  CONSTRAINT `insurance_claims_ibfk_4` FOREIGN KEY (`bill_id`) REFERENCES `billing` (`id`) ON DELETE SET NULL,
  CONSTRAINT `insurance_claims_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `insurance_claims_ibfk_6` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `insurance_pre_authorizations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `insurance_pre_authorizations`;
CREATE TABLE `insurance_pre_authorizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `authorization_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `insurance_company_id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `treatment_date` date DEFAULT NULL,
  `diagnosis` text NOT NULL,
  `proposed_treatment` text NOT NULL,
  `estimated_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `authorized_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','approved','rejected','expired') DEFAULT 'pending',
  `approval_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `conditions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `authorization_number` (`authorization_number`),
  KEY `patient_id` (`patient_id`),
  KEY `insurance_company_id` (`insurance_company_id`),
  KEY `policy_id` (`policy_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `created_by` (`created_by`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_2` FOREIGN KEY (`insurance_company_id`) REFERENCES `insurance_companies` (`id`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_3` FOREIGN KEY (`policy_id`) REFERENCES `patient_insurance_policies` (`id`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_4` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_6` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ADDITIONAL TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `activity_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `table_name` (`table_name`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `action_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BLOOD BANK MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `blood_inventory`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_inventory`;
CREATE TABLE `blood_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_available` int(11) NOT NULL DEFAULT 0,
  `collection_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `source_donation_id` int(11) DEFAULT NULL,
  `status` enum('available','expired','used') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `blood_group` (`blood_group`),
  KEY `expiry_date` (`expiry_date`),
  KEY `source_donation_id` (`source_donation_id`),
  CONSTRAINT `blood_inventory_ibfk_1` FOREIGN KEY (`source_donation_id`) REFERENCES `blood_donations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `blood_donations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_donations`;
CREATE TABLE `blood_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_patient_id` int(11) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_collected` int(11) NOT NULL,
  `donation_date` date NOT NULL,
  `status` enum('completed','pending','rejected') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `donor_patient_id` (`donor_patient_id`),
  KEY `created_by` (`created_by`),
  KEY `donation_date` (`donation_date`),
  KEY `blood_group` (`blood_group`),
  CONSTRAINT `blood_donations_ibfk_1` FOREIGN KEY (`donor_patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `blood_donations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `blood_requests`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_requests`;
CREATE TABLE `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_needed` int(11) NOT NULL,
  `urgency` enum('low','medium','high','critical') NOT NULL,
  `status` enum('pending','fulfilled','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `request_date` date NOT NULL DEFAULT curdate(),
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `created_by` (`created_by`),
  KEY `request_date` (`request_date`),
  KEY `urgency` (`urgency`),
  CONSTRAINT `blood_requests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `blood_requests_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ORGAN DONATION MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `organ_donors`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_donors`;
CREATE TABLE `organ_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `organs_to_donate` text NOT NULL,
  `medical_conditions` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','deceased') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_id` (`patient_id`),
  CONSTRAINT `organ_donors_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `organ_donations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_donations`;
CREATE TABLE `organ_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_patient_id` int(11) NOT NULL,
  `recipient_patient_id` int(11) DEFAULT NULL,
  `organ_type` varchar(50) NOT NULL,
  `donation_date` date NOT NULL,
  `status` enum('completed','pending','cancelled') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `donor_patient_id` (`donor_patient_id`),
  KEY `recipient_patient_id` (`recipient_patient_id`),
  KEY `created_by` (`created_by`),
  KEY `donation_date` (`donation_date`),
  CONSTRAINT `organ_donations_ibfk_1` FOREIGN KEY (`donor_patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `organ_donations_ibfk_2` FOREIGN KEY (`recipient_patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organ_donations_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `organ_requests`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_requests`;
CREATE TABLE `organ_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `organ_type` varchar(50) NOT NULL,
  `urgency` enum('low','medium','high','critical') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `medical_condition` text NOT NULL,
  `status` enum('pending','fulfilled','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `request_date` date NOT NULL DEFAULT curdate(),
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `created_by` (`created_by`),
  KEY `request_date` (`request_date`),
  KEY `urgency` (`urgency`),
  CONSTRAINT `organ_requests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `organ_requests_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `organ_availability`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_availability`;
CREATE TABLE `organ_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organ_type` varchar(50) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `donor_patient_id` int(11) NOT NULL,
  `status` enum('available','reserved','used') DEFAULT 'available',
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `organ_type` (`organ_type`),
  KEY `blood_group` (`blood_group`),
  KEY `donor_patient_id` (`donor_patient_id`),
  KEY `status` (`status`),
  CONSTRAINT `organ_availability_ibfk_1` FOREIGN KEY (`donor_patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `organ_transplants`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_transplants`;
CREATE TABLE `organ_transplants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `surgery_date` date NOT NULL,
  `surgeon_id` int(11) NOT NULL,
  `hospital_name` varchar(200) NOT NULL,
  `surgery_notes` text DEFAULT NULL,
  `status` enum('scheduled','in_progress','successful','failed','cancelled') DEFAULT 'scheduled',
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `donor_id` (`donor_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `surgeon_id` (`surgeon_id`),
  KEY `status` (`status`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `organ_transplants_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `organ_donors` (`id`),
  CONSTRAINT `organ_transplants_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `organ_recipients` (`id`),
  CONSTRAINT `organ_transplants_ibfk_3` FOREIGN KEY (`surgeon_id`) REFERENCES `users` (`id`),
  CONSTRAINT `organ_transplants_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE DATA INSERTS
-- ============================================================================

-- Insert roles
INSERT INTO `roles` (`role_name`, `role_display`, `permissions`, `is_active`) VALUES
('admin', 'Administrator', 'all', 1),
('doctor', 'Doctor', 'patients,appointments,prescriptions,lab_results', 1),
('nurse', 'Nurse', 'patients,appointments,basic_care', 1),
('receptionist', 'Receptionist', 'patients,appointments,billing', 1),
('pharmacy_staff', 'Pharmacy Staff', 'pharmacy,prescriptions', 1),
('lab_technician', 'Lab Technician', 'laboratory,lab_results', 1),
('patient', 'Patient', 'view_own_records', 1),
('intern_doctor', 'Intern Doctor', 'patients,appointments,prescriptions', 1),
('intern_pharmacy', 'Intern Pharmacy', 'pharmacy', 1),
('intern_lab', 'Intern Lab', 'laboratory', 1);

-- Insert users with proper login credentials
INSERT INTO `users` (`username`, `email`, `password`, `role`, `role_display`, `is_active`) VALUES
('admin', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrator', 1),
('dr_smith', 'dr.smith@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Doctor', 1),
('dr_johnson', 'dr.johnson@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Doctor', 1),
('nurse_mary', 'mary@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nurse', 'Nurse', 1),
('receptionist', 'reception@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'receptionist', 'Receptionist', 1),
('pharmacy', 'pharmacy@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'pharmacy_staff', 'Pharmacy Staff', 1),
('lab_tech', 'lab@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lab_technician', 'Lab Technician', 1),
('patient1', 'john.doe@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Patient', 1),
('patient2', 'jane.smith@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Patient', 1);

-- Insert patients
INSERT INTO `patients` (`patient_id`, `first_name`, `last_name`, `email`, `phone`, `date_of_birth`, `gender`, `blood_group`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `medical_history`, `allergies`) VALUES
('PAT001', 'John', 'Doe', 'john.doe@email.com', '+91-9876543210', '1990-05-15', 'male', 'O+', '123 Main Street, City, State 12345', 'Jane Doe', '+91-9876543211', 'No significant medical history', 'None known'),
('PAT002', 'Jane', 'Smith', 'jane.smith@email.com', '+91-9876543212', '1985-08-22', 'female', 'A+', '456 Oak Avenue, City, State 12345', 'Bob Smith', '+91-9876543213', 'Hypertension', 'Penicillin'),
('PAT003', 'Robert', 'Johnson', 'robert.j@email.com', '+91-9876543214', '1978-12-10', 'male', 'B+', '789 Pine Road, City, State 12345', 'Mary Johnson', '+91-9876543215', 'Diabetes Type 2', 'None known'),
('PAT004', 'Emily', 'Davis', 'emily.d@email.com', '+91-9876543216', '1992-03-18', 'female', 'AB+', '321 Elm Street, City, State 12345', 'Tom Davis', '+91-9876543217', 'Asthma', 'Dust, Pollen'),
('PAT005', 'Michael', 'Wilson', 'michael.w@email.com', '+91-9876543218', '1988-07-25', 'male', 'O-', '654 Maple Lane, City, State 12345', 'Sarah Wilson', '+91-9876543219', 'No significant medical history', 'Shellfish');

-- Insert doctors
INSERT INTO `doctors` (`user_id`, `doctor_name`, `specialization`, `qualification`, `experience_years`, `consultation_fee`, `phone`, `email`, `schedule`) VALUES
(2, 'Dr. John Smith', 'Cardiology', 'MBBS, MD Cardiology', 15, 1500.00, '+91-9876543220', 'dr.smith@hospital.com', 'Mon-Fri: 9:00 AM - 5:00 PM'),
(3, 'Dr. Sarah Johnson', 'Pediatrics', 'MBBS, MD Pediatrics', 12, 1200.00, '+91-9876543221', 'dr.johnson@hospital.com', 'Mon-Sat: 10:00 AM - 6:00 PM'),
(NULL, 'Dr. Michael Brown', 'Orthopedics', 'MBBS, MS Orthopedics', 18, 1800.00, '+91-9876543222', 'dr.brown@hospital.com', 'Tue-Sat: 8:00 AM - 4:00 PM'),
(NULL, 'Dr. Lisa Davis', 'Dermatology', 'MBBS, MD Dermatology', 10, 1000.00, '+91-9876543223', 'dr.davis@hospital.com', 'Mon-Wed-Fri: 11:00 AM - 7:00 PM'),
(NULL, 'Dr. James Wilson', 'Neurology', 'MBBS, DM Neurology', 20, 2000.00, '+91-9876543224', 'dr.wilson@hospital.com', 'Mon-Thu: 9:00 AM - 3:00 PM');

-- Insert staff
INSERT INTO `staff` (`user_id`, `staff_id`, `first_name`, `last_name`, `email`, `phone`, `department`, `position`, `hire_date`, `salary`) VALUES
(4, 'STAFF001', 'Mary', 'Johnson', 'mary@hospital.com', '+91-9876543225', 'Nursing', 'Senior Nurse', '2020-01-15', 45000.00),
(5, 'STAFF002', 'David', 'Brown', 'reception@hospital.com', '+91-9876543226', 'Administration', 'Receptionist', '2021-03-10', 35000.00),
(6, 'STAFF003', 'Lisa', 'Wilson', 'pharmacy@hospital.com', '+91-9876543227', 'Pharmacy', 'Pharmacist', '2019-06-20', 50000.00),
(7, 'STAFF004', 'Tom', 'Davis', 'lab@hospital.com', '+91-9876543228', 'Laboratory', 'Lab Technician', '2020-09-05', 40000.00),
(NULL, 'STAFF005', 'Anna', 'Taylor', 'anna@hospital.com', '+91-9876543229', 'Nursing', 'Nurse', '2022-01-12', 38000.00);

-- Insert appointments
INSERT INTO `appointments` (`patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `appointment_type`, `status`, `notes`, `created_by`) VALUES
(1, 1, '2024-12-20', '10:00:00', 'consultation', 'completed', 'Regular checkup completed', 1),
(2, 2, '2024-12-21', '11:30:00', 'follow_up', 'scheduled', 'Follow-up for hypertension', 1),
(3, 1, '2024-12-22', '14:00:00', 'consultation', 'scheduled', 'Chest pain consultation', 1),
(4, 2, '2024-12-23', '09:15:00', 'routine_checkup', 'scheduled', 'Annual pediatric checkup', 1),
(5, 3, '2024-12-24', '15:30:00', 'consultation', 'completed', 'Knee pain consultation completed', 1);

-- Insert pharmacy items
INSERT INTO `pharmacy` (`medicine_name`, `generic_name`, `manufacturer`, `category`, `unit_price`, `stock_quantity`, `reorder_level`, `expiry_date`, `batch_number`) VALUES
('Paracetamol 500mg', 'Paracetamol', 'ABC Pharma', 'Analgesic', 2.50, 1000, 100, '2025-12-31', 'PAR001'),
('Amoxicillin 250mg', 'Amoxicillin', 'XYZ Medicines', 'Antibiotic', 8.75, 500, 50, '2025-06-30', 'AMX001'),
('Aspirin 75mg', 'Acetylsalicylic Acid', 'DEF Pharma', 'Antiplatelet', 1.25, 800, 80, '2025-09-15', 'ASP001'),
('Metformin 500mg', 'Metformin HCl', 'GHI Medicines', 'Antidiabetic', 3.20, 600, 60, '2025-11-20', 'MET001'),
('Lisinopril 10mg', 'Lisinopril', 'JKL Pharma', 'ACE Inhibitor', 5.50, 400, 40, '2025-08-10', 'LIS001');

-- Insert laboratory tests
INSERT INTO `laboratory` (`test_name`, `test_category`, `description`, `normal_range`, `unit`, `price`, `sample_type`, `preparation_instructions`) VALUES
('Complete Blood Count', 'Hematology', 'Comprehensive blood analysis', 'Varies by component', 'Various', 500.00, 'Blood', 'No special preparation required'),
('Lipid Profile', 'Biochemistry', 'Cholesterol and lipid analysis', 'Total Cholesterol: <200 mg/dL', 'mg/dL', 800.00, 'Blood', '12-hour fasting required'),
('Liver Function Test', 'Biochemistry', 'Liver enzyme analysis', 'ALT: 7-56 U/L, AST: 10-40 U/L', 'U/L', 600.00, 'Blood', 'No special preparation required'),
('Kidney Function Test', 'Biochemistry', 'Kidney function analysis', 'Creatinine: 0.6-1.2 mg/dL', 'mg/dL', 550.00, 'Blood', 'No special preparation required'),
('Thyroid Function Test', 'Endocrinology', 'Thyroid hormone analysis', 'TSH: 0.4-4.0 mIU/L', 'mIU/L', 700.00, 'Blood', 'No special preparation required'),
('Urine Analysis', 'Microbiology', 'Complete urine examination', 'No abnormal findings', 'Various', 300.00, 'Urine', 'Clean catch midstream sample'),
('Blood Sugar (Fasting)', 'Biochemistry', 'Fasting glucose level', '70-100 mg/dL', 'mg/dL', 200.00, 'Blood', '8-12 hour fasting required'),
('Blood Sugar (Random)', 'Biochemistry', 'Random glucose level', '<140 mg/dL', 'mg/dL', 150.00, 'Blood', 'No special preparation required');

-- Insert equipment
INSERT INTO `equipment` (`equipment_name`, `equipment_id`, `category`, `manufacturer`, `model`, `serial_number`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `status`, `location`, `last_maintenance`, `next_maintenance`) VALUES
('X-Ray Machine', 'EQ001', 'Radiology', 'Siemens', 'MOBILETT XP Digital', 'SN123456', '2022-03-15', 1500000.00, '2025-03-15', 'active', 'Radiology Department', '2024-11-01', '2025-02-01'),
('ECG Machine', 'EQ002', 'Cardiology', 'Philips', 'PageWriter TC70', 'SN789012', '2021-08-20', 350000.00, '2024-08-20', 'active', 'Cardiology Ward', '2024-10-15', '2025-01-15'),
('Ultrasound Machine', 'EQ003', 'Radiology', 'GE Healthcare', 'LOGIQ P9', 'SN345678', '2023-01-10', 2500000.00, '2026-01-10', 'active', 'Ultrasound Room', '2024-12-01', '2025-03-01'),
('Ventilator', 'EQ004', 'ICU', 'Medtronic', 'Puritan Bennett 980', 'SN901234', '2022-11-05', 800000.00, '2025-11-05', 'maintenance', 'ICU', '2024-11-20', '2024-12-20'),
('Defibrillator', 'EQ005', 'Emergency', 'Zoll', 'R Series ALS', 'SN567890', '2023-06-12', 450000.00, '2026-06-12', 'active', 'Emergency Department', '2024-09-10', '2024-12-10');

-- Insert sample prescriptions
INSERT INTO `prescriptions` (`prescription_id`, `patient_id`, `doctor_id`, `appointment_id`, `prescription_date`, `status`, `notes`, `prescribed_by`) VALUES
('RX001', 1, 1, 1, '2024-12-20', 'dispensed', 'Post consultation medication', 2),
('RX002', 2, 2, NULL, '2024-12-18', 'pending', 'Hypertension management', 3),
('RX003', 3, 1, NULL, '2024-12-19', 'dispensed', 'Diabetes management', 2);

-- Insert prescription details
INSERT INTO `prescription_details` (`prescription_id`, `medicine_id`, `dosage`, `frequency`, `duration`, `instructions`, `quantity`) VALUES
(1, 1, '500mg', 'Twice daily', '5 days', 'Take after meals', 10),
(1, 3, '75mg', 'Once daily', '30 days', 'Take in morning', 30),
(2, 5, '10mg', 'Once daily', '30 days', 'Take in morning', 30),
(3, 4, '500mg', 'Twice daily', '30 days', 'Take before meals', 60);

-- Insert sample lab tests
INSERT INTO `lab_tests` (`test_id`, `patient_id`, `doctor_id`, `test_date`, `status`, `priority`, `notes`, `total_amount`, `created_by`) VALUES
('LT20241220001', 1, 1, '2024-12-20', 'completed', 'normal', 'Routine blood work', 500.00, 2),
('LT20241221001', 2, 2, '2024-12-21', 'pending', 'high', 'Lipid profile for hypertension', 800.00, 3),
('LT20241222001', 3, 1, '2024-12-22', 'in_progress', 'normal', 'Diabetes monitoring', 750.00, 2);

-- Insert sample lab results
INSERT INTO `laboratory_results` (`test_id`, `patient_id`, `doctor_id`, `result_value`, `reference_range`, `unit`, `notes`, `test_date`, `status`, `conducted_by`) VALUES
(1, 1, 1, '12.5', '12.0-15.5', 'g/dL', 'Normal hemoglobin level', '2024-12-20', 'completed', 7),
(1, 1, 1, '4500', '4000-11000', '/μL', 'Normal WBC count', '2024-12-20', 'completed', 7);

-- Insert sample pharmacy sales
INSERT INTO `pharmacy_sales` (`sale_id`, `patient_id`, `sale_date`, `total_amount`, `discount_amount`, `payment_status`, `payment_method`, `sold_by`) VALUES
('PS001', 1, '2024-12-20', 105.00, 5.00, 'paid', 'cash', 6),
('PS002', 2, '2024-12-18', 165.00, 0.00, 'pending', NULL, 6);

-- Insert pharmacy sale items
INSERT INTO `pharmacy_sale_items` (`sale_id`, `medicine_id`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 10, 2.50, 25.00),
(1, 3, 30, 1.25, 37.50),
(1, 2, 5, 8.75, 43.75),
(2, 5, 30, 5.50, 165.00);

-- Insert sample insurance companies
INSERT INTO `insurance_companies` (`company_name`, `company_code`, `contact_person`, `contact_phone`, `contact_email`, `address`, `website`, `policy_types`, `is_active`, `created_by`) VALUES
('Star Health Insurance', 'STAR', 'Rajesh Kumar', '+91-9876543230', 'rajesh@starhealth.com', '123 Insurance Plaza, Mumbai, Maharashtra', 'www.starhealth.in', 'Individual, Family, Senior Citizen', 1, 1),
('HDFC ERGO Health Insurance', 'HDFC', 'Priya Sharma', '+91-9876543231', 'priya@hdfcergo.com', '456 HDFC Tower, Delhi, Delhi', 'www.hdfcergo.com', 'Individual, Group, Critical Illness', 1, 1),
('ICICI Lombard Health Insurance', 'ICICI', 'Amit Patel', '+91-9876543232', 'amit@icicilombard.com', '789 ICICI Building, Bangalore, Karnataka', 'www.icicilombard.com', 'Individual, Family, Travel', 1, 1);

-- Insert sample patient insurance policies
INSERT INTO `patient_insurance_policies` (`patient_id`, `insurance_company_id`, `policy_number`, `policy_holder_name`, `relationship`, `policy_type`, `coverage_amount`, `deductible_amount`, `co_payment_percentage`, `start_date`, `expiry_date`, `premium_amount`, `premium_frequency`, `created_by`) VALUES
(1, 1, 'STAR123456789', 'John Doe', 'self', 'Individual Health', 500000.00, 10000.00, 10.00, '2024-01-01', '2024-12-31', 15000.00, 'yearly', 1),
(2, 2, 'HDFC987654321', 'Jane Smith', 'self', 'Family Health', 1000000.00, 15000.00, 15.00, '2024-06-01', '2025-05-31', 25000.00, 'yearly', 1),
(3, 3, 'ICICI456789123', 'Robert Johnson', 'self', 'Individual Health', 300000.00, 5000.00, 20.00, '2024-03-01', '2025-02-28', 12000.00, 'yearly', 1);

-- Insert sample insurance claims
INSERT INTO `insurance_claims` (`claim_number`, `patient_id`, `insurance_company_id`, `policy_id`, `claim_date`, `service_date`, `diagnosis`, `treatment_details`, `claimed_amount`, `approved_amount`, `status`, `created_by`) VALUES
('CLM001', 1, 1, 1, '2024-12-21', '2024-12-20', 'Routine Health Checkup', 'Complete blood count and consultation', 2000.00, 1800.00, 'approved', 1),
('CLM002', 2, 2, 2, '2024-12-19', '2024-12-18', 'Hypertension', 'Blood pressure monitoring and medication', 3500.00, NULL, 'under_review', 1);

-- ============================================================================
-- SAMPLE DATA FOR BLOOD BANK MANAGEMENT
-- ============================================================================

-- Sample data for blood_donations
INSERT INTO `blood_donations` (`donor_patient_id`, `blood_group`, `units_collected`, `donation_date`, `status`, `notes`, `created_by`) VALUES
(1, 'O+', 1, '2024-11-15', 'completed', 'Regular donor, excellent vitals', 1),
(2, 'A+', 1, '2024-11-18', 'completed', 'First time donor, did well', 1),
(3, 'B+', 1, '2024-11-20', 'completed', 'Regular donor, no issues', 1),
(5, 'O-', 2, '2024-11-10', 'completed', 'Double red cell donation', 1),
(1, 'O+', 1, '2024-12-01', 'completed', 'Follow-up donation', 1);

-- Sample data for blood_inventory
INSERT INTO `blood_inventory` (`blood_group`, `units_available`, `collection_date`, `expiry_date`, `source_donation_id`, `status`) VALUES
('A+', 8, '2024-11-18', '2025-01-15', 2, 'available'),
('A-', 3, '2024-11-20', '2025-01-20', NULL, 'available'),
('B+', 12, '2024-11-20', '2025-01-18', 3, 'available'),
('B-', 4, '2024-11-22', '2025-01-22', NULL, 'available'),
('AB+', 6, '2024-11-25', '2025-01-25', NULL, 'available'),
('AB-', 2, '2024-11-28', '2025-01-28', NULL, 'available'),
('O+', 15, '2024-11-15', '2025-01-15', 1, 'available'),
('O-', 10, '2024-11-10', '2025-01-10', 4, 'available');

-- Sample data for blood_requests
INSERT INTO `blood_requests` (`patient_id`, `blood_group`, `units_needed`, `urgency`, `status`, `notes`, `request_date`, `created_by`) VALUES
(1, 'O+', 2, 'high', 'pending', 'Surgery scheduled, blood needed for transfusion', '2024-12-01', 1),
(3, 'A+', 3, 'critical', 'fulfilled', 'Emergency surgery, massive blood loss', '2024-11-30', 1),
(5, 'B+', 1, 'medium', 'pending', 'Elective surgery preparation', '2024-12-05', 1),
(7, 'AB+', 2, 'high', 'pending', 'Liver transplant surgery', '2024-12-03', 1),
(2, 'O-', 1, 'critical', 'fulfilled', 'Emergency trauma case', '2024-11-28', 1);

-- ============================================================================
-- SAMPLE DATA FOR ORGAN DONATION MANAGEMENT
-- ============================================================================

-- Sample data for organ_donors
INSERT INTO `organ_donors` (`patient_id`, `organs_to_donate`, `medical_conditions`, `emergency_contact_name`, `emergency_contact_phone`, `status`) VALUES
(1, 'Heart,Liver,Kidney', 'No significant medical history', 'Kavita Mehta', '9876543221', 'active'),
(2, 'Kidney,Cornea', 'Healthy individual with no known medical issues', 'Suresh Verma', '9876543223', 'active'),
(3, 'Lung,Kidney', 'Controlled hypertension, otherwise healthy', 'Pooja Agarwal', '9876543225', 'active'),
(5, 'Pancreas,Cornea', 'No known medical issues', 'Vikash Joshi', '9876543227', 'active'),
(7, 'Heart,Liver,Kidney', 'Excellent health, regular donor', 'Priya Reddy', '9876543229', 'active');

-- Sample data for organ_donations
INSERT INTO `organ_donations` (`donor_patient_id`, `recipient_patient_id`, `organ_type`, `donation_date`, `status`, `notes`, `created_by`) VALUES
(1, 8, 'Kidney', '2024-11-20', 'completed', 'Successful kidney transplant, no complications observed', 1),
(2, 9, 'Liver', '2024-11-25', 'completed', 'Liver transplant completed successfully, patient stable', 1),
(3, NULL, 'Cornea', '2024-12-01', 'completed', 'Corneal donation for eye bank', 1);

-- Sample data for organ_requests
INSERT INTO `organ_requests` (`patient_id`, `organ_type`, `urgency`, `blood_group`, `medical_condition`, `status`, `notes`, `request_date`, `created_by`) VALUES
(8, 'Kidney', 'critical', 'O+', 'Chronic kidney disease stage 5, dialysis dependent', 'fulfilled', 'Patient requires urgent kidney transplant', '2024-09-15', 1),
(9, 'Liver', 'critical', 'A+', 'End-stage liver disease, cirrhosis', 'fulfilled', 'Critical condition, needs immediate transplant', '2024-10-01', 1),
(10, 'Heart', 'high', 'B+', 'Dilated cardiomyopathy, heart failure', 'pending', 'Heart failure, transplant only option', '2024-10-15', 1),
(1, 'Cornea', 'low', 'O+', 'Corneal opacity bilateral, vision impairment', 'pending', 'Vision restoration needed', '2024-11-01', 1),
(3, 'Lung', 'high', 'B+', 'Pulmonary fibrosis, respiratory failure', 'pending', 'Progressive lung disease', '2024-11-10', 1);

-- Sample data for organ_availability
INSERT INTO `organ_availability` (`organ_type`, `blood_group`, `donor_patient_id`, `status`, `expiry_date`) VALUES
('Heart', 'O+', 1, 'available', '2024-12-25'),
('Liver', 'A+', 2, 'available', '2024-12-30'),
('Kidney', 'B+', 3, 'available', '2025-01-15'),
('Cornea', 'AB+', 5, 'available', '2025-02-01'),
('Lung', 'O-', 7, 'available', '2024-12-28');








-- ============================================================================
-- LOGIN CREDENTIALS INFORMATION
-- ============================================================================
/*
DEFAULT LOGIN CREDENTIALS (Password: 'password' for all users):

Admin:
- Username: admin
- Password: password
- Role: Administrator

Doctor:
- Username: dr_smith
- Password: password
- Role: Doctor

Nurse:
- Username: nurse_mary
- Password: password
- Role: Nurse

Receptionist:
- Username: receptionist
- Password: password
- Role: Receptionist

Pharmacy Staff:
- Username: pharmacy
- Password: password
- Role: Pharmacy Staff

Lab Technician:
- Username: lab_tech
- Password: password
- Role: Lab Technician

Patients:
- Username: patient1
- Password: password
- Role: Patient

- Username: patient2
- Password: password
- Role: Patient

Note: Please change these default passwords after first login for security.
*/

COMMIT;
-- ============================================================================
-- HOSPITAL CRM - COMPLETE DATABASE SCHEMA
-- ============================================================================
-- Version: 2.0 (Updated with Insurance Management & Patient Portal)
-- Created: 2024
-- Features: Patient Management, Doctor Management, Appointments, Billing, 
--          Insurance, Pharmacy, Laboratory, Prescriptions, Equipment, Staff
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database
CREATE DATABASE IF NOT EXISTS `hospital_crm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hospital_crm`;

-- ============================================================================
-- USER MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `roles`
-- --------------------------------------------------------

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `role_display_name` varchar(100) NOT NULL,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 1,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PATIENT MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `patients`
-- --------------------------------------------------------

CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') NOT NULL DEFAULT 'male',
  `blood_group` varchar(5) DEFAULT NULL,
  `emergency_contact_name` varchar(200) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `medical_history` text,
  `allergies` text,
  `photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_id` (`patient_id`),
  UNIQUE KEY `email` (`email`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DOCTOR MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `doctors`
-- --------------------------------------------------------

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_name` varchar(200) NOT NULL,
  `specialization` varchar(200) DEFAULT NULL,
  `qualification` varchar(500) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `experience_years` int(11) DEFAULT 0,
  `schedule` text,
  `photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- STAFF MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `staff`
-- --------------------------------------------------------

CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT 0.00,
  `date_of_joining` date DEFAULT NULL,
  `address` text,
  `emergency_contact` varchar(200) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- APPOINTMENT MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `appointments`
-- --------------------------------------------------------

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `appointment_type` enum('consultation','follow_up','routine_checkup','emergency') DEFAULT 'consultation',
  `reason` text,
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_id` (`appointment_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BILLING MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `billing`
-- --------------------------------------------------------

CREATE TABLE `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `bill_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid') DEFAULT 'pending',
  `payment_method` enum('cash','card','upi','cheque','bank_transfer','insurance') DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_id` (`bill_id`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `billing_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSURANCE MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `insurance_companies`
-- --------------------------------------------------------

CREATE TABLE `insurance_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(200) NOT NULL,
  `company_code` varchar(10) NOT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `address` text,
  `website` varchar(255) DEFAULT NULL,
  `coverage_types` varchar(500) DEFAULT NULL,
  `network_hospitals` text,
  `cashless_limit` decimal(10,2) DEFAULT 0.00,
  `reimbursement_percentage` decimal(5,2) DEFAULT 80.00,
  `claim_settlement_days` int(11) DEFAULT 30,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `insurance_companies_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `patient_insurance_policies`
-- --------------------------------------------------------

CREATE TABLE `patient_insurance_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `insurance_company_id` int(11) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `policy_holder_name` varchar(200) NOT NULL,
  `relationship_to_patient` enum('self','spouse','child','parent','other') DEFAULT 'self',
  `policy_type` enum('individual','family','group','corporate') DEFAULT 'individual',
  `coverage_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deductible_amount` decimal(10,2) DEFAULT 0.00,
  `co_payment_percentage` decimal(5,2) DEFAULT 0.00,
  `policy_start_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `premium_amount` decimal(10,2) DEFAULT 0.00,
  `premium_frequency` enum('monthly','quarterly','half_yearly','yearly') DEFAULT 'yearly',
  `cashless_available` tinyint(1) DEFAULT 1,
  `pre_authorization_required` tinyint(1) DEFAULT 0,
  `exclusions` text,
  `documents_path` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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

CREATE TABLE `insurance_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `claim_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `insurance_company_id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `claim_type` enum('cashless','reimbursement','emergency') DEFAULT 'cashless',
  `treatment_type` enum('outpatient','inpatient','emergency','surgery','diagnostic') DEFAULT 'outpatient',
  `admission_date` date DEFAULT NULL,
  `discharge_date` date DEFAULT NULL,
  `diagnosis` text,
  `treatment_details` text,
  `claimed_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `approved_amount` decimal(12,2) DEFAULT 0.00,
  `deductible_amount` decimal(10,2) DEFAULT 0.00,
  `co_payment_amount` decimal(10,2) DEFAULT 0.00,
  `final_settlement_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('pending','under_review','approved','rejected','paid','cancelled') DEFAULT 'pending',
  `submission_date` date NOT NULL,
  `processed_date` date DEFAULT NULL,
  `settlement_date` date DEFAULT NULL,
  `rejection_reason` text,
  `notes` text,
  `documents_submitted` text,
  `pre_authorization_number` varchar(100) DEFAULT NULL,
  `pre_authorization_amount` decimal(10,2) DEFAULT 0.00,
  `created_by` int(11) DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `claim_number` (`claim_number`),
  KEY `patient_id` (`patient_id`),
  KEY `policy_id` (`policy_id`),
  KEY `insurance_company_id` (`insurance_company_id`),
  KEY `bill_id` (`bill_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `created_by` (`created_by`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `insurance_claims_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `insurance_claims_ibfk_2` FOREIGN KEY (`policy_id`) REFERENCES `patient_insurance_policies` (`id`),
  CONSTRAINT `insurance_claims_ibfk_3` FOREIGN KEY (`insurance_company_id`) REFERENCES `insurance_companies` (`id`),
  CONSTRAINT `insurance_claims_ibfk_4` FOREIGN KEY (`bill_id`) REFERENCES `billing` (`id`),
  CONSTRAINT `insurance_claims_ibfk_5` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `insurance_claims_ibfk_6` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `insurance_claims_ibfk_7` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `insurance_pre_authorizations`
-- --------------------------------------------------------

CREATE TABLE `insurance_pre_authorizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `authorization_number` varchar(50) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `insurance_company_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `treatment_type` varchar(200) NOT NULL,
  `diagnosis` text,
  `estimated_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `authorized_amount` decimal(10,2) DEFAULT 0.00,
  `valid_from` date NOT NULL,
  `valid_until` date NOT NULL,
  `status` enum('pending','approved','rejected','expired','used') DEFAULT 'pending',
  `approval_date` date DEFAULT NULL,
  `rejection_reason` text,
  `conditions` text,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `authorization_number` (`authorization_number`),
  KEY `patient_id` (`patient_id`),
  KEY `policy_id` (`policy_id`),
  KEY `insurance_company_id` (`insurance_company_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_2` FOREIGN KEY (`policy_id`) REFERENCES `patient_insurance_policies` (`id`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_3` FOREIGN KEY (`insurance_company_id`) REFERENCES `insurance_companies` (`id`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_4` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `insurance_pre_authorizations_ibfk_6` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHARMACY MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `pharmacy`
-- --------------------------------------------------------

CREATE TABLE `pharmacy` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `medicine_name` varchar(200) NOT NULL,
  `generic_name` varchar(200) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 10,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `pharmacy_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `pharmacy_sales`
-- --------------------------------------------------------

CREATE TABLE `pharmacy_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` varchar(20) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','card','upi','insurance') DEFAULT 'cash',
  `payment_status` enum('pending','paid','partial') NOT NULL DEFAULT 'pending',
  `sale_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_id` (`sale_id`),
  KEY `patient_id` (`patient_id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `pharmacy_sales_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `pharmacy_sales_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `pharmacy_sale_items`
-- --------------------------------------------------------

CREATE TABLE `pharmacy_sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `medicine_id` (`medicine_id`),
  CONSTRAINT `pharmacy_sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `pharmacy_sales` (`id`),
  CONSTRAINT `pharmacy_sale_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `pharmacy` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PRESCRIPTION MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `prescriptions`
-- --------------------------------------------------------

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `diagnosis` text,
  `notes` text,
  `status` enum('pending','dispensed','cancelled') DEFAULT 'pending',
  `prescribed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_id` (`prescription_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `prescribed_by` (`prescribed_by`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `prescriptions_ibfk_4` FOREIGN KEY (`prescribed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `prescription_details`
-- --------------------------------------------------------

CREATE TABLE `prescription_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `duration` varchar(100) NOT NULL,
  `instructions` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `medicine_id` (`medicine_id`),
  CONSTRAINT `prescription_details_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`),
  CONSTRAINT `prescription_details_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `pharmacy` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- LABORATORY MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `laboratory`
-- --------------------------------------------------------

CREATE TABLE `laboratory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_name` varchar(200) NOT NULL,
  `test_category` varchar(100) DEFAULT NULL,
  `normal_range` varchar(200) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `laboratory_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `laboratory_results`
-- --------------------------------------------------------

CREATE TABLE `laboratory_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `result_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `test_date` date NOT NULL,
  `result_value` varchar(200) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `notes` text,
  `conducted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `result_id` (`result_id`),
  KEY `patient_id` (`patient_id`),
  KEY `test_id` (`test_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `conducted_by` (`conducted_by`),
  CONSTRAINT `laboratory_results_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `laboratory_results_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `laboratory` (`id`),
  CONSTRAINT `laboratory_results_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `laboratory_results_ibfk_4` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `laboratory_results_ibfk_5` FOREIGN KEY (`conducted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- EQUIPMENT MANAGEMENT TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `equipment`
-- --------------------------------------------------------

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` varchar(20) NOT NULL,
  `equipment_name` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT 0.00,
  `warranty_expiry` date DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `status` enum('active','maintenance','repair','retired') NOT NULL DEFAULT 'active',
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_id` (`equipment_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SYSTEM TABLES
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `activity_logs`
-- --------------------------------------------------------

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `module` varchar(50) DEFAULT 'system',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `notifications`
-- --------------------------------------------------------

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','error') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE DATA INSERTS
-- ============================================================================

-- Insert roles
INSERT INTO `roles` (`id`, `role_name`, `role_display_name`, `description`) VALUES
(1, 'admin', 'Administrator', 'Full system access'),
(2, 'doctor', 'Doctor', 'Medical professional with patient access'),
(3, 'nurse', 'Nurse', 'Nursing staff with limited patient access'),
(4, 'patient', 'Patient', 'Hospital patient with limited access'),
(5, 'receptionist', 'Receptionist', 'Front desk staff for appointments and billing'),
(6, 'pharmacy_staff', 'Pharmacy Staff', 'Pharmacy management access'),
(7, 'lab_technician', 'Lab Technician', 'Laboratory test management'),
(8, 'intern_doctor', 'Intern Doctor', 'Medical intern with supervised access'),
(9, 'intern_pharmacy', 'Pharmacy Intern', 'Pharmacy intern with limited access'),
(10, 'intern_lab', 'Lab Intern', 'Laboratory intern with limited access');

-- Insert users (password is 'admin' for all - $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `first_name`, `last_name`) VALUES
(1, 'admin@hospital.com', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'System', 'Administrator'),
(2, 'doctor1@hospital.com', 'doctor1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Dr. John', 'Smith'),
(3, 'nurse1@hospital.com', 'nurse1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Mary', 'Johnson'),
(4, 'patient1@hospital.com', 'patient1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Robert', 'Wilson'),
(5, 'reception@hospital.com', 'reception@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Sarah', 'Davis'),
(6, 'pharmacy@hospital.com', 'pharmacy@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 'Anna', 'Rodriguez'),
(7, 'lab@hospital.com', 'lab@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 7, 'Mark', 'Garcia');

-- Insert doctors
INSERT INTO `doctors` (`id`, `doctor_name`, `specialization`, `qualification`, `phone`, `email`, `consultation_fee`, `experience_years`, `created_by`) VALUES
(1, 'Dr. John Smith', 'Cardiology', 'MBBS, MD Cardiology', '+1234567890', 'doctor1@hospital.com', 500.00, 10, 1),
(2, 'Dr. Emily Johnson', 'Pediatrics', 'MBBS, MD Pediatrics', '+1234567891', 'emily.johnson@hospital.com', 400.00, 8, 1),
(3, 'Dr. Michael Brown', 'Orthopedics', 'MBBS, MS Orthopedics', '+1234567892', 'michael.brown@hospital.com', 600.00, 12, 1),
(4, 'Dr. Sarah Wilson', 'Dermatology', 'MBBS, MD Dermatology', '+1234567893', 'sarah.wilson@hospital.com', 450.00, 7, 1),
(5, 'Dr. David Lee', 'Neurology', 'MBBS, DM Neurology', '+1234567894', 'david.lee@hospital.com', 700.00, 15, 1);

-- Insert patients
INSERT INTO `patients` (`id`, `patient_id`, `first_name`, `last_name`, `email`, `phone`, `date_of_birth`, `gender`, `blood_group`, `created_by`) VALUES
(1, 'PAT0001', 'Robert', 'Wilson', 'patient1@hospital.com', '+1234567895', '1985-06-15', 'male', 'O+', 1),
(2, 'PAT0002', 'Jennifer', 'Martinez', 'jennifer.martinez@email.com', '+1234567896', '1990-03-22', 'female', 'A+', 1),
(3, 'PAT0003', 'William', 'Anderson', 'william.anderson@email.com', '+1234567897', '1978-11-08', 'male', 'B+', 1),
(4, 'PAT0004', 'Lisa', 'Taylor', 'lisa.taylor@email.com', '+1234567898', '1992-09-14', 'female', 'AB+', 1),
(5, 'PAT0005', 'James', 'Thomas', 'james.thomas@email.com', '+1234567899', '1988-12-03', 'male', 'O-', 1);

-- Insert staff
INSERT INTO `staff` (`id`, `staff_id`, `first_name`, `last_name`, `email`, `phone`, `position`, `department`, `salary`, `date_of_joining`, `created_by`) VALUES
(1, 'STF0001', 'Mary', 'Johnson', 'nurse1@hospital.com', '+1234567900', 'Senior Nurse', 'Nursing', 35000.00, '2020-01-15', 1),
(2, 'STF0002', 'Sarah', 'Davis', 'reception@hospital.com', '+1234567901', 'Receptionist', 'Administration', 25000.00, '2021-03-10', 1),
(3, 'STF0003', 'Mark', 'Garcia', 'mark.garcia@hospital.com', '+1234567902', 'Lab Technician', 'Laboratory', 30000.00, '2019-08-20', 1),
(4, 'STF0004', 'Anna', 'Rodriguez', 'anna.rodriguez@hospital.com', '+1234567903', 'Pharmacist', 'Pharmacy', 40000.00, '2018-05-12', 1),
(5, 'STF0005', 'Tom', 'Miller', 'tom.miller@hospital.com', '+1234567904', 'Cleaner', 'Maintenance', 20000.00, '2022-01-08', 1);

-- Insert pharmacy items
INSERT INTO `pharmacy` (`id`, `medicine_name`, `generic_name`, `category`, `manufacturer`, `unit_price`, `stock_quantity`, `created_by`) VALUES
(1, 'Paracetamol 500mg', 'Paracetamol', 'Analgesic', 'PharmaCorp', 2.50, 500, 1),
(2, 'Amoxicillin 250mg', 'Amoxicillin', 'Antibiotic', 'MediLab', 15.00, 200, 1),
(3, 'Ibuprofen 400mg', 'Ibuprofen', 'NSAID', 'HealthPharma', 5.00, 300, 1),
(4, 'Cetirizine 10mg', 'Cetirizine', 'Antihistamine', 'AllergyMed', 3.00, 250, 1),
(5, 'Omeprazole 20mg', 'Omeprazole', 'PPI', 'GastroMed', 8.00, 150, 1);

-- Insert laboratory tests
INSERT INTO `laboratory` (`id`, `test_name`, `test_category`, `normal_range`, `unit`, `price`, `created_by`) VALUES
(1, 'Complete Blood Count', 'Hematology', 'Various', 'cells/μL', 300.00, 1),
(2, 'Blood Sugar (Fasting)', 'Biochemistry', '70-100', 'mg/dL', 150.00, 1),
(3, 'Lipid Profile', 'Biochemistry', 'Various', 'mg/dL', 400.00, 1),
(4, 'Liver Function Test', 'Biochemistry', 'Various', 'U/L', 350.00, 1),
(5, 'Kidney Function Test', 'Biochemistry', 'Various', 'mg/dL', 300.00, 1),
(6, 'Thyroid Profile', 'Endocrinology', 'Various', 'μIU/mL', 450.00, 1),
(7, 'Urine Analysis', 'Pathology', 'Normal', 'Various', 200.00, 1),
(8, 'ECG', 'Cardiology', 'Normal', 'Various', 250.00, 1);

-- Insert equipment
INSERT INTO `equipment` (`id`, `equipment_id`, `equipment_name`, `category`, `manufacturer`, `model_number`, `purchase_price`, `location`, `created_by`) VALUES
(1, 'EQP0001', 'X-Ray Machine', 'Radiology', 'Siemens', 'XR-2000', 150000.00, 'Radiology Room 1', 1),
(2, 'EQP0002', 'ECG Machine', 'Cardiology', 'Philips', 'ECG-Pro', 25000.00, 'Cardiology Room', 1),
(3, 'EQP0003', 'Ultrasound Machine', 'Radiology', 'GE Healthcare', 'US-3000', 80000.00, 'Ultrasound Room', 1),
(4, 'EQP0004', 'Blood Analyzer', 'Laboratory', 'Abbott', 'BA-500', 120000.00, 'Main Laboratory', 1),
(5, 'EQP0005', 'Ventilator', 'ICU', 'Medtronic', 'V-200', 200000.00, 'ICU Ward', 1);

-- Insert sample appointments
INSERT INTO `appointments` (`id`, `appointment_id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `appointment_type`, `reason`, `created_by`) VALUES
(1, 'APT0001', 1, 1, '2024-02-15', '10:00:00', 'consultation', 'Chest pain consultation', 1),
(2, 'APT0002', 2, 2, '2024-02-15', '11:00:00', 'routine_checkup', 'Regular pediatric checkup', 1),
(3, 'APT0003', 3, 3, '2024-02-16', '09:30:00', 'follow_up', 'Post-surgery follow-up', 1),
(4, 'APT0004', 4, 4, '2024-02-16', '14:00:00', 'consultation', 'Skin rash examination', 1),
(5, 'APT0005', 5, 5, '2024-02-17', '15:30:00', 'consultation', 'Headache and dizziness', 1);

-- Insert sample bills
INSERT INTO `billing` (`id`, `bill_id`, `patient_id`, `appointment_id`, `bill_date`, `total_amount`, `paid_amount`, `payment_status`, `payment_method`, `created_by`) VALUES
(1, 'BILL0001', 1, 1, '2024-02-15', 750.00, 750.00, 'paid', 'cash', 1),
(2, 'BILL0002', 2, 2, '2024-02-15', 600.00, 300.00, 'partial', 'card', 1),
(3, 'BILL0003', 3, 3, '2024-02-16', 800.00, 0.00, 'pending', NULL, 1),
(4, 'BILL0004', 4, 4, '2024-02-16', 650.00, 650.00, 'paid', 'upi', 1),
(5, 'BILL0005', 5, 5, '2024-02-17', 950.00, 500.00, 'partial', 'insurance', 1);

-- Insert sample insurance companies
INSERT INTO `insurance_companies` (`id`, `company_name`, `company_code`, `license_number`, `contact_email`, `contact_phone`, `coverage_types`, `cashless_limit`, `reimbursement_percentage`, `created_by`) VALUES
(1, 'Star Health Insurance', 'STAR', 'LIC001234', 'claims@starhealth.in', '+91-80-12345678', 'Medical,Surgical,Emergency,Maternity', 500000.00, 80.00, 1),
(2, 'HDFC ERGO Health Insurance', 'HDFC', 'LIC002345', 'health@hdfcergo.com', '+91-22-87654321', 'Medical,Surgical,Emergency,Critical Illness', 1000000.00, 85.00, 1),
(3, 'ICICI Lombard Health Insurance', 'ICICI', 'LIC003456', 'claims@icicilombard.com', '+91-22-11223344', 'Medical,Surgical,Emergency,Dental,Vision', 750000.00, 80.00, 1),
(4, 'New India Assurance', 'NIAC', 'LIC004567', 'health@newindia.co.in', '+91-11-55667788', 'Medical,Surgical,Emergency,Ayurveda', 300000.00, 75.00, 1),
(5, 'United India Insurance', 'UIIC', 'LIC005678', 'claims@uiic.co.in', '+91-44-99887766', 'Medical,Surgical,Emergency,Maternity', 400000.00, 80.00, 1);

-- Insert sample patient insurance policies
INSERT INTO `patient_insurance_policies` (`id`, `patient_id`, `insurance_company_id`, `policy_number`, `policy_holder_name`, `coverage_amount`, `deductible_amount`, `policy_start_date`, `expiry_date`, `premium_amount`, `created_by`) VALUES
(1, 1, 1, 'STAR123456789', 'Robert Wilson', 500000.00, 5000.00, '2024-01-01', '2024-12-31', 25000.00, 1),
(2, 2, 2, 'HDFC987654321', 'Jennifer Martinez', 1000000.00, 10000.00, '2024-02-01', '2025-01-31', 45000.00, 1),
(3, 3, 3, 'ICICI456789123', 'William Anderson', 750000.00, 7500.00, '2024-01-15', '2025-01-14', 35000.00, 1),
(4, 4, 4, 'NIAC789123456', 'Lisa Taylor', 300000.00, 3000.00, '2024-03-01', '2025-02-28', 18000.00, 1),
(5, 5, 5, 'UIIC321654987', 'James Thomas', 400000.00, 4000.00, '2024-01-10', '2024-12-31', 22000.00, 1);

-- Insert sample insurance claims
INSERT INTO `insurance_claims` (`id`, `claim_number`, `patient_id`, `policy_id`, `insurance_company_id`, `claim_type`, `treatment_type`, `diagnosis`, `claimed_amount`, `approved_amount`, `status`, `submission_date`, `created_by`) VALUES
(1, 'CLM2024001', 1, 1, 1, 'cashless', 'outpatient', 'Hypertension consultation and medication', 15000.00, 12000.00, 'approved', '2024-01-15', 1),
(2, 'CLM2024002', 2, 2, 2, 'reimbursement', 'inpatient', 'Appendectomy surgery', 85000.00, 80000.00, 'approved', '2024-02-10', 1),
(3, 'CLM2024003', 3, 3, 3, 'cashless', 'diagnostic', 'MRI scan and blood tests', 25000.00, NULL, 'pending', '2024-02-20', 1),
(4, 'CLM2024004', 4, 4, 4, 'emergency', 'emergency', 'Emergency treatment for accident', 45000.00, 35000.00, 'approved', '2024-02-05', 1),
(5, 'CLM2024005', 5, 5, 5, 'cashless', 'outpatient', 'Diabetes management and consultation', 18000.00, NULL, 'under_review', '2024-02-18', 1);

-- Insert sample pre-authorizations
INSERT INTO `insurance_pre_authorizations` (`id`, `authorization_number`, `patient_id`, `policy_id`, `insurance_company_id`, `treatment_type`, `diagnosis`, `estimated_cost`, `authorized_amount`, `valid_from`, `valid_until`, `status`, `created_by`) VALUES
(1, 'AUTH2024001', 1, 1, 1, 'Cardiac Surgery', 'Coronary Artery Disease', 350000.00, 300000.00, '2024-03-01', '2024-03-31', 'approved', 1),
(2, 'AUTH2024002', 2, 2, 2, 'Orthopedic Surgery', 'Knee Replacement', 250000.00, 200000.00, '2024-03-15', '2024-04-14', 'approved', 1),
(3, 'AUTH2024003', 3, 3, 3, 'Cancer Treatment', 'Chemotherapy', 500000.00, NULL, '2024-03-10', '2024-04-09', 'pending', 1);

-- Insert sample prescriptions
INSERT INTO `prescriptions` (`id`, `prescription_id`, `patient_id`, `doctor_id`, `diagnosis`, `notes`, `status`, `prescribed_by`) VALUES
(1, 'PRES0001', 1, 1, 'Hypertension', 'Regular monitoring required', 'dispensed', 2),
(2, 'PRES0002', 2, 2, 'Common Cold', 'Complete the course', 'pending', 2),
(3, 'PRES0003', 3, 3, 'Post-operative care', 'Take as prescribed', 'dispensed', 2);

-- Insert sample prescription details
INSERT INTO `prescription_details` (`prescription_id`, `medicine_id`, `dosage`, `frequency`, `duration`, `instructions`) VALUES
(1, 1, '500mg', 'Twice daily', '7 days', 'Take after meals'),
(1, 3, '400mg', 'Once daily', '5 days', 'Take with water'),
(2, 2, '250mg', 'Three times daily', '5 days', 'Complete the course'),
(2, 4, '10mg', 'Once daily', '3 days', 'Take at bedtime'),
(3, 1, '500mg', 'As needed', '10 days', 'For pain relief');

-- Insert sample lab results
INSERT INTO `laboratory_results` (`id`, `result_id`, `patient_id`, `test_id`, `doctor_id`, `test_date`, `result_value`, `status`, `conducted_by`) VALUES
(1, 'LAB0001', 1, 1, 1, '2024-02-10', 'Normal', 'completed', 7),
(2, 'LAB0002', 2, 2, 2, '2024-02-11', '95 mg/dL', 'completed', 7),
(3, 'LAB0003', 3, 3, 3, '2024-02-12', 'Within normal limits', 'completed', 7),
(4, 'LAB0004', 4, 4, 4, '2024-02-13', 'Elevated ALT', 'completed', 7),
(5, 'LAB0005', 5, 5, 5, '2024-02-14', 'Normal creatinine', 'completed', 7);

COMMIT;

-- ============================================================================
-- END OF HOSPITAL CRM DATABASE SCHEMA
-- ============================================================================

-- Default Login Credentials:
-- Username: admin@hospital.com
-- Password: admin
-- 
-- Other test accounts:
-- doctor1@hospital.com / admin (Doctor)
-- nurse1@hospital.com / admin (Nurse)  
-- patient1@hospital.com / admin (Patient)
-- reception@hospital.com / admin (Receptionist)
-- pharmacy@hospital.com / admin (Pharmacy Staff)
-- lab@hospital.com / admin (Lab Technician)
--
-- ============================================================================
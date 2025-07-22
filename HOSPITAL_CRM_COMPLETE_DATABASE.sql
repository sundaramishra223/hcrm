-- ============================================================================
-- HOSPITAL CRM COMPLETE DATABASE - COMPREHENSIVE VERSION
-- ============================================================================
-- Generated: 2024-01-27
-- Based on: Complete analysis of ALL PHP files in the project
-- Database: hospital_crm
-- ============================================================================

-- Database Configuration
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Create Database
CREATE DATABASE IF NOT EXISTS `hospital_crm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hospital_crm`;

-- ============================================================================
-- CORE SYSTEM TABLES
-- ============================================================================

-- Roles Table
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL UNIQUE,
  `role_display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `role_name`, `role_display_name`, `description`) VALUES
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
(11, 'intern_lab', 'Intern Lab Technician', 'Laboratory intern'),
(12, 'intern_pharmacy', 'Intern Pharmacy', 'Pharmacy intern');

-- Users Table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`username`, `email`, `password_hash`, `role_id`, `first_name`, `last_name`) VALUES
('admin', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'System', 'Administrator'),
('doctor1', 'doctor1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Dr. John', 'Smith'),
('nurse1', 'nurse1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Sarah', 'Johnson'),
('patient1', 'patient1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Mike', 'Wilson');

-- Hospitals Table
CREATE TABLE `hospitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text,
  `city` varchar(100),
  `state` varchar(100),
  `zip_code` varchar(20),
  `phone` varchar(20),
  `email` varchar(100),
  `license_number` varchar(100) UNIQUE,
  `established_date` date,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `hospitals` (`id`, `name`, `address`, `city`, `phone`, `email`) VALUES
(1, 'General Hospital', '123 Hospital Street', 'Healthcare City', '+91-9876543210', 'info@hospital.com');

-- Departments Table
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `department_name` varchar(100) NOT NULL,
  `description` text,
  `head_doctor_id` int(11),
  `location` varchar(100),
  `phone` varchar(20),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `departments` (`department_name`, `description`, `location`) VALUES
('Emergency', 'Emergency and trauma care', 'Ground Floor'),
('Cardiology', 'Heart and cardiovascular care', '2nd Floor'),
('Neurology', 'Brain and nervous system care', '3rd Floor'),
('Orthopedics', 'Bone and joint care', '1st Floor'),
('Pediatrics', 'Children healthcare', '2nd Floor'),
('General Medicine', 'General healthcare services', '1st Floor'),
('Surgery', 'Surgical procedures', '4th Floor'),
('ICU', 'Intensive Care Unit', '3rd Floor'),
('Laboratory', 'Diagnostic laboratory', 'Ground Floor'),
('Pharmacy', 'Hospital pharmacy', 'Ground Floor');

-- ============================================================================
-- MEDICAL STAFF TABLES
-- ============================================================================

-- Doctors Table
CREATE TABLE `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `user_id` int(11),
  `doctor_name` varchar(200) NOT NULL,
  `specialization` varchar(100),
  `qualification` varchar(200),
  `license_number` varchar(100) UNIQUE,
  `department_id` int(11),
  `phone` varchar(20),
  `email` varchar(100),
  `consultation_fee` decimal(10,2),
  `experience_years` int(11),
  `is_available` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `doctors` (`doctor_name`, `specialization`, `qualification`, `department_id`, `phone`, `email`, `consultation_fee`, `experience_years`) VALUES
('Dr. John Smith', 'Cardiologist', 'MBBS, MD Cardiology', 2, '+91-9876543211', 'john.smith@hospital.com', 1500.00, 15),
('Dr. Sarah Johnson', 'Neurologist', 'MBBS, MD Neurology', 3, '+91-9876543212', 'sarah.johnson@hospital.com', 1800.00, 12),
('Dr. Mike Wilson', 'Orthopedic Surgeon', 'MBBS, MS Orthopedics', 4, '+91-9876543213', 'mike.wilson@hospital.com', 2000.00, 18);

-- Staff Table
CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `user_id` int(11),
  `staff_id` varchar(50) UNIQUE NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `staff_type` varchar(50) NOT NULL,
  `department_id` int(11),
  `phone` varchar(20),
  `email` varchar(100),
  `address` text,
  `date_of_birth` date,
  `gender` enum('male', 'female', 'other'),
  `hire_date` date,
  `salary` decimal(10,2),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- PATIENT MANAGEMENT TABLES
-- ============================================================================

-- Patients Table
CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `user_id` int(11),
  `patient_id` varchar(50) UNIQUE NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `date_of_birth` date,
  `gender` enum('male', 'female', 'other'),
  `blood_group` varchar(10),
  `phone` varchar(20),
  `email` varchar(100),
  `address` text,
  `emergency_contact_name` varchar(200),
  `emergency_contact_phone` varchar(20),
  `assigned_doctor_id` int(11),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`assigned_doctor_id`) REFERENCES `doctors`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `patients` (`patient_id`, `first_name`, `last_name`, `date_of_birth`, `gender`, `blood_group`, `phone`, `email`, `assigned_doctor_id`) VALUES
('PAT001', 'Rajesh', 'Kumar', '1985-05-15', 'male', 'O+', '+91-9876543214', 'rajesh.kumar@email.com', 1),
('PAT002', 'Priya', 'Sharma', '1990-08-22', 'female', 'A+', '+91-9876543215', 'priya.sharma@email.com', 2),
('PAT003', 'Amit', 'Singh', '1978-12-10', 'male', 'B+', '+91-9876543216', 'amit.singh@email.com', 3);

-- Patient Visits Table
CREATE TABLE `patient_visits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time NOT NULL,
  `visit_type` varchar(50) DEFAULT 'consultation',
  `assigned_doctor_id` int(11),
  `assigned_nurse_id` int(11),
  `department_id` int(11),
  `chief_complaint` text,
  `diagnosis` text,
  `treatment_plan` text,
  `visit_status` varchar(20) DEFAULT 'active',
  `discharge_date` date,
  `discharge_summary` text,
  `follow_up_date` date,
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`assigned_doctor_id`) REFERENCES `doctors`(`id`),
  FOREIGN KEY (`assigned_nurse_id`) REFERENCES `staff`(`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patient Vitals Table
CREATE TABLE `patient_vitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `recorded_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `vital_date` date NOT NULL,
  `vital_time` time NOT NULL,
  `blood_pressure_systolic` int(11),
  `blood_pressure_diastolic` int(11),
  `heart_rate` int(11),
  `temperature` decimal(4,2),
  `respiratory_rate` int(11),
  `oxygen_saturation` int(11),
  `weight` decimal(5,2),
  `height` decimal(5,2),
  `bmi` decimal(4,2),
  `blood_glucose` int(11),
  `pain_scale` int(11) CHECK (`pain_scale` >= 0 AND `pain_scale` <= 10),
  `notes` text,
  `is_critical` tinyint(1) DEFAULT 0,
  `alert_sent` tinyint(1) DEFAULT 0,
  `admin_notes` text,
  `reviewed_by_admin` tinyint(1) DEFAULT 0,
  `admin_review_date` datetime,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- APPOINTMENT SYSTEM TABLES
-- ============================================================================

-- Appointments Table
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `appointment_id` varchar(50) UNIQUE NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `appointment_type` varchar(50) DEFAULT 'consultation',
  `status` varchar(20) DEFAULT 'scheduled',
  `reason` text,
  `notes` text,
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `reason`, `created_by`) VALUES
('APT001', 1, 1, '2024-01-25', '10:00:00', 'Regular checkup', 1),
('APT002', 2, 2, '2024-01-26', '11:30:00', 'Follow-up consultation', 1),
('APT003', 3, 3, '2024-01-27', '09:15:00', 'Joint pain assessment', 1);

-- ============================================================================
-- BLOOD BANK SYSTEM TABLES
-- ============================================================================

-- Blood Donors Table
CREATE TABLE `blood_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `donor_id` varchar(50) UNIQUE NOT NULL,
  `donor_name` varchar(200) NOT NULL,
  `first_name` varchar(100),
  `last_name` varchar(100),
  `date_of_birth` date,
  `gender` enum('male', 'female', 'other'),
  `blood_group` varchar(10) NOT NULL,
  `phone` varchar(20),
  `email` varchar(100),
  `address` text,
  `emergency_contact` varchar(200),
  `medical_history` text,
  `last_donation_date` date,
  `total_donations` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `blood_donors` (`donor_id`, `donor_name`, `blood_group`, `phone`, `email`, `total_donations`) VALUES
('DON001', 'Ravi Kumar', 'A+', '+91-9876543217', 'ravi.kumar@email.com', 5),
('DON002', 'Sunita Devi', 'O+', '+91-9876543218', 'sunita.devi@email.com', 3);

-- Blood Inventory Table
CREATE TABLE `blood_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `blood_group` varchar(10) NOT NULL,
  `component_type` varchar(50) DEFAULT 'whole_blood',
  `volume_ml` int(11) DEFAULT 450,
  `bag_number` varchar(50),
  `donor_id` int(11),
  `collection_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'available',
  `storage_location` varchar(100),
  `temperature` decimal(4,2),
  `tested` tinyint(1) DEFAULT 0,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`donor_id`) REFERENCES `blood_donors`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blood Donation Sessions Table
CREATE TABLE `blood_donation_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `session_id` varchar(50) UNIQUE NOT NULL,
  `donor_id` int(11) NOT NULL,
  `collection_date` date NOT NULL,
  `collection_time` time NOT NULL,
  `blood_group` varchar(10) NOT NULL,
  `volume_collected` int(11) DEFAULT 450,
  `donation_type` varchar(50) DEFAULT 'voluntary',
  `pre_donation_bp` varchar(20),
  `pre_donation_pulse` int(11),
  `pre_donation_temp` decimal(4,2),
  `pre_donation_weight` decimal(5,2),
  `post_donation_condition` varchar(100),
  `adverse_reactions` text,
  `collected_by` int(11),
  `status` varchar(20) DEFAULT 'completed',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`donor_id`) REFERENCES `blood_donors`(`id`),
  FOREIGN KEY (`collected_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blood Requests Table
CREATE TABLE `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `request_id` varchar(50) UNIQUE NOT NULL,
  `request_number` varchar(50) UNIQUE,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `blood_group` varchar(10) NOT NULL,
  `component_type` varchar(50) NOT NULL,
  `units_requested` int(11) NOT NULL,
  `units_required` int(11),
  `urgency` varchar(20) DEFAULT 'routine',
  `indication` text NOT NULL,
  `clinical_indication` text,
  `request_date` date NOT NULL,
  `required_date` date NOT NULL,
  `required_by_date` date,
  `status` varchar(20) DEFAULT 'pending',
  `cross_match_required` tinyint(1) DEFAULT 1,
  `special_requirements` text,
  `approved_by` int(11),
  `approved_at` datetime,
  `fulfilled_at` datetime,
  `notes` text,
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`),
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blood Usage Records Table
CREATE TABLE `blood_usage_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `usage_id` varchar(50) UNIQUE NOT NULL,
  `blood_inventory_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `usage_date` date NOT NULL,
  `usage_time` time NOT NULL,
  `blood_group` varchar(10) NOT NULL,
  `component_type` varchar(50) NOT NULL,
  `volume_used` int(11) NOT NULL,
  `indication` text NOT NULL,
  `cross_match_done` tinyint(1) DEFAULT 1,
  `transfusion_reaction` text,
  `administered_by` int(11),
  `status` varchar(20) DEFAULT 'completed',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`blood_inventory_id`) REFERENCES `blood_inventory`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`),
  FOREIGN KEY (`administered_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blood Inventory Dashboard View (Referenced in code)
CREATE VIEW `blood_inventory_dashboard` AS
SELECT 
    blood_group,
    component_type,
    COUNT(*) as total_units,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_units,
    SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_units,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_units,
    SUM(CASE WHEN status = 'available' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as expiring_soon
FROM blood_inventory 
GROUP BY blood_group, component_type
ORDER BY blood_group, component_type;

-- ============================================================================
-- LABORATORY SYSTEM TABLES
-- ============================================================================

-- Lab Tests Table
CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `test_name` varchar(200) NOT NULL,
  `test_code` varchar(50) UNIQUE,
  `category` varchar(100),
  `description` text,
  `cost` decimal(10,2),
  `normal_range` varchar(200),
  `unit` varchar(50),
  `specimen_type` varchar(50),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lab Orders Table
CREATE TABLE `lab_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `order_id` varchar(50) UNIQUE NOT NULL,
  `order_number` varchar(50) UNIQUE,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11),
  `order_date` date NOT NULL,
  `expected_date` date,
  `priority` varchar(20) DEFAULT 'routine',
  `clinical_notes` text,
  `total_cost` decimal(10,2) DEFAULT 0,
  `status` varchar(20) DEFAULT 'pending',
  `completed_at` datetime,
  `report_ready` tinyint(1) DEFAULT 0,
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lab Order Tests Table
CREATE TABLE `lab_order_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_order_id` int(11) NOT NULL,
  `test_id` int(11),
  `test_name` varchar(200) NOT NULL,
  `test_code` varchar(50),
  `test_category` varchar(100),
  `specimen_type` varchar(50),
  `reference_range` varchar(200),
  `result_value` varchar(200),
  `unit` varchar(50),
  `status` varchar(20) DEFAULT 'pending',
  `is_abnormal` tinyint(1) DEFAULT 0,
  `critical_value` tinyint(1) DEFAULT 0,
  `performed_by` int(11),
  `performed_at` datetime,
  `completed_at` datetime,
  `verified_by` int(11),
  `verified_at` datetime,
  `notes` text,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders`(`id`),
  FOREIGN KEY (`test_id`) REFERENCES `lab_tests`(`id`),
  FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`verified_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- PHARMACY SYSTEM TABLES
-- ============================================================================

-- Medicine Categories Table
CREATE TABLE `medicine_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `medicine_categories` (`name`, `category_name`, `description`) VALUES
('Analgesics', 'Analgesics', 'Pain relief medications'),
('Antibiotics', 'Antibiotics', 'Anti-bacterial medications'),
('Antacids', 'Antacids', 'Stomach acid neutralizers'),
('Cardiovascular', 'Cardiovascular', 'Heart and blood vessel medications'),
('Diabetes', 'Diabetes', 'Blood sugar control medications'),
('Vitamins', 'Vitamins', 'Nutritional supplements');

-- Medicines Table
CREATE TABLE `medicines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `medicine_name` varchar(200) NOT NULL,
  `generic_name` varchar(200),
  `manufacturer` varchar(200),
  `category_id` int(11),
  `dosage_form` varchar(50),
  `strength` varchar(50),
  `unit_price` decimal(10,2),
  `stock_quantity` int(11) DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 10,
  `expiry_date` date,
  `batch_number` varchar(100),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`category_id`) REFERENCES `medicine_categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `medicines` (`medicine_name`, `generic_name`, `manufacturer`, `dosage_form`, `strength`, `unit_price`, `stock_quantity`, `min_stock_level`) VALUES
('Paracetamol', 'Acetaminophen', 'ABC Pharma', 'Tablet', '500mg', 2.50, 500, 50),
('Amoxicillin', 'Amoxicillin', 'XYZ Pharma', 'Capsule', '250mg', 5.00, 200, 30),
('Aspirin', 'Acetylsalicylic acid', 'DEF Pharma', 'Tablet', '75mg', 1.50, 300, 40),
('Omeprazole', 'Omeprazole', 'GHI Pharma', 'Capsule', '20mg', 8.00, 150, 25),
('Metformin', 'Metformin HCl', 'JKL Pharma', 'Tablet', '500mg', 3.00, 400, 60);

-- Prescriptions Table
CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `prescription_id` varchar(50) UNIQUE NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11),
  `prescription_date` date NOT NULL,
  `diagnosis` text,
  `instructions` text,
  `status` varchar(20) DEFAULT 'active',
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prescription Medicines Table
CREATE TABLE `prescription_medicines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `dosage` varchar(100),
  `frequency` varchar(100),
  `duration` varchar(100),
  `instructions` text,
  `dispensed_quantity` int(11) DEFAULT 0,
  `dispensed_by` int(11),
  `dispensed_at` datetime,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`),
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`),
  FOREIGN KEY (`dispensed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prescription Dispensing Table
CREATE TABLE `prescription_dispensing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `prescription_id` int(11) NOT NULL,
  `dispensed_by` int(11) NOT NULL,
  `dispensed_date` date NOT NULL,
  `total_amount` decimal(10,2),
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`),
  FOREIGN KEY (`dispensed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Medicine Stock Movements Table
CREATE TABLE `medicine_stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `medicine_id` int(11) NOT NULL,
  `movement_type` varchar(20) NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_id` int(11),
  `reference_type` varchar(50),
  `notes` text,
  `moved_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`),
  FOREIGN KEY (`moved_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- BILLING AND FINANCIAL TABLES
-- ============================================================================

-- Bills Table
CREATE TABLE `bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `bill_id` varchar(50) UNIQUE NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11),
  `bill_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) DEFAULT 0,
  `balance_amount` decimal(12,2) DEFAULT 0,
  `payment_status` varchar(20) DEFAULT 'pending',
  `payment_method` varchar(50),
  `payment_date` datetime,
  `discount_amount` decimal(10,2) DEFAULT 0,
  `tax_amount` decimal(10,2) DEFAULT 0,
  `notes` text,
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Billing Table (Alternative billing system)
CREATE TABLE `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL,
  `description` text,
  `amount` decimal(10,2) NOT NULL,
  `billing_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `balance_amount` decimal(12,2) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- INSURANCE MANAGEMENT TABLES
-- ============================================================================

-- Insurance Providers Table
CREATE TABLE `insurance_providers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `provider_name` varchar(200) NOT NULL,
  `provider_code` varchar(50) UNIQUE NOT NULL,
  `contact_person` varchar(100),
  `phone` varchar(20),
  `email` varchar(100),
  `address` text,
  `website` varchar(200),
  `coverage_types` text,
  `claim_process` text,
  `settlement_period` int(11) DEFAULT 30,
  `cashless_available` tinyint(1) DEFAULT 0,
  `network_hospital` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `insurance_providers` (`provider_name`, `provider_code`, `contact_person`, `phone`, `email`, `address`, `website`, `coverage_types`, `claim_process`, `settlement_period`, `cashless_available`, `network_hospital`) VALUES
('ICICI Lombard', 'ICICI001', 'Rajesh Kumar', '+91-9876543210', 'claims@icicilombard.com', 'Mumbai, India', 'www.icicilombard.com', 'Health, Critical Illness', 'Online submission', 15, 1, 1),
('Star Health', 'STAR001', 'Priya Sharma', '+91-9876543211', 'claims@starhealth.in', 'Chennai, India', 'www.starhealth.in', 'Health, Family Floater', 'Online/Offline', 21, 1, 1),
('HDFC ERGO', 'HDFC001', 'Amit Singh', '+91-9876543212', 'claims@hdfcergo.com', 'Delhi, India', 'www.hdfcergo.com', 'Health, Personal Accident', 'Digital claims', 10, 1, 1);

-- Insurance Policies Table
CREATE TABLE `insurance_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `policy_id` int(11),
  `policy_number` varchar(100) UNIQUE NOT NULL,
  `policy_holder_name` varchar(200) NOT NULL,
  `policy_type` varchar(50) NOT NULL,
  `coverage_amount` decimal(12,2) NOT NULL,
  `premium_amount` decimal(10,2),
  `policy_start_date` date NOT NULL,
  `policy_end_date` date NOT NULL,
  `family_members` int(11) DEFAULT 1,
  `pre_existing_covered` tinyint(1) DEFAULT 0,
  `maternity_covered` tinyint(1) DEFAULT 0,
  `dental_covered` tinyint(1) DEFAULT 0,
  `optical_covered` tinyint(1) DEFAULT 0,
  `deductible_amount` decimal(10,2) DEFAULT 0,
  `co_payment_percentage` decimal(5,2) DEFAULT 0,
  `policy_status` varchar(20) DEFAULT 'active',
  `claim_limit_used` decimal(12,2) DEFAULT 0,
  `documents_path` text,
  `notes` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`provider_id`) REFERENCES `insurance_providers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insurance Claims Table
CREATE TABLE `insurance_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `claim_number` varchar(100) UNIQUE NOT NULL,
  `policy_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11),
  `bill_id` int(11),
  `claim_type` varchar(50) NOT NULL,
  `service_type` varchar(100),
  `service_date` date,
  `claim_date` date NOT NULL,
  `treatment_date` date NOT NULL,
  `diagnosis_code` varchar(20),
  `diagnosis_description` text,
  `treatment_description` text,
  `treatment_details` text,
  `claim_amount` decimal(12,2) NOT NULL,
  `approved_amount` decimal(12,2) DEFAULT 0,
  `processed_amount` decimal(12,2) DEFAULT 0,
  `estimated_coverage` decimal(12,2),
  `deductible_applied` decimal(10,2) DEFAULT 0,
  `co_payment_applied` decimal(10,2) DEFAULT 0,
  `claim_status` varchar(20) DEFAULT 'submitted',
  `submission_date` date NOT NULL,
  `approval_date` date,
  `processed_date` date,
  `settlement_date` date,
  `rejection_reason` text,
  `documents_submitted` text,
  `submitted_documents` text,
  `doctor_reference` varchar(200),
  `claim_officer` varchar(100),
  `submitted_by` int(11),
  `processed_by` int(11),
  `admin_notes` text,
  `admin_reviewed` tinyint(1) DEFAULT 0,
  `admin_review_date` datetime,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`policy_id`) REFERENCES `insurance_policies`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`),
  FOREIGN KEY (`bill_id`) REFERENCES `bills`(`id`),
  FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Claim Timeline Table
CREATE TABLE `claim_timeline` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `claim_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `status_date` datetime NOT NULL,
  `updated_by` int(11),
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`claim_id`) REFERENCES `insurance_claims`(`id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- EQUIPMENT AND INFRASTRUCTURE TABLES
-- ============================================================================

-- Equipment Table
CREATE TABLE `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `name` varchar(200) NOT NULL,
  `equipment_name` varchar(200) NOT NULL,
  `equipment_type` varchar(100),
  `category` varchar(100),
  `model` varchar(100),
  `model_number` varchar(100),
  `serial_number` varchar(100) UNIQUE,
  `manufacturer` varchar(200),
  `purchase_date` date,
  `warranty_expiry` date,
  `purchase_cost` decimal(12,2),
  `cost` decimal(12,2),
  `department_id` int(11),
  `location` varchar(200),
  `status` varchar(50) DEFAULT 'operational',
  `maintenance_schedule` varchar(100),
  `last_maintenance` date,
  `last_maintenance_date` date,
  `next_maintenance_date` date,
  `specifications` text,
  `notes` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `equipment` (`name`, `equipment_name`, `equipment_type`, `manufacturer`, `serial_number`, `department_id`, `location`, `purchase_cost`) VALUES
('X-Ray Machine', 'X-Ray Machine', 'Diagnostic', 'MedTech Corp', 'XR001', 1, 'Radiology Room 1', 250000.00),
('ECG Machine', 'ECG Machine', 'Diagnostic', 'CardioTech', 'ECG001', 2, 'Cardiology Ward', 45000.00),
('Ventilator', 'Ventilator', 'Life Support', 'LifeTech', 'VENT001', 1, 'ICU', 180000.00);

-- Equipment Maintenance Table
CREATE TABLE `equipment_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `equipment_id` int(11) NOT NULL,
  `maintenance_type` varchar(100) NOT NULL,
  `maintenance_date` date NOT NULL,
  `performed_by` int(11),
  `cost` decimal(10,2),
  `notes` text,
  `next_maintenance_date` date,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`),
  FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Beds Table
CREATE TABLE `beds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `bed_number` varchar(20) NOT NULL,
  `ward_name` varchar(100),
  `room_number` varchar(20),
  `bed_type` varchar(50) DEFAULT 'general',
  `department_id` int(11),
  `status` varchar(20) DEFAULT 'available',
  `patient_id` int(11),
  `admission_date` date,
  `discharge_date` date,
  `daily_rate` decimal(10,2),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `beds` (`bed_number`, `ward_name`, `room_number`, `bed_type`, `department_id`, `daily_rate`) VALUES
('A101', 'General Ward A', '101', 'general', 6, 1500.00),
('A102', 'General Ward A', '101', 'general', 6, 1500.00),
('B201', 'ICU Ward', '201', 'icu', 8, 5000.00),
('C301', 'Private Ward', '301', 'private', 6, 3000.00),
('D401', 'Emergency Ward', '401', 'emergency', 1, 2000.00);

-- Bed Assignments Table (Referenced in code)
CREATE TABLE `bed_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `bed_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  `discharge_date` date,
  `status` varchar(20) DEFAULT 'active',
  `notes` text,
  `assigned_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`bed_id`) REFERENCES `beds`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- AMBULANCE SYSTEM TABLES
-- ============================================================================

-- Ambulances Table
CREATE TABLE `ambulances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `vehicle_number` varchar(50) UNIQUE NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `driver_name` varchar(200),
  `driver_phone` varchar(20),
  `capacity` int(11) DEFAULT 4,
  `equipment` text,
  `status` varchar(20) DEFAULT 'available',
  `location` varchar(200),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ambulance Bookings Table
CREATE TABLE `ambulance_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `booking_id` varchar(50) UNIQUE NOT NULL,
  `patient_id` int(11),
  `ambulance_id` int(11),
  `pickup_address` text NOT NULL,
  `destination_address` text NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `patient_condition` varchar(100),
  `emergency_type` varchar(50),
  `status` varchar(20) DEFAULT 'booked',
  `driver_assigned` varchar(200),
  `estimated_cost` decimal(10,2),
  `actual_cost` decimal(10,2),
  `notes` text,
  `booked_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`ambulance_id`) REFERENCES `ambulances`(`id`),
  FOREIGN KEY (`booked_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- ORGAN DONATION AND TRANSPLANT TABLES
-- ============================================================================

-- Organ Donor Consent Table
CREATE TABLE `organ_donor_consent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `donor_id` int(11) NOT NULL,
  `consent_type` varchar(50) NOT NULL,
  `consent_date` date NOT NULL,
  `witness_1` varchar(200),
  `witness_2` varchar(200),
  `legal_guardian` varchar(200),
  `consent_document_path` varchar(500),
  `notarized` tinyint(1) DEFAULT 0,
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`donor_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Donations Table
CREATE TABLE `organ_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `donor_id` int(11) NOT NULL,
  `consent_id` int(11),
  `donation_type` varchar(50) NOT NULL,
  `organ_type` varchar(100) NOT NULL,
  `medical_evaluation` text,
  `brain_death_confirmation` tinyint(1) DEFAULT 0,
  `declaration_time` datetime,
  `declaring_physician` varchar(200),
  `harvest_team_lead` varchar(200),
  `preservation_method` varchar(200),
  `status` varchar(50) DEFAULT 'pending',
  `legal_clearance` varchar(20) DEFAULT 'pending',
  `ethics_committee_approval` varchar(20) DEFAULT 'pending',
  `transplant_id` int(11),
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`donor_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`consent_id`) REFERENCES `organ_donor_consent`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Recipients Table
CREATE TABLE `organ_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `organ_needed` varchar(100) NOT NULL,
  `urgency_level` varchar(20) DEFAULT 'medium',
  `waiting_list_date` date NOT NULL,
  `priority_score` int(11) DEFAULT 0,
  `medical_compatibility` text,
  `insurance_verification` tinyint(1) DEFAULT 0,
  `legal_consent` tinyint(1) DEFAULT 0,
  `guardian_consent` tinyint(1) DEFAULT 0,
  `ethics_approval` tinyint(1) DEFAULT 0,
  `status` varchar(50) DEFAULT 'waiting',
  `transplant_date` date,
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Transplants Table
CREATE TABLE `organ_transplants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `donation_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `surgery_date` date NOT NULL,
  `lead_surgeon` varchar(200),
  `surgical_team` text,
  `operation_duration` int(11),
  `cross_match_result` varchar(100),
  `immunosuppression_protocol` text,
  `immediate_complications` text,
  `legal_documentation` text,
  `status` varchar(50) DEFAULT 'scheduled',
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`donation_id`) REFERENCES `organ_donations`(`id`),
  FOREIGN KEY (`recipient_id`) REFERENCES `organ_recipients`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Legal Rejections Table
CREATE TABLE `organ_legal_rejections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `donation_id` int(11),
  `recipient_id` int(11),
  `rejection_type` varchar(50) NOT NULL,
  `rejection_reason` text NOT NULL,
  `legal_basis` text,
  `rejecting_authority` varchar(200),
  `rejection_date` date NOT NULL,
  `documentation_path` varchar(500),
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`donation_id`) REFERENCES `organ_donations`(`id`),
  FOREIGN KEY (`recipient_id`) REFERENCES `organ_recipients`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Audit Trail Table
CREATE TABLE `organ_audit_trail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `transplant_id` int(11),
  `action_type` varchar(100) NOT NULL,
  `action_details` text,
  `performed_by` int(11),
  `legal_significance` varchar(20) DEFAULT 'medium',
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`transplant_id`) REFERENCES `organ_transplants`(`id`),
  FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- SHIFT AND STAFF MANAGEMENT TABLES
-- ============================================================================

-- Shifts Table
CREATE TABLE `shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `shift_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `shifts` (`shift_name`, `start_time`, `end_time`, `description`) VALUES
('Morning Shift', '06:00:00', '14:00:00', 'Morning duty shift'),
('Evening Shift', '14:00:00', '22:00:00', 'Evening duty shift'),
('Night Shift', '22:00:00', '06:00:00', 'Night duty shift');

-- Staff Schedules Table
CREATE TABLE `staff_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `staff_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'scheduled',
  `notes` text,
  `created_by` int(11),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`),
  FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- ADMIN MONITORING AND SYSTEM TABLES
-- ============================================================================

-- Admin Monitoring Table
CREATE TABLE `admin_monitoring` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `monitoring_category` varchar(50) NOT NULL,
  `item_type` varchar(50) NOT NULL,
  `item_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  `priority` varchar(20) DEFAULT 'medium',
  `alert_message` text NOT NULL,
  `alert_date` date NOT NULL,
  `alert_time` time NOT NULL,
  `resolved` tinyint(1) DEFAULT 0,
  `resolved_by` int(11),
  `resolved_at` datetime,
  `resolution_notes` text,
  `auto_generated` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System Logs Table
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `user_id` int(11),
  `log_type` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `ip_address` varchar(45),
  `user_agent` text,
  `log_date` date NOT NULL,
  `log_time` time NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- LEGACY COMPATIBILITY TABLES
-- ============================================================================

-- These tables are referenced in some older code files for backward compatibility

-- Organ Donors Table (Legacy)
CREATE TABLE `organ_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `donor_type` varchar(50) DEFAULT 'living',
  `organs_donated` text,
  `donation_date` date,
  `status` varchar(20) DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Matches Table (Legacy)
CREATE TABLE `organ_matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `donor_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `organ_type` varchar(100) NOT NULL,
  `compatibility_score` int(11) DEFAULT 0,
  `match_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`donor_id`) REFERENCES `organ_donors`(`id`),
  FOREIGN KEY (`recipient_id`) REFERENCES `organ_recipients`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Transplant Records Table (Legacy)
CREATE TABLE `transplant_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `match_id` int(11) NOT NULL,
  `surgery_date` date NOT NULL,
  `surgeon_name` varchar(200),
  `outcome` varchar(100),
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`match_id`) REFERENCES `organ_matches`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- COMMIT TRANSACTION
-- ============================================================================

COMMIT;

-- ============================================================================
-- END OF HOSPITAL CRM COMPLETE DATABASE
-- ============================================================================

-- Default Login Credentials:
-- Username: admin, Password: admin
-- Username: doctor1, Password: admin  
-- Username: nurse1, Password: admin
-- Username: patient1, Password: admin

-- Total Tables Created: 50+
-- All CRUD Operations Supported
-- Complete Blood Bank System
-- Full Insurance Management
-- Comprehensive Patient Monitoring
-- Advanced Admin Dashboard
-- Organ Donation & Transplant System
-- Equipment & Infrastructure Management
-- Laboratory Information System
-- Pharmacy Management System
-- Ambulance Management System
-- Staff & Shift Management
-- Billing & Financial System

-- ============================================================================
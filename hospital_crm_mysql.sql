-- Hospital CRM Complete Database - MySQL/MariaDB Compatible
-- Generated on: 2024-01-27
-- Database: hospital_crm

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: `hospital_crm`
CREATE DATABASE IF NOT EXISTS `hospital_crm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `hospital_crm`;

-- --------------------------------------------------------
-- Table structure for table `roles`
-- --------------------------------------------------------

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL UNIQUE,
  `role_display_name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `role_name`, `role_display_name`) VALUES
(1, 'admin', 'Administrator'),
(2, 'doctor', 'Doctor'),
(3, 'nurse', 'Nurse'),
(4, 'patient', 'Patient'),
(5, 'receptionist', 'Receptionist'),
(6, 'lab_technician', 'Lab Technician'),
(7, 'pharmacy_staff', 'Pharmacy Staff'),
(8, 'intern_doctor', 'Intern Doctor'),
(9, 'intern_nurse', 'Intern Nurse'),
(10, 'intern_lab', 'Intern Lab Technician'),
(11, 'intern_pharmacy', 'Intern Pharmacy');

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `hospitals`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `departments`
-- --------------------------------------------------------

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
('Surgery', 'Surgical procedures', '4th Floor');

-- --------------------------------------------------------
-- Table structure for table `doctors`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `patients`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `appointments`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `blood_donors`
-- --------------------------------------------------------

CREATE TABLE `blood_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `donor_id` varchar(50) UNIQUE NOT NULL,
  `donor_name` varchar(200) NOT NULL,
  `date_of_birth` date,
  `gender` enum('male', 'female', 'other'),
  `blood_group` varchar(10) NOT NULL,
  `phone` varchar(20),
  `email` varchar(100),
  `address` text,
  `last_donation_date` date,
  `total_donations` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `blood_donors` (`donor_id`, `donor_name`, `blood_group`, `phone`, `email`, `total_donations`) VALUES
('DON001', 'Ravi Kumar', 'A+', '+91-9876543217', 'ravi.kumar@email.com', 5),
('DON002', 'Sunita Devi', 'O+', '+91-9876543218', 'sunita.devi@email.com', 3);

-- --------------------------------------------------------
-- Table structure for table `blood_inventory`
-- --------------------------------------------------------

CREATE TABLE `blood_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `blood_group` varchar(10) NOT NULL,
  `component_type` varchar(50) DEFAULT 'whole_blood',
  `volume_ml` int(11) DEFAULT 450,
  `bag_number` varchar(50),
  `collection_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'available',
  `donor_id` int(11),
  `storage_location` varchar(100),
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`donor_id`) REFERENCES `blood_donors`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample blood inventory data will be inserted via separate script

-- --------------------------------------------------------
-- Table structure for table `blood_donation_sessions`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `blood_requests`
-- --------------------------------------------------------

CREATE TABLE `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `request_id` varchar(50) UNIQUE NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `blood_group` varchar(10) NOT NULL,
  `component_type` varchar(50) NOT NULL,
  `units_requested` int(11) NOT NULL,
  `urgency` varchar(20) DEFAULT 'routine',
  `indication` text NOT NULL,
  `request_date` date NOT NULL,
  `required_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
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

-- --------------------------------------------------------
-- Table structure for table `blood_usage_records`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `patient_vitals`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `insurance_providers`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `insurance_policies`
-- --------------------------------------------------------

CREATE TABLE `insurance_policies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
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

-- --------------------------------------------------------
-- Table structure for table `insurance_claims`
-- --------------------------------------------------------

CREATE TABLE `insurance_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `claim_number` varchar(100) UNIQUE NOT NULL,
  `policy_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11),
  `bill_id` int(11),
  `claim_type` varchar(50) NOT NULL,
  `claim_date` date NOT NULL,
  `treatment_date` date NOT NULL,
  `diagnosis_code` varchar(20),
  `diagnosis_description` text,
  `treatment_description` text,
  `claim_amount` decimal(12,2) NOT NULL,
  `approved_amount` decimal(12,2) DEFAULT 0,
  `deductible_applied` decimal(10,2) DEFAULT 0,
  `co_payment_applied` decimal(10,2) DEFAULT 0,
  `claim_status` varchar(20) DEFAULT 'submitted',
  `submission_date` date NOT NULL,
  `approval_date` date,
  `settlement_date` date,
  `rejection_reason` text,
  `documents_submitted` text,
  `claim_officer` varchar(100),
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
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `admin_monitoring`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `medicines`
-- --------------------------------------------------------

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
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `medicines` (`medicine_name`, `generic_name`, `manufacturer`, `dosage_form`, `strength`, `unit_price`, `stock_quantity`, `min_stock_level`) VALUES
('Paracetamol', 'Acetaminophen', 'ABC Pharma', 'Tablet', '500mg', 2.50, 500, 50),
('Amoxicillin', 'Amoxicillin', 'XYZ Pharma', 'Capsule', '250mg', 5.00, 200, 30),
('Aspirin', 'Acetylsalicylic acid', 'DEF Pharma', 'Tablet', '75mg', 1.50, 300, 40),
('Omeprazole', 'Omeprazole', 'GHI Pharma', 'Capsule', '20mg', 8.00, 150, 25),
('Metformin', 'Metformin HCl', 'JKL Pharma', 'Tablet', '500mg', 3.00, 400, 60);

-- --------------------------------------------------------
-- Table structure for table `medicine_categories`
-- --------------------------------------------------------

CREATE TABLE `medicine_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `category_name` varchar(100) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `medicine_categories` (`category_name`, `description`) VALUES
('Analgesics', 'Pain relief medications'),
('Antibiotics', 'Anti-bacterial medications'),
('Antacids', 'Stomach acid neutralizers'),
('Cardiovascular', 'Heart and blood vessel medications'),
('Diabetes', 'Blood sugar control medications'),
('Vitamins', 'Nutritional supplements');

-- --------------------------------------------------------
-- Table structure for table `bills`
-- --------------------------------------------------------

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

-- --------------------------------------------------------
-- Table structure for table `equipment`
-- --------------------------------------------------------

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) DEFAULT 1,
  `equipment_name` varchar(200) NOT NULL,
  `equipment_type` varchar(100),
  `model_number` varchar(100),
  `manufacturer` varchar(200),
  `serial_number` varchar(100) UNIQUE,
  `purchase_date` date,
  `purchase_cost` decimal(12,2),
  `department_id` int(11),
  `location` varchar(200),
  `status` varchar(50) DEFAULT 'operational',
  `last_maintenance_date` date,
  `next_maintenance_date` date,
  `warranty_expiry` date,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`hospital_id`) REFERENCES `hospitals`(`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `equipment` (`equipment_name`, `equipment_type`, `manufacturer`, `serial_number`, `department_id`, `location`, `purchase_cost`) VALUES
('X-Ray Machine', 'Diagnostic', 'MedTech Corp', 'XR001', 1, 'Radiology Room 1', 250000.00),
('ECG Machine', 'Diagnostic', 'CardioTech', 'ECG001', 2, 'Cardiology Ward', 45000.00),
('Ventilator', 'Life Support', 'LifeTech', 'VENT001', 1, 'ICU', 180000.00);

-- --------------------------------------------------------
-- Table structure for table `beds`
-- --------------------------------------------------------

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
('B201', 'ICU Ward', '201', 'icu', 1, 5000.00),
('C301', 'Private Ward', '301', 'private', 6, 3000.00),
('D401', 'Emergency Ward', '401', 'emergency', 1, 2000.00);

-- --------------------------------------------------------
-- Table structure for table `shifts`
-- --------------------------------------------------------

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

COMMIT;

-- --------------------------------------------------------
-- END OF HOSPITAL CRM DATABASE STRUCTURE
-- --------------------------------------------------------

-- Note: This is the complete database structure for Hospital CRM
-- To populate with sample data, run the PHP scripts provided
-- Default login: admin/admin (password will be hashed)
-- --------------------------------------------------------
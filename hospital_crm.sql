-- Hospital CRM Database Schema
-- Created for Hospital Management System

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create database
CREATE DATABASE IF NOT EXISTS `hospital_crm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hospital_crm`;

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

-- --------------------------------------------------------
-- Table structure for table `staff`
-- --------------------------------------------------------

CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT 0.00,
  `date_of_joining` date DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  UNIQUE KEY `email` (`email`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `appointment_type` enum('consultation','follow_up','emergency','routine_checkup','vaccination','surgery') NOT NULL DEFAULT 'consultation',
  `status` enum('scheduled','completed','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
  `reason` text,
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
  `symptoms` text,
  `medications` text,
  `instructions` text,
  `follow_up_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_id` (`prescription_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `prescriptions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT `pharmacy_sales_ibfk_2` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`),
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
-- Table structure for table `lab_tests`
-- --------------------------------------------------------

CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `test_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `report_file` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `test_id` (`test_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `lab_tests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `lab_tests_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `lab_tests_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `lab_tests_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `lab_test_items`
-- --------------------------------------------------------

CREATE TABLE `lab_test_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_test_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `result_value` varchar(200) DEFAULT NULL,
  `result_status` enum('normal','abnormal','critical') DEFAULT 'normal',
  `reference_range` varchar(200) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `lab_test_id` (`lab_test_id`),
  KEY `test_id` (`test_id`),
  CONSTRAINT `lab_test_items_ibfk_1` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`),
  CONSTRAINT `lab_test_items_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `laboratory` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `billing`
-- --------------------------------------------------------

CREATE TABLE `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `bill_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) DEFAULT 0.00,
  `balance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('pending','paid','partial','overdue') NOT NULL DEFAULT 'pending',
  `payment_method` enum('cash','card','upi','cheque','bank_transfer','insurance') DEFAULT NULL,
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

-- --------------------------------------------------------
-- Table structure for table `billing_items`
-- --------------------------------------------------------

CREATE TABLE `billing_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `item_type` enum('consultation','test','medicine','procedure','room','other') NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `description` text,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  CONSTRAINT `billing_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `billing` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------
-- Insert sample data
-- --------------------------------------------------------

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

-- Insert users (password is 'admin' for all)
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `first_name`, `last_name`) VALUES
(1, 'admin@hospital.com', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'System', 'Administrator'),
(2, 'doctor1@hospital.com', 'doctor1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Dr. John', 'Smith'),
(3, 'nurse1@hospital.com', 'nurse1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Mary', 'Johnson'),
(4, 'patient1@hospital.com', 'patient1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Robert', 'Wilson'),
(5, 'reception@hospital.com', 'reception@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Sarah', 'Davis');

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

COMMIT;
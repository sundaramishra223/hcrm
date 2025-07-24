-- Hospital CRM Database Structure
-- Created for comprehensive healthcare management system

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: hospital_crm
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
  `role_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
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
  `specialization` varchar(100) DEFAULT NULL,
  `qualification` varchar(200) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `experience_years` int(11) DEFAULT 0,
  `photo` varchar(255) DEFAULT NULL,
  `schedule` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  UNIQUE KEY `email` (`email`)
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
  `appointment_type` enum('consultation','follow_up','emergency','routine_checkup','surgery') NOT NULL DEFAULT 'consultation',
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
  `medications` text NOT NULL,
  `instructions` text,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_id` (`prescription_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`)
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
  `minimum_stock` int(11) NOT NULL DEFAULT 10,
  `description` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
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
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','card','insurance','online') NOT NULL DEFAULT 'cash',
  `payment_status` enum('pending','paid','partial') NOT NULL DEFAULT 'pending',
  `sold_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_id` (`sale_id`),
  KEY `patient_id` (`patient_id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `sold_by` (`sold_by`),
  CONSTRAINT `pharmacy_sales_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `pharmacy_sales_ibfk_2` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`),
  CONSTRAINT `pharmacy_sales_ibfk_3` FOREIGN KEY (`sold_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `pharmacy_sale_items`
-- --------------------------------------------------------

CREATE TABLE `pharmacy_sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
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
  `normal_range` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text,
  `preparation_required` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
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
  `sample_collection_date` datetime DEFAULT NULL,
  `result_date` datetime DEFAULT NULL,
  `status` enum('pending','sample_collected','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('pending','paid','partial') NOT NULL DEFAULT 'pending',
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
  `test_id` int(11) NOT NULL,
  `laboratory_id` int(11) NOT NULL,
  `result_value` varchar(200) DEFAULT NULL,
  `result_status` enum('normal','abnormal','critical') DEFAULT NULL,
  `reference_range` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `test_id` (`test_id`),
  KEY `laboratory_id` (`laboratory_id`),
  CONSTRAINT `lab_test_items_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `lab_tests` (`id`),
  CONSTRAINT `lab_test_items_ibfk_2` FOREIGN KEY (`laboratory_id`) REFERENCES `laboratory` (`id`)
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
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','overdue') NOT NULL DEFAULT 'pending',
  `payment_method` enum('cash','card','cheque','bank_transfer','insurance','online') DEFAULT NULL,
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
  `item_description` text,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
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
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `notes` text,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_id` (`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `activity_logs`
-- --------------------------------------------------------

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text NOT NULL,
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
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- INSERT SAMPLE DATA
-- --------------------------------------------------------

-- Insert roles
INSERT INTO `roles` (`id`, `role_name`, `role_display_name`, `description`) VALUES
(1, 'admin', 'Administrator', 'Full system access and management'),
(2, 'doctor', 'Doctor', 'Medical professional with patient care access'),
(3, 'nurse', 'Nurse', 'Nursing staff with patient care access'),
(4, 'patient', 'Patient', 'Registered patient with limited access'),
(5, 'receptionist', 'Receptionist', 'Front desk operations and appointment management'),
(6, 'pharmacist', 'Pharmacist', 'Pharmacy operations and medication management'),
(7, 'lab_technician', 'Lab Technician', 'Laboratory operations and test management'),
(8, 'accountant', 'Accountant', 'Financial operations and billing management');

-- Insert users (password is 'admin' for all demo accounts)
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `first_name`, `last_name`, `phone`) VALUES
(1, 'admin@hospital.com', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'System', 'Administrator', '9876543210'),
(2, 'doctor1@hospital.com', 'doctor1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 'Dr. John', 'Smith', '9876543211'),
(3, 'nurse1@hospital.com', 'nurse1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 'Mary', 'Johnson', '9876543212'),
(4, 'patient1@hospital.com', 'patient1@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 4, 'Robert', 'Williams', '9876543213'),
(5, 'reception@hospital.com', 'reception@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 'Sarah', 'Davis', '9876543214');

-- Insert sample doctors
INSERT INTO `doctors` (`id`, `doctor_name`, `specialization`, `qualification`, `phone`, `email`, `consultation_fee`, `experience_years`) VALUES
(1, 'Dr. John Smith', 'Cardiology', 'MBBS, MD Cardiology', '9876543211', 'doctor1@hospital.com', 1500.00, 15),
(2, 'Dr. Emily Johnson', 'Pediatrics', 'MBBS, MD Pediatrics', '9876543215', 'emily.johnson@hospital.com', 1200.00, 12),
(3, 'Dr. Michael Brown', 'Orthopedics', 'MBBS, MS Orthopedics', '9876543216', 'michael.brown@hospital.com', 1800.00, 18),
(4, 'Dr. Sarah Wilson', 'Gynecology', 'MBBS, MD Gynecology', '9876543217', 'sarah.wilson@hospital.com', 1400.00, 10),
(5, 'Dr. David Lee', 'Neurology', 'MBBS, DM Neurology', '9876543218', 'david.lee@hospital.com', 2000.00, 20);

-- Insert sample patients
INSERT INTO `patients` (`id`, `patient_id`, `first_name`, `last_name`, `email`, `phone`, `date_of_birth`, `gender`, `blood_group`, `created_by`) VALUES
(1, 'PAT0001', 'Robert', 'Williams', 'patient1@hospital.com', '9876543213', '1985-06-15', 'male', 'B+', 1),
(2, 'PAT0002', 'Jennifer', 'Garcia', 'jennifer.garcia@email.com', '9876543219', '1990-03-22', 'female', 'A+', 1),
(3, 'PAT0003', 'James', 'Martinez', 'james.martinez@email.com', '9876543220', '1978-11-08', 'male', 'O+', 1),
(4, 'PAT0004', 'Lisa', 'Anderson', 'lisa.anderson@email.com', '9876543221', '1995-09-12', 'female', 'AB+', 1),
(5, 'PAT0005', 'William', 'Taylor', 'william.taylor@email.com', '9876543222', '1982-01-30', 'male', 'A-', 1);

-- Insert sample staff
INSERT INTO `staff` (`id`, `staff_id`, `first_name`, `last_name`, `email`, `phone`, `position`, `department`, `salary`, `date_of_joining`) VALUES
(1, 'STF0001', 'Mary', 'Johnson', 'nurse1@hospital.com', '9876543212', 'Senior Nurse', 'General Ward', 45000.00, '2020-01-15'),
(2, 'STF0002', 'Sarah', 'Davis', 'reception@hospital.com', '9876543214', 'Receptionist', 'Front Desk', 25000.00, '2021-03-10'),
(3, 'STF0003', 'Tom', 'Wilson', 'tom.wilson@hospital.com', '9876543223', 'Lab Technician', 'Laboratory', 35000.00, '2019-08-20'),
(4, 'STF0004', 'Anna', 'Miller', 'anna.miller@hospital.com', '9876543224', 'Pharmacist', 'Pharmacy', 50000.00, '2020-06-05'),
(5, 'STF0005', 'Chris', 'Moore', 'chris.moore@hospital.com', '9876543225', 'Accountant', 'Finance', 55000.00, '2018-12-01');

-- Insert sample pharmacy items
INSERT INTO `pharmacy` (`id`, `medicine_name`, `generic_name`, `category`, `manufacturer`, `unit_price`, `stock_quantity`, `minimum_stock`) VALUES
(1, 'Paracetamol 500mg', 'Acetaminophen', 'Analgesic', 'ABC Pharma', 2.50, 1000, 100),
(2, 'Amoxicillin 250mg', 'Amoxicillin', 'Antibiotic', 'XYZ Pharma', 15.00, 500, 50),
(3, 'Omeprazole 20mg', 'Omeprazole', 'Antacid', 'DEF Pharma', 8.75, 300, 30),
(4, 'Aspirin 75mg', 'Acetylsalicylic Acid', 'Antiplatelet', 'GHI Pharma', 3.25, 800, 80),
(5, 'Metformin 500mg', 'Metformin HCl', 'Antidiabetic', 'JKL Pharma', 12.00, 600, 60);

-- Insert sample laboratory tests
INSERT INTO `laboratory` (`id`, `test_name`, `test_category`, `normal_range`, `unit`, `price`) VALUES
(1, 'Complete Blood Count', 'Hematology', 'Various', 'Various', 500.00),
(2, 'Blood Sugar Fasting', 'Biochemistry', '70-110', 'mg/dL', 150.00),
(3, 'Lipid Profile', 'Biochemistry', 'Various', 'mg/dL', 800.00),
(4, 'Liver Function Test', 'Biochemistry', 'Various', 'U/L', 600.00),
(5, 'Kidney Function Test', 'Biochemistry', 'Various', 'mg/dL', 550.00),
(6, 'Thyroid Profile', 'Hormones', 'Various', 'mIU/L', 900.00),
(7, 'Urine Analysis', 'Pathology', 'Various', 'Various', 300.00),
(8, 'ECG', 'Cardiology', 'Normal', 'Various', 400.00);

-- Insert sample equipment
INSERT INTO `equipment` (`id`, `equipment_id`, `equipment_name`, `category`, `manufacturer`, `model_number`, `purchase_date`, `purchase_price`, `location`, `status`) VALUES
(1, 'EQP0001', 'X-Ray Machine', 'Radiology', 'Siemens', 'YSIO Max', '2020-01-15', 2500000.00, 'Radiology Department', 'active'),
(2, 'EQP0002', 'ECG Machine', 'Cardiology', 'Philips', 'PageWriter TC70', '2021-03-10', 350000.00, 'Cardiology Department', 'active'),
(3, 'EQP0003', 'Ultrasound Machine', 'Radiology', 'GE Healthcare', 'LOGIQ P9', '2019-08-20', 1800000.00, 'Radiology Department', 'active'),
(4, 'EQP0004', 'Ventilator', 'ICU', 'Medtronic', 'PB980', '2020-06-05', 1200000.00, 'ICU', 'active'),
(5, 'EQP0005', 'Defibrillator', 'Emergency', 'Zoll', 'R Series Plus', '2021-12-01', 450000.00, 'Emergency Department', 'active');

-- Insert sample appointments
INSERT INTO `appointments` (`id`, `appointment_id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `appointment_type`, `reason`, `created_by`) VALUES
(1, 'APT0001', 1, 1, '2024-01-25', '10:00:00', 'consultation', 'Chest pain and shortness of breath', 5),
(2, 'APT0002', 2, 2, '2024-01-25', '11:30:00', 'routine_checkup', 'Regular pediatric checkup', 5),
(3, 'APT0003', 3, 3, '2024-01-26', '09:00:00', 'consultation', 'Knee pain after fall', 5),
(4, 'APT0004', 4, 4, '2024-01-26', '14:00:00', 'consultation', 'Prenatal checkup', 5),
(5, 'APT0005', 5, 5, '2024-01-27', '10:30:00', 'consultation', 'Frequent headaches', 5);

COMMIT;
-- ===================================================================
-- COMPLETE HOSPITAL CRM (HCRM) DATABASE SCHEMA - PART 2
-- Laboratory, Pharmacy, Blood Bank, Organ Management, Insurance, Equipment
-- ===================================================================

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
-- CONTINUED IN PART 3...
-- ===================================================================
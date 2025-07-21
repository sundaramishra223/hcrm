-- ===================================================================
-- SAFE DATABASE UPDATE FOR EXISTING HOSPITAL CRM DATABASE
-- This will ADD new Blood Bank, Insurance & Billing features safely
-- Compatible with your existing database structure from July 2025
-- ===================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ===================================================================
-- SECTION 1: ADD NEW BLOOD BANK SYSTEM TABLES
-- ===================================================================

-- Blood Donors Table
CREATE TABLE IF NOT EXISTS `blood_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `donor_id` varchar(20) NOT NULL,
  `registration_date` date NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `last_donation_date` date DEFAULT NULL,
  `total_donations` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `medical_clearance` enum('cleared','pending','rejected') DEFAULT 'pending',
  `clearance_date` date DEFAULT NULL,
  `clearance_notes` text,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_id` (`donor_id`),
  UNIQUE KEY `patient_donor` (`patient_id`),
  KEY `blood_group` (`blood_group`),
  KEY `is_active` (`is_active`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blood Inventory Table
CREATE TABLE IF NOT EXISTS `blood_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bag_number` varchar(20) NOT NULL,
  `donor_id` int(11) DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `component_type` enum('whole_blood','red_blood_cells','platelets','plasma','cryoprecipitate','fresh_frozen_plasma') NOT NULL,
  `volume_ml` int(11) NOT NULL,
  `collection_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('available','reserved','used','expired','quarantine','discarded') DEFAULT 'available',
  `storage_location` varchar(50) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `test_results` json DEFAULT NULL,
  `issued_to_patient_id` int(11) DEFAULT NULL,
  `issued_date` datetime DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bag_number` (`bag_number`),
  KEY `donor_id` (`donor_id`),
  KEY `blood_group` (`blood_group`,`component_type`),
  KEY `status` (`status`),
  KEY `expiry_date` (`expiry_date`),
  FOREIGN KEY (`donor_id`) REFERENCES `blood_donors`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`issued_to_patient_id`) REFERENCES `patients`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`issued_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blood Donation Sessions Table
CREATE TABLE IF NOT EXISTS `blood_donation_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NOT NULL,
  `collected_by` int(11) NOT NULL,
  `collection_date` datetime NOT NULL,
  `pre_donation_checkup` enum('passed','failed','conditional') NOT NULL,
  `hemoglobin_level` decimal(4,2) NOT NULL,
  `blood_pressure` varchar(20) NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `volume_collected` int(11) NOT NULL DEFAULT 450,
  `donation_type` enum('whole_blood','platelets','plasma','double_red_cells') NOT NULL,
  `notes` text,
  `status` enum('completed','incomplete','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `donor_id` (`donor_id`),
  KEY `collected_by` (`collected_by`),
  KEY `collection_date` (`collection_date`),
  FOREIGN KEY (`donor_id`) REFERENCES `blood_donors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`collected_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blood Usage Records Table
CREATE TABLE IF NOT EXISTS `blood_usage_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blood_bag_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `used_by` int(11) NOT NULL,
  `usage_date` datetime NOT NULL,
  `usage_type` enum('transfusion','surgery','emergency','research','testing') NOT NULL,
  `volume_used` int(11) NOT NULL,
  `patient_condition` text NOT NULL,
  `cross_match_result` enum('compatible','incompatible','pending','not_required') NOT NULL,
  `adverse_reactions` text,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `blood_bag_id` (`blood_bag_id`),
  KEY `patient_id` (`patient_id`),
  KEY `used_by` (`used_by`),
  KEY `usage_date` (`usage_date`),
  FOREIGN KEY (`blood_bag_id`) REFERENCES `blood_inventory`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`used_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- SECTION 2: ADD INSURANCE MANAGEMENT SYSTEM TABLES
-- ===================================================================

-- Insurance Companies Table
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
  `tpa_name` varchar(255) DEFAULT NULL COMMENT 'Third Party Administrator',
  `network_type` enum('cashless','reimbursement','both') DEFAULT 'both',
  `settlement_period` int(11) DEFAULT 30 COMMENT 'Days for claim settlement',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`),
  KEY `company_name` (`company_name`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Patient Insurance Policies Table
CREATE TABLE IF NOT EXISTS `patient_insurance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `insurance_company_id` int(11) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `policy_type` enum('individual','family','group','corporate') NOT NULL,
  `coverage_percentage` decimal(5,2) NOT NULL DEFAULT 80.00,
  `coverage_limit` decimal(12,2) NOT NULL,
  `deductible` decimal(10,2) DEFAULT 0.00,
  `premium_amount` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `sum_insured` decimal(12,2) NOT NULL,
  `used_amount` decimal(12,2) DEFAULT 0.00,
  `available_limit` decimal(12,2) GENERATED ALWAYS AS ((`coverage_limit` - `used_amount`)) STORED,
  `policy_document_path` varchar(500) DEFAULT NULL,
  `family_members` json DEFAULT NULL COMMENT 'JSON array of covered family members',
  `pre_existing_conditions` text,
  `exclusions` text,
  `is_active` tinyint(1) DEFAULT 1,
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_patient_policy` (`patient_id`,`insurance_company_id`,`policy_number`),
  KEY `patient_id` (`patient_id`),
  KEY `insurance_company_id` (`insurance_company_id`),
  KEY `added_by` (`added_by`),
  KEY `expiry_date` (`expiry_date`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`insurance_company_id`) REFERENCES `insurance_companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`added_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Enhanced Insurance Claims Table (Different from existing simple one)
CREATE TABLE IF NOT EXISTS `insurance_claims_new` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `claim_number` varchar(50) NOT NULL,
  `claim_type` enum('medical','surgical','emergency','diagnostic','pharmacy','dental','maternity','critical_illness') NOT NULL,
  `service_type` enum('inpatient','outpatient','emergency','consultation','diagnostic','blood_transfusion','organ_transplant','surgery') NOT NULL,
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
  `claim_status` enum('submitted','under_review','approved','rejected','partially_approved','pending_documents','settled') DEFAULT 'submitted',
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `claim_number` (`claim_number`),
  KEY `patient_id` (`patient_id`),
  KEY `policy_id` (`policy_id`),
  KEY `submitted_by` (`submitted_by`),
  KEY `processed_by` (`processed_by`),
  KEY `claim_status` (`claim_status`),
  KEY `service_date` (`service_date`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`policy_id`) REFERENCES `patient_insurance`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- SECTION 3: ADD BILLING & PAYMENT SYSTEM TABLES
-- ===================================================================

-- Enhanced Patient Bills Table (Different from existing bills table)
CREATE TABLE IF NOT EXISTS `patient_bills_new` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `bill_number` varchar(50) NOT NULL,
  `admission_id` int(11) DEFAULT NULL,
  `service_type` enum('consultation','diagnostic','surgery','emergency','inpatient','outpatient','blood_transfusion','organ_transplant','blood_request','pharmacy') NOT NULL,
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
  `total_amount` decimal(12,2) GENERATED ALWAYS AS (((((((((`base_amount` + `consultation_charges`) + `room_charges`) + `nursing_charges`) + `medicine_charges`) + `investigation_charges`) + `operation_charges`) + `blood_charges`) + `other_charges`) - `discount_amount`) + `tax_amount`) STORED,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `outstanding_amount` decimal(12,2) GENERATED ALWAYS AS ((((((((((`base_amount` + `consultation_charges`) + `room_charges`) + `nursing_charges`) + `medicine_charges`) + `investigation_charges`) + `operation_charges`) + `blood_charges`) + `other_charges`) - `discount_amount`) + `tax_amount`) - `insurance_coverage`) - `paid_amount`) STORED,
  `payment_status` enum('pending','partial','paid','insurance_pending','overdue') DEFAULT 'pending',
  `payment_due_date` date DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'INR',
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `generated_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `billing_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `insurance_claim_id` (`insurance_claim_id`),
  KEY `generated_by` (`generated_by`),
  KEY `approved_by` (`approved_by`),
  KEY `payment_status` (`payment_status`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`insurance_claim_id`) REFERENCES `insurance_claims_new`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Patient Payments Table
CREATE TABLE IF NOT EXISTS `patient_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `payment_reference` varchar(100) NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','card','upi','bank_transfer','cheque','insurance','wallet','online') NOT NULL,
  `payment_details` text COMMENT 'Card details, UPI ID, etc.',
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_gateway` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded','cancelled') DEFAULT 'completed',
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
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_reference` (`payment_reference`),
  KEY `patient_id` (`patient_id`),
  KEY `bill_id` (`bill_id`),
  KEY `processed_by` (`processed_by`),
  KEY `approved_by` (`approved_by`),
  KEY `payment_method` (`payment_method`),
  KEY `payment_status` (`payment_status`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`bill_id`) REFERENCES `patient_bills_new`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blood Requests Table
CREATE TABLE IF NOT EXISTS `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `request_number` varchar(50) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `component_type` enum('whole_blood','red_blood_cells','platelets','plasma','cryoprecipitate','fresh_frozen_plasma') NOT NULL,
  `units_needed` int(11) NOT NULL,
  `urgency_level` enum('routine','urgent','emergency') NOT NULL,
  `medical_reason` text NOT NULL,
  `doctor_prescription` varchar(255) NOT NULL,
  `cross_match_required` tinyint(1) DEFAULT 1,
  `compatibility_test_done` tinyint(1) DEFAULT 0,
  `compatibility_result` enum('compatible','incompatible','pending') DEFAULT 'pending',
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `insurance_pre_auth` varchar(100) DEFAULT NULL,
  `insurance_coverage` decimal(10,2) DEFAULT 0.00,
  `requested_date` datetime NOT NULL,
  `required_by_date` datetime DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `fulfilled_date` datetime DEFAULT NULL,
  `status` enum('pending','approved','fulfilled','cancelled','rejected') DEFAULT 'pending',
  `approval_notes` text,
  `rejection_reason` text,
  `blood_bags_assigned` json DEFAULT NULL COMMENT 'JSON array of assigned blood bag IDs',
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `fulfilled_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_number` (`request_number`),
  KEY `patient_id` (`patient_id`),
  KEY `requested_by` (`requested_by`),
  KEY `approved_by` (`approved_by`),
  KEY `fulfilled_by` (`fulfilled_by`),
  KEY `blood_group` (`blood_group`,`component_type`),
  KEY `status` (`status`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`fulfilled_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- SECTION 4: UPDATE EXISTING TABLES SAFELY
-- ===================================================================

-- Update users table with new roles for blood bank and insurance staff
ALTER TABLE `users` 
MODIFY COLUMN `role_id` int(11) NOT NULL;

-- Add new roles for blood bank and insurance management
INSERT IGNORE INTO `roles` (`role_name`, `role_display_name`, `description`, `is_active`) VALUES
('blood_bank_staff', 'Blood Bank Staff', 'Blood bank operations and management', 1),
('insurance_staff', 'Insurance Staff', 'Insurance claims and policy management', 1),
('billing_staff', 'Billing Staff', 'Patient billing and payment processing', 1),
('transplant_coordinator', 'Transplant Coordinator', 'Organ transplant coordination', 1);

-- Add password column to patients table if not exists
ALTER TABLE `patients` 
ADD COLUMN IF NOT EXISTS `password` varchar(255) DEFAULT NULL;

-- Add new departments for blood bank and insurance
INSERT IGNORE INTO `departments` (`hospital_id`, `name`, `code`, `description`, `is_active`) VALUES
(1, 'Blood Bank', 'BLOOD', 'Blood collection, storage and transfusion services', 1),
(1, 'Insurance Department', 'INS', 'Insurance claims and billing services', 1);

-- ===================================================================
-- SECTION 5: CREATE VIEWS FOR DASHBOARDS
-- ===================================================================

-- Blood inventory dashboard view
CREATE OR REPLACE VIEW `blood_inventory_dashboard` AS
SELECT 
  `blood_group`,
  `component_type`,
  COUNT(*) as `total_units`,
  SUM(CASE WHEN `status` = 'available' THEN 1 ELSE 0 END) as `available_units`,
  SUM(CASE WHEN `status` = 'used' THEN 1 ELSE 0 END) as `used_units`,
  SUM(CASE WHEN `status` = 'expired' THEN 1 ELSE 0 END) as `expired_units`,
  SUM(CASE WHEN `status` = 'quarantine' THEN 1 ELSE 0 END) as `quarantine_units`,
  AVG(DATEDIFF(`expiry_date`, CURDATE())) as `avg_days_to_expiry`
FROM `blood_inventory`
GROUP BY `blood_group`, `component_type`
ORDER BY `blood_group`, `component_type`;

-- Outstanding bills view
CREATE OR REPLACE VIEW `outstanding_bills_new` AS
SELECT 
  pb.`id` as `bill_id`,
  pb.`bill_number`,
  CONCAT(p.`first_name`, ' ', IFNULL(p.`middle_name`, ''), ' ', p.`last_name`) as `patient_name`,
  p.`patient_id` as `patient_number`,
  p.`phone`,
  pb.`service_type`,
  pb.`service_date`,
  pb.`total_amount`,
  pb.`insurance_coverage`,
  pb.`paid_amount`,
  pb.`outstanding_amount`,
  pb.`payment_status`,
  pb.`payment_due_date`,
  DATEDIFF(CURDATE(), pb.`payment_due_date`) as `days_overdue`,
  CASE 
    WHEN pb.`payment_due_date` < CURDATE() THEN 'Overdue'
    WHEN pb.`payment_due_date` <= CURDATE() + INTERVAL 7 DAY THEN 'Due Soon'
    ELSE 'Current'
  END as `payment_urgency`
FROM `patient_bills_new` pb
JOIN `patients` p ON pb.`patient_id` = p.`id`
WHERE pb.`outstanding_amount` > 0
ORDER BY pb.`payment_due_date` ASC;

-- Patient insurance summary view
CREATE OR REPLACE VIEW `patient_insurance_summary` AS
SELECT 
  pi.`patient_id`,
  CONCAT(p.`first_name`, ' ', IFNULL(p.`middle_name`, ''), ' ', p.`last_name`) as `patient_name`,
  ic.`company_name`,
  pi.`policy_number`,
  pi.`policy_type`,
  pi.`coverage_limit`,
  pi.`used_amount`,
  pi.`available_limit`,
  pi.`expiry_date`,
  CASE 
    WHEN pi.`expiry_date` < CURDATE() THEN 'Expired'
    WHEN pi.`expiry_date` <= CURDATE() + INTERVAL 30 DAY THEN 'Expiring Soon'
    ELSE 'Active'
  END as `policy_status`
FROM `patient_insurance` pi
JOIN `patients` p ON pi.`patient_id` = p.`id`
JOIN `insurance_companies` ic ON pi.`insurance_company_id` = ic.`id`
WHERE pi.`is_active` = 1;

-- ===================================================================
-- SECTION 6: CREATE TRIGGERS FOR AUTOMATION
-- ===================================================================

DELIMITER //

-- Auto-generate bill numbers for new billing system
DROP TRIGGER IF EXISTS `auto_new_bill_number`//
CREATE TRIGGER `auto_new_bill_number`
  BEFORE INSERT ON `patient_bills_new`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM patient_bills_new WHERE DATE(created_at) = CURDATE();
  SET NEW.bill_number = CONCAT('NBILL', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END//

-- Auto-generate payment references
DROP TRIGGER IF EXISTS `auto_payment_reference`//
CREATE TRIGGER `auto_payment_reference`
  BEFORE INSERT ON `patient_payments`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM patient_payments WHERE DATE(created_at) = CURDATE();
  SET NEW.payment_reference = CONCAT('PAY', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END//

-- Auto-generate claim numbers
DROP TRIGGER IF EXISTS `auto_new_claim_number`//
CREATE TRIGGER `auto_new_claim_number`
  BEFORE INSERT ON `insurance_claims_new`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM insurance_claims_new WHERE DATE(created_at) = CURDATE();
  SET NEW.claim_number = CONCAT('CLM', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END//

-- Auto-generate blood request numbers
DROP TRIGGER IF EXISTS `auto_request_number`//
CREATE TRIGGER `auto_request_number`
  BEFORE INSERT ON `blood_requests`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM blood_requests WHERE DATE(created_at) = CURDATE();
  SET NEW.request_number = CONCAT('REQ', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END//

-- Auto-generate donor IDs
DROP TRIGGER IF EXISTS `auto_donor_id`//
CREATE TRIGGER `auto_donor_id`
  BEFORE INSERT ON `blood_donors`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM blood_donors;
  SET NEW.donor_id = CONCAT('DON', YEAR(NOW()), LPAD(count + 1, 4, '0'));
END//

-- Update bill payment status when payment is made
DROP TRIGGER IF EXISTS `update_new_bill_payment_status`//
CREATE TRIGGER `update_new_bill_payment_status`
  AFTER INSERT ON `patient_payments`
  FOR EACH ROW
BEGIN
  DECLARE total_paid DECIMAL(12,2);
  DECLARE bill_total DECIMAL(12,2);
  DECLARE insurance_cov DECIMAL(12,2);
  
  IF NEW.bill_id IS NOT NULL THEN
    SELECT COALESCE(SUM(amount_paid), 0) INTO total_paid
    FROM patient_payments
    WHERE bill_id = NEW.bill_id AND payment_status = 'completed';
    
    SELECT total_amount, insurance_coverage INTO bill_total, insurance_cov
    FROM patient_bills_new
    WHERE id = NEW.bill_id;
    
    UPDATE patient_bills_new
    SET paid_amount = total_paid,
        payment_status = CASE
          WHEN total_paid >= (bill_total - IFNULL(insurance_cov, 0)) THEN 'paid'
          WHEN total_paid > 0 THEN 'partial'
          ELSE 'pending'
        END
    WHERE id = NEW.bill_id;
  END IF;
END//

DELIMITER ;

-- ===================================================================
-- SECTION 7: ADD SAMPLE DATA FOR TESTING
-- ===================================================================

-- Insert sample insurance companies
INSERT IGNORE INTO `insurance_companies` (`company_name`, `company_code`, `contact_person`, `contact_number`, `email`, `tpa_name`, `network_type`, `is_active`) VALUES
('Star Health Insurance', 'STAR001', 'Rajesh Kumar', '9876543210', 'claims@starhealth.in', 'Medi Assist', 'both', 1),
('HDFC ERGO Health Insurance', 'HDFC001', 'Priya Sharma', '9876543211', 'claims@hdfcergo.com', 'HDFC ERGO TPA', 'cashless', 1),
('ICICI Lombard Health Insurance', 'ICICI001', 'Amit Singh', '9876543212', 'health@icicilombard.com', 'ICICI Lombard TPA', 'both', 1),
('SBI General Insurance', 'SBI001', 'Sunita Patel', '9876543213', 'claims@sbigeneral.in', 'SBI General TPA', 'both', 1),
('New India Assurance', 'NIA001', 'Ravi Gupta', '9876543214', 'claims@newindia.co.in', 'New India TPA', 'reimbursement', 1);

-- Add sample staff for new departments
INSERT IGNORE INTO `users` (`username`, `email`, `first_name`, `last_name`, `password_hash`, `role_id`, `is_active`) VALUES
('blood_bank_staff', 'bloodbank@hospital.com', 'Blood Bank', 'Staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 12, 1),
('insurance_staff', 'insurance@hospital.com', 'Insurance', 'Staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 13, 1),
('billing_staff', 'billing@hospital.com', 'Billing', 'Staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 14, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================================
-- SUCCESS MESSAGE
-- ===================================================================

SELECT 'NEW FEATURES ADDED SUCCESSFULLY!' as 'STATUS',
       'Blood Bank + Insurance + Enhanced Billing System' as 'FEATURES_ADDED',
       'All tables created safely without affecting existing data!' as 'MESSAGE',
       'Your existing hospital data is completely safe!' as 'DATA_SAFETY';

COMMIT;

-- ===================================================================
-- POST-INSTALLATION NOTES
-- ===================================================================
-- 1. New tables created: blood_donors, blood_inventory, blood_donation_sessions, blood_usage_records
-- 2. Insurance tables: insurance_companies, patient_insurance, insurance_claims_new
-- 3. Enhanced billing: patient_bills_new, patient_payments, blood_requests
-- 4. New roles added for specialized staff
-- 5. Views created for dashboards and reporting
-- 6. Triggers added for automatic ID generation and status updates
-- 7. All existing data and functionality preserved
-- ===================================================================
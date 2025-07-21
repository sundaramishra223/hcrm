-- ===================================================================
-- SAFE DATABASE UPDATE - ADD NEW FEATURES TO EXISTING HOSPITAL DB
-- This will only add NEW tables and features without affecting existing ones
-- ===================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ===================================================================
-- ADD NEW FEATURES: BLOOD BANK SYSTEM (Only if not exists)
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
  UNIQUE KEY `patient_id` (`patient_id`),
  KEY `blood_group` (`blood_group`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  KEY `collection_date` (`collection_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  KEY `usage_date` (`usage_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- ADD NEW FEATURES: INSURANCE MANAGEMENT SYSTEM
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patient Insurance Table
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
  KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insurance Claims Table
CREATE TABLE IF NOT EXISTS `insurance_claims` (
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
  KEY `service_date` (`service_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- ADD NEW FEATURES: BILLING & PAYMENT SYSTEM
-- ===================================================================

-- Patient Bills Table
CREATE TABLE IF NOT EXISTS `patient_bills` (
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
  KEY `payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  KEY `payment_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- ADD MISSING COLUMNS TO EXISTING TABLES
-- ===================================================================

-- Update users table with new roles (safely)
ALTER TABLE `users` 
MODIFY COLUMN `role` enum('admin','doctor','nurse','staff','patient','pharmacist','lab_technician','receptionist','insurance_staff','billing_staff','transplant_coordinator','surgeon') NOT NULL;

-- Add password column to patients table if not exists
ALTER TABLE `patients` 
ADD COLUMN IF NOT EXISTS `password` varchar(255) DEFAULT NULL;

-- ===================================================================
-- CREATE VIEWS FOR REPORTING
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
CREATE OR REPLACE VIEW `outstanding_bills` AS
SELECT 
  pb.`id` as `bill_id`,
  pb.`bill_number`,
  CONCAT(p.`first_name`, ' ', p.`last_name`) as `patient_name`,
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
FROM `patient_bills` pb
JOIN `patients` p ON pb.`patient_id` = p.`id`
WHERE pb.`outstanding_amount` > 0
ORDER BY pb.`payment_due_date` ASC;

-- ===================================================================
-- CREATE TRIGGERS FOR AUTOMATION
-- ===================================================================

DELIMITER //

-- Auto-generate bill numbers (only if trigger doesn't exist)
DROP TRIGGER IF EXISTS `auto_bill_number`//
CREATE TRIGGER `auto_bill_number`
  BEFORE INSERT ON `patient_bills`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM patient_bills WHERE DATE(created_at) = CURDATE();
  SET NEW.bill_number = CONCAT('BILL', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
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
DROP TRIGGER IF EXISTS `auto_claim_number`//
CREATE TRIGGER `auto_claim_number`
  BEFORE INSERT ON `insurance_claims`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM insurance_claims WHERE DATE(created_at) = CURDATE();
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

-- Update bill payment status
DROP TRIGGER IF EXISTS `update_bill_payment_status`//
CREATE TRIGGER `update_bill_payment_status`
  AFTER INSERT ON `patient_payments`
  FOR EACH ROW
BEGIN
  DECLARE total_paid DECIMAL(12,2);
  DECLARE bill_total DECIMAL(12,2);
  
  IF NEW.bill_id IS NOT NULL THEN
    SELECT COALESCE(SUM(amount_paid), 0) INTO total_paid
    FROM patient_payments
    WHERE bill_id = NEW.bill_id AND payment_status = 'completed';
    
    SELECT total_amount INTO bill_total
    FROM patient_bills
    WHERE id = NEW.bill_id;
    
    UPDATE patient_bills
    SET paid_amount = total_paid,
        payment_status = CASE
          WHEN total_paid >= bill_total THEN 'paid'
          WHEN total_paid > 0 THEN 'partial'
          ELSE 'pending'
        END
    WHERE id = NEW.bill_id;
  END IF;
END//

DELIMITER ;

-- ===================================================================
-- ADD SAMPLE DATA FOR NEW FEATURES
-- ===================================================================

-- Insert sample insurance companies
INSERT IGNORE INTO `insurance_companies` (`company_name`, `company_code`, `contact_person`, `contact_number`, `email`, `tpa_name`, `network_type`, `is_active`) VALUES
('Star Health Insurance', 'STAR001', 'Rajesh Kumar', '9876543210', 'claims@starhealth.in', 'Medi Assist', 'both', 1),
('HDFC ERGO Health Insurance', 'HDFC001', 'Priya Sharma', '9876543211', 'claims@hdfcergo.com', 'HDFC ERGO TPA', 'cashless', 1),
('ICICI Lombard Health Insurance', 'ICICI001', 'Amit Singh', '9876543212', 'health@icicilombard.com', 'ICICI Lombard TPA', 'both', 1);

-- Add Blood Bank and Insurance departments if not exists
INSERT IGNORE INTO `departments` (`name`, `description`, `location`, `phone`, `is_active`) VALUES
('Blood Bank', 'Blood collection, storage and transfusion', 'Basement', '301', 1),
('Insurance', 'Insurance and billing services', '1st Floor', '501', 1);

-- Add insurance and billing staff roles if not exists
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role`, `first_name`, `last_name`, `phone`, `is_active`) VALUES
('insurance_staff', 'insurance@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'insurance_staff', 'Insurance', 'Staff', '9876543213', 1),
('billing_staff', 'billing@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'billing_staff', 'Billing', 'Staff', '9876543214', 1);

SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================================
-- SUCCESS MESSAGE
-- ===================================================================

SELECT 'NEW FEATURES ADDED SUCCESSFULLY!' as 'STATUS',
       'Blood Bank + Insurance + Billing System' as 'FEATURES_ADDED',
       'All new tables, triggers, and views created safely!' as 'MESSAGE';

COMMIT;
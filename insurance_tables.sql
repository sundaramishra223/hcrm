-- Additional Insurance Management Tables for Hospital CRM
-- Add these tables to your existing hospital_crm database

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

-- --------------------------------------------------------
-- Insert sample insurance data
-- --------------------------------------------------------

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
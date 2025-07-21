-- ===================================================================
-- COMPLETE HOSPITAL CRM (HCRM) DATABASE SCHEMA - PART 3
-- Organ Management, Insurance, Equipment, Beds, Ambulance, Security, Audit
-- ===================================================================

-- ===================================================================
-- ORGAN TRANSPLANT SYSTEM
-- ===================================================================

-- Organ Donor Consent table
CREATE TABLE IF NOT EXISTS `organ_donor_consent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donor_id` int(11) NOT NULL,
  `consent_type` enum('full_body','specific_organs','research_only') NOT NULL,
  `consent_date` date NOT NULL,
  `witness_1` varchar(255) DEFAULT NULL,
  `witness_2` varchar(255) DEFAULT NULL,
  `legal_guardian` varchar(255) DEFAULT NULL,
  `consent_document_path` varchar(500) DEFAULT NULL,
  `notarized` tinyint(1) DEFAULT 0,
  `organs_consented` text DEFAULT NULL,
  `restrictions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `revoked_date` date DEFAULT NULL,
  `revocation_reason` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donor_id` (`donor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Organ Donations table
CREATE TABLE IF NOT EXISTS `organ_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donor_id` int(11) NOT NULL,
  `consent_id` int(11) NOT NULL,
  `donation_type` enum('living_donor','deceased_donor','brain_dead') NOT NULL,
  `organ_type` enum('kidney','liver','heart','lung','pancreas','intestine','cornea','skin','bone','tissue') NOT NULL,
  `medical_evaluation` text DEFAULT NULL,
  `brain_death_confirmation` tinyint(1) DEFAULT 0,
  `declaration_time` timestamp NULL DEFAULT NULL,
  `declaring_physician` int(11) DEFAULT NULL,
  `harvest_team_lead` int(11) DEFAULT NULL,
  `preservation_method` varchar(255) DEFAULT NULL,
  `preservation_time` timestamp NULL DEFAULT NULL,
  `viability_assessment` text DEFAULT NULL,
  `transplant_id` int(11) DEFAULT NULL,
  `status` enum('evaluated','harvested','preserved','transplanted','expired','rejected') DEFAULT 'evaluated',
  `legal_clearance` enum('pending','approved','rejected') DEFAULT 'pending',
  `ethics_committee_approval` enum('pending','approved','rejected') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donor_id` (`donor_id`),
  KEY `consent_id` (`consent_id`),
  KEY `organ_type` (`organ_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Organ Recipients table
CREATE TABLE IF NOT EXISTS `organ_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `organ_needed` enum('kidney','liver','heart','lung','pancreas','intestine','cornea','skin','bone','tissue') NOT NULL,
  `urgency_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `waiting_list_date` date NOT NULL,
  `priority_score` int(11) DEFAULT 0,
  `medical_compatibility` text DEFAULT NULL,
  `blood_type_compatibility` varchar(100) DEFAULT NULL,
  `tissue_type` varchar(255) DEFAULT NULL,
  `antibody_levels` varchar(255) DEFAULT NULL,
  `insurance_verification` tinyint(1) DEFAULT 0,
  `legal_consent` tinyint(1) DEFAULT 0,
  `guardian_consent` tinyint(1) DEFAULT 0,
  `ethics_approval` tinyint(1) DEFAULT 0,
  `transplant_date` date DEFAULT NULL,
  `status` enum('waiting','matched','transplanted','removed','deceased') DEFAULT 'waiting',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `organ_needed` (`organ_needed`),
  KEY `urgency_level` (`urgency_level`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Organ Transplants table
CREATE TABLE IF NOT EXISTS `organ_transplants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donation_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `surgery_date` timestamp NOT NULL,
  `lead_surgeon` int(11) NOT NULL,
  `surgical_team` text DEFAULT NULL,
  `operation_duration` int(11) DEFAULT NULL,
  `cross_match_result` enum('compatible','incompatible') NOT NULL,
  `immunosuppression_protocol` text DEFAULT NULL,
  `immediate_complications` text DEFAULT NULL,
  `post_operative_care` text DEFAULT NULL,
  `follow_up_schedule` text DEFAULT NULL,
  `success_rate` enum('excellent','good','fair','poor','failed') DEFAULT NULL,
  `rejection_episodes` int(11) DEFAULT 0,
  `legal_completion` tinyint(1) DEFAULT 0,
  `legal_documentation` varchar(500) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','failed','complications') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donation_id` (`donation_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `lead_surgeon` (`lead_surgeon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Organ Legal Rejections table
CREATE TABLE IF NOT EXISTS `organ_legal_rejections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `donation_id` int(11) DEFAULT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `transplant_id` int(11) DEFAULT NULL,
  `rejection_type` enum('consent_withdrawal','medical_incompatibility','legal_violation','ethics_violation','family_objection') NOT NULL,
  `rejection_reason` text NOT NULL,
  `legal_basis` text DEFAULT NULL,
  `rejecting_authority` varchar(255) NOT NULL,
  `rejection_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `documentation_path` varchar(500) DEFAULT NULL,
  `appeal_possible` tinyint(1) DEFAULT 1,
  `appeal_deadline` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `donation_id` (`donation_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `transplant_id` (`transplant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- INSURANCE SYSTEM
-- ===================================================================

-- Insurance Companies table
CREATE TABLE IF NOT EXISTS `insurance_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `company_name` varchar(255) NOT NULL,
  `company_code` varchar(50) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `policy_types` text DEFAULT NULL,
  `coverage_percentage` decimal(5,2) DEFAULT NULL,
  `max_coverage_amount` decimal(12,2) DEFAULT NULL,
  `claim_processing_time` varchar(100) DEFAULT NULL,
  `preferred_hospitals` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`),
  KEY `hospital_id` (`hospital_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Patient Insurance table
CREATE TABLE IF NOT EXISTS `patient_insurance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `patient_id` int(11) NOT NULL,
  `insurance_company_id` int(11) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `policy_type` enum('individual','family','group','corporate') DEFAULT 'individual',
  `policy_holder_name` varchar(255) NOT NULL,
  `relationship_to_patient` varchar(100) DEFAULT NULL,
  `policy_start_date` date NOT NULL,
  `policy_end_date` date NOT NULL,
  `coverage_amount` decimal(12,2) DEFAULT NULL,
  `deductible_amount` decimal(10,2) DEFAULT NULL,
  `copay_percentage` decimal(5,2) DEFAULT NULL,
  `pre_authorization_required` tinyint(1) DEFAULT 0,
  `coverage_details` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `claim_limit_per_year` decimal(12,2) DEFAULT NULL,
  `claims_used_this_year` decimal(12,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `verification_status` enum('pending','verified','rejected','expired') DEFAULT 'pending',
  `verification_date` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `policy_number` (`policy_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `insurance_company_id` (`insurance_company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insurance Claims table
CREATE TABLE IF NOT EXISTS `insurance_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `claim_number` varchar(50) NOT NULL,
  `patient_insurance_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `claim_type` enum('hospitalization','outpatient','emergency','surgery','diagnostic','pharmacy') DEFAULT 'outpatient',
  `claim_amount` decimal(12,2) NOT NULL,
  `approved_amount` decimal(12,2) DEFAULT NULL,
  `deductible_applied` decimal(10,2) DEFAULT NULL,
  `copay_amount` decimal(10,2) DEFAULT NULL,
  `claim_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `submission_date` timestamp NULL DEFAULT NULL,
  `processing_date` timestamp NULL DEFAULT NULL,
  `approval_date` timestamp NULL DEFAULT NULL,
  `settlement_date` timestamp NULL DEFAULT NULL,
  `status` enum('draft','submitted','under_review','approved','rejected','settled','cancelled') DEFAULT 'draft',
  `rejection_reason` text DEFAULT NULL,
  `diagnosis_codes` varchar(500) DEFAULT NULL,
  `procedure_codes` varchar(500) DEFAULT NULL,
  `supporting_documents` text DEFAULT NULL,
  `claim_adjuster` varchar(255) DEFAULT NULL,
  `settlement_amount` decimal(12,2) DEFAULT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `claim_number` (`claim_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `patient_insurance_id` (`patient_insurance_id`),
  KEY `patient_id` (`patient_id`),
  KEY `bill_id` (`bill_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- EQUIPMENT MANAGEMENT
-- ===================================================================

-- Equipment table
CREATE TABLE IF NOT EXISTS `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `equipment_code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `cost` decimal(12,2) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `status` enum('operational','maintenance','out_of_order','retired') DEFAULT 'operational',
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `maintenance_schedule` varchar(255) DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `operating_instructions` text DEFAULT NULL,
  `safety_guidelines` text DEFAULT NULL,
  `calibration_required` tinyint(1) DEFAULT 0,
  `last_calibration` date DEFAULT NULL,
  `next_calibration` date DEFAULT NULL,
  `responsible_person` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_code` (`equipment_code`),
  KEY `hospital_id` (`hospital_id`),
  KEY `department_id` (`department_id`),
  KEY `status` (`status`),
  KEY `responsible_person` (`responsible_person`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Equipment Maintenance table
CREATE TABLE IF NOT EXISTS `equipment_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `equipment_id` int(11) NOT NULL,
  `maintenance_type` enum('preventive','corrective','calibration','inspection') NOT NULL,
  `maintenance_date` date NOT NULL,
  `performed_by` varchar(255) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `vendor_company` varchar(255) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `parts_replaced` text DEFAULT NULL,
  `work_performed` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `warranty_claim` tinyint(1) DEFAULT 0,
  `downtime_hours` decimal(5,2) DEFAULT NULL,
  `maintenance_completion_status` enum('completed','pending','cancelled') DEFAULT 'completed',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `equipment_id` (`equipment_id`),
  KEY `maintenance_date` (`maintenance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- BED MANAGEMENT
-- ===================================================================

-- Beds table
CREATE TABLE IF NOT EXISTS `beds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `bed_number` varchar(50) NOT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `wing` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `bed_type` enum('general','icu','emergency','pediatric','maternity','isolation','vip') DEFAULT 'general',
  `bed_category` enum('regular','oxygen','cardiac_monitor','ventilator') DEFAULT 'regular',
  `patient_id` int(11) DEFAULT NULL,
  `status` enum('available','occupied','maintenance','reserved','out_of_service') DEFAULT 'available',
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `equipment_attached` text DEFAULT NULL,
  `last_cleaned` timestamp NULL DEFAULT NULL,
  `cleaned_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bed_number` (`bed_number`),
  KEY `hospital_id` (`hospital_id`),
  KEY `department_id` (`department_id`),
  KEY `patient_id` (`patient_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Bed Assignments table
CREATE TABLE IF NOT EXISTS `bed_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_id` int(11) NOT NULL DEFAULT 1,
  `bed_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `admission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `discharge_date` timestamp NULL DEFAULT NULL,
  `admission_type` enum('emergency','scheduled','transfer','observation') DEFAULT 'scheduled',
  `admission_reason` text DEFAULT NULL,
  `assigned_by` int(11) NOT NULL,
  `attending_doctor` int(11) DEFAULT NULL,
  `nursing_station` varchar(100) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `status` enum('active','discharged','transferred') DEFAULT 'active',
  `discharge_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hospital_id` (`hospital_id`),
  KEY `bed_id` (`bed_id`),
  KEY `patient_id` (`patient_id`),
  KEY `attending_doctor` (`attending_doctor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===================================================================
-- CONTINUED IN PART 4...
-- ===================================================================
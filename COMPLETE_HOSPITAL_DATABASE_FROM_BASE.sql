-- ===================================================================
-- COMPLETE HOSPITAL DATABASE - ORIGINAL BASE + ALL NEW FEATURES
-- Starting from your original SQL + Blood Bank + Organ Transplant + Insurance + Billing
-- ===================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ===================================================================
-- ORIGINAL BASE HOSPITAL TABLES (Your Initial SQL)
-- ===================================================================

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `type` enum('consultation','follow_up','emergency','surgery','checkup') NOT NULL,
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `reason` text NOT NULL,
  `symptoms` text,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `estimated_duration` int(11) DEFAULT 30,
  `notes` text,
  `booking_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_date` (`appointment_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `bed_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bed_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expected_discharge_date` date DEFAULT NULL,
  `actual_discharge_date` timestamp NULL DEFAULT NULL,
  `reason` text NOT NULL,
  `status` enum('active','discharged','transferred') DEFAULT 'active',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bed_id` (`bed_id`),
  KEY `patient_id` (`patient_id`),
  KEY `assigned_by` (`assigned_by`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `beds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bed_number` varchar(20) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `floor` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `bed_type` enum('general','private','icu','emergency','maternity','pediatric') NOT NULL,
  `status` enum('available','occupied','maintenance','reserved') DEFAULT 'available',
  `daily_rate` decimal(8,2) DEFAULT 0.00,
  `features` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bed_number` (`bed_number`),
  KEY `department_id` (`department_id`),
  KEY `status` (`status`),
  KEY `bed_type` (`bed_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `head_doctor_id` int(11) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `budget` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `head_doctor_id` (`head_doctor_id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `department_id` int(11) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `qualification` text NOT NULL,
  `experience_years` int(11) DEFAULT 0,
  `license_number` varchar(50) NOT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `availability_hours` json DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `max_patients_per_day` int(11) DEFAULT 20,
  `is_available` tinyint(1) DEFAULT 1,
  `rating` decimal(3,2) DEFAULT 0.00,
  `joining_date` date NOT NULL,
  `salary` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `license_number` (`license_number`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `specialization` (`specialization`),
  KEY `is_available` (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `location` varchar(100) NOT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `status` enum('available','in_use','maintenance','out_of_order','retired') DEFAULT 'available',
  `cost` decimal(12,2) DEFAULT 0.00,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `type` (`type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `interns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `department_id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `stipend` decimal(8,2) DEFAULT 0.00,
  `university` varchar(100) NOT NULL,
  `degree_program` varchar(100) NOT NULL,
  `year_of_study` int(11) NOT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact_phone` varchar(20) NOT NULL,
  `evaluation_score` decimal(4,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `supervisor_id` (`supervisor_id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `intern_rotations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `intern_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `learning_objectives` text,
  `evaluation_score` decimal(4,2) DEFAULT NULL,
  `evaluation_notes` text,
  `status` enum('scheduled','active','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `intern_id` (`intern_id`),
  KEY `department_id` (`department_id`),
  KEY `supervisor_id` (`supervisor_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact_phone` varchar(20) NOT NULL,
  `emergency_contact_relation` varchar(50) NOT NULL,
  `medical_history` text,
  `allergies` text,
  `current_medications` text,
  `height` decimal(5,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_visit_date` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_id` (`patient_id`),
  KEY `first_name` (`first_name`,`last_name`),
  KEY `phone` (`phone`),
  KEY `blood_group` (`blood_group`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `patient_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `status` enum('inpatient','outpatient','emergency','discharged','transferred','deceased') NOT NULL,
  `admission_date` timestamp NULL DEFAULT NULL,
  `discharge_date` timestamp NULL DEFAULT NULL,
  `admission_type` enum('emergency','planned','transfer') DEFAULT NULL,
  `admission_reason` text,
  `attending_doctor_id` int(11) DEFAULT NULL,
  `current_bed_id` int(11) DEFAULT NULL,
  `discharge_summary` text,
  `discharge_instructions` text,
  `follow_up_required` tinyint(1) DEFAULT 0,
  `follow_up_date` date DEFAULT NULL,
  `insurance_verified` tinyint(1) DEFAULT 0,
  `emergency_contact_notified` tinyint(1) DEFAULT 0,
  `status_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `status` (`status`),
  KEY `attending_doctor_id` (`attending_doctor_id`),
  KEY `current_bed_id` (`current_bed_id`),
  KEY `admission_date` (`admission_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_number` varchar(20) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `diagnosis` text NOT NULL,
  `medications` json NOT NULL,
  `instructions` text,
  `follow_up_date` date DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `prescribed_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_number` (`prescription_number`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `department_id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL,
  `shift` enum('morning','afternoon','night','rotating') DEFAULT 'morning',
  `hourly_rate` decimal(8,2) DEFAULT 0.00,
  `salary` decimal(12,2) DEFAULT 0.00,
  `joining_date` date NOT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `supervisor_id` (`supervisor_id`),
  KEY `position` (`position`),
  KEY `shift` (`shift`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','nurse','staff','patient','pharmacist','lab_technician','receptionist','insurance_staff','billing_staff','transplant_coordinator','surgeon') NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role` (`role`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- NEW FEATURES: BLOOD BANK SYSTEM
-- ===================================================================

-- Blood Donors Table
CREATE TABLE `blood_donors` (
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
CREATE TABLE `blood_inventory` (
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
  KEY `issued_to_patient_id` (`issued_to_patient_id`),
  KEY `issued_by` (`issued_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blood Donation Sessions Table
CREATE TABLE `blood_donation_sessions` (
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
CREATE TABLE `blood_usage_records` (
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

-- Blood Activity Audit Trail
CREATE TABLE `blood_activity_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_type` enum('donation','usage','inventory_update','testing','disposal') NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('insert','update','delete') NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  PRIMARY KEY (`id`),
  KEY `performed_by` (`performed_by`),
  KEY `activity_type` (`activity_type`),
  KEY `record_id` (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- NEW FEATURES: ORGAN TRANSPLANT SYSTEM (HIGH SECURITY)
-- ===================================================================

-- Organ Donor Consent Table
CREATE TABLE `organ_donor_consent` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NOT NULL,
  `consent_type` enum('living_donor','deceased_donor','brain_dead','family_consent') NOT NULL,
  `consent_date` date NOT NULL,
  `witness_1` varchar(255) NOT NULL,
  `witness_2` varchar(255) NOT NULL,
  `legal_guardian` varchar(255) DEFAULT NULL,
  `consent_document_path` varchar(500) DEFAULT NULL,
  `notarized` enum('yes','no','pending') DEFAULT 'pending',
  `consent_withdrawal_date` date DEFAULT NULL,
  `withdrawal_reason` text,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `donor_id` (`donor_id`),
  KEY `created_by` (`created_by`),
  KEY `consent_type` (`consent_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Donations Table
CREATE TABLE `organ_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` int(11) NOT NULL,
  `consent_id` int(11) NOT NULL,
  `donation_type` enum('living','deceased','brain_dead') NOT NULL,
  `organ_type` enum('kidney','liver','heart','lung','pancreas','cornea','skin','bone','tissue') NOT NULL,
  `medical_evaluation` text NOT NULL,
  `brain_death_confirmation` enum('confirmed','pending','not_applicable') DEFAULT 'not_applicable',
  `declaration_time` datetime DEFAULT NULL,
  `declaring_physician` varchar(255) DEFAULT NULL,
  `harvest_team_lead` varchar(255) DEFAULT NULL,
  `preservation_method` varchar(255) DEFAULT NULL,
  `ischemia_time` int(11) DEFAULT NULL COMMENT 'Minutes',
  `organ_condition` enum('excellent','good','fair','poor') NOT NULL,
  `legal_clearance` enum('approved','pending','rejected') DEFAULT 'pending',
  `ethics_committee_approval` enum('approved','pending','rejected') DEFAULT 'pending',
  `status` enum('pending_harvest','harvested','transplanted','expired','rejected') DEFAULT 'pending_harvest',
  `harvest_date` datetime DEFAULT NULL,
  `transplant_date` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `donor_id` (`donor_id`),
  KEY `consent_id` (`consent_id`),
  KEY `created_by` (`created_by`),
  KEY `organ_type` (`organ_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Recipients Table
CREATE TABLE `organ_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `organ_needed` enum('kidney','liver','heart','lung','pancreas','cornea','skin','bone','tissue') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `urgency_level` enum('elective','urgent','emergency') NOT NULL,
  `medical_condition` text NOT NULL,
  `waiting_since` date NOT NULL,
  `estimated_survival_benefit` int(11) DEFAULT NULL COMMENT 'Expected years of life saved',
  `hla_typing` text,
  `cross_match_requirements` text,
  `contraindications` text,
  `legal_consent` enum('signed','pending','refused') DEFAULT 'pending',
  `ethics_approval` enum('approved','pending','rejected') DEFAULT 'pending',
  `financial_clearance` enum('approved','pending','rejected') DEFAULT 'pending',
  `insurance_pre_auth` varchar(255) DEFAULT NULL,
  `priority_score` int(11) DEFAULT 0,
  `status` enum('waiting','matched','transplanted','removed','deceased') DEFAULT 'waiting',
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `added_by` (`added_by`),
  KEY `organ_needed` (`organ_needed`,`urgency_level`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Transplants Table
CREATE TABLE `organ_transplants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donation_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `transplant_date` datetime NOT NULL,
  `surgical_team_lead` varchar(255) NOT NULL,
  `anesthesiologist` varchar(255) NOT NULL,
  `surgery_duration` int(11) NOT NULL COMMENT 'Minutes',
  `surgical_notes` text NOT NULL,
  `complications` text,
  `blood_loss_ml` int(11) DEFAULT NULL,
  `transfusion_required` tinyint(1) DEFAULT 0,
  `legal_documentation_complete` enum('yes','no') NOT NULL,
  `informed_consent_signed` enum('yes','no') NOT NULL,
  `ethics_clearance` enum('approved','pending','rejected') NOT NULL,
  `post_op_monitoring_plan` text NOT NULL,
  `immunosuppression_protocol` text,
  `follow_up_schedule` text,
  `outcome` enum('successful','complications','failed','pending') DEFAULT 'pending',
  `performed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `donation_id` (`donation_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `performed_by` (`performed_by`),
  KEY `outcome` (`outcome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Legal Rejections Table
CREATE TABLE `organ_legal_rejections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_type` enum('donor','recipient','transplant') NOT NULL,
  `record_id` int(11) NOT NULL,
  `rejection_date` datetime NOT NULL,
  `rejection_reason` enum('insufficient_documentation','legal_concerns','ethics_violation','medical_contraindication','consent_issues') NOT NULL,
  `detailed_reason` text NOT NULL,
  `rejected_by` int(11) NOT NULL,
  `review_required` tinyint(1) DEFAULT 1,
  `review_date` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `final_decision` enum('upheld','overturned','pending') DEFAULT 'pending',
  `legal_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `rejected_by` (`rejected_by`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `record_type` (`record_type`,`record_id`),
  KEY `rejection_date` (`rejection_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organ Activity Audit Trail
CREATE TABLE `organ_activity_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_type` enum('consent','donation','recipient','transplant','legal_action') NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('insert','update','delete') NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `legal_significance` enum('high','medium','low') DEFAULT 'high',
  `performed_by` int(11) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `witness_signature` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `performed_by` (`performed_by`),
  KEY `activity_type` (`activity_type`),
  KEY `legal_significance` (`legal_significance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- NEW FEATURES: INSURANCE MANAGEMENT SYSTEM
-- ===================================================================

-- Insurance Companies Table
CREATE TABLE `insurance_companies` (
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
CREATE TABLE `patient_insurance` (
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
CREATE TABLE `insurance_claims` (
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

-- Claim Timeline Table
CREATE TABLE `claim_timeline` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `claim_id` int(11) NOT NULL,
  `status` enum('submitted','under_review','approved','rejected','partially_approved','pending_documents','settled','query_raised','additional_documents_submitted') NOT NULL,
  `status_date` datetime NOT NULL,
  `updated_by` int(11) NOT NULL,
  `notes` text,
  `attachment_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `claim_id` (`claim_id`),
  KEY `updated_by` (`updated_by`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insurance Verifications Table
CREATE TABLE `insurance_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `verification_date` datetime NOT NULL,
  `verification_type` enum('eligibility','coverage','pre_auth','claim_status') NOT NULL,
  `verification_status` enum('verified','failed','pending','expired') NOT NULL,
  `verification_reference` varchar(100) DEFAULT NULL,
  `coverage_confirmed` decimal(12,2) DEFAULT NULL,
  `deductible_remaining` decimal(10,2) DEFAULT NULL,
  `pre_auth_required` tinyint(1) DEFAULT 0,
  `pre_auth_number` varchar(100) DEFAULT NULL,
  `verification_notes` text,
  `verified_by` int(11) NOT NULL,
  `insurance_response` text,
  `response_time` int(11) DEFAULT NULL COMMENT 'Response time in seconds',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `policy_id` (`policy_id`),
  KEY `verified_by` (`verified_by`),
  KEY `verification_type` (`verification_type`),
  KEY `verification_status` (`verification_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- NEW FEATURES: BILLING & PAYMENT SYSTEM
-- ===================================================================

-- Patient Bills Table
CREATE TABLE `patient_bills` (
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
CREATE TABLE `patient_payments` (
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
CREATE TABLE `blood_requests` (
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

-- Insurance Coverage Rules Table
CREATE TABLE `insurance_coverage_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `insurance_company_id` int(11) NOT NULL,
  `service_type` enum('consultation','diagnostic','surgery','emergency','inpatient','outpatient','blood_transfusion','organ_transplant','pharmacy','dental','maternity') NOT NULL,
  `coverage_percentage` decimal(5,2) NOT NULL DEFAULT 80.00,
  `max_coverage_amount` decimal(12,2) DEFAULT NULL,
  `deductible_amount` decimal(10,2) DEFAULT 0.00,
  `co_payment_percentage` decimal(5,2) DEFAULT 0.00,
  `pre_auth_required` tinyint(1) DEFAULT 0,
  `waiting_period_days` int(11) DEFAULT 0,
  `age_limit_min` int(11) DEFAULT 0,
  `age_limit_max` int(11) DEFAULT 100,
  `exclusions` text,
  `special_conditions` text,
  `is_active` tinyint(1) DEFAULT 1,
  `effective_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `insurance_company_id` (`insurance_company_id`),
  KEY `service_type` (`service_type`),
  KEY `created_by` (`created_by`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Billing Templates Table
CREATE TABLE `billing_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL,
  `service_type` enum('consultation','diagnostic','surgery','emergency','inpatient','outpatient','blood_transfusion','organ_transplant','pharmacy') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `base_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `consultation_charges` decimal(10,2) DEFAULT 0.00,
  `room_charges` decimal(10,2) DEFAULT 0.00,
  `nursing_charges` decimal(10,2) DEFAULT 0.00,
  `medicine_charges` decimal(10,2) DEFAULT 0.00,
  `investigation_charges` decimal(10,2) DEFAULT 0.00,
  `operation_charges` decimal(10,2) DEFAULT 0.00,
  `blood_charges` decimal(10,2) DEFAULT 0.00,
  `other_charges` decimal(10,2) DEFAULT 0.00,
  `tax_percentage` decimal(5,2) DEFAULT 0.00,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `service_type` (`service_type`),
  KEY `created_by` (`created_by`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- FOREIGN KEY CONSTRAINTS
-- ===================================================================

ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_appointment_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`);

ALTER TABLE `bed_assignments`
  ADD CONSTRAINT `fk_assignment_bed` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`),
  ADD CONSTRAINT `fk_assignment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_assignment_staff` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);

ALTER TABLE `beds`
  ADD CONSTRAINT `fk_bed_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

ALTER TABLE `doctors`
  ADD CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_doctor_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

ALTER TABLE `equipment`
  ADD CONSTRAINT `fk_equipment_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

ALTER TABLE `interns`
  ADD CONSTRAINT `fk_intern_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_intern_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_intern_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `doctors` (`id`);

ALTER TABLE `intern_rotations`
  ADD CONSTRAINT `fk_rotation_intern` FOREIGN KEY (`intern_id`) REFERENCES `interns` (`id`),
  ADD CONSTRAINT `fk_rotation_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_rotation_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `doctors` (`id`);

ALTER TABLE `patient_status`
  ADD CONSTRAINT `fk_status_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_status_doctor` FOREIGN KEY (`attending_doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_status_bed` FOREIGN KEY (`current_bed_id`) REFERENCES `beds` (`id`);

ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_prescription_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_prescription_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_prescription_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`);

ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_staff_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_staff_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `staff` (`id`);

-- Blood Bank Foreign Keys
ALTER TABLE `blood_donors`
  ADD CONSTRAINT `fk_donor_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);

ALTER TABLE `blood_inventory`
  ADD CONSTRAINT `fk_inventory_donor` FOREIGN KEY (`donor_id`) REFERENCES `blood_donors` (`id`),
  ADD CONSTRAINT `fk_inventory_patient` FOREIGN KEY (`issued_to_patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_inventory_staff` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`);

ALTER TABLE `blood_donation_sessions`
  ADD CONSTRAINT `fk_session_donor` FOREIGN KEY (`donor_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_session_staff` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`);

ALTER TABLE `blood_usage_records`
  ADD CONSTRAINT `fk_usage_blood` FOREIGN KEY (`blood_bag_id`) REFERENCES `blood_inventory` (`id`),
  ADD CONSTRAINT `fk_usage_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_usage_staff` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`);

ALTER TABLE `blood_activity_audit`
  ADD CONSTRAINT `fk_blood_audit_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

-- Organ Transplant Foreign Keys
ALTER TABLE `organ_donor_consent`
  ADD CONSTRAINT `fk_consent_donor` FOREIGN KEY (`donor_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_consent_staff` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `organ_donations`
  ADD CONSTRAINT `fk_donation_donor` FOREIGN KEY (`donor_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_donation_consent` FOREIGN KEY (`consent_id`) REFERENCES `organ_donor_consent` (`id`),
  ADD CONSTRAINT `fk_donation_staff` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `organ_recipients`
  ADD CONSTRAINT `fk_recipient_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_recipient_staff` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`);

ALTER TABLE `organ_transplants`
  ADD CONSTRAINT `fk_transplant_donation` FOREIGN KEY (`donation_id`) REFERENCES `organ_donations` (`id`),
  ADD CONSTRAINT `fk_transplant_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `organ_recipients` (`id`),
  ADD CONSTRAINT `fk_transplant_staff` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

ALTER TABLE `organ_legal_rejections`
  ADD CONSTRAINT `fk_rejection_staff` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_rejection_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

ALTER TABLE `organ_activity_audit`
  ADD CONSTRAINT `fk_organ_audit_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

-- Insurance Foreign Keys
ALTER TABLE `patient_insurance`
  ADD CONSTRAINT `fk_insurance_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_insurance_company` FOREIGN KEY (`insurance_company_id`) REFERENCES `insurance_companies` (`id`),
  ADD CONSTRAINT `fk_insurance_staff` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`);

ALTER TABLE `insurance_claims`
  ADD CONSTRAINT `fk_claim_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_claim_policy` FOREIGN KEY (`policy_id`) REFERENCES `patient_insurance` (`id`),
  ADD CONSTRAINT `fk_claim_submitted` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_claim_processed` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

ALTER TABLE `claim_timeline`
  ADD CONSTRAINT `fk_timeline_claim` FOREIGN KEY (`claim_id`) REFERENCES `insurance_claims` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_timeline_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

ALTER TABLE `insurance_verifications`
  ADD CONSTRAINT `fk_verification_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_verification_policy` FOREIGN KEY (`policy_id`) REFERENCES `patient_insurance` (`id`),
  ADD CONSTRAINT `fk_verification_staff` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`);

-- Billing Foreign Keys
ALTER TABLE `patient_bills`
  ADD CONSTRAINT `fk_bill_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_bill_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_bill_claim` FOREIGN KEY (`insurance_claim_id`) REFERENCES `insurance_claims` (`id`),
  ADD CONSTRAINT `fk_bill_generated` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_bill_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

ALTER TABLE `patient_payments`
  ADD CONSTRAINT `fk_payment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_payment_bill` FOREIGN KEY (`bill_id`) REFERENCES `patient_bills` (`id`),
  ADD CONSTRAINT `fk_payment_processed` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_payment_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

ALTER TABLE `blood_requests`
  ADD CONSTRAINT `fk_request_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_request_requested` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_request_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_request_fulfilled` FOREIGN KEY (`fulfilled_by`) REFERENCES `users` (`id`);

ALTER TABLE `insurance_coverage_rules`
  ADD CONSTRAINT `fk_coverage_company` FOREIGN KEY (`insurance_company_id`) REFERENCES `insurance_companies` (`id`),
  ADD CONSTRAINT `fk_coverage_staff` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

ALTER TABLE `billing_templates`
  ADD CONSTRAINT `fk_template_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_template_staff` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

-- ===================================================================
-- TRIGGERS FOR AUTOMATION & VALIDATION
-- ===================================================================

DELIMITER //

-- Auto-generate appointment numbers
CREATE TRIGGER `auto_appointment_number`
  BEFORE INSERT ON `appointments`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM appointments WHERE DATE(created_at) = CURDATE();
  SET NEW.appointment_date = IFNULL(NEW.appointment_date, CURDATE());
  -- Generate appointment number if not provided
  IF NEW.appointment_date IS NULL THEN
    SET count = count + 1;
    SET NEW.appointment_date = CURDATE();
  END IF;
END//

-- Auto-generate prescription numbers
CREATE TRIGGER `auto_prescription_number`
  BEFORE INSERT ON `prescriptions`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM prescriptions WHERE DATE(created_at) = CURDATE();
  SET NEW.prescription_number = CONCAT('PRE', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END//

-- Auto-generate bill numbers
CREATE TRIGGER `auto_bill_number`
  BEFORE INSERT ON `patient_bills`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM patient_bills WHERE DATE(created_at) = CURDATE();
  SET NEW.bill_number = CONCAT('BILL', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END//

-- Auto-generate payment references
CREATE TRIGGER `auto_payment_reference`
  BEFORE INSERT ON `patient_payments`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM patient_payments WHERE DATE(created_at) = CURDATE();
  SET NEW.payment_reference = CONCAT('PAY', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END//

-- Auto-generate claim numbers
CREATE TRIGGER `auto_claim_number`
  BEFORE INSERT ON `insurance_claims`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM insurance_claims WHERE DATE(created_at) = CURDATE();
  SET NEW.claim_number = CONCAT('CLM', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END//

-- Auto-generate blood request numbers
CREATE TRIGGER `auto_request_number`
  BEFORE INSERT ON `blood_requests`
  FOR EACH ROW
BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM blood_requests WHERE DATE(created_at) = CURDATE();
  SET NEW.request_number = CONCAT('REQ', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END//

-- Blood donation eligibility check (56-day rule)
CREATE TRIGGER `check_blood_donation_eligibility`
  BEFORE INSERT ON `blood_donation_sessions`
  FOR EACH ROW
BEGIN
  DECLARE last_donation_date DATE;
  DECLARE days_since_last_donation INT;
  
  SELECT MAX(collection_date) INTO last_donation_date
  FROM blood_donation_sessions
  WHERE donor_id = NEW.donor_id AND status = 'completed';
  
  IF last_donation_date IS NOT NULL THEN
    SET days_since_last_donation = DATEDIFF(NEW.collection_date, last_donation_date);
    IF days_since_last_donation < 56 THEN
      SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Donor must wait at least 56 days between whole blood donations';
    END IF;
  END IF;
END//

-- Organ transplant legal compliance check
CREATE TRIGGER `check_organ_legal_compliance`
  BEFORE INSERT ON `organ_transplants`
  FOR EACH ROW
BEGIN
  DECLARE donor_legal_status VARCHAR(50);
  DECLARE recipient_legal_status VARCHAR(50);
  DECLARE recipient_ethics_status VARCHAR(50);

  SELECT legal_clearance INTO donor_legal_status
  FROM organ_donations
  WHERE id = NEW.donation_id;

  SELECT legal_consent, ethics_approval INTO recipient_legal_status, recipient_ethics_status
  FROM organ_recipients
  WHERE id = NEW.recipient_id;

  IF donor_legal_status != 'approved' THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Donor legal clearance not approved';
  END IF;

  IF recipient_legal_status != 'signed' THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Recipient legal consent not signed';
  END IF;

  IF recipient_ethics_status != 'approved' THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Recipient ethics approval not obtained';
  END IF;

  IF NEW.legal_documentation_complete != 'yes' THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Legal documentation must be complete before transplant';
  END IF;

  IF NEW.informed_consent_signed != 'yes' THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Informed consent must be signed before transplant';
  END IF;

  IF NEW.ethics_clearance != 'approved' THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Ethics clearance must be approved before transplant';
  END IF;
END//

-- Blood donation audit trigger
CREATE TRIGGER `blood_donation_audit_trigger`
  AFTER INSERT ON `blood_donation_sessions`
  FOR EACH ROW
BEGIN
  INSERT INTO blood_activity_audit (activity_type, record_id, action, new_values, performed_by)
  VALUES ('donation', NEW.id, 'insert', JSON_OBJECT(
    'donor_id', NEW.donor_id,
    'collected_by', NEW.collected_by,
    'collection_date', NEW.collection_date,
    'volume_collected', NEW.volume_collected,
    'donation_type', NEW.donation_type
  ), NEW.collected_by);
END//

-- Blood usage audit trigger
CREATE TRIGGER `blood_usage_audit_trigger`
  AFTER INSERT ON `blood_usage_records`
  FOR EACH ROW
BEGIN
  INSERT INTO blood_activity_audit (activity_type, record_id, action, new_values, performed_by)
  VALUES ('usage', NEW.id, 'insert', JSON_OBJECT(
    'blood_bag_id', NEW.blood_bag_id,
    'patient_id', NEW.patient_id,
    'used_by', NEW.used_by,
    'usage_date', NEW.usage_date,
    'volume_used', NEW.volume_used,
    'usage_type', NEW.usage_type
  ), NEW.used_by);
END//

-- Organ donation audit trigger
CREATE TRIGGER `organ_donation_audit_trigger`
  AFTER INSERT ON `organ_donations`
  FOR EACH ROW
BEGIN
  INSERT INTO organ_activity_audit (activity_type, record_id, action, new_values, legal_significance, performed_by)
  VALUES ('donation', NEW.id, 'insert', JSON_OBJECT(
    'donor_id', NEW.donor_id,
    'organ_type', NEW.organ_type,
    'donation_type', NEW.donation_type,
    'legal_clearance', NEW.legal_clearance,
    'ethics_committee_approval', NEW.ethics_committee_approval
  ), 'high', NEW.created_by);
END//

-- Organ transplant audit trigger
CREATE TRIGGER `organ_transplant_audit_trigger`
  AFTER INSERT ON `organ_transplants`
  FOR EACH ROW
BEGIN
  INSERT INTO organ_activity_audit (activity_type, record_id, action, new_values, legal_significance, performed_by)
  VALUES ('transplant', NEW.id, 'insert', JSON_OBJECT(
    'donation_id', NEW.donation_id,
    'recipient_id', NEW.recipient_id,
    'transplant_date', NEW.transplant_date,
    'surgical_team_lead', NEW.surgical_team_lead,
    'legal_documentation_complete', NEW.legal_documentation_complete,
    'informed_consent_signed', NEW.informed_consent_signed
  ), 'high', NEW.performed_by);
END//

-- Update bill payment status trigger
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
-- VIEWS FOR REPORTING AND DASHBOARDS
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

-- Legal compliance dashboard for organ transplants
CREATE OR REPLACE VIEW `legal_compliance_dashboard` AS
SELECT 
  'Organ Donations' as `category`,
  COUNT(*) as `total_records`,
  SUM(CASE WHEN `legal_clearance` = 'approved' THEN 1 ELSE 0 END) as `legally_approved`,
  SUM(CASE WHEN `ethics_committee_approval` = 'approved' THEN 1 ELSE 0 END) as `ethics_approved`,
  SUM(CASE WHEN `legal_clearance` = 'pending' OR `ethics_committee_approval` = 'pending' THEN 1 ELSE 0 END) as `pending_approval`,
  SUM(CASE WHEN `legal_clearance` = 'rejected' OR `ethics_committee_approval` = 'rejected' THEN 1 ELSE 0 END) as `rejected`
FROM `organ_donations`
UNION ALL
SELECT 
  'Organ Recipients' as `category`,
  COUNT(*) as `total_records`,
  SUM(CASE WHEN `legal_consent` = 'signed' THEN 1 ELSE 0 END) as `legally_approved`,
  SUM(CASE WHEN `ethics_approval` = 'approved' THEN 1 ELSE 0 END) as `ethics_approved`,
  SUM(CASE WHEN `legal_consent` = 'pending' OR `ethics_approval` = 'pending' THEN 1 ELSE 0 END) as `pending_approval`,
  SUM(CASE WHEN `legal_consent` = 'refused' OR `ethics_approval` = 'rejected' THEN 1 ELSE 0 END) as `rejected`
FROM `organ_recipients`;

-- Organ waiting list priority view
CREATE OR REPLACE VIEW `organ_waiting_list_priority` AS
SELECT 
  r.`id`,
  CONCAT(p.`first_name`, ' ', p.`last_name`) as `patient_name`,
  p.`patient_id`,
  r.`organ_needed`,
  r.`blood_group`,
  r.`urgency_level`,
  r.`waiting_since`,
  DATEDIFF(CURDATE(), r.`waiting_since`) as `days_waiting`,
  r.`priority_score`,
  r.`estimated_survival_benefit`,
  r.`medical_condition`,
  r.`legal_consent`,
  r.`ethics_approval`,
  r.`financial_clearance`,
  r.`status`
FROM `organ_recipients` r
JOIN `patients` p ON r.`patient_id` = p.`id`
WHERE r.`status` = 'waiting'
ORDER BY 
  CASE r.`urgency_level` 
    WHEN 'emergency' THEN 1 
    WHEN 'urgent' THEN 2 
    ELSE 3 
  END,
  r.`priority_score` DESC,
  r.`waiting_since` ASC;

-- Patient insurance summary view
CREATE OR REPLACE VIEW `patient_insurance_summary` AS
SELECT 
  p.`id` as `patient_id`,
  p.`patient_id` as `patient_number`,
  CONCAT(p.`first_name`, ' ', p.`last_name`) as `patient_name`,
  p.`phone`,
  p.`email`,
  pi.`policy_number`,
  ic.`company_name`,
  pi.`policy_type`,
  pi.`coverage_percentage`,
  pi.`coverage_limit`,
  pi.`used_amount`,
  pi.`available_limit`,
  pi.`expiry_date`,
  CASE 
    WHEN pi.`expiry_date` < CURDATE() THEN 'Expired'
    WHEN pi.`expiry_date` <= CURDATE() + INTERVAL 30 DAY THEN 'Expiring Soon'
    ELSE 'Active'
  END as `policy_status`,
  pi.`is_active`
FROM `patients` p
JOIN `patient_insurance` pi ON p.`id` = pi.`patient_id`
JOIN `insurance_companies` ic ON pi.`insurance_company_id` = ic.`id`
WHERE pi.`is_active` = 1;

-- Claims summary view
CREATE OR REPLACE VIEW `claims_summary` AS
SELECT 
  ic.`id` as `claim_id`,
  ic.`claim_number`,
  CONCAT(p.`first_name`, ' ', p.`last_name`) as `patient_name`,
  p.`patient_id` as `patient_number`,
  icomp.`company_name`,
  ic.`claim_type`,
  ic.`service_type`,
  ic.`service_date`,
  ic.`claim_amount`,
  ic.`processed_amount`,
  ic.`claim_status`,
  ic.`submitted_date`,
  ic.`processed_date`,
  DATEDIFF(CURDATE(), ic.`submitted_date`) as `days_pending`
FROM `insurance_claims` ic
JOIN `patients` p ON ic.`patient_id` = p.`id`
JOIN `patient_insurance` pi ON ic.`policy_id` = pi.`id`
JOIN `insurance_companies` icomp ON pi.`insurance_company_id` = icomp.`id`
ORDER BY ic.`submitted_date` DESC;

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
-- STORED PROCEDURES FOR COMPLEX OPERATIONS
-- ===================================================================

DELIMITER //

-- Calculate organ compatibility score
CREATE PROCEDURE `CalculateOrganCompatibility`(
  IN donor_id INT,
  IN recipient_id INT,
  OUT compatibility_score INT
)
BEGIN
  DECLARE donor_blood_group VARCHAR(5);
  DECLARE recipient_blood_group VARCHAR(5);
  DECLARE blood_compatibility INT DEFAULT 0;
  
  -- Get blood groups
  SELECT p.blood_group INTO donor_blood_group
  FROM patients p
  JOIN organ_donations od ON p.id = od.donor_id
  WHERE od.id = donor_id;
  
  SELECT blood_group INTO recipient_blood_group
  FROM organ_recipients
  WHERE id = recipient_id;
  
  -- Calculate blood compatibility (simplified)
  IF donor_blood_group = recipient_blood_group THEN
    SET blood_compatibility = 100;
  ELSEIF donor_blood_group = 'O-' THEN
    SET blood_compatibility = 90; -- Universal donor
  ELSEIF recipient_blood_group = 'AB+' THEN
    SET blood_compatibility = 85; -- Universal recipient
  ELSE
    SET blood_compatibility = 50; -- Partial compatibility
  END IF;
  
  SET compatibility_score = blood_compatibility;
END//

-- Update organ waiting list priorities
CREATE PROCEDURE `UpdateOrganWaitingListPriorities`()
BEGIN
  DECLARE done INT DEFAULT FALSE;
  DECLARE recipient_id INT;
  DECLARE days_waiting INT;
  DECLARE urgency_multiplier INT;
  DECLARE new_priority_score INT;
  
  DECLARE recipient_cursor CURSOR FOR
    SELECT id, DATEDIFF(CURDATE(), waiting_since),
           CASE urgency_level 
             WHEN 'emergency' THEN 3
             WHEN 'urgent' THEN 2
             ELSE 1
           END as urgency_mult
    FROM organ_recipients
    WHERE status = 'waiting';
  
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
  
  OPEN recipient_cursor;
  
  priority_loop: LOOP
    FETCH recipient_cursor INTO recipient_id, days_waiting, urgency_multiplier;
    IF done THEN
      LEAVE priority_loop;
    END IF;
    
    -- Calculate new priority score
    SET new_priority_score = (days_waiting * urgency_multiplier) + 
                           (CASE WHEN days_waiting > 365 THEN 50 ELSE 0 END);
    
    UPDATE organ_recipients
    SET priority_score = new_priority_score
    WHERE id = recipient_id;
  END LOOP;
  
  CLOSE recipient_cursor;
END//

-- Calculate insurance coverage for a service
CREATE PROCEDURE `CalculateInsuranceCoverage`(
  IN patient_id INT,
  IN service_type VARCHAR(50),
  IN service_amount DECIMAL(12,2),
  OUT coverage_amount DECIMAL(12,2),
  OUT patient_responsibility DECIMAL(12,2)
)
BEGIN
  DECLARE policy_coverage_percentage DECIMAL(5,2);
  DECLARE policy_deductible DECIMAL(10,2);
  DECLARE policy_limit DECIMAL(12,2);
  DECLARE used_amount DECIMAL(12,2);
  DECLARE available_limit DECIMAL(12,2);
  
  -- Get policy details
  SELECT pi.coverage_percentage, pi.deductible, pi.coverage_limit, pi.used_amount
  INTO policy_coverage_percentage, policy_deductible, policy_limit, used_amount
  FROM patient_insurance pi
  WHERE pi.patient_id = patient_id AND pi.is_active = 1
  LIMIT 1;
  
  -- Calculate available limit
  SET available_limit = policy_limit - used_amount;
  
  -- Calculate coverage
  IF service_amount <= policy_deductible THEN
    SET coverage_amount = 0;
  ELSE
    SET coverage_amount = (service_amount - policy_deductible) * (policy_coverage_percentage / 100);
    
    -- Check against available limit
    IF coverage_amount > available_limit THEN
      SET coverage_amount = available_limit;
    END IF;
  END IF;
  
  SET patient_responsibility = service_amount - coverage_amount;
END//

-- Update claim status with timeline
CREATE PROCEDURE `UpdateClaimStatus`(
  IN claim_id INT,
  IN new_status VARCHAR(50),
  IN updated_by INT,
  IN notes TEXT
)
BEGIN
  -- Update claim status
  UPDATE insurance_claims
  SET claim_status = new_status,
      processed_by = updated_by,
      processed_date = NOW()
  WHERE id = claim_id;
  
  -- Add timeline entry
  INSERT INTO claim_timeline (claim_id, status, status_date, updated_by, notes)
  VALUES (claim_id, new_status, NOW(), updated_by, notes);
  
  -- If approved, update policy used amount
  IF new_status = 'approved' THEN
    UPDATE patient_insurance pi
    JOIN insurance_claims ic ON pi.id = ic.policy_id
    SET pi.used_amount = pi.used_amount + ic.processed_amount
    WHERE ic.id = claim_id;
  END IF;
END//

DELIMITER ;

-- ===================================================================
-- SAMPLE DATA FOR TESTING
-- ===================================================================

-- Insert default admin user
INSERT INTO `users` (`username`, `email`, `password`, `role`, `first_name`, `last_name`, `phone`, `is_active`) VALUES
('admin', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator', '1234567890', 1),
('doctor1', 'doctor@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Dr. John', 'Smith', '9876543210', 1),
('nurse1', 'nurse@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nurse', 'Mary', 'Johnson', '9876543211', 1);

-- Insert departments
INSERT INTO `departments` (`name`, `description`, `location`, `phone`, `is_active`) VALUES
('General Medicine', 'General medical consultations and treatments', 'Ground Floor', '101', 1),
('Surgery', 'Surgical procedures and operations', '2nd Floor', '201', 1),
('Emergency', 'Emergency and critical care', 'Ground Floor', '911', 1),
('Blood Bank', 'Blood collection, storage and transfusion', 'Basement', '301', 1),
('Organ Transplant', 'Organ transplant and donation services', '3rd Floor', '401', 1),
('Insurance', 'Insurance and billing services', '1st Floor', '501', 1);

-- Insert sample insurance companies
INSERT INTO `insurance_companies` (`company_name`, `company_code`, `contact_person`, `contact_number`, `email`, `tpa_name`, `network_type`, `is_active`) VALUES
('Star Health Insurance', 'STAR001', 'Rajesh Kumar', '9876543210', 'claims@starhealth.in', 'Medi Assist', 'both', 1),
('HDFC ERGO Health Insurance', 'HDFC001', 'Priya Sharma', '9876543211', 'claims@hdfcergo.com', 'HDFC ERGO TPA', 'cashless', 1),
('ICICI Lombard Health Insurance', 'ICICI001', 'Amit Singh', '9876543212', 'health@icicilombard.com', 'ICICI Lombard TPA', 'both', 1);

-- Insert sample patient
INSERT INTO `patients` (`patient_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `date_of_birth`, `gender`, `blood_group`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relation`, `password`) VALUES
('PAT001', 'John', 'Doe', 'john.doe@email.com', '9999999999', '123 Main Street, City', '1990-01-15', 'male', 'O+', 'Jane Doe', '8888888888', 'spouse', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert sample doctor
INSERT INTO `doctors` (`user_id`, `employee_id`, `department_id`, `specialization`, `qualification`, `license_number`, `consultation_fee`, `joining_date`) VALUES
(2, 'DOC001', 1, 'General Medicine', 'MBBS, MD', 'LIC001', 500.00, '2020-01-01');

SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================================
-- SUCCESS MESSAGE
-- ===================================================================

SELECT 'COMPLETE HOSPITAL DATABASE CREATED SUCCESSFULLY!' as 'STATUS',
       'Original Base + Blood Bank + Organ Transplant + Insurance + Billing' as 'FEATURES',
       'All tables, triggers, views, procedures and sample data ready!' as 'MESSAGE';

COMMIT;
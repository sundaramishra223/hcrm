-- ===================================================================
-- COMPLETE CONSOLIDATED HOSPITAL MANAGEMENT SYSTEM
-- ERROR-FREE SQL DATABASE SCHEMA
-- Combines Original Hospital System + Blood Bank + Organ Transplant + Insurance
-- ===================================================================

-- Disable foreign key checks during setup
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables if they exist (in correct order)
DROP TABLE IF EXISTS `claim_timeline`;
DROP TABLE IF EXISTS `insurance_claims`;
DROP TABLE IF EXISTS `patient_payments`;
DROP TABLE IF EXISTS `patient_bills`;
DROP TABLE IF EXISTS `blood_requests`;
DROP TABLE IF EXISTS `patient_insurance`;
DROP TABLE IF EXISTS `insurance_companies`;
DROP TABLE IF EXISTS `organ_legal_rejections`;
DROP TABLE IF EXISTS `organ_transplants`;
DROP TABLE IF EXISTS `organ_recipients`;
DROP TABLE IF EXISTS `organ_donations`;
DROP TABLE IF EXISTS `organ_donor_consent`;
DROP TABLE IF EXISTS `blood_usage_records`;
DROP TABLE IF EXISTS `blood_donation_sessions`;
DROP TABLE IF EXISTS `organ_activity_audit`;
DROP TABLE IF EXISTS `blood_activity_audit`;
DROP TABLE IF EXISTS `blood_inventory`;
DROP TABLE IF EXISTS `blood_donors`;
DROP TABLE IF EXISTS `prescriptions`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `bed_assignments`;
DROP TABLE IF EXISTS `beds`;
DROP TABLE IF EXISTS `equipment`;
DROP TABLE IF EXISTS `patient_status`;
DROP TABLE IF EXISTS `patients`;
DROP TABLE IF EXISTS `intern_rotations`;
DROP TABLE IF EXISTS `interns`;
DROP TABLE IF EXISTS `staff`;
DROP TABLE IF EXISTS `doctors`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `departments`;

-- ===================================================================
-- CORE HOSPITAL TABLES
-- ===================================================================

-- Departments Table
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
  KEY `idx_department_head` (`head_doctor_id`),
  KEY `idx_department_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users Table (Authentication)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','doctor','nurse','staff','patient','pharmacist','lab_technician','receptionist','insurance_staff','billing_staff') NOT NULL,
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
  KEY `idx_user_role` (`role`),
  KEY `idx_user_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Doctors Table
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
  KEY `fk_doctor_user` (`user_id`),
  KEY `fk_doctor_department` (`department_id`),
  KEY `idx_doctor_specialization` (`specialization`),
  KEY `idx_doctor_available` (`is_available`),
  CONSTRAINT `fk_doctor_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Staff Table
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
  KEY `fk_staff_user` (`user_id`),
  KEY `fk_staff_department` (`department_id`),
  KEY `fk_staff_supervisor` (`supervisor_id`),
  KEY `idx_staff_position` (`position`),
  KEY `idx_staff_shift` (`shift`),
  CONSTRAINT `fk_staff_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `fk_staff_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `staff` (`id`),
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patients Table
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
  KEY `idx_patient_name` (`first_name`,`last_name`),
  KEY `idx_patient_phone` (`phone`),
  KEY `idx_patient_blood_group` (`blood_group`),
  KEY `idx_patient_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Equipment Table
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
  KEY `fk_equipment_department` (`department_id`),
  KEY `idx_equipment_type` (`type`),
  KEY `idx_equipment_status` (`status`),
  CONSTRAINT `fk_equipment_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Beds Table
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
  KEY `fk_bed_department` (`department_id`),
  KEY `idx_bed_status` (`status`),
  KEY `idx_bed_type` (`bed_type`),
  CONSTRAINT `fk_bed_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bed Assignments Table
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
  KEY `fk_assignment_bed` (`bed_id`),
  KEY `fk_assignment_patient` (`patient_id`),
  KEY `fk_assignment_staff` (`assigned_by`),
  KEY `idx_assignment_status` (`status`),
  CONSTRAINT `fk_assignment_bed` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`),
  CONSTRAINT `fk_assignment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_assignment_staff` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Appointments Table
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_number` varchar(20) NOT NULL,
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
  UNIQUE KEY `appointment_number` (`appointment_number`),
  KEY `fk_appointment_patient` (`patient_id`),
  KEY `fk_appointment_doctor` (`doctor_id`),
  KEY `idx_appointment_date` (`appointment_date`),
  KEY `idx_appointment_status` (`status`),
  CONSTRAINT `fk_appointment_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prescriptions Table
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
  KEY `fk_prescription_patient` (`patient_id`),
  KEY `fk_prescription_doctor` (`doctor_id`),
  KEY `fk_prescription_appointment` (`appointment_id`),
  KEY `idx_prescription_status` (`status`),
  CONSTRAINT `fk_prescription_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  CONSTRAINT `fk_prescription_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_prescription_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- BLOOD BANK SYSTEM TABLES
-- ===================================================================

-- Blood Donors Table (Reference to Patients)
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
  KEY `idx_donor_blood_group` (`blood_group`),
  KEY `idx_donor_active` (`is_active`),
  CONSTRAINT `fk_donor_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
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
  KEY `fk_inventory_donor` (`donor_id`),
  KEY `fk_inventory_patient` (`issued_to_patient_id`),
  KEY `fk_inventory_staff` (`issued_by`),
  KEY `idx_blood_group_component` (`blood_group`,`component_type`),
  KEY `idx_inventory_status` (`status`),
  KEY `idx_expiry_date` (`expiry_date`),
  CONSTRAINT `fk_inventory_donor` FOREIGN KEY (`donor_id`) REFERENCES `blood_donors` (`id`),
  CONSTRAINT `fk_inventory_patient` FOREIGN KEY (`issued_to_patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_inventory_staff` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`)
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
  KEY `fk_session_donor` (`donor_id`),
  KEY `fk_session_staff` (`collected_by`),
  KEY `idx_collection_date` (`collection_date`),
  CONSTRAINT `fk_session_donor` FOREIGN KEY (`donor_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_session_staff` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`)
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
  KEY `fk_usage_blood` (`blood_bag_id`),
  KEY `fk_usage_patient` (`patient_id`),
  KEY `fk_usage_staff` (`used_by`),
  KEY `idx_usage_date` (`usage_date`),
  CONSTRAINT `fk_usage_blood` FOREIGN KEY (`blood_bag_id`) REFERENCES `blood_inventory` (`id`),
  CONSTRAINT `fk_usage_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_usage_staff` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- ORGAN TRANSPLANT SYSTEM TABLES
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
  KEY `fk_consent_donor` (`donor_id`),
  KEY `fk_consent_staff` (`created_by`),
  KEY `idx_consent_type` (`consent_type`),
  CONSTRAINT `fk_consent_donor` FOREIGN KEY (`donor_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_consent_staff` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
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
  KEY `fk_donation_donor` (`donor_id`),
  KEY `fk_donation_consent` (`consent_id`),
  KEY `fk_donation_staff` (`created_by`),
  KEY `idx_organ_type` (`organ_type`),
  KEY `idx_donation_status` (`status`),
  CONSTRAINT `fk_donation_consent` FOREIGN KEY (`consent_id`) REFERENCES `organ_donor_consent` (`id`),
  CONSTRAINT `fk_donation_donor` FOREIGN KEY (`donor_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_donation_staff` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
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
  KEY `fk_recipient_patient` (`patient_id`),
  KEY `fk_recipient_staff` (`added_by`),
  KEY `idx_organ_urgency` (`organ_needed`,`urgency_level`),
  KEY `idx_recipient_status` (`status`),
  CONSTRAINT `fk_recipient_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_recipient_staff` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`)
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
  KEY `fk_transplant_donation` (`donation_id`),
  KEY `fk_transplant_recipient` (`recipient_id`),
  KEY `fk_transplant_staff` (`performed_by`),
  KEY `idx_transplant_outcome` (`outcome`),
  CONSTRAINT `fk_transplant_donation` FOREIGN KEY (`donation_id`) REFERENCES `organ_donations` (`id`),
  CONSTRAINT `fk_transplant_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `organ_recipients` (`id`),
  CONSTRAINT `fk_transplant_staff` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- INSURANCE SYSTEM TABLES
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
  KEY `idx_company_name` (`company_name`),
  KEY `idx_company_active` (`is_active`)
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
  KEY `fk_insurance_patient` (`patient_id`),
  KEY `fk_insurance_company` (`insurance_company_id`),
  KEY `fk_insurance_staff` (`added_by`),
  KEY `idx_policy_expiry` (`expiry_date`),
  CONSTRAINT `fk_insurance_company` FOREIGN KEY (`insurance_company_id`) REFERENCES `insurance_companies` (`id`),
  CONSTRAINT `fk_insurance_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_insurance_staff` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`)
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
  KEY `fk_claim_patient` (`patient_id`),
  KEY `fk_claim_policy` (`policy_id`),
  KEY `fk_claim_submitted` (`submitted_by`),
  KEY `fk_claim_processed` (`processed_by`),
  KEY `idx_claim_status` (`claim_status`),
  KEY `idx_service_date` (`service_date`),
  CONSTRAINT `fk_claim_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_claim_policy` FOREIGN KEY (`policy_id`) REFERENCES `patient_insurance` (`id`),
  CONSTRAINT `fk_claim_processed` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_claim_submitted` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- BILLING SYSTEM TABLES
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
  KEY `fk_bill_patient` (`patient_id`),
  KEY `fk_bill_doctor` (`doctor_id`),
  KEY `fk_bill_claim` (`insurance_claim_id`),
  KEY `fk_bill_generated` (`generated_by`),
  KEY `fk_bill_approved` (`approved_by`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `fk_bill_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_bill_claim` FOREIGN KEY (`insurance_claim_id`) REFERENCES `insurance_claims` (`id`),
  CONSTRAINT `fk_bill_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `fk_bill_generated` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_bill_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
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
  KEY `fk_payment_patient` (`patient_id`),
  KEY `fk_payment_bill` (`bill_id`),
  KEY `fk_payment_processed` (`processed_by`),
  KEY `fk_payment_approved` (`approved_by`),
  KEY `idx_payment_method` (`payment_method`),
  KEY `idx_payment_status` (`payment_status`),
  CONSTRAINT `fk_payment_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_payment_bill` FOREIGN KEY (`bill_id`) REFERENCES `patient_bills` (`id`),
  CONSTRAINT `fk_payment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_payment_processed` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`)
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
  KEY `fk_request_patient` (`patient_id`),
  KEY `fk_request_requested` (`requested_by`),
  KEY `fk_request_approved` (`approved_by`),
  KEY `fk_request_fulfilled` (`fulfilled_by`),
  KEY `idx_blood_group_component` (`blood_group`,`component_type`),
  KEY `idx_request_status` (`status`),
  CONSTRAINT `fk_request_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_request_fulfilled` FOREIGN KEY (`fulfilled_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_request_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `fk_request_requested` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- AUDIT TRAIL TABLES
-- ===================================================================

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
  KEY `fk_blood_audit_user` (`performed_by`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_record_id` (`record_id`),
  CONSTRAINT `fk_blood_audit_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
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
  KEY `fk_organ_audit_user` (`performed_by`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_legal_significance` (`legal_significance`),
  CONSTRAINT `fk_organ_audit_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
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
  KEY `fk_timeline_claim` (`claim_id`),
  KEY `fk_timeline_user` (`updated_by`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_timeline_claim` FOREIGN KEY (`claim_id`) REFERENCES `insurance_claims` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_timeline_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===================================================================
-- TRIGGERS FOR AUTOMATION
-- ===================================================================

DELIMITER //

-- Auto-generate appointment numbers
CREATE TRIGGER `generate_appointment_number`
  BEFORE INSERT ON `appointments`
  FOR EACH ROW
BEGIN
  DECLARE appointment_count INT;
  SELECT COUNT(*) INTO appointment_count FROM appointments WHERE DATE(created_at) = CURDATE();
  SET NEW.appointment_number = CONCAT('APT', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(appointment_count + 1, 4, '0'));
END//

-- Auto-generate prescription numbers
CREATE TRIGGER `generate_prescription_number`
  BEFORE INSERT ON `prescriptions`
  FOR EACH ROW
BEGIN
  DECLARE prescription_count INT;
  SELECT COUNT(*) INTO prescription_count FROM prescriptions WHERE DATE(created_at) = CURDATE();
  SET NEW.prescription_number = CONCAT('PRE', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(prescription_count + 1, 4, '0'));
END//

-- Auto-generate bill numbers
CREATE TRIGGER `generate_bill_number`
  BEFORE INSERT ON `patient_bills`
  FOR EACH ROW
BEGIN
  DECLARE bill_count INT;
  SELECT COUNT(*) INTO bill_count FROM patient_bills WHERE DATE(created_at) = CURDATE();
  SET NEW.bill_number = CONCAT('BILL', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(bill_count + 1, 4, '0'));
END//

-- Auto-generate payment references
CREATE TRIGGER `generate_payment_reference`
  BEFORE INSERT ON `patient_payments`
  FOR EACH ROW
BEGIN
  DECLARE payment_count INT;
  SELECT COUNT(*) INTO payment_count FROM patient_payments WHERE DATE(created_at) = CURDATE();
  SET NEW.payment_reference = CONCAT('PAY', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(payment_count + 1, 4, '0'));
END//

-- Auto-generate blood request numbers
CREATE TRIGGER `generate_request_number`
  BEFORE INSERT ON `blood_requests`
  FOR EACH ROW
BEGIN
  DECLARE request_count INT;
  SELECT COUNT(*) INTO request_count FROM blood_requests WHERE DATE(created_at) = CURDATE();
  SET NEW.request_number = CONCAT('REQ', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(request_count + 1, 4, '0'));
END//

-- Auto-generate claim numbers
CREATE TRIGGER `generate_claim_number`
  BEFORE INSERT ON `insurance_claims`
  FOR EACH ROW
BEGIN
  DECLARE claim_count INT;
  SELECT COUNT(*) INTO claim_count FROM insurance_claims WHERE DATE(created_at) = CURDATE();
  SET NEW.claim_number = CONCAT('CLM', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(claim_count + 1, 4, '0'));
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
-- SAMPLE DATA
-- ===================================================================

-- Insert default admin user
INSERT INTO `users` (`username`, `email`, `password`, `role`, `first_name`, `last_name`, `phone`, `is_active`) VALUES
('admin', 'admin@hospital.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator', '1234567890', 1);

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

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ===================================================================
-- VIEWS FOR REPORTING
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
-- SUCCESS MESSAGE
-- ===================================================================

SELECT 'COMPLETE CONSOLIDATED HOSPITAL SYSTEM DATABASE CREATED SUCCESSFULLY!' as 'STATUS',
       'All tables, triggers, views and sample data have been set up.' as 'MESSAGE',
       'System includes: Hospital Management + Blood Bank + Organ Transplant + Insurance + Billing' as 'FEATURES';

COMMIT;
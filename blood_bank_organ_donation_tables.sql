-- ============================================================================
-- BLOOD BANK MANAGEMENT TABLES (UPDATED)
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `blood_donors`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_donors`;
CREATE TABLE `blood_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` varchar(20) NOT NULL UNIQUE,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `status` enum('active','inactive','deferred') DEFAULT 'active',
  `last_donation_date` date DEFAULT NULL,
  `total_donations` int(11) DEFAULT 0,
  `registered_by` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_id` (`donor_id`),
  KEY `blood_group` (`blood_group`),
  KEY `status` (`status`),
  KEY `registered_by` (`registered_by`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `blood_donors_ibfk_1` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`),
  CONSTRAINT `blood_donors_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `blood_donations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_donations`;
CREATE TABLE `blood_donations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donation_id` varchar(20) NOT NULL UNIQUE,
  `donor_id` int(11) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_collected` int(11) NOT NULL DEFAULT 1,
  `donation_date` date NOT NULL,
  `collection_center` varchar(100) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `hemoglobin_level` decimal(3,1) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `temperature` decimal(3,1) DEFAULT NULL,
  `medical_clearance` text DEFAULT NULL,
  `status` enum('pending','completed','rejected') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `donation_id` (`donation_id`),
  KEY `donor_id` (`donor_id`),
  KEY `blood_group` (`blood_group`),
  KEY `donation_date` (`donation_date`),
  KEY `status` (`status`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `blood_donations_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `blood_donors` (`id`),
  CONSTRAINT `blood_donations_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `blood_inventory`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_inventory`;
CREATE TABLE `blood_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `total_units` int(11) NOT NULL DEFAULT 0,
  `available_units` int(11) NOT NULL DEFAULT 0,
  `reserved_units` int(11) NOT NULL DEFAULT 0,
  `expired_units` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `blood_group` (`blood_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `blood_requests`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `blood_requests`;
CREATE TABLE `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` varchar(20) NOT NULL UNIQUE,
  `patient_id` int(11) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `units_required` int(11) NOT NULL DEFAULT 1,
  `urgency_level` enum('normal','urgent','critical') DEFAULT 'normal',
  `required_date` date NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `hospital_unit` varchar(100) DEFAULT NULL,
  `medical_reason` text DEFAULT NULL,
  `status` enum('pending','approved','fulfilled','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `fulfilled_by` int(11) DEFAULT NULL,
  `fulfilled_date` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_id` (`request_id`),
  KEY `patient_id` (`patient_id`),
  KEY `blood_group` (`blood_group`),
  KEY `status` (`status`),
  KEY `urgency_level` (`urgency_level`),
  KEY `doctor_id` (`doctor_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  KEY `fulfilled_by` (`fulfilled_by`),
  CONSTRAINT `blood_requests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `blood_requests_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blood_requests_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `blood_requests_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `blood_requests_ibfk_5` FOREIGN KEY (`fulfilled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ORGAN DONATION MANAGEMENT TABLES (UPDATED)
-- ============================================================================

-- --------------------------------------------------------
-- Table structure for table `organ_donors`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_donors`;
CREATE TABLE `organ_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_id` varchar(20) NOT NULL UNIQUE,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `organs_to_donate` text NOT NULL,
  `medical_history` text DEFAULT NULL,
  `consent_date` date NOT NULL,
  `consent_document` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','deceased','donated') DEFAULT 'active',
  `registered_by` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_id` (`donor_id`),
  KEY `blood_group` (`blood_group`),
  KEY `status` (`status`),
  KEY `consent_date` (`consent_date`),
  KEY `registered_by` (`registered_by`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `organ_donors_ibfk_1` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`),
  CONSTRAINT `organ_donors_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `organ_inventory`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_inventory`;
CREATE TABLE `organ_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `organ_id` varchar(20) NOT NULL UNIQUE,
  `donor_id` int(11) NOT NULL,
  `organ_type` varchar(50) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `harvest_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `hospital_location` varchar(100) NOT NULL,
  `medical_condition` text DEFAULT NULL,
  `status` enum('available','reserved','transplanted','expired') DEFAULT 'available',
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `organ_id` (`organ_id`),
  KEY `donor_id` (`donor_id`),
  KEY `organ_type` (`organ_type`),
  KEY `blood_group` (`blood_group`),
  KEY `status` (`status`),
  KEY `harvest_date` (`harvest_date`),
  KEY `expiry_date` (`expiry_date`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `organ_inventory_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `organ_donors` (`id`),
  CONSTRAINT `organ_inventory_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `organ_requests`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_requests`;
CREATE TABLE `organ_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` varchar(20) NOT NULL UNIQUE,
  `patient_id` int(11) NOT NULL,
  `organ_type` varchar(50) NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `urgency_level` enum('normal','urgent','critical') DEFAULT 'normal',
  `required_date` date NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `hospital_unit` varchar(100) DEFAULT NULL,
  `medical_reason` text DEFAULT NULL,
  `status` enum('pending','approved','matched','transplanted','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_id` (`request_id`),
  KEY `patient_id` (`patient_id`),
  KEY `organ_type` (`organ_type`),
  KEY `blood_group` (`blood_group`),
  KEY `status` (`status`),
  KEY `urgency_level` (`urgency_level`),
  KEY `doctor_id` (`doctor_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `organ_requests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `organ_requests_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organ_requests_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `organ_requests_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `organ_transplants`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `organ_transplants`;
CREATE TABLE `organ_transplants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transplant_id` varchar(20) NOT NULL UNIQUE,
  `organ_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `surgeon_id` int(11) NOT NULL,
  `transplant_date` date NOT NULL,
  `hospital_location` varchar(100) NOT NULL,
  `surgery_duration` varchar(50) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','failed','cancelled') DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transplant_id` (`transplant_id`),
  KEY `organ_id` (`organ_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `donor_id` (`donor_id`),
  KEY `surgeon_id` (`surgeon_id`),
  KEY `transplant_date` (`transplant_date`),
  KEY `status` (`status`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `organ_transplants_ibfk_1` FOREIGN KEY (`organ_id`) REFERENCES `organ_inventory` (`id`),
  CONSTRAINT `organ_transplants_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `organ_transplants_ibfk_3` FOREIGN KEY (`donor_id`) REFERENCES `organ_donors` (`id`),
  CONSTRAINT `organ_transplants_ibfk_4` FOREIGN KEY (`surgeon_id`) REFERENCES `doctors` (`id`),
  CONSTRAINT `organ_transplants_ibfk_5` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE DATA FOR BLOOD BANK MANAGEMENT
-- ============================================================================

-- Sample data for blood_inventory
INSERT INTO `blood_inventory` (`blood_group`, `total_units`, `available_units`, `reserved_units`, `expired_units`) VALUES
('A+', 25, 20, 3, 2),
('A-', 15, 12, 2, 1),
('B+', 30, 25, 4, 1),
('B-', 10, 8, 1, 1),
('AB+', 8, 6, 1, 1),
('AB-', 5, 4, 1, 0),
('O+', 40, 35, 3, 2),
('O-', 12, 10, 1, 1);

-- Sample data for blood_donors
INSERT INTO `blood_donors` (`donor_id`, `name`, `email`, `phone`, `blood_group`, `date_of_birth`, `gender`, `address`, `emergency_contact`, `medical_history`, `status`, `registered_by`) VALUES
('BD000001', 'John Smith', 'john.smith@email.com', '+1234567890', 'O+', '1990-05-15', 'male', '123 Main St, City', '+1234567891', 'No known allergies', 'active', 1),
('BD000002', 'Sarah Johnson', 'sarah.j@email.com', '+1234567892', 'A+', '1985-08-22', 'female', '456 Oak Ave, City', '+1234567893', 'Hypertension controlled', 'active', 1),
('BD000003', 'Michael Brown', 'michael.b@email.com', '+1234567894', 'B-', '1992-12-10', 'male', '789 Pine St, City', '+1234567895', 'Diabetic', 'active', 1);

-- Sample data for blood_donations
INSERT INTO `blood_donations` (`donation_id`, `donor_id`, `blood_group`, `units_collected`, `donation_date`, `collection_center`, `staff_id`, `hemoglobin_level`, `medical_clearance`, `status`) VALUES
('DON000001', 1, 'O+', 1, '2024-01-15', 'Main Hospital Blood Bank', 1, 14.5, 'Cleared for donation', 'completed'),
('DON000002', 2, 'A+', 1, '2024-01-20', 'Main Hospital Blood Bank', 1, 13.2, 'Cleared for donation', 'completed'),
('DON000003', 3, 'B-', 1, '2024-01-25', 'Main Hospital Blood Bank', 1, 15.1, 'Cleared for donation', 'completed');

-- Sample data for blood_requests
INSERT INTO `blood_requests` (`request_id`, `patient_id`, `blood_group`, `units_required`, `urgency_level`, `required_date`, `doctor_id`, `hospital_unit`, `medical_reason`, `status`, `created_by`) VALUES
('REQ000001', 1, 'O+', 2, 'urgent', '2024-02-01', 1, 'Emergency', 'Trauma surgery blood loss', 'pending', 1),
('REQ000002', 2, 'A+', 1, 'normal', '2024-02-05', 2, 'Surgery', 'Elective surgery preparation', 'pending', 1);

-- ============================================================================
-- SAMPLE DATA FOR ORGAN DONATION MANAGEMENT
-- ============================================================================

-- Sample data for organ_donors
INSERT INTO `organ_donors` (`donor_id`, `name`, `email`, `phone`, `blood_group`, `date_of_birth`, `gender`, `address`, `emergency_contact`, `organs_to_donate`, `medical_history`, `consent_date`, `status`, `registered_by`) VALUES
('OD000001', 'Robert Wilson', 'robert.w@email.com', '+1234567896', 'O-', '1988-03-12', 'male', '321 Elm St, City', '+1234567897', 'Heart,Liver,Kidney', 'No significant medical history', '2024-01-10', 'active', 1),
('OD000002', 'Emily Davis', 'emily.d@email.com', '+1234567898', 'AB+', '1995-07-08', 'female', '654 Maple Ave, City', '+1234567899', 'Cornea,Kidney,Liver', 'Mild asthma', '2024-01-15', 'active', 1);

-- Sample data for organ_inventory
INSERT INTO `organ_inventory` (`organ_id`, `donor_id`, `organ_type`, `blood_group`, `harvest_date`, `expiry_date`, `hospital_location`, `medical_condition`, `status`, `recorded_by`) VALUES
('ORG000001', 1, 'Kidney', 'O-', '2024-01-20', '2024-01-22', 'Main Hospital OR-1', 'Excellent condition', 'available', 1),
('ORG000002', 2, 'Cornea', 'AB+', '2024-01-21', '2024-01-28', 'Main Hospital Eye Bank', 'Perfect condition', 'available', 1);

-- Sample data for organ_requests
INSERT INTO `organ_requests` (`request_id`, `patient_id`, `organ_type`, `blood_group`, `urgency_level`, `required_date`, `doctor_id`, `hospital_unit`, `medical_reason`, `status`, `created_by`) VALUES
('OREQ000001', 3, 'Kidney', 'O+', 'critical', '2024-02-01', 1, 'Nephrology', 'End-stage renal disease', 'pending', 1),
('OREQ000002', 4, 'Cornea', 'A+', 'normal', '2024-02-15', 2, 'Ophthalmology', 'Corneal blindness', 'pending', 1);

-- Sample data for organ_transplants
INSERT INTO `organ_transplants` (`transplant_id`, `organ_id`, `recipient_id`, `donor_id`, `surgeon_id`, `transplant_date`, `hospital_location`, `surgery_duration`, `status`, `notes`, `recorded_by`) VALUES
('TXP000001', 1, 3, 1, 1, '2024-01-22', 'Main Hospital OR-1', '4 hours 30 minutes', 'completed', 'Successful kidney transplant', 1);
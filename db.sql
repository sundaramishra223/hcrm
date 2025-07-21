-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 21, 2025 at 12:41 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hospital_crm`
--

-- --------------------------------------------------------

--
-- Table structure for table `ambulances`
--

CREATE TABLE `ambulances` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `vehicle_number` varchar(20) NOT NULL,
  `vehicle_type` enum('basic','advanced','icu') DEFAULT 'basic',
  `driver_name` varchar(100) DEFAULT NULL,
  `driver_phone` varchar(20) DEFAULT NULL,
  `status` enum('available','in_use','maintenance') DEFAULT 'available',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `capacity` int(11) DEFAULT 4,
  `equipment` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ambulance_bookings`
--

CREATE TABLE `ambulance_bookings` (
  `id` int(11) NOT NULL,
  `ambulance_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `pickup_address` text NOT NULL,
  `destination_address` text NOT NULL,
  `booking_date` datetime NOT NULL,
  `pickup_time` datetime DEFAULT NULL,
  `return_time` datetime DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `charges` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `appointment_type` enum('consultation','follow_up','emergency','routine') DEFAULT 'consultation',
  `status` enum('scheduled','confirmed','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `appointment_number` varchar(50) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `chief_complaint` text DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `type` enum('consultation','follow_up','emergency') DEFAULT 'consultation'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `hospital_id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `appointment_type`, `status`, `reason`, `notes`, `created_by`, `created_at`, `updated_at`, `appointment_number`, `duration_minutes`, `chief_complaint`, `consultation_fee`, `type`) VALUES
(1, 1, 2, 1, '2025-07-25', '14:00:00', 'consultation', 'scheduled', '', '', 1, '2025-07-20 02:49:45', '2025-07-20 02:49:45', NULL, 30, NULL, 0.00, 'consultation'),
(2, 1, 3, 1, '2025-07-20', '14:00:00', 'consultation', 'scheduled', '', '', 1, '2025-07-20 06:38:06', '2025-07-20 06:38:06', NULL, 30, NULL, 0.00, 'consultation');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `beds`
--

CREATE TABLE `beds` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `bed_number` varchar(20) NOT NULL,
  `bed_type` enum('general','icu','private','semi_private') DEFAULT 'general',
  `room_number` varchar(20) DEFAULT NULL,
  `floor_number` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `status` enum('available','occupied','maintenance','reserved') DEFAULT 'available',
  `current_patient_id` int(11) DEFAULT NULL,
  `daily_rate` decimal(10,2) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `beds`
--

INSERT INTO `beds` (`id`, `hospital_id`, `bed_number`, `bed_type`, `room_number`, `floor_number`, `department_id`, `status`, `current_patient_id`, `daily_rate`, `last_updated`, `created_at`) VALUES
(1, 1, 'BED001', 'general', '101', 1, 1, 'available', NULL, 2000.00, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(2, 1, 'BED002', 'general', '102', 1, 1, 'available', NULL, 2000.00, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(3, 1, 'BED003', 'icu', 'ICU01', 2, 5, 'available', NULL, 5000.00, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(4, 1, 'BED004', 'private', '201', 2, 1, 'available', NULL, 3000.00, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(5, 1, 'B001', 'general', 'R101', 1, 1, 'occupied', 2, 500.00, '2025-07-20 06:40:58', '2025-07-19 18:26:38'),
(6, 1, 'B002', 'general', 'R102', 1, 1, 'available', NULL, 500.00, '2025-07-19 18:26:38', '2025-07-19 18:26:38'),
(7, 1, 'B003', 'icu', 'ICU01', 2, 3, 'available', NULL, 2000.00, '2025-07-19 18:26:38', '2025-07-19 18:26:38'),
(8, 1, 'B004', 'icu', 'ICU02', 2, 3, 'available', NULL, 2000.00, '2025-07-19 18:26:38', '2025-07-19 18:26:38'),
(9, 1, 'B005', 'private', 'R201', 2, 1, 'available', NULL, 1500.00, '2025-07-19 18:26:38', '2025-07-19 18:26:38'),
(10, 1, 'B006', 'private', 'R202', 2, 1, 'available', NULL, 1500.00, '2025-07-19 18:26:38', '2025-07-19 18:26:38'),
(11, 1, 'B007', 'semi_private', 'R203', 2, 1, 'available', NULL, 1000.00, '2025-07-19 18:26:38', '2025-07-19 18:26:38'),
(12, 1, 'B008', 'general', 'R103', 1, 2, 'available', NULL, 500.00, '2025-07-19 18:26:38', '2025-07-19 18:26:38'),
(13, 1, 'B009', 'general', 'R104', 1, 4, 'available', NULL, 500.00, '2025-07-19 18:26:38', '2025-07-19 18:26:38'),
(14, 1, 'B010', 'icu', 'ICU03', 2, 3, 'available', NULL, 2000.00, '2025-07-19 18:26:38', '2025-07-19 18:26:38');

-- --------------------------------------------------------

--
-- Table structure for table `bed_assignments`
--

CREATE TABLE `bed_assignments` (
  `id` int(11) NOT NULL,
  `bed_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `assigned_date` datetime NOT NULL,
  `discharge_date` datetime DEFAULT NULL,
  `status` enum('active','discharged','transferred') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `assigned_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `priority` enum('low','medium','high') DEFAULT 'medium'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bed_assignments`
--

INSERT INTO `bed_assignments` (`id`, `bed_id`, `patient_id`, `assigned_date`, `discharge_date`, `status`, `notes`, `assigned_by`, `created_at`, `priority`) VALUES
(1, 5, 2, '2025-07-20 00:00:00', NULL, 'active', '', 1, '2025-07-20 06:40:58', 'medium');

-- --------------------------------------------------------

--
-- Table structure for table `billing_templates`
--

CREATE TABLE `billing_templates` (
  `id` int(11) NOT NULL,
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
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `visit_id` int(11) DEFAULT NULL,
  `bill_number` varchar(50) NOT NULL,
  `bill_date` date NOT NULL,
  `bill_type` enum('consultation','lab','pharmacy','equipment','bed','comprehensive') NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `balance_amount` decimal(12,2) NOT NULL,
  `payment_status` enum('pending','partial','paid','cancelled') DEFAULT 'pending',
  `payment_method` enum('cash','card','online','insurance') DEFAULT 'cash',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`id`, `hospital_id`, `patient_id`, `visit_id`, `bill_number`, `bill_date`, `bill_type`, `subtotal`, `discount_amount`, `tax_amount`, `total_amount`, `paid_amount`, `balance_amount`, `payment_status`, `payment_method`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, 'BILL202507195107', '2025-07-19', 'consultation', 500.00, 0.00, 90.00, 590.00, 590.00, 0.00, 'paid', 'cash', '', 1, '2025-07-19 12:34:53', '2025-07-20 02:50:46');

-- --------------------------------------------------------

--
-- Table structure for table `bill_items`
--

CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `item_type` enum('consultation','medicine','lab_test','equipment','bed','other') NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `final_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_items`
--

INSERT INTO `bill_items` (`id`, `bill_id`, `item_type`, `item_name`, `item_code`, `quantity`, `unit_price`, `total_price`, `discount_amount`, `final_price`, `created_at`) VALUES
(1, 1, 'consultation', 'consultancy', '', 1, 500.00, 500.00, 0.00, 500.00, '2025-07-19 12:34:53');

-- --------------------------------------------------------

--
-- Table structure for table `bill_payments`
--

CREATE TABLE `bill_payments` (
  `id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `payment_amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','card','online','cheque','insurance') NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `payment_date` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_payments`
--

INSERT INTO `bill_payments` (`id`, `bill_id`, `payment_amount`, `payment_method`, `payment_reference`, `payment_date`, `notes`, `recorded_by`, `created_at`) VALUES
(1, 1, 590.00, 'cash', '', '2025-07-19 22:50:46', '', 1, '2025-07-20 02:50:46');

-- --------------------------------------------------------

--
-- Table structure for table `blood_activity_audit`
--

CREATE TABLE `blood_activity_audit` (
  `id` int(11) NOT NULL,
  `activity_type` enum('donation','usage','inventory_update','testing','disposal') NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('insert','update','delete') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `performed_by` int(11) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blood_donation_sessions`
--

CREATE TABLE `blood_donation_sessions` (
  `id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `collected_by` int(11) NOT NULL,
  `collection_date` datetime NOT NULL,
  `pre_donation_checkup` enum('passed','failed','conditional') NOT NULL,
  `hemoglobin_level` decimal(4,2) NOT NULL,
  `blood_pressure` varchar(20) NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `volume_collected` int(11) NOT NULL DEFAULT 450,
  `donation_type` enum('whole_blood','platelets','plasma','double_red_cells') NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('completed','incomplete','cancelled') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blood_donors`
--

CREATE TABLE `blood_donors` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `donor_id` varchar(20) NOT NULL,
  `registration_date` date NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `last_donation_date` date DEFAULT NULL,
  `total_donations` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `medical_clearance` enum('cleared','pending','rejected') DEFAULT 'pending',
  `clearance_date` date DEFAULT NULL,
  `clearance_notes` text DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `blood_donors`
--
DELIMITER $$
CREATE TRIGGER `auto_donor_id_enhanced` BEFORE INSERT ON `blood_donors` FOR EACH ROW BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM blood_donors;
  SET NEW.donor_id = CONCAT('DON', YEAR(NOW()), LPAD(count + 1, 4, '0'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `blood_inventory`
--

CREATE TABLE `blood_inventory` (
  `id` int(11) NOT NULL,
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
  `test_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`test_results`)),
  `issued_to_patient_id` int(11) DEFAULT NULL,
  `issued_date` datetime DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `blood_inventory_dashboard`
-- (See below for the actual view)
--
CREATE TABLE `blood_inventory_dashboard` (
`blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-')
,`component_type` enum('whole_blood','red_blood_cells','platelets','plasma','cryoprecipitate','fresh_frozen_plasma')
,`total_units` bigint(21)
,`available_units` decimal(22,0)
,`used_units` decimal(22,0)
,`expired_units` decimal(22,0)
,`quarantine_units` decimal(22,0)
,`avg_days_to_expiry` decimal(10,4)
);

-- --------------------------------------------------------

--
-- Table structure for table `blood_requests`
--

CREATE TABLE `blood_requests` (
  `id` int(11) NOT NULL,
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
  `approval_notes` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `blood_bags_assigned` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of assigned blood bag IDs' CHECK (json_valid(`blood_bags_assigned`)),
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `fulfilled_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `blood_requests`
--
DELIMITER $$
CREATE TRIGGER `auto_blood_request_number` BEFORE INSERT ON `blood_requests` FOR EACH ROW BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM blood_requests WHERE DATE(created_at) = CURDATE();
  SET NEW.request_number = CONCAT('BREQ', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `blood_usage_records`
--

CREATE TABLE `blood_usage_records` (
  `id` int(11) NOT NULL,
  `blood_bag_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `used_by` int(11) NOT NULL,
  `usage_date` datetime NOT NULL,
  `usage_type` enum('transfusion','surgery','emergency','research','testing') NOT NULL,
  `volume_used` int(11) NOT NULL,
  `patient_condition` text NOT NULL,
  `cross_match_result` enum('compatible','incompatible','pending','not_required') NOT NULL,
  `adverse_reactions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `claim_timeline`
--

CREATE TABLE `claim_timeline` (
  `id` int(11) NOT NULL,
  `claim_id` int(11) NOT NULL,
  `status` enum('submitted','under_review','approved','rejected','partially_approved','pending_documents','settled','query_raised','additional_documents_submitted') NOT NULL,
  `status_date` datetime NOT NULL,
  `updated_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `head_doctor_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `hospital_id`, `name`, `code`, `description`, `head_doctor_id`, `is_active`, `created_at`) VALUES
(1, 1, 'Cardiology', 'CARD', NULL, 1, 1, '2025-07-19 08:17:08'),
(2, 1, 'Neurology', 'NEURO', NULL, 2, 1, '2025-07-19 08:17:08'),
(3, 1, 'Orthopedics', 'ORTHO', NULL, NULL, 1, '2025-07-19 08:17:08'),
(4, 1, 'Pediatrics', 'PED', NULL, NULL, 1, '2025-07-19 08:17:08'),
(5, 1, 'Emergency Medicine', 'EMERG', NULL, NULL, 1, '2025-07-19 08:17:08'),
(6, 1, 'General Medicine', 'GEN', NULL, NULL, 1, '2025-07-19 08:17:08'),
(7, 1, 'Surgery', 'SURG', NULL, NULL, 1, '2025-07-19 08:17:08'),
(8, 1, 'Laboratory', 'LAB', NULL, NULL, 1, '2025-07-19 08:17:08'),
(9, 1, 'Pharmacy', 'PHARM', NULL, NULL, 1, '2025-07-19 08:17:08'),
(10, 1, 'Radiology', 'RAD', NULL, NULL, 1, '2025-07-19 08:17:08'),
(11, 1, 'Blood Bank', 'BLOOD', 'Blood collection, storage and transfusion services', NULL, 1, '2025-07-21 10:18:48'),
(12, 1, 'Insurance Department', 'INS', 'Insurance claims and billing services', NULL, 1, '2025-07-21 10:18:48'),
(13, 1, 'Blood Bank', 'BLOOD', 'Blood bank services', NULL, 1, '2025-07-21 10:35:44'),
(14, 1, 'Insurance', 'INS', 'Insurance services', NULL, 1, '2025-07-21 10:35:44');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `hospital_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `employee_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `qualification` text DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT NULL,
  `joined_date` date DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `is_intern` tinyint(1) DEFAULT 0,
  `senior_doctor_id` int(11) DEFAULT NULL,
  `certificates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`certificates`)),
  `awards` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`awards`)),
  `vitals` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vitals`)),
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `user_id`, `hospital_id`, `department_id`, `employee_id`, `first_name`, `middle_name`, `last_name`, `specialization`, `qualification`, `experience_years`, `registration_number`, `phone`, `emergency_contact`, `address`, `date_of_birth`, `gender`, `blood_group`, `consultation_fee`, `joined_date`, `is_available`, `is_intern`, `senior_doctor_id`, `certificates`, `awards`, `vitals`, `image_url`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 1, 'DOC001', 'Dr. Rajesh', NULL, 'Sharma', 'Cardiologist', 'MBBS, MD (Cardiology)', 15, 'CARD001', '+919876543210', '+919876543211', '123 Doctor Lane, City', '1980-05-15', 'male', 'B+', 500.00, '2020-01-15', 1, 0, NULL, NULL, NULL, NULL, NULL, '2025-07-19 08:17:08', '2025-07-20 05:44:32'),
(2, 8, 1, 2, 'DOC002', 'Dr. Priya', NULL, 'Intern', 'Neurologist', 'MBBS, MD (Neurology)', 2, 'NEURO001', '+919876543212', '+919876543213', '456 Intern Street, City', '1995-08-20', 'female', 'O+', 800.00, '2023-06-01', 1, 0, 1, NULL, NULL, NULL, NULL, '2025-07-19 08:17:08', '2025-07-19 08:17:08');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `hospital_id`, `template_name`, `subject`, `body`, `variables`, `is_active`, `created_at`) VALUES
(1, 1, 'appointment_reminder', 'Appointment Reminder', 'Dear {patient_name},\n\nThis is a reminder for your appointment on {appointment_date} at {appointment_time} with Dr. {doctor_name}.\n\nPlease arrive 15 minutes before your scheduled time.\n\nBest regards,\nHospital Team', '[\"patient_name\", \"appointment_date\", \"appointment_time\", \"doctor_name\"]', 1, '2025-07-19 08:17:08'),
(2, 1, 'bill_notification', 'Bill Notification', 'Dear {patient_name},\n\nYour bill for {bill_amount} has been generated. Please visit the hospital to make the payment.\n\nBill Number: {bill_number}\nDue Date: {due_date}\n\nBest regards,\nHospital Team', '[\"patient_name\", \"bill_amount\", \"bill_number\", \"due_date\"]', 1, '2025-07-19 08:17:08'),
(3, 1, 'emergency_notification', 'Emergency Notification', 'Dear {recipient_name},\n\nThis is an emergency notification regarding {patient_name}.\n\nPlease contact the hospital immediately.\n\nBest regards,\nHospital Team', '[\"recipient_name\", \"patient_name\"]', 1, '2025-07-19 08:17:08');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `cost` decimal(12,2) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `status` enum('operational','maintenance','out_of_service','retired') DEFAULT 'operational',
  `maintenance_schedule` text DEFAULT NULL,
  `specifications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specifications`)),
  `notes` text DEFAULT NULL,
  `last_maintenance` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `hospital_id`, `name`, `category`, `model`, `serial_number`, `manufacturer`, `purchase_date`, `warranty_expiry`, `cost`, `location`, `status`, `maintenance_schedule`, `specifications`, `notes`, `last_maintenance`, `created_at`, `updated_at`) VALUES
(1, 1, 'MRI Machine', 'Imaging', 'Siemens MAGNETOM', 'MRI001', 'Siemens', '2023-01-15', NULL, 2500000.00, 'Radiology Department', 'operational', NULL, NULL, NULL, NULL, '2025-07-19 18:31:53', '2025-07-19 18:31:53');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_maintenance`
--

CREATE TABLE `equipment_maintenance` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `maintenance_type` enum('routine','repair','calibration','inspection') NOT NULL,
  `maintenance_date` date NOT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `feedback_type` enum('patient','staff','general') NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `title` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('pending','reviewed','resolved') DEFAULT 'pending',
  `response` text DEFAULT NULL,
  `responded_by` int(11) DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hospitals`
--

CREATE TABLE `hospitals` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `favicon_url` varchar(255) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT '#004685',
  `secondary_color` varchar(7) DEFAULT '#0066cc',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `site_title` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hospitals`
--

INSERT INTO `hospitals` (`id`, `name`, `code`, `address`, `phone`, `email`, `website`, `logo_url`, `favicon_url`, `primary_color`, `secondary_color`, `is_active`, `created_at`, `site_title`) VALUES
(1, 'General Hospital', 'GH001', '123 Main Street, City, State', '+1234567890', 'info@generalhospital.com', 'www.generalhospital.com', 'assets/images/logo.svg', 'data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"%232563eb\"><path d=\"M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM12 17h-2v-2h2v2zm0-4h-2V7h2v6z\"/></svg>', '#2563eb', '#10b981', 1, '2025-07-19 08:17:08', 'MediCare Hospital - Advanced Healthcare Management');

-- --------------------------------------------------------

--
-- Table structure for table `insurance_claims`
--

CREATE TABLE `insurance_claims` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `insurance_provider` varchar(100) NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `claim_amount` decimal(12,2) NOT NULL,
  `approved_amount` decimal(12,2) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `insurance_claims_enhanced`
--

CREATE TABLE `insurance_claims_enhanced` (
  `id` int(11) NOT NULL,
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
  `diagnosis_description` text DEFAULT NULL,
  `treatment_details` text NOT NULL,
  `submitted_documents` text DEFAULT NULL,
  `claim_status` enum('submitted','under_review','approved','rejected','partially_approved','pending_documents','settled') DEFAULT 'submitted',
  `rejection_reason` text DEFAULT NULL,
  `settlement_reference` varchar(100) DEFAULT NULL,
  `pre_auth_number` varchar(100) DEFAULT NULL,
  `cashless_approval` tinyint(1) DEFAULT 0,
  `submitted_by` int(11) NOT NULL,
  `submitted_date` datetime NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_date` datetime DEFAULT NULL,
  `settled_date` datetime DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT 0,
  `follow_up_notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `insurance_claims_enhanced`
--
DELIMITER $$
CREATE TRIGGER `auto_enhanced_claim_number` BEFORE INSERT ON `insurance_claims_enhanced` FOR EACH ROW BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM insurance_claims_enhanced WHERE DATE(created_at) = CURDATE();
  SET NEW.claim_number = CONCAT('ECLM', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `insurance_claims_new`
--

CREATE TABLE `insurance_claims_new` (
  `id` int(11) NOT NULL,
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
  `diagnosis_description` text DEFAULT NULL,
  `treatment_details` text NOT NULL,
  `submitted_documents` text DEFAULT NULL,
  `claim_status` enum('submitted','under_review','approved','rejected','partially_approved','pending_documents','settled') DEFAULT 'submitted',
  `rejection_reason` text DEFAULT NULL,
  `settlement_reference` varchar(100) DEFAULT NULL,
  `pre_auth_number` varchar(100) DEFAULT NULL,
  `cashless_approval` tinyint(1) DEFAULT 0,
  `submitted_by` int(11) NOT NULL,
  `submitted_date` datetime NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_date` datetime DEFAULT NULL,
  `settled_date` datetime DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT 0,
  `follow_up_notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `insurance_companies`
--

CREATE TABLE `insurance_companies` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_code` varchar(50) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `tpa_name` varchar(255) DEFAULT NULL COMMENT 'Third Party Administrator',
  `network_type` enum('cashless','reimbursement','both') DEFAULT 'both',
  `settlement_period` int(11) DEFAULT 30 COMMENT 'Days for claim settlement',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insurance_companies`
--

INSERT INTO `insurance_companies` (`id`, `company_name`, `company_code`, `contact_person`, `contact_number`, `email`, `address`, `website`, `license_number`, `tpa_name`, `network_type`, `settlement_period`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Star Health Insurance', 'STAR001', 'Rajesh Kumar', '9876543210', 'claims@starhealth.in', NULL, NULL, NULL, 'Medi Assist', 'both', 30, 1, '2025-07-21 10:18:49', '2025-07-21 10:18:49'),
(2, 'HDFC ERGO Health Insurance', 'HDFC001', 'Priya Sharma', '9876543211', 'claims@hdfcergo.com', NULL, NULL, NULL, 'HDFC ERGO TPA', 'cashless', 30, 1, '2025-07-21 10:18:49', '2025-07-21 10:18:49'),
(3, 'ICICI Lombard Health Insurance', 'ICICI001', 'Amit Singh', '9876543212', 'health@icicilombard.com', NULL, NULL, NULL, 'ICICI Lombard TPA', 'both', 30, 1, '2025-07-21 10:18:49', '2025-07-21 10:18:49'),
(4, 'SBI General Insurance', 'SBI001', 'Sunita Patel', '9876543213', 'claims@sbigeneral.in', NULL, NULL, NULL, 'SBI General TPA', 'both', 30, 1, '2025-07-21 10:18:49', '2025-07-21 10:18:49'),
(5, 'New India Assurance', 'NIA001', 'Ravi Gupta', '9876543214', 'claims@newindia.co.in', NULL, NULL, NULL, 'New India TPA', 'reimbursement', 30, 1, '2025-07-21 10:18:49', '2025-07-21 10:18:49');

-- --------------------------------------------------------

--
-- Table structure for table `insurance_coverage_rules`
--

CREATE TABLE `insurance_coverage_rules` (
  `id` int(11) NOT NULL,
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
  `exclusions` text DEFAULT NULL,
  `special_conditions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `effective_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `insurance_verifications`
--

CREATE TABLE `insurance_verifications` (
  `id` int(11) NOT NULL,
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
  `verification_notes` text DEFAULT NULL,
  `verified_by` int(11) NOT NULL,
  `insurance_response` text DEFAULT NULL,
  `response_time` int(11) DEFAULT NULL COMMENT 'Response time in seconds',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interns`
--

CREATE TABLE `interns` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `intern_rotations`
--

CREATE TABLE `intern_rotations` (
  `id` int(11) NOT NULL,
  `intern_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `learning_objectives` text DEFAULT NULL,
  `evaluation_score` decimal(4,2) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `status` enum('scheduled','active','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_orders`
--

CREATE TABLE `lab_orders` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `order_date` date NOT NULL,
  `expected_date` date DEFAULT NULL,
  `priority` enum('routine','urgent','emergency') DEFAULT 'routine',
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `total_cost` decimal(10,2) DEFAULT NULL,
  `clinical_notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_order_tests`
--

CREATE TABLE `lab_order_tests` (
  `id` int(11) NOT NULL,
  `lab_order_id` int(11) NOT NULL,
  `lab_test_id` int(11) NOT NULL,
  `test_name` varchar(200) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `result_value` varchar(200) DEFAULT NULL,
  `result_unit` varchar(50) DEFAULT NULL,
  `normal_range` varchar(100) DEFAULT NULL,
  `result_status` enum('normal','abnormal','critical') DEFAULT 'normal',
  `is_abnormal` tinyint(1) DEFAULT 0,
  `technician_notes` text DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_tests`
--

CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `test_name` varchar(200) DEFAULT NULL,
  `test_code` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `preparation_instructions` text DEFAULT NULL,
  `normal_range` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_tests`
--

INSERT INTO `lab_tests` (`id`, `name`, `test_name`, `test_code`, `category`, `cost`, `description`, `preparation_instructions`, `normal_range`, `unit`, `is_active`, `created_at`) VALUES
(1, 'Complete Blood Count', 'Complete Blood Count', 'CBC001', 'Hematology', 500.00, 'Complete blood count test', 'Fasting required for 8 hours', '4.5-11.0', 'cells/μL', 1, '2025-07-19 08:17:08'),
(2, 'Blood Glucose', 'Blood Glucose', 'GLU001', 'Biochemistry', 300.00, 'Blood glucose level test', 'Fasting required for 12 hours', '70-100', 'mg/dL', 1, '2025-07-19 08:17:08'),
(3, 'Lipid Profile', 'Lipid Profile', 'LIP001', 'Biochemistry', 800.00, 'Complete lipid profile test', 'Fasting required for 12 hours', 'Total: <200', 'mg/dL', 1, '2025-07-19 08:17:08'),
(4, 'Complete Blood Count', 'Complete Blood Count', NULL, 'Hematology', 300.00, NULL, 'No special preparation required', '4.5-11.0 x10^9/L', 'cells/L', 1, '2025-07-19 18:26:38'),
(5, 'Blood Sugar (Fasting)', 'Blood Sugar (Fasting)', NULL, 'Biochemistry', 150.00, NULL, 'Fasting for 8-12 hours required', '70-100 mg/dL', 'mg/dL', 1, '2025-07-19 18:26:38'),
(6, 'Lipid Profile', 'Lipid Profile', NULL, 'Biochemistry', 500.00, NULL, 'Fasting for 12 hours required', 'Total: <200 mg/dL', 'mg/dL', 1, '2025-07-19 18:26:38'),
(7, 'Kidney Function', 'Kidney Function', NULL, 'Biochemistry', 400.00, NULL, 'No special preparation', 'Creatinine: 0.6-1.2 mg/dL', 'mg/dL', 1, '2025-07-19 18:26:38'),
(8, 'Liver Function', 'Liver Function', NULL, 'Biochemistry', 450.00, NULL, 'No special preparation', 'ALT: 7-56 U/L', 'U/L', 1, '2025-07-19 18:26:38'),
(9, 'Thyroid Profile', 'Thyroid Profile', NULL, 'Endocrinology', 600.00, NULL, 'No special preparation', 'TSH: 0.4-4.0 mIU/L', 'mIU/L', 1, '2025-07-19 18:26:38'),
(10, 'Urine Analysis', 'Urine Analysis', NULL, 'Clinical Pathology', 200.00, NULL, 'Clean catch sample', 'Normal', 'Various', 1, '2025-07-19 18:26:38'),
(11, 'X-Ray Chest', 'X-Ray Chest', NULL, 'Radiology', 800.00, NULL, 'Remove metal objects', 'Normal', 'Image', 1, '2025-07-19 18:26:38'),
(12, 'Complete Blood Count', 'Complete Blood Count', NULL, 'Hematology', 300.00, NULL, 'No special preparation required', '4.5-11.0 x10^9/L', 'cells/L', 1, '2025-07-19 18:27:38'),
(13, 'Blood Sugar (Fasting)', 'Blood Sugar (Fasting)', NULL, 'Biochemistry', 150.00, NULL, 'Fasting for 8-12 hours required', '70-100 mg/dL', 'mg/dL', 1, '2025-07-19 18:27:38'),
(14, 'Lipid Profile', 'Lipid Profile', NULL, 'Biochemistry', 500.00, NULL, 'Fasting for 12 hours required', 'Total: <200 mg/dL', 'mg/dL', 1, '2025-07-19 18:27:38'),
(15, 'Kidney Function', 'Kidney Function', NULL, 'Biochemistry', 400.00, NULL, 'No special preparation', 'Creatinine: 0.6-1.2 mg/dL', 'mg/dL', 1, '2025-07-19 18:27:38'),
(16, 'Liver Function', 'Liver Function', NULL, 'Biochemistry', 450.00, NULL, 'No special preparation', 'ALT: 7-56 U/L', 'U/L', 1, '2025-07-19 18:27:38'),
(17, 'Thyroid Profile', 'Thyroid Profile', NULL, 'Endocrinology', 600.00, NULL, 'No special preparation', 'TSH: 0.4-4.0 mIU/L', 'mIU/L', 1, '2025-07-19 18:27:38'),
(18, 'Urine Analysis', 'Urine Analysis', NULL, 'Clinical Pathology', 200.00, NULL, 'Clean catch sample', 'Normal', 'Various', 1, '2025-07-19 18:27:38'),
(19, 'X-Ray Chest', 'X-Ray Chest', NULL, 'Radiology', 800.00, NULL, 'Remove metal objects', 'Normal', 'Image', 1, '2025-07-19 18:27:38'),
(20, 'Complete Blood Count', 'Complete Blood Count', NULL, 'Hematology', 300.00, NULL, 'No special preparation required', '4.5-11.0 x10^9/L', 'cells/L', 1, '2025-07-19 18:31:53'),
(21, 'Blood Sugar (Fasting)', 'Blood Sugar (Fasting)', NULL, 'Biochemistry', 150.00, NULL, 'Fasting for 8-12 hours required', '70-100 mg/dL', 'mg/dL', 1, '2025-07-19 18:31:53'),
(22, 'Lipid Profile', 'Lipid Profile', NULL, 'Biochemistry', 500.00, NULL, 'Fasting for 12 hours required', 'Total: <200 mg/dL', 'mg/dL', 1, '2025-07-19 18:31:53'),
(23, 'Kidney Function', 'Kidney Function', NULL, 'Biochemistry', 400.00, NULL, 'No special preparation', 'Creatinine: 0.6-1.2 mg/dL', 'mg/dL', 1, '2025-07-19 18:31:53'),
(24, 'Liver Function', 'Liver Function', NULL, 'Biochemistry', 450.00, NULL, 'No special preparation', 'ALT: 7-56 U/L', 'U/L', 1, '2025-07-19 18:31:53'),
(25, 'Complete Blood Count', NULL, NULL, 'Hematology', 300.00, NULL, 'No special preparation required', '4.5-11.0 x10^9/L', 'cells/L', 1, '2025-07-19 19:38:31'),
(26, 'Blood Sugar (Fasting)', NULL, NULL, 'Biochemistry', 150.00, NULL, 'Fasting for 8-12 hours required', '70-100 mg/dL', 'mg/dL', 1, '2025-07-19 19:38:31'),
(27, 'Lipid Profile', NULL, NULL, 'Biochemistry', 500.00, NULL, 'Fasting for 12 hours required', 'Total: <200 mg/dL', 'mg/dL', 1, '2025-07-19 19:38:31'),
(28, 'Kidney Function', NULL, NULL, 'Biochemistry', 400.00, NULL, 'No special preparation', 'Creatinine: 0.6-1.2 mg/dL', 'mg/dL', 1, '2025-07-19 19:38:31'),
(29, 'Liver Function', NULL, NULL, 'Biochemistry', 450.00, NULL, 'No special preparation', 'ALT: 7-56 U/L', 'U/L', 1, '2025-07-19 19:38:31');

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `generic_name` varchar(200) DEFAULT NULL,
  `manufacturer` varchar(200) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `dosage_form` varchar(100) DEFAULT NULL,
  `strength` varchar(100) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `pack_size` varchar(50) DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 10,
  `prescription_required` tinyint(1) DEFAULT 0,
  `side_effects` text DEFAULT NULL,
  `contraindications` text DEFAULT NULL,
  `storage_conditions` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `hospital_id`, `name`, `generic_name`, `manufacturer`, `category`, `dosage_form`, `strength`, `unit_price`, `pack_size`, `batch_number`, `expiry_date`, `stock_quantity`, `min_stock_level`, `prescription_required`, `side_effects`, `contraindications`, `storage_conditions`, `notes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Paracetamol', 'Acetaminophen', 'ABC Pharma', 'Analgesic', 'Tablet', '500mg', 5.00, '10 tablets', 'BATCH001', '2025-12-31', 100, 20, 0, 'Nausea, stomach upset', 'Liver disease', 'Store in cool, dry place', NULL, 1, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(2, 1, 'Amoxicillin', 'Amoxicillin', 'XYZ Pharma', 'Antibiotic', 'Capsule', '250mg', 15.00, '10 capsules', 'BATCH002', '2024-12-31', 50, 10, 1, 'Diarrhea, rash', 'Penicillin allergy', 'Store in refrigerator', NULL, 1, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(3, 1, 'Omeprazole', 'Omeprazole', 'DEF Pharma', 'Antacid', 'Capsule', '20mg', 25.00, '10 capsules', 'BATCH003', '2025-06-30', 75, 15, 1, 'Headache, nausea', 'Pregnancy', 'Store at room temperature', NULL, 1, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(4, 1, 'IPSIRIN', '', 'SUNDARAM MISHRA', 'Others', 'Tablet', '5', 9.00, '10', '', '2026-08-21', 129, 10, 0, '', '', '', '', 1, '2025-07-20 02:53:56', '2025-07-20 02:53:56'),
(5, 1, 'Paracetamol', 'Acetaminophen', 'PharmaCorp', 'Pain Relief', 'Tablet', '500mg', 2.50, '10 tablets', 'PAR001', '2027-07-20', 1000, 50, 0, 'Nausea, rash', 'Liver disease', 'Store in cool dry place', 'Common pain reliever', 1, '2025-07-20 06:15:57', '2025-07-20 06:15:57'),
(6, 1, 'Amoxicillin', 'Amoxicillin', 'AntiBio Ltd', 'Antibiotics', 'Capsule', '250mg', 15.00, '21 capsules', 'AMO001', '2027-01-20', 500, 30, 1, 'Diarrhea, nausea', 'Penicillin allergy', 'Refrigerate', 'Broad spectrum antibiotic', 1, '2025-07-20 06:15:57', '2025-07-20 06:15:57'),
(7, 1, 'Vitamin C', 'Ascorbic Acid', 'VitaLife', 'Vitamins', 'Tablet', '1000mg', 8.00, '30 tablets', 'VIT001', '2028-07-20', 800, 40, 0, 'Stomach upset', 'Kidney stones', 'Store in dry place', 'Immune system support', 1, '2025-07-20 06:15:57', '2025-07-20 06:15:57'),
(8, 1, 'Metformin', 'Metformin HCl', 'DiabCare', 'Diabetes', 'Tablet', '500mg', 12.00, '30 tablets', 'MET001', '2027-07-20', 300, 25, 1, 'Nausea, diarrhea', 'Kidney disease', 'Store below 25°C', 'Diabetes management', 1, '2025-07-20 06:15:57', '2025-07-20 06:15:57'),
(9, 1, 'Atenolol', 'Atenolol', 'CardioMed', 'Blood Pressure', 'Tablet', '50mg', 18.00, '28 tablets', 'ATE001', '2027-07-20', 200, 20, 1, 'Dizziness, fatigue', 'Asthma, heart block', 'Store in cool place', 'Blood pressure control', 1, '2025-07-20 06:15:57', '2025-07-20 06:15:57');

-- --------------------------------------------------------

--
-- Table structure for table `medicine_categories`
--

CREATE TABLE `medicine_categories` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_categories`
--

INSERT INTO `medicine_categories` (`id`, `hospital_id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Antibiotics', 'Medications that fight bacterial infections', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(2, 1, 'Pain Relief', 'Analgesics and pain management medications', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(3, 1, 'Vitamins', 'Vitamin supplements and nutritional aids', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(4, 1, 'Heart Medications', 'Cardiovascular and cardiac medications', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(5, 1, 'Diabetes', 'Medications for diabetes management', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(6, 1, 'Blood Pressure', 'Hypertension and blood pressure medications', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(7, 1, 'Respiratory', 'Medications for breathing and lung conditions', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(8, 1, 'Digestive', 'Medications for stomach and digestive issues', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(9, 1, 'Skin Care', 'Topical medications and skin treatments', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(10, 1, 'Mental Health', 'Psychiatric and mental health medications', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(11, 1, 'Emergency', 'Emergency and critical care medications', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(12, 1, 'Pediatric', 'Medications specifically for children', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(13, 1, 'Surgical', 'Pre and post-operative medications', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56'),
(14, 1, 'Injectable', 'Injectable medications and vaccines', 1, '2025-07-20 06:15:56', '2025-07-20 06:15:56');

-- --------------------------------------------------------

--
-- Table structure for table `medicine_stock_movements`
--

CREATE TABLE `medicine_stock_movements` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `movement_type` enum('add','subtract','adjust','expired','damaged') NOT NULL,
  `quantity` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organ_activity_audit`
--

CREATE TABLE `organ_activity_audit` (
  `id` int(11) NOT NULL,
  `activity_type` enum('consent','donation','recipient','transplant','legal_action') NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('insert','update','delete') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `legal_significance` enum('high','medium','low') DEFAULT 'high',
  `performed_by` int(11) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `witness_signature` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organ_donations`
--

CREATE TABLE `organ_donations` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organ_donor_consent`
--

CREATE TABLE `organ_donor_consent` (
  `id` int(11) NOT NULL,
  `donor_id` int(11) NOT NULL,
  `consent_type` enum('living_donor','deceased_donor','brain_dead','family_consent') NOT NULL,
  `consent_date` date NOT NULL,
  `witness_1` varchar(255) NOT NULL,
  `witness_2` varchar(255) NOT NULL,
  `legal_guardian` varchar(255) DEFAULT NULL,
  `consent_document_path` varchar(500) DEFAULT NULL,
  `notarized` enum('yes','no','pending') DEFAULT 'pending',
  `consent_withdrawal_date` date DEFAULT NULL,
  `withdrawal_reason` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organ_legal_rejections`
--

CREATE TABLE `organ_legal_rejections` (
  `id` int(11) NOT NULL,
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
  `legal_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organ_recipients`
--

CREATE TABLE `organ_recipients` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `organ_needed` enum('kidney','liver','heart','lung','pancreas','cornea','skin','bone','tissue') NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  `urgency_level` enum('elective','urgent','emergency') NOT NULL,
  `medical_condition` text NOT NULL,
  `waiting_since` date NOT NULL,
  `estimated_survival_benefit` int(11) DEFAULT NULL COMMENT 'Expected years of life saved',
  `hla_typing` text DEFAULT NULL,
  `cross_match_requirements` text DEFAULT NULL,
  `contraindications` text DEFAULT NULL,
  `legal_consent` enum('signed','pending','refused') DEFAULT 'pending',
  `ethics_approval` enum('approved','pending','rejected') DEFAULT 'pending',
  `financial_clearance` enum('approved','pending','rejected') DEFAULT 'pending',
  `insurance_pre_auth` varchar(255) DEFAULT NULL,
  `priority_score` int(11) DEFAULT 0,
  `status` enum('waiting','matched','transplanted','removed','deceased') DEFAULT 'waiting',
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `organ_transplants`
--

CREATE TABLE `organ_transplants` (
  `id` int(11) NOT NULL,
  `donation_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `transplant_date` datetime NOT NULL,
  `surgical_team_lead` varchar(255) NOT NULL,
  `anesthesiologist` varchar(255) NOT NULL,
  `surgery_duration` int(11) NOT NULL COMMENT 'Minutes',
  `surgical_notes` text NOT NULL,
  `complications` text DEFAULT NULL,
  `blood_loss_ml` int(11) DEFAULT NULL,
  `transfusion_required` tinyint(1) DEFAULT 0,
  `legal_documentation_complete` enum('yes','no') NOT NULL,
  `informed_consent_signed` enum('yes','no') NOT NULL,
  `ethics_clearance` enum('approved','pending','rejected') NOT NULL,
  `post_op_monitoring_plan` text NOT NULL,
  `immunosuppression_protocol` text DEFAULT NULL,
  `follow_up_schedule` text DEFAULT NULL,
  `outcome` enum('successful','complications','failed','pending') DEFAULT 'pending',
  `performed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `outstanding_bills_enhanced`
-- (See below for the actual view)
--
CREATE TABLE `outstanding_bills_enhanced` (
`bill_id` int(11)
,`bill_number` varchar(50)
,`patient_name` varchar(152)
,`patient_number` varchar(20)
,`phone` varchar(20)
,`service_type` enum('consultation','diagnostic','surgery','emergency','inpatient','outpatient','blood_transfusion','organ_transplant','blood_request','pharmacy')
,`service_date` date
,`total_amount` decimal(12,2)
,`insurance_coverage` decimal(12,2)
,`paid_amount` decimal(12,2)
,`outstanding_amount` decimal(12,2)
,`payment_status` enum('pending','partial','paid','insurance_pending','overdue')
,`payment_due_date` date
,`days_overdue` int(7)
,`payment_urgency` varchar(8)
);

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `hospital_id` int(11) NOT NULL,
  `patient_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `assigned_doctor_id` int(11) DEFAULT NULL,
  `patient_type` enum('outpatient','inpatient') DEFAULT 'outpatient',
  `insurance_provider` varchar(100) DEFAULT NULL,
  `insurance_number` varchar(50) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_relation` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `hospital_id`, `patient_id`, `first_name`, `middle_name`, `last_name`, `phone`, `emergency_contact`, `email`, `address`, `date_of_birth`, `gender`, `blood_group`, `marital_status`, `occupation`, `medical_history`, `allergies`, `assigned_doctor_id`, `patient_type`, `insurance_provider`, `insurance_number`, `emergency_contact_name`, `emergency_contact_relation`, `password`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 'PAT001', 'John', NULL, 'Doe', '9876543210', '+919876543229', 'john.doe@email.com', '741 Patient Street, City', '1985-06-18', 'male', 'O+', 'married', 'Software Engineer', 'Hypertension', 'Penicillin', 1, 'outpatient', NULL, NULL, NULL, NULL, NULL, '2025-07-19 08:17:08', '2025-07-20 05:44:32'),
(2, NULL, 1, 'P20250002', 'sundaram', '', 'mishra', '+91 93703 80798', '', 'misun220320@gmail.com', 'b8 203 chandresh hills md nagar achole road nsp', '2001-07-23', 'male', 'B+', 'single', 'student', 'none', 'none', NULL, 'outpatient', NULL, NULL, NULL, NULL, '$2y$10$z.knaSssdHGf4GHuISxMmu34nWHET46RgxRDMdoKBmSXaXRLlV/EG', '2025-07-19 12:48:32', '2025-07-19 12:48:32'),
(3, NULL, 1, 'P20250003', 'atharva', '', 'channa', '+91987654321', '', 'atharva@email.com', 'pune , mh', '2000-07-20', 'male', 'AB+', 'single', 'lawyer', 'none', 'none', NULL, 'outpatient', NULL, NULL, NULL, NULL, '$2y$10$D3V0yxto3JEPID1UpFdfS.dUI9Oax08qcSMa7bk6GJf6WPfqY7C5G', '2025-07-19 13:18:47', '2025-07-19 13:18:47'),
(4, 14, 1, 'P20250004', 'shivam', '', 'mishra', '+918855872756', '', 'maju@email.com', 'b8 203 chandresh hills', '2001-07-23', 'male', 'B+', 'single', 'student', '', '', NULL, 'outpatient', NULL, NULL, NULL, NULL, NULL, '2025-07-19 19:47:17', '2025-07-19 19:47:17');

-- --------------------------------------------------------

--
-- Table structure for table `patient_bills_enhanced`
--

CREATE TABLE `patient_bills_enhanced` (
  `id` int(11) NOT NULL,
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
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `outstanding_amount` decimal(12,2) DEFAULT 0.00,
  `payment_status` enum('pending','partial','paid','insurance_pending','overdue') DEFAULT 'pending',
  `payment_due_date` date DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'INR',
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `generated_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `billing_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `patient_bills_enhanced`
--
DELIMITER $$
CREATE TRIGGER `auto_enhanced_bill_number` BEFORE INSERT ON `patient_bills_enhanced` FOR EACH ROW BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM patient_bills_enhanced WHERE DATE(created_at) = CURDATE();
  SET NEW.bill_number = CONCAT('EBILL', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
  
  -- Calculate total amount
  SET NEW.total_amount = NEW.base_amount + NEW.consultation_charges + NEW.room_charges + 
                        NEW.nursing_charges + NEW.medicine_charges + NEW.investigation_charges + 
                        NEW.operation_charges + NEW.blood_charges + NEW.other_charges - 
                        NEW.discount_amount + NEW.tax_amount;
  
  -- Calculate outstanding amount
  SET NEW.outstanding_amount = NEW.total_amount - NEW.insurance_coverage - NEW.paid_amount;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `patient_insurance`
--

CREATE TABLE `patient_insurance` (
  `id` int(11) NOT NULL,
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
  `available_limit` decimal(12,2) GENERATED ALWAYS AS (`coverage_limit` - `used_amount`) STORED,
  `policy_document_path` varchar(500) DEFAULT NULL,
  `family_members` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of covered family members' CHECK (json_valid(`family_members`)),
  `pre_existing_conditions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `patient_insurance`
--
DELIMITER $$
CREATE TRIGGER `update_insurance_limit` BEFORE UPDATE ON `patient_insurance` FOR EACH ROW BEGIN
  SET NEW.available_limit = NEW.coverage_limit - NEW.used_amount;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `patient_insurance_summary`
-- (See below for the actual view)
--
CREATE TABLE `patient_insurance_summary` (
`patient_id` int(11)
,`patient_name` varchar(152)
,`company_name` varchar(255)
,`policy_number` varchar(100)
,`policy_type` enum('individual','family','group','corporate')
,`coverage_limit` decimal(12,2)
,`used_amount` decimal(12,2)
,`available_limit` decimal(12,2)
,`expiry_date` date
,`policy_status` varchar(13)
);

-- --------------------------------------------------------

--
-- Table structure for table `patient_payments`
--

CREATE TABLE `patient_payments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `payment_reference` varchar(100) NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `payment_method` enum('cash','card','upi','bank_transfer','cheque','insurance','wallet','online') NOT NULL,
  `payment_details` text DEFAULT NULL COMMENT 'Card details, UPI ID, etc.',
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_gateway` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded','cancelled') DEFAULT 'completed',
  `payment_date` datetime NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `refund_amount` decimal(12,2) DEFAULT 0.00,
  `refund_reason` text DEFAULT NULL,
  `refund_date` datetime DEFAULT NULL,
  `processed_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `receipt_number` varchar(50) DEFAULT NULL,
  `receipt_path` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `patient_payments`
--
DELIMITER $$
CREATE TRIGGER `auto_payment_reference_enhanced` BEFORE INSERT ON `patient_payments` FOR EACH ROW BEGIN
  DECLARE count INT;
  SELECT COUNT(*) INTO count FROM patient_payments WHERE DATE(created_at) = CURDATE();
  SET NEW.payment_reference = CONCAT('PAY', DATE_FORMAT(NOW(), '%Y%m%d'), LPAD(count + 1, 4, '0'));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_enhanced_bill_payment_status` AFTER INSERT ON `patient_payments` FOR EACH ROW BEGIN
  DECLARE total_paid DECIMAL(12,2);
  DECLARE bill_total DECIMAL(12,2);
  DECLARE insurance_cov DECIMAL(12,2);
  
  IF NEW.bill_id IS NOT NULL THEN
    SELECT COALESCE(SUM(amount_paid), 0) INTO total_paid
    FROM patient_payments
    WHERE bill_id = NEW.bill_id AND payment_status = 'completed';
    
    SELECT total_amount, insurance_coverage INTO bill_total, insurance_cov
    FROM patient_bills_enhanced
    WHERE id = NEW.bill_id;
    
    UPDATE patient_bills_enhanced
    SET paid_amount = total_paid,
        outstanding_amount = bill_total - IFNULL(insurance_cov, 0) - total_paid,
        payment_status = CASE
          WHEN total_paid >= (bill_total - IFNULL(insurance_cov, 0)) THEN 'paid'
          WHEN total_paid > 0 THEN 'partial'
          ELSE 'pending'
        END
    WHERE id = NEW.bill_id;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `patient_status`
--

CREATE TABLE `patient_status` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `status` enum('inpatient','outpatient','emergency','discharged','transferred','deceased') NOT NULL,
  `admission_date` timestamp NULL DEFAULT NULL,
  `discharge_date` timestamp NULL DEFAULT NULL,
  `admission_type` enum('emergency','planned','transfer') DEFAULT NULL,
  `admission_reason` text DEFAULT NULL,
  `attending_doctor_id` int(11) DEFAULT NULL,
  `current_bed_id` int(11) DEFAULT NULL,
  `discharge_summary` text DEFAULT NULL,
  `discharge_instructions` text DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT 0,
  `follow_up_date` date DEFAULT NULL,
  `insurance_verified` tinyint(1) DEFAULT 0,
  `emergency_contact_notified` tinyint(1) DEFAULT 0,
  `status_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_visits`
--

CREATE TABLE `patient_visits` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `visit_type` enum('consultation','emergency','follow_up','routine') NOT NULL,
  `visit_reason` text NOT NULL,
  `attendant_name` varchar(100) DEFAULT NULL,
  `attendant_phone` varchar(20) DEFAULT NULL,
  `assigned_doctor_id` int(11) DEFAULT NULL,
  `assigned_nurse_id` int(11) DEFAULT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_vitals`
--

CREATE TABLE `patient_vitals` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `recorded_by_type` enum('doctor','nurse','lab_tech') NOT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(4,2) DEFAULT NULL,
  `oxygen_saturation` decimal(4,1) DEFAULT NULL,
  `vital_signs` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_vitals`
--

INSERT INTO `patient_vitals` (`id`, `patient_id`, `recorded_by`, `recorded_by_type`, `temperature`, `blood_pressure`, `heart_rate`, `respiratory_rate`, `weight`, `height`, `bmi`, `oxygen_saturation`, `vital_signs`, `notes`, `recorded_at`) VALUES
(1, 2, 4, 'nurse', 33.0, '102', 81, 17, 60.00, 167.00, 21.50, 99.0, '102', NULL, '2025-07-19 12:50:10'),
(2, 1, 1, 'nurse', 98.6, '120/80', 72, 16, 70.50, 175.00, NULL, 98.5, NULL, 'Normal vitals recorded during routine check', '2025-07-19 19:38:31'),
(3, 1, 1, 'nurse', 99.1, '125/85', 78, 18, 70.20, 175.00, NULL, 97.8, NULL, 'Slightly elevated temperature', '2025-07-19 19:38:31'),
(4, 3, 1, 'nurse', 35.0, '108', 74, 15, 73.00, 169.00, 25.60, 99.0, NULL, NULL, '2025-07-20 06:42:56'),
(5, 3, 1, 'nurse', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-07-20 06:44:29');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `prescription_date` date NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `dispensed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_medicines`
--

CREATE TABLE `prescription_medicines` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `dispensed_quantity` int(11) DEFAULT 0,
  `dispensed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `role_display_name`, `description`, `permissions`, `is_active`, `created_at`) VALUES
(1, 'admin', 'Administrator', 'Full system access', NULL, 1, '2025-07-19 08:17:08'),
(2, 'doctor', 'Doctor', 'Doctor access with patient management', NULL, 1, '2025-07-19 08:17:08'),
(3, 'nurse', 'Nurse', 'Nurse access with patient care', NULL, 1, '2025-07-19 08:17:08'),
(4, 'patient', 'Patient', 'Patient access to own records', NULL, 1, '2025-07-19 08:17:08'),
(5, 'receptionist', 'Receptionist', 'Reception and patient registration', NULL, 1, '2025-07-19 08:17:08'),
(6, 'lab_technician', 'Lab Technician', 'Laboratory test management', NULL, 1, '2025-07-19 08:17:08'),
(7, 'pharmacy_staff', 'Pharmacy Staff', 'Pharmacy and medicine management', NULL, 1, '2025-07-19 08:17:08'),
(8, 'intern_doctor', 'Intern Doctor', 'Junior doctor with limited access', NULL, 1, '2025-07-19 08:17:08'),
(9, 'intern_nurse', 'Intern Nurse', 'Junior nurse with limited access', NULL, 1, '2025-07-19 08:17:08'),
(10, 'intern_lab', 'Intern Lab Tech', 'Junior lab technician with limited access', NULL, 1, '2025-07-19 08:17:08'),
(11, 'intern_pharmacy', 'Intern Pharmacy', 'Junior pharmacy staff with limited access', NULL, 1, '2025-07-19 08:17:08'),
(12, 'blood_bank_staff', 'Blood Bank Staff', 'Blood bank operations and management', NULL, 1, '2025-07-21 10:18:48'),
(13, 'insurance_staff', 'Insurance Staff', 'Insurance claims and policy management', NULL, 1, '2025-07-21 10:18:48'),
(14, 'billing_staff', 'Billing Staff', 'Patient billing and payment processing', NULL, 1, '2025-07-21 10:18:48'),
(15, 'transplant_coordinator', 'Transplant Coordinator', 'Organ transplant coordination', NULL, 1, '2025-07-21 10:18:48');

-- --------------------------------------------------------

--
-- Table structure for table `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `shift_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shifts`
--

INSERT INTO `shifts` (`id`, `hospital_id`, `shift_name`, `start_time`, `end_time`, `is_active`, `created_at`) VALUES
(1, 1, 'Morning Shift', '08:00:00', '16:00:00', 1, '2025-07-19 08:17:08'),
(2, 1, 'Evening Shift', '16:00:00', '00:00:00', 1, '2025-07-19 08:17:08'),
(3, 1, 'Night Shift', '00:00:00', '08:00:00', 1, '2025-07-19 08:17:08');

-- --------------------------------------------------------

--
-- Table structure for table `shift_assignments`
--

CREATE TABLE `shift_assignments` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `assignment_date` date NOT NULL,
  `status` enum('assigned','completed','cancelled') DEFAULT 'assigned',
  `notes` text DEFAULT NULL,
  `assigned_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `hospital_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `employee_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `staff_type` enum('nurse','receptionist','lab_technician','pharmacy_staff','intern_nurse','intern_lab','intern_pharmacy','driver') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `date_of_joining` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `qualification` text DEFAULT NULL,
  `is_intern` tinyint(1) DEFAULT 0,
  `senior_staff_id` int(11) DEFAULT NULL,
  `vitals` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vitals`)),
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `user_id`, `hospital_id`, `department_id`, `employee_id`, `first_name`, `middle_name`, `last_name`, `staff_type`, `phone`, `emergency_contact`, `address`, `date_of_birth`, `gender`, `blood_group`, `date_of_joining`, `salary`, `qualification`, `is_intern`, `senior_staff_id`, `vitals`, `image_url`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 1, 'NUR001', 'Priya', NULL, 'Nurse', 'nurse', '+919876543214', '+919876543215', '789 Nurse Road, City', '1990-03-10', 'female', 'A+', '2021-02-01', 35000.00, 'BSc Nursing', 0, NULL, NULL, NULL, 1, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(2, 5, 1, 1, 'REC001', 'Reception', NULL, 'Staff', 'receptionist', '+919876543216', '+919876543217', '321 Reception Ave, City', '1988-12-05', 'female', 'AB+', '2020-03-01', 25000.00, 'BA', 0, NULL, NULL, NULL, 1, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(3, 6, 1, 8, 'LAB001', 'Lab', NULL, 'Technician', 'lab_technician', '+919876543218', '+919876543219', '654 Lab Street, City', '1992-07-15', 'male', 'B-', '2021-04-01', 30000.00, 'BSc MLT', 0, NULL, NULL, NULL, 1, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(4, 7, 1, 9, 'PHARM001', 'Pharmacy', NULL, 'Staff', 'pharmacy_staff', '+919876543220', '+919876543221', '987 Pharmacy Lane, City', '1993-11-20', 'male', 'O-', '2021-05-01', 28000.00, 'BPharm', 0, NULL, NULL, NULL, 1, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(5, 9, 1, 1, 'NUR002', 'Intern', NULL, 'Nurse', 'intern_nurse', '+919876543222', '+919876543223', '147 Intern Road, City', '1998-04-12', 'female', 'A-', '2023-07-01', 20000.00, 'BSc Nursing', 0, NULL, NULL, NULL, 1, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(6, 10, 1, 8, 'LAB002', 'Intern', NULL, 'Lab Tech', 'intern_lab', '+919876543224', '+919876543225', '258 Intern Lab, City', '1999-09-08', 'male', 'B+', '2023-08-01', 18000.00, 'BSc MLT', 0, NULL, NULL, NULL, 1, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(7, 11, 1, 9, 'PHARM002', 'Intern', NULL, 'Pharmacy', 'intern_pharmacy', '+919876543226', '+919876543227', '369 Intern Pharm, City', '2000-01-25', 'female', 'AB-', '2023-09-01', 17000.00, 'BPharm', 0, NULL, NULL, NULL, 1, '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(12, 15, 1, NULL, 'PHAR001', 'Pharmacy', NULL, 'Staff', 'pharmacy_staff', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, '2025-07-20 05:49:29', '2025-07-20 05:49:29');

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','half_day','leave','holiday') NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `marked_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_salary`
--

CREATE TABLE `staff_salary` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `base_salary` decimal(12,2) NOT NULL,
  `present_days` int(11) DEFAULT 0,
  `absent_days` int(11) DEFAULT 0,
  `half_days` int(11) DEFAULT 0,
  `leave_days` int(11) DEFAULT 0,
  `total_salary` decimal(12,2) NOT NULL,
  `deductions` decimal(12,2) DEFAULT 0.00,
  `net_salary` decimal(12,2) NOT NULL,
  `calculated_by` int(11) NOT NULL,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `hospital_id`, `setting_key`, `setting_value`, `setting_type`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 'site_title', 'Hospital CRM', 'string', 'Website title', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(2, 1, 'site_logo', '', 'string', 'Logo URL', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(3, 1, 'site_favicon', '', 'string', 'Favicon URL', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(4, 1, 'primary_color', '#004685', 'string', 'Primary color', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(5, 1, 'secondary_color', '#0066cc', 'string', 'Secondary color', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(6, 1, 'enable_dark_mode', 'false', 'boolean', 'Enable dark mode', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(7, 1, 'enable_email_notifications', 'false', 'boolean', 'Enable email notifications', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(8, 1, 'enable_sms_notifications', 'false', 'boolean', 'Enable SMS notifications', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(9, 1, 'enable_intern_system', 'false', 'boolean', 'Enable intern system', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(10, 1, 'enable_attendance_system', 'true', 'boolean', 'Enable attendance system', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(11, 1, 'enable_multi_hospital', 'false', 'boolean', 'Enable multi-hospital system', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(12, 1, 'enable_insurance_claims', 'true', 'boolean', 'Enable insurance claims', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(13, 1, 'enable_ambulance_management', 'false', 'boolean', 'Enable ambulance management', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(14, 1, 'enable_feedback_system', 'false', 'boolean', 'Enable feedback system', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(15, 1, 'enable_home_visits', 'false', 'boolean', 'Enable home visits', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(16, 1, 'enable_video_consultation', 'false', 'boolean', 'Enable video consultation', '2025-07-19 08:17:08', '2025-07-21 10:38:02'),
(17, 1, 'enable_backup_system', 'true', 'boolean', 'Enable auto backup system', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(18, 1, 'enable_audit_logging', 'true', 'boolean', 'Enable audit logging', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(19, 1, 'enable_two_factor_auth', 'false', 'boolean', 'Enable two-factor authentication', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(20, 1, 'min_password_length', '8', 'number', 'Minimum password length', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(21, 1, 'require_uppercase', 'true', 'boolean', 'Require uppercase letters in password', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(22, 1, 'require_numbers', 'true', 'boolean', 'Require numbers in password', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(23, 1, 'require_special_chars', 'false', 'boolean', 'Require special characters in password', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(24, 1, 'session_timeout', '30', 'number', 'Session timeout in minutes', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(25, 1, 'force_logout_on_password_change', 'true', 'boolean', 'Force logout on password change', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(26, 1, 'prevent_concurrent_logins', 'false', 'boolean', 'Prevent concurrent logins', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(27, 1, 'max_login_attempts', '5', 'number', 'Maximum login attempts', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(28, 1, 'lockout_duration', '15', 'number', 'Lockout duration in minutes', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(29, 1, 'enable_captcha', 'false', 'boolean', 'Enable CAPTCHA on login', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(30, 1, 'enable_data_encryption', 'true', 'boolean', 'Enable data encryption', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(31, 1, 'enable_ssl_redirect', 'false', 'boolean', 'Force HTTPS redirect', '2025-07-19 08:17:08', '2025-07-19 08:17:08'),
(32, 1, 'enable_csrf_protection', 'true', 'boolean', 'Enable CSRF protection', '2025-07-19 08:17:08', '2025-07-19 08:17:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `first_name`, `last_name`, `password_hash`, `role_id`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@hospital.com', 'Admin', 'User', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 1, 1, '2025-07-21 06:36:54', '2025-07-19 08:17:08', '2025-07-21 10:36:54'),
(2, 'dr.sharma', 'dr.sharma@hospital.com', 'dr.sharma', 'dr.sharma', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 2, 1, '2025-07-19 09:24:32', '2025-07-19 08:17:08', '2025-07-20 06:36:19'),
(3, 'john.doe', 'john.doe@email.com', 'john.doe', 'john.doe', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 4, 1, '2025-07-21 06:23:34', '2025-07-19 08:17:08', '2025-07-21 10:23:34'),
(4, 'priya.nurse', 'priya.nurse@hospital.com', 'priya.nurse', 'priya.nurse', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 3, 1, '2025-07-19 08:49:10', '2025-07-19 08:17:08', '2025-07-20 06:36:23'),
(5, 'reception', 'reception@hospital.com', 'reception', 'reception', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 5, 1, '2025-07-19 04:39:02', '2025-07-19 08:17:08', '2025-07-20 05:52:05'),
(6, 'lab.tech', 'lab.tech@hospital.com', 'lab.tech', 'lab.tech', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 6, 1, '2025-07-20 01:56:47', '2025-07-19 08:17:08', '2025-07-20 05:56:47'),
(7, 'pharmacy.staff', 'pharmacy.staff@hospital.com', 'pharmacy.staff', 'pharmacy.staff', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 7, 1, '2025-07-20 01:38:22', '2025-07-19 08:17:08', '2025-07-20 05:52:14'),
(8, 'intern.doctor', 'intern.doctor@hospital.com', 'intern.doctor', 'intern.doctor', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 8, 1, NULL, '2025-07-19 08:17:08', '2025-07-20 05:52:19'),
(9, 'intern.nurse', 'intern.nurse@hospital.com', 'intern.nurse', 'intern.nurse', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 9, 1, NULL, '2025-07-19 08:17:08', '2025-07-20 05:52:22'),
(10, 'intern.lab', 'intern.lab@hospital.com', 'intern.lab', 'intern.lab', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 10, 1, NULL, '2025-07-19 08:17:08', '2025-07-20 05:52:25'),
(11, 'intern.pharmacy', 'intern.pharmacy@hospital.com', 'intern.pharmacy', 'intern.pharmacy', '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 11, 1, NULL, '2025-07-19 08:17:08', '2025-07-20 05:52:28'),
(14, 'maju', 'maju@email.com', NULL, NULL, '$2a$10$EMPd6Usa6cdftJy/E19Od.nLt8NQpXNmuVg24V3IxY.dPJmy5zmiG', 4, 1, NULL, '2025-07-19 19:47:17', '2025-07-20 05:52:30'),
(15, 'pharmacy_demo', 'pharmacy@demo.com', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 7, 1, '2025-07-20 01:55:32', '2025-07-20 05:35:45', '2025-07-20 06:27:50'),
(16, 'patient.demo', 'demo@patient.com', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 1, NULL, '2025-07-20 05:44:31', '2025-07-20 06:27:50'),
(17, 'reception.demo', 'reception@demo.com', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, NULL, '2025-07-20 05:44:31', '2025-07-20 06:27:50'),
(18, 'nurse.priya', 'nurse@demo.com', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 7, 1, NULL, '2025-07-20 05:44:31', '2025-07-20 06:27:50'),
(51, 'driver.demo', 'driver@demo.com', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 8, 1, NULL, '2025-07-20 06:35:00', '2025-07-20 06:35:00'),
(53, 'blood_bank_staff', 'bloodbank@hospital.com', 'Blood Bank', 'Staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 12, 1, NULL, '2025-07-21 10:18:49', '2025-07-21 10:18:49'),
(54, 'insurance_staff', 'insurance@hospital.com', 'Insurance', 'Staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 13, 1, NULL, '2025-07-21 10:18:49', '2025-07-21 10:18:49'),
(55, 'billing_staff', 'billing@hospital.com', 'Billing', 'Staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 14, 1, NULL, '2025-07-21 10:18:49', '2025-07-21 10:18:49');

-- --------------------------------------------------------

--
-- Structure for view `blood_inventory_dashboard`
--
DROP TABLE IF EXISTS `blood_inventory_dashboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `blood_inventory_dashboard`  AS SELECT `blood_inventory`.`blood_group` AS `blood_group`, `blood_inventory`.`component_type` AS `component_type`, count(0) AS `total_units`, sum(case when `blood_inventory`.`status` = 'available' then 1 else 0 end) AS `available_units`, sum(case when `blood_inventory`.`status` = 'used' then 1 else 0 end) AS `used_units`, sum(case when `blood_inventory`.`status` = 'expired' then 1 else 0 end) AS `expired_units`, sum(case when `blood_inventory`.`status` = 'quarantine' then 1 else 0 end) AS `quarantine_units`, avg(to_days(`blood_inventory`.`expiry_date`) - to_days(curdate())) AS `avg_days_to_expiry` FROM `blood_inventory` GROUP BY `blood_inventory`.`blood_group`, `blood_inventory`.`component_type` ORDER BY `blood_inventory`.`blood_group` ASC, `blood_inventory`.`component_type` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `outstanding_bills_enhanced`
--
DROP TABLE IF EXISTS `outstanding_bills_enhanced`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `outstanding_bills_enhanced`  AS SELECT `pb`.`id` AS `bill_id`, `pb`.`bill_number` AS `bill_number`, concat(`p`.`first_name`,' ',ifnull(`p`.`middle_name`,''),' ',`p`.`last_name`) AS `patient_name`, `p`.`patient_id` AS `patient_number`, `p`.`phone` AS `phone`, `pb`.`service_type` AS `service_type`, `pb`.`service_date` AS `service_date`, `pb`.`total_amount` AS `total_amount`, `pb`.`insurance_coverage` AS `insurance_coverage`, `pb`.`paid_amount` AS `paid_amount`, `pb`.`outstanding_amount` AS `outstanding_amount`, `pb`.`payment_status` AS `payment_status`, `pb`.`payment_due_date` AS `payment_due_date`, to_days(curdate()) - to_days(`pb`.`payment_due_date`) AS `days_overdue`, CASE WHEN `pb`.`payment_due_date` < curdate() THEN 'Overdue' WHEN `pb`.`payment_due_date` <= curdate() + interval 7 day THEN 'Due Soon' ELSE 'Current' END AS `payment_urgency` FROM (`patient_bills_enhanced` `pb` join `patients` `p` on(`pb`.`patient_id` = `p`.`id`)) WHERE `pb`.`outstanding_amount` > 0 ORDER BY `pb`.`payment_due_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `patient_insurance_summary`
--
DROP TABLE IF EXISTS `patient_insurance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `patient_insurance_summary`  AS SELECT `pi`.`patient_id` AS `patient_id`, concat(`p`.`first_name`,' ',ifnull(`p`.`middle_name`,''),' ',`p`.`last_name`) AS `patient_name`, `ic`.`company_name` AS `company_name`, `pi`.`policy_number` AS `policy_number`, `pi`.`policy_type` AS `policy_type`, `pi`.`coverage_limit` AS `coverage_limit`, `pi`.`used_amount` AS `used_amount`, `pi`.`available_limit` AS `available_limit`, `pi`.`expiry_date` AS `expiry_date`, CASE WHEN `pi`.`expiry_date` < curdate() THEN 'Expired' WHEN `pi`.`expiry_date` <= curdate() + interval 30 day THEN 'Expiring Soon' ELSE 'Active' END AS `policy_status` FROM ((`patient_insurance` `pi` join `patients` `p` on(`pi`.`patient_id` = `p`.`id`)) join `insurance_companies` `ic` on(`pi`.`insurance_company_id` = `ic`.`id`)) WHERE `pi`.`is_active` = 1 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ambulances`
--
ALTER TABLE `ambulances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vehicle_number` (`vehicle_number`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `ambulance_bookings`
--
ALTER TABLE `ambulance_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ambulance_id` (`ambulance_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `appointment_number` (`appointment_number`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_appointment_patient` (`patient_id`),
  ADD KEY `fk_appointment_doctor` (`doctor_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `beds`
--
ALTER TABLE `beds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bed_number` (`bed_number`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `current_patient_id` (`current_patient_id`),
  ADD KEY `fk_bed_department` (`department_id`);

--
-- Indexes for table `bed_assignments`
--
ALTER TABLE `bed_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_assignment_bed` (`bed_id`),
  ADD KEY `fk_assignment_patient` (`patient_id`),
  ADD KEY `fk_assignment_staff` (`assigned_by`);

--
-- Indexes for table `billing_templates`
--
ALTER TABLE `billing_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `service_type` (`service_type`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_number` (`bill_number`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`);

--
-- Indexes for table `bill_payments`
--
ALTER TABLE `bill_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `blood_activity_audit`
--
ALTER TABLE `blood_activity_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `activity_type` (`activity_type`),
  ADD KEY `record_id` (`record_id`);

--
-- Indexes for table `blood_donation_sessions`
--
ALTER TABLE `blood_donation_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `collected_by` (`collected_by`),
  ADD KEY `collection_date` (`collection_date`);

--
-- Indexes for table `blood_donors`
--
ALTER TABLE `blood_donors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `donor_id` (`donor_id`),
  ADD UNIQUE KEY `patient_id` (`patient_id`),
  ADD KEY `blood_group` (`blood_group`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bag_number` (`bag_number`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `blood_group` (`blood_group`,`component_type`),
  ADD KEY `status` (`status`),
  ADD KEY `expiry_date` (`expiry_date`),
  ADD KEY `issued_to_patient_id` (`issued_to_patient_id`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `blood_requests`
--
ALTER TABLE `blood_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `fulfilled_by` (`fulfilled_by`),
  ADD KEY `blood_group` (`blood_group`,`component_type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `blood_usage_records`
--
ALTER TABLE `blood_usage_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blood_bag_id` (`blood_bag_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `used_by` (`used_by`),
  ADD KEY `usage_date` (`usage_date`);

--
-- Indexes for table `claim_timeline`
--
ALTER TABLE `claim_timeline`
  ADD PRIMARY KEY (`id`),
  ADD KEY `claim_id` (`claim_id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `senior_doctor_id` (`senior_doctor_id`),
  ADD KEY `fk_doctor_department` (`department_id`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `equipment_maintenance`
--
ALTER TABLE `equipment_maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipment_id` (`equipment_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `responded_by` (`responded_by`);

--
-- Indexes for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `insurance_claims`
--
ALTER TABLE `insurance_claims`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `insurance_claims_enhanced`
--
ALTER TABLE `insurance_claims_enhanced`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `claim_number` (`claim_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `policy_id` (`policy_id`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `claim_status` (`claim_status`),
  ADD KEY `service_date` (`service_date`);

--
-- Indexes for table `insurance_claims_new`
--
ALTER TABLE `insurance_claims_new`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `claim_number` (`claim_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `policy_id` (`policy_id`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `claim_status` (`claim_status`),
  ADD KEY `service_date` (`service_date`);

--
-- Indexes for table `insurance_companies`
--
ALTER TABLE `insurance_companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `company_code` (`company_code`),
  ADD KEY `company_name` (`company_name`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `insurance_coverage_rules`
--
ALTER TABLE `insurance_coverage_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `insurance_company_id` (`insurance_company_id`),
  ADD KEY `service_type` (`service_type`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `insurance_verifications`
--
ALTER TABLE `insurance_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `policy_id` (`policy_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `verification_type` (`verification_type`),
  ADD KEY `verification_status` (`verification_status`);

--
-- Indexes for table `interns`
--
ALTER TABLE `interns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `intern_rotations`
--
ALTER TABLE `intern_rotations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `intern_id` (`intern_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `lab_order_tests`
--
ALTER TABLE `lab_order_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lab_order_id` (`lab_order_id`),
  ADD KEY `lab_test_id` (`lab_test_id`),
  ADD KEY `completed_by` (`completed_by`);

--
-- Indexes for table `lab_tests`
--
ALTER TABLE `lab_tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `test_code` (`test_code`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `medicine_categories`
--
ALTER TABLE `medicine_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_category_per_hospital` (`hospital_id`,`name`);

--
-- Indexes for table `medicine_stock_movements`
--
ALTER TABLE `medicine_stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medicine_id` (`medicine_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `organ_activity_audit`
--
ALTER TABLE `organ_activity_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `activity_type` (`activity_type`),
  ADD KEY `legal_significance` (`legal_significance`);

--
-- Indexes for table `organ_donations`
--
ALTER TABLE `organ_donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `consent_id` (`consent_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `organ_type` (`organ_type`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `organ_donor_consent`
--
ALTER TABLE `organ_donor_consent`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `consent_type` (`consent_type`);

--
-- Indexes for table `organ_legal_rejections`
--
ALTER TABLE `organ_legal_rejections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `rejected_by` (`rejected_by`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `record_type` (`record_type`,`record_id`),
  ADD KEY `rejection_date` (`rejection_date`);

--
-- Indexes for table `organ_recipients`
--
ALTER TABLE `organ_recipients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `organ_needed` (`organ_needed`,`urgency_level`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `organ_transplants`
--
ALTER TABLE `organ_transplants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donation_id` (`donation_id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `outcome` (`outcome`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_id` (`patient_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `assigned_doctor_id` (`assigned_doctor_id`);

--
-- Indexes for table `patient_bills_enhanced`
--
ALTER TABLE `patient_bills_enhanced`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_number` (`bill_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`),
  ADD KEY `insurance_claim_id` (`insurance_claim_id`),
  ADD KEY `generated_by` (`generated_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `payment_status` (`payment_status`);

--
-- Indexes for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_patient_policy` (`patient_id`,`insurance_company_id`,`policy_number`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `insurance_company_id` (`insurance_company_id`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `expiry_date` (`expiry_date`);

--
-- Indexes for table `patient_payments`
--
ALTER TABLE `patient_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_reference` (`payment_reference`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `payment_method` (`payment_method`),
  ADD KEY `payment_status` (`payment_status`);

--
-- Indexes for table `patient_status`
--
ALTER TABLE `patient_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `status` (`status`),
  ADD KEY `attending_doctor_id` (`attending_doctor_id`),
  ADD KEY `current_bed_id` (`current_bed_id`),
  ADD KEY `admission_date` (`admission_date`);

--
-- Indexes for table `patient_visits`
--
ALTER TABLE `patient_visits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `assigned_doctor_id` (`assigned_doctor_id`),
  ADD KEY `assigned_nurse_id` (`assigned_nurse_id`);

--
-- Indexes for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `prescription_medicines`
--
ALTER TABLE `prescription_medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `medicine_id` (`medicine_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `shift_id` (`shift_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `senior_staff_id` (`senior_staff_id`);

--
-- Indexes for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `marked_by` (`marked_by`);

--
-- Indexes for table `staff_salary`
--
ALTER TABLE `staff_salary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `calculated_by` (`calculated_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting` (`hospital_id`,`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ambulances`
--
ALTER TABLE `ambulances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ambulance_bookings`
--
ALTER TABLE `ambulance_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `beds`
--
ALTER TABLE `beds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `bed_assignments`
--
ALTER TABLE `bed_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `billing_templates`
--
ALTER TABLE `billing_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bills`
--
ALTER TABLE `bills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bill_items`
--
ALTER TABLE `bill_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bill_payments`
--
ALTER TABLE `bill_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `blood_activity_audit`
--
ALTER TABLE `blood_activity_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blood_donation_sessions`
--
ALTER TABLE `blood_donation_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blood_donors`
--
ALTER TABLE `blood_donors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blood_requests`
--
ALTER TABLE `blood_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blood_usage_records`
--
ALTER TABLE `blood_usage_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `claim_timeline`
--
ALTER TABLE `claim_timeline`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `equipment_maintenance`
--
ALTER TABLE `equipment_maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `insurance_claims`
--
ALTER TABLE `insurance_claims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `insurance_claims_enhanced`
--
ALTER TABLE `insurance_claims_enhanced`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `insurance_claims_new`
--
ALTER TABLE `insurance_claims_new`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `insurance_companies`
--
ALTER TABLE `insurance_companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `insurance_coverage_rules`
--
ALTER TABLE `insurance_coverage_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `insurance_verifications`
--
ALTER TABLE `insurance_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interns`
--
ALTER TABLE `interns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `intern_rotations`
--
ALTER TABLE `intern_rotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_orders`
--
ALTER TABLE `lab_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_order_tests`
--
ALTER TABLE `lab_order_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_tests`
--
ALTER TABLE `lab_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `medicine_categories`
--
ALTER TABLE `medicine_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `medicine_stock_movements`
--
ALTER TABLE `medicine_stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organ_activity_audit`
--
ALTER TABLE `organ_activity_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organ_donations`
--
ALTER TABLE `organ_donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organ_donor_consent`
--
ALTER TABLE `organ_donor_consent`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organ_legal_rejections`
--
ALTER TABLE `organ_legal_rejections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organ_recipients`
--
ALTER TABLE `organ_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organ_transplants`
--
ALTER TABLE `organ_transplants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `patient_bills_enhanced`
--
ALTER TABLE `patient_bills_enhanced`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_payments`
--
ALTER TABLE `patient_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_status`
--
ALTER TABLE `patient_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_visits`
--
ALTER TABLE `patient_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescription_medicines`
--
ALTER TABLE `prescription_medicines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_salary`
--
ALTER TABLE `staff_salary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=321;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ambulances`
--
ALTER TABLE `ambulances`
  ADD CONSTRAINT `ambulances_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`);

--
-- Constraints for table `ambulance_bookings`
--
ALTER TABLE `ambulance_bookings`
  ADD CONSTRAINT `ambulance_bookings_ibfk_1` FOREIGN KEY (`ambulance_id`) REFERENCES `ambulances` (`id`),
  ADD CONSTRAINT `ambulance_bookings_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `ambulance_bookings_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_appointment_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `beds`
--
ALTER TABLE `beds`
  ADD CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  ADD CONSTRAINT `beds_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `beds_ibfk_3` FOREIGN KEY (`current_patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_bed_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `bed_assignments`
--
ALTER TABLE `bed_assignments`
  ADD CONSTRAINT `bed_assignments_ibfk_1` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`),
  ADD CONSTRAINT `bed_assignments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `bed_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_assignment_bed` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`),
  ADD CONSTRAINT `fk_assignment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_assignment_staff` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  ADD CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `bills_ibfk_3` FOREIGN KEY (`visit_id`) REFERENCES `patient_visits` (`id`),
  ADD CONSTRAINT `bills_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `bill_items`
--
ALTER TABLE `bill_items`
  ADD CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`);

--
-- Constraints for table `bill_payments`
--
ALTER TABLE `bill_payments`
  ADD CONSTRAINT `bill_payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`),
  ADD CONSTRAINT `bill_payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `blood_donation_sessions`
--
ALTER TABLE `blood_donation_sessions`
  ADD CONSTRAINT `fk_session_donor` FOREIGN KEY (`donor_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_session_staff` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `blood_donors`
--
ALTER TABLE `blood_donors`
  ADD CONSTRAINT `fk_donor_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);

--
-- Constraints for table `blood_inventory`
--
ALTER TABLE `blood_inventory`
  ADD CONSTRAINT `fk_inventory_donor` FOREIGN KEY (`donor_id`) REFERENCES `blood_donors` (`id`),
  ADD CONSTRAINT `fk_inventory_patient` FOREIGN KEY (`issued_to_patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `fk_inventory_staff` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`);

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `doctors_ibfk_2` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  ADD CONSTRAINT `doctors_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `doctors_ibfk_4` FOREIGN KEY (`senior_doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_doctor_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_doctor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`);

--
-- Constraints for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD CONSTRAINT `email_templates_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`);

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `equipment_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`);

--
-- Constraints for table `equipment_maintenance`
--
ALTER TABLE `equipment_maintenance`
  ADD CONSTRAINT `equipment_maintenance_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `feedback_ibfk_3` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `insurance_claims`
--
ALTER TABLE `insurance_claims`
  ADD CONSTRAINT `insurance_claims_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `insurance_claims_ibfk_2` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`),
  ADD CONSTRAINT `insurance_claims_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `insurance_claims_new`
--
ALTER TABLE `insurance_claims_new`
  ADD CONSTRAINT `insurance_claims_new_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `insurance_claims_new_ibfk_2` FOREIGN KEY (`policy_id`) REFERENCES `patient_insurance` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `insurance_claims_new_ibfk_3` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `insurance_claims_new_ibfk_4` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `interns`
--
ALTER TABLE `interns`
  ADD CONSTRAINT `fk_intern_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_intern_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_intern_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `intern_rotations`
--
ALTER TABLE `intern_rotations`
  ADD CONSTRAINT `fk_rotation_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `fk_rotation_intern` FOREIGN KEY (`intern_id`) REFERENCES `interns` (`id`),
  ADD CONSTRAINT `fk_rotation_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `doctors` (`id`);

--
-- Constraints for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD CONSTRAINT `lab_orders_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `lab_orders_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `lab_orders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `lab_order_tests`
--
ALTER TABLE `lab_order_tests`
  ADD CONSTRAINT `lab_order_tests_ibfk_1` FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`id`),
  ADD CONSTRAINT `lab_order_tests_ibfk_2` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`),
  ADD CONSTRAINT `lab_order_tests_ibfk_3` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `medicines`
--
ALTER TABLE `medicines`
  ADD CONSTRAINT `medicines_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`);

--
-- Constraints for table `medicine_categories`
--
ALTER TABLE `medicine_categories`
  ADD CONSTRAINT `medicine_categories_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`);

--
-- Constraints for table `medicine_stock_movements`
--
ALTER TABLE `medicine_stock_movements`
  ADD CONSTRAINT `medicine_stock_movements_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`),
  ADD CONSTRAINT `medicine_stock_movements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `patients_ibfk_2` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  ADD CONSTRAINT `patients_ibfk_3` FOREIGN KEY (`assigned_doctor_id`) REFERENCES `doctors` (`id`);

--
-- Constraints for table `patient_status`
--
ALTER TABLE `patient_status`
  ADD CONSTRAINT `fk_status_bed` FOREIGN KEY (`current_bed_id`) REFERENCES `beds` (`id`),
  ADD CONSTRAINT `fk_status_doctor` FOREIGN KEY (`attending_doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `fk_status_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);

--
-- Constraints for table `patient_visits`
--
ALTER TABLE `patient_visits`
  ADD CONSTRAINT `patient_visits_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `patient_visits_ibfk_2` FOREIGN KEY (`assigned_doctor_id`) REFERENCES `doctors` (`id`),
  ADD CONSTRAINT `patient_visits_ibfk_3` FOREIGN KEY (`assigned_nurse_id`) REFERENCES `staff` (`id`);

--
-- Constraints for table `patient_vitals`
--
ALTER TABLE `patient_vitals`
  ADD CONSTRAINT `patient_vitals_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`);

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  ADD CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`);

--
-- Constraints for table `prescription_medicines`
--
ALTER TABLE `prescription_medicines`
  ADD CONSTRAINT `prescription_medicines_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`),
  ADD CONSTRAINT `prescription_medicines_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`);

--
-- Constraints for table `shifts`
--
ALTER TABLE `shifts`
  ADD CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`);

--
-- Constraints for table `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD CONSTRAINT `shift_assignments_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `shift_assignments_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`),
  ADD CONSTRAINT `shift_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `staff_ibfk_2` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`),
  ADD CONSTRAINT `staff_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `staff_ibfk_4` FOREIGN KEY (`senior_staff_id`) REFERENCES `staff` (`id`);

--
-- Constraints for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `staff_attendance_ibfk_2` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `staff_salary`
--
ALTER TABLE `staff_salary`
  ADD CONSTRAINT `staff_salary_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `staff_salary_ibfk_2` FOREIGN KEY (`calculated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

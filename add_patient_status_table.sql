-- Add patient status tracking table
CREATE TABLE IF NOT EXISTS `patient_status_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `status` enum('inpatient','outpatient','discharged','emergency') NOT NULL,
  `notes` text DEFAULT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `updated_by` (`updated_by`),
  KEY `updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add status column to patients table if it doesn't exist
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `status` enum('inpatient','outpatient','discharged','emergency') DEFAULT 'outpatient' AFTER `password`;
ALTER TABLE `patients` ADD COLUMN IF NOT EXISTS `last_status_update` timestamp NULL DEFAULT NULL AFTER `status`;

-- Add some sample status data
INSERT INTO `patient_status_logs` (`patient_id`, `status`, `notes`, `updated_by`, `updated_at`) VALUES
(1, 'outpatient', 'Initial registration', 1, NOW()),
(2, 'inpatient', 'Admitted for surgery', 1, NOW()),
(3, 'outpatient', 'Regular checkup', 1, NOW()),
(4, 'emergency', 'Emergency admission', 1, NOW()),
(5, 'discharged', 'Recovery complete', 1, NOW());
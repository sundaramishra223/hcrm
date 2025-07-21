-- ===================================================================
-- SECURITY LOGGING TABLES SETUP
-- Creates missing security_logs and login_attempts tables
-- ===================================================================

-- Security Logs table
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `event_type` (`event_type`),
  KEY `created_at` (`created_at`),
  KEY `ip_address` (`ip_address`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Login Attempts table
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `ip_address` (`ip_address`),
  KEY `success` (`success`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add indexes for better performance during cleanup operations
CREATE INDEX IF NOT EXISTS `idx_security_logs_cleanup` ON `security_logs` (`created_at`, `event_type`);
CREATE INDEX IF NOT EXISTS `idx_login_attempts_cleanup` ON `login_attempts` (`created_at`, `success`);
CREATE INDEX IF NOT EXISTS `idx_audit_logs_cleanup` ON `audit_logs` (`created_at`, `table_name`);
CREATE INDEX IF NOT EXISTS `idx_email_logs_cleanup` ON `email_logs` (`created_at`, `status`);
CREATE INDEX IF NOT EXISTS `idx_patient_status_logs_cleanup` ON `patient_status_logs` (`updated_at`, `status`);
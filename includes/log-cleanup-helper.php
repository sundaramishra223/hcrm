<?php
/**
 * Log Cleanup Helper
 * Centralizes all log cleanup functionality for the HCRM system
 * Handles both database logs and file-based logs
 */

class LogCleanupHelper {
    private $db;
    private $config;
    
    // Default retention periods (in days)
    private $default_retention = [
        'security_logs' => 90,
        'login_attempts' => 1,
        'audit_logs' => 180,
        'email_logs' => 30,
        'patient_status_logs' => 365,
        'error_logs' => 30
    ];
    
    public function __construct($database_connection) {
        $this->db = $database_connection;
        $this->loadConfig();
    }
    
    /**
     * Load cleanup configuration from system settings
     */
    private function loadConfig() {
        try {
            $result = $this->db->query(
                "SELECT setting_key, setting_value FROM system_settings 
                 WHERE setting_key LIKE 'log_retention_%'"
            );
            
            $this->config = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $key = str_replace('log_retention_', '', $row['setting_key']);
                    $this->config[$key] = (int)$row['setting_value'];
                }
            }
        } catch (Exception $e) {
            error_log("Log cleanup config loading error: " . $e->getMessage());
            $this->config = [];
        }
    }
    
    /**
     * Get retention period for a specific log type
     */
    private function getRetentionPeriod($log_type) {
        return $this->config[$log_type] ?? $this->default_retention[$log_type] ?? 30;
    }
    
    /**
     * Clean old security logs
     */
    public function cleanSecurityLogs($days = null) {
        $days = $days ?? $this->getRetentionPeriod('security_logs');
        
        try {
            $result = $this->db->query(
                "DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
            
            $deleted_count = $this->db->affected_rows ?? 0;
            $this->logCleanupActivity('security_logs', $deleted_count, $days);
            
            return [
                'success' => true,
                'deleted_count' => $deleted_count,
                'retention_days' => $days
            ];
            
        } catch (Exception $e) {
            error_log("Clean security logs error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Clean old login attempts
     */
    public function cleanLoginAttempts($days = null) {
        $days = $days ?? $this->getRetentionPeriod('login_attempts');
        
        try {
            $result = $this->db->query(
                "DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
            
            $deleted_count = $this->db->affected_rows ?? 0;
            $this->logCleanupActivity('login_attempts', $deleted_count, $days);
            
            return [
                'success' => true,
                'deleted_count' => $deleted_count,
                'retention_days' => $days
            ];
            
        } catch (Exception $e) {
            error_log("Clean login attempts error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Clean old audit logs
     */
    public function cleanAuditLogs($days = null) {
        $days = $days ?? $this->getRetentionPeriod('audit_logs');
        
        try {
            $result = $this->db->query(
                "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
            
            $deleted_count = $this->db->affected_rows ?? 0;
            $this->logCleanupActivity('audit_logs', $deleted_count, $days);
            
            return [
                'success' => true,
                'deleted_count' => $deleted_count,
                'retention_days' => $days
            ];
            
        } catch (Exception $e) {
            error_log("Clean audit logs error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Clean old email logs
     */
    public function cleanEmailLogs($days = null) {
        $days = $days ?? $this->getRetentionPeriod('email_logs');
        
        try {
            $result = $this->db->query(
                "DELETE FROM email_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
            
            $deleted_count = $this->db->affected_rows ?? 0;
            $this->logCleanupActivity('email_logs', $deleted_count, $days);
            
            return [
                'success' => true,
                'deleted_count' => $deleted_count,
                'retention_days' => $days
            ];
            
        } catch (Exception $e) {
            error_log("Clean email logs error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Clean old patient status logs
     */
    public function cleanPatientStatusLogs($days = null) {
        $days = $days ?? $this->getRetentionPeriod('patient_status_logs');
        
        try {
            $result = $this->db->query(
                "DELETE FROM patient_status_logs WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
            
            $deleted_count = $this->db->affected_rows ?? 0;
            $this->logCleanupActivity('patient_status_logs', $deleted_count, $days);
            
            return [
                'success' => true,
                'deleted_count' => $deleted_count,
                'retention_days' => $days
            ];
            
        } catch (Exception $e) {
            error_log("Clean patient status logs error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Clean PHP error logs (file-based)
     */
    public function cleanErrorLogFiles($days = null) {
        $days = $days ?? $this->getRetentionPeriod('error_logs');
        $deleted_files = 0;
        $errors = [];
        
        try {
            // Common PHP error log locations
            $log_paths = [
                ini_get('error_log'),
                '/var/log/php_errors.log',
                '/var/log/apache2/error.log',
                '/var/log/nginx/error.log',
                __DIR__ . '/../logs/php_error.log',
                __DIR__ . '/../error.log'
            ];
            
            foreach ($log_paths as $log_path) {
                if (empty($log_path) || !file_exists($log_path)) {
                    continue;
                }
                
                try {
                    $file_mtime = filemtime($log_path);
                    $cutoff_time = time() - ($days * 24 * 60 * 60);
                    
                    if ($file_mtime < $cutoff_time) {
                        // Archive old log file instead of deleting completely
                        $archive_name = $log_path . '.old.' . date('Y-m-d', $file_mtime);
                        
                        if (rename($log_path, $archive_name)) {
                            // Create new empty log file
                            touch($log_path);
                            chmod($log_path, 0666);
                            $deleted_files++;
                        }
                    } else {
                        // Rotate large log files
                        $file_size = filesize($log_path);
                        if ($file_size > 50 * 1024 * 1024) { // 50MB
                            $this->rotateLogFile($log_path);
                            $deleted_files++;
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = "Error processing {$log_path}: " . $e->getMessage();
                }
            }
            
            $this->logCleanupActivity('error_log_files', $deleted_files, $days);
            
            return [
                'success' => true,
                'processed_files' => $deleted_files,
                'retention_days' => $days,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            error_log("Clean error log files error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Rotate a log file
     */
    private function rotateLogFile($log_path) {
        $timestamp = date('Y-m-d-H-i-s');
        $rotated_name = $log_path . '.rotated.' . $timestamp;
        
        if (copy($log_path, $rotated_name)) {
            // Truncate original file
            file_put_contents($log_path, '');
            chmod($log_path, 0666);
            return true;
        }
        
        return false;
    }
    
    /**
     * Run comprehensive cleanup for all log types
     */
    public function cleanAllLogs($custom_retention = []) {
        $results = [];
        
        // Merge custom retention with defaults
        $retention = array_merge($this->default_retention, $custom_retention);
        
        // Clean database logs
        $results['security_logs'] = $this->cleanSecurityLogs($retention['security_logs']);
        $results['login_attempts'] = $this->cleanLoginAttempts($retention['login_attempts']);
        $results['audit_logs'] = $this->cleanAuditLogs($retention['audit_logs']);
        $results['email_logs'] = $this->cleanEmailLogs($retention['email_logs']);
        $results['patient_status_logs'] = $this->cleanPatientStatusLogs($retention['patient_status_logs']);
        
        // Clean file-based logs
        $results['error_log_files'] = $this->cleanErrorLogFiles($retention['error_logs']);
        
        // Generate summary
        $total_deleted = 0;
        $total_errors = 0;
        
        foreach ($results as $log_type => $result) {
            if ($result['success']) {
                $total_deleted += $result['deleted_count'] ?? $result['processed_files'] ?? 0;
            } else {
                $total_errors++;
            }
        }
        
        $summary = [
            'success' => $total_errors === 0,
            'total_deleted' => $total_deleted,
            'total_errors' => $total_errors,
            'details' => $results,
            'executed_at' => date('Y-m-d H:i:s')
        ];
        
        // Log the summary
        $this->logCleanupActivity('comprehensive_cleanup', $total_deleted, 0, json_encode($summary));
        
        return $summary;
    }
    
    /**
     * Get log statistics
     */
    public function getLogStatistics() {
        $stats = [];
        
        try {
            // Database log counts
            $log_tables = [
                'security_logs' => 'created_at',
                'login_attempts' => 'created_at', 
                'audit_logs' => 'created_at',
                'email_logs' => 'created_at',
                'patient_status_logs' => 'updated_at'
            ];
            
            foreach ($log_tables as $table => $date_field) {
                try {
                    $result = $this->db->query("SELECT COUNT(*) as total_count FROM {$table}");
                    $total = $result ? $result->fetch_assoc()['total_count'] : 0;
                    
                    $result = $this->db->query(
                        "SELECT COUNT(*) as old_count FROM {$table} 
                         WHERE {$date_field} < DATE_SUB(NOW(), INTERVAL ? DAY)",
                        [$this->getRetentionPeriod(str_replace('_logs', '', $table))]
                    );
                    $old = $result ? $result->fetch_assoc()['old_count'] : 0;
                    
                    $stats[$table] = [
                        'total_records' => $total,
                        'old_records' => $old,
                        'retention_days' => $this->getRetentionPeriod(str_replace('_logs', '', $table))
                    ];
                } catch (Exception $e) {
                    $stats[$table] = ['error' => $e->getMessage()];
                }
            }
            
        } catch (Exception $e) {
            error_log("Get log statistics error: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Log cleanup activity
     */
    private function logCleanupActivity($log_type, $deleted_count, $retention_days, $details = null) {
        try {
            $this->db->query(
                "INSERT INTO audit_logs (user_id, action, table_name, notes, created_at) 
                 VALUES (?, 'log_cleanup', ?, ?, NOW())",
                [
                    $_SESSION['user_id'] ?? 0,
                    $log_type,
                    "Cleaned {$deleted_count} records older than {$retention_days} days" . 
                    ($details ? ". Details: {$details}" : "")
                ]
            );
        } catch (Exception $e) {
            error_log("Log cleanup activity logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Schedule automatic cleanup (to be called via cron)
     */
    public function scheduleCleanup() {
        try {
            // Check if cleanup is enabled
            $result = $this->db->query(
                "SELECT setting_value FROM system_settings 
                 WHERE setting_key = 'auto_log_cleanup_enabled'"
            );
            
            if (!$result || $result->fetch_assoc()['setting_value'] !== 'true') {
                return ['success' => false, 'message' => 'Auto cleanup disabled'];
            }
            
            // Get last cleanup time
            $result = $this->db->query(
                "SELECT setting_value FROM system_settings 
                 WHERE setting_key = 'last_log_cleanup'"
            );
            
            $last_cleanup = $result ? $result->fetch_assoc()['setting_value'] : null;
            $should_run = false;
            
            if (!$last_cleanup) {
                $should_run = true;
            } else {
                $last_time = strtotime($last_cleanup);
                $now = time();
                // Run cleanup every 24 hours
                $should_run = ($now - $last_time) > (24 * 60 * 60);
            }
            
            if ($should_run) {
                $cleanup_result = $this->cleanAllLogs();
                
                // Update last cleanup time
                $this->db->query(
                    "INSERT INTO system_settings (hospital_id, setting_key, setting_value) 
                     VALUES (1, 'last_log_cleanup', NOW()) 
                     ON DUPLICATE KEY UPDATE setting_value = NOW()"
                );
                
                return $cleanup_result;
            }
            
            return ['success' => true, 'message' => 'Cleanup not needed yet'];
            
        } catch (Exception $e) {
            error_log("Schedule log cleanup error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
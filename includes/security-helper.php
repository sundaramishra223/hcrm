<?php
/**
 * Security Helper Functions
 * Enhanced security features for Hospital CRM
 */

class SecurityHelper {
    private $db;
    private $encryption_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->encryption_key = $this->getEncryptionKey();
    }
    
    /**
     * Get encryption key from environment or generate one
     */
    private function getEncryptionKey() {
        $key = getenv('HOSPITAL_CRM_ENCRYPTION_KEY');
        if (!$key) {
            // Generate a secure key if not set
            $key = bin2hex(random_bytes(32));
            // Store in database for persistence
            $this->db->query(
                "INSERT INTO system_settings (hospital_id, setting_key, setting_value, setting_type) 
                 VALUES (1, 'encryption_key', ?, 'security') 
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [$key, $key]
            );
        }
        return $key;
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt($data) {
        try {
            $cipher = "aes-256-gcm";
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
            $tag = "";
            
            $encrypted = openssl_encrypt($data, $cipher, $this->encryption_key, OPENSSL_RAW_DATA, $iv, $tag);
            
            if ($encrypted === false) {
                throw new Exception("Encryption failed");
            }
            
            // Combine IV, encrypted data, and tag
            return base64_encode($iv . $tag . $encrypted);
        } catch (Exception $e) {
            error_log("Encryption error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt($encrypted_data) {
        try {
            $cipher = "aes-256-gcm";
            $ivlen = openssl_cipher_iv_length($cipher);
            $taglen = 16; // GCM tag length
            
            $data = base64_decode($encrypted_data);
            $iv = substr($data, 0, $ivlen);
            $tag = substr($data, $ivlen, $taglen);
            $encrypted = substr($data, $ivlen + $taglen);
            
            $decrypted = openssl_decrypt($encrypted, $cipher, $this->encryption_key, OPENSSL_RAW_DATA, $iv, $tag);
            
            if ($decrypted === false) {
                throw new Exception("Decryption failed");
            }
            
            return $decrypted;
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hash password with salt
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random token
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($user_id, $event_type, $details = '', $ip_address = null) {
        try {
            $ip = $ip_address ?: $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $this->db->query(
                "INSERT INTO security_logs (user_id, event_type, details, ip_address, user_agent, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$user_id, $event_type, $details, $ip, $user_agent]
            );
        } catch (Exception $e) {
            error_log("Security log error: " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    public function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($user_id, $permission) {
        try {
            $result = $this->db->query(
                "SELECT COUNT(*) as count FROM user_permissions up
                 JOIN permissions p ON up.permission_id = p.id
                 WHERE up.user_id = ? AND p.permission_name = ? AND up.is_active = 1",
                [$user_id, $permission]
            )->fetch();
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Permission check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rate limiting for login attempts
     */
    public function checkLoginRateLimit($ip_address) {
        try {
            $time_window = 300; // 5 minutes
            $max_attempts = 5;
            
            $attempts = $this->db->query(
                "SELECT COUNT(*) as count FROM login_attempts 
                 WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$ip_address, $time_window]
            )->fetch()['count'];
            
            return $attempts < $max_attempts;
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return true; // Allow if check fails
        }
    }
    
    /**
     * Record login attempt
     */
    public function recordLoginAttempt($username, $ip_address, $success = false) {
        try {
            $this->db->query(
                "INSERT INTO login_attempts (username, ip_address, success, created_at) 
                 VALUES (?, ?, ?, NOW())",
                [$username, $ip_address, $success ? 1 : 0]
            );
        } catch (Exception $e) {
            error_log("Login attempt record error: " . $e->getMessage());
        }
    }
    
    /**
     * Clean old login attempts
     */
    public function cleanOldLoginAttempts() {
        try {
            $this->db->query(
                "DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            );
        } catch (Exception $e) {
            error_log("Clean login attempts error: " . $e->getMessage());
        }
    }
    
    /**
     * Validate session
     */
    public function validateSession($session_data) {
        try {
            if (!isset($session_data['user_id']) || !isset($session_data['session_token'])) {
                return false;
            }
            
            $result = $this->db->query(
                "SELECT * FROM user_sessions 
                 WHERE user_id = ? AND session_token = ? AND expires_at > NOW() AND is_active = 1",
                [$session_data['user_id'], $session_data['session_token']]
            )->fetch();
            
            return $result !== false;
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create secure session
     */
    public function createSession($user_id) {
        try {
            $session_token = $this->generateToken(64);
            $expires_at = date('Y-m-d H:i:s', strtotime('+8 hours'));
            
            $this->db->query(
                "INSERT INTO user_sessions (user_id, session_token, expires_at, created_at) 
                 VALUES (?, ?, ?, NOW())",
                [$user_id, $session_token, $expires_at]
            );
            
            return $session_token;
        } catch (Exception $e) {
            error_log("Session creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Destroy session
     */
    public function destroySession($user_id, $session_token) {
        try {
            $this->db->query(
                "UPDATE user_sessions SET is_active = 0 WHERE user_id = ? AND session_token = ?",
                [$user_id, $session_token]
            );
        } catch (Exception $e) {
            error_log("Session destruction error: " . $e->getMessage());
        }
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions() {
        try {
            $this->db->query(
                "UPDATE user_sessions SET is_active = 0 WHERE expires_at < NOW()"
            );
        } catch (Exception $e) {
            error_log("Clean sessions error: " . $e->getMessage());
        }
    }
    
    /**
     * Sanitize input data
     */
    public function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email format
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number
     */
    public function validatePhone($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if it's a valid length (10-15 digits)
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
    
    /**
     * Generate secure file name
     */
    public function generateSecureFileName($original_name, $extension = '') {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $ext = $extension ?: pathinfo($original_name, PATHINFO_EXTENSION);
        
        return $timestamp . '_' . $random . '.' . $ext;
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload($file, $allowed_types = [], $max_size = 5242880) {
        $errors = [];
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors[] = "File size exceeds maximum limit of " . ($max_size / 1024 / 1024) . "MB";
        }
        
        // Check file type
        if (!empty($allowed_types)) {
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_types)) {
                $errors[] = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
            }
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error: " . $file['error'];
        }
        
        return $errors;
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $this->generateToken(32);
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Log user activity
     */
    public function logActivity($user_id, $action, $table_name = null, $record_id = null, $details = '') {
        try {
            $this->db->query(
                "INSERT INTO audit_logs (user_id, action, table_name, record_id, notes, ip_address, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$user_id, $action, $table_name, $record_id, $details, $this->getClientIP()]
            );
        } catch (Exception $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
    
    /**
     * Get user activity history
     */
    public function getUserActivityHistory($user_id, $limit = 50) {
        try {
            return $this->db->query(
                "SELECT * FROM audit_logs 
                 WHERE user_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT ?",
                [$user_id, $limit]
            )->fetchAll();
        } catch (Exception $e) {
            error_log("Get user activity error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check for suspicious activity
     */
    public function checkSuspiciousActivity($user_id) {
        try {
            // Check for multiple failed login attempts
            $failed_attempts = $this->db->query(
                "SELECT COUNT(*) as count FROM login_attempts 
                 WHERE username = (SELECT username FROM users WHERE id = ?) 
                 AND success = 0 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                [$user_id]
            )->fetch()['count'];
            
            if ($failed_attempts > 3) {
                $this->logSecurityEvent($user_id, 'suspicious_activity', 'Multiple failed login attempts');
                return true;
            }
            
            // Check for unusual access patterns
            $recent_activity = $this->db->query(
                "SELECT COUNT(*) as count FROM audit_logs 
                 WHERE user_id = ? 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                [$user_id]
            )->fetch()['count'];
            
            if ($recent_activity > 100) {
                $this->logSecurityEvent($user_id, 'suspicious_activity', 'Unusual high activity');
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Suspicious activity check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Block user account
     */
    public function blockUserAccount($user_id, $reason = '') {
        try {
            $this->db->query(
                "UPDATE users SET is_active = 0, blocked_at = NOW(), block_reason = ? WHERE id = ?",
                [$reason, $user_id]
            );
            
            $this->logSecurityEvent($user_id, 'account_blocked', $reason);
        } catch (Exception $e) {
            error_log("Block user account error: " . $e->getMessage());
        }
    }
    
    /**
     * Unblock user account
     */
    public function unblockUserAccount($user_id) {
        try {
            $this->db->query(
                "UPDATE users SET is_active = 1, blocked_at = NULL, block_reason = NULL WHERE id = ?",
                [$user_id]
            );
            
            $this->logSecurityEvent($user_id, 'account_unblocked');
        } catch (Exception $e) {
            error_log("Unblock user account error: " . $e->getMessage());
        }
    }
    
    /**
     * Get security statistics
     */
    public function getSecurityStats($days = 30) {
        try {
            $stats = $this->db->query(
                "SELECT 
                    COUNT(*) as total_events,
                    COUNT(CASE WHEN event_type = 'login_success' THEN 1 END) as successful_logins,
                    COUNT(CASE WHEN event_type = 'login_failed' THEN 1 END) as failed_logins,
                    COUNT(CASE WHEN event_type = 'suspicious_activity' THEN 1 END) as suspicious_events,
                    COUNT(CASE WHEN event_type = 'account_blocked' THEN 1 END) as blocked_accounts
                 FROM security_logs 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            )->fetch();
            
            return $stats;
        } catch (Exception $e) {
            error_log("Get security stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean old security logs
     */
    public function cleanOldSecurityLogs($days = 90) {
        try {
            $this->db->query(
                "DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
        } catch (Exception $e) {
            error_log("Clean security logs error: " . $e->getMessage());
        }
    }
}
?>
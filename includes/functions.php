<?php
// Common functions for the Hospital CRM

/**
 * Show error as popup instead of displaying on page
 */
function showErrorPopup($message) {
    echo "<script>
        alert('Error: " . addslashes($message) . "');
        window.history.back();
    </script>";
}

/**
 * Show success popup
 */
function showSuccessPopup($message, $redirect = null) {
    echo "<script>
        alert('Success: " . addslashes($message) . "');
        " . ($redirect ? "window.location.href = '$redirect';" : "window.history.back();") . "
    </script>";
}

/**
 * Show warning popup
 */
function showWarningPopup($message) {
    echo "<script>
        alert('Warning: " . addslashes($message) . "');
    </script>";
}

/**
 * Show info popup
 */
function showInfoPopup($message) {
    echo "<script>
        alert('Info: " . addslashes($message) . "');
    </script>";
}

/**
 * Safe database query with popup error handling
 */
function safeQuery($db, $sql, $params = []) {
    try {
        return $db->query($sql, $params);
    } catch (Exception $e) {
        showErrorPopup($e->getMessage());
    }
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Format date
 */
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime) {
    return date('M d, Y H:i', strtotime($datetime));
}

/**
 * Get user role display name
 */
function getRoleDisplayName($role) {
    $roles = [
        'admin' => 'Administrator',
        'doctor' => 'Doctor',
        'nurse' => 'Nurse',
        'patient' => 'Patient',
        'receptionist' => 'Receptionist',
        'lab_technician' => 'Lab Technician',
        'pharmacy_staff' => 'Pharmacy Staff',
        'intern_doctor' => 'Intern Doctor',
        'intern_nurse' => 'Intern Nurse',
        'intern_lab' => 'Intern Lab',
        'intern_pharmacy' => 'Intern Pharmacy'
    ];
    
    return $roles[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

/**
 * Check if user has permission
 */
function hasPermission($requiredRole) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // Admin has all permissions
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    return $_SESSION['role'] === $requiredRole;
}

/**
 * Check if user has any of the required roles
 */
function hasAnyRole($requiredRoles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    // Admin has all permissions
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    return in_array($_SESSION['role'], $requiredRoles);
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Log activity
 */
function logActivity($db, $user_id, $action, $details = '') {
    try {
        $sql = "INSERT INTO audit_logs (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $db->query($sql, [
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // Silently fail for logging errors
    }
}
?>
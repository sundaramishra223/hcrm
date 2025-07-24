<?php
/**
 * Hospital CRM Functions
 * Common functions used throughout the application
 */

/**
 * Show error popup message
 */
function showErrorPopup($message) {
    echo "<script>
        alert('Error: " . addslashes($message) . "');
        window.history.back();
    </script>";
}

/**
 * Show success popup message
 */
function showSuccessPopup($message, $redirect = null) {
    echo "<script>
        alert('Success: " . addslashes($message) . "');
        " . ($redirect ? "window.location.href = '$redirect';" : "window.history.back();") . "
    </script>";
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random password
 */
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Format phone number
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 10) {
        return preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $phone);
    }
    return $phone;
}

/**
 * Calculate age from date of birth
 */
function calculateAge($dob) {
    if (!$dob) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age . ' years';
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Get role display name
 */
function getRoleDisplayName($role) {
    $roles = [
        'admin' => 'Administrator',
        'doctor' => 'Doctor',
        'nurse' => 'Nurse',
        'receptionist' => 'Receptionist',
        'pharmacy_staff' => 'Pharmacy Staff',
        'lab_technician' => 'Lab Technician',
        'patient' => 'Patient'
    ];
    return $roles[$role] ?? ucfirst($role);
}

/**
 * Check user permission
 */
function hasPermission($user_role, $required_roles) {
    return in_array($user_role, $required_roles);
}

/**
 * Render dynamic styles
 */
function renderDynamicStyles() {
    echo "<style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #059669;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --info-color: #0891b2;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --border-color: #e2e8f0;
            --text-color: #334155;
            --text-muted: #64748b;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f1f5f9;
            color: var(--text-color);
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .sidebar-header p {
            font-size: 0.875rem;
            opacity: 0.8;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
        }

        .sidebar-menu li {
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: white;
            padding-left: 2rem;
        }

        .sidebar-menu a i {
            margin-right: 0.75rem;
            width: 1.25rem;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark-color);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: var(--light-color);
            border-radius: 0.375rem;
        }

        .user-avatar {
            width: 2rem;
            height: 2rem;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--light-color);
        }

        .card-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: var(--text-muted);
            font-weight: 500;
        }

        .stat-card i {
            margin-right: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #b45309;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-1px);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        .grid-3 {
            grid-template-columns: repeat(3, 1fr);
        }

        .grid-4 {
            grid-template-columns: repeat(4, 1fr);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .table th {
            background: var(--light-color);
            font-weight: 600;
            color: var(--dark-color);
        }

        .table tbody tr:hover {
            background: rgba(37, 99, 235, 0.05);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
        }

        .badge-success {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
        }

        .badge-danger {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
        }

        .badge-warning {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
        }

        .badge-info {
            background: rgba(8, 145, 178, 0.1);
            color: var(--info-color);
        }

        .badge-secondary {
            background: rgba(100, 116, 139, 0.1);
            color: var(--secondary-color);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
        }

        .alert-success {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
            border-color: rgba(5, 150, 105, 0.2);
        }

        .alert-danger {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
            border-color: rgba(220, 38, 38, 0.2);
        }

        .alert-warning {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
            border-color: rgba(217, 119, 6, 0.2);
        }

        .alert-info {
            background: rgba(8, 145, 178, 0.1);
            color: var(--info-color);
            border-color: rgba(8, 145, 178, 0.2);
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: var(--text-muted);
        }

        .mb-3 {
            margin-bottom: 1rem;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .p-3 {
            padding: 1rem;
        }

        .search-box {
            margin-bottom: 1.5rem;
        }

        .search-box .form-control {
            padding-left: 2.5rem;
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.2s ease;
            text-decoration: none;
            color: var(--text-color);
        }

        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            text-decoration: none;
            color: var(--primary-color);
        }

        .quick-action i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .quick-action h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .quick-action p {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .grid-2,
            .grid-3,
            .grid-4 {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 0.875rem;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        .loading {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>";
}

/**
 * Get file icon based on extension
 */
function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => 'fas fa-file-pdf',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image',
        'txt' => 'fas fa-file-alt',
        'zip' => 'fas fa-file-archive',
        'rar' => 'fas fa-file-archive'
    ];
    return $icons[$extension] ?? 'fas fa-file';
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Generate unique ID
 */
function generateUniqueId($prefix = '') {
    return $prefix . uniqid() . mt_rand(1000, 9999);
}

/**
 * Log activity
 */
function logActivity($db, $user_id, $action, $description, $module = 'system') {
    try {
        $db->query(
            "INSERT INTO activity_logs (user_id, action, description, module, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$user_id, $action, $description, $module]
        );
    } catch (Exception $e) {
        // Log error but don't break functionality
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Send notification
 */
function sendNotification($db, $user_id, $title, $message, $type = 'info') {
    try {
        $db->query(
            "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())",
            [$user_id, $title, $message, $type]
        );
    } catch (Exception $e) {
        // Log error but don't break functionality
        error_log("Failed to send notification: " . $e->getMessage());
    }
}
?>
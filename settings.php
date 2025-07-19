<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle settings update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    try {
        $db->getConnection()->beginTransaction();
        
        $settings = [
            'site_title' => $_POST['site_title'] ?? 'Hospital CRM',
            'site_logo' => $_POST['site_logo'] ?? '',
            'site_favicon' => $_POST['site_favicon'] ?? '',
            'primary_color' => $_POST['primary_color'] ?? '#004685',
            'secondary_color' => $_POST['secondary_color'] ?? '#0066cc',
            'enable_dark_mode' => isset($_POST['enable_dark_mode']) ? 'true' : 'false',
            'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? 'true' : 'false',
            'enable_sms_notifications' => isset($_POST['enable_sms_notifications']) ? 'true' : 'false',
            'enable_intern_system' => isset($_POST['enable_intern_system']) ? 'true' : 'false',
            'enable_attendance_system' => isset($_POST['enable_attendance_system']) ? 'true' : 'false',
            'enable_multi_hospital' => isset($_POST['enable_multi_hospital']) ? 'true' : 'false',
            'enable_insurance_claims' => isset($_POST['enable_insurance_claims']) ? 'true' : 'false',
            'enable_ambulance_management' => isset($_POST['enable_ambulance_management']) ? 'true' : 'false',
            'enable_feedback_system' => isset($_POST['enable_feedback_system']) ? 'true' : 'false',
            'enable_home_visits' => isset($_POST['enable_home_visits']) ? 'true' : 'false',
            'enable_video_consultation' => isset($_POST['enable_video_consultation']) ? 'true' : 'false'
        ];
        
        foreach ($settings as $key => $value) {
            $db->query(
                "INSERT INTO system_settings (hospital_id, setting_key, setting_value, setting_type) 
                 VALUES (1, ?, ?, 'string') 
                 ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP",
                [$key, $value, $value]
            );
        }
        
        $db->getConnection()->commit();
        $message = "Settings updated successfully!";
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle role management
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    try {
        $role_id = $_POST['role_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $db->query("UPDATE roles SET is_active = ? WHERE id = ?", [$is_active, $role_id]);
        $message = "Role updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get current settings
$current_settings = [];
try {
    $settings_result = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE hospital_id = 1")->fetchAll();
    foreach ($settings_result as $setting) {
        $current_settings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (Exception $e) {
    $current_settings = [];
}

// Get roles
$roles = [];
try {
    $roles = $db->query("SELECT * FROM roles ORDER BY role_name")->fetchAll();
} catch (Exception $e) {
    $roles = [];
}

// Get departments
$departments = [];
try {
    $departments = $db->query("SELECT * FROM departments WHERE hospital_id = 1 ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $departments = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-hospital"></i> Hospital CRM</h2>
                <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="billing.php"><i class="fas fa-money-bill-wave"></i> Billing</a></li>
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="staff.php"><i class="fas fa-user-nurse"></i> Staff</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>System Settings</h1>
                    <p>Manage system configuration and preferences</p>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                        <i class="fas fa-moon"></i>
                    </button>
                    <div class="user-info">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['role_display']); ?></span>
                    </div>
                    <a href="logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">System Configuration</h3>
                </div>
                <div class="settings-tabs">
                    <button class="tab-btn active" data-tab="general">
                        <i class="fas fa-cog"></i> General
                    </button>
                    <button class="tab-btn" data-tab="appearance">
                        <i class="fas fa-palette"></i> Appearance
                    </button>
                    <button class="tab-btn" data-tab="features">
                        <i class="fas fa-toggle-on"></i> Features
                    </button>
                    <button class="tab-btn" data-tab="roles">
                        <i class="fas fa-users-cog"></i> Roles
                    </button>
                    <button class="tab-btn" data-tab="departments">
                        <i class="fas fa-building"></i> Departments
                    </button>
                    <button class="tab-btn" data-tab="security">
                        <i class="fas fa-shield-alt"></i> Security
                    </button>
                </div>

                <!-- General Settings -->
                <div class="tab-content active" id="general">
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="form-group">
                            <label class="form-label">Site Title</label>
                            <input type="text" name="site_title" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['site_title'] ?? 'Hospital CRM'); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Logo URL</label>
                            <input type="url" name="site_logo" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['site_logo'] ?? ''); ?>"
                                   placeholder="https://example.com/logo.png">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Favicon URL</label>
                            <input type="url" name="site_favicon" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['site_favicon'] ?? ''); ?>"
                                   placeholder="https://example.com/favicon.ico">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save General Settings
                        </button>
                    </form>
                </div>

                <!-- Appearance Settings -->
                <div class="tab-content" id="appearance">
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="form-group">
                            <label class="form-label">Primary Color</label>
                            <input type="color" name="primary_color" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['primary_color'] ?? '#004685'); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Secondary Color</label>
                            <input type="color" name="secondary_color" class="form-control" 
                                   value="<?php echo htmlspecialchars($current_settings['secondary_color'] ?? '#0066cc'); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <input type="checkbox" name="enable_dark_mode" 
                                       <?php echo ($current_settings['enable_dark_mode'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                Enable Dark Mode
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Appearance Settings
                        </button>
                    </form>
                </div>

                <!-- Features Settings -->
                <div class="tab-content" id="features">
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="features-grid">
                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_email_notifications" 
                                           <?php echo ($current_settings['enable_email_notifications'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    Email Notifications
                                </label>
                                <p class="feature-description">Enable email notifications for appointments, bills, etc.</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_sms_notifications" 
                                           <?php echo ($current_settings['enable_sms_notifications'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    SMS Notifications
                                </label>
                                <p class="feature-description">Enable SMS notifications (requires SMS API integration)</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_intern_system" 
                                           <?php echo ($current_settings['enable_intern_system'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    Intern System
                                </label>
                                <p class="feature-description">Enable intern roles for doctors, nurses, lab techs, and pharmacy staff</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_attendance_system" 
                                           <?php echo ($current_settings['enable_attendance_system'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    Attendance System
                                </label>
                                <p class="feature-description">Enable staff attendance tracking and salary management</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_multi_hospital" 
                                           <?php echo ($current_settings['enable_multi_hospital'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    Multi-Hospital System
                                </label>
                                <p class="feature-description">Enable support for multiple hospitals/clinics</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_insurance_claims" 
                                           <?php echo ($current_settings['enable_insurance_claims'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    Insurance Claims
                                </label>
                                <p class="feature-description">Enable insurance claim processing and management</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_ambulance_management" 
                                           <?php echo ($current_settings['enable_ambulance_management'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    Ambulance Management
                                </label>
                                <p class="feature-description">Enable ambulance booking and management system</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_feedback_system" 
                                           <?php echo ($current_settings['enable_feedback_system'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    Feedback System
                                </label>
                                <p class="feature-description">Enable patient and staff feedback collection</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_home_visits" 
                                           <?php echo ($current_settings['enable_home_visits'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    Home Visits
                                </label>
                                <p class="feature-description">Enable doctor home visit appointments</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_video_consultation" 
                                           <?php echo ($current_settings['enable_video_consultation'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    Video Consultation
                                </label>
                                <p class="feature-description">Enable video consultation appointments</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_backup_system" 
                                           <?php echo ($current_settings['enable_backup_system'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    Auto Backup System
                                </label>
                                <p class="feature-description">Enable automatic database backup</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_audit_logging" 
                                           <?php echo ($current_settings['enable_audit_logging'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                    Audit Logging
                                </label>
                                <p class="feature-description">Track all system activities and changes</p>
                            </div>

                            <div class="feature-item">
                                <label class="form-label">
                                    <input type="checkbox" name="enable_two_factor_auth" 
                                           <?php echo ($current_settings['enable_two_factor_auth'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                    Two-Factor Authentication
                                </label>
                                <p class="feature-description">Enable 2FA for enhanced security</p>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Feature Settings
                        </button>
                    </form>
                </div>

                <!-- Roles Management -->
                <div class="tab-content" id="roles">
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Role Name</th>
                                    <th>Display Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($role['role_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($role['role_display_name']); ?></td>
                                    <td><?php echo htmlspecialchars($role['description']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $role['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_role">
                                            <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo $role['is_active'] ? '0' : '1'; ?>">
                                            <button type="submit" class="btn btn-sm <?php echo $role['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                <?php echo $role['is_active'] ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Departments Management -->
                <div class="tab-content" id="departments">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4>Hospital Departments</h4>
                        <button class="btn btn-primary" onclick="addDepartment()">
                            <i class="fas fa-plus"></i> Add Department
                        </button>
                    </div>
                    
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Department Name</th>
                                    <th>Code</th>
                                    <th>Head Doctor</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($dept['name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($dept['code']); ?></td>
                                    <td>
                                        <?php 
                                        if ($dept['head_doctor_id']) {
                                            $head_doctor = $db->query("SELECT CONCAT(first_name, ' ', last_name) as name FROM doctors WHERE id = ?", [$dept['head_doctor_id']])->fetch();
                                            echo htmlspecialchars($head_doctor['name'] ?? 'Not Assigned');
                                        } else {
                                            echo 'Not Assigned';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $dept['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" onclick="editDepartment(<?php echo $dept['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="tab-content" id="security">
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="security-grid">
                            <div class="security-item">
                                <h4>Password Policy</h4>
                                <div class="form-group">
                                    <label class="form-label">Minimum Password Length</label>
                                    <input type="number" name="min_password_length" class="form-control" 
                                           value="<?php echo htmlspecialchars($current_settings['min_password_length'] ?? '8'); ?>" min="6" max="20">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="require_uppercase" 
                                               <?php echo ($current_settings['require_uppercase'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                        Require Uppercase Letters
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="require_numbers" 
                                               <?php echo ($current_settings['require_numbers'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                        Require Numbers
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="require_special_chars" 
                                               <?php echo ($current_settings['require_special_chars'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                        Require Special Characters
                                    </label>
                                </div>
                            </div>

                            <div class="security-item">
                                <h4>Session Management</h4>
                                <div class="form-group">
                                    <label class="form-label">Session Timeout (minutes)</label>
                                    <input type="number" name="session_timeout" class="form-control" 
                                           value="<?php echo htmlspecialchars($current_settings['session_timeout'] ?? '30'); ?>" min="5" max="480">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="force_logout_on_password_change" 
                                               <?php echo ($current_settings['force_logout_on_password_change'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                        Force Logout on Password Change
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="prevent_concurrent_logins" 
                                               <?php echo ($current_settings['prevent_concurrent_logins'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                        Prevent Concurrent Logins
                                    </label>
                                </div>
                            </div>

                            <div class="security-item">
                                <h4>Login Security</h4>
                                <div class="form-group">
                                    <label class="form-label">Maximum Login Attempts</label>
                                    <input type="number" name="max_login_attempts" class="form-control" 
                                           value="<?php echo htmlspecialchars($current_settings['max_login_attempts'] ?? '5'); ?>" min="3" max="10">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Lockout Duration (minutes)</label>
                                    <input type="number" name="lockout_duration" class="form-control" 
                                           value="<?php echo htmlspecialchars($current_settings['lockout_duration'] ?? '15'); ?>" min="5" max="60">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="enable_captcha" 
                                               <?php echo ($current_settings['enable_captcha'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                        Enable CAPTCHA on Login
                                    </label>
                                </div>
                            </div>

                            <div class="security-item">
                                <h4>Data Protection</h4>
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="enable_data_encryption" 
                                               <?php echo ($current_settings['enable_data_encryption'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                        Enable Data Encryption
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="enable_ssl_redirect" 
                                               <?php echo ($current_settings['enable_ssl_redirect'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
                                        Force HTTPS Redirect
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        <input type="checkbox" name="enable_csrf_protection" 
                                               <?php echo ($current_settings['enable_csrf_protection'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
                                        Enable CSRF Protection
                                    </label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Security Settings
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        const icon = themeToggle.querySelector('i');

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }

        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');

        function checkMobile() {
            if (window.innerWidth <= 768) {
                mobileMenuToggle.style.display = 'block';
                sidebar.classList.remove('open');
            } else {
                mobileMenuToggle.style.display = 'none';
                sidebar.classList.remove('open');
            }
        }

        mobileMenuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        window.addEventListener('resize', checkMobile);
        checkMobile();

        // Tab Management
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                
                // Remove active class from all tabs
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab
                btn.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Department Management Functions
        function addDepartment() {
            const name = prompt('Enter department name:');
            if (name) {
                const code = prompt('Enter department code:');
                if (code) {
                    // Here you would typically make an AJAX call to add the department
                    alert('Department added successfully! (This is a demo - implement AJAX call)');
                }
            }
        }

        function editDepartment(id) {
            alert('Edit department ' + id + ' (This is a demo - implement edit functionality)');
        }
    </script>

    <style>
        .settings-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all var(--transition-fast);
            color: var(--text-secondary);
            font-weight: 500;
        }

        .tab-btn:hover {
            background: var(--bg-tertiary);
            color: var(--primary-color);
        }

        .tab-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .settings-form {
            max-width: 600px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .security-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .security-item {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-light);
        }

        .security-item h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }

        .feature-item {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-light);
        }

        .feature-item label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .feature-item input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .feature-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin: 0;
        }

        .mobile-menu-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: none;
        }

        @media (max-width: 768px) {
            .settings-tabs {
                flex-direction: column;
            }
            
            .tab-btn {
                text-align: left;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
</body>
</html>
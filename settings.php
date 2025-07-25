<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if ($user_role !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Handle form submission
if ($_POST) {
    try {
        $settings = [
            'site_title' => $_POST['site_title'] ?? 'Hospital CRM',
            'site_tagline' => $_POST['site_tagline'] ?? 'Complete Hospital Management System',
            'primary_color' => $_POST['primary_color'] ?? '#2c3e50',
            'secondary_color' => $_POST['secondary_color'] ?? '#3498db',
            'accent_color' => $_POST['accent_color'] ?? '#e74c3c',
            'success_color' => $_POST['success_color'] ?? '#27ae60',
            'warning_color' => $_POST['warning_color'] ?? '#f39c12',
            'danger_color' => $_POST['danger_color'] ?? '#e74c3c',
            'hospital_name' => $_POST['hospital_name'] ?? 'City General Hospital',
            'hospital_address' => $_POST['hospital_address'] ?? '',
            'hospital_phone' => $_POST['hospital_phone'] ?? '',
            'hospital_email' => $_POST['hospital_email'] ?? '',
            'emergency_number' => $_POST['emergency_number'] ?? '',
            'website_url' => $_POST['website_url'] ?? '',
            'timezone' => $_POST['timezone'] ?? 'Asia/Kolkata',
            'currency' => $_POST['currency'] ?? 'INR',
            'currency_symbol' => $_POST['currency_symbol'] ?? '₹',
            'date_format' => $_POST['date_format'] ?? 'd/m/Y',
            'time_format' => $_POST['time_format'] ?? 'H:i',
            'language' => $_POST['language'] ?? 'en',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'allow_registration' => isset($_POST['allow_registration']) ? 1 : 0,
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0
        ];

        foreach ($settings as $key => $value) {
            $existing = $db->query("SELECT id FROM settings WHERE setting_key = ?", [$key])->fetch();
            
            if ($existing) {
                $db->query("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?", [$value, $key]);
            } else {
                $db->query("INSERT INTO settings (setting_key, setting_value, created_by) VALUES (?, ?, ?)", [$key, $value, $_SESSION['user_id']]);
            }
        }

        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/settings/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $logoName = 'logo_' . time() . '.' . pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $logoPath = $uploadDir . $logoName;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath)) {
                $existing = $db->query("SELECT id FROM settings WHERE setting_key = 'site_logo'", [])->fetch();
                if ($existing) {
                    $db->query("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = 'site_logo'", [$logoPath]);
                } else {
                    $db->query("INSERT INTO settings (setting_key, setting_value, created_by) VALUES ('site_logo', ?, ?)", [$logoPath, $_SESSION['user_id']]);
                }
            }
        }

        // Handle favicon upload
        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/settings/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $faviconName = 'favicon_' . time() . '.' . pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION);
            $faviconPath = $uploadDir . $faviconName;
            
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $faviconPath)) {
                $existing = $db->query("SELECT id FROM settings WHERE setting_key = 'site_favicon'", [])->fetch();
                if ($existing) {
                    $db->query("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = 'site_favicon'", [$faviconPath]);
                } else {
                    $db->query("INSERT INTO settings (setting_key, setting_value, created_by) VALUES ('site_favicon', ?, ?)", [$faviconPath, $_SESSION['user_id']]);
                }
            }
        }

        $success_message = "Settings updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
try {
    $currentSettings = [];
    $settingsData = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
    foreach ($settingsData as $setting) {
        $currentSettings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (Exception $e) {
    $currentSettings = [];
}

// Default values
$defaults = [
    'site_title' => 'Hospital CRM',
    'site_tagline' => 'Complete Hospital Management System',
    'primary_color' => '#2c3e50',
    'secondary_color' => '#3498db',
    'accent_color' => '#e74c3c',
    'success_color' => '#27ae60',
    'warning_color' => '#f39c12',
    'danger_color' => '#e74c3c',
    'hospital_name' => 'City General Hospital',
    'timezone' => 'Asia/Kolkata',
    'currency' => 'INR',
    'currency_symbol' => '₹',
    'date_format' => 'd/m/Y',
    'time_format' => 'H:i',
    'language' => 'en'
];

function getSetting($key, $default = '') {
    global $currentSettings, $defaults;
    return $currentSettings[$key] ?? $defaults[$key] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo getSetting('site_title'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php if (getSetting('site_favicon')): ?>
        <link rel="icon" type="image/x-icon" href="<?php echo getSetting('site_favicon'); ?>">
    <?php endif; ?>
    <?php renderDynamicStyles(); ?>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>
                    <?php if (getSetting('site_logo')): ?>
                        <img src="<?php echo getSetting('site_logo'); ?>" alt="Logo" style="height: 30px; margin-right: 10px;">
                    <?php else: ?>
                        <i class="fas fa-hospital"></i>
                    <?php endif; ?>
                    <?php echo getSetting('site_title'); ?>
                </h2>
                <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="insurance.php"><i class="fas fa-shield-alt"></i> Insurance</a></li>
                <li><a href="blood-bank.php"><i class="fas fa-tint"></i> Blood Bank</a></li>
                <li><a href="organ-donation.php"><i class="fas fa-heart"></i> Organ Donation</a></li>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php" class="active"><i class="fas fa-cogs"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-cogs"></i> System Settings</h1>
                    <p>Configure your hospital management system</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="settings-form">
                <!-- Website Settings -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-globe"></i> Website Settings</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="site_title">Site Title</label>
                                    <input type="text" id="site_title" name="site_title" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting('site_title')); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="site_tagline">Site Tagline</label>
                                    <input type="text" id="site_tagline" name="site_tagline" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting('site_tagline')); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="logo">Site Logo</label>
                                    <input type="file" id="logo" name="logo" class="form-control" accept="image/*">
                                    <?php if (getSetting('site_logo')): ?>
                                        <div class="current-logo">
                                            <img src="<?php echo getSetting('site_logo'); ?>" alt="Current Logo" style="max-height: 50px; margin-top: 10px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="favicon">Favicon</label>
                                    <input type="file" id="favicon" name="favicon" class="form-control" accept="image/*">
                                    <?php if (getSetting('site_favicon')): ?>
                                        <div class="current-favicon">
                                            <img src="<?php echo getSetting('site_favicon'); ?>" alt="Current Favicon" style="max-height: 32px; margin-top: 10px;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Color Scheme -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-palette"></i> Color Scheme</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="primary_color">Primary Color</label>
                                    <input type="color" id="primary_color" name="primary_color" class="form-control" 
                                           value="<?php echo getSetting('primary_color'); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="secondary_color">Secondary Color</label>
                                    <input type="color" id="secondary_color" name="secondary_color" class="form-control" 
                                           value="<?php echo getSetting('secondary_color'); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="success_color">Success Color</label>
                                    <input type="color" id="success_color" name="success_color" class="form-control" 
                                           value="<?php echo getSetting('success_color'); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="danger_color">Danger Color</label>
                                    <input type="color" id="danger_color" name="danger_color" class="form-control" 
                                           value="<?php echo getSetting('danger_color'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hospital Information -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-hospital"></i> Hospital Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="hospital_name">Hospital Name</label>
                                    <input type="text" id="hospital_name" name="hospital_name" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting('hospital_name')); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="hospital_email">Hospital Email</label>
                                    <input type="email" id="hospital_email" name="hospital_email" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting('hospital_email')); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="hospital_phone">Hospital Phone</label>
                                    <input type="tel" id="hospital_phone" name="hospital_phone" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting('hospital_phone')); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="emergency_number">Emergency Number</label>
                                    <input type="tel" id="emergency_number" name="emergency_number" class="form-control" 
                                           value="<?php echo htmlspecialchars(getSetting('emergency_number')); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="hospital_address">Hospital Address</label>
                            <textarea id="hospital_address" name="hospital_address" class="form-control" rows="3"><?php echo htmlspecialchars(getSetting('hospital_address')); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- System Configuration -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cog"></i> System Configuration</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="timezone">Timezone</label>
                                    <select id="timezone" name="timezone" class="form-control">
                                        <option value="Asia/Kolkata" <?php echo getSetting('timezone') === 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata (IST)</option>
                                        <option value="America/New_York" <?php echo getSetting('timezone') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (EST)</option>
                                        <option value="Europe/London" <?php echo getSetting('timezone') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT)</option>
                                        <option value="Asia/Dubai" <?php echo getSetting('timezone') === 'Asia/Dubai' ? 'selected' : ''; ?>>Asia/Dubai (GST)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="currency">Currency</label>
                                    <select id="currency" name="currency" class="form-control">
                                        <option value="INR" <?php echo getSetting('currency') === 'INR' ? 'selected' : ''; ?>>Indian Rupee (INR)</option>
                                        <option value="USD" <?php echo getSetting('currency') === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                        <option value="EUR" <?php echo getSetting('currency') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                        <option value="GBP" <?php echo getSetting('currency') === 'GBP' ? 'selected' : ''; ?>>British Pound (GBP)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date_format">Date Format</label>
                                    <select id="date_format" name="date_format" class="form-control">
                                        <option value="d/m/Y" <?php echo getSetting('date_format') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                        <option value="m/d/Y" <?php echo getSetting('date_format') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <option value="Y-m-d" <?php echo getSetting('date_format') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="time_format">Time Format</label>
                                    <select id="time_format" name="time_format" class="form-control">
                                        <option value="H:i" <?php echo getSetting('time_format') === 'H:i' ? 'selected' : ''; ?>>24 Hour (HH:MM)</option>
                                        <option value="h:i A" <?php echo getSetting('time_format') === 'h:i A' ? 'selected' : ''; ?>>12 Hour (HH:MM AM/PM)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label">System Options</label>
                                    <div class="checkbox-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="maintenance_mode" value="1" <?php echo getSetting('maintenance_mode') ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                            Maintenance Mode
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="allow_registration" value="1" <?php echo getSetting('allow_registration') ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                            Allow Patient Registration
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="email_notifications" value="1" <?php echo getSetting('email_notifications') ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                            Email Notifications
                                        </label>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="sms_notifications" value="1" <?php echo getSetting('sms_notifications') ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                            SMS Notifications
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </main>
    </div>

    <style>
    .settings-form .card {
        margin-bottom: 20px;
    }

    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -15px;
    }

    .col-md-3 {
        flex: 0 0 25%;
        max-width: 25%;
        padding: 0 15px;
    }

    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
        padding: 0 15px;
    }

    .col-md-12 {
        flex: 0 0 100%;
        max-width: 100%;
        padding: 0 15px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }

    .form-control {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    .form-control:focus {
        outline: none;
        border-color: <?php echo getSetting('primary_color'); ?>;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    .checkbox-group {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        cursor: pointer;
        font-weight: normal;
    }

    .checkbox-label input[type="checkbox"] {
        margin-right: 8px;
    }

    .form-actions {
        margin-top: 30px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        text-align: center;
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid transparent;
        border-radius: 4px;
    }

    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .current-logo, .current-favicon {
        margin-top: 10px;
    }

    @media (max-width: 768px) {
        .col-md-3, .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }

        .checkbox-group {
            flex-direction: column;
            gap: 10px;
        }
    }
    </style>
</body>
</html>
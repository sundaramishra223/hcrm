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

// Handle form submission
if ($_POST) {
    try {
        $update_sql = "UPDATE hospitals SET 
            name = ?, 
            site_title = ?, 
            logo_url = ?, 
            favicon_url = ?, 
            primary_color = ?, 
            secondary_color = ?,
            phone = ?,
            email = ?,
            address = ?
            WHERE id = 1";
            
        $db->query($update_sql, [
            $_POST['hospital_name'],
            $_POST['site_title'],
            $_POST['logo_url'],
            $_POST['favicon_url'],
            $_POST['primary_color'],
            $_POST['secondary_color'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['address']
        ]);
        
        $message = "Site settings updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get current settings
$settings = $db->query("SELECT * FROM hospitals WHERE id = 1")->fetch();
if (!$settings) {
    // Create default hospital record if it doesn't exist
    $db->query("INSERT INTO hospitals (id, name, site_title, primary_color, secondary_color) VALUES (1, 'MediCare Hospital', 'MediCare Hospital - Advanced Healthcare Management', '#2563eb', '#10b981')");
    $settings = $db->query("SELECT * FROM hospitals WHERE id = 1")->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    include_once 'includes/site-config.php';
    renderSiteHead('Site Settings');
    ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
    <style>
        .site-settings {
            padding: 20px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }
        
        .page-header {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
        }
        
        .settings-form {
            background: var(--bg-card);
            padding: 30px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            max-width: 800px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-card);
            color: var(--text-primary);
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .color-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .color-input-group input[type="color"] {
            width: 50px;
            height: 40px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .color-input-group input[type="text"] {
            flex: 1;
        }
        
        .preview-section {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .preview-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-primary);
        }
        
        .color-preview {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .color-item {
            text-align: center;
        }
        
        .color-box {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            margin-bottom: 5px;
            border: 2px solid var(--border-color);
        }
        
        .color-label {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-right: 10px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .color-preview {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="site-settings">
                <div class="page-header">
                    <h1><i class="fas fa-cogs"></i> Site Settings</h1>
                    <p>Customize your hospital management system appearance and branding</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="settings-form">
                    <!-- Hospital Information -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-hospital"></i>
                            Hospital Information
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="hospital_name">Hospital Name</label>
                                <input type="text" id="hospital_name" name="hospital_name" value="<?php echo htmlspecialchars($settings['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="site_title">Site Title</label>
                                <input type="text" id="site_title" name="site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>" placeholder="Appears in browser tab">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($settings['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Visual Branding -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-palette"></i>
                            Visual Branding
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="logo_url">Logo URL</label>
                                <input type="text" id="logo_url" name="logo_url" value="<?php echo htmlspecialchars($settings['logo_url'] ?? ''); ?>" placeholder="assets/images/logo.svg">
                            </div>
                            <div class="form-group">
                                <label for="favicon_url">Favicon URL</label>
                                <input type="text" id="favicon_url" name="favicon_url" value="<?php echo htmlspecialchars($settings['favicon_url'] ?? ''); ?>" placeholder="assets/images/favicon.ico">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Color Scheme -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-fill-drip"></i>
                            Color Scheme
                        </h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="primary_color">Primary Color</label>
                                <div class="color-input-group">
                                    <input type="color" id="primary_color" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#2563eb'); ?>" onchange="updateColorPreview()">
                                    <input type="text" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#2563eb'); ?>" onchange="updateColorFromText(this, 'primary_color')">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="secondary_color">Secondary Color</label>
                                <div class="color-input-group">
                                    <input type="color" id="secondary_color" name="secondary_color" value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#10b981'); ?>" onchange="updateColorPreview()">
                                    <input type="text" value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#10b981'); ?>" onchange="updateColorFromText(this, 'secondary_color')">
                                </div>
                            </div>
                        </div>
                        
                        <div class="preview-section">
                            <div class="preview-title">Color Preview</div>
                            <div class="color-preview">
                                <div class="color-item">
                                    <div class="color-box" id="primary-preview" style="background: <?php echo htmlspecialchars($settings['primary_color'] ?? '#2563eb'); ?>"></div>
                                    <div class="color-label">Primary</div>
                                </div>
                                <div class="color-item">
                                    <div class="color-box" id="secondary-preview" style="background: <?php echo htmlspecialchars($settings['secondary_color'] ?? '#10b981'); ?>"></div>
                                    <div class="color-label">Secondary</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 30px;">
                        <button type="button" onclick="location.reload()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
    
    <script>
        function updateColorPreview() {
            const primaryColor = document.getElementById('primary_color').value;
            const secondaryColor = document.getElementById('secondary_color').value;
            
            document.getElementById('primary-preview').style.background = primaryColor;
            document.getElementById('secondary-preview').style.background = secondaryColor;
            
            // Update text inputs
            document.querySelector('#primary_color').nextElementSibling.value = primaryColor;
            document.querySelector('#secondary_color').nextElementSibling.value = secondaryColor;
            
            // Live preview - update CSS variables
            document.documentElement.style.setProperty('--primary-color', primaryColor);
            document.documentElement.style.setProperty('--secondary-color', secondaryColor);
        }
        
        function updateColorFromText(textInput, colorInputId) {
            const colorValue = textInput.value;
            if (/^#[0-9A-F]{6}$/i.test(colorValue)) {
                document.getElementById(colorInputId).value = colorValue;
                updateColorPreview();
            }
        }
        
        // Initialize color sync
        document.addEventListener('DOMContentLoaded', function() {
            updateColorPreview();
        });
    </script>
</body>
</html>
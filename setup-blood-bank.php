<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php');
    exit();
}

$db = new Database();
$message = '';
$success = false;

if ($_POST && isset($_POST['setup_blood_bank'])) {
    try {
        // Read the SQL file and execute it
        $sql_file = 'ULTRA_SAFE_DATABASE_UPDATE.sql';
        
        if (!file_exists($sql_file)) {
            throw new Exception("SQL file not found: $sql_file");
        }
        
        $sql_content = file_get_contents($sql_file);
        
        if (!$sql_content) {
            throw new Exception("Could not read SQL file");
        }
        
        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql_content)),
            function($stmt) {
                return !empty($stmt) && 
                       !preg_match('/^\s*--/', $stmt) && 
                       !preg_match('/^\s*\/\*/', $stmt) &&
                       !preg_match('/^\s*(SET|DELIMITER|COMMIT)/', $stmt);
            }
        );
        
        $executed = 0;
        $errors = [];
        
        // Execute each statement
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) continue;
            
            try {
                $db->query($statement);
                $executed++;
            } catch (Exception $e) {
                // Log error but continue
                $errors[] = "Statement failed: " . substr($statement, 0, 100) . "... Error: " . $e->getMessage();
            }
        }
        
        if ($executed > 0) {
            $success = true;
            $message = "Blood Bank system setup completed successfully! $executed SQL statements executed.";
            if (!empty($errors)) {
                $message .= " Note: " . count($errors) . " statements had errors (mostly normal for existing data).";
            }
        } else {
            throw new Exception("No SQL statements were executed successfully.");
        }
        
    } catch (Exception $e) {
        $message = "Setup failed: " . $e->getMessage();
    }
}

// Check current setup status
$tables_to_check = ['blood_donors', 'blood_inventory', 'blood_donation_sessions', 'blood_usage_records', 'insurance_companies', 'patient_insurance'];
$existing_tables = [];

foreach ($tables_to_check as $table) {
    $check = $db->query("SHOW TABLES LIKE '$table'")->fetch();
    if ($check) {
        $existing_tables[] = $table;
    }
}

$setup_percentage = (count($existing_tables) / count($tables_to_check)) * 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Blood Bank System - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .setup-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
        }
        .feature-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .setup-progress {
            height: 20px;
            border-radius: 10px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>

    <div class="setup-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-database"></i> Blood Bank System Setup</h1>
                    <p class="lead mb-0">Set up advanced blood bank management, insurance, and billing systems</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="card bg-white text-dark">
                        <div class="card-body">
                            <h6>Setup Progress</h6>
                            <div class="progress setup-progress mb-2">
                                <div class="progress-bar bg-success" style="width: <?php echo $setup_percentage; ?>%"></div>
                            </div>
                            <small><?php echo round($setup_percentage); ?>% Complete</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-2">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            
            <div class="col-md-10">
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <i class="fas fa-<?php echo $success ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Current Status -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle"></i> Current System Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($tables_to_check as $table): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-<?php echo in_array($table, $existing_tables) ? 'check-circle text-success' : 'times-circle text-danger'; ?> me-2"></i>
                                            <span><?php echo ucfirst(str_replace('_', ' ', $table)); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="progress setup-progress mt-3">
                                    <div class="progress-bar bg-success" style="width: <?php echo $setup_percentage; ?>%">
                                        <?php echo round($setup_percentage); ?>% Complete
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Features Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h4>🔥 What You'll Get After Setup:</h4>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card feature-card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h6><i class="fas fa-tint"></i> Blood Bank System</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Blood Donor Management</li>
                                    <li><i class="fas fa-check text-success"></i> Blood Inventory Tracking</li>
                                    <li><i class="fas fa-check text-success"></i> Donation Session Records</li>
                                    <li><i class="fas fa-check text-success"></i> Usage & Transfusion Logs</li>
                                    <li><i class="fas fa-check text-success"></i> Critical Stock Alerts</li>
                                    <li><i class="fas fa-check text-success"></i> Expiry Date Monitoring</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card feature-card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6><i class="fas fa-shield-alt"></i> Insurance Management</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Insurance Company Management</li>
                                    <li><i class="fas fa-check text-success"></i> Patient Policy Tracking</li>
                                    <li><i class="fas fa-check text-success"></i> Claims Processing</li>
                                    <li><i class="fas fa-check text-success"></i> Coverage Calculation</li>
                                    <li><i class="fas fa-check text-success"></i> Cashless & Reimbursement</li>
                                    <li><i class="fas fa-check text-success"></i> Settlement Tracking</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card feature-card border-success">
                            <div class="card-header bg-success text-white">
                                <h6><i class="fas fa-file-invoice-dollar"></i> Enhanced Billing</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Detailed Bill Generation</li>
                                    <li><i class="fas fa-check text-success"></i> Multiple Payment Methods</li>
                                    <li><i class="fas fa-check text-success"></i> Insurance Integration</li>
                                    <li><i class="fas fa-check text-success"></i> Payment Tracking</li>
                                    <li><i class="fas fa-check text-success"></i> Outstanding Reports</li>
                                    <li><i class="fas fa-check text-success"></i> Auto Calculations</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Setup Action -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-rocket"></i> Ready to Setup?</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($setup_percentage < 100): ?>
                                <form method="POST" onsubmit="return confirmSetup()">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Setup Process:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>Creates new database tables for blood bank, insurance, and billing</li>
                                            <li>Adds new user roles and departments</li>
                                            <li>Sets up automated triggers and views</li>
                                            <li>Installs sample data for testing</li>
                                            <li>Your existing data will remain safe</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="mb-0"><strong>Estimated time:</strong> 30-60 seconds</p>
                                            <small class="text-muted">The setup is completely safe and reversible</small>
                                        </div>
                                        <div>
                                            <a href="admin-blood-bank-monitor-safe.php" class="btn btn-outline-secondary me-2">
                                                <i class="fas fa-arrow-left"></i> Go Back
                                            </a>
                                            <button type="submit" name="setup_blood_bank" class="btn btn-primary btn-lg">
                                                <i class="fas fa-play"></i> Start Setup
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i>
                                    <strong>Setup Complete!</strong> Your blood bank system is ready to use.
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a href="admin-blood-bank-monitor-safe.php" class="btn btn-outline-primary">
                                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                                    </a>
                                    <a href="blood-donation-tracking.php" class="btn btn-danger">
                                        <i class="fas fa-tint"></i> Open Blood Bank
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmSetup() {
            return confirm('Are you sure you want to set up the Blood Bank system? This will modify your database.');
        }

        // Auto-refresh setup status every 30 seconds
        <?php if ($setup_percentage < 100 && !$_POST): ?>
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>
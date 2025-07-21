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

// Check if blood bank tables exist, if not show setup message
$table_check = $db->query("SHOW TABLES LIKE 'blood_donors'")->fetch();
$blood_bank_setup = !empty($table_check);

// Get basic hospital statistics (always available)
$basic_stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM patients) as total_patients,
        (SELECT COUNT(*) FROM doctors) as active_doctors,
        (SELECT COUNT(*) FROM staff) as active_staff,
        (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()) as today_appointments,
        (SELECT COUNT(*) FROM bills WHERE payment_status = 'pending') as pending_bills,
        (SELECT IFNULL(SUM(balance_amount), 0) FROM bills WHERE payment_status != 'paid') as outstanding_amount
";
$basic_stats = $db->query($basic_stats_query)->fetch();

$blood_stats = [];
$recent_donations = [];
$pending_requests = [];
$critical_stock = [];
$expiring_blood = [];
$inventory_data = [];

// Only run blood bank queries if tables exist
if ($blood_bank_setup) {
    try {
        // Get blood bank statistics
        $blood_stats_query = "
            SELECT 
                (SELECT IFNULL(COUNT(*), 0) FROM blood_donors WHERE is_active = 1) as active_donors,
                (SELECT IFNULL(COUNT(*), 0) FROM blood_inventory WHERE status = 'available') as available_units,
                (SELECT IFNULL(COUNT(*), 0) FROM blood_donation_sessions WHERE DATE(collection_date) = CURDATE()) as today_donations,
                (SELECT IFNULL(COUNT(*), 0) FROM blood_usage_records WHERE DATE(usage_date) = CURDATE()) as today_usage,
                (SELECT IFNULL(COUNT(*), 0) FROM blood_requests WHERE status = 'pending') as pending_requests,
                (SELECT IFNULL(COUNT(*), 0) FROM blood_inventory WHERE status = 'available' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)) as expiring_soon
        ";
        $blood_stats = $db->query($blood_stats_query)->fetch();

        // Get blood inventory data from view (if exists)
        $view_check = $db->query("SHOW TABLES LIKE 'blood_inventory_dashboard'")->fetch();
        if (!empty($view_check)) {
            $inventory_query = "SELECT * FROM blood_inventory_dashboard ORDER BY blood_group, component_type";
            $inventory_data = $db->query($inventory_query)->fetchAll();
        } else {
            // Fallback to direct table query
            $inventory_query = "
                SELECT 
                    blood_group,
                    component_type,
                    COUNT(*) as total_units,
                    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_units,
                    SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_units,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_units,
                    SUM(CASE WHEN status = 'quarantine' THEN 1 ELSE 0 END) as quarantine_units
                FROM blood_inventory 
                GROUP BY blood_group, component_type
                ORDER BY blood_group, component_type
            ";
            $inventory_data = $db->query($inventory_query)->fetchAll();
        }

        // Get critical stock alerts
        $critical_stock_query = "
            SELECT blood_group, component_type, COUNT(*) as available_units
            FROM blood_inventory 
            WHERE status = 'available' AND expiry_date > CURDATE()
            GROUP BY blood_group, component_type
            HAVING COUNT(*) < 5
            ORDER BY COUNT(*) ASC
        ";
        $critical_stock = $db->query($critical_stock_query)->fetchAll();

        // Get expiring blood
        $expiring_blood_query = "
            SELECT bag_number, blood_group, component_type, volume_ml, expiry_date,
                   DATEDIFF(expiry_date, CURDATE()) as days_remaining
            FROM blood_inventory 
            WHERE status = 'available' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY expiry_date ASC
            LIMIT 10
        ";
        $expiring_blood = $db->query($expiring_blood_query)->fetchAll();

        // Get recent donations (safe query)
        $recent_donations_query = "
            SELECT bds.*, bd.donor_id, 
                   CONCAT(IFNULL(p.first_name, 'Unknown'), ' ', IFNULL(p.last_name, '')) as donor_name,
                   CONCAT(IFNULL(u.first_name, 'System'), ' ', IFNULL(u.last_name, '')) as collected_by_name
            FROM blood_donation_sessions bds
            LEFT JOIN blood_donors bd ON bds.donor_id = bd.id
            LEFT JOIN patients p ON bd.patient_id = p.id
            LEFT JOIN users u ON bds.collected_by = u.id
            WHERE bds.collection_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY bds.collection_date DESC
            LIMIT 10
        ";
        $recent_donations = $db->query($recent_donations_query)->fetchAll();

        // Get pending blood requests
        $pending_requests_query = "
            SELECT br.*, 
                   CONCAT(IFNULL(p.first_name, 'Unknown'), ' ', IFNULL(p.last_name, '')) as patient_name,
                   CONCAT(IFNULL(u.first_name, 'System'), ' ', IFNULL(u.last_name, '')) as requested_by_name
            FROM blood_requests br
            LEFT JOIN patients p ON br.patient_id = p.id
            LEFT JOIN users u ON br.requested_by = u.id
            WHERE br.status = 'pending'
            ORDER BY br.urgency_level DESC, br.requested_date ASC
            LIMIT 10
        ";
        $pending_requests = $db->query($pending_requests_query)->fetchAll();

    } catch (Exception $e) {
        // If any blood bank query fails, set flag to false
        $blood_bank_setup = false;
        error_log("Blood bank query error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hospital Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .blood-type-badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: bold;
            color: white;
        }
        .blood-A { background-color: #dc3545; }
        .blood-B { background-color: #007bff; }
        .blood-AB { background-color: #28a745; }
        .blood-O { background-color: #ffc107; color: #000; }
        .urgency-emergency { background-color: #dc3545; color: white; }
        .urgency-urgent { background-color: #fd7e14; color: white; }
        .urgency-routine { background-color: #6c757d; color: white; }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .setup-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/header.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-2">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            
            <div class="col-md-10">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-tachometer-alt text-primary"></i> Admin Dashboard</h2>
                    <div>
                        <button class="btn btn-outline-primary" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <?php if ($blood_bank_setup): ?>
                        <a href="blood-donation-tracking.php" class="btn btn-danger">
                            <i class="fas fa-tint"></i> Blood Bank
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$blood_bank_setup): ?>
                <!-- Blood Bank Setup Banner -->
                <div class="setup-banner">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4><i class="fas fa-plus-circle"></i> Setup Blood Bank System</h4>
                            <p class="mb-0">Your blood bank system is not set up yet. Run the database update to enable blood bank monitoring.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-light btn-lg" onclick="setupBloodBank()">
                                <i class="fas fa-database"></i> Setup Now
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Basic Hospital Statistics -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5><i class="fas fa-hospital"></i> Hospital Overview</h5>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h4 class="text-primary"><?php echo $basic_stats['total_patients'] ?? 0; ?></h4>
                                <small class="text-muted">Total Patients</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-success">
                            <div class="card-body text-center">
                                <i class="fas fa-user-md fa-2x text-success mb-2"></i>
                                <h4 class="text-success"><?php echo $basic_stats['active_doctors'] ?? 0; ?></h4>
                                <small class="text-muted">Active Doctors</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-info">
                            <div class="card-body text-center">
                                <i class="fas fa-user-nurse fa-2x text-info mb-2"></i>
                                <h4 class="text-info"><?php echo $basic_stats['active_staff'] ?? 0; ?></h4>
                                <small class="text-muted">Active Staff</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar fa-2x text-warning mb-2"></i>
                                <h4 class="text-warning"><?php echo $basic_stats['today_appointments'] ?? 0; ?></h4>
                                <small class="text-muted">Today's Appointments</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-danger">
                            <div class="card-body text-center">
                                <i class="fas fa-file-invoice fa-2x text-danger mb-2"></i>
                                <h4 class="text-danger"><?php echo $basic_stats['pending_bills'] ?? 0; ?></h4>
                                <small class="text-muted">Pending Bills</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-dark">
                            <div class="card-body text-center">
                                <i class="fas fa-rupee-sign fa-2x text-dark mb-2"></i>
                                <h4 class="text-dark">₹<?php echo number_format($basic_stats['outstanding_amount'] ?? 0, 0); ?></h4>
                                <small class="text-muted">Outstanding Amount</small>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($blood_bank_setup && !empty($blood_stats)): ?>
                <!-- Blood Bank Statistics -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5><i class="fas fa-tint text-danger"></i> Blood Bank Overview</h5>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-heart fa-2x text-primary mb-2"></i>
                                <h4 class="text-primary"><?php echo $blood_stats['active_donors'] ?? 0; ?></h4>
                                <small class="text-muted">Active Donors</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-success">
                            <div class="card-body text-center">
                                <i class="fas fa-vial fa-2x text-success mb-2"></i>
                                <h4 class="text-success"><?php echo $blood_stats['available_units'] ?? 0; ?></h4>
                                <small class="text-muted">Available Units</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-info">
                            <div class="card-body text-center">
                                <i class="fas fa-plus fa-2x text-info mb-2"></i>
                                <h4 class="text-info"><?php echo $blood_stats['today_donations'] ?? 0; ?></h4>
                                <small class="text-muted">Today's Donations</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-minus fa-2x text-warning mb-2"></i>
                                <h4 class="text-warning"><?php echo $blood_stats['today_usage'] ?? 0; ?></h4>
                                <small class="text-muted">Today's Usage</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-danger">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x text-danger mb-2"></i>
                                <h4 class="text-danger"><?php echo $blood_stats['pending_requests'] ?? 0; ?></h4>
                                <small class="text-muted">Pending Requests</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-secondary">
                            <div class="card-body text-center">
                                <i class="fas fa-exclamation-triangle fa-2x text-secondary mb-2"></i>
                                <h4 class="text-secondary"><?php echo $blood_stats['expiring_soon'] ?? 0; ?></h4>
                                <small class="text-muted">Expiring Soon</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critical Alerts -->
                <?php if (!empty($critical_stock) || !empty($expiring_blood)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5><i class="fas fa-exclamation-triangle"></i> Blood Bank Alerts</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if (!empty($critical_stock)): ?>
                                    <div class="col-md-6">
                                        <h6 class="text-danger">Critical Stock Levels</h6>
                                        <?php foreach ($critical_stock as $stock): ?>
                                        <div class="alert alert-danger p-2 mb-2">
                                            <strong><?php echo $stock['blood_group']; ?> - <?php echo ucfirst(str_replace('_', ' ', $stock['component_type'])); ?></strong>
                                            <span class="badge bg-danger ms-2"><?php echo $stock['available_units']; ?> units</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($expiring_blood)): ?>
                                    <div class="col-md-6">
                                        <h6 class="text-warning">Expiring Soon</h6>
                                        <?php foreach ($expiring_blood as $blood): ?>
                                        <div class="alert alert-warning p-2 mb-2">
                                            <strong><?php echo $blood['bag_number']; ?></strong> - <?php echo $blood['blood_group']; ?>
                                            <span class="badge bg-warning text-dark ms-2"><?php echo $blood['days_remaining']; ?> days</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Blood Inventory (if available) -->
                    <?php if (!empty($inventory_data)): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar"></i> Blood Inventory</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Blood Group</th>
                                                <th>Component</th>
                                                <th>Available</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inventory_data as $item): ?>
                                            <tr>
                                                <td>
                                                    <span class="blood-type-badge blood-<?php echo substr($item['blood_group'], 0, -1); ?>">
                                                        <?php echo $item['blood_group']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $item['component_type'])); ?></td>
                                                <td><strong><?php echo $item['available_units']; ?></strong></td>
                                                <td>
                                                    <?php if ($item['available_units'] < 5): ?>
                                                        <span class="badge bg-danger">Critical</span>
                                                    <?php elseif ($item['available_units'] < 10): ?>
                                                        <span class="badge bg-warning">Low</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Good</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Pending Requests (if available) -->
                    <?php if (!empty($pending_requests)): ?>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-list"></i> Pending Blood Requests</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Request#</th>
                                                <th>Patient</th>
                                                <th>Blood Type</th>
                                                <th>Units</th>
                                                <th>Urgency</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_requests as $request): ?>
                                            <tr>
                                                <td><?php echo $request['request_number']; ?></td>
                                                <td><?php echo $request['patient_name']; ?></td>
                                                <td>
                                                    <span class="blood-type-badge blood-<?php echo substr($request['blood_group'], 0, -1); ?>">
                                                        <?php echo $request['blood_group']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $request['units_needed']; ?></td>
                                                <td>
                                                    <span class="badge urgency-<?php echo $request['urgency_level']; ?>">
                                                        <?php echo ucfirst($request['urgency_level']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <a href="patients.php" class="btn btn-outline-primary btn-lg w-100 mb-2">
                                            <i class="fas fa-user-plus"></i><br>
                                            <small>Add Patient</small>
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="appointments.php" class="btn btn-outline-success btn-lg w-100 mb-2">
                                            <i class="fas fa-calendar-plus"></i><br>
                                            <small>Book Appointment</small>
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <a href="billing.php" class="btn btn-outline-warning btn-lg w-100 mb-2">
                                            <i class="fas fa-file-invoice"></i><br>
                                            <small>Generate Bill</small>
                                        </a>
                                    </div>
                                    <div class="col-md-3">
                                        <?php if ($blood_bank_setup): ?>
                                        <a href="blood-donation-tracking.php" class="btn btn-outline-danger btn-lg w-100 mb-2">
                                            <i class="fas fa-tint"></i><br>
                                            <small>Blood Bank</small>
                                        </a>
                                        <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-lg w-100 mb-2" onclick="setupBloodBank()">
                                            <i class="fas fa-database"></i><br>
                                            <small>Setup Blood Bank</small>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setupBloodBank() {
            if (confirm('Do you want to set up the Blood Bank system? This will create the necessary database tables.')) {
                window.location.href = 'setup-blood-bank.php';
            }
        }

        // Auto-refresh every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);

        <?php if (!empty($critical_stock)): ?>
        // Show critical alerts
        setTimeout(function() {
            alert('⚠️ CRITICAL ALERT: Low blood stock detected!');
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>
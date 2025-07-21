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

// Get blood inventory dashboard data
$inventory_query = "SELECT * FROM blood_inventory_dashboard ORDER BY blood_group, component_type";
$inventory_data = $db->query($inventory_query)->fetchAll();

// Get critical stock alerts (less than 5 units)
$critical_stock_query = "
    SELECT blood_group, component_type, COUNT(*) as available_units
    FROM blood_inventory 
    WHERE status = 'available' AND expiry_date > CURDATE()
    GROUP BY blood_group, component_type
    HAVING COUNT(*) < 5
    ORDER BY COUNT(*) ASC
";
$critical_stock = $db->query($critical_stock_query)->fetchAll();

// Get expiring blood (expires within 7 days)
$expiring_blood_query = "
    SELECT bag_number, blood_group, component_type, volume_ml, expiry_date,
           DATEDIFF(expiry_date, CURDATE()) as days_remaining
    FROM blood_inventory 
    WHERE status = 'available' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY expiry_date ASC
";
$expiring_blood = $db->query($expiring_blood_query)->fetchAll();

// Get recent donations (last 7 days)
$recent_donations_query = "
    SELECT bds.*, bd.donor_id, CONCAT(p.first_name, ' ', p.last_name) as donor_name,
           CONCAT(u.first_name, ' ', u.last_name) as collected_by_name
    FROM blood_donation_sessions bds
    JOIN blood_donors bd ON bds.donor_id = bd.id
    JOIN patients p ON bd.patient_id = p.id
    JOIN users u ON bds.collected_by = u.id
    WHERE bds.collection_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY bds.collection_date DESC
    LIMIT 10
";
$recent_donations = $db->query($recent_donations_query)->fetchAll();

// Get pending blood requests
$pending_requests_query = "
    SELECT br.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
           CONCAT(u.first_name, ' ', u.last_name) as requested_by_name
    FROM blood_requests br
    JOIN patients p ON br.patient_id = p.id
    JOIN users u ON br.requested_by = u.id
    WHERE br.status = 'pending'
    ORDER BY br.urgency_level DESC, br.requested_date ASC
";
$pending_requests = $db->query($pending_requests_query)->fetchAll();

// Get blood bank statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM blood_donors WHERE is_active = 1) as active_donors,
        (SELECT COUNT(*) FROM blood_inventory WHERE status = 'available') as available_units,
        (SELECT COUNT(*) FROM blood_donation_sessions WHERE DATE(collection_date) = CURDATE()) as today_donations,
        (SELECT COUNT(*) FROM blood_usage_records WHERE DATE(usage_date) = CURDATE()) as today_usage,
        (SELECT COUNT(*) FROM blood_requests WHERE status = 'pending') as pending_requests,
        (SELECT COUNT(*) FROM blood_inventory WHERE status = 'available' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)) as expiring_soon
";
$stats = $db->query($stats_query)->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Monitoring - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .blood-group-card { border-left: 4px solid #dc3545; }
        .critical-alert { border-color: #dc3545 !important; background-color: #fff5f5; }
        .warning-alert { border-color: #ffc107 !important; background-color: #fffbf0; }
        .success-alert { border-color: #28a745 !important; background-color: #f8fff9; }
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
                    <h2><i class="fas fa-tint text-danger"></i> Blood Bank Monitoring Dashboard</h2>
                    <div>
                        <button class="btn btn-outline-primary" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh Data
                        </button>
                        <a href="blood-donation-tracking.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Donation
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card stat-card border-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                <h4 class="text-primary"><?php echo $stats['active_donors']; ?></h4>
                                <small class="text-muted">Active Donors</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-success">
                            <div class="card-body text-center">
                                <i class="fas fa-vial fa-2x text-success mb-2"></i>
                                <h4 class="text-success"><?php echo $stats['available_units']; ?></h4>
                                <small class="text-muted">Available Units</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-info">
                            <div class="card-body text-center">
                                <i class="fas fa-plus fa-2x text-info mb-2"></i>
                                <h4 class="text-info"><?php echo $stats['today_donations']; ?></h4>
                                <small class="text-muted">Today's Donations</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-minus fa-2x text-warning mb-2"></i>
                                <h4 class="text-warning"><?php echo $stats['today_usage']; ?></h4>
                                <small class="text-muted">Today's Usage</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-danger">
                            <div class="card-body text-center">
                                <i class="fas fa-clock fa-2x text-danger mb-2"></i>
                                <h4 class="text-danger"><?php echo $stats['pending_requests']; ?></h4>
                                <small class="text-muted">Pending Requests</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card border-secondary">
                            <div class="card-body text-center">
                                <i class="fas fa-exclamation-triangle fa-2x text-secondary mb-2"></i>
                                <h4 class="text-secondary"><?php echo $stats['expiring_soon']; ?></h4>
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
                                <h5><i class="fas fa-exclamation-triangle"></i> Critical Alerts</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if (!empty($critical_stock)): ?>
                                    <div class="col-md-6">
                                        <h6 class="text-danger">Critical Stock Levels (< 5 units)</h6>
                                        <?php foreach ($critical_stock as $stock): ?>
                                        <div class="alert alert-danger p-2 mb-2">
                                            <strong><?php echo $stock['blood_group']; ?> - <?php echo ucfirst(str_replace('_', ' ', $stock['component_type'])); ?></strong>
                                            <span class="badge bg-danger ms-2"><?php echo $stock['available_units']; ?> units left</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($expiring_blood)): ?>
                                    <div class="col-md-6">
                                        <h6 class="text-warning">Expiring Within 7 Days</h6>
                                        <?php foreach ($expiring_blood as $blood): ?>
                                        <div class="alert alert-warning p-2 mb-2">
                                            <strong><?php echo $blood['bag_number']; ?></strong> - <?php echo $blood['blood_group']; ?>
                                            <span class="badge bg-warning text-dark ms-2"><?php echo $blood['days_remaining']; ?> days left</span>
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
                    <!-- Blood Inventory Overview -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar"></i> Blood Inventory Overview</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Blood Group</th>
                                                <th>Component</th>
                                                <th>Available</th>
                                                <th>Total</th>
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
                                                <td><?php echo $item['total_units']; ?></td>
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

                    <!-- Pending Blood Requests -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-exclamation-circle"></i> Pending Blood Requests</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pending_requests)): ?>
                                    <p class="text-muted text-center">No pending requests</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Request#</th>
                                                    <th>Patient</th>
                                                    <th>Blood Type</th>
                                                    <th>Units</th>
                                                    <th>Urgency</th>
                                                    <th>Action</th>
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
                                                    <td>
                                                        <a href="blood-request-details.php?id=<?php echo $request['id']; ?>" 
                                                           class="btn btn-sm btn-primary">Process</a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Donations -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-history"></i> Recent Donations (Last 7 Days)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_donations)): ?>
                                    <p class="text-muted text-center">No recent donations</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Donor</th>
                                                    <th>Donor ID</th>
                                                    <th>Type</th>
                                                    <th>Volume (ml)</th>
                                                    <th>Collected By</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_donations as $donation): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y H:i', strtotime($donation['collection_date'])); ?></td>
                                                    <td><?php echo $donation['donor_name']; ?></td>
                                                    <td><?php echo $donation['donor_id']; ?></td>
                                                    <td><?php echo ucfirst(str_replace('_', ' ', $donation['donation_type'])); ?></td>
                                                    <td><?php echo $donation['volume_collected']; ?></td>
                                                    <td><?php echo $donation['collected_by_name']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $donation['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                            <?php echo ucfirst($donation['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
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
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);

        // Show alerts for critical stock
        <?php if (!empty($critical_stock)): ?>
        setTimeout(function() {
            alert('⚠️ CRITICAL ALERT: Low blood stock detected!\nPlease arrange for blood collection immediately.');
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>
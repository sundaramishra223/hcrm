<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$message = '';

// Get user role and permissions
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'add_donation_session':
                $session_sql = "INSERT INTO blood_donation_sessions (donor_id, collected_by, collection_date, pre_donation_checkup, hemoglobin_level, blood_pressure, weight, volume_collected, donation_type, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
                $db->query($session_sql, [
                    $_POST['donor_id'], $_SESSION['user_id'], $_POST['collection_date'],
                    $_POST['pre_donation_checkup'], $_POST['hemoglobin_level'], $_POST['blood_pressure'],
                    $_POST['weight'], $_POST['volume_collected'], $_POST['donation_type'], $_POST['notes']
                ]);
                
                // Update donor's last donation date and total count
                $db->query("UPDATE blood_donors SET last_donation_date = ?, total_donations = total_donations + 1 WHERE id = ?", 
                          [$_POST['collection_date'], $_POST['donor_id']]);
                
                $message = "Blood donation session recorded successfully!";
                break;
                
            case 'record_blood_usage':
                $usage_sql = "INSERT INTO blood_usage_records (blood_bag_id, patient_id, used_by, usage_date, usage_type, volume_used, patient_condition, cross_match_result, adverse_reactions, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $db->query($usage_sql, [
                    $_POST['blood_bag_id'], $_POST['patient_id'], $_SESSION['user_id'], $_POST['usage_date'],
                    $_POST['usage_type'], $_POST['volume_used'], $_POST['patient_condition'],
                    $_POST['cross_match_result'], $_POST['adverse_reactions'], $_POST['notes']
                ]);
                
                // Update blood inventory status
                $db->query("UPDATE blood_inventory SET status = 'used', issued_to_patient_id = ?, issued_date = ?, issued_by = ? WHERE id = ?", 
                          [$_POST['patient_id'], $_POST['usage_date'], $_SESSION['user_id'], $_POST['blood_bag_id']]);
                
                $message = "Blood usage recorded successfully!";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get tracking data based on user role and filters
$date_filter = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Blood inventory summary
$inventory_summary = $db->query("
    SELECT 
        blood_group,
        COUNT(*) as total_units,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_units,
        SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_units,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_units,
        SUM(volume_ml) as total_volume
    FROM blood_inventory 
    WHERE hospital_id = 1
    GROUP BY blood_group
    ORDER BY blood_group
")->fetchAll();

// Recent donations
$recent_donations = $db->query("
    SELECT bds.*, 
           bd.donor_id, bd.first_name as donor_first_name, bd.last_name as donor_last_name, bd.phone as donor_phone,
           s.first_name as collector_first_name, s.last_name as collector_last_name, s.staff_type
    FROM blood_donation_sessions bds
    JOIN blood_donors bd ON bds.donor_id = bd.id
    LEFT JOIN staff s ON bds.collected_by = s.user_id
    WHERE bds.collection_date BETWEEN ? AND ?
    ORDER BY bds.collection_date DESC, bds.created_at DESC
    LIMIT 20
", [$date_filter, $date_to])->fetchAll();

// Recent blood usage
$recent_usage = $db->query("
    SELECT bur.*, 
           bi.bag_number, bi.blood_group, bi.component_type, bi.volume_ml,
           p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id as patient_number,
           s.first_name as staff_first_name, s.last_name as staff_last_name, s.staff_type
    FROM blood_usage_records bur
    JOIN blood_inventory bi ON bur.blood_bag_id = bi.id
    JOIN patients p ON bur.patient_id = p.id
    LEFT JOIN staff s ON bur.used_by = s.user_id
    WHERE bur.usage_date BETWEEN ? AND ?
    ORDER BY bur.usage_date DESC, bur.created_at DESC
    LIMIT 20
", [$date_filter, $date_to])->fetchAll();

// Get data for forms
$active_donors = $db->query("SELECT * FROM blood_donors WHERE is_active = 1 AND is_eligible = 1 ORDER BY first_name")->fetchAll();
$available_blood = $db->query("SELECT * FROM blood_inventory WHERE status = 'available' ORDER BY blood_group, expiry_date")->fetchAll();
$patients = $db->query("SELECT id, patient_id, CONCAT(first_name, ' ', last_name) as name FROM patients WHERE is_active = 1 ORDER BY first_name")->fetchAll();

// Statistics for dashboard
$stats = [
    'total_donations_today' => $db->query("SELECT COUNT(*) as count FROM blood_donation_sessions WHERE DATE(collection_date) = CURDATE()")->fetch()['count'],
    'total_usage_today' => $db->query("SELECT COUNT(*) as count FROM blood_usage_records WHERE DATE(usage_date) = CURDATE()")->fetch()['count'],
    'total_available_units' => $db->query("SELECT COUNT(*) as count FROM blood_inventory WHERE status = 'available'")->fetch()['count'],
    'low_stock_groups' => $db->query("SELECT COUNT(DISTINCT blood_group) as count FROM blood_inventory WHERE status = 'available' GROUP BY blood_group HAVING COUNT(*) < 5")->fetch()['count'] ?? 0
];

// My activity (for specific user)
$my_collections = [];
$my_usage = [];
if (in_array($user_role, ['doctor', 'nurse', 'blood_bank_staff'])) {
    $my_collections = $db->query("
        SELECT bds.*, bd.donor_id, bd.first_name as donor_first_name, bd.last_name as donor_last_name
        FROM blood_donation_sessions bds
        JOIN blood_donors bd ON bds.donor_id = bd.id
        WHERE bds.collected_by = ? AND bds.collection_date BETWEEN ? AND ?
        ORDER BY bds.collection_date DESC
        LIMIT 10
    ", [$user_id, $date_filter, $date_to])->fetchAll();
    
    $my_usage = $db->query("
        SELECT bur.*, bi.bag_number, bi.blood_group, p.first_name as patient_first_name, p.last_name as patient_last_name
        FROM blood_usage_records bur
        JOIN blood_inventory bi ON bur.blood_bag_id = bi.id
        JOIN patients p ON bur.patient_id = p.id
        WHERE bur.used_by = ? AND bur.usage_date BETWEEN ? AND ?
        ORDER BY bur.usage_date DESC
        LIMIT 10
    ", [$user_id, $date_filter, $date_to])->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    include_once 'includes/site-config.php';
    renderSiteHead('Blood Donation Tracking');
    ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
    <style>
        .blood-tracking {
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-section {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-section input, .filter-section select {
            padding: 8px 12px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .inventory-summary {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }
        
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .blood-group-card {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #dc2626;
        }
        
        .blood-type {
            font-size: 1.5rem;
            font-weight: bold;
            color: #dc2626;
            margin-bottom: 10px;
        }
        
        .blood-stats {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .available-count {
            color: var(--secondary-color);
            font-weight: bold;
        }
        
        .management-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 12px 20px;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .tab-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-section {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .btn {
            background: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        
        .btn-success { background: var(--secondary-color); }
        .btn-warning { background: var(--accent-color); }
        .btn-danger { background: #dc2626; }
        
        .tracking-table {
            background: var(--bg-card);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-top: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fecaca; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
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
        
        .timeline-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary-color);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .timeline-title {
            font-weight: bold;
            color: var(--text-primary);
        }
        
        .timeline-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .timeline-details {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        
        .low-stock {
            background: #fef3c7 !important;
            border-left-color: #f59e0b !important;
        }
        
        .critical-stock {
            background: #fecaca !important;
            border-left-color: #ef4444 !important;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="blood-tracking">
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-tint"></i> Blood Donation Tracking</h1>
                        <p>Complete blood donation and usage tracking system</p>
                    </div>
                    <div class="filter-section">
                        <form method="GET">
                            <label>From:</label>
                            <input type="date" name="date_from" value="<?php echo $date_filter; ?>">
                            <label>To:</label>
                            <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                            <button type="submit" class="btn">Filter</button>
                        </form>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Dashboard -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_donations_today']; ?></div>
                        <div class="stat-label">Donations Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_usage_today']; ?></div>
                        <div class="stat-label">Blood Used Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_available_units']; ?></div>
                        <div class="stat-label">Available Units</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['low_stock_groups']; ?></div>
                        <div class="stat-label">Low Stock Groups</div>
                    </div>
                </div>
                
                <!-- Blood Inventory Summary -->
                <div class="inventory-summary">
                    <h3><i class="fas fa-warehouse"></i> Current Blood Inventory</h3>
                    <div class="inventory-grid">
                        <?php foreach ($inventory_summary as $group): ?>
                            <div class="blood-group-card <?php 
                                if ($group['available_units'] < 3) echo 'critical-stock';
                                elseif ($group['available_units'] < 5) echo 'low-stock';
                            ?>">
                                <div class="blood-type"><?php echo htmlspecialchars($group['blood_group']); ?></div>
                                <div class="blood-stats">
                                    <div class="available-count"><?php echo $group['available_units']; ?> Available</div>
                                    <div>Used: <?php echo $group['used_units']; ?></div>
                                    <div>Expired: <?php echo $group['expired_units']; ?></div>
                                    <div>Total: <?php echo number_format($group['total_volume']); ?>ml</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Management Tabs -->
                <div class="management-tabs">
                    <div class="tab-btn active" onclick="showTab('donations')">
                        <i class="fas fa-hand-holding-heart"></i> Recent Donations
                    </div>
                    <div class="tab-btn" onclick="showTab('usage')">
                        <i class="fas fa-syringe"></i> Blood Usage
                    </div>
                    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'blood_bank_staff'])): ?>
                        <div class="tab-btn" onclick="showTab('record-donation')">
                            <i class="fas fa-plus-circle"></i> Record Donation
                        </div>
                        <div class="tab-btn" onclick="showTab('record-usage')">
                            <i class="fas fa-notes-medical"></i> Record Usage
                        </div>
                    <?php endif; ?>
                    <?php if (in_array($user_role, ['doctor', 'nurse', 'blood_bank_staff'])): ?>
                        <div class="tab-btn" onclick="showTab('my-activity')">
                            <i class="fas fa-user"></i> My Activity
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Donations Tab -->
                <div id="donations" class="tab-content active">
                    <h2>Recent Blood Donations</h2>
                    <div class="tracking-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Donor</th>
                                    <th>Blood Group</th>
                                    <th>Volume (ml)</th>
                                    <th>Collected By</th>
                                    <th>Hemoglobin</th>
                                    <th>Status</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_donations as $donation): ?>
                                    <tr>
                                        <td><?php echo date('d-M-Y H:i', strtotime($donation['collection_date'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($donation['donor_first_name'] . ' ' . $donation['donor_last_name']); ?></strong>
                                            <br><small><?php echo $donation['donor_id']; ?> | <?php echo $donation['donor_phone']; ?></small>
                                        </td>
                                        <td><span class="badge badge-info"><?php echo $donation['blood_group']; ?></span></td>
                                        <td><?php echo $donation['volume_collected']; ?>ml</td>
                                        <td>
                                            <?php echo htmlspecialchars($donation['collector_first_name'] . ' ' . $donation['collector_last_name']); ?>
                                            <br><small><?php echo ucfirst(str_replace('_', ' ', $donation['staff_type'])); ?></small>
                                        </td>
                                        <td><?php echo $donation['hemoglobin_level']; ?> g/dL</td>
                                        <td><span class="badge badge-success"><?php echo ucfirst($donation['status']); ?></span></td>
                                        <td>
                                            <small>
                                                <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $donation['donation_type'])); ?><br>
                                                <strong>BP:</strong> <?php echo $donation['blood_pressure']; ?><br>
                                                <strong>Weight:</strong> <?php echo $donation['weight']; ?>kg
                                                <?php if ($donation['notes']): ?>
                                                    <br><strong>Notes:</strong> <?php echo htmlspecialchars($donation['notes']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Blood Usage Tab -->
                <div id="usage" class="tab-content">
                    <h2>Recent Blood Usage</h2>
                    <div class="tracking-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Blood Bag</th>
                                    <th>Patient</th>
                                    <th>Used By</th>
                                    <th>Usage Type</th>
                                    <th>Volume</th>
                                    <th>Cross Match</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_usage as $usage): ?>
                                    <tr>
                                        <td><?php echo date('d-M-Y H:i', strtotime($usage['usage_date'])); ?></td>
                                        <td>
                                            <strong><?php echo $usage['bag_number']; ?></strong>
                                            <br><span class="badge badge-info"><?php echo $usage['blood_group']; ?></span>
                                            <br><small><?php echo ucfirst(str_replace('_', ' ', $usage['component_type'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($usage['patient_first_name'] . ' ' . $usage['patient_last_name']); ?></strong>
                                            <br><small><?php echo $usage['patient_number']; ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($usage['staff_first_name'] . ' ' . $usage['staff_last_name']); ?>
                                            <br><small><?php echo ucfirst(str_replace('_', ' ', $usage['staff_type'])); ?></small>
                                        </td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $usage['usage_type'])); ?></td>
                                        <td><?php echo $usage['volume_used']; ?>ml / <?php echo $usage['volume_ml']; ?>ml</td>
                                        <td>
                                            <span class="badge badge-<?php echo $usage['cross_match_result'] === 'compatible' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($usage['cross_match_result']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <strong>Condition:</strong> <?php echo htmlspecialchars($usage['patient_condition']); ?>
                                                <?php if ($usage['adverse_reactions']): ?>
                                                    <br><strong>Reactions:</strong> <?php echo htmlspecialchars($usage['adverse_reactions']); ?>
                                                <?php endif; ?>
                                                <?php if ($usage['notes']): ?>
                                                    <br><strong>Notes:</strong> <?php echo htmlspecialchars($usage['notes']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Record Donation Tab -->
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'blood_bank_staff'])): ?>
                <div id="record-donation" class="tab-content">
                    <h2>Record Blood Donation</h2>
                    <div class="form-grid">
                        <div class="form-section">
                            <h3><i class="fas fa-hand-holding-heart"></i> New Donation Session</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_donation_session">
                                <div class="form-group">
                                    <label>Donor</label>
                                    <select name="donor_id" required>
                                        <option value="">Select Donor</option>
                                        <?php foreach ($active_donors as $donor): ?>
                                            <option value="<?php echo $donor['id']; ?>">
                                                <?php echo htmlspecialchars($donor['donor_id'] . ' - ' . $donor['first_name'] . ' ' . $donor['last_name'] . ' (' . $donor['blood_group'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Collection Date & Time</label>
                                    <input type="datetime-local" name="collection_date" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Pre-Donation Checkup</label>
                                    <select name="pre_donation_checkup" required>
                                        <option value="passed">Passed</option>
                                        <option value="failed">Failed</option>
                                        <option value="conditional">Conditional</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Hemoglobin Level (g/dL)</label>
                                    <input type="number" name="hemoglobin_level" step="0.1" min="8" max="20" required>
                                </div>
                                <div class="form-group">
                                    <label>Blood Pressure</label>
                                    <input type="text" name="blood_pressure" placeholder="120/80" required>
                                </div>
                                <div class="form-group">
                                    <label>Weight (kg)</label>
                                    <input type="number" name="weight" step="0.1" min="45" max="200" required>
                                </div>
                                <div class="form-group">
                                    <label>Volume Collected (ml)</label>
                                    <input type="number" name="volume_collected" min="200" max="500" value="450" required>
                                </div>
                                <div class="form-group">
                                    <label>Donation Type</label>
                                    <select name="donation_type" required>
                                        <option value="whole_blood">Whole Blood</option>
                                        <option value="platelets">Platelets</option>
                                        <option value="plasma">Plasma</option>
                                        <option value="double_red_cells">Double Red Cells</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" rows="3" placeholder="Any additional notes or observations"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success">Record Donation</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Record Usage Tab -->
                <div id="record-usage" class="tab-content">
                    <h2>Record Blood Usage</h2>
                    <div class="form-grid">
                        <div class="form-section">
                            <h3><i class="fas fa-syringe"></i> Blood Transfusion/Usage</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="record_blood_usage">
                                <div class="form-group">
                                    <label>Blood Bag</label>
                                    <select name="blood_bag_id" required>
                                        <option value="">Select Blood Bag</option>
                                        <?php foreach ($available_blood as $bag): ?>
                                            <option value="<?php echo $bag['id']; ?>">
                                                <?php echo $bag['bag_number'] . ' - ' . $bag['blood_group'] . ' (' . ucfirst(str_replace('_', ' ', $bag['component_type'])) . ', ' . $bag['volume_ml'] . 'ml)'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Patient</label>
                                    <select name="patient_id" required>
                                        <option value="">Select Patient</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>">
                                                <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Usage Date & Time</label>
                                    <input type="datetime-local" name="usage_date" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Usage Type</label>
                                    <select name="usage_type" required>
                                        <option value="transfusion">Blood Transfusion</option>
                                        <option value="surgery">Surgery</option>
                                        <option value="emergency">Emergency</option>
                                        <option value="research">Research</option>
                                        <option value="testing">Testing</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Volume Used (ml)</label>
                                    <input type="number" name="volume_used" min="1" max="500" required>
                                </div>
                                <div class="form-group">
                                    <label>Patient Condition</label>
                                    <textarea name="patient_condition" rows="2" required placeholder="Patient's medical condition"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Cross Match Result</label>
                                    <select name="cross_match_result" required>
                                        <option value="compatible">Compatible</option>
                                        <option value="incompatible">Incompatible</option>
                                        <option value="pending">Pending</option>
                                        <option value="not_required">Not Required</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Adverse Reactions</label>
                                    <textarea name="adverse_reactions" rows="2" placeholder="Any adverse reactions observed (if none, leave blank)"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" rows="3" placeholder="Additional notes"></textarea>
                                </div>
                                <button type="submit" class="btn btn-warning">Record Usage</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- My Activity Tab -->
                <?php if (in_array($user_role, ['doctor', 'nurse', 'blood_bank_staff'])): ?>
                <div id="my-activity" class="tab-content">
                    <h2>My Blood Bank Activity</h2>
                    <div class="form-grid">
                        <div class="form-section">
                            <h3><i class="fas fa-hand-holding-heart"></i> My Blood Collections</h3>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($my_collections as $collection): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-header">
                                            <div class="timeline-title">
                                                Blood Collection - <?php echo htmlspecialchars($collection['donor_first_name'] . ' ' . $collection['donor_last_name']); ?>
                                            </div>
                                            <div class="timeline-time"><?php echo date('d-M-Y H:i', strtotime($collection['collection_date'])); ?></div>
                                        </div>
                                        <div class="timeline-details">
                                            <strong>Donor ID:</strong> <?php echo $collection['donor_id']; ?><br>
                                            <strong>Volume:</strong> <?php echo $collection['volume_collected']; ?>ml<br>
                                            <strong>Hemoglobin:</strong> <?php echo $collection['hemoglobin_level']; ?> g/dL<br>
                                            <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $collection['donation_type'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3><i class="fas fa-syringe"></i> My Blood Usage Records</h3>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($my_usage as $usage): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-header">
                                            <div class="timeline-title">
                                                Blood Usage - <?php echo htmlspecialchars($usage['patient_first_name'] . ' ' . $usage['patient_last_name']); ?>
                                            </div>
                                            <div class="timeline-time"><?php echo date('d-M-Y H:i', strtotime($usage['usage_date'])); ?></div>
                                        </div>
                                        <div class="timeline-details">
                                            <strong>Blood Bag:</strong> <?php echo $usage['bag_number']; ?> (<?php echo $usage['blood_group']; ?>)<br>
                                            <strong>Volume Used:</strong> <?php echo $usage['volume_used']; ?>ml<br>
                                            <strong>Usage Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $usage['usage_type'])); ?><br>
                                            <strong>Cross Match:</strong> <?php echo ucfirst($usage['cross_match_result']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
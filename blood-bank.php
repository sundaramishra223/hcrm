<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Get current page from URL parameter
$current_page = $_GET['page'] ?? 'overview';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_donation':
                if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])) {
                    $donor_name = $_POST['donor_name'];
                    $donor_phone = $_POST['donor_phone'];
                    $donor_email = $_POST['donor_email'];
                    $blood_type = $_POST['blood_type'];
                    $quantity = $_POST['quantity'];
                    $donation_date = $_POST['donation_date'];
                    $expiry_date = $_POST['expiry_date'];
                    $notes = $_POST['notes'];
                    
                    $stmt = $db->prepare("INSERT INTO blood_donations (donor_name, donor_phone, donor_email, blood_type, quantity_ml, donation_date, expiry_date, status, notes, collected_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'available', ?, ?, NOW())");
                    $stmt->execute([$donor_name, $donor_phone, $donor_email, $blood_type, $quantity, $donation_date, $expiry_date, $notes, $user_id]);
                    
                    // Update blood inventory
                    $stmt = $db->prepare("INSERT INTO blood_inventory (blood_type, quantity_ml, last_updated) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE quantity_ml = quantity_ml + VALUES(quantity_ml), last_updated = NOW()");
                    $stmt->execute([$blood_type, $quantity]);
                    
                    $success_message = "Blood donation recorded successfully!";
                }
                break;
                
            case 'add_request':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $patient_id = $_POST['patient_id'];
                    $blood_type = $_POST['blood_type'];
                    $quantity = $_POST['quantity'];
                    $urgency = $_POST['urgency'];
                    $required_date = $_POST['required_date'];
                    $notes = $_POST['notes'];
                    
                    $stmt = $db->prepare("INSERT INTO blood_requests (patient_id, blood_type, quantity_ml, urgency_level, required_date, status, notes, requested_by, created_at) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW())");
                    $stmt->execute([$patient_id, $blood_type, $quantity, $urgency, $required_date, $notes, $user_id]);
                    
                    $success_message = "Blood request submitted successfully!";
                }
                break;
                
            case 'fulfill_request':
                if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])) {
                    $request_id = $_POST['request_id'];
                    $donation_ids = $_POST['donation_ids']; // Array of donation IDs
                    
                    // Update request status
                    $stmt = $db->prepare("UPDATE blood_requests SET status = 'fulfilled', fulfilled_by = ?, fulfilled_at = NOW() WHERE id = ?");
                    $stmt->execute([$user_id, $request_id]);
                    
                    // Update donation status and inventory
                    foreach ($donation_ids as $donation_id) {
                        $stmt = $db->prepare("UPDATE blood_donations SET status = 'used', used_for_request_id = ?, used_at = NOW() WHERE id = ?");
                        $stmt->execute([$request_id, $donation_id]);
                        
                        // Get donation details to update inventory
                        $stmt = $db->prepare("SELECT blood_type, quantity_ml FROM blood_donations WHERE id = ?");
                        $stmt->execute([$donation_id]);
                        $donation = $stmt->fetch();
                        
                        // Update inventory
                        $stmt = $db->prepare("UPDATE blood_inventory SET quantity_ml = quantity_ml - ?, last_updated = NOW() WHERE blood_type = ?");
                        $stmt->execute([$donation['quantity_ml'], $donation['blood_type']]);
                    }
                    
                    $success_message = "Blood request fulfilled successfully!";
                }
                break;
        }
    }
}

// Fetch data based on user role
if ($user_role === 'patient') {
    // Patient can only see their own requests
    $patient_stmt = $db->prepare("SELECT * FROM patients WHERE user_id = ?");
    $patient_stmt->execute([$user_id]);
    $patient = $patient_stmt->fetch();
    
    if ($patient) {
        $requests_stmt = $db->prepare("
            SELECT br.*, u.first_name, u.last_name 
            FROM blood_requests br 
            LEFT JOIN users u ON br.requested_by = u.id 
            WHERE br.patient_id = ? 
            ORDER BY br.created_at DESC
        ");
        $requests_stmt->execute([$patient['id']]);
        $patient_requests = $requests_stmt->fetchAll();
    }
} else {
    // Admin/Staff can see all data
    
    // Blood inventory
    $inventory_stmt = $db->prepare("SELECT * FROM blood_inventory ORDER BY blood_type");
    $inventory = $inventory_stmt->fetchAll();
    
    // Recent donations
    $donations_stmt = $db->prepare("
        SELECT bd.*, u.first_name, u.last_name 
        FROM blood_donations bd 
        LEFT JOIN users u ON bd.collected_by = u.id 
        ORDER BY bd.created_at DESC 
        LIMIT 20
    ");
    $donations = $donations_stmt->fetchAll();
    
    // Blood requests
    $requests_stmt = $db->prepare("
        SELECT br.*, p.first_name as patient_first_name, p.last_name as patient_last_name, 
               p.patient_id, u.first_name, u.last_name 
        FROM blood_requests br 
        LEFT JOIN patients p ON br.patient_id = p.id 
        LEFT JOIN users u ON br.requested_by = u.id 
        ORDER BY br.created_at DESC
    ");
    $requests = $requests_stmt->fetchAll();
    
    // Statistics
    $stats_stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM blood_donations WHERE status = 'available') as available_units,
            (SELECT COUNT(*) FROM blood_donations WHERE status = 'used') as used_units,
            (SELECT COUNT(*) FROM blood_requests WHERE status = 'pending') as pending_requests,
            (SELECT COUNT(*) FROM blood_requests WHERE status = 'fulfilled') as fulfilled_requests,
            (SELECT SUM(quantity_ml) FROM blood_inventory) as total_blood_ml
    ");
    $stats = $stats_stmt->fetch();
    
    // Get patients for dropdown
    $patients_stmt = $db->prepare("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name, last_name");
    $patients = $patients_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Management - Hospital CRM</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            text-align: center;
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            margin-bottom: 10px;
            font-size: 1.5rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin: 5px 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(255,255,255,0.1);
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .breadcrumb {
            color: #666;
            font-size: 0.9rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stat-card.available .icon { color: #28a745; }
        .stat-card.used .icon { color: #dc3545; }
        .stat-card.pending .icon { color: #ffc107; }
        .stat-card.fulfilled .icon { color: #17a2b8; }

        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: #666;
            font-size: 0.9rem;
        }

        .content-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .section-header h2 {
            color: #333;
            margin: 0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .table-container {
            padding: 20px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge.available { background: #d4edda; color: #155724; }
        .badge.used { background: #f8d7da; color: #721c24; }
        .badge.expired { background: #fff3cd; color: #856404; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.fulfilled { background: #d1ecf1; color: #0c5460; }
        .badge.urgent { background: #f8d7da; color: #721c24; }
        .badge.normal { background: #d4edda; color: #155724; }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        .close:hover {
            color: #333;
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .blood-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .blood-type-card {
            background: white;
            border: 2px solid #eee;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
        }

        .blood-type-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }

        .blood-type-card .type {
            font-size: 1.5rem;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 5px;
        }

        .blood-type-card .quantity {
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-tint"></i> Blood Bank</h3>
                <?php if ($user_role === 'patient'): ?>
                    <p><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                    <small><?php echo htmlspecialchars($patient['patient_id']); ?></small>
                <?php else: ?>
                    <p>Management System</p>
                <?php endif; ?>
            </div>
            <ul class="sidebar-menu">
                <?php if ($user_role === 'patient'): ?>
                    <li><a href="blood-bank.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Overview</a></li>
                    <li><a href="blood-bank.php?page=my-requests" class="<?php echo $current_page === 'my-requests' ? 'active' : ''; ?>"><i class="fas fa-list"></i> My Requests</a></li>
                    <li><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Portal</a></li>
                <?php else: ?>
                    <li><a href="blood-bank.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Overview</a></li>
                    <li><a href="blood-bank.php?page=inventory" class="<?php echo $current_page === 'inventory' ? 'active' : ''; ?>"><i class="fas fa-boxes"></i> Blood Inventory</a></li>
                    <li><a href="blood-bank.php?page=donations" class="<?php echo $current_page === 'donations' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-heart"></i> Donations</a></li>
                    <li><a href="blood-bank.php?page=requests" class="<?php echo $current_page === 'requests' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> Blood Requests</a></li>
                    <li><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></li>
                <?php endif; ?>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($current_page === 'overview'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-tint"></i> Blood Bank Overview</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Blood Bank / Overview
                    </div>
                </div>

                <?php if ($user_role === 'patient'): ?>
                    <!-- Patient Overview -->
                    <div class="stats-grid">
                        <div class="stat-card pending">
                            <div class="icon"><i class="fas fa-clock"></i></div>
                            <div class="number"><?php echo count(array_filter($patient_requests, function($r) { return $r['status'] === 'pending'; })); ?></div>
                            <div class="label">Pending Requests</div>
                        </div>
                        <div class="stat-card fulfilled">
                            <div class="icon"><i class="fas fa-check"></i></div>
                            <div class="number"><?php echo count(array_filter($patient_requests, function($r) { return $r['status'] === 'fulfilled'; })); ?></div>
                            <div class="label">Fulfilled Requests</div>
                        </div>
                    </div>

                    <div class="content-section">
                        <div class="section-header">
                            <h2>Recent Blood Requests</h2>
                        </div>
                        <div class="table-container">
                            <?php if (!empty($patient_requests)): ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Request Date</th>
                                            <th>Blood Type</th>
                                            <th>Quantity (ml)</th>
                                            <th>Urgency</th>
                                            <th>Status</th>
                                            <th>Required Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($patient_requests, 0, 10) as $request): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                                <td><strong><?php echo htmlspecialchars($request['blood_type']); ?></strong></td>
                                                <td><?php echo number_format($request['quantity_ml']); ?> ml</td>
                                                <td><span class="badge <?php echo $request['urgency_level']; ?>"><?php echo ucfirst($request['urgency_level']); ?></span></td>
                                                <td><span class="badge <?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                                <td><?php echo date('M d, Y', strtotime($request['required_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="text-align: center; padding: 40px; color: #666;">No blood requests found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Admin/Staff Overview -->
                    <div class="stats-grid">
                        <div class="stat-card available">
                            <div class="icon"><i class="fas fa-tint"></i></div>
                            <div class="number"><?php echo $stats['available_units']; ?></div>
                            <div class="label">Available Units</div>
                        </div>
                        <div class="stat-card used">
                            <div class="icon"><i class="fas fa-tint-slash"></i></div>
                            <div class="number"><?php echo $stats['used_units']; ?></div>
                            <div class="label">Used Units</div>
                        </div>
                        <div class="stat-card pending">
                            <div class="icon"><i class="fas fa-clock"></i></div>
                            <div class="number"><?php echo $stats['pending_requests']; ?></div>
                            <div class="label">Pending Requests</div>
                        </div>
                        <div class="stat-card fulfilled">
                            <div class="icon"><i class="fas fa-check"></i></div>
                            <div class="number"><?php echo $stats['fulfilled_requests']; ?></div>
                            <div class="label">Fulfilled Requests</div>
                        </div>
                    </div>

                    <!-- Blood Type Inventory -->
                    <div class="content-section">
                        <div class="section-header">
                            <h2>Blood Type Inventory</h2>
                        </div>
                        <div class="blood-type-grid">
                            <?php 
                            $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($blood_types as $type): 
                                $found = false;
                                $quantity = 0;
                                foreach ($inventory as $item) {
                                    if ($item['blood_type'] === $type) {
                                        $quantity = $item['quantity_ml'];
                                        $found = true;
                                        break;
                                    }
                                }
                            ?>
                                <div class="blood-type-card">
                                    <div class="type"><?php echo $type; ?></div>
                                    <div class="quantity"><?php echo number_format($quantity); ?> ml</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($current_page === 'inventory' && $user_role !== 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-boxes"></i> Blood Inventory</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Blood Bank / Inventory
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>Current Blood Inventory</h2>
                        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])): ?>
                            <button class="btn btn-primary" onclick="openModal('addDonationModal')">
                                <i class="fas fa-plus"></i> Add Donation
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Blood Type</th>
                                    <th>Quantity (ml)</th>
                                    <th>Quantity (Units)</th>
                                    <th>Last Updated</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory as $item): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($item['blood_type']); ?></strong></td>
                                        <td><?php echo number_format($item['quantity_ml']); ?> ml</td>
                                        <td><?php echo number_format($item['quantity_ml'] / 450, 1); ?> units</td>
                                        <td><?php echo date('M d, Y H:i', strtotime($item['last_updated'])); ?></td>
                                        <td>
                                            <?php if ($item['quantity_ml'] > 2000): ?>
                                                <span class="badge available">Good Stock</span>
                                            <?php elseif ($item['quantity_ml'] > 500): ?>
                                                <span class="badge pending">Low Stock</span>
                                            <?php else: ?>
                                                <span class="badge used">Critical</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'donations' && $user_role !== 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-hand-holding-heart"></i> Blood Donations</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Blood Bank / Donations
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>Recent Donations</h2>
                        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])): ?>
                            <button class="btn btn-primary" onclick="openModal('addDonationModal')">
                                <i class="fas fa-plus"></i> Add Donation
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Donor Name</th>
                                    <th>Contact</th>
                                    <th>Blood Type</th>
                                    <th>Quantity (ml)</th>
                                    <th>Donation Date</th>
                                    <th>Expiry Date</th>
                                    <th>Status</th>
                                    <th>Collected By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donations as $donation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($donation['donor_phone']); ?><br>
                                            <small><?php echo htmlspecialchars($donation['donor_email']); ?></small>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($donation['blood_type']); ?></strong></td>
                                        <td><?php echo number_format($donation['quantity_ml']); ?> ml</td>
                                        <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($donation['expiry_date'])); ?></td>
                                        <td>
                                            <?php
                                            $status = $donation['status'];
                                            if ($status === 'available' && strtotime($donation['expiry_date']) < time()) {
                                                $status = 'expired';
                                            }
                                            ?>
                                            <span class="badge <?php echo $status; ?>"><?php echo ucfirst($status); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($donation['first_name'] . ' ' . $donation['last_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'requests' && $user_role !== 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-clipboard-list"></i> Blood Requests</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Blood Bank / Requests
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>Blood Requests</h2>
                        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                            <button class="btn btn-primary" onclick="openModal('addRequestModal')">
                                <i class="fas fa-plus"></i> New Request
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Blood Type</th>
                                    <th>Quantity (ml)</th>
                                    <th>Urgency</th>
                                    <th>Required Date</th>
                                    <th>Status</th>
                                    <th>Requested By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($request['patient_first_name'] . ' ' . $request['patient_last_name']); ?><br>
                                            <small><?php echo htmlspecialchars($request['patient_id']); ?></small>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($request['blood_type']); ?></strong></td>
                                        <td><?php echo number_format($request['quantity_ml']); ?> ml</td>
                                        <td><span class="badge <?php echo $request['urgency_level']; ?>"><?php echo ucfirst($request['urgency_level']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($request['required_date'])); ?></td>
                                        <td><span class="badge <?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                        <td>
                                            <?php if ($request['status'] === 'pending' && in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])): ?>
                                                <button class="btn btn-success btn-sm" onclick="fulfillRequest(<?php echo $request['id']; ?>, '<?php echo $request['blood_type']; ?>', <?php echo $request['quantity_ml']; ?>)">
                                                    <i class="fas fa-check"></i> Fulfill
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'my-requests' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-list"></i> My Blood Requests</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Blood Bank / My Requests
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>All My Blood Requests</h2>
                    </div>
                    <div class="table-container">
                        <?php if (!empty($patient_requests)): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Request Date</th>
                                        <th>Blood Type</th>
                                        <th>Quantity (ml)</th>
                                        <th>Urgency</th>
                                        <th>Required Date</th>
                                        <th>Status</th>
                                        <th>Requested By</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patient_requests as $request): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($request['blood_type']); ?></strong></td>
                                            <td><?php echo number_format($request['quantity_ml']); ?> ml</td>
                                            <td><span class="badge <?php echo $request['urgency_level']; ?>"><?php echo ucfirst($request['urgency_level']); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($request['required_date'])); ?></td>
                                            <td><span class="badge <?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($request['notes'] ?: 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="text-align: center; padding: 40px; color: #666;">No blood requests found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Donation Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])): ?>
    <div id="addDonationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Blood Donation</h3>
                <span class="close" onclick="closeModal('addDonationModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_donation">
                    
                    <div class="form-group">
                        <label for="donor_name">Donor Name *</label>
                        <input type="text" id="donor_name" name="donor_name" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="donor_phone">Phone Number *</label>
                            <input type="tel" id="donor_phone" name="donor_phone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="donor_email">Email</label>
                            <input type="email" id="donor_email" name="donor_email" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="blood_type">Blood Type *</label>
                            <select id="blood_type" name="blood_type" class="form-control" required>
                                <option value="">Select Blood Type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity (ml) *</label>
                            <input type="number" id="quantity" name="quantity" class="form-control" min="100" max="500" value="450" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="donation_date">Donation Date *</label>
                            <input type="date" id="donation_date" name="donation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="expiry_date">Expiry Date *</label>
                            <input type="date" id="expiry_date" name="expiry_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+35 days')); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="button" class="btn" onclick="closeModal('addDonationModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Donation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Request Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
    <div id="addRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>New Blood Request</h3>
                <span class="close" onclick="closeModal('addRequestModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_request">
                    
                    <div class="form-group">
                        <label for="patient_id">Patient *</label>
                        <select id="patient_id" name="patient_id" class="form-control" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="blood_type_req">Blood Type *</label>
                            <select id="blood_type_req" name="blood_type" class="form-control" required>
                                <option value="">Select Blood Type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantity_req">Quantity (ml) *</label>
                            <input type="number" id="quantity_req" name="quantity" class="form-control" min="100" max="2000" value="450" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="urgency">Urgency Level *</label>
                            <select id="urgency" name="urgency" class="form-control" required>
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="required_date">Required Date *</label>
                            <input type="date" id="required_date" name="required_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes_req">Notes</label>
                        <textarea id="notes_req" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="button" class="btn" onclick="closeModal('addRequestModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function fulfillRequest(requestId, bloodType, quantityNeeded) {
            // This would typically open a modal to select available donations
            // For now, we'll use a simple confirmation
            if (confirm(`Fulfill blood request for ${bloodType} (${quantityNeeded}ml)?`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="fulfill_request">
                    <input type="hidden" name="request_id" value="${requestId}">
                    <input type="hidden" name="donation_ids[]" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
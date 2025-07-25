<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$user_role = $_SESSION['role'];

// Get current page from URL parameter
$current_page = $_GET['page'] ?? 'overview';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_donor':
                if (in_array($user_role, ['admin', 'nurse', 'receptionist'])) {
                    $stmt = $db->prepare("INSERT INTO blood_donors (donor_id, first_name, last_name, email, phone, blood_group, date_of_birth, gender, address, emergency_contact_name, emergency_contact_phone, medical_history, last_donation_date, eligibility_status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $donor_id = 'BD' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $last_donation = !empty($_POST['last_donation_date']) ? $_POST['last_donation_date'] : null;
                    
                    $stmt->execute([
                        $donor_id,
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['blood_group'],
                        $_POST['date_of_birth'],
                        $_POST['gender'],
                        $_POST['address'],
                        $_POST['emergency_contact_name'],
                        $_POST['emergency_contact_phone'],
                        $_POST['medical_history'],
                        $last_donation,
                        $_POST['eligibility_status'],
                        $_SESSION['user_id']
                    ]);
                    
                    $_SESSION['success_message'] = "Blood donor registered successfully!";
                }
                break;
                
            case 'add_donation':
                if (in_array($user_role, ['admin', 'nurse', 'lab_technician'])) {
                    $stmt = $db->prepare("INSERT INTO blood_donations (donation_id, donor_id, blood_group, units_collected, donation_date, collection_site, staff_id, hemoglobin_level, blood_pressure, temperature, weight, donation_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $donation_id = 'DON' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $donation_id,
                        $_POST['donor_id'],
                        $_POST['blood_group'],
                        $_POST['units_collected'],
                        $_POST['donation_date'],
                        $_POST['collection_site'],
                        $_SESSION['user_id'],
                        $_POST['hemoglobin_level'],
                        $_POST['blood_pressure'],
                        $_POST['temperature'],
                        $_POST['weight'],
                        $_POST['donation_status'],
                        $_POST['notes']
                    ]);
                    
                    // Update blood inventory
                    $stmt = $db->prepare("INSERT INTO blood_inventory (blood_group, units_available, expiry_date, collection_date, source_donation_id) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE units_available = units_available + VALUES(units_available)");
                    $expiry_date = date('Y-m-d', strtotime($_POST['donation_date'] . ' + 35 days'));
                    $stmt->execute([
                        $_POST['blood_group'],
                        $_POST['units_collected'],
                        $expiry_date,
                        $_POST['donation_date'],
                        $donation_id
                    ]);
                    
                    $_SESSION['success_message'] = "Blood donation recorded successfully!";
                }
                break;
                
            case 'issue_blood':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $stmt = $db->prepare("INSERT INTO blood_requests (request_id, patient_id, blood_group, units_requested, urgency_level, requesting_doctor, request_date, required_date, request_status, notes, issued_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $request_id = 'REQ' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $request_id,
                        $_POST['patient_id'],
                        $_POST['blood_group'],
                        $_POST['units_requested'],
                        $_POST['urgency_level'],
                        $_POST['requesting_doctor'],
                        $_POST['request_date'],
                        $_POST['required_date'],
                        'approved',
                        $_POST['notes'],
                        $_SESSION['user_id']
                    ]);
                    
                    // Update blood inventory (reduce units)
                    $stmt = $db->prepare("UPDATE blood_inventory SET units_available = units_available - ? WHERE blood_group = ? AND units_available >= ?");
                    $stmt->execute([$_POST['units_requested'], $_POST['blood_group'], $_POST['units_requested']]);
                    
                    $_SESSION['success_message'] = "Blood issued successfully!";
                }
                break;
        }
        
        header('Location: blood-bank.php?page=' . $current_page);
        exit;
    }
}

// Fetch data based on current page
$donors = [];
$donations = [];
$inventory = [];
$requests = [];
$stats = [];

if ($current_page === 'overview' || $current_page === 'donors' || $current_page === 'donations' || $current_page === 'inventory' || $current_page === 'requests') {
    // Get statistics
    $stmt = $db->query("SELECT COUNT(*) as total_donors FROM blood_donors WHERE status = 'active'");
    $stats['total_donors'] = $stmt->fetch()['total_donors'];
    
    $stmt = $db->query("SELECT COUNT(*) as total_donations FROM blood_donations WHERE donation_status = 'completed'");
    $stats['total_donations'] = $stmt->fetch()['total_donations'];
    
    $stmt = $db->query("SELECT SUM(units_available) as total_units FROM blood_inventory");
    $stats['total_units'] = $stmt->fetch()['total_units'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as pending_requests FROM blood_requests WHERE request_status = 'pending'");
    $stats['pending_requests'] = $stmt->fetch()['pending_requests'];
}

if ($current_page === 'donors' || $current_page === 'overview') {
    $stmt = $db->query("SELECT * FROM blood_donors ORDER BY created_at DESC LIMIT 10");
    $donors = $stmt->fetchAll();
}

if ($current_page === 'donations' || $current_page === 'overview') {
    $stmt = $db->query("SELECT bd.*, CONCAT(bdr.first_name, ' ', bdr.last_name) as donor_name FROM blood_donations bd LEFT JOIN blood_donors bdr ON bd.donor_id = bdr.id ORDER BY bd.donation_date DESC LIMIT 10");
    $donations = $stmt->fetchAll();
}

if ($current_page === 'inventory' || $current_page === 'overview') {
    $stmt = $db->query("SELECT blood_group, SUM(units_available) as total_units, MIN(expiry_date) as earliest_expiry FROM blood_inventory WHERE units_available > 0 GROUP BY blood_group ORDER BY blood_group");
    $inventory = $stmt->fetchAll();
}

if ($current_page === 'requests' || $current_page === 'overview') {
    $stmt = $db->query("SELECT br.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, CONCAT(d.first_name, ' ', d.last_name) as doctor_name FROM blood_requests br LEFT JOIN patients p ON br.patient_id = p.id LEFT JOIN doctors d ON br.requesting_doctor = d.id ORDER BY br.request_date DESC LIMIT 10");
    $requests = $stmt->fetchAll();
}

// Get all donors for dropdown
$all_donors = [];
if (in_array($user_role, ['admin', 'nurse', 'lab_technician'])) {
    $stmt = $db->query("SELECT id, donor_id, CONCAT(first_name, ' ', last_name) as name, blood_group FROM blood_donors WHERE status = 'active'");
    $all_donors = $stmt->fetchAll();
}

// Get all patients for dropdown
$all_patients = [];
if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
    $stmt = $db->query("SELECT id, patient_id, CONCAT(first_name, ' ', last_name) as name FROM patients WHERE status = 'active'");
    $all_patients = $stmt->fetchAll();
}

// Get all doctors for dropdown
$all_doctors = [];
if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
    $stmt = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM doctors WHERE status = 'active'");
    $all_doctors = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Management - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f6fa;
            line-height: 1.6;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header h3 {
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
            flex: 1;
        }
        
        .page-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .page-title {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            color: #7f8c8d;
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
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stat-card.donors::before { background: #3498db; }
        .stat-card.donations::before { background: #e74c3c; }
        .stat-card.inventory::before { background: #2ecc71; }
        .stat-card.requests::before { background: #f39c12; }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-card.donors .stat-icon { color: #3498db; }
        .stat-card.donations .stat-icon { color: #e74c3c; }
        .stat-card.inventory .stat-icon { color: #2ecc71; }
        .stat-card.requests .stat-icon { color: #f39c12; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.2rem;
            color: #2c3e50;
            margin: 0;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn i {
            margin-right: 5px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #2ecc71;
            color: white;
        }
        
        .btn-success:hover {
            background: #27ae60;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
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
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            margin: 0;
            color: #2c3e50;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #7f8c8d;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
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
        
        .blood-group-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .blood-group-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #e74c3c;
        }
        
        .blood-group-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 5px;
        }
        
        .blood-group-units {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .blood-group-expiry {
            font-size: 0.8rem;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-tint"></i> Blood Bank</h3>
                <p>Management System</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="blood-bank.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Overview</a></li>
                <?php if (in_array($user_role, ['admin', 'nurse', 'receptionist'])): ?>
                <li><a href="blood-bank.php?page=donors" class="<?php echo $current_page === 'donors' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Donors</a></li>
                <?php endif; ?>
                <?php if (in_array($user_role, ['admin', 'nurse', 'lab_technician'])): ?>
                <li><a href="blood-bank.php?page=donations" class="<?php echo $current_page === 'donations' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-heart"></i> Donations</a></li>
                <?php endif; ?>
                <li><a href="blood-bank.php?page=inventory" class="<?php echo $current_page === 'inventory' ? 'active' : ''; ?>"><i class="fas fa-warehouse"></i> Inventory</a></li>
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                <li><a href="blood-bank.php?page=requests" class="<?php echo $current_page === 'requests' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> Requests</a></li>
                <?php endif; ?>
                <li><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if ($current_page === 'overview'): ?>
                <!-- Overview Page -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Blood Bank Overview</h1>
                        <p class="page-subtitle">Monitor blood bank operations and statistics</p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card donors">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo number_format($stats['total_donors']); ?></div>
                        <div class="stat-label">Active Donors</div>
                    </div>
                    <div class="stat-card donations">
                        <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                        <div class="stat-number"><?php echo number_format($stats['total_donations']); ?></div>
                        <div class="stat-label">Total Donations</div>
                    </div>
                    <div class="stat-card inventory">
                        <div class="stat-icon"><i class="fas fa-warehouse"></i></div>
                        <div class="stat-number"><?php echo number_format($stats['total_units']); ?></div>
                        <div class="stat-label">Units Available</div>
                    </div>
                    <div class="stat-card requests">
                        <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                        <div class="stat-number"><?php echo number_format($stats['pending_requests']); ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                </div>

                <!-- Blood Inventory by Group -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">Blood Inventory by Group</h3>
                    </div>
                    <div class="blood-group-grid">
                        <?php foreach ($inventory as $item): ?>
                            <div class="blood-group-card">
                                <div class="blood-group-name"><?php echo htmlspecialchars($item['blood_group']); ?></div>
                                <div class="blood-group-units"><?php echo $item['total_units']; ?> Units</div>
                                <div class="blood-group-expiry">Expires: <?php echo date('M d, Y', strtotime($item['earliest_expiry'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Donations</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Donor</th>
                                        <th>Blood Group</th>
                                        <th>Units</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($donations, 0, 5) as $donation): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
                                            <td><span class="badge badge-danger"><?php echo htmlspecialchars($donation['blood_group']); ?></span></td>
                                            <td><?php echo $donation['units_collected']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Requests</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Blood Group</th>
                                        <th>Units</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($requests, 0, 5) as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                            <td><span class="badge badge-danger"><?php echo htmlspecialchars($request['blood_group']); ?></span></td>
                                            <td><?php echo $request['units_requested']; ?></td>
                                            <td>
                                                <?php
                                                $status_class = '';
                                                switch($request['request_status']) {
                                                    case 'pending': $status_class = 'badge-warning'; break;
                                                    case 'approved': $status_class = 'badge-success'; break;
                                                    case 'rejected': $status_class = 'badge-danger'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($request['request_status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_page === 'donors' && in_array($user_role, ['admin', 'nurse', 'receptionist'])): ?>
                <!-- Donors Page -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Blood Donors</h1>
                        <p class="page-subtitle">Manage blood donor registrations</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('addDonorModal')">
                        <i class="fas fa-plus"></i> Add New Donor
                    </button>
                </div>

                <div class="content-card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Donor ID</th>
                                    <th>Name</th>
                                    <th>Blood Group</th>
                                    <th>Phone</th>
                                    <th>Last Donation</th>
                                    <th>Eligibility</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donors as $donor): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($donor['donor_id']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></td>
                                        <td><span class="badge badge-danger"><?php echo htmlspecialchars($donor['blood_group']); ?></span></td>
                                        <td><?php echo htmlspecialchars($donor['phone']); ?></td>
                                        <td><?php echo $donor['last_donation_date'] ? date('M d, Y', strtotime($donor['last_donation_date'])) : 'Never'; ?></td>
                                        <td>
                                            <?php
                                            $status_class = $donor['eligibility_status'] === 'eligible' ? 'badge-success' : 'badge-warning';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($donor['eligibility_status']); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'donations' && in_array($user_role, ['admin', 'nurse', 'lab_technician'])): ?>
                <!-- Donations Page -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Blood Donations</h1>
                        <p class="page-subtitle">Record and track blood donations</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('addDonationModal')">
                        <i class="fas fa-plus"></i> Record Donation
                    </button>
                </div>

                <div class="content-card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Donation ID</th>
                                    <th>Donor</th>
                                    <th>Blood Group</th>
                                    <th>Units Collected</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donations as $donation): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($donation['donation_id']); ?></td>
                                        <td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
                                        <td><span class="badge badge-danger"><?php echo htmlspecialchars($donation['blood_group']); ?></span></td>
                                        <td><?php echo $donation['units_collected']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch($donation['donation_status']) {
                                                case 'completed': $status_class = 'badge-success'; break;
                                                case 'pending': $status_class = 'badge-warning'; break;
                                                case 'rejected': $status_class = 'badge-danger'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($donation['donation_status']); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'inventory'): ?>
                <!-- Inventory Page -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Blood Inventory</h1>
                        <p class="page-subtitle">Monitor blood stock levels and expiry dates</p>
                    </div>
                </div>

                <div class="blood-group-grid">
                    <?php foreach ($inventory as $item): ?>
                        <div class="blood-group-card">
                            <div class="blood-group-name"><?php echo htmlspecialchars($item['blood_group']); ?></div>
                            <div class="blood-group-units"><?php echo $item['total_units']; ?> Units</div>
                            <div class="blood-group-expiry">Expires: <?php echo date('M d, Y', strtotime($item['earliest_expiry'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($current_page === 'requests' && in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                <!-- Requests Page -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Blood Requests</h1>
                        <p class="page-subtitle">Manage blood transfusion requests</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('issueBloodModal')">
                        <i class="fas fa-plus"></i> Issue Blood
                    </button>
                </div>

                <div class="content-card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Patient</th>
                                    <th>Blood Group</th>
                                    <th>Units</th>
                                    <th>Doctor</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                        <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                        <td><span class="badge badge-danger"><?php echo htmlspecialchars($request['blood_group']); ?></span></td>
                                        <td><?php echo $request['units_requested']; ?></td>
                                        <td><?php echo htmlspecialchars($request['doctor_name']); ?></td>
                                        <td>
                                            <?php
                                            $urgency_class = '';
                                            switch($request['urgency_level']) {
                                                case 'critical': $urgency_class = 'badge-danger'; break;
                                                case 'urgent': $urgency_class = 'badge-warning'; break;
                                                case 'normal': $urgency_class = 'badge-info'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $urgency_class; ?>"><?php echo ucfirst($request['urgency_level']); ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch($request['request_status']) {
                                                case 'pending': $status_class = 'badge-warning'; break;
                                                case 'approved': $status_class = 'badge-success'; break;
                                                case 'rejected': $status_class = 'badge-danger'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($request['request_status']); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- Access Denied -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Access Denied</h1>
                        <p class="page-subtitle">You don't have permission to access this section</p>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Donor Modal -->
    <div id="addDonorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Blood Donor</h3>
                <button class="close" onclick="closeModal('addDonorModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_donor">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone *</label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Blood Group *</label>
                            <select name="blood_group" class="form-control" required>
                                <option value="">Select Blood Group</option>
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
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" name="date_of_birth" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Gender *</label>
                            <select name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Eligibility Status</label>
                            <select name="eligibility_status" class="form-control">
                                <option value="eligible">Eligible</option>
                                <option value="temporarily_ineligible">Temporarily Ineligible</option>
                                <option value="permanently_ineligible">Permanently Ineligible</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="tel" name="emergency_contact_phone" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Last Donation Date</label>
                            <input type="date" name="last_donation_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Medical History</label>
                            <textarea name="medical_history" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Register Donor
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addDonorModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Donation Modal -->
    <div id="addDonationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Record Blood Donation</h3>
                <button class="close" onclick="closeModal('addDonationModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_donation">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Donor *</label>
                            <select name="donor_id" class="form-control" required>
                                <option value="">Select Donor</option>
                                <?php foreach ($all_donors as $donor): ?>
                                    <option value="<?php echo $donor['id']; ?>"><?php echo htmlspecialchars($donor['name'] . ' (' . $donor['donor_id'] . ') - ' . $donor['blood_group']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Blood Group *</label>
                            <select name="blood_group" class="form-control" required>
                                <option value="">Select Blood Group</option>
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
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Units Collected *</label>
                            <input type="number" name="units_collected" class="form-control" min="1" max="5" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Donation Date *</label>
                            <input type="date" name="donation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Collection Site</label>
                            <input type="text" name="collection_site" class="form-control" value="Main Hospital">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Donation Status</label>
                            <select name="donation_status" class="form-control">
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Hemoglobin Level (g/dL)</label>
                            <input type="number" name="hemoglobin_level" class="form-control" step="0.1" min="0" max="20">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Blood Pressure</label>
                            <input type="text" name="blood_pressure" class="form-control" placeholder="120/80">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Temperature (°F)</label>
                            <input type="number" name="temperature" class="form-control" step="0.1" min="90" max="110">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" name="weight" class="form-control" step="0.1" min="0" max="200">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Record Donation
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addDonationModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Issue Blood Modal -->
    <div id="issueBloodModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Issue Blood</h3>
                <button class="close" onclick="closeModal('issueBloodModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="issue_blood">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Patient *</label>
                            <select name="patient_id" class="form-control" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($all_patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['name'] . ' (' . $patient['patient_id'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Requesting Doctor *</label>
                            <select name="requesting_doctor" class="form-control" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($all_doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Blood Group *</label>
                            <select name="blood_group" class="form-control" required>
                                <option value="">Select Blood Group</option>
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
                            <label class="form-label">Units Requested *</label>
                            <input type="number" name="units_requested" class="form-control" min="1" max="10" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Urgency Level *</label>
                            <select name="urgency_level" class="form-control" required>
                                <option value="">Select Urgency</option>
                                <option value="critical">Critical</option>
                                <option value="urgent">Urgent</option>
                                <option value="normal">Normal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Required Date *</label>
                            <input type="date" name="required_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Request Date *</label>
                        <input type="date" name="request_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Issue Blood
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('issueBloodModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
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
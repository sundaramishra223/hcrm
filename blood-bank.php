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
$user_id = $_SESSION['user_id'];

// Get current page
$current_page = $_GET['page'] ?? 'overview';

// Fetch blood bank statistics
$total_donors_stmt = $db->prepare("SELECT COUNT(*) as count FROM blood_donors WHERE status = 'active'");
$total_donors_stmt->execute();
$total_donors = $total_donors_stmt->fetch()['count'];

$total_donations_stmt = $db->prepare("SELECT COUNT(*) as count FROM blood_donations WHERE status = 'completed'");
$total_donations_stmt->execute();
$total_donations = $total_donations_stmt->fetch()['count'];

$available_units_stmt = $db->prepare("SELECT SUM(available_units) as total FROM blood_inventory WHERE available_units > 0");
$available_units_stmt->execute();
$available_units = $available_units_stmt->fetch()['total'] ?? 0;

$pending_requests_stmt = $db->prepare("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'pending'");
$pending_requests_stmt->execute();
$pending_requests = $pending_requests_stmt->fetch()['count'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_donor'])) {
        $stmt = $db->prepare("
            INSERT INTO blood_donors (donor_id, name, email, phone, blood_group, date_of_birth, gender, address, emergency_contact, medical_history, status, registered_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
        ");
        $donor_id = 'BD' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt->execute([
            $donor_id, $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['blood_group'],
            $_POST['date_of_birth'], $_POST['gender'], $_POST['address'], $_POST['emergency_contact'],
            $_POST['medical_history'], $user_id
        ]);
        $success_message = "Donor registered successfully!";
    }
    
    if (isset($_POST['add_donation'])) {
        $stmt = $db->prepare("
            INSERT INTO blood_donations (donation_id, donor_id, blood_group, units_collected, donation_date, collection_center, staff_id, medical_clearance, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
        ");
        $donation_id = 'DON' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt->execute([
            $donation_id, $_POST['donor_id'], $_POST['blood_group'], $_POST['units_collected'],
            $_POST['donation_date'], $_POST['collection_center'], $user_id, $_POST['medical_clearance']
        ]);
        
        // Update blood inventory
        $inventory_stmt = $db->prepare("
            INSERT INTO blood_inventory (blood_group, total_units, available_units, reserved_units, expired_units, last_updated) 
            VALUES (?, ?, ?, 0, 0, NOW()) 
            ON DUPLICATE KEY UPDATE 
            total_units = total_units + VALUES(total_units), 
            available_units = available_units + VALUES(available_units),
            last_updated = NOW()
        ");
        $inventory_stmt->execute([$_POST['blood_group'], $_POST['units_collected'], $_POST['units_collected']]);
        
        $success_message = "Blood donation recorded successfully!";
    }
    
    if (isset($_POST['add_request'])) {
        $stmt = $db->prepare("
            INSERT INTO blood_requests (request_id, patient_id, blood_group, units_required, urgency_level, required_date, doctor_id, hospital_unit, medical_reason, status, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $request_id = 'REQ' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt->execute([
            $request_id, $_POST['patient_id'], $_POST['blood_group'], $_POST['units_required'],
            $_POST['urgency_level'], $_POST['required_date'], $_POST['doctor_id'], $_POST['hospital_unit'],
            $_POST['medical_reason'], $user_id
        ]);
        $success_message = "Blood request submitted successfully!";
    }
}

// Fetch data based on current page
if ($current_page === 'donors') {
    $donors_stmt = $db->prepare("SELECT * FROM blood_donors ORDER BY created_at DESC LIMIT 50");
    $donors_stmt->execute();
    $donors = $donors_stmt->fetchAll();
}

if ($current_page === 'donations') {
    $donations_stmt = $db->prepare("
        SELECT bd.*, bdr.name as donor_name 
        FROM blood_donations bd 
        LEFT JOIN blood_donors bdr ON bd.donor_id = bdr.id 
        ORDER BY bd.donation_date DESC LIMIT 50
    ");
    $donations_stmt->execute();
    $donations = $donations_stmt->fetchAll();
}

if ($current_page === 'inventory') {
    $inventory_stmt = $db->prepare("SELECT * FROM blood_inventory ORDER BY blood_group");
    $inventory_stmt->execute();
    $inventory = $inventory_stmt->fetchAll();
}

if ($current_page === 'requests') {
    $requests_stmt = $db->prepare("
        SELECT br.*, p.first_name, p.last_name, d.name as doctor_name 
        FROM blood_requests br 
        LEFT JOIN patients p ON br.patient_id = p.id 
        LEFT JOIN doctors d ON br.doctor_id = d.id 
        ORDER BY br.created_at DESC LIMIT 50
    ");
    $requests_stmt->execute();
    $requests = $requests_stmt->fetchAll();
}

// Patient-specific data
if ($user_role === 'patient') {
    $patient_requests_stmt = $db->prepare("
        SELECT br.*, d.name as doctor_name 
        FROM blood_requests br 
        LEFT JOIN doctors d ON br.doctor_id = d.id 
        WHERE br.patient_id = (SELECT id FROM patients WHERE user_id = ?) 
        ORDER BY br.created_at DESC
    ");
    $patient_requests_stmt->execute([$user_id]);
    $patient_requests = $patient_requests_stmt->fetchAll();
    
    $patient_donations_stmt = $db->prepare("
        SELECT bd.* 
        FROM blood_donations bd 
        LEFT JOIN blood_donors bdr ON bd.donor_id = bdr.id 
        WHERE bdr.user_id = ? 
        ORDER BY bd.donation_date DESC
    ");
    $patient_donations_stmt->execute([$user_id]);
    $patient_donations = $patient_donations_stmt->fetchAll();
}

// Fetch dropdown data
$patients_stmt = $db->prepare("SELECT id, first_name, last_name, patient_id FROM patients ORDER BY first_name");
$patients_stmt->execute();
$patients = $patients_stmt->fetchAll();

$doctors_stmt = $db->prepare("SELECT id, name FROM doctors ORDER BY name");
$doctors_stmt->execute();
$doctors = $doctors_stmt->fetchAll();

$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
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
            font-size: 1.5rem;
            margin-bottom: 5px;
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
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
            border-right: 3px solid #fff;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
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

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stat-card.donors i { color: #e74c3c; }
        .stat-card.donations i { color: #3498db; }
        .stat-card.inventory i { color: #2ecc71; }
        .stat-card.requests i { color: #f39c12; }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 5px;
            color: #333;
        }

        .stat-card p {
            color: #666;
            font-size: 0.9rem;
        }

        .content-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background: #5a6fd8;
        }

        .btn-success { background: #2ecc71; }
        .btn-success:hover { background: #27ae60; }

        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
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
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        .form-group {
            margin-bottom: 15px;
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
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.25);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .alert {
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .blood-group-badge {
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .inventory-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: transform 0.2s;
        }

        .inventory-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
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
                <p><?php echo htmlspecialchars($_SESSION['name']); ?></p>
                <small><?php echo ucfirst($user_role); ?></small>
            </div>
            <ul class="sidebar-menu">
                <li><a href="blood-bank.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> Overview
                </a></li>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <li><a href="blood-bank.php?page=donors" class="<?php echo $current_page === 'donors' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Donors
                </a></li>
                <li><a href="blood-bank.php?page=donations" class="<?php echo $current_page === 'donations' ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-heart"></i> Donations
                </a></li>
                <li><a href="blood-bank.php?page=inventory" class="<?php echo $current_page === 'inventory' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i> Inventory
                </a></li>
                <li><a href="blood-bank.php?page=requests" class="<?php echo $current_page === 'requests' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i> Requests
                </a></li>
                <?php endif; ?>
                
                <?php if ($user_role === 'patient'): ?>
                <li><a href="blood-bank.php?page=my-requests" class="<?php echo $current_page === 'my-requests' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i> My Requests
                </a></li>
                <li><a href="blood-bank.php?page=my-donations" class="<?php echo $current_page === 'my-donations' ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-heart"></i> My Donations
                </a></li>
                <li><a href="blood-bank.php?page=donate" class="<?php echo $current_page === 'donate' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle"></i> Become Donor
                </a></li>
                <?php endif; ?>
                
                <li><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($current_page === 'overview'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-tint"></i> Blood Bank Management</h1>
                    <p>Manage blood donations, inventory, and requests</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card donors">
                        <i class="fas fa-users"></i>
                        <h3><?php echo $total_donors; ?></h3>
                        <p>Active Donors</p>
                    </div>
                    <div class="stat-card donations">
                        <i class="fas fa-hand-holding-heart"></i>
                        <h3><?php echo $total_donations; ?></h3>
                        <p>Total Donations</p>
                    </div>
                    <div class="stat-card inventory">
                        <i class="fas fa-warehouse"></i>
                        <h3><?php echo $available_units; ?></h3>
                        <p>Available Units</p>
                    </div>
                    <div class="stat-card requests">
                        <i class="fas fa-clipboard-list"></i>
                        <h3><?php echo $pending_requests; ?></h3>
                        <p>Pending Requests</p>
                    </div>
                </div>

                <?php if ($user_role === 'patient'): ?>
                <div class="content-section">
                    <h2>My Blood Bank Dashboard</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-clipboard-list"></i>
                            <h3><?php echo count($patient_requests ?? []); ?></h3>
                            <p>My Requests</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-hand-holding-heart"></i>
                            <h3><?php echo count($patient_donations ?? []); ?></h3>
                            <p>My Donations</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            <?php elseif ($current_page === 'donors' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-users"></i> Blood Donors</h1>
                    <button class="btn" onclick="openModal('donorModal')">
                        <i class="fas fa-plus"></i> Add New Donor
                    </button>
                </div>

                <div class="content-section">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Donor ID</th>
                                <th>Name</th>
                                <th>Blood Group</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donors as $donor): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($donor['donor_id']); ?></td>
                                <td><?php echo htmlspecialchars($donor['name']); ?></td>
                                <td><span class="blood-group-badge"><?php echo htmlspecialchars($donor['blood_group']); ?></span></td>
                                <td><?php echo htmlspecialchars($donor['phone']); ?></td>
                                <td><?php echo htmlspecialchars($donor['email']); ?></td>
                                <td><span class="badge badge-success"><?php echo ucfirst($donor['status']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($donor['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($current_page === 'donations' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-hand-holding-heart"></i> Blood Donations</h1>
                    <button class="btn" onclick="openModal('donationModal')">
                        <i class="fas fa-plus"></i> Record Donation
                    </button>
                </div>

                <div class="content-section">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Donation ID</th>
                                <th>Donor</th>
                                <th>Blood Group</th>
                                <th>Units</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donations as $donation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($donation['donation_id']); ?></td>
                                <td><?php echo htmlspecialchars($donation['donor_name'] ?? 'N/A'); ?></td>
                                <td><span class="blood-group-badge"><?php echo htmlspecialchars($donation['blood_group']); ?></span></td>
                                <td><?php echo $donation['units_collected']; ?> units</td>
                                <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                <td><span class="badge badge-success"><?php echo ucfirst($donation['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($current_page === 'inventory' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-warehouse"></i> Blood Inventory</h1>
                </div>

                <div class="content-section">
                    <div class="inventory-grid">
                        <?php foreach ($inventory as $item): ?>
                        <div class="inventory-card">
                            <div class="blood-group-badge" style="font-size: 1.2rem; padding: 8px 12px;">
                                <?php echo htmlspecialchars($item['blood_group']); ?>
                            </div>
                            <h3><?php echo $item['available_units']; ?> Units</h3>
                            <p>Available</p>
                            <small>Total: <?php echo $item['total_units']; ?> | Reserved: <?php echo $item['reserved_units']; ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($current_page === 'requests' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-clipboard-list"></i> Blood Requests</h1>
                    <button class="btn" onclick="openModal('requestModal')">
                        <i class="fas fa-plus"></i> New Request
                    </button>
                </div>

                <div class="content-section">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Patient</th>
                                <th>Blood Group</th>
                                <th>Units</th>
                                <th>Urgency</th>
                                <th>Required Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                <td><span class="blood-group-badge"><?php echo htmlspecialchars($request['blood_group']); ?></span></td>
                                <td><?php echo $request['units_required']; ?> units</td>
                                <td>
                                    <span class="badge <?php 
                                        echo $request['urgency_level'] === 'critical' ? 'badge-danger' : 
                                            ($request['urgency_level'] === 'urgent' ? 'badge-warning' : 'badge-info'); 
                                    ?>">
                                        <?php echo ucfirst($request['urgency_level']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['required_date'])); ?></td>
                                <td><span class="badge badge-warning"><?php echo ucfirst($request['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($current_page === 'my-requests' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-clipboard-list"></i> My Blood Requests</h1>
                </div>

                <div class="content-section">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Blood Group</th>
                                <th>Units</th>
                                <th>Urgency</th>
                                <th>Required Date</th>
                                <th>Doctor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patient_requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                <td><span class="blood-group-badge"><?php echo htmlspecialchars($request['blood_group']); ?></span></td>
                                <td><?php echo $request['units_required']; ?> units</td>
                                <td>
                                    <span class="badge <?php 
                                        echo $request['urgency_level'] === 'critical' ? 'badge-danger' : 
                                            ($request['urgency_level'] === 'urgent' ? 'badge-warning' : 'badge-info'); 
                                    ?>">
                                        <?php echo ucfirst($request['urgency_level']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['required_date'])); ?></td>
                                <td><?php echo htmlspecialchars($request['doctor_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge badge-warning"><?php echo ucfirst($request['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($current_page === 'my-donations' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-hand-holding-heart"></i> My Donations</h1>
                </div>

                <div class="content-section">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Donation ID</th>
                                <th>Blood Group</th>
                                <th>Units</th>
                                <th>Date</th>
                                <th>Collection Center</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patient_donations as $donation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($donation['donation_id']); ?></td>
                                <td><span class="blood-group-badge"><?php echo htmlspecialchars($donation['blood_group']); ?></span></td>
                                <td><?php echo $donation['units_collected']; ?> units</td>
                                <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                <td><?php echo htmlspecialchars($donation['collection_center']); ?></td>
                                <td><span class="badge badge-success"><?php echo ucfirst($donation['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </main>
    </div>

    <!-- Add Donor Modal -->
    <div id="donorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Donor</h2>
                <span class="close" onclick="closeModal('donorModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Blood Group *</label>
                        <select name="blood_group" class="form-control" required>
                            <option value="">Select Blood Group</option>
                            <?php foreach ($blood_groups as $group): ?>
                                <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth *</label>
                        <input type="date" name="date_of_birth" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Gender *</label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Emergency Contact</label>
                    <input type="tel" name="emergency_contact" class="form-control">
                </div>
                <div class="form-group">
                    <label>Medical History</label>
                    <textarea name="medical_history" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="add_donor" class="btn btn-success">
                    <i class="fas fa-save"></i> Register Donor
                </button>
            </form>
        </div>
    </div>

    <!-- Add Donation Modal -->
    <div id="donationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Record Blood Donation</h2>
                <span class="close" onclick="closeModal('donationModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Donor ID *</label>
                        <input type="number" name="donor_id" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Blood Group *</label>
                        <select name="blood_group" class="form-control" required>
                            <option value="">Select Blood Group</option>
                            <?php foreach ($blood_groups as $group): ?>
                                <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Units Collected *</label>
                        <input type="number" name="units_collected" class="form-control" min="1" max="10" required>
                    </div>
                    <div class="form-group">
                        <label>Donation Date *</label>
                        <input type="date" name="donation_date" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Collection Center *</label>
                    <input type="text" name="collection_center" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Medical Clearance</label>
                    <textarea name="medical_clearance" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="add_donation" class="btn btn-success">
                    <i class="fas fa-save"></i> Record Donation
                </button>
            </form>
        </div>
    </div>

    <!-- Add Request Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>New Blood Request</h2>
                <span class="close" onclick="closeModal('requestModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Patient *</label>
                        <select name="patient_id" class="form-control" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Blood Group *</label>
                        <select name="blood_group" class="form-control" required>
                            <option value="">Select Blood Group</option>
                            <?php foreach ($blood_groups as $group): ?>
                                <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Units Required *</label>
                        <input type="number" name="units_required" class="form-control" min="1" max="20" required>
                    </div>
                    <div class="form-group">
                        <label>Urgency Level *</label>
                        <select name="urgency_level" class="form-control" required>
                            <option value="">Select Urgency</option>
                            <option value="normal">Normal</option>
                            <option value="urgent">Urgent</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Required Date *</label>
                        <input type="date" name="required_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Doctor *</label>
                        <select name="doctor_id" class="form-control" required>
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    <?php echo htmlspecialchars($doctor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Hospital Unit</label>
                    <input type="text" name="hospital_unit" class="form-control">
                </div>
                <div class="form-group">
                    <label>Medical Reason</label>
                    <textarea name="medical_reason" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="add_request" class="btn btn-success">
                    <i class="fas fa-save"></i> Submit Request
                </button>
            </form>
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
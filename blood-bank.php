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

// Check role permissions
$allowed_roles = ['admin', 'doctor', 'nurse', 'receptionist', 'patient'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

// Fetch blood bank statistics
$stats = [];
if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
    // Total blood units
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM blood_inventory WHERE expiry_date > CURDATE()");
    $stmt->execute();
    $stats['total_units'] = $stmt->fetch()['total'];

    // Available units by blood group
    $stmt = $db->prepare("SELECT blood_group, SUM(units_available) as total FROM blood_inventory WHERE expiry_date > CURDATE() AND units_available > 0 GROUP BY blood_group");
    $stmt->execute();
    $stats['by_blood_group'] = $stmt->fetchAll();

    // Recent donations
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM blood_donations WHERE donation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute();
    $stats['recent_donations'] = $stmt->fetch()['total'];

    // Pending requests
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM blood_requests WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_requests'] = $stmt->fetch()['total'];
}

// Fetch patient-specific data if patient
$patient_data = [];
if ($user_role === 'patient') {
    // Get patient ID
    $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch();
    
    if ($patient) {
        $patient_id = $patient['id'];
        
        // Patient's donations
        $stmt = $db->prepare("SELECT bd.*, p.first_name, p.last_name FROM blood_donations bd JOIN patients p ON bd.donor_patient_id = p.id WHERE bd.donor_patient_id = ? ORDER BY bd.donation_date DESC");
        $stmt->execute([$patient_id]);
        $patient_data['donations'] = $stmt->fetchAll();
        
        // Patient's requests
        $stmt = $db->prepare("SELECT br.*, p.first_name, p.last_name FROM blood_requests br JOIN patients p ON br.patient_id = p.id WHERE br.patient_id = ? ORDER BY br.request_date DESC");
        $stmt->execute([$patient_id]);
        $patient_data['requests'] = $stmt->fetchAll();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_donation':
                if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
                    $donor_patient_id = $_POST['donor_patient_id'];
                    $blood_group = $_POST['blood_group'];
                    $units_collected = $_POST['units_collected'];
                    $donation_date = $_POST['donation_date'];
                    $notes = $_POST['notes'] ?? '';
                    
                    $stmt = $db->prepare("INSERT INTO blood_donations (donor_patient_id, blood_group, units_collected, donation_date, status, notes, created_by) VALUES (?, ?, ?, ?, 'completed', ?, ?)");
                    $stmt->execute([$donor_patient_id, $blood_group, $units_collected, $donation_date, $notes, $user_id]);
                    
                    // Update inventory
                    $expiry_date = date('Y-m-d', strtotime($donation_date . ' + 35 days'));
                    $stmt = $db->prepare("INSERT INTO blood_inventory (blood_group, units_available, collection_date, expiry_date, source_donation_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$blood_group, $units_collected, $donation_date, $expiry_date, $db->lastInsertId()]);
                    
                    $success_message = "Blood donation recorded successfully!";
                }
                break;
                
            case 'add_request':
                if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
                    $patient_id = $_POST['patient_id'];
                    $blood_group = $_POST['blood_group'];
                    $units_needed = $_POST['units_needed'];
                    $urgency = $_POST['urgency'];
                    $notes = $_POST['notes'] ?? '';
                    
                    $stmt = $db->prepare("INSERT INTO blood_requests (patient_id, blood_group, units_needed, urgency, status, notes, created_by) VALUES (?, ?, ?, ?, 'pending', ?, ?)");
                    $stmt->execute([$patient_id, $blood_group, $units_needed, $urgency, $notes, $user_id]);
                    
                    $success_message = "Blood request created successfully!";
                }
                break;
        }
    }
}

// Fetch data based on current page
$page_data = [];
switch ($current_page) {
    case 'donations':
        if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
            $stmt = $db->prepare("SELECT bd.*, p.first_name, p.last_name, p.phone, u.username as created_by_name FROM blood_donations bd JOIN patients p ON bd.donor_patient_id = p.id LEFT JOIN users u ON bd.created_by = u.id ORDER BY bd.donation_date DESC LIMIT 50");
            $stmt->execute();
            $page_data['donations'] = $stmt->fetchAll();
        }
        break;
        
    case 'inventory':
        if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
            $stmt = $db->prepare("SELECT * FROM blood_inventory WHERE expiry_date > CURDATE() ORDER BY blood_group, expiry_date ASC");
            $stmt->execute();
            $page_data['inventory'] = $stmt->fetchAll();
        }
        break;
        
    case 'requests':
        if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
            $stmt = $db->prepare("SELECT br.*, p.first_name, p.last_name, p.phone, u.username as created_by_name FROM blood_requests br JOIN patients p ON br.patient_id = p.id LEFT JOIN users u ON br.created_by = u.id ORDER BY br.request_date DESC LIMIT 50");
            $stmt->execute();
            $page_data['requests'] = $stmt->fetchAll();
        }
        break;
}

// Get patients for dropdowns
$patients = [];
if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
    $stmt = $db->prepare("SELECT id, first_name, last_name, blood_group FROM patients ORDER BY first_name, last_name");
    $stmt->execute();
    $patients = $stmt->fetchAll();
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
            background: rgba(255,255,255,0.1);
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

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .content-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
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
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

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
            max-width: 500px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
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

        .blood-group-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .blood-group-card {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .blood-group-card h4 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .badge-info {
            background-color: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-tint"></i> Blood Bank</h3>
                <p><?php echo ucfirst($user_role); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="blood-bank.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Overview</a></li>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <li><a href="blood-bank.php?page=donations" class="<?php echo $current_page === 'donations' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-heart"></i> Donations</a></li>
                <li><a href="blood-bank.php?page=inventory" class="<?php echo $current_page === 'inventory' ? 'active' : ''; ?>"><i class="fas fa-warehouse"></i> Inventory</a></li>
                <li><a href="blood-bank.php?page=requests" class="<?php echo $current_page === 'requests' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> Requests</a></li>
                <?php endif; ?>
                
                <?php if ($user_role === 'patient'): ?>
                <li><a href="blood-bank.php?page=my-donations" class="<?php echo $current_page === 'my-donations' ? 'active' : ''; ?>"><i class="fas fa-heart"></i> My Donations</a></li>
                <li><a href="blood-bank.php?page=my-requests" class="<?php echo $current_page === 'my-requests' ? 'active' : ''; ?>"><i class="fas fa-list"></i> My Requests</a></li>
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

                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-tint" style="color: #dc3545;"></i>
                            <h3><?php echo $stats['total_units']; ?></h3>
                            <p>Available Blood Units</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-hand-holding-heart" style="color: #28a745;"></i>
                            <h3><?php echo $stats['recent_donations']; ?></h3>
                            <p>Donations This Month</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-clipboard-list" style="color: #ffc107;"></i>
                            <h3><?php echo $stats['pending_requests']; ?></h3>
                            <p>Pending Requests</p>
                        </div>
                    </div>

                    <div class="content-card">
                        <h3>Blood Group Availability</h3>
                        <div class="blood-group-grid">
                            <?php
                            $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            $availability = [];
                            foreach ($stats['by_blood_group'] as $group) {
                                $availability[$group['blood_group']] = $group['total'];
                            }
                            
                            foreach ($blood_groups as $group):
                                $units = $availability[$group] ?? 0;
                            ?>
                                <div class="blood-group-card">
                                    <h4><?php echo $group; ?></h4>
                                    <p><?php echo $units; ?> units</p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="content-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3>Quick Actions</h3>
                        </div>
                        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                            <button class="btn btn-primary" onclick="openModal('donationModal')">
                                <i class="fas fa-plus"></i> Record Donation
                            </button>
                            <button class="btn btn-success" onclick="openModal('requestModal')">
                                <i class="fas fa-plus"></i> Create Request
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($user_role === 'patient'): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-heart" style="color: #dc3545;"></i>
                            <h3><?php echo count($patient_data['donations']); ?></h3>
                            <p>My Donations</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-list" style="color: #17a2b8;"></i>
                            <h3><?php echo count($patient_data['requests']); ?></h3>
                            <p>My Requests</p>
                        </div>
                    </div>

                    <div class="content-card">
                        <h3>Recent Donations</h3>
                        <?php if (!empty($patient_data['donations'])): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Blood Group</th>
                                            <th>Units</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($patient_data['donations'], 0, 5) as $donation): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                                <td><strong><?php echo $donation['blood_group']; ?></strong></td>
                                                <td><?php echo $donation['units_collected']; ?></td>
                                                <td><span class="badge badge-success"><?php echo ucfirst($donation['status']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p>No donations recorded yet.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($current_page === 'donations' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-hand-holding-heart"></i> Blood Donations</h1>
                    <button class="btn btn-primary" onclick="openModal('donationModal')" style="float: right;">
                        <i class="fas fa-plus"></i> Record New Donation
                    </button>
                </div>

                <div class="content-card">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Donor</th>
                                    <th>Phone</th>
                                    <th>Blood Group</th>
                                    <th>Units</th>
                                    <th>Status</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($page_data['donations'] as $donation): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($donation['first_name'] . ' ' . $donation['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($donation['phone']); ?></td>
                                        <td><strong><?php echo $donation['blood_group']; ?></strong></td>
                                        <td><?php echo $donation['units_collected']; ?></td>
                                        <td><span class="badge badge-success"><?php echo ucfirst($donation['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($donation['created_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'inventory' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-warehouse"></i> Blood Inventory</h1>
                </div>

                <div class="content-card">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Blood Group</th>
                                    <th>Units Available</th>
                                    <th>Collection Date</th>
                                    <th>Expiry Date</th>
                                    <th>Days to Expiry</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($page_data['inventory'] as $item): 
                                    $days_to_expiry = (strtotime($item['expiry_date']) - time()) / (60 * 60 * 24);
                                    $status_class = $days_to_expiry <= 7 ? 'badge-danger' : ($days_to_expiry <= 14 ? 'badge-warning' : 'badge-success');
                                ?>
                                    <tr>
                                        <td><strong><?php echo $item['blood_group']; ?></strong></td>
                                        <td><?php echo $item['units_available']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($item['collection_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($item['expiry_date'])); ?></td>
                                        <td><?php echo floor($days_to_expiry); ?> days</td>
                                        <td><span class="badge <?php echo $status_class; ?>">
                                            <?php echo $days_to_expiry <= 7 ? 'Expiring Soon' : ($days_to_expiry <= 14 ? 'Monitor' : 'Good'); ?>
                                        </span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'requests' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-clipboard-list"></i> Blood Requests</h1>
                    <button class="btn btn-success" onclick="openModal('requestModal')" style="float: right;">
                        <i class="fas fa-plus"></i> Create New Request
                    </button>
                </div>

                <div class="content-card">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Phone</th>
                                    <th>Blood Group</th>
                                    <th>Units Needed</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($page_data['requests'] as $request): 
                                    $urgency_class = $request['urgency'] === 'critical' ? 'badge-danger' : ($request['urgency'] === 'high' ? 'badge-warning' : 'badge-info');
                                    $status_class = $request['status'] === 'fulfilled' ? 'badge-success' : ($request['status'] === 'pending' ? 'badge-warning' : 'badge-danger');
                                ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($request['phone']); ?></td>
                                        <td><strong><?php echo $request['blood_group']; ?></strong></td>
                                        <td><?php echo $request['units_needed']; ?></td>
                                        <td><span class="badge <?php echo $urgency_class; ?>"><?php echo ucfirst($request['urgency']); ?></span></td>
                                        <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($request['created_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'my-donations' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-heart"></i> My Donations</h1>
                </div>

                <div class="content-card">
                    <?php if (!empty($patient_data['donations'])): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Blood Group</th>
                                        <th>Units Donated</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patient_data['donations'] as $donation): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                            <td><strong><?php echo $donation['blood_group']; ?></strong></td>
                                            <td><?php echo $donation['units_collected']; ?></td>
                                            <td><span class="badge badge-success"><?php echo ucfirst($donation['status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($donation['notes']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>You haven't made any blood donations yet.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($current_page === 'my-requests' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-list"></i> My Blood Requests</h1>
                </div>

                <div class="content-card">
                    <?php if (!empty($patient_data['requests'])): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Blood Group</th>
                                        <th>Units Needed</th>
                                        <th>Urgency</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patient_data['requests'] as $request): 
                                        $urgency_class = $request['urgency'] === 'critical' ? 'badge-danger' : ($request['urgency'] === 'high' ? 'badge-warning' : 'badge-info');
                                        $status_class = $request['status'] === 'fulfilled' ? 'badge-success' : ($request['status'] === 'pending' ? 'badge-warning' : 'badge-danger');
                                    ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                            <td><strong><?php echo $request['blood_group']; ?></strong></td>
                                            <td><?php echo $request['units_needed']; ?></td>
                                            <td><span class="badge <?php echo $urgency_class; ?>"><?php echo ucfirst($request['urgency']); ?></span></td>
                                            <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($request['notes']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>You haven't made any blood requests yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Donation Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
    <div id="donationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('donationModal')">&times;</span>
            <h2>Record Blood Donation</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_donation">
                
                <div class="form-group">
                    <label for="donor_patient_id">Donor Patient:</label>
                    <select name="donor_patient_id" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['blood_group'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="blood_group">Blood Group:</label>
                    <select name="blood_group" required>
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
                    <label for="units_collected">Units Collected:</label>
                    <input type="number" name="units_collected" min="1" max="10" required>
                </div>
                
                <div class="form-group">
                    <label for="donation_date">Donation Date:</label>
                    <input type="date" name="donation_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Record Donation</button>
            </form>
        </div>
    </div>

    <!-- Request Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('requestModal')">&times;</span>
            <h2>Create Blood Request</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_request">
                
                <div class="form-group">
                    <label for="patient_id">Patient:</label>
                    <select name="patient_id" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['blood_group'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="blood_group">Blood Group Needed:</label>
                    <select name="blood_group" required>
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
                    <label for="units_needed">Units Needed:</label>
                    <input type="number" name="units_needed" min="1" max="20" required>
                </div>
                
                <div class="form-group">
                    <label for="urgency">Urgency:</label>
                    <select name="urgency" required>
                        <option value="">Select Urgency</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-success">Create Request</button>
            </form>
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
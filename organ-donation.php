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

// Fetch organ donation statistics
$total_donors_stmt = $db->prepare("SELECT COUNT(*) as count FROM organ_donors WHERE status = 'active'");
$total_donors_stmt->execute();
$total_donors = $total_donors_stmt->fetch()['count'];

$available_organs_stmt = $db->prepare("SELECT COUNT(*) as count FROM organ_inventory WHERE status = 'available'");
$available_organs_stmt->execute();
$available_organs = $available_organs_stmt->fetch()['count'];

$pending_requests_stmt = $db->prepare("SELECT COUNT(*) as count FROM organ_requests WHERE status = 'pending'");
$pending_requests_stmt->execute();
$pending_requests = $pending_requests_stmt->fetch()['count'];

$successful_transplants_stmt = $db->prepare("SELECT COUNT(*) as count FROM organ_transplants WHERE status = 'completed'");
$successful_transplants_stmt->execute();
$successful_transplants = $successful_transplants_stmt->fetch()['count'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register_donor'])) {
        $stmt = $db->prepare("
            INSERT INTO organ_donors (donor_id, name, email, phone, blood_group, date_of_birth, gender, address, emergency_contact, organs_to_donate, medical_history, consent_date, status, registered_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
        ");
        $donor_id = 'OD' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $organs_to_donate = implode(',', $_POST['organs_to_donate']);
        $stmt->execute([
            $donor_id, $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['blood_group'],
            $_POST['date_of_birth'], $_POST['gender'], $_POST['address'], $_POST['emergency_contact'],
            $organs_to_donate, $_POST['medical_history'], $_POST['consent_date'], $user_id
        ]);
        $success_message = "Organ donor registered successfully!";
    }
    
    if (isset($_POST['add_organ'])) {
        $stmt = $db->prepare("
            INSERT INTO organ_inventory (organ_id, donor_id, organ_type, blood_group, harvest_date, expiry_date, hospital_location, medical_condition, status, recorded_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, NOW())
        ");
        $organ_id = 'ORG' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt->execute([
            $organ_id, $_POST['donor_id'], $_POST['organ_type'], $_POST['blood_group'],
            $_POST['harvest_date'], $_POST['expiry_date'], $_POST['hospital_location'],
            $_POST['medical_condition'], $user_id
        ]);
        $success_message = "Organ added to inventory successfully!";
    }
    
    if (isset($_POST['add_request'])) {
        $stmt = $db->prepare("
            INSERT INTO organ_requests (request_id, patient_id, organ_type, blood_group, urgency_level, required_date, doctor_id, hospital_unit, medical_reason, status, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        $request_id = 'OREQ' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt->execute([
            $request_id, $_POST['patient_id'], $_POST['organ_type'], $_POST['blood_group'],
            $_POST['urgency_level'], $_POST['required_date'], $_POST['doctor_id'], $_POST['hospital_unit'],
            $_POST['medical_reason'], $user_id
        ]);
        $success_message = "Organ request submitted successfully!";
    }
    
    if (isset($_POST['record_transplant'])) {
        $stmt = $db->prepare("
            INSERT INTO organ_transplants (transplant_id, organ_id, recipient_id, donor_id, surgeon_id, transplant_date, hospital_location, surgery_duration, status, notes, recorded_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, NOW())
        ");
        $transplant_id = 'TXP' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt->execute([
            $transplant_id, $_POST['organ_id'], $_POST['recipient_id'], $_POST['donor_id'],
            $_POST['surgeon_id'], $_POST['transplant_date'], $_POST['hospital_location'],
            $_POST['surgery_duration'], $_POST['notes'], $user_id
        ]);
        
        // Update organ status
        $update_stmt = $db->prepare("UPDATE organ_inventory SET status = 'transplanted' WHERE id = ?");
        $update_stmt->execute([$_POST['organ_id']]);
        
        $success_message = "Transplant recorded successfully!";
    }
}

// Fetch data based on current page
if ($current_page === 'donors') {
    $donors_stmt = $db->prepare("SELECT * FROM organ_donors ORDER BY created_at DESC LIMIT 50");
    $donors_stmt->execute();
    $donors = $donors_stmt->fetchAll();
}

if ($current_page === 'inventory') {
    $inventory_stmt = $db->prepare("
        SELECT oi.*, od.name as donor_name 
        FROM organ_inventory oi 
        LEFT JOIN organ_donors od ON oi.donor_id = od.id 
        ORDER BY oi.harvest_date DESC LIMIT 50
    ");
    $inventory_stmt->execute();
    $inventory = $inventory_stmt->fetchAll();
}

if ($current_page === 'requests') {
    $requests_stmt = $db->prepare("
        SELECT or_req.*, p.first_name, p.last_name, d.name as doctor_name 
        FROM organ_requests or_req 
        LEFT JOIN patients p ON or_req.patient_id = p.id 
        LEFT JOIN doctors d ON or_req.doctor_id = d.id 
        ORDER BY or_req.created_at DESC LIMIT 50
    ");
    $requests_stmt->execute();
    $requests = $requests_stmt->fetchAll();
}

if ($current_page === 'transplants') {
    $transplants_stmt = $db->prepare("
        SELECT ot.*, p.first_name, p.last_name, od.name as donor_name, d.name as surgeon_name, oi.organ_type 
        FROM organ_transplants ot 
        LEFT JOIN patients p ON ot.recipient_id = p.id 
        LEFT JOIN organ_donors od ON ot.donor_id = od.id 
        LEFT JOIN doctors d ON ot.surgeon_id = d.id 
        LEFT JOIN organ_inventory oi ON ot.organ_id = oi.id 
        ORDER BY ot.transplant_date DESC LIMIT 50
    ");
    $transplants_stmt->execute();
    $transplants = $transplants_stmt->fetchAll();
}

// Patient-specific data
if ($user_role === 'patient') {
    $patient_requests_stmt = $db->prepare("
        SELECT or_req.*, d.name as doctor_name 
        FROM organ_requests or_req 
        LEFT JOIN doctors d ON or_req.doctor_id = d.id 
        WHERE or_req.patient_id = (SELECT id FROM patients WHERE user_id = ?) 
        ORDER BY or_req.created_at DESC
    ");
    $patient_requests_stmt->execute([$user_id]);
    $patient_requests = $patient_requests_stmt->fetchAll();
    
    $patient_transplants_stmt = $db->prepare("
        SELECT ot.*, od.name as donor_name, d.name as surgeon_name, oi.organ_type 
        FROM organ_transplants ot 
        LEFT JOIN organ_donors od ON ot.donor_id = od.id 
        LEFT JOIN doctors d ON ot.surgeon_id = d.id 
        LEFT JOIN organ_inventory oi ON ot.organ_id = oi.id 
        WHERE ot.recipient_id = (SELECT id FROM patients WHERE user_id = ?) 
        ORDER BY ot.transplant_date DESC
    ");
    $patient_transplants_stmt->execute([$user_id]);
    $patient_transplants = $patient_transplants_stmt->fetchAll();
}

// Fetch dropdown data
$patients_stmt = $db->prepare("SELECT id, first_name, last_name, patient_id FROM patients ORDER BY first_name");
$patients_stmt->execute();
$patients = $patients_stmt->fetchAll();

$doctors_stmt = $db->prepare("SELECT id, name FROM doctors ORDER BY name");
$doctors_stmt->execute();
$doctors = $doctors_stmt->fetchAll();

$organ_donors_stmt = $db->prepare("SELECT id, donor_id, name FROM organ_donors WHERE status = 'active' ORDER BY name");
$organ_donors_stmt->execute();
$organ_donors = $organ_donors_stmt->fetchAll();

$available_organs_list_stmt = $db->prepare("SELECT id, organ_id, organ_type, blood_group FROM organ_inventory WHERE status = 'available' ORDER BY harvest_date");
$available_organs_list_stmt->execute();
$available_organs_list = $available_organs_list_stmt->fetchAll();

$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$organ_types = ['Heart', 'Liver', 'Kidney', 'Lung', 'Pancreas', 'Cornea', 'Skin', 'Bone', 'Heart Valve', 'Small Intestine'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organ Donation Management - Hospital CRM</title>
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
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .stat-card.donors i { color: #e67e22; }
        .stat-card.organs i { color: #27ae60; }
        .stat-card.requests i { color: #e74c3c; }
        .stat-card.transplants i { color: #8e44ad; }

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

        .btn {
            background: #3498db;
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
            background: #2980b9;
        }

        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }

        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }

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
        .badge-secondary { background: #d6d8db; color: #383d41; }

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
            justify-content: space-between;
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
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.25);
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

        .organ-badge {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .blood-group-badge {
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 5px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
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
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-heart"></i> Organ Donation</h3>
                <p><?php echo htmlspecialchars($_SESSION['name']); ?></p>
                <small><?php echo ucfirst($user_role); ?></small>
            </div>
            <ul class="sidebar-menu">
                <li><a href="organ-donation.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> Overview
                </a></li>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <li><a href="organ-donation.php?page=donors" class="<?php echo $current_page === 'donors' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Donors
                </a></li>
                <li><a href="organ-donation.php?page=inventory" class="<?php echo $current_page === 'inventory' ? 'active' : ''; ?>">
                    <i class="fas fa-warehouse"></i> Organ Inventory
                </a></li>
                <li><a href="organ-donation.php?page=requests" class="<?php echo $current_page === 'requests' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i> Requests
                </a></li>
                <li><a href="organ-donation.php?page=transplants" class="<?php echo $current_page === 'transplants' ? 'active' : ''; ?>">
                    <i class="fas fa-procedures"></i> Transplants
                </a></li>
                <?php endif; ?>
                
                <?php if ($user_role === 'patient'): ?>
                <li><a href="organ-donation.php?page=my-requests" class="<?php echo $current_page === 'my-requests' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i> My Requests
                </a></li>
                <li><a href="organ-donation.php?page=my-transplants" class="<?php echo $current_page === 'my-transplants' ? 'active' : ''; ?>">
                    <i class="fas fa-procedures"></i> My Transplants
                </a></li>
                <li><a href="organ-donation.php?page=register-donor" class="<?php echo $current_page === 'register-donor' ? 'active' : ''; ?>">
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
                    <div>
                        <h1><i class="fas fa-heart"></i> Organ Donation Management</h1>
                        <p>Manage organ donations, inventory, and transplants</p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card donors">
                        <i class="fas fa-users"></i>
                        <h3><?php echo $total_donors; ?></h3>
                        <p>Active Donors</p>
                    </div>
                    <div class="stat-card organs">
                        <i class="fas fa-heart"></i>
                        <h3><?php echo $available_organs; ?></h3>
                        <p>Available Organs</p>
                    </div>
                    <div class="stat-card requests">
                        <i class="fas fa-clipboard-list"></i>
                        <h3><?php echo $pending_requests; ?></h3>
                        <p>Pending Requests</p>
                    </div>
                    <div class="stat-card transplants">
                        <i class="fas fa-procedures"></i>
                        <h3><?php echo $successful_transplants; ?></h3>
                        <p>Successful Transplants</p>
                    </div>
                </div>

                <?php if ($user_role === 'patient'): ?>
                <div class="content-section">
                    <h2>My Organ Donation Dashboard</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-clipboard-list"></i>
                            <h3><?php echo count($patient_requests ?? []); ?></h3>
                            <p>My Requests</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-procedures"></i>
                            <h3><?php echo count($patient_transplants ?? []); ?></h3>
                            <p>My Transplants</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            <?php elseif ($current_page === 'donors' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-users"></i> Organ Donors</h1>
                    <button class="btn" onclick="openModal('donorModal')">
                        <i class="fas fa-plus"></i> Register New Donor
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
                                <th>Organs to Donate</th>
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
                                <td>
                                    <?php 
                                    $organs = explode(',', $donor['organs_to_donate']);
                                    foreach (array_slice($organs, 0, 3) as $organ): ?>
                                        <span class="organ-badge"><?php echo trim($organ); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($organs) > 3): ?>
                                        <small>+<?php echo count($organs) - 3; ?> more</small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-success"><?php echo ucfirst($donor['status']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($donor['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($current_page === 'inventory' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-warehouse"></i> Organ Inventory</h1>
                    <button class="btn" onclick="openModal('organModal')">
                        <i class="fas fa-plus"></i> Add Organ
                    </button>
                </div>

                <div class="content-section">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Organ ID</th>
                                <th>Organ Type</th>
                                <th>Donor</th>
                                <th>Blood Group</th>
                                <th>Harvest Date</th>
                                <th>Expiry Date</th>
                                <th>Location</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inventory as $organ): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($organ['organ_id']); ?></td>
                                <td><span class="organ-badge"><?php echo htmlspecialchars($organ['organ_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($organ['donor_name'] ?? 'N/A'); ?></td>
                                <td><span class="blood-group-badge"><?php echo htmlspecialchars($organ['blood_group']); ?></span></td>
                                <td><?php echo date('M d, Y', strtotime($organ['harvest_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($organ['expiry_date'])); ?></td>
                                <td><?php echo htmlspecialchars($organ['hospital_location']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $organ['status'] === 'available' ? 'badge-success' : 
                                            ($organ['status'] === 'transplanted' ? 'badge-info' : 'badge-warning'); 
                                    ?>">
                                        <?php echo ucfirst($organ['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($current_page === 'requests' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-clipboard-list"></i> Organ Requests</h1>
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
                                <th>Organ Type</th>
                                <th>Blood Group</th>
                                <th>Urgency</th>
                                <th>Required Date</th>
                                <th>Doctor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                <td><span class="organ-badge"><?php echo htmlspecialchars($request['organ_type']); ?></span></td>
                                <td><span class="blood-group-badge"><?php echo htmlspecialchars($request['blood_group']); ?></span></td>
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

            <?php elseif ($current_page === 'transplants' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-procedures"></i> Organ Transplants</h1>
                    <button class="btn" onclick="openModal('transplantModal')">
                        <i class="fas fa-plus"></i> Record Transplant
                    </button>
                </div>

                <div class="content-section">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Transplant ID</th>
                                <th>Recipient</th>
                                <th>Organ Type</th>
                                <th>Donor</th>
                                <th>Surgeon</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transplants as $transplant): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transplant['transplant_id']); ?></td>
                                <td><?php echo htmlspecialchars($transplant['first_name'] . ' ' . $transplant['last_name']); ?></td>
                                <td><span class="organ-badge"><?php echo htmlspecialchars($transplant['organ_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($transplant['donor_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transplant['surgeon_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($transplant['transplant_date'])); ?></td>
                                <td><span class="badge badge-success"><?php echo ucfirst($transplant['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($current_page === 'my-requests' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-clipboard-list"></i> My Organ Requests</h1>
                </div>

                <div class="content-section">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Organ Type</th>
                                <th>Blood Group</th>
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
                                <td><span class="organ-badge"><?php echo htmlspecialchars($request['organ_type']); ?></span></td>
                                <td><span class="blood-group-badge"><?php echo htmlspecialchars($request['blood_group']); ?></span></td>
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

            <?php elseif ($current_page === 'my-transplants' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-procedures"></i> My Transplants</h1>
                </div>

                <div class="content-section">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Transplant ID</th>
                                <th>Organ Type</th>
                                <th>Donor</th>
                                <th>Surgeon</th>
                                <th>Date</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patient_transplants as $transplant): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transplant['transplant_id']); ?></td>
                                <td><span class="organ-badge"><?php echo htmlspecialchars($transplant['organ_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($transplant['donor_name'] ?? 'Anonymous'); ?></td>
                                <td><?php echo htmlspecialchars($transplant['surgeon_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($transplant['transplant_date'])); ?></td>
                                <td><?php echo htmlspecialchars($transplant['surgery_duration']); ?></td>
                                <td><span class="badge badge-success"><?php echo ucfirst($transplant['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </main>
    </div>

    <!-- Register Donor Modal -->
    <div id="donorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Register Organ Donor</h2>
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
                    <label>Organs to Donate *</label>
                    <div class="checkbox-group">
                        <?php foreach ($organ_types as $organ): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="organs_to_donate[]" value="<?php echo $organ; ?>" id="organ_<?php echo str_replace(' ', '_', $organ); ?>">
                                <label for="organ_<?php echo str_replace(' ', '_', $organ); ?>"><?php echo $organ; ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Medical History</label>
                    <textarea name="medical_history" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Consent Date *</label>
                    <input type="date" name="consent_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <button type="submit" name="register_donor" class="btn btn-success">
                    <i class="fas fa-save"></i> Register Donor
                </button>
            </form>
        </div>
    </div>

    <!-- Add Organ Modal -->
    <div id="organModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Organ to Inventory</h2>
                <span class="close" onclick="closeModal('organModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Donor *</label>
                        <select name="donor_id" class="form-control" required>
                            <option value="">Select Donor</option>
                            <?php foreach ($organ_donors as $donor): ?>
                                <option value="<?php echo $donor['id']; ?>">
                                    <?php echo htmlspecialchars($donor['name'] . ' (' . $donor['donor_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Organ Type *</label>
                        <select name="organ_type" class="form-control" required>
                            <option value="">Select Organ Type</option>
                            <?php foreach ($organ_types as $organ): ?>
                                <option value="<?php echo $organ; ?>"><?php echo $organ; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Blood Group *</label>
                        <select name="blood_group" class="form-control" required>
                            <option value="">Select Blood Group</option>
                            <?php foreach ($blood_groups as $group): ?>
                                <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Hospital Location *</label>
                        <input type="text" name="hospital_location" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Harvest Date *</label>
                        <input type="date" name="harvest_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Expiry Date *</label>
                        <input type="date" name="expiry_date" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Medical Condition</label>
                    <textarea name="medical_condition" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="add_organ" class="btn btn-success">
                    <i class="fas fa-save"></i> Add Organ
                </button>
            </form>
        </div>
    </div>

    <!-- Add Request Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>New Organ Request</h2>
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
                        <label>Organ Type *</label>
                        <select name="organ_type" class="form-control" required>
                            <option value="">Select Organ Type</option>
                            <?php foreach ($organ_types as $organ): ?>
                                <option value="<?php echo $organ; ?>"><?php echo $organ; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Blood Group *</label>
                        <select name="blood_group" class="form-control" required>
                            <option value="">Select Blood Group</option>
                            <?php foreach ($blood_groups as $group): ?>
                                <option value="<?php echo $group; ?>"><?php echo $group; ?></option>
                            <?php endforeach; ?>
                        </select>
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

    <!-- Record Transplant Modal -->
    <div id="transplantModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Record Organ Transplant</h2>
                <span class="close" onclick="closeModal('transplantModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Available Organ *</label>
                        <select name="organ_id" class="form-control" required>
                            <option value="">Select Organ</option>
                            <?php foreach ($available_organs_list as $organ): ?>
                                <option value="<?php echo $organ['id']; ?>">
                                    <?php echo htmlspecialchars($organ['organ_type'] . ' (' . $organ['organ_id'] . ') - ' . $organ['blood_group']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Recipient *</label>
                        <select name="recipient_id" class="form-control" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Donor *</label>
                        <select name="donor_id" class="form-control" required>
                            <option value="">Select Donor</option>
                            <?php foreach ($organ_donors as $donor): ?>
                                <option value="<?php echo $donor['id']; ?>">
                                    <?php echo htmlspecialchars($donor['name'] . ' (' . $donor['donor_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Surgeon *</label>
                        <select name="surgeon_id" class="form-control" required>
                            <option value="">Select Surgeon</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    <?php echo htmlspecialchars($doctor['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Transplant Date *</label>
                        <input type="date" name="transplant_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Surgery Duration</label>
                        <input type="text" name="surgery_duration" class="form-control" placeholder="e.g., 4 hours 30 minutes">
                    </div>
                </div>
                <div class="form-group">
                    <label>Hospital Location *</label>
                    <input type="text" name="hospital_location" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
                <button type="submit" name="record_transplant" class="btn btn-success">
                    <i class="fas fa-save"></i> Record Transplant
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
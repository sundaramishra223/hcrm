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

// Fetch organ donation statistics
$stats = [];
if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
    // Total registered donors
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM organ_donors WHERE status = 'active'");
    $stmt->execute();
    $stats['total_donors'] = $stmt->fetch()['total'];

    // Available organs
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM organ_availability WHERE status = 'available'");
    $stmt->execute();
    $stats['available_organs'] = $stmt->fetch()['total'];

    // Pending requests
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM organ_requests WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_requests'] = $stmt->fetch()['total'];

    // Recent donations
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM organ_donations WHERE donation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute();
    $stats['recent_donations'] = $stmt->fetch()['total'];
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
        
        // Patient's organ donor registration
        $stmt = $db->prepare("SELECT * FROM organ_donors WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $patient_data['donor_info'] = $stmt->fetch();
        
        // Patient's organ requests
        $stmt = $db->prepare("SELECT or.*, p.first_name, p.last_name FROM organ_requests or JOIN patients p ON or.patient_id = p.id WHERE or.patient_id = ? ORDER BY or.request_date DESC");
        $stmt->execute([$patient_id]);
        $patient_data['requests'] = $stmt->fetchAll();
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register_donor':
                if ($user_role === 'patient') {
                    // Get patient ID
                    $stmt = $db->prepare("SELECT id FROM patients WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $patient = $stmt->fetch();
                    
                    if ($patient) {
                        $patient_id = $patient['id'];
                        $organs_to_donate = implode(',', $_POST['organs_to_donate']);
                        $medical_conditions = $_POST['medical_conditions'] ?? '';
                        $emergency_contact = $_POST['emergency_contact'] ?? '';
                        $emergency_phone = $_POST['emergency_phone'] ?? '';
                        
                        // Check if already registered
                        $stmt = $db->prepare("SELECT id FROM organ_donors WHERE patient_id = ?");
                        $stmt->execute([$patient_id]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) {
                            // Update existing registration
                            $stmt = $db->prepare("UPDATE organ_donors SET organs_to_donate = ?, medical_conditions = ?, emergency_contact_name = ?, emergency_contact_phone = ?, status = 'active', updated_at = NOW() WHERE patient_id = ?");
                            $stmt->execute([$organs_to_donate, $medical_conditions, $emergency_contact, $emergency_phone, $patient_id]);
                        } else {
                            // Create new registration
                            $stmt = $db->prepare("INSERT INTO organ_donors (patient_id, organs_to_donate, medical_conditions, emergency_contact_name, emergency_contact_phone, status) VALUES (?, ?, ?, ?, ?, 'active')");
                            $stmt->execute([$patient_id, $organs_to_donate, $medical_conditions, $emergency_contact, $emergency_phone]);
                        }
                        
                        $success_message = "Organ donor registration updated successfully!";
                    }
                }
                break;
                
            case 'add_donation':
                if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
                    $donor_patient_id = $_POST['donor_patient_id'];
                    $organ_type = $_POST['organ_type'];
                    $donation_date = $_POST['donation_date'];
                    $recipient_patient_id = $_POST['recipient_patient_id'] ?? null;
                    $notes = $_POST['notes'] ?? '';
                    
                    $stmt = $db->prepare("INSERT INTO organ_donations (donor_patient_id, recipient_patient_id, organ_type, donation_date, status, notes, created_by) VALUES (?, ?, ?, ?, 'completed', ?, ?)");
                    $stmt->execute([$donor_patient_id, $recipient_patient_id, $organ_type, $donation_date, $notes, $user_id]);
                    
                    $success_message = "Organ donation recorded successfully!";
                }
                break;
                
            case 'add_request':
                if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
                    $patient_id = $_POST['patient_id'];
                    $organ_type = $_POST['organ_type'];
                    $urgency = $_POST['urgency'];
                    $blood_group = $_POST['blood_group'];
                    $medical_condition = $_POST['medical_condition'];
                    $notes = $_POST['notes'] ?? '';
                    
                    $stmt = $db->prepare("INSERT INTO organ_requests (patient_id, organ_type, urgency, blood_group, medical_condition, status, notes, created_by) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
                    $stmt->execute([$patient_id, $organ_type, $urgency, $blood_group, $medical_condition, $notes, $user_id]);
                    
                    $success_message = "Organ request created successfully!";
                }
                break;
        }
    }
}

// Fetch data based on current page
$page_data = [];
switch ($current_page) {
    case 'donors':
        if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
            $stmt = $db->prepare("SELECT od.*, p.first_name, p.last_name, p.phone, p.blood_group FROM organ_donors od JOIN patients p ON od.patient_id = p.id ORDER BY od.created_at DESC LIMIT 50");
            $stmt->execute();
            $page_data['donors'] = $stmt->fetchAll();
        }
        break;
        
    case 'donations':
        if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
            $stmt = $db->prepare("SELECT odn.*, pd.first_name as donor_first, pd.last_name as donor_last, pr.first_name as recipient_first, pr.last_name as recipient_last, u.username as created_by_name FROM organ_donations odn JOIN patients pd ON odn.donor_patient_id = pd.id LEFT JOIN patients pr ON odn.recipient_patient_id = pr.id LEFT JOIN users u ON odn.created_by = u.id ORDER BY odn.donation_date DESC LIMIT 50");
            $stmt->execute();
            $page_data['donations'] = $stmt->fetchAll();
        }
        break;
        
    case 'requests':
        if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
            $stmt = $db->prepare("SELECT orq.*, p.first_name, p.last_name, p.phone, u.username as created_by_name FROM organ_requests orq JOIN patients p ON orq.patient_id = p.id LEFT JOIN users u ON orq.created_by = u.id ORDER BY orq.request_date DESC LIMIT 50");
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

// Available organ types
$organ_types = ['Heart', 'Liver', 'Kidney', 'Lung', 'Pancreas', 'Cornea', 'Skin', 'Bone', 'Heart Valves', 'Blood Vessels'];
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

        .btn-info {
            background: #17a2b8;
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

        .organ-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .organ-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .organ-card h4 {
            font-size: 1.2rem;
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

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }

        .donor-status {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .donor-status h3 {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-heart"></i> Organ Donation</h3>
                <p><?php echo ucfirst($user_role); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="organ-donation.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Overview</a></li>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <li><a href="organ-donation.php?page=donors" class="<?php echo $current_page === 'donors' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Donors</a></li>
                <li><a href="organ-donation.php?page=donations" class="<?php echo $current_page === 'donations' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-heart"></i> Donations</a></li>
                <li><a href="organ-donation.php?page=requests" class="<?php echo $current_page === 'requests' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> Requests</a></li>
                <?php endif; ?>
                
                <?php if ($user_role === 'patient'): ?>
                <li><a href="organ-donation.php?page=register" class="<?php echo $current_page === 'register' ? 'active' : ''; ?>"><i class="fas fa-user-plus"></i> Register as Donor</a></li>
                <li><a href="organ-donation.php?page=my-requests" class="<?php echo $current_page === 'my-requests' ? 'active' : ''; ?>"><i class="fas fa-list"></i> My Requests</a></li>
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
                    <h1><i class="fas fa-heart"></i> Organ Donation Management</h1>
                    <p>Manage organ donations, donors, and requests</p>
                </div>

                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-users" style="color: #667eea;"></i>
                            <h3><?php echo $stats['total_donors']; ?></h3>
                            <p>Registered Donors</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-heart" style="color: #28a745;"></i>
                            <h3><?php echo $stats['available_organs']; ?></h3>
                            <p>Available Organs</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-clipboard-list" style="color: #ffc107;"></i>
                            <h3><?php echo $stats['pending_requests']; ?></h3>
                            <p>Pending Requests</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-hand-holding-heart" style="color: #dc3545;"></i>
                            <h3><?php echo $stats['recent_donations']; ?></h3>
                            <p>Recent Donations</p>
                        </div>
                    </div>

                    <div class="content-card">
                        <h3>Organ Types</h3>
                        <div class="organ-grid">
                            <?php foreach ($organ_types as $organ): ?>
                                <div class="organ-card">
                                    <h4><?php echo $organ; ?></h4>
                                    <p>Available</p>
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
                    <?php if ($patient_data['donor_info']): ?>
                        <div class="donor-status">
                            <h3><i class="fas fa-check-circle"></i> You are a registered organ donor!</h3>
                            <p>Organs to donate: <?php echo htmlspecialchars($patient_data['donor_info']['organs_to_donate']); ?></p>
                            <p>Status: <strong><?php echo ucfirst($patient_data['donor_info']['status']); ?></strong></p>
                        </div>
                    <?php else: ?>
                        <div class="content-card">
                            <h3>Become an Organ Donor</h3>
                            <p>Save lives by registering as an organ donor. Your decision to donate organs can help multiple people in need.</p>
                            <a href="organ-donation.php?page=register" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Register as Donor
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <i class="fas fa-list" style="color: #17a2b8;"></i>
                            <h3><?php echo count($patient_data['requests']); ?></h3>
                            <p>My Requests</p>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-heart" style="color: #dc3545;"></i>
                            <h3><?php echo $patient_data['donor_info'] ? 1 : 0; ?></h3>
                            <p>Donor Registration</p>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($current_page === 'donors' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-users"></i> Organ Donors</h1>
                </div>

                <div class="content-card">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Donor Name</th>
                                    <th>Phone</th>
                                    <th>Blood Group</th>
                                    <th>Organs to Donate</th>
                                    <th>Status</th>
                                    <th>Registration Date</th>
                                    <th>Emergency Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($page_data['donors'] as $donor): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($donor['phone']); ?></td>
                                        <td><strong><?php echo $donor['blood_group']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($donor['organs_to_donate']); ?></td>
                                        <td><span class="badge badge-success"><?php echo ucfirst($donor['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($donor['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($donor['emergency_contact_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'donations' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-hand-holding-heart"></i> Organ Donations</h1>
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
                                    <th>Recipient</th>
                                    <th>Organ Type</th>
                                    <th>Status</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($page_data['donations'] as $donation): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($donation['donor_first'] . ' ' . $donation['donor_last']); ?></td>
                                        <td><?php echo $donation['recipient_first'] ? htmlspecialchars($donation['recipient_first'] . ' ' . $donation['recipient_last']) : 'N/A'; ?></td>
                                        <td><strong><?php echo htmlspecialchars($donation['organ_type']); ?></strong></td>
                                        <td><span class="badge badge-success"><?php echo ucfirst($donation['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($donation['created_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'requests' && in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                <div class="page-header">
                    <h1><i class="fas fa-clipboard-list"></i> Organ Requests</h1>
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
                                    <th>Organ Type</th>
                                    <th>Blood Group</th>
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
                                        <td><strong><?php echo htmlspecialchars($request['organ_type']); ?></strong></td>
                                        <td><?php echo $request['blood_group']; ?></td>
                                        <td><span class="badge <?php echo $urgency_class; ?>"><?php echo ucfirst($request['urgency']); ?></span></td>
                                        <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($request['created_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'register' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-user-plus"></i> Register as Organ Donor</h1>
                </div>

                <div class="content-card">
                    <form method="POST">
                        <input type="hidden" name="action" value="register_donor">
                        
                        <div class="form-group">
                            <label>Organs you wish to donate:</label>
                            <div class="checkbox-group">
                                <?php foreach ($organ_types as $organ): ?>
                                    <label>
                                        <input type="checkbox" name="organs_to_donate[]" value="<?php echo $organ; ?>"
                                            <?php echo ($patient_data['donor_info'] && strpos($patient_data['donor_info']['organs_to_donate'], $organ) !== false) ? 'checked' : ''; ?>>
                                        <?php echo $organ; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="medical_conditions">Medical Conditions (if any):</label>
                            <textarea name="medical_conditions" rows="4" placeholder="Please list any medical conditions or medications..."><?php echo htmlspecialchars($patient_data['donor_info']['medical_conditions'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact Name:</label>
                            <input type="text" name="emergency_contact" value="<?php echo htmlspecialchars($patient_data['donor_info']['emergency_contact_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_phone">Emergency Contact Phone:</label>
                            <input type="tel" name="emergency_phone" value="<?php echo htmlspecialchars($patient_data['donor_info']['emergency_contact_phone'] ?? ''); ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $patient_data['donor_info'] ? 'Update Registration' : 'Register as Donor'; ?>
                        </button>
                    </form>
                </div>

            <?php elseif ($current_page === 'my-requests' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-list"></i> My Organ Requests</h1>
                </div>

                <div class="content-card">
                    <?php if (!empty($patient_data['requests'])): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Organ Type</th>
                                        <th>Blood Group</th>
                                        <th>Urgency</th>
                                        <th>Status</th>
                                        <th>Medical Condition</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patient_data['requests'] as $request): 
                                        $urgency_class = $request['urgency'] === 'critical' ? 'badge-danger' : ($request['urgency'] === 'high' ? 'badge-warning' : 'badge-info');
                                        $status_class = $request['status'] === 'fulfilled' ? 'badge-success' : ($request['status'] === 'pending' ? 'badge-warning' : 'badge-danger');
                                    ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                            <td><strong><?php echo htmlspecialchars($request['organ_type']); ?></strong></td>
                                            <td><?php echo $request['blood_group']; ?></td>
                                            <td><span class="badge <?php echo $urgency_class; ?>"><?php echo ucfirst($request['urgency']); ?></span></td>
                                            <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($request['medical_condition']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>You haven't made any organ requests yet.</p>
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
            <h2>Record Organ Donation</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_donation">
                
                <div class="form-group">
                    <label for="donor_patient_id">Donor Patient:</label>
                    <select name="donor_patient_id" required>
                        <option value="">Select Donor</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['blood_group'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="recipient_patient_id">Recipient Patient (Optional):</label>
                    <select name="recipient_patient_id">
                        <option value="">Select Recipient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['blood_group'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="organ_type">Organ Type:</label>
                    <select name="organ_type" required>
                        <option value="">Select Organ</option>
                        <?php foreach ($organ_types as $organ): ?>
                            <option value="<?php echo $organ; ?>"><?php echo $organ; ?></option>
                        <?php endforeach; ?>
                    </select>
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
    <?php endif; ?>

    <!-- Request Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('requestModal')">&times;</span>
            <h2>Create Organ Request</h2>
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
                    <label for="organ_type">Organ Type Needed:</label>
                    <select name="organ_type" required>
                        <option value="">Select Organ</option>
                        <?php foreach ($organ_types as $organ): ?>
                            <option value="<?php echo $organ; ?>"><?php echo $organ; ?></option>
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
                    <label for="medical_condition">Medical Condition:</label>
                    <textarea name="medical_condition" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes:</label>
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
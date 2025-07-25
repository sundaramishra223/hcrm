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
            case 'register_donor':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $patient_id = $_POST['patient_id'];
                    $organs = $_POST['organs']; // Array of organ types
                    $consent_date = $_POST['consent_date'];
                    $emergency_contact = $_POST['emergency_contact'];
                    $emergency_phone = $_POST['emergency_phone'];
                    $medical_conditions = $_POST['medical_conditions'];
                    $notes = $_POST['notes'];
                    
                    // Register as organ donor
                    $stmt = $db->prepare("INSERT INTO organ_donors (patient_id, consent_date, emergency_contact_name, emergency_contact_phone, medical_conditions, notes, status, registered_by, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW())");
                    $stmt->execute([$patient_id, $consent_date, $emergency_contact, $emergency_phone, $medical_conditions, $notes, $user_id]);
                    
                    $donor_id = $db->lastInsertId();
                    
                    // Register organs
                    foreach ($organs as $organ_type) {
                        $stmt = $db->prepare("INSERT INTO organ_registry (donor_id, organ_type, status, registered_date) VALUES (?, ?, 'available', ?)");
                        $stmt->execute([$donor_id, $organ_type, $consent_date]);
                    }
                    
                    $success_message = "Organ donor registered successfully!";
                }
                break;
                
            case 'add_request':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $patient_id = $_POST['patient_id'];
                    $organ_type = $_POST['organ_type'];
                    $urgency = $_POST['urgency'];
                    $blood_type = $_POST['blood_type'];
                    $medical_notes = $_POST['medical_notes'];
                    $doctor_notes = $_POST['doctor_notes'];
                    
                    $stmt = $db->prepare("INSERT INTO organ_requests (patient_id, organ_type, urgency_level, blood_type_compatibility, medical_notes, doctor_notes, status, requested_by, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
                    $stmt->execute([$patient_id, $organ_type, $urgency, $blood_type, $medical_notes, $doctor_notes, $user_id]);
                    
                    $success_message = "Organ request submitted successfully!";
                }
                break;
                
            case 'match_organ':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $request_id = $_POST['request_id'];
                    $registry_id = $_POST['registry_id'];
                    $surgery_date = $_POST['surgery_date'];
                    $hospital = $_POST['hospital'];
                    $surgeon = $_POST['surgeon'];
                    
                    // Update organ registry
                    $stmt = $db->prepare("UPDATE organ_registry SET status = 'matched', matched_date = NOW() WHERE id = ?");
                    $stmt->execute([$registry_id]);
                    
                    // Update request
                    $stmt = $db->prepare("UPDATE organ_requests SET status = 'matched', matched_registry_id = ?, matched_date = NOW() WHERE id = ?");
                    $stmt->execute([$registry_id, $request_id]);
                    
                    // Create transplant record
                    $stmt = $db->prepare("INSERT INTO organ_transplants (request_id, registry_id, surgery_date, hospital, surgeon, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'scheduled', ?, NOW())");
                    $stmt->execute([$request_id, $registry_id, $surgery_date, $hospital, $surgeon, $user_id]);
                    
                    $success_message = "Organ matched successfully! Transplant scheduled.";
                }
                break;
                
            case 'update_transplant':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $transplant_id = $_POST['transplant_id'];
                    $status = $_POST['status'];
                    $notes = $_POST['notes'];
                    
                    $stmt = $db->prepare("UPDATE organ_transplants SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $notes, $transplant_id]);
                    
                    if ($status === 'completed') {
                        // Update registry and request status
                        $stmt = $db->prepare("SELECT request_id, registry_id FROM organ_transplants WHERE id = ?");
                        $stmt->execute([$transplant_id]);
                        $transplant = $stmt->fetch();
                        
                        $stmt = $db->prepare("UPDATE organ_registry SET status = 'transplanted' WHERE id = ?");
                        $stmt->execute([$transplant['registry_id']]);
                        
                        $stmt = $db->prepare("UPDATE organ_requests SET status = 'completed' WHERE id = ?");
                        $stmt->execute([$transplant['request_id']]);
                    }
                    
                    $success_message = "Transplant status updated successfully!";
                }
                break;
        }
    }
}

// Fetch data based on user role
if ($user_role === 'patient') {
    // Patient can only see their own data
    $patient_stmt = $db->prepare("SELECT * FROM patients WHERE user_id = ?");
    $patient_stmt->execute([$user_id]);
    $patient = $patient_stmt->fetch();
    
    if ($patient) {
        // Check if patient is registered as donor
        $donor_stmt = $db->prepare("SELECT * FROM organ_donors WHERE patient_id = ?");
        $donor_stmt->execute([$patient['id']]);
        $patient_donor = $donor_stmt->fetch();
        
        if ($patient_donor) {
            // Get registered organs
            $organs_stmt = $db->prepare("SELECT * FROM organ_registry WHERE donor_id = ?");
            $organs_stmt->execute([$patient_donor['id']]);
            $patient_organs = $organs_stmt->fetchAll();
        }
        
        // Get patient's organ requests
        $requests_stmt = $db->prepare("
            SELECT or.*, u.first_name, u.last_name 
            FROM organ_requests or 
            LEFT JOIN users u ON or.requested_by = u.id 
            WHERE or.patient_id = ? 
            ORDER BY or.created_at DESC
        ");
        $requests_stmt->execute([$patient['id']]);
        $patient_requests = $requests_stmt->fetchAll();
    }
} else {
    // Admin/Staff can see all data
    
    // Statistics
    $stats_stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM organ_donors WHERE status = 'active') as active_donors,
            (SELECT COUNT(*) FROM organ_registry WHERE status = 'available') as available_organs,
            (SELECT COUNT(*) FROM organ_requests WHERE status = 'pending') as pending_requests,
            (SELECT COUNT(*) FROM organ_transplants WHERE status = 'completed') as completed_transplants
    ");
    $stats = $stats_stmt->fetch();
    
    // Organ donors
    $donors_stmt = $db->prepare("
        SELECT od.*, p.first_name, p.last_name, p.patient_id, p.blood_type, u.first_name as reg_first_name, u.last_name as reg_last_name 
        FROM organ_donors od 
        LEFT JOIN patients p ON od.patient_id = p.id 
        LEFT JOIN users u ON od.registered_by = u.id 
        ORDER BY od.created_at DESC
    ");
    $donors = $donors_stmt->fetchAll();
    
    // Organ registry
    $registry_stmt = $db->prepare("
        SELECT oreg.*, od.patient_id, p.first_name, p.last_name, p.patient_id as patient_code, p.blood_type 
        FROM organ_registry oreg 
        LEFT JOIN organ_donors od ON oreg.donor_id = od.id 
        LEFT JOIN patients p ON od.patient_id = p.id 
        ORDER BY oreg.registered_date DESC
    ");
    $registry = $registry_stmt->fetchAll();
    
    // Organ requests
    $requests_stmt = $db->prepare("
        SELECT or.*, p.first_name as patient_first_name, p.last_name as patient_last_name, 
               p.patient_id, u.first_name, u.last_name 
        FROM organ_requests or 
        LEFT JOIN patients p ON or.patient_id = p.id 
        LEFT JOIN users u ON or.requested_by = u.id 
        ORDER BY or.created_at DESC
    ");
    $requests = $requests_stmt->fetchAll();
    
    // Transplants
    $transplants_stmt = $db->prepare("
        SELECT ot.*, 
               req.patient_id as recipient_id, rp.first_name as recipient_first_name, rp.last_name as recipient_last_name, rp.patient_id as recipient_code,
               don.patient_id as donor_patient_id, dp.first_name as donor_first_name, dp.last_name as donor_last_name, dp.patient_id as donor_code,
               oreg.organ_type
        FROM organ_transplants ot 
        LEFT JOIN organ_requests req ON ot.request_id = req.id 
        LEFT JOIN patients rp ON req.patient_id = rp.id 
        LEFT JOIN organ_registry oreg ON ot.registry_id = oreg.id 
        LEFT JOIN organ_donors don ON oreg.donor_id = don.id 
        LEFT JOIN patients dp ON don.patient_id = dp.id 
        ORDER BY ot.created_at DESC
    ");
    $transplants = $transplants_stmt->fetchAll();
    
    // Get patients for dropdown
    $patients_stmt = $db->prepare("SELECT id, patient_id, first_name, last_name, blood_type FROM patients ORDER BY first_name, last_name");
    $patients = $patients_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organ Donation Management - Hospital CRM</title>
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

        .stat-card.donors .icon { color: #28a745; }
        .stat-card.available .icon { color: #17a2b8; }
        .stat-card.pending .icon { color: #ffc107; }
        .stat-card.completed .icon { color: #6f42c1; }

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
            justify-content: space-between;
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

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
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

        .badge.active { background: #d4edda; color: #155724; }
        .badge.inactive { background: #f8d7da; color: #721c24; }
        .badge.available { background: #d1ecf1; color: #0c5460; }
        .badge.matched { background: #fff3cd; color: #856404; }
        .badge.transplanted { background: #e2e3e5; color: #495057; }
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.completed { background: #d4edda; color: #155724; }
        .badge.cancelled { background: #f8d7da; color: #721c24; }
        .badge.scheduled { background: #d1ecf1; color: #0c5460; }
        .badge.normal { background: #d4edda; color: #155724; }
        .badge.urgent { background: #fff3cd; color: #856404; }
        .badge.critical { background: #f8d7da; color: #721c24; }

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

        .form-checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-checkbox input[type="checkbox"] {
            width: auto;
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

        .donor-status {
            background: #e8f5e8;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .donor-status .icon {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 10px;
        }

        .organ-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .organ-card {
            background: white;
            border: 2px solid #eee;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s;
        }

        .organ-card:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }

        .organ-card .organ-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #dc3545;
        }

        .organ-card .organ-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .organ-card .organ-status {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-heart"></i> Organ Donation</h3>
                <?php if ($user_role === 'patient'): ?>
                    <p><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                    <small><?php echo htmlspecialchars($patient['patient_id']); ?></small>
                <?php else: ?>
                    <p>Management System</p>
                <?php endif; ?>
            </div>
            <ul class="sidebar-menu">
                <?php if ($user_role === 'patient'): ?>
                    <li><a href="organ-donation.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Overview</a></li>
                    <li><a href="organ-donation.php?page=my-donations" class="<?php echo $current_page === 'my-donations' ? 'active' : ''; ?>"><i class="fas fa-hand-holding-heart"></i> My Donations</a></li>
                    <li><a href="organ-donation.php?page=my-requests" class="<?php echo $current_page === 'my-requests' ? 'active' : ''; ?>"><i class="fas fa-list"></i> My Requests</a></li>
                    <li><a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Portal</a></li>
                <?php else: ?>
                    <li><a href="organ-donation.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Overview</a></li>
                    <li><a href="organ-donation.php?page=donors" class="<?php echo $current_page === 'donors' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Donors</a></li>
                    <li><a href="organ-donation.php?page=registry" class="<?php echo $current_page === 'registry' ? 'active' : ''; ?>"><i class="fas fa-list-alt"></i> Organ Registry</a></li>
                    <li><a href="organ-donation.php?page=requests" class="<?php echo $current_page === 'requests' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i> Requests</a></li>
                    <li><a href="organ-donation.php?page=transplants" class="<?php echo $current_page === 'transplants' ? 'active' : ''; ?>"><i class="fas fa-procedures"></i> Transplants</a></li>
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
                    <h1><i class="fas fa-heart"></i> Organ Donation Overview</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Organ Donation / Overview
                    </div>
                </div>

                <?php if ($user_role === 'patient'): ?>
                    <!-- Patient Overview -->
                    <?php if (isset($patient_donor) && $patient_donor): ?>
                        <div class="donor-status">
                            <div class="icon"><i class="fas fa-heart"></i></div>
                            <h3>You are a Registered Organ Donor</h3>
                            <p>Thank you for your generous decision to help save lives!</p>
                            <small>Registered on: <?php echo date('M d, Y', strtotime($patient_donor['consent_date'])); ?></small>
                        </div>
                        
                        <div class="content-section">
                            <div class="section-header">
                                <h2>Your Registered Organs</h2>
                            </div>
                            <div class="organ-grid">
                                <?php if (!empty($patient_organs)): ?>
                                    <?php foreach ($patient_organs as $organ): ?>
                                        <div class="organ-card">
                                            <div class="organ-icon">
                                                <?php
                                                $icons = [
                                                    'heart' => 'fas fa-heart',
                                                    'liver' => 'fas fa-square',
                                                    'kidney' => 'fas fa-circle',
                                                    'lung' => 'fas fa-lungs',
                                                    'pancreas' => 'fas fa-square',
                                                    'cornea' => 'fas fa-eye',
                                                    'skin' => 'fas fa-hand-paper',
                                                    'bone' => 'fas fa-bone'
                                                ];
                                                echo '<i class="' . ($icons[$organ['organ_type']] ?? 'fas fa-heart') . '"></i>';
                                                ?>
                                            </div>
                                            <div class="organ-name"><?php echo ucfirst($organ['organ_type']); ?></div>
                                            <div class="organ-status">
                                                <span class="badge <?php echo $organ['status']; ?>"><?php echo ucfirst($organ['status']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No organs registered.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="content-section">
                            <div class="section-header">
                                <h2>Become an Organ Donor</h2>
                            </div>
                            <div style="padding: 40px; text-align: center;">
                                <div style="font-size: 4rem; color: #dc3545; margin-bottom: 20px;">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <h3>Save Lives Through Organ Donation</h3>
                                <p style="margin: 20px 0; color: #666; font-size: 1.1rem;">
                                    One organ donor can save up to 8 lives and improve the lives of many more through tissue donation.
                                </p>
                                <p style="color: #666;">
                                    Contact your doctor or hospital staff to register as an organ donor.
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="stats-grid">
                        <div class="stat-card pending">
                            <div class="icon"><i class="fas fa-clock"></i></div>
                            <div class="number"><?php echo count(array_filter($patient_requests ?? [], function($r) { return $r['status'] === 'pending'; })); ?></div>
                            <div class="label">Pending Requests</div>
                        </div>
                        <div class="stat-card completed">
                            <div class="icon"><i class="fas fa-check"></i></div>
                            <div class="number"><?php echo count(array_filter($patient_requests ?? [], function($r) { return $r['status'] === 'completed'; })); ?></div>
                            <div class="label">Completed Requests</div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Admin/Staff Overview -->
                    <div class="stats-grid">
                        <div class="stat-card donors">
                            <div class="icon"><i class="fas fa-users"></i></div>
                            <div class="number"><?php echo $stats['active_donors']; ?></div>
                            <div class="label">Active Donors</div>
                        </div>
                        <div class="stat-card available">
                            <div class="icon"><i class="fas fa-heart"></i></div>
                            <div class="number"><?php echo $stats['available_organs']; ?></div>
                            <div class="label">Available Organs</div>
                        </div>
                        <div class="stat-card pending">
                            <div class="icon"><i class="fas fa-clock"></i></div>
                            <div class="number"><?php echo $stats['pending_requests']; ?></div>
                            <div class="label">Pending Requests</div>
                        </div>
                        <div class="stat-card completed">
                            <div class="icon"><i class="fas fa-procedures"></i></div>
                            <div class="number"><?php echo $stats['completed_transplants']; ?></div>
                            <div class="label">Completed Transplants</div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="content-section">
                        <div class="section-header">
                            <h2>Recent Organ Requests</h2>
                        </div>
                        <div class="table-container">
                            <?php if (!empty($requests)): ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Patient</th>
                                            <th>Organ Type</th>
                                            <th>Urgency</th>
                                            <th>Blood Type</th>
                                            <th>Status</th>
                                            <th>Request Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($requests, 0, 10) as $request): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($request['patient_first_name'] . ' ' . $request['patient_last_name']); ?><br>
                                                    <small><?php echo htmlspecialchars($request['patient_id']); ?></small>
                                                </td>
                                                <td><strong><?php echo ucfirst($request['organ_type']); ?></strong></td>
                                                <td><span class="badge <?php echo $request['urgency_level']; ?>"><?php echo ucfirst($request['urgency_level']); ?></span></td>
                                                <td><?php echo htmlspecialchars($request['blood_type_compatibility']); ?></td>
                                                <td><span class="badge <?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="text-align: center; padding: 40px; color: #666;">No organ requests found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($current_page === 'donors' && $user_role !== 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-users"></i> Organ Donors</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Organ Donation / Donors
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>Registered Organ Donors</h2>
                        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                            <button class="btn btn-primary" onclick="openModal('registerDonorModal')">
                                <i class="fas fa-plus"></i> Register Donor
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Blood Type</th>
                                    <th>Consent Date</th>
                                    <th>Emergency Contact</th>
                                    <th>Status</th>
                                    <th>Registered By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donors as $donor): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?><br>
                                            <small><?php echo htmlspecialchars($donor['patient_id']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($donor['blood_type']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($donor['consent_date'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($donor['emergency_contact_name']); ?><br>
                                            <small><?php echo htmlspecialchars($donor['emergency_contact_phone']); ?></small>
                                        </td>
                                        <td><span class="badge <?php echo $donor['status']; ?>"><?php echo ucfirst($donor['status']); ?></span></td>
                                        <td><?php echo htmlspecialchars($donor['reg_first_name'] . ' ' . $donor['reg_last_name']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="viewDonorDetails(<?php echo $donor['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'registry' && $user_role !== 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-list-alt"></i> Organ Registry</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Organ Donation / Registry
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>Available Organs</h2>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Organ Type</th>
                                    <th>Donor</th>
                                    <th>Blood Type</th>
                                    <th>Registered Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registry as $organ): ?>
                                    <tr>
                                        <td><strong><?php echo ucfirst($organ['organ_type']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($organ['first_name'] . ' ' . $organ['last_name']); ?><br>
                                            <small><?php echo htmlspecialchars($organ['patient_code']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($organ['blood_type']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($organ['registered_date'])); ?></td>
                                        <td><span class="badge <?php echo $organ['status']; ?>"><?php echo ucfirst($organ['status']); ?></span></td>
                                        <td>
                                            <?php if ($organ['status'] === 'available' && in_array($user_role, ['admin', 'doctor'])): ?>
                                                <button class="btn btn-sm btn-success" onclick="matchOrgan(<?php echo $organ['id']; ?>, '<?php echo $organ['organ_type']; ?>')">
                                                    <i class="fas fa-link"></i> Match
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'requests' && $user_role !== 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-clipboard-list"></i> Organ Requests</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Organ Donation / Requests
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>Organ Requests</h2>
                        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
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
                                    <th>Organ Type</th>
                                    <th>Urgency</th>
                                    <th>Blood Type</th>
                                    <th>Status</th>
                                    <th>Request Date</th>
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
                                        <td><strong><?php echo ucfirst($request['organ_type']); ?></strong></td>
                                        <td><span class="badge <?php echo $request['urgency_level']; ?>"><?php echo ucfirst($request['urgency_level']); ?></span></td>
                                        <td><?php echo htmlspecialchars($request['blood_type_compatibility']); ?></td>
                                        <td><span class="badge <?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                        <td>
                                            <?php if ($request['status'] === 'pending' && in_array($user_role, ['admin', 'doctor'])): ?>
                                                <button class="btn btn-sm btn-success" onclick="findMatch(<?php echo $request['id']; ?>, '<?php echo $request['organ_type']; ?>', '<?php echo $request['blood_type_compatibility']; ?>')">
                                                    <i class="fas fa-search"></i> Find Match
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'transplants' && $user_role !== 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-procedures"></i> Organ Transplants</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Organ Donation / Transplants
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>Scheduled & Completed Transplants</h2>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Organ</th>
                                    <th>Recipient</th>
                                    <th>Donor</th>
                                    <th>Surgery Date</th>
                                    <th>Hospital</th>
                                    <th>Surgeon</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transplants as $transplant): ?>
                                    <tr>
                                        <td><strong><?php echo ucfirst($transplant['organ_type']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($transplant['recipient_first_name'] . ' ' . $transplant['recipient_last_name']); ?><br>
                                            <small><?php echo htmlspecialchars($transplant['recipient_code']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($transplant['donor_first_name'] . ' ' . $transplant['donor_last_name']); ?><br>
                                            <small><?php echo htmlspecialchars($transplant['donor_code']); ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($transplant['surgery_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($transplant['hospital']); ?></td>
                                        <td><?php echo htmlspecialchars($transplant['surgeon']); ?></td>
                                        <td><span class="badge <?php echo $transplant['status']; ?>"><?php echo ucfirst($transplant['status']); ?></span></td>
                                        <td>
                                            <?php if (in_array($transplant['status'], ['scheduled', 'in_progress']) && in_array($user_role, ['admin', 'doctor'])): ?>
                                                <button class="btn btn-sm btn-warning" onclick="updateTransplant(<?php echo $transplant['id']; ?>)">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($current_page === 'my-donations' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-hand-holding-heart"></i> My Organ Donations</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Organ Donation / My Donations
                    </div>
                </div>

                <?php if (isset($patient_donor) && $patient_donor): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h2>Donation Details</h2>
                        </div>
                        <div style="padding: 20px;">
                            <div class="form-row">
                                <div>
                                    <strong>Consent Date:</strong><br>
                                    <?php echo date('M d, Y', strtotime($patient_donor['consent_date'])); ?>
                                </div>
                                <div>
                                    <strong>Status:</strong><br>
                                    <span class="badge <?php echo $patient_donor['status']; ?>"><?php echo ucfirst($patient_donor['status']); ?></span>
                                </div>
                            </div>
                            <div style="margin-top: 20px;">
                                <strong>Emergency Contact:</strong><br>
                                <?php echo htmlspecialchars($patient_donor['emergency_contact_name']); ?> - <?php echo htmlspecialchars($patient_donor['emergency_contact_phone']); ?>
                            </div>
                            <?php if ($patient_donor['medical_conditions']): ?>
                                <div style="margin-top: 20px;">
                                    <strong>Medical Conditions:</strong><br>
                                    <?php echo htmlspecialchars($patient_donor['medical_conditions']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="content-section">
                        <div class="section-header">
                            <h2>Registered Organs</h2>
                        </div>
                        <div class="organ-grid">
                            <?php if (!empty($patient_organs)): ?>
                                <?php foreach ($patient_organs as $organ): ?>
                                    <div class="organ-card">
                                        <div class="organ-icon">
                                            <?php
                                            $icons = [
                                                'heart' => 'fas fa-heart',
                                                'liver' => 'fas fa-square',
                                                'kidney' => 'fas fa-circle',
                                                'lung' => 'fas fa-lungs',
                                                'pancreas' => 'fas fa-square',
                                                'cornea' => 'fas fa-eye',
                                                'skin' => 'fas fa-hand-paper',
                                                'bone' => 'fas fa-bone'
                                            ];
                                            echo '<i class="' . ($icons[$organ['organ_type']] ?? 'fas fa-heart') . '"></i>';
                                            ?>
                                        </div>
                                        <div class="organ-name"><?php echo ucfirst($organ['organ_type']); ?></div>
                                        <div class="organ-status">
                                            <span class="badge <?php echo $organ['status']; ?>"><?php echo ucfirst($organ['status']); ?></span>
                                        </div>
                                        <?php if ($organ['matched_date']): ?>
                                            <div style="margin-top: 10px; font-size: 0.8rem; color: #666;">
                                                Matched: <?php echo date('M d, Y', strtotime($organ['matched_date'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No organs registered.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="content-section">
                        <div style="padding: 40px; text-align: center;">
                            <div style="font-size: 4rem; color: #dc3545; margin-bottom: 20px;">
                                <i class="fas fa-heart"></i>
                            </div>
                            <h3>You are not registered as an organ donor</h3>
                            <p style="margin: 20px 0; color: #666;">
                                Contact your doctor or hospital staff to register as an organ donor and help save lives.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($current_page === 'my-requests' && $user_role === 'patient'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-list"></i> My Organ Requests</h1>
                    <div class="breadcrumb">
                        <i class="fas fa-home"></i> Home / Organ Donation / My Requests
                    </div>
                </div>

                <div class="content-section">
                    <div class="section-header">
                        <h2>All My Organ Requests</h2>
                    </div>
                    <div class="table-container">
                        <?php if (!empty($patient_requests)): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Request Date</th>
                                        <th>Organ Type</th>
                                        <th>Urgency</th>
                                        <th>Blood Type</th>
                                        <th>Status</th>
                                        <th>Requested By</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patient_requests as $request): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                            <td><strong><?php echo ucfirst($request['organ_type']); ?></strong></td>
                                            <td><span class="badge <?php echo $request['urgency_level']; ?>"><?php echo ucfirst($request['urgency_level']); ?></span></td>
                                            <td><?php echo htmlspecialchars($request['blood_type_compatibility']); ?></td>
                                            <td><span class="badge <?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                            <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($request['medical_notes'] ?: 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="text-align: center; padding: 40px; color: #666;">No organ requests found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Register Donor Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
    <div id="registerDonorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Register Organ Donor</h3>
                <span class="close" onclick="closeModal('registerDonorModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="register_donor">
                    
                    <div class="form-group">
                        <label for="patient_id">Patient *</label>
                        <select id="patient_id" name="patient_id" class="form-control" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['blood_type'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Organs to Donate *</label>
                        <div class="form-checkbox-group">
                            <div class="form-checkbox">
                                <input type="checkbox" id="heart" name="organs[]" value="heart">
                                <label for="heart">Heart</label>
                            </div>
                            <div class="form-checkbox">
                                <input type="checkbox" id="liver" name="organs[]" value="liver">
                                <label for="liver">Liver</label>
                            </div>
                            <div class="form-checkbox">
                                <input type="checkbox" id="kidney" name="organs[]" value="kidney">
                                <label for="kidney">Kidney</label>
                            </div>
                            <div class="form-checkbox">
                                <input type="checkbox" id="lung" name="organs[]" value="lung">
                                <label for="lung">Lungs</label>
                            </div>
                            <div class="form-checkbox">
                                <input type="checkbox" id="pancreas" name="organs[]" value="pancreas">
                                <label for="pancreas">Pancreas</label>
                            </div>
                            <div class="form-checkbox">
                                <input type="checkbox" id="cornea" name="organs[]" value="cornea">
                                <label for="cornea">Cornea</label>
                            </div>
                            <div class="form-checkbox">
                                <input type="checkbox" id="skin" name="organs[]" value="skin">
                                <label for="skin">Skin</label>
                            </div>
                            <div class="form-checkbox">
                                <input type="checkbox" id="bone" name="organs[]" value="bone">
                                <label for="bone">Bone</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="consent_date">Consent Date *</label>
                        <input type="date" id="consent_date" name="consent_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact Name *</label>
                            <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="emergency_phone">Emergency Contact Phone *</label>
                            <input type="tel" id="emergency_phone" name="emergency_phone" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="medical_conditions">Medical Conditions</label>
                        <textarea id="medical_conditions" name="medical_conditions" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="button" class="btn" onclick="closeModal('registerDonorModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Register Donor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Request Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
    <div id="addRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>New Organ Request</h3>
                <span class="close" onclick="closeModal('addRequestModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_request">
                    
                    <div class="form-group">
                        <label for="patient_id_req">Patient *</label>
                        <select id="patient_id_req" name="patient_id" class="form-control" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['blood_type'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="organ_type">Organ Type *</label>
                            <select id="organ_type" name="organ_type" class="form-control" required>
                                <option value="">Select Organ</option>
                                <option value="heart">Heart</option>
                                <option value="liver">Liver</option>
                                <option value="kidney">Kidney</option>
                                <option value="lung">Lungs</option>
                                <option value="pancreas">Pancreas</option>
                                <option value="cornea">Cornea</option>
                                <option value="skin">Skin</option>
                                <option value="bone">Bone</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="urgency">Urgency Level *</label>
                            <select id="urgency" name="urgency" class="form-control" required>
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="blood_type">Blood Type Compatibility *</label>
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
                            <option value="Any">Any Compatible</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="medical_notes">Medical Notes</label>
                        <textarea id="medical_notes" name="medical_notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="doctor_notes">Doctor Notes</label>
                        <textarea id="doctor_notes" name="doctor_notes" class="form-control" rows="3"></textarea>
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

        function viewDonorDetails(donorId) {
            // This would typically open a modal with detailed donor information
            alert('View donor details for ID: ' + donorId);
        }

        function matchOrgan(registryId, organType) {
            // This would typically open a modal to match with pending requests
            if (confirm(`Match ${organType} organ with a pending request?`)) {
                alert('Match organ functionality would be implemented here');
            }
        }

        function findMatch(requestId, organType, bloodType) {
            // This would typically open a modal to find compatible organs
            alert(`Find match for ${organType} request (Blood type: ${bloodType})`);
        }

        function updateTransplant(transplantId) {
            // This would typically open a modal to update transplant status
            alert('Update transplant status for ID: ' + transplantId);
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
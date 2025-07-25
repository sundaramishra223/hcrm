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
            case 'register_donor':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $stmt = $db->prepare("INSERT INTO organ_donors (donor_id, first_name, last_name, email, phone, date_of_birth, gender, blood_group, address, emergency_contact_name, emergency_contact_phone, organs_to_donate, medical_history, consent_date, donor_status, registered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $donor_id = 'OD' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $organs_to_donate = implode(',', $_POST['organs_to_donate']);
                    
                    $stmt->execute([
                        $donor_id,
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['date_of_birth'],
                        $_POST['gender'],
                        $_POST['blood_group'],
                        $_POST['address'],
                        $_POST['emergency_contact_name'],
                        $_POST['emergency_contact_phone'],
                        $organs_to_donate,
                        $_POST['medical_history'],
                        $_POST['consent_date'],
                        $_POST['donor_status'],
                        $_SESSION['user_id']
                    ]);
                    
                    $_SESSION['success_message'] = "Organ donor registered successfully!";
                }
                break;
                
            case 'add_recipient':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $stmt = $db->prepare("INSERT INTO organ_recipients (recipient_id, patient_id, organ_needed, blood_group, urgency_level, medical_condition, doctor_id, registration_date, priority_score, recipient_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $recipient_id = 'OR' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $recipient_id,
                        $_POST['patient_id'],
                        $_POST['organ_needed'],
                        $_POST['blood_group'],
                        $_POST['urgency_level'],
                        $_POST['medical_condition'],
                        $_POST['doctor_id'],
                        $_POST['registration_date'],
                        $_POST['priority_score'],
                        $_POST['recipient_status'],
                        $_POST['notes']
                    ]);
                    
                    $_SESSION['success_message'] = "Recipient registered successfully!";
                }
                break;
                
            case 'record_transplant':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $stmt = $db->prepare("INSERT INTO organ_transplants (transplant_id, donor_id, recipient_id, organ_type, transplant_date, surgeon_id, hospital_unit, surgery_duration, transplant_status, complications, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $transplant_id = 'TR' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $transplant_id,
                        $_POST['donor_id'],
                        $_POST['recipient_id'],
                        $_POST['organ_type'],
                        $_POST['transplant_date'],
                        $_POST['surgeon_id'],
                        $_POST['hospital_unit'],
                        $_POST['surgery_duration'],
                        $_POST['transplant_status'],
                        $_POST['complications'],
                        $_POST['notes'],
                        $_SESSION['user_id']
                    ]);
                    
                    // Update donor and recipient status
                    $stmt = $db->prepare("UPDATE organ_donors SET donor_status = 'donated' WHERE id = ?");
                    $stmt->execute([$_POST['donor_id']]);
                    
                    $stmt = $db->prepare("UPDATE organ_recipients SET recipient_status = 'transplanted' WHERE id = ?");
                    $stmt->execute([$_POST['recipient_id']]);
                    
                    $_SESSION['success_message'] = "Transplant recorded successfully!";
                }
                break;
        }
        
        header('Location: organ-donation.php?page=' . $current_page);
        exit;
    }
}

// Fetch data based on current page
$donors = [];
$recipients = [];
$transplants = [];
$matches = [];
$stats = [];

if ($current_page === 'overview' || $current_page === 'donors' || $current_page === 'recipients' || $current_page === 'transplants' || $current_page === 'matches') {
    // Get statistics
    $stmt = $db->query("SELECT COUNT(*) as total_donors FROM organ_donors WHERE donor_status = 'active'");
    $stats['total_donors'] = $stmt->fetch()['total_donors'];
    
    $stmt = $db->query("SELECT COUNT(*) as total_recipients FROM organ_recipients WHERE recipient_status = 'waiting'");
    $stats['total_recipients'] = $stmt->fetch()['total_recipients'];
    
    $stmt = $db->query("SELECT COUNT(*) as total_transplants FROM organ_transplants WHERE transplant_status = 'successful'");
    $stats['total_transplants'] = $stmt->fetch()['total_transplants'];
    
    $stmt = $db->query("SELECT COUNT(*) as pending_matches FROM organ_recipients WHERE recipient_status = 'waiting' AND urgency_level = 'critical'");
    $stats['pending_matches'] = $stmt->fetch()['pending_matches'];
}

if ($current_page === 'donors' || $current_page === 'overview') {
    $stmt = $db->query("SELECT * FROM organ_donors ORDER BY created_at DESC LIMIT 10");
    $donors = $stmt->fetchAll();
}

if ($current_page === 'recipients' || $current_page === 'overview') {
    $stmt = $db->query("SELECT or.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, CONCAT(d.first_name, ' ', d.last_name) as doctor_name FROM organ_recipients or LEFT JOIN patients p ON or.patient_id = p.id LEFT JOIN doctors d ON or.doctor_id = d.id ORDER BY or.priority_score DESC LIMIT 10");
    $recipients = $stmt->fetchAll();
}

if ($current_page === 'transplants' || $current_page === 'overview') {
    $stmt = $db->query("SELECT ot.*, CONCAT(od.first_name, ' ', od.last_name) as donor_name, CONCAT(p.first_name, ' ', p.last_name) as recipient_name, CONCAT(d.first_name, ' ', d.last_name) as surgeon_name FROM organ_transplants ot LEFT JOIN organ_donors od ON ot.donor_id = od.id LEFT JOIN organ_recipients or ON ot.recipient_id = or.id LEFT JOIN patients p ON or.patient_id = p.id LEFT JOIN doctors d ON ot.surgeon_id = d.id ORDER BY ot.transplant_date DESC LIMIT 10");
    $transplants = $stmt->fetchAll();
}

if ($current_page === 'matches') {
    // Find potential matches based on blood group and organ type
    $stmt = $db->query("
        SELECT 
            or.id as recipient_id,
            or.recipient_id as recipient_code,
            CONCAT(p.first_name, ' ', p.last_name) as recipient_name,
            or.organ_needed,
            or.blood_group as recipient_blood_group,
            or.urgency_level,
            or.priority_score,
            od.id as donor_id,
            od.donor_id as donor_code,
            CONCAT(od.first_name, ' ', od.last_name) as donor_name,
            od.blood_group as donor_blood_group,
            od.organs_to_donate
        FROM organ_recipients or
        LEFT JOIN patients p ON or.patient_id = p.id
        LEFT JOIN organ_donors od ON (
            od.donor_status = 'active' AND
            (od.blood_group = or.blood_group OR od.blood_group = 'O-' OR (or.blood_group = 'AB+')) AND
            FIND_IN_SET(or.organ_needed, od.organs_to_donate)
        )
        WHERE or.recipient_status = 'waiting'
        ORDER BY or.urgency_level DESC, or.priority_score DESC
    ");
    $matches = $stmt->fetchAll();
}

// Get all patients for dropdown
$all_patients = [];
if (in_array($user_role, ['admin', 'doctor'])) {
    $stmt = $db->query("SELECT id, patient_id, CONCAT(first_name, ' ', last_name) as name FROM patients WHERE status = 'active'");
    $all_patients = $stmt->fetchAll();
}

// Get all doctors for dropdown
$all_doctors = [];
if (in_array($user_role, ['admin', 'doctor'])) {
    $stmt = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM doctors WHERE status = 'active'");
    $all_doctors = $stmt->fetchAll();
}

// Get all organ donors for dropdown
$all_donors = [];
if (in_array($user_role, ['admin', 'doctor'])) {
    $stmt = $db->query("SELECT id, donor_id, CONCAT(first_name, ' ', last_name) as name, organs_to_donate FROM organ_donors WHERE donor_status = 'active'");
    $all_donors = $stmt->fetchAll();
}

// Get all recipients for dropdown
$all_recipients = [];
if (in_array($user_role, ['admin', 'doctor'])) {
    $stmt = $db->query("SELECT or.id, or.recipient_id, CONCAT(p.first_name, ' ', p.last_name) as name, or.organ_needed FROM organ_recipients or LEFT JOIN patients p ON or.patient_id = p.id WHERE or.recipient_status = 'waiting'");
    $all_recipients = $stmt->fetchAll();
}
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
        .stat-card.recipients::before { background: #e74c3c; }
        .stat-card.transplants::before { background: #2ecc71; }
        .stat-card.matches::before { background: #f39c12; }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-card.donors .stat-icon { color: #3498db; }
        .stat-card.recipients .stat-icon { color: #e74c3c; }
        .stat-card.transplants .stat-icon { color: #2ecc71; }
        .stat-card.matches .stat-icon { color: #f39c12; }
        
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
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
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
        .badge-primary { background: #d6eaff; color: #004085; }
        
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
        
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .form-check input {
            margin-right: 8px;
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
        
        .organ-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .organ-card {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        
        .organ-name {
            font-size: 1.1rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .organ-count {
            font-size: 1.5rem;
            color: #3498db;
            font-weight: bold;
        }
        
        .match-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .match-score {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
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
                <h3><i class="fas fa-heart"></i> Organ Donation</h3>
                <p>Management System</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="organ-donation.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Overview</a></li>
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                <li><a href="organ-donation.php?page=donors" class="<?php echo $current_page === 'donors' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Donors</a></li>
                <?php endif; ?>
                <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                <li><a href="organ-donation.php?page=recipients" class="<?php echo $current_page === 'recipients' ? 'active' : ''; ?>"><i class="fas fa-user-injured"></i> Recipients</a></li>
                <li><a href="organ-donation.php?page=matches" class="<?php echo $current_page === 'matches' ? 'active' : ''; ?>"><i class="fas fa-exchange-alt"></i> Matching</a></li>
                <li><a href="organ-donation.php?page=transplants" class="<?php echo $current_page === 'transplants' ? 'active' : ''; ?>"><i class="fas fa-procedures"></i> Transplants</a></li>
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
                        <h1 class="page-title">Organ Donation Overview</h1>
                        <p class="page-subtitle">Monitor organ donation and transplant operations</p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card donors">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-number"><?php echo number_format($stats['total_donors']); ?></div>
                        <div class="stat-label">Active Donors</div>
                    </div>
                    <div class="stat-card recipients">
                        <div class="stat-icon"><i class="fas fa-user-injured"></i></div>
                        <div class="stat-number"><?php echo number_format($stats['total_recipients']); ?></div>
                        <div class="stat-label">Waiting Recipients</div>
                    </div>
                    <div class="stat-card transplants">
                        <div class="stat-icon"><i class="fas fa-procedures"></i></div>
                        <div class="stat-number"><?php echo number_format($stats['total_transplants']); ?></div>
                        <div class="stat-label">Successful Transplants</div>
                    </div>
                    <div class="stat-card matches">
                        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-number"><?php echo number_format($stats['pending_matches']); ?></div>
                        <div class="stat-label">Critical Cases</div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Donors</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Blood Group</th>
                                        <th>Organs</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($donors, 0, 5) as $donor): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></td>
                                            <td><span class="badge badge-danger"><?php echo htmlspecialchars($donor['blood_group']); ?></span></td>
                                            <td><?php echo count(explode(',', $donor['organs_to_donate'])); ?> organs</td>
                                            <td>
                                                <?php
                                                $status_class = $donor['donor_status'] === 'active' ? 'badge-success' : 'badge-warning';
                                                ?>
                                                <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($donor['donor_status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Recipients</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Organ Needed</th>
                                        <th>Urgency</th>
                                        <th>Priority</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recipients, 0, 5) as $recipient): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($recipient['patient_name']); ?></td>
                                            <td><?php echo ucfirst($recipient['organ_needed']); ?></td>
                                            <td>
                                                <?php
                                                $urgency_class = '';
                                                switch($recipient['urgency_level']) {
                                                    case 'critical': $urgency_class = 'badge-danger'; break;
                                                    case 'urgent': $urgency_class = 'badge-warning'; break;
                                                    case 'normal': $urgency_class = 'badge-info'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $urgency_class; ?>"><?php echo ucfirst($recipient['urgency_level']); ?></span>
                                            </td>
                                            <td><strong><?php echo $recipient['priority_score']; ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            <?php elseif ($current_page === 'donors' && in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                <!-- Donors Page -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Organ Donors</h1>
                        <p class="page-subtitle">Manage organ donor registrations</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('addDonorModal')">
                        <i class="fas fa-plus"></i> Register Donor
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
                                    <th>Organs to Donate</th>
                                    <th>Consent Date</th>
                                    <th>Status</th>
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
                                        <td><?php echo str_replace(',', ', ', $donor['organs_to_donate']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($donor['consent_date'])); ?></td>
                                        <td>
                                            <?php
                                            $status_class = $donor['donor_status'] === 'active' ? 'badge-success' : 'badge-warning';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($donor['donor_status']); ?></span>
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

            <?php elseif ($current_page === 'recipients' && in_array($user_role, ['admin', 'doctor'])): ?>
                <!-- Recipients Page -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Organ Recipients</h1>
                        <p class="page-subtitle">Manage patients waiting for organ transplants</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('addRecipientModal')">
                        <i class="fas fa-plus"></i> Add Recipient
                    </button>
                </div>

                <div class="content-card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Recipient ID</th>
                                    <th>Patient</th>
                                    <th>Organ Needed</th>
                                    <th>Blood Group</th>
                                    <th>Urgency</th>
                                    <th>Priority Score</th>
                                    <th>Doctor</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recipients as $recipient): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($recipient['recipient_id']); ?></td>
                                        <td><?php echo htmlspecialchars($recipient['patient_name']); ?></td>
                                        <td><?php echo ucfirst($recipient['organ_needed']); ?></td>
                                        <td><span class="badge badge-danger"><?php echo htmlspecialchars($recipient['blood_group']); ?></span></td>
                                        <td>
                                            <?php
                                            $urgency_class = '';
                                            switch($recipient['urgency_level']) {
                                                case 'critical': $urgency_class = 'badge-danger'; break;
                                                case 'urgent': $urgency_class = 'badge-warning'; break;
                                                case 'normal': $urgency_class = 'badge-info'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $urgency_class; ?>"><?php echo ucfirst($recipient['urgency_level']); ?></span>
                                        </td>
                                        <td><strong><?php echo $recipient['priority_score']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($recipient['doctor_name']); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch($recipient['recipient_status']) {
                                                case 'waiting': $status_class = 'badge-warning'; break;
                                                case 'matched': $status_class = 'badge-info'; break;
                                                case 'transplanted': $status_class = 'badge-success'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($recipient['recipient_status']); ?></span>
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

            <?php elseif ($current_page === 'matches' && in_array($user_role, ['admin', 'doctor'])): ?>
                <!-- Matches Page -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Organ Matching</h1>
                        <p class="page-subtitle">Find compatible donors for recipients</p>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">Potential Matches</h3>
                    </div>
                    <div style="padding: 20px;">
                        <?php 
                        $current_recipient = null;
                        foreach ($matches as $match): 
                            if ($current_recipient !== $match['recipient_id']):
                                if ($current_recipient !== null): echo "</div>"; endif;
                                $current_recipient = $match['recipient_id'];
                        ?>
                            <div class="match-card">
                                <div class="match-header">
                                    <div>
                                        <strong><?php echo htmlspecialchars($match['recipient_name']); ?></strong>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($match['recipient_code']); ?></span>
                                    </div>
                                    <div>
                                        <span class="badge badge-danger"><?php echo ucfirst($match['urgency_level']); ?></span>
                                        <span class="match-score">Score: <?php echo $match['priority_score']; ?></span>
                                    </div>
                                </div>
                                <p><strong>Needs:</strong> <?php echo ucfirst($match['organ_needed']); ?> | <strong>Blood Group:</strong> <?php echo $match['recipient_blood_group']; ?></p>
                                
                                <?php if ($match['donor_id']): ?>
                                    <div style="margin-top: 10px; padding: 10px; background: #e8f5e8; border-radius: 5px;">
                                        <strong>Compatible Donor Found:</strong><br>
                                        <strong><?php echo htmlspecialchars($match['donor_name']); ?></strong> 
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($match['donor_code']); ?></span><br>
                                        <strong>Blood Group:</strong> <?php echo $match['donor_blood_group']; ?> | 
                                        <strong>Available Organs:</strong> <?php echo str_replace(',', ', ', $match['organs_to_donate']); ?>
                                        <br>
                                        <button class="btn btn-success btn-sm" onclick="openTransplantModal('<?php echo $match['donor_id']; ?>', '<?php echo $match['recipient_id']; ?>', '<?php echo $match['organ_needed']; ?>')" style="margin-top: 10px;">
                                            <i class="fas fa-procedures"></i> Schedule Transplant
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-radius: 5px;">
                                        <strong>No compatible donor found</strong>
                                    </div>
                                <?php endif; ?>
                        <?php 
                            endif;
                        endforeach; 
                        if ($current_recipient !== null): echo "</div>"; endif;
                        ?>
                    </div>
                </div>

            <?php elseif ($current_page === 'transplants' && in_array($user_role, ['admin', 'doctor'])): ?>
                <!-- Transplants Page -->
                <div class="page-header">
                    <div>
                        <h1 class="page-title">Organ Transplants</h1>
                        <p class="page-subtitle">Track organ transplant procedures</p>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('addTransplantModal')">
                        <i class="fas fa-plus"></i> Record Transplant
                    </button>
                </div>

                <div class="content-card">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Transplant ID</th>
                                    <th>Donor</th>
                                    <th>Recipient</th>
                                    <th>Organ</th>
                                    <th>Date</th>
                                    <th>Surgeon</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transplants as $transplant): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transplant['transplant_id']); ?></td>
                                        <td><?php echo htmlspecialchars($transplant['donor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($transplant['recipient_name']); ?></td>
                                        <td><?php echo ucfirst($transplant['organ_type']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($transplant['transplant_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($transplant['surgeon_name']); ?></td>
                                        <td><?php echo $transplant['surgery_duration']; ?> hrs</td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch($transplant['transplant_status']) {
                                                case 'successful': $status_class = 'badge-success'; break;
                                                case 'failed': $status_class = 'badge-danger'; break;
                                                case 'pending': $status_class = 'badge-warning'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($transplant['transplant_status']); ?></span>
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
                <h3 class="modal-title">Register Organ Donor</h3>
                <button class="close" onclick="closeModal('addDonorModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="register_donor">
                    
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
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" name="date_of_birth" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender *</label>
                            <select name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
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
                            <label class="form-label">Donor Status</label>
                            <select name="donor_status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="deceased">Deceased</option>
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
                    
                    <div class="form-group">
                        <label class="form-label">Organs to Donate *</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                            <div class="form-check">
                                <input type="checkbox" name="organs_to_donate[]" value="heart" id="heart">
                                <label for="heart">Heart</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="organs_to_donate[]" value="liver" id="liver">
                                <label for="liver">Liver</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="organs_to_donate[]" value="kidney" id="kidney">
                                <label for="kidney">Kidney</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="organs_to_donate[]" value="lung" id="lung">
                                <label for="lung">Lung</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="organs_to_donate[]" value="pancreas" id="pancreas">
                                <label for="pancreas">Pancreas</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="organs_to_donate[]" value="cornea" id="cornea">
                                <label for="cornea">Cornea</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Consent Date *</label>
                            <input type="date" name="consent_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
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

    <!-- Add Recipient Modal -->
    <div id="addRecipientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Organ Recipient</h3>
                <button class="close" onclick="closeModal('addRecipientModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_recipient">
                    
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
                            <label class="form-label">Doctor *</label>
                            <select name="doctor_id" class="form-control" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($all_doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Organ Needed *</label>
                            <select name="organ_needed" class="form-control" required>
                                <option value="">Select Organ</option>
                                <option value="heart">Heart</option>
                                <option value="liver">Liver</option>
                                <option value="kidney">Kidney</option>
                                <option value="lung">Lung</option>
                                <option value="pancreas">Pancreas</option>
                                <option value="cornea">Cornea</option>
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
                            <label class="form-label">Urgency Level *</label>
                            <select name="urgency_level" class="form-control" required>
                                <option value="">Select Urgency</option>
                                <option value="critical">Critical</option>
                                <option value="urgent">Urgent</option>
                                <option value="normal">Normal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Priority Score *</label>
                            <input type="number" name="priority_score" class="form-control" min="1" max="100" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Registration Date *</label>
                            <input type="date" name="registration_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Recipient Status</label>
                            <select name="recipient_status" class="form-control">
                                <option value="waiting">Waiting</option>
                                <option value="matched">Matched</option>
                                <option value="transplanted">Transplanted</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Medical Condition</label>
                        <textarea name="medical_condition" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Recipient
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addRecipientModal')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Transplant Modal -->
    <div id="addTransplantModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Record Organ Transplant</h3>
                <button class="close" onclick="closeModal('addTransplantModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="record_transplant">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Donor *</label>
                            <select name="donor_id" class="form-control" required>
                                <option value="">Select Donor</option>
                                <?php foreach ($all_donors as $donor): ?>
                                    <option value="<?php echo $donor['id']; ?>"><?php echo htmlspecialchars($donor['name'] . ' (' . $donor['donor_id'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Recipient *</label>
                            <select name="recipient_id" class="form-control" required>
                                <option value="">Select Recipient</option>
                                <?php foreach ($all_recipients as $recipient): ?>
                                    <option value="<?php echo $recipient['id']; ?>"><?php echo htmlspecialchars($recipient['name'] . ' (' . $recipient['recipient_id'] . ') - ' . $recipient['organ_needed']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Organ Type *</label>
                            <select name="organ_type" class="form-control" required>
                                <option value="">Select Organ</option>
                                <option value="heart">Heart</option>
                                <option value="liver">Liver</option>
                                <option value="kidney">Kidney</option>
                                <option value="lung">Lung</option>
                                <option value="pancreas">Pancreas</option>
                                <option value="cornea">Cornea</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Surgeon *</label>
                            <select name="surgeon_id" class="form-control" required>
                                <option value="">Select Surgeon</option>
                                <?php foreach ($all_doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Transplant Date *</label>
                            <input type="date" name="transplant_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Surgery Duration (hours)</label>
                            <input type="number" name="surgery_duration" class="form-control" step="0.5" min="0" max="24">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Hospital Unit</label>
                            <input type="text" name="hospital_unit" class="form-control" placeholder="e.g., OR-1, ICU-2">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Transplant Status</label>
                            <select name="transplant_status" class="form-control">
                                <option value="successful">Successful</option>
                                <option value="failed">Failed</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Complications</label>
                        <textarea name="complications" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Record Transplant
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addTransplantModal')">Cancel</button>
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

        function openTransplantModal(donorId, recipientId, organType) {
            // Pre-fill the transplant modal with matched donor and recipient
            document.querySelector('[name="donor_id"]').value = donorId;
            document.querySelector('[name="recipient_id"]').value = recipientId;
            document.querySelector('[name="organ_type"]').value = organType;
            openModal('addTransplantModal');
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
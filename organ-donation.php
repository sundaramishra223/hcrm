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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register_donor':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $stmt = $db->prepare("INSERT INTO organ_donors (donor_id, first_name, last_name, email, phone, date_of_birth, gender, blood_group, address, emergency_contact, emergency_phone, medical_history, organs_to_donate, consent_date, next_of_kin, relationship, status, registered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
                    
                    $donor_id = 'OD' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $organs_to_donate = implode(',', $_POST['organs_to_donate'] ?? []);
                    
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
                        $_POST['emergency_contact'],
                        $_POST['emergency_phone'],
                        $_POST['medical_history'],
                        $organs_to_donate,
                        $_POST['consent_date'],
                        $_POST['next_of_kin'],
                        $_POST['relationship'],
                        $user_id
                    ]);
                    $success = "Organ donor registered successfully!";
                }
                break;
                
            case 'add_recipient':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $stmt = $db->prepare("INSERT INTO organ_recipients (recipient_id, patient_id, organ_needed, blood_group, urgency_level, medical_condition, doctor_id, hospital, date_added, status, notes, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting', ?, ?)");
                    
                    $recipient_id = 'OR' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $recipient_id,
                        $_POST['patient_id'],
                        $_POST['organ_needed'],
                        $_POST['blood_group'],
                        $_POST['urgency_level'],
                        $_POST['medical_condition'],
                        $_POST['doctor_id'],
                        $_POST['hospital'],
                        $_POST['date_added'],
                        $_POST['notes'],
                        $user_id
                    ]);
                    $success = "Organ recipient added successfully!";
                }
                break;
                
            case 'record_transplant':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $stmt = $db->prepare("INSERT INTO organ_transplants (transplant_id, donor_id, recipient_id, organ_type, transplant_date, surgeon_id, hospital, surgery_duration, success_rate, complications, notes, status, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)");
                    
                    $transplant_id = 'TR' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $transplant_id,
                        $_POST['donor_id'],
                        $_POST['recipient_id'],
                        $_POST['organ_type'],
                        $_POST['transplant_date'],
                        $_POST['surgeon_id'],
                        $_POST['hospital'],
                        $_POST['surgery_duration'],
                        $_POST['success_rate'],
                        $_POST['complications'],
                        $_POST['notes'],
                        $user_id
                    ]);
                    
                    // Update recipient status
                    $stmt = $db->prepare("UPDATE organ_recipients SET status = 'transplanted' WHERE id = ?");
                    $stmt->execute([$_POST['recipient_id']]);
                    
                    $success = "Organ transplant recorded successfully!";
                }
                break;
        }
    }
}

// Get statistics
$stats = [];
$stats['total_donors'] = $db->query("SELECT COUNT(*) FROM organ_donors WHERE status = 'active'")->fetchColumn();
$stats['waiting_recipients'] = $db->query("SELECT COUNT(*) FROM organ_recipients WHERE status = 'waiting'")->fetchColumn();
$stats['successful_transplants'] = $db->query("SELECT COUNT(*) FROM organ_transplants WHERE status = 'completed'")->fetchColumn();
$stats['organs_available'] = $db->query("
    SELECT organ_type, COUNT(*) as available_count
    FROM organ_inventory 
    WHERE status = 'available' 
    GROUP BY organ_type
")->fetchAll();

// Get organ donors
$organ_donors = $db->query("
    SELECT * FROM organ_donors 
    WHERE status = 'active' 
    ORDER BY consent_date DESC
")->fetchAll();

// Get organ recipients
$organ_recipients = $db->query("
    SELECT or.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_id as patient_code,
           CONCAT(d.first_name, ' ', d.last_name) as doctor_name
    FROM organ_recipients or 
    JOIN patients p ON or.patient_id = p.id 
    LEFT JOIN doctors d ON or.doctor_id = d.id 
    ORDER BY or.urgency_level DESC, or.date_added ASC
")->fetchAll();

// Get recent transplants
$recent_transplants = $db->query("
    SELECT ot.*, 
           CONCAT(od.first_name, ' ', od.last_name) as donor_name, od.donor_id as donor_code,
           CONCAT(p.first_name, ' ', p.last_name) as recipient_name, or.recipient_id as recipient_code,
           CONCAT(d.first_name, ' ', d.last_name) as surgeon_name
    FROM organ_transplants ot 
    LEFT JOIN organ_donors od ON ot.donor_id = od.id 
    LEFT JOIN organ_recipients or ON ot.recipient_id = or.id 
    LEFT JOIN patients p ON or.patient_id = p.id 
    LEFT JOIN doctors d ON ot.surgeon_id = d.id 
    ORDER BY ot.transplant_date DESC 
    LIMIT 10
")->fetchAll();

// Get organ availability
$organ_availability = $db->query("
    SELECT organ_type, COUNT(*) as available_count
    FROM organ_inventory 
    WHERE status = 'available' 
    GROUP BY organ_type
")->fetchAll();
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
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-icon.donors { background: linear-gradient(135deg, #ff9a9e, #fecfef); }
        .stat-icon.recipients { background: linear-gradient(135deg, #a18cd1, #fbc2eb); }
        .stat-icon.transplants { background: linear-gradient(135deg, #fad0c4, #ffd1ff); }
        .stat-icon.organs { background: linear-gradient(135deg, #ffecd2, #fcb69f); }
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            margin-bottom: 2rem;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            background: white;
            border: none;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .tab-content {
            display: none;
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge.active { background: #d4edda; color: #155724; }
        .badge.waiting { background: #fff3cd; color: #856404; }
        .badge.transplanted { background: #d1ecf1; color: #0c5460; }
        .badge.critical { background: #f8d7da; color: #721c24; }
        .badge.high { background: #ffeaa7; color: #856404; }
        .badge.medium { background: #e2e3e5; color: #383d41; }
        .badge.low { background: #d4edda; color: #155724; }
        .badge.completed { background: #d1ecf1; color: #0c5460; }
        
        .organ-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .organ-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .organ-card h3 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .count {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-heart"></i> Organ Donation Management</h1>
        <div class="nav-links">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon donors">
                    <i class="fas fa-user-heart"></i>
                </div>
                <div>
                    <h3><?php echo number_format($stats['total_donors']); ?></h3>
                    <p>Registered Donors</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon recipients">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div>
                    <h3><?php echo number_format($stats['waiting_recipients']); ?></h3>
                    <p>Waiting Recipients</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon transplants">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div>
                    <h3><?php echo number_format($stats['successful_transplants']); ?></h3>
                    <p>Successful Transplants</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon organs">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <div>
                    <h3><?php echo number_format($stats['organs_available']); ?></h3>
                    <p>Organ Types Available</p>
                </div>
            </div>
        </div>

        <!-- Organ Availability -->
        <div class="tab-content active">
            <h2><i class="fas fa-list"></i> Organ Availability</h2>
            <div class="organ-list">
                <?php 
                $organs = ['Heart', 'Liver', 'Kidney', 'Lung', 'Pancreas', 'Cornea', 'Bone Marrow', 'Skin'];
                foreach ($organs as $organ): 
                    $count = 0;
                    foreach ($organ_availability as $avail) {
                        if ($avail['organ_type'] === $organ) {
                            $count = $avail['available_count'];
                            break;
                        }
                    }
                ?>
                <div class="organ-card">
                    <h3><?php echo $organ; ?></h3>
                    <div class="count"><?php echo $count; ?></div>
                    <small>Available</small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
            <button class="tab active" onclick="showTab('donors')">
                <i class="fas fa-user-heart"></i> Donor Registration
            </button>
            <button class="tab" onclick="showTab('recipients')">
                <i class="fas fa-user-clock"></i> Recipients
            </button>
            <button class="tab" onclick="showTab('transplants')">
                <i class="fas fa-procedures"></i> Transplants
            </button>
            <?php endif; ?>
            <?php if ($user_role === 'patient'): ?>
            <button class="tab active" onclick="showTab('my-status')">
                <i class="fas fa-user"></i> My Status
            </button>
            <button class="tab" onclick="showTab('donation-info')">
                <i class="fas fa-info-circle"></i> Donation Info
            </button>
            <?php endif; ?>
        </div>

        <!-- Donor Registration Tab -->
        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
        <div id="donors" class="tab-content active">
            <h2><i class="fas fa-user-plus"></i> Register Organ Donor</h2>
            <form method="POST">
                <input type="hidden" name="action" value="register_donor">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth *</label>
                        <input type="date" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label>Gender *</label>
                        <select name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Blood Group *</label>
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
                        <label>Consent Date *</label>
                        <input type="date" name="consent_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="3"></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Emergency Contact</label>
                        <input type="text" name="emergency_contact">
                    </div>
                    <div class="form-group">
                        <label>Emergency Phone</label>
                        <input type="tel" name="emergency_phone">
                    </div>
                    <div class="form-group">
                        <label>Next of Kin</label>
                        <input type="text" name="next_of_kin">
                    </div>
                    <div class="form-group">
                        <label>Relationship</label>
                        <input type="text" name="relationship">
                    </div>
                </div>
                <div class="form-group">
                    <label>Organs to Donate *</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="organs_to_donate[]" value="Heart" id="heart">
                            <label for="heart">Heart</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="organs_to_donate[]" value="Liver" id="liver">
                            <label for="liver">Liver</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="organs_to_donate[]" value="Kidney" id="kidney">
                            <label for="kidney">Kidney</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="organs_to_donate[]" value="Lung" id="lung">
                            <label for="lung">Lung</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="organs_to_donate[]" value="Pancreas" id="pancreas">
                            <label for="pancreas">Pancreas</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="organs_to_donate[]" value="Cornea" id="cornea">
                            <label for="cornea">Cornea</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="organs_to_donate[]" value="Bone Marrow" id="bone_marrow">
                            <label for="bone_marrow">Bone Marrow</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="organs_to_donate[]" value="Skin" id="skin">
                            <label for="skin">Skin</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Medical History</label>
                    <textarea name="medical_history" rows="3"></textarea>
                </div>
                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i> Register Donor
                </button>
            </form>

            <h3 style="margin-top: 2rem;"><i class="fas fa-list"></i> Registered Organ Donors</h3>
            <div class="table-container">
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($organ_donors as $donor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($donor['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></td>
                            <td><span class="badge active"><?php echo htmlspecialchars($donor['blood_group']); ?></span></td>
                            <td><?php echo htmlspecialchars($donor['phone']); ?></td>
                            <td><?php echo htmlspecialchars($donor['organs_to_donate']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($donor['consent_date'])); ?></td>
                            <td><span class="badge <?php echo $donor['status']; ?>"><?php echo ucfirst($donor['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recipients Tab -->
        <div id="recipients" class="tab-content">
            <h2><i class="fas fa-plus-circle"></i> Add Organ Recipient</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_recipient">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Patient *</label>
                        <select name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php 
                            $patients = $db->query("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name")->fetchAll();
                            foreach ($patients as $patient): 
                            ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Organ Needed *</label>
                        <select name="organ_needed" required>
                            <option value="">Select Organ</option>
                            <option value="Heart">Heart</option>
                            <option value="Liver">Liver</option>
                            <option value="Kidney">Kidney</option>
                            <option value="Lung">Lung</option>
                            <option value="Pancreas">Pancreas</option>
                            <option value="Cornea">Cornea</option>
                            <option value="Bone Marrow">Bone Marrow</option>
                            <option value="Skin">Skin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Blood Group *</label>
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
                        <label>Urgency Level *</label>
                        <select name="urgency_level" required>
                            <option value="">Select Urgency</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Doctor *</label>
                        <select name="doctor_id" required>
                            <option value="">Select Doctor</option>
                            <?php 
                            $doctors = $db->query("SELECT id, first_name, last_name FROM doctors ORDER BY first_name")->fetchAll();
                            foreach ($doctors as $doctor): 
                            ?>
                            <option value="<?php echo $doctor['id']; ?>">
                                <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Hospital</label>
                        <input type="text" name="hospital" value="Main Hospital">
                    </div>
                    <div class="form-group">
                        <label>Date Added *</label>
                        <input type="date" name="date_added" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Medical Condition *</label>
                    <textarea name="medical_condition" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn">
                    <i class="fas fa-plus-circle"></i> Add Recipient
                </button>
            </form>

            <h3 style="margin-top: 2rem;"><i class="fas fa-list"></i> Organ Recipients</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Recipient ID</th>
                            <th>Patient</th>
                            <th>Organ Needed</th>
                            <th>Blood Group</th>
                            <th>Urgency</th>
                            <th>Doctor</th>
                            <th>Date Added</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($organ_recipients as $recipient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($recipient['recipient_id']); ?></td>
                            <td><?php echo htmlspecialchars($recipient['patient_name']); ?><br>
                                <small><?php echo htmlspecialchars($recipient['patient_code']); ?></small></td>
                            <td><?php echo htmlspecialchars($recipient['organ_needed']); ?></td>
                            <td><span class="badge active"><?php echo htmlspecialchars($recipient['blood_group']); ?></span></td>
                            <td><span class="badge <?php echo $recipient['urgency_level']; ?>"><?php echo ucfirst($recipient['urgency_level']); ?></span></td>
                            <td><?php echo htmlspecialchars($recipient['doctor_name'] ?: 'Not assigned'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($recipient['date_added'])); ?></td>
                            <td><span class="badge <?php echo $recipient['status']; ?>"><?php echo ucfirst($recipient['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transplants Tab -->
        <div id="transplants" class="tab-content">
            <h2><i class="fas fa-plus-circle"></i> Record Organ Transplant</h2>
            <form method="POST">
                <input type="hidden" name="action" value="record_transplant">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Donor *</label>
                        <select name="donor_id" required>
                            <option value="">Select Donor</option>
                            <?php foreach ($organ_donors as $donor): ?>
                            <option value="<?php echo $donor['id']; ?>">
                                <?php echo htmlspecialchars($donor['donor_id'] . ' - ' . $donor['first_name'] . ' ' . $donor['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Recipient *</label>
                        <select name="recipient_id" required>
                            <option value="">Select Recipient</option>
                            <?php foreach ($organ_recipients as $recipient): ?>
                            <option value="<?php echo $recipient['id']; ?>">
                                <?php echo htmlspecialchars($recipient['recipient_id'] . ' - ' . $recipient['patient_name'] . ' (' . $recipient['organ_needed'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Organ Type *</label>
                        <select name="organ_type" required>
                            <option value="">Select Organ</option>
                            <option value="Heart">Heart</option>
                            <option value="Liver">Liver</option>
                            <option value="Kidney">Kidney</option>
                            <option value="Lung">Lung</option>
                            <option value="Pancreas">Pancreas</option>
                            <option value="Cornea">Cornea</option>
                            <option value="Bone Marrow">Bone Marrow</option>
                            <option value="Skin">Skin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Transplant Date *</label>
                        <input type="date" name="transplant_date" required>
                    </div>
                    <div class="form-group">
                        <label>Surgeon *</label>
                        <select name="surgeon_id" required>
                            <option value="">Select Surgeon</option>
                            <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>">
                                <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Hospital</label>
                        <input type="text" name="hospital" value="Main Hospital">
                    </div>
                    <div class="form-group">
                        <label>Surgery Duration (hours)</label>
                        <input type="number" name="surgery_duration" step="0.5" min="0">
                    </div>
                    <div class="form-group">
                        <label>Success Rate (%)</label>
                        <input type="number" name="success_rate" min="0" max="100">
                    </div>
                </div>
                <div class="form-group">
                    <label>Complications</label>
                    <textarea name="complications" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn">
                    <i class="fas fa-plus-circle"></i> Record Transplant
                </button>
            </form>

            <h3 style="margin-top: 2rem;"><i class="fas fa-history"></i> Recent Transplants</h3>
            <div class="table-container">
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transplants as $transplant): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($transplant['transplant_id']); ?></td>
                            <td><?php echo htmlspecialchars($transplant['donor_name'] ?: 'Anonymous'); ?><br>
                                <small><?php echo htmlspecialchars($transplant['donor_code'] ?: ''); ?></small></td>
                            <td><?php echo htmlspecialchars($transplant['recipient_name'] ?: 'Unknown'); ?><br>
                                <small><?php echo htmlspecialchars($transplant['recipient_code'] ?: ''); ?></small></td>
                            <td><?php echo htmlspecialchars($transplant['organ_type']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($transplant['transplant_date'])); ?></td>
                            <td><?php echo htmlspecialchars($transplant['surgeon_name'] ?: 'Not specified'); ?></td>
                            <td><?php echo $transplant['surgery_duration'] ? $transplant['surgery_duration'] . ' hrs' : '-'; ?></td>
                            <td><span class="badge <?php echo $transplant['status']; ?>"><?php echo ucfirst($transplant['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Patient Dashboard -->
        <?php if ($user_role === 'patient'): ?>
        <div id="my-status" class="tab-content active">
            <h2><i class="fas fa-user"></i> My Organ Donation Status</h2>
            <?php
            // Get patient's organ donor record
            $patient_donor = $db->prepare("SELECT * FROM organ_donors WHERE email = ? OR phone = ?");
            $patient_info = $db->prepare("SELECT email, phone FROM patients WHERE id = ?");
            $patient_info->execute([$_SESSION['patient_id'] ?? 0]);
            $patient_data = $patient_info->fetch();
            
            if ($patient_data) {
                $patient_donor->execute([$patient_data['email'], $patient_data['phone']]);
                $donor_record = $patient_donor->fetch();
            }
            
            // Check if patient is a recipient
            $patient_recipient = $db->prepare("SELECT * FROM organ_recipients WHERE patient_id = ?");
            $patient_recipient->execute([$_SESSION['patient_id'] ?? 0]);
            $recipient_record = $patient_recipient->fetch();
            ?>
            
            <?php if ($donor_record): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon donors">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div>
                            <h3>Registered</h3>
                            <p>Organ Donor</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon organs">
                            <i class="fas fa-list"></i>
                        </div>
                        <div>
                            <h3><?php echo count(explode(',', $donor_record['organs_to_donate'])); ?></h3>
                            <p>Organs to Donate</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon transplants">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div>
                            <h3><?php echo date('M d, Y', strtotime($donor_record['consent_date'])); ?></h3>
                            <p>Consent Date</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon recipients">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <h3><?php echo ucfirst($donor_record['status']); ?></h3>
                            <p>Status</p>
                        </div>
                    </div>
                </div>
                
                <div style="background: white; padding: 2rem; border-radius: 10px; margin-top: 2rem;">
                    <h3>Organs I'm Donating:</h3>
                    <p style="font-size: 18px; margin-top: 1rem;"><?php echo htmlspecialchars($donor_record['organs_to_donate']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($recipient_record): ?>
                <div style="background: white; padding: 2rem; border-radius: 10px; margin-top: 2rem;">
                    <h3>My Recipient Status:</h3>
                    <div class="stats-grid" style="margin-top: 1rem;">
                        <div class="stat-card">
                            <div class="stat-icon recipients">
                                <i class="fas fa-heartbeat"></i>
                            </div>
                            <div>
                                <h3><?php echo htmlspecialchars($recipient_record['organ_needed']); ?></h3>
                                <p>Organ Needed</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon organs">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h3><?php echo ucfirst($recipient_record['urgency_level']); ?></h3>
                                <p>Urgency Level</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon transplants">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div>
                                <h3><?php echo ucfirst($recipient_record['status']); ?></h3>
                                <p>Status</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!$donor_record && !$recipient_record): ?>
                <div class="alert" style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                    <i class="fas fa-info-circle"></i> You are not currently registered as an organ donor or recipient. Contact our organ donation coordinator to learn more about organ donation.
                </div>
            <?php endif; ?>
        </div>

        <div id="donation-info" class="tab-content">
            <h2><i class="fas fa-info-circle"></i> Organ Donation Information</h2>
            <div style="background: white; padding: 2rem; border-radius: 10px;">
                <h3>About Organ Donation</h3>
                <p style="margin: 1rem 0;">Organ donation is the process of surgically removing an organ or tissue from one person (the organ donor) and placing it into another person (the recipient). Transplantation is necessary because the recipient's organ has failed or has been damaged by disease or injury.</p>
                
                <h3 style="margin-top: 2rem;">Organs That Can Be Donated</h3>
                <div class="organ-list" style="margin-top: 1rem;">
                    <div class="organ-card">
                        <h3>Heart</h3>
                        <p>Can save one life</p>
                    </div>
                    <div class="organ-card">
                        <h3>Liver</h3>
                        <p>Can save up to 2 lives</p>
                    </div>
                    <div class="organ-card">
                        <h3>Kidneys</h3>
                        <p>Can save up to 2 lives</p>
                    </div>
                    <div class="organ-card">
                        <h3>Lungs</h3>
                        <p>Can save up to 2 lives</p>
                    </div>
                    <div class="organ-card">
                        <h3>Pancreas</h3>
                        <p>Can save one life</p>
                    </div>
                    <div class="organ-card">
                        <h3>Corneas</h3>
                        <p>Can restore sight</p>
                    </div>
                </div>
                
                <h3 style="margin-top: 2rem;">How to Become a Donor</h3>
                <ol style="margin: 1rem 0; padding-left: 2rem;">
                    <li>Register your decision to donate</li>
                    <li>Tell your family about your decision</li>
                    <li>Carry a donor card or mark your driver's license</li>
                    <li>Keep your information up to date</li>
                </ol>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <p style="font-size: 18px; color: #667eea; font-weight: bold;">One donor can save up to 8 lives and enhance the lives of up to 75 others.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
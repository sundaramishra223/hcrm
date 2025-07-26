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
                    $stmt = $db->prepare("INSERT INTO organ_donors (donor_id, first_name, last_name, email, phone, date_of_birth, gender, blood_type, address, emergency_contact, emergency_phone, medical_history, organs_to_donate, consent_date, consent_witness, status, registered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $donor_id = 'OD' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $organs_to_donate = implode(',', $_POST['organs_to_donate']);
                    
                    $stmt->execute([
                        $donor_id, $_POST['first_name'], $_POST['last_name'], $_POST['email'], 
                        $_POST['phone'], $_POST['date_of_birth'], $_POST['gender'], $_POST['blood_type'],
                        $_POST['address'], $_POST['emergency_contact'], $_POST['emergency_phone'],
                        $_POST['medical_history'], $organs_to_donate, $_POST['consent_date'],
                        $_POST['consent_witness'], 'active', $user_id
                    ]);
                    
                    $success_message = "Organ donor registered successfully!";
                }
                break;
                
            case 'register_recipient':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $stmt = $db->prepare("INSERT INTO organ_recipients (recipient_id, patient_id, organ_needed, blood_type, urgency_level, medical_condition, doctor_id, registration_date, priority_score, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $recipient_id = 'OR' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $recipient_id, $_POST['patient_id'], $_POST['organ_needed'], $_POST['blood_type'],
                        $_POST['urgency_level'], $_POST['medical_condition'], $user_id,
                        date('Y-m-d'), $_POST['priority_score'], 'waiting'
                    ]);
                    
                    $success_message = "Organ recipient registered successfully!";
                }
                break;
                
            case 'record_transplant':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $stmt = $db->prepare("INSERT INTO organ_transplants (transplant_id, donor_id, recipient_id, organ_type, transplant_date, surgeon_id, hospital, operation_duration, complications, success_rate, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $transplant_id = 'TX' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $transplant_id, $_POST['donor_id'], $_POST['recipient_id'], $_POST['organ_type'],
                        $_POST['transplant_date'], $user_id, $_POST['hospital'], $_POST['operation_duration'],
                        $_POST['complications'], $_POST['success_rate'], $_POST['notes'], 'completed'
                    ]);
                    
                    // Update recipient status
                    $stmt = $db->prepare("UPDATE organ_recipients SET status = 'transplanted' WHERE id = ?");
                    $stmt->execute([$_POST['recipient_id']]);
                    
                    $success_message = "Transplant recorded successfully!";
                }
                break;
        }
    }
}

// Get statistics
$stats = [];
$stats['total_donors'] = $db->query("SELECT COUNT(*) as count FROM organ_donors WHERE status = 'active'")->fetch()['count'];
$stats['waiting_recipients'] = $db->query("SELECT COUNT(*) as count FROM organ_recipients WHERE status = 'waiting'")->fetch()['count'];
$stats['successful_transplants'] = $db->query("SELECT COUNT(*) as count FROM organ_transplants WHERE status = 'completed'")->fetch()['count'];
$stats['organs_available'] = $db->query("SELECT COUNT(*) as count FROM organ_donors WHERE status = 'active'")->fetch()['count'];

// Get organ availability
$organ_types = ['Heart', 'Liver', 'Kidney', 'Lung', 'Pancreas', 'Cornea', 'Bone', 'Skin'];
$organ_availability = [];
foreach ($organ_types as $organ) {
    $count = $db->query("SELECT COUNT(*) as count FROM organ_donors WHERE status = 'active' AND FIND_IN_SET('$organ', organs_to_donate) > 0")->fetch()['count'];
    $organ_availability[$organ] = $count;
}

// Get recent transplants
$recent_transplants = $db->query("SELECT ot.*, CONCAT(od.first_name, ' ', od.last_name) as donor_name, CONCAT(p.first_name, ' ', p.last_name) as recipient_name, CONCAT(u.first_name, ' ', u.last_name) as surgeon_name FROM organ_transplants ot LEFT JOIN organ_donors od ON ot.donor_id = od.id LEFT JOIN organ_recipients ore ON ot.recipient_id = ore.id LEFT JOIN patients p ON ore.patient_id = p.id LEFT JOIN users u ON ot.surgeon_id = u.id ORDER BY ot.transplant_date DESC LIMIT 10")->fetchAll();

// Get waiting recipients
$waiting_recipients = $db->query("SELECT ore.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_id as patient_code, CONCAT(u.first_name, ' ', u.last_name) as doctor_name FROM organ_recipients ore LEFT JOIN patients p ON ore.patient_id = p.id LEFT JOIN users u ON ore.doctor_id = u.id WHERE ore.status = 'waiting' ORDER BY ore.urgency_level DESC, ore.priority_score DESC")->fetchAll();

// Get donors for dropdowns
$donors = $db->query("SELECT * FROM organ_donors WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll();

// Get patients for dropdowns
$patients = $db->query("SELECT * FROM patients ORDER BY first_name, last_name")->fetchAll();

// Get recipients for dropdowns
$recipients = $db->query("SELECT ore.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name FROM organ_recipients ore LEFT JOIN patients p ON ore.patient_id = p.id WHERE ore.status = 'waiting' ORDER BY p.first_name, p.last_name")->fetchAll();
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .container {
            max-width: 1200px;
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
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-card.donors i { color: #28a745; }
        .stat-card.recipients i { color: #ffc107; }
        .stat-card.transplants i { color: #17a2b8; }
        .stat-card.available i { color: #6f42c1; }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .tab.active {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tab-content.active {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .badge.active { background: #d4edda; color: #155724; }
        .badge.waiting { background: #fff3cd; color: #856404; }
        .badge.transplanted { background: #d1ecf1; color: #0c5460; }
        .badge.urgent { background: #f8d7da; color: #721c24; }
        .badge.high { background: #ffeaa7; color: #d63031; }
        .badge.medium { background: #81ecec; color: #00b894; }
        .badge.low { background: #a29bfe; color: #6c5ce7; }
        .badge.completed { background: #d4edda; color: #155724; }

        .organ-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .organ-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #28a745;
        }

        .organ-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 0.5rem;
        }

        .organ-count {
            font-size: 1.5rem;
            color: #17a2b8;
            margin-bottom: 0.5rem;
        }

        .organ-info {
            font-size: 0.875rem;
            color: #666;
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

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            text-decoration: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="header">
        <h1><i class="fas fa-heart"></i> Organ Donation Management</h1>
        <p>Comprehensive Organ Donation & Transplant System</p>
    </div>

    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card donors">
                <i class="fas fa-user-heart"></i>
                <div class="stat-number"><?php echo $stats['total_donors']; ?></div>
                <div>Registered Donors</div>
            </div>
            <div class="stat-card recipients">
                <i class="fas fa-user-clock"></i>
                <div class="stat-number"><?php echo $stats['waiting_recipients']; ?></div>
                <div>Waiting Recipients</div>
            </div>
            <div class="stat-card transplants">
                <i class="fas fa-procedures"></i>
                <div class="stat-number"><?php echo $stats['successful_transplants']; ?></div>
                <div>Successful Transplants</div>
            </div>
            <div class="stat-card available">
                <i class="fas fa-hand-holding-heart"></i>
                <div class="stat-number"><?php echo $stats['organs_available']; ?></div>
                <div>Available Donors</div>
            </div>
        </div>

        <!-- Organ Availability -->
        <div class="tab-content active">
            <h3><i class="fas fa-hand-holding-heart"></i> Organ Availability</h3>
            <div class="organ-grid">
                <?php foreach ($organ_availability as $organ => $count): ?>
                    <div class="organ-card">
                        <div class="organ-name"><?php echo $organ; ?></div>
                        <div class="organ-count"><?php echo $count; ?></div>
                        <div class="organ-info">Donors Available</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('availability')">
                <i class="fas fa-hand-holding-heart"></i> Availability
            </div>
            <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
            <div class="tab" onclick="showTab('donors')">
                <i class="fas fa-user-plus"></i> Donors
            </div>
            <?php endif; ?>
            <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
            <div class="tab" onclick="showTab('recipients')">
                <i class="fas fa-user-clock"></i> Recipients
            </div>
            <div class="tab" onclick="showTab('transplants')">
                <i class="fas fa-procedures"></i> Transplants
            </div>
            <?php endif; ?>
        </div>

        <!-- Donor Registration Tab -->
        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
        <div id="donors" class="tab-content">
            <h3><i class="fas fa-user-plus"></i> Donor Registration</h3>
            
            <form method="POST" class="form-grid">
                <input type="hidden" name="action" value="register_donor">
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
                    <label>Blood Type *</label>
                    <select name="blood_type" required>
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
                    <label>Emergency Contact</label>
                    <input type="text" name="emergency_contact">
                </div>
                <div class="form-group">
                    <label>Emergency Phone</label>
                    <input type="tel" name="emergency_phone">
                </div>
                <div class="form-group">
                    <label>Consent Date *</label>
                    <input type="date" name="consent_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Consent Witness</label>
                    <input type="text" name="consent_witness">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Address</label>
                    <textarea name="address" rows="3"></textarea>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Medical History</label>
                    <textarea name="medical_history" rows="3"></textarea>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
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
                            <input type="checkbox" name="organs_to_donate[]" value="Bone" id="bone">
                            <label for="bone">Bone</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="organs_to_donate[]" value="Skin" id="skin">
                            <label for="skin">Skin</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-user-plus"></i> Register Donor
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Recipient Registration Tab -->
        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
        <div id="recipients" class="tab-content">
            <h3><i class="fas fa-user-clock"></i> Recipient Registration</h3>
            
            <form method="POST" class="form-grid">
                <input type="hidden" name="action" value="register_recipient">
                <div class="form-group">
                    <label>Patient *</label>
                    <select name="patient_id" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
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
                        <option value="Bone">Bone</option>
                        <option value="Skin">Skin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Blood Type *</label>
                    <select name="blood_type" required>
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
                    <label>Urgency Level *</label>
                    <select name="urgency_level" required>
                        <option value="">Select Urgency</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority Score (1-100) *</label>
                    <input type="number" name="priority_score" min="1" max="100" required>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Medical Condition *</label>
                    <textarea name="medical_condition" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-user-clock"></i> Register Recipient
                    </button>
                </div>
            </form>

            <h4>Waiting Recipients</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Recipient ID</th>
                        <th>Patient</th>
                        <th>Organ Needed</th>
                        <th>Blood Type</th>
                        <th>Urgency</th>
                        <th>Priority Score</th>
                        <th>Registration Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($waiting_recipients as $recipient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($recipient['recipient_id']); ?></td>
                            <td><?php echo htmlspecialchars($recipient['patient_name']); ?><br>
                                <small><?php echo htmlspecialchars($recipient['patient_code']); ?></small></td>
                            <td><?php echo htmlspecialchars($recipient['organ_needed']); ?></td>
                            <td><?php echo htmlspecialchars($recipient['blood_type']); ?></td>
                            <td><span class="badge <?php echo $recipient['urgency_level']; ?>"><?php echo ucfirst($recipient['urgency_level']); ?></span></td>
                            <td><?php echo $recipient['priority_score']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($recipient['registration_date'])); ?></td>
                            <td><span class="badge waiting"><?php echo ucfirst($recipient['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Transplant Management Tab -->
        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
        <div id="transplants" class="tab-content">
            <h3><i class="fas fa-procedures"></i> Transplant Management</h3>
            
            <form method="POST" class="form-grid">
                <input type="hidden" name="action" value="record_transplant">
                <div class="form-group">
                    <label>Donor *</label>
                    <select name="donor_id" required>
                        <option value="">Select Donor</option>
                        <?php foreach ($donors as $donor): ?>
                            <option value="<?php echo $donor['id']; ?>">
                                <?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name'] . ' (' . $donor['blood_type'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Recipient *</label>
                    <select name="recipient_id" required>
                        <option value="">Select Recipient</option>
                        <?php foreach ($recipients as $recipient): ?>
                            <option value="<?php echo $recipient['id']; ?>">
                                <?php echo htmlspecialchars($recipient['patient_name'] . ' (' . $recipient['organ_needed'] . ')'); ?>
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
                        <option value="Bone">Bone</option>
                        <option value="Skin">Skin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Transplant Date *</label>
                    <input type="date" name="transplant_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Hospital</label>
                    <input type="text" name="hospital" value="Main Hospital">
                </div>
                <div class="form-group">
                    <label>Operation Duration (hours)</label>
                    <input type="number" name="operation_duration" step="0.5" min="0">
                </div>
                <div class="form-group">
                    <label>Success Rate (%)</label>
                    <input type="number" name="success_rate" min="0" max="100" value="95">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Complications</label>
                    <textarea name="complications" rows="3"></textarea>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-procedures"></i> Record Transplant
                    </button>
                </div>
            </form>

            <h4>Recent Transplants</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Transplant ID</th>
                        <th>Donor</th>
                        <th>Recipient</th>
                        <th>Organ</th>
                        <th>Date</th>
                        <th>Surgeon</th>
                        <th>Success Rate</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transplants as $transplant): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($transplant['transplant_id']); ?></td>
                            <td><?php echo htmlspecialchars($transplant['donor_name']); ?></td>
                            <td><?php echo htmlspecialchars($transplant['recipient_name']); ?></td>
                            <td><?php echo htmlspecialchars($transplant['organ_type']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($transplant['transplant_date'])); ?></td>
                            <td><?php echo htmlspecialchars($transplant['surgeon_name']); ?></td>
                            <td><?php echo $transplant['success_rate']; ?>%</td>
                            <td><span class="badge completed"><?php echo ucfirst($transplant['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
            const selectedContent = document.getElementById(tabName);
            if (selectedContent) {
                selectedContent.classList.add('active');
            } else {
                // Show availability by default
                tabContents[0].classList.add('active');
            }

            // Add active class to clicked tab
            event.target.closest('.tab').classList.add('active');
        }
    </script>
</body>
</html>
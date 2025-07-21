<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle form submissions
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'add_organ_donor':
                $organs_array = isset($_POST['organs_to_donate']) ? $_POST['organs_to_donate'] : [];
                $organs_json = json_encode($organs_array);
                
                $donor_sql = "INSERT INTO organ_donors (donor_id, first_name, last_name, email, phone, blood_group, date_of_birth, gender, address, emergency_contact, organs_to_donate, consent_type, consent_date, consent_witness, medical_history, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $db->query($donor_sql, [
                    $_POST['donor_id'], $_POST['first_name'], $_POST['last_name'], $_POST['email'],
                    $_POST['phone'], $_POST['blood_group'], $_POST['date_of_birth'], $_POST['gender'],
                    $_POST['address'], $_POST['emergency_contact'], $organs_json, $_POST['consent_type'],
                    $_POST['consent_date'], $_POST['consent_witness'], $_POST['medical_history'], $_SESSION['user_id']
                ]);
                $message = "Organ donor registered successfully!";
                break;
                
            case 'add_organ_recipient':
                $recipient_sql = "INSERT INTO organ_recipients (recipient_id, patient_id, organ_needed, blood_group, urgency_level, medical_condition, date_added_to_list, priority_score, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $db->query($recipient_sql, [
                    $_POST['recipient_id'], $_POST['patient_id'], $_POST['organ_needed'], $_POST['blood_group'],
                    $_POST['urgency_level'], $_POST['medical_condition'], $_POST['date_added_to_list'],
                    $_POST['priority_score'], $_POST['notes'], $_SESSION['user_id']
                ]);
                $message = "Organ recipient added to waiting list successfully!";
                break;
                
            case 'create_organ_match':
                $match_sql = "INSERT INTO organ_matches (donor_id, recipient_id, organ_type, compatibility_score, status, coordinator_id, notes) VALUES (?, ?, ?, ?, 'potential', ?, ?)";
                $db->query($match_sql, [
                    $_POST['donor_id'], $_POST['recipient_id'], $_POST['organ_type'],
                    $_POST['compatibility_score'], $_SESSION['user_id'], $_POST['notes']
                ]);
                $message = "Organ match created successfully!";
                break;
                
            case 'schedule_transplant':
                $transplant_id = 'TR' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $transplant_sql = "INSERT INTO transplant_records (transplant_id, donor_id, recipient_id, organ_type, surgery_date, surgeon_id, coordinator_id, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)";
                $db->query($transplant_sql, [
                    $transplant_id, $_POST['donor_id'], $_POST['recipient_id'], $_POST['organ_type'],
                    $_POST['surgery_date'], $_POST['surgeon_id'], $_SESSION['user_id'], $_POST['notes']
                ]);
                
                // Update match status
                if (!empty($_POST['match_id'])) {
                    $db->query("UPDATE organ_matches SET status = 'allocated' WHERE id = ?", [$_POST['match_id']]);
                }
                
                // Update recipient status
                $db->query("UPDATE organ_recipients SET status = 'matched' WHERE id = ?", [$_POST['recipient_id']]);
                
                $message = "Transplant scheduled successfully! Transplant ID: $transplant_id";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get statistics
$stats = [
    'total_donors' => $db->query("SELECT COUNT(*) as count FROM organ_donors WHERE is_active = 1")->fetch()['count'],
    'total_recipients' => $db->query("SELECT COUNT(*) as count FROM organ_recipients WHERE status = 'active'")->fetch()['count'],
    'pending_matches' => $db->query("SELECT COUNT(*) as count FROM organ_matches WHERE status = 'potential'")->fetch()['count'],
    'scheduled_transplants' => $db->query("SELECT COUNT(*) as count FROM transplant_records WHERE status = 'scheduled'")->fetch()['count']
];

// Get organ statistics
$organ_stats = $db->query("
    SELECT ot.organ_name, 
           COUNT(DISTINCT od.id) as donor_count,
           COUNT(DISTINCT ore.id) as recipient_count
    FROM organ_types ot
    LEFT JOIN organ_donors od ON JSON_CONTAINS(od.organs_to_donate, CONCAT('\"', ot.organ_name, '\"')) AND od.is_active = 1
    LEFT JOIN organ_recipients ore ON ore.organ_needed = ot.organ_name AND ore.status = 'active'
    WHERE ot.is_active = 1
    GROUP BY ot.organ_name
    ORDER BY ot.organ_name
")->fetchAll();

// Get recent data
$recent_donors = $db->query("SELECT * FROM organ_donors WHERE is_active = 1 ORDER BY registered_date DESC LIMIT 10")->fetchAll();
$active_recipients = $db->query("
    SELECT ore.*, p.first_name as patient_first_name, p.last_name as patient_last_name
    FROM organ_recipients ore
    JOIN patients p ON ore.patient_id = p.id
    WHERE ore.status = 'active'
    ORDER BY ore.priority_score DESC, ore.date_added_to_list ASC
    LIMIT 10
")->fetchAll();

$pending_matches = $db->query("
    SELECT om.*, 
           od.first_name as donor_first_name, od.last_name as donor_last_name,
           ore.recipient_id, p.first_name as recipient_first_name, p.last_name as recipient_last_name
    FROM organ_matches om
    JOIN organ_donors od ON om.donor_id = od.id
    JOIN organ_recipients ore ON om.recipient_id = ore.id
    JOIN patients p ON ore.patient_id = p.id
    WHERE om.status = 'potential'
    ORDER BY om.compatibility_score DESC
    LIMIT 10
")->fetchAll();

$scheduled_transplants = $db->query("
    SELECT tr.*, 
           od.first_name as donor_first_name, od.last_name as donor_last_name,
           ore.recipient_id, p.first_name as recipient_first_name, p.last_name as recipient_last_name,
           s.first_name as surgeon_first_name, s.last_name as surgeon_last_name
    FROM transplant_records tr
    JOIN organ_donors od ON tr.donor_id = od.id
    JOIN organ_recipients ore ON tr.recipient_id = ore.id
    JOIN patients p ON ore.patient_id = p.id
    LEFT JOIN staff s ON tr.surgeon_id = s.id
    WHERE tr.status = 'scheduled'
    ORDER BY tr.surgery_date ASC
")->fetchAll();

// Get data for dropdowns
$organ_types = $db->query("SELECT * FROM organ_types WHERE is_active = 1 ORDER BY organ_name")->fetchAll();
$blood_groups = $db->query("SELECT * FROM blood_groups WHERE is_active = 1")->fetchAll();
$patients = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM patients WHERE is_active = 1 ORDER BY first_name")->fetchAll();
$surgeons = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM staff WHERE staff_type = 'doctor' AND is_active = 1 ORDER BY first_name")->fetchAll();
$active_donors = $db->query("SELECT * FROM organ_donors WHERE is_active = 1 AND is_eligible = 1 ORDER BY first_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    include_once 'includes/site-config.php';
    renderSiteHead('Organ Donation Management');
    ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
    <style>
        .organ-donation {
            padding: 20px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }
        
        .page-header {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .organ-stats {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }
        
        .organ-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .organ-stat-item {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #e74c3c;
        }
        
        .organ-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 8px;
        }
        
        .organ-counts {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .management-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 12px 20px;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .tab-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-section {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            background: var(--bg-card);
            color: var(--text-primary);
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
            padding: 8px;
            background: var(--bg-card);
            border-radius: 4px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .btn {
            background: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        
        .btn-success { background: var(--secondary-color); }
        .btn-warning { background: var(--accent-color); }
        .btn-danger { background: #e74c3c; }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fecaca; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #e0e7ff; color: #3730a3; }
        
        .priority-high { color: #e74c3c; font-weight: bold; }
        .priority-urgent { color: #f39c12; font-weight: bold; }
        .priority-routine { color: var(--text-secondary); }
        
        .compatibility-score {
            font-size: 1.2rem;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
            text-align: center;
        }
        
        .score-excellent { background: #d4edda; color: #155724; }
        .score-good { background: #fff3cd; color: #856404; }
        .score-fair { background: #f8d7da; color: #721c24; }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .countdown {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 5px;
        }
        
        .countdown-critical { background: #fecaca; color: #991b1b; }
        .countdown-warning { background: #fef3c7; color: #92400e; }
        .countdown-normal { background: #dcfce7; color: #166534; }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="organ-donation">
                <div class="page-header">
                    <h1><i class="fas fa-heart"></i> Organ Donation Management</h1>
                    <p>Comprehensive organ donation and transplant coordination system</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Dashboard -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_donors']; ?></div>
                        <div class="stat-label">Registered Donors</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_recipients']; ?></div>
                        <div class="stat-label">Waiting Recipients</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['pending_matches']; ?></div>
                        <div class="stat-label">Pending Matches</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['scheduled_transplants']; ?></div>
                        <div class="stat-label">Scheduled Transplants</div>
                    </div>
                </div>
                
                <!-- Organ Statistics -->
                <div class="organ-stats">
                    <h3><i class="fas fa-chart-bar"></i> Organ-wise Statistics</h3>
                    <div class="organ-stats-grid">
                        <?php foreach ($organ_stats as $organ): ?>
                            <div class="organ-stat-item">
                                <div class="organ-name"><?php echo htmlspecialchars($organ['organ_name']); ?></div>
                                <div class="organ-counts">
                                    <span>Donors: <?php echo $organ['donor_count']; ?></span>
                                    <span>Recipients: <?php echo $organ['recipient_count']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Management Tabs -->
                <div class="management-tabs">
                    <div class="tab-btn active" onclick="showTab('donors')">
                        <i class="fas fa-hand-holding-heart"></i> Donors
                    </div>
                    <div class="tab-btn" onclick="showTab('recipients')">
                        <i class="fas fa-user-injured"></i> Recipients
                    </div>
                    <div class="tab-btn" onclick="showTab('matches')">
                        <i class="fas fa-handshake"></i> Matches
                    </div>
                    <div class="tab-btn" onclick="showTab('transplants')">
                        <i class="fas fa-procedures"></i> Transplants
                    </div>
                </div>
                
                <!-- Donors Tab -->
                <div id="donors" class="tab-content active">
                    <h2>Organ Donor Management</h2>
                    
                    <div class="form-grid">
                        <!-- Add New Donor -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-plus"></i> Register Organ Donor</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_organ_donor">
                                <div class="form-group">
                                    <label>Donor ID</label>
                                    <input type="text" name="donor_id" required placeholder="OD001" value="OD<?php echo str_pad($stats['total_donors'] + 1, 3, '0', STR_PAD_LEFT); ?>">
                                </div>
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" placeholder="optional">
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" name="phone" required>
                                </div>
                                <div class="form-group">
                                    <label>Blood Group</label>
                                    <select name="blood_group" required>
                                        <option value="">Select Blood Group</option>
                                        <?php foreach ($blood_groups as $bg): ?>
                                            <option value="<?php echo $bg['blood_group']; ?>"><?php echo $bg['blood_group']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" name="date_of_birth" required>
                                </div>
                                <div class="form-group">
                                    <label>Gender</label>
                                    <select name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Address</label>
                                    <textarea name="address" rows="3"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Emergency Contact</label>
                                    <input type="tel" name="emergency_contact">
                                </div>
                                <div class="form-group">
                                    <label>Organs to Donate</label>
                                    <div class="checkbox-group">
                                        <?php foreach ($organ_types as $organ): ?>
                                            <div class="checkbox-item">
                                                <input type="checkbox" name="organs_to_donate[]" value="<?php echo $organ['organ_name']; ?>" id="organ_<?php echo $organ['id']; ?>">
                                                <label for="organ_<?php echo $organ['id']; ?>"><?php echo htmlspecialchars($organ['organ_name']); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Consent Type</label>
                                    <select name="consent_type" required>
                                        <option value="deceased_donor">Deceased Donor</option>
                                        <option value="living_donor">Living Donor</option>
                                        <option value="both">Both</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Consent Date</label>
                                    <input type="date" name="consent_date" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Consent Witness</label>
                                    <input type="text" name="consent_witness" placeholder="Witness name">
                                </div>
                                <div class="form-group">
                                    <label>Medical History</label>
                                    <textarea name="medical_history" rows="3" placeholder="Any relevant medical conditions"></textarea>
                                </div>
                                <button type="submit" class="btn">Register Donor</button>
                            </form>
                        </div>
                        
                        <!-- Recent Donors -->
                        <div class="form-section">
                            <h3><i class="fas fa-list"></i> Recent Donors</h3>
                            <div style="max-height: 600px; overflow-y: auto;">
                                <?php foreach ($recent_donors as $donor): ?>
                                    <div style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                                        <strong><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></strong>
                                        <span class="badge badge-<?php echo $donor['is_eligible'] ? 'success' : 'warning'; ?>">
                                            <?php echo $donor['is_eligible'] ? 'Eligible' : 'Under Review'; ?>
                                        </span>
                                        <br><small>
                                            ID: <?php echo $donor['donor_id']; ?> | 
                                            Blood: <?php echo $donor['blood_group']; ?> | 
                                            Phone: <?php echo $donor['phone']; ?>
                                            <br>Consent: <?php echo ucfirst(str_replace('_', ' ', $donor['consent_type'])); ?> | 
                                            Date: <?php echo date('d-M-Y', strtotime($donor['consent_date'])); ?>
                                            <br>Organs: 
                                            <?php 
                                            $organs = json_decode($donor['organs_to_donate'], true);
                                            if ($organs) {
                                                echo implode(', ', array_slice($organs, 0, 3));
                                                if (count($organs) > 3) echo ' +' . (count($organs) - 3) . ' more';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recipients Tab -->
                <div id="recipients" class="tab-content">
                    <h2>Organ Recipient Management</h2>
                    
                    <div class="form-grid">
                        <!-- Add New Recipient -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-plus"></i> Add to Waiting List</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_organ_recipient">
                                <div class="form-group">
                                    <label>Recipient ID</label>
                                    <input type="text" name="recipient_id" required placeholder="OR001" value="OR<?php echo str_pad($stats['total_recipients'] + 1, 3, '0', STR_PAD_LEFT); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Patient</label>
                                    <select name="patient_id" required>
                                        <option value="">Select Patient</option>
                                        <?php foreach ($patients as $patient): ?>
                                            <option value="<?php echo $patient['id']; ?>"><?php echo htmlspecialchars($patient['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Organ Needed</label>
                                    <select name="organ_needed" required>
                                        <option value="">Select Organ</option>
                                        <?php foreach ($organ_types as $organ): ?>
                                            <option value="<?php echo $organ['organ_name']; ?>"><?php echo htmlspecialchars($organ['organ_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Blood Group</label>
                                    <select name="blood_group" required>
                                        <option value="">Select Blood Group</option>
                                        <?php foreach ($blood_groups as $bg): ?>
                                            <option value="<?php echo $bg['blood_group']; ?>"><?php echo $bg['blood_group']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Urgency Level</label>
                                    <select name="urgency_level" required>
                                        <option value="routine">Routine</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Medical Condition</label>
                                    <textarea name="medical_condition" required rows="3" placeholder="Description of medical condition requiring transplant"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Date Added to List</label>
                                    <input type="date" name="date_added_to_list" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Priority Score (0-100)</label>
                                    <input type="number" name="priority_score" min="0" max="100" value="50">
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn">Add to Waiting List</button>
                            </form>
                        </div>
                        
                        <!-- Active Recipients -->
                        <div class="form-section">
                            <h3><i class="fas fa-hourglass-half"></i> Waiting List</h3>
                            <div style="max-height: 600px; overflow-y: auto;">
                                <?php foreach ($active_recipients as $recipient): ?>
                                    <div style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                                        <strong><?php echo htmlspecialchars($recipient['patient_first_name'] . ' ' . $recipient['patient_last_name']); ?></strong>
                                        <span class="badge badge-<?php echo $recipient['urgency_level'] === 'emergency' ? 'danger' : ($recipient['urgency_level'] === 'urgent' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($recipient['urgency_level']); ?>
                                        </span>
                                        <br><small>
                                            ID: <?php echo $recipient['recipient_id']; ?> | 
                                            Organ: <?php echo $recipient['organ_needed']; ?> | 
                                            Blood: <?php echo $recipient['blood_group']; ?>
                                            <br>Priority: <?php echo $recipient['priority_score']; ?>/100 | 
                                            Added: <?php echo date('d-M-Y', strtotime($recipient['date_added_to_list'])); ?>
                                            <?php 
                                            $days_waiting = (time() - strtotime($recipient['date_added_to_list'])) / (60 * 60 * 24);
                                            echo ' (' . floor($days_waiting) . ' days waiting)';
                                            ?>
                                            <br><?php echo htmlspecialchars(substr($recipient['medical_condition'], 0, 80)) . '...'; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Matches Tab -->
                <div id="matches" class="tab-content">
                    <h2>Organ Matching System</h2>
                    
                    <div class="form-grid">
                        <!-- Create Match -->
                        <div class="form-section">
                            <h3><i class="fas fa-handshake"></i> Create Organ Match</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="create_organ_match">
                                <div class="form-group">
                                    <label>Donor</label>
                                    <select name="donor_id" required>
                                        <option value="">Select Donor</option>
                                        <?php foreach ($active_donors as $donor): ?>
                                            <option value="<?php echo $donor['id']; ?>">
                                                <?php echo htmlspecialchars($donor['donor_id'] . ' - ' . $donor['first_name'] . ' ' . $donor['last_name'] . ' (' . $donor['blood_group'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Recipient</label>
                                    <select name="recipient_id" required>
                                        <option value="">Select Recipient</option>
                                        <?php foreach ($active_recipients as $recipient): ?>
                                            <option value="<?php echo $recipient['id']; ?>">
                                                <?php echo htmlspecialchars($recipient['recipient_id'] . ' - ' . $recipient['patient_first_name'] . ' ' . $recipient['patient_last_name'] . ' (' . $recipient['blood_group'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Organ Type</label>
                                    <select name="organ_type" required>
                                        <option value="">Select Organ</option>
                                        <?php foreach ($organ_types as $organ): ?>
                                            <option value="<?php echo $organ['organ_name']; ?>"><?php echo htmlspecialchars($organ['organ_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Compatibility Score (%)</label>
                                    <input type="number" name="compatibility_score" min="0" max="100" step="0.1" required placeholder="85.5">
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" rows="3" placeholder="Additional notes about compatibility"></textarea>
                                </div>
                                <button type="submit" class="btn">Create Match</button>
                            </form>
                        </div>
                        
                        <!-- Pending Matches -->
                        <div class="form-section">
                            <h3><i class="fas fa-clock"></i> Pending Matches</h3>
                            <div style="max-height: 600px; overflow-y: auto;">
                                <?php foreach ($pending_matches as $match): ?>
                                    <div style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <strong><?php echo $match['organ_type']; ?> Match</strong>
                                            <div class="compatibility-score <?php 
                                                if ($match['compatibility_score'] >= 80) echo 'score-excellent';
                                                elseif ($match['compatibility_score'] >= 60) echo 'score-good';
                                                else echo 'score-fair';
                                            ?>">
                                                <?php echo $match['compatibility_score']; ?>%
                                            </div>
                                        </div>
                                        <small>
                                            <strong>Donor:</strong> <?php echo htmlspecialchars($match['donor_first_name'] . ' ' . $match['donor_last_name']); ?>
                                            <br><strong>Recipient:</strong> <?php echo htmlspecialchars($match['recipient_first_name'] . ' ' . $match['recipient_last_name']); ?> (<?php echo $match['recipient_id']; ?>)
                                            <br><strong>Match Date:</strong> <?php echo date('d-M-Y H:i', strtotime($match['match_date'])); ?>
                                            <?php if ($match['notes']): ?>
                                                <br><strong>Notes:</strong> <?php echo htmlspecialchars($match['notes']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Transplants Tab -->
                <div id="transplants" class="tab-content">
                    <h2>Transplant Management</h2>
                    
                    <div class="form-grid">
                        <!-- Schedule Transplant -->
                        <div class="form-section">
                            <h3><i class="fas fa-calendar-plus"></i> Schedule Transplant</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="schedule_transplant">
                                <div class="form-group">
                                    <label>Match (Optional)</label>
                                    <select name="match_id">
                                        <option value="">Select from Pending Matches</option>
                                        <?php foreach ($pending_matches as $match): ?>
                                            <option value="<?php echo $match['id']; ?>" 
                                                    data-donor="<?php echo $match['donor_id']; ?>"
                                                    data-recipient="<?php echo $match['recipient_id']; ?>"
                                                    data-organ="<?php echo $match['organ_type']; ?>">
                                                <?php echo $match['organ_type'] . ' - ' . htmlspecialchars($match['donor_first_name'] . ' → ' . $match['recipient_first_name']) . ' (' . $match['compatibility_score'] . '%)'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Donor</label>
                                    <select name="donor_id" required>
                                        <option value="">Select Donor</option>
                                        <?php foreach ($active_donors as $donor): ?>
                                            <option value="<?php echo $donor['id']; ?>">
                                                <?php echo htmlspecialchars($donor['donor_id'] . ' - ' . $donor['first_name'] . ' ' . $donor['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Recipient</label>
                                    <select name="recipient_id" required>
                                        <option value="">Select Recipient</option>
                                        <?php foreach ($active_recipients as $recipient): ?>
                                            <option value="<?php echo $recipient['id']; ?>">
                                                <?php echo htmlspecialchars($recipient['recipient_id'] . ' - ' . $recipient['patient_first_name'] . ' ' . $recipient['patient_last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Organ Type</label>
                                    <select name="organ_type" required>
                                        <option value="">Select Organ</option>
                                        <?php foreach ($organ_types as $organ): ?>
                                            <option value="<?php echo $organ['organ_name']; ?>"><?php echo htmlspecialchars($organ['organ_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Surgery Date & Time</label>
                                    <input type="datetime-local" name="surgery_date" required>
                                </div>
                                <div class="form-group">
                                    <label>Surgeon</label>
                                    <select name="surgeon_id" required>
                                        <option value="">Select Surgeon</option>
                                        <?php foreach ($surgeons as $surgeon): ?>
                                            <option value="<?php echo $surgeon['id']; ?>"><?php echo htmlspecialchars($surgeon['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" rows="3" placeholder="Pre-operative notes and instructions"></textarea>
                                </div>
                                <button type="submit" class="btn btn-success">Schedule Transplant</button>
                            </form>
                        </div>
                        
                        <!-- Scheduled Transplants -->
                        <div class="form-section">
                            <h3><i class="fas fa-procedures"></i> Scheduled Transplants</h3>
                            <div style="max-height: 600px; overflow-y: auto;">
                                <?php foreach ($scheduled_transplants as $transplant): ?>
                                    <div style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <strong><?php echo $transplant['organ_type']; ?> Transplant</strong>
                                            <span class="badge badge-primary"><?php echo $transplant['transplant_id']; ?></span>
                                        </div>
                                        <small>
                                            <strong>Surgery Date:</strong> <?php echo date('d-M-Y H:i', strtotime($transplant['surgery_date'])); ?>
                                            <?php 
                                            $days_until = (strtotime($transplant['surgery_date']) - time()) / (60 * 60 * 24);
                                            if ($days_until > 0) {
                                                echo ' <span class="countdown ';
                                                if ($days_until <= 1) echo 'countdown-critical';
                                                elseif ($days_until <= 3) echo 'countdown-warning';
                                                else echo 'countdown-normal';
                                                echo '">' . ceil($days_until) . ' days</span>';
                                            }
                                            ?>
                                            <br><strong>Donor:</strong> <?php echo htmlspecialchars($transplant['donor_first_name'] . ' ' . $transplant['donor_last_name']); ?>
                                            <br><strong>Recipient:</strong> <?php echo htmlspecialchars($transplant['recipient_first_name'] . ' ' . $transplant['recipient_last_name']); ?> (<?php echo $transplant['recipient_id']; ?>)
                                            <br><strong>Surgeon:</strong> <?php echo htmlspecialchars($transplant['surgeon_first_name'] . ' ' . $transplant['surgeon_last_name']); ?>
                                            <?php if ($transplant['notes']): ?>
                                                <br><strong>Notes:</strong> <?php echo htmlspecialchars($transplant['notes']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        // Auto-fill transplant form when match is selected
        document.addEventListener('DOMContentLoaded', function() {
            const matchSelect = document.querySelector('select[name="match_id"]');
            const donorSelect = document.querySelector('select[name="donor_id"]');
            const recipientSelect = document.querySelector('select[name="recipient_id"]');
            const organSelect = document.querySelector('select[name="organ_type"]');
            
            if (matchSelect && donorSelect && recipientSelect && organSelect) {
                matchSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        donorSelect.value = selectedOption.dataset.donor || '';
                        recipientSelect.value = selectedOption.dataset.recipient || '';
                        organSelect.value = selectedOption.dataset.organ || '';
                    }
                });
            }
        });
    </script>
</body>
</html>
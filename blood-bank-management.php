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
            case 'add_donor':
                $donor_sql = "INSERT INTO blood_donors (donor_id, first_name, last_name, email, phone, blood_group, date_of_birth, gender, address, emergency_contact, medical_history, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $db->query($donor_sql, [
                    $_POST['donor_id'], $_POST['first_name'], $_POST['last_name'], $_POST['email'],
                    $_POST['phone'], $_POST['blood_group'], $_POST['date_of_birth'], $_POST['gender'],
                    $_POST['address'], $_POST['emergency_contact'], $_POST['medical_history'], $_SESSION['user_id']
                ]);
                $message = "Blood donor registered successfully!";
                break;
                
            case 'add_blood_inventory':
                $inventory_sql = "INSERT INTO blood_inventory (blood_group, component_type, bag_number, donor_id, collection_date, expiry_date, volume_ml, storage_location, temperature, tested, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $db->query($inventory_sql, [
                    $_POST['blood_group'], $_POST['component_type'], $_POST['bag_number'], $_POST['donor_id'],
                    $_POST['collection_date'], $_POST['expiry_date'], $_POST['volume_ml'], $_POST['storage_location'],
                    $_POST['temperature'], isset($_POST['tested']) ? 1 : 0, $_POST['notes']
                ]);
                $message = "Blood inventory added successfully!";
                break;
                
            case 'create_blood_request':
                $request_number = 'BR' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $request_sql = "INSERT INTO blood_requests (request_number, patient_id, doctor_id, blood_group, component_type, units_required, urgency, clinical_indication, required_by_date, cross_match_required, special_requirements, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $db->query($request_sql, [
                    $request_number, $_POST['patient_id'], $_POST['doctor_id'], $_POST['blood_group'],
                    $_POST['component_type'], $_POST['units_required'], $_POST['urgency'], $_POST['clinical_indication'],
                    $_POST['required_by_date'], isset($_POST['cross_match_required']) ? 1 : 0, $_POST['special_requirements'], $_POST['notes']
                ]);
                $message = "Blood request created successfully! Request Number: $request_number";
                break;
                
            case 'issue_blood':
                $bag_id = $_POST['bag_id'];
                $patient_id = $_POST['patient_id'];
                
                // Update inventory status
                $update_sql = "UPDATE blood_inventory SET status = 'used', issued_to_patient_id = ?, issued_date = NOW(), issued_by = ? WHERE id = ?";
                $db->query($update_sql, [$patient_id, $_SESSION['user_id'], $bag_id]);
                
                // Update request status if provided
                if (!empty($_POST['request_id'])) {
                    $db->query("UPDATE blood_requests SET status = 'fulfilled', fulfilled_by = ?, fulfilled_date = NOW() WHERE id = ?", 
                              [$_SESSION['user_id'], $_POST['request_id']]);
                }
                
                $message = "Blood issued successfully!";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get statistics
$stats = [
    'total_donors' => $db->query("SELECT COUNT(*) as count FROM blood_donors WHERE is_active = 1")->fetch()['count'],
    'total_inventory' => $db->query("SELECT COUNT(*) as count FROM blood_inventory WHERE status = 'available'")->fetch()['count'],
    'pending_requests' => $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'pending'")->fetch()['count'],
    'blood_groups' => $db->query("SELECT blood_group, COUNT(*) as count FROM blood_inventory WHERE status = 'available' GROUP BY blood_group")->fetchAll()
];

// Get recent data
$recent_donors = $db->query("SELECT * FROM blood_donors WHERE is_active = 1 ORDER BY registered_date DESC LIMIT 10")->fetchAll();
$recent_inventory = $db->query("SELECT bi.*, bd.first_name, bd.last_name FROM blood_inventory bi LEFT JOIN blood_donors bd ON bi.donor_id = bd.id ORDER BY bi.created_at DESC LIMIT 10")->fetchAll();
$pending_requests = $db->query("
    SELECT br.*, p.first_name as patient_name, p.last_name as patient_lastname, 
           s.first_name as doctor_name, s.last_name as doctor_lastname
    FROM blood_requests br
    JOIN patients p ON br.patient_id = p.id
    JOIN staff s ON br.doctor_id = s.id
    WHERE br.status = 'pending'
    ORDER BY br.requested_date DESC
")->fetchAll();

// Get blood groups and patients for dropdowns
$blood_groups = $db->query("SELECT * FROM blood_groups WHERE is_active = 1")->fetchAll();
$patients = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM patients WHERE is_active = 1 ORDER BY first_name")->fetchAll();
$doctors = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM staff WHERE staff_type IN ('doctor') AND is_active = 1 ORDER BY first_name")->fetchAll();
$available_inventory = $db->query("SELECT * FROM blood_inventory WHERE status = 'available' ORDER BY blood_group, expiry_date")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    include_once 'includes/site-config.php';
    renderSiteHead('Blood Bank Management');
    ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
    <style>
        .blood-bank {
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
        
        .blood-group-stats {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }
        
        .blood-group-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .blood-group-item {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #dc2626;
        }
        
        .blood-type {
            font-size: 1.5rem;
            font-weight: bold;
            color: #dc2626;
        }
        
        .blood-count {
            color: var(--text-secondary);
            font-size: 0.9rem;
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
        .btn-danger { background: #dc2626; }
        
        .table-container {
            background: var(--bg-card);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-top: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }
        
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
        
        .urgency-emergency { color: #dc2626; font-weight: bold; }
        .urgency-urgent { color: #f59e0b; font-weight: bold; }
        .urgency-routine { color: var(--text-secondary); }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="blood-bank">
                <div class="page-header">
                    <h1><i class="fas fa-tint"></i> Blood Bank Management</h1>
                    <p>Comprehensive blood donation and inventory management system</p>
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
                        <div class="stat-number"><?php echo $stats['total_inventory']; ?></div>
                        <div class="stat-label">Available Blood Units</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                </div>
                
                <!-- Blood Group Inventory -->
                <div class="blood-group-stats">
                    <h3><i class="fas fa-chart-bar"></i> Blood Group Inventory</h3>
                    <div class="blood-group-grid">
                        <?php foreach ($stats['blood_groups'] as $group): ?>
                            <div class="blood-group-item">
                                <div class="blood-type"><?php echo htmlspecialchars($group['blood_group']); ?></div>
                                <div class="blood-count"><?php echo $group['count']; ?> units</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Management Tabs -->
                <div class="management-tabs">
                    <div class="tab-btn active" onclick="showTab('donors')">
                        <i class="fas fa-users"></i> Donors
                    </div>
                    <div class="tab-btn" onclick="showTab('inventory')">
                        <i class="fas fa-warehouse"></i> Inventory
                    </div>
                    <div class="tab-btn" onclick="showTab('requests')">
                        <i class="fas fa-file-medical"></i> Requests
                    </div>
                    <div class="tab-btn" onclick="showTab('issue')">
                        <i class="fas fa-hand-holding-medical"></i> Issue Blood
                    </div>
                </div>
                
                <!-- Donors Tab -->
                <div id="donors" class="tab-content active">
                    <h2>Blood Donor Management</h2>
                    
                    <div class="form-grid">
                        <!-- Add New Donor -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-plus"></i> Register New Donor</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_donor">
                                <div class="form-group">
                                    <label>Donor ID</label>
                                    <input type="text" name="donor_id" required placeholder="BD001" value="BD<?php echo str_pad($stats['total_donors'] + 1, 3, '0', STR_PAD_LEFT); ?>">
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
                                    <label>Medical History</label>
                                    <textarea name="medical_history" rows="3" placeholder="Any relevant medical conditions"></textarea>
                                </div>
                                <button type="submit" class="btn">Register Donor</button>
                            </form>
                        </div>
                        
                        <!-- Recent Donors -->
                        <div class="form-section">
                            <h3><i class="fas fa-list"></i> Recent Donors</h3>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php foreach ($recent_donors as $donor): ?>
                                    <div style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                                        <strong><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></strong>
                                        <br><small>
                                            ID: <?php echo $donor['donor_id']; ?> | 
                                            Blood: <?php echo $donor['blood_group']; ?> | 
                                            Phone: <?php echo $donor['phone']; ?>
                                            <br>Donations: <?php echo $donor['total_donations']; ?> | 
                                            Last: <?php echo $donor['last_donation_date'] ? date('d-M-Y', strtotime($donor['last_donation_date'])) : 'Never'; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Inventory Tab -->
                <div id="inventory" class="tab-content">
                    <h2>Blood Inventory Management</h2>
                    
                    <div class="form-grid">
                        <!-- Add Blood Inventory -->
                        <div class="form-section">
                            <h3><i class="fas fa-plus-circle"></i> Add Blood Unit</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_blood_inventory">
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
                                    <label>Component Type</label>
                                    <select name="component_type" required>
                                        <option value="whole_blood">Whole Blood</option>
                                        <option value="red_cells">Red Blood Cells</option>
                                        <option value="platelets">Platelets</option>
                                        <option value="plasma">Plasma</option>
                                        <option value="white_cells">White Blood Cells</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Bag Number</label>
                                    <input type="text" name="bag_number" required placeholder="BB<?php echo str_pad($stats['total_inventory'] + 1, 3, '0', STR_PAD_LEFT); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Donor (Optional)</label>
                                    <select name="donor_id">
                                        <option value="">Select Donor</option>
                                        <?php foreach ($recent_donors as $donor): ?>
                                            <option value="<?php echo $donor['id']; ?>"><?php echo htmlspecialchars($donor['donor_id'] . ' - ' . $donor['first_name'] . ' ' . $donor['last_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Collection Date</label>
                                    <input type="date" name="collection_date" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Expiry Date</label>
                                    <input type="date" name="expiry_date" required value="<?php echo date('Y-m-d', strtotime('+35 days')); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Volume (ml)</label>
                                    <input type="number" name="volume_ml" required value="450" min="100" max="500">
                                </div>
                                <div class="form-group">
                                    <label>Storage Location</label>
                                    <input type="text" name="storage_location" placeholder="Refrigerator-A1">
                                </div>
                                <div class="form-group">
                                    <label>Temperature (°C)</label>
                                    <input type="number" name="temperature" step="0.1" value="4.0" min="0" max="10">
                                </div>
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="tested" checked> Tested for pathogens
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn">Add to Inventory</button>
                            </form>
                        </div>
                        
                        <!-- Recent Inventory -->
                        <div class="form-section">
                            <h3><i class="fas fa-warehouse"></i> Recent Inventory</h3>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php foreach ($recent_inventory as $item): ?>
                                    <div style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                                        <strong><?php echo $item['bag_number']; ?> - <?php echo $item['blood_group']; ?></strong>
                                        <span class="badge badge-<?php echo $item['status'] === 'available' ? 'success' : ($item['status'] === 'used' ? 'info' : 'warning'); ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                        <br><small>
                                            Type: <?php echo ucfirst(str_replace('_', ' ', $item['component_type'])); ?> | 
                                            Volume: <?php echo $item['volume_ml']; ?>ml |
                                            Expiry: <?php echo date('d-M-Y', strtotime($item['expiry_date'])); ?>
                                            <?php if ($item['first_name']): ?>
                                                <br>Donor: <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Requests Tab -->
                <div id="requests" class="tab-content">
                    <h2>Blood Request Management</h2>
                    
                    <div class="form-grid">
                        <!-- Create New Request -->
                        <div class="form-section">
                            <h3><i class="fas fa-file-medical"></i> Create Blood Request</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="create_blood_request">
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
                                    <label>Requesting Doctor</label>
                                    <select name="doctor_id" required>
                                        <option value="">Select Doctor</option>
                                        <?php foreach ($doctors as $doctor): ?>
                                            <option value="<?php echo $doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?></option>
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
                                    <label>Component Type</label>
                                    <select name="component_type" required>
                                        <option value="whole_blood">Whole Blood</option>
                                        <option value="red_cells">Red Blood Cells</option>
                                        <option value="platelets">Platelets</option>
                                        <option value="plasma">Plasma</option>
                                        <option value="white_cells">White Blood Cells</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Units Required</label>
                                    <input type="number" name="units_required" required min="1" value="1">
                                </div>
                                <div class="form-group">
                                    <label>Urgency</label>
                                    <select name="urgency" required>
                                        <option value="routine">Routine</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Clinical Indication</label>
                                    <textarea name="clinical_indication" required rows="3" placeholder="Reason for blood requirement"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Required By Date</label>
                                    <input type="datetime-local" name="required_by_date" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                                </div>
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="cross_match_required" checked> Cross-match required
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>Special Requirements</label>
                                    <textarea name="special_requirements" rows="2" placeholder="Any special requirements"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Notes</label>
                                    <textarea name="notes" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn">Create Request</button>
                            </form>
                        </div>
                        
                        <!-- Pending Requests -->
                        <div class="form-section">
                            <h3><i class="fas fa-clock"></i> Pending Requests</h3>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php foreach ($pending_requests as $request): ?>
                                    <div style="padding: 10px; border-bottom: 1px solid var(--border-color);">
                                        <strong><?php echo $request['request_number']; ?></strong>
                                        <span class="badge badge-warning">Pending</span>
                                        <br>
                                        <strong>Patient:</strong> <?php echo htmlspecialchars($request['patient_name'] . ' ' . $request['patient_lastname']); ?>
                                        <br><strong>Doctor:</strong> <?php echo htmlspecialchars($request['doctor_name'] . ' ' . $request['doctor_lastname']); ?>
                                        <br><strong>Requirement:</strong> <?php echo $request['units_required']; ?> units of <?php echo $request['blood_group']; ?> (<?php echo ucfirst(str_replace('_', ' ', $request['component_type'])); ?>)
                                        <br><strong>Urgency:</strong> <span class="urgency-<?php echo $request['urgency']; ?>"><?php echo ucfirst($request['urgency']); ?></span>
                                        <br><strong>Required by:</strong> <?php echo date('d-M-Y H:i', strtotime($request['required_by_date'])); ?>
                                        <br><small><?php echo htmlspecialchars($request['clinical_indication']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Issue Blood Tab -->
                <div id="issue" class="tab-content">
                    <h2>Issue Blood to Patient</h2>
                    
                    <div class="form-grid">
                        <!-- Issue Blood Form -->
                        <div class="form-section">
                            <h3><i class="fas fa-hand-holding-medical"></i> Issue Blood Unit</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="issue_blood">
                                <div class="form-group">
                                    <label>Blood Bag</label>
                                    <select name="bag_id" required>
                                        <option value="">Select Blood Bag</option>
                                        <?php foreach ($available_inventory as $bag): ?>
                                            <option value="<?php echo $bag['id']; ?>">
                                                <?php echo $bag['bag_number']; ?> - <?php echo $bag['blood_group']; ?> 
                                                (<?php echo ucfirst(str_replace('_', ' ', $bag['component_type'])); ?>, 
                                                <?php echo $bag['volume_ml']; ?>ml, 
                                                Exp: <?php echo date('d-M-Y', strtotime($bag['expiry_date'])); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                                    <label>Related Request (Optional)</label>
                                    <select name="request_id">
                                        <option value="">Select Request</option>
                                        <?php foreach ($pending_requests as $request): ?>
                                            <option value="<?php echo $request['id']; ?>">
                                                <?php echo $request['request_number']; ?> - 
                                                <?php echo htmlspecialchars($request['patient_name'] . ' ' . $request['patient_lastname']); ?>
                                                (<?php echo $request['blood_group']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success">Issue Blood</button>
                            </form>
                        </div>
                        
                        <!-- Available Inventory Quick View -->
                        <div class="form-section">
                            <h3><i class="fas fa-list-ul"></i> Available Blood Units</h3>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php 
                                $grouped_inventory = [];
                                foreach ($available_inventory as $item) {
                                    $grouped_inventory[$item['blood_group']][] = $item;
                                }
                                ?>
                                <?php foreach ($grouped_inventory as $group => $items): ?>
                                    <h4 style="color: #dc2626; margin: 15px 0 5px 0;"><?php echo $group; ?> (<?php echo count($items); ?> units)</h4>
                                    <?php foreach ($items as $item): ?>
                                        <div style="padding: 8px; border-bottom: 1px solid var(--border-color); margin-left: 15px;">
                                            <strong><?php echo $item['bag_number']; ?></strong> - 
                                            <?php echo ucfirst(str_replace('_', ' ', $item['component_type'])); ?>
                                            <br><small>
                                                Volume: <?php echo $item['volume_ml']; ?>ml | 
                                                Collected: <?php echo date('d-M-Y', strtotime($item['collection_date'])); ?> | 
                                                Expires: <?php echo date('d-M-Y', strtotime($item['expiry_date'])); ?>
                                                <?php if ($item['storage_location']): ?>
                                                    <br>Location: <?php echo htmlspecialchars($item['storage_location']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
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
        
        // Auto-suggest bag for blood group selection
        document.addEventListener('DOMContentLoaded', function() {
            const bloodGroupSelect = document.querySelector('select[name="blood_group"]');
            const bagSelect = document.querySelector('select[name="bag_id"]');
            
            if (bloodGroupSelect && bagSelect) {
                bloodGroupSelect.addEventListener('change', function() {
                    const selectedGroup = this.value;
                    const bagOptions = bagSelect.querySelectorAll('option');
                    
                    bagOptions.forEach(option => {
                        if (option.value === '') return;
                        
                        if (selectedGroup && option.textContent.includes(selectedGroup)) {
                            option.style.display = 'block';
                        } else if (selectedGroup) {
                            option.style.display = 'none';
                        } else {
                            option.style.display = 'block';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
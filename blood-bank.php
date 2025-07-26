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
            case 'add_donor':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $stmt = $db->prepare("INSERT INTO blood_donors (donor_id, first_name, last_name, email, phone, blood_group, date_of_birth, gender, address, emergency_contact, emergency_phone, medical_history, last_donation_date, next_eligible_date, status, registered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
                    
                    $donor_id = 'BD' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $next_eligible = date('Y-m-d', strtotime('+56 days'));
                    
                    $stmt->execute([
                        $donor_id,
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'],
                        $_POST['blood_group'],
                        $_POST['date_of_birth'],
                        $_POST['gender'],
                        $_POST['address'],
                        $_POST['emergency_contact'],
                        $_POST['emergency_phone'],
                        $_POST['medical_history'],
                        $_POST['last_donation_date'] ?: null,
                        $_POST['last_donation_date'] ? date('Y-m-d', strtotime($_POST['last_donation_date'] . ' +56 days')) : $next_eligible,
                        $user_id
                    ]);
                    $success = "Donor registered successfully!";
                }
                break;
                
            case 'add_donation':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $stmt = $db->prepare("INSERT INTO blood_donations (donation_id, donor_id, blood_group, units_collected, donation_date, collection_center, staff_id, hemoglobin_level, blood_pressure, temperature, weight, medical_clearance, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'cleared', ?, 'collected')");
                    
                    $donation_id = 'DON' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $donation_id,
                        $_POST['donor_id'],
                        $_POST['blood_group'],
                        $_POST['units_collected'],
                        $_POST['donation_date'],
                        $_POST['collection_center'],
                        $user_id,
                        $_POST['hemoglobin_level'],
                        $_POST['blood_pressure'],
                        $_POST['temperature'],
                        $_POST['weight'],
                        $_POST['notes']
                    ]);
                    
                    // Update blood inventory
                    $stmt = $db->prepare("INSERT INTO blood_inventory (blood_group, units_available, last_updated, updated_by) VALUES (?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE units_available = units_available + ?, last_updated = NOW(), updated_by = ?");
                    $stmt->execute([$_POST['blood_group'], $_POST['units_collected'], $user_id, $_POST['units_collected'], $user_id]);
                    
                    // Update donor's last donation date
                    $next_eligible = date('Y-m-d', strtotime($_POST['donation_date'] . ' +56 days'));
                    $stmt = $db->prepare("UPDATE blood_donors SET last_donation_date = ?, next_eligible_date = ? WHERE id = ?");
                    $stmt->execute([$_POST['donation_date'], $next_eligible, $_POST['donor_id']]);
                    
                    $success = "Blood donation recorded successfully!";
                }
                break;
                
            case 'blood_request':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $stmt = $db->prepare("INSERT INTO blood_requests (request_id, patient_id, blood_group, units_requested, urgency_level, required_date, requesting_doctor, department, medical_reason, status, requested_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                    
                    $request_id = 'REQ' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $request_id,
                        $_POST['patient_id'],
                        $_POST['blood_group'],
                        $_POST['units_requested'],
                        $_POST['urgency_level'],
                        $_POST['required_date'],
                        $_POST['requesting_doctor'],
                        $_POST['department'],
                        $_POST['medical_reason'],
                        $user_id
                    ]);
                    $success = "Blood request submitted successfully!";
                }
                break;
        }
    }
}

// Get statistics
$stats = [];
$stats['total_donors'] = $db->query("SELECT COUNT(*) FROM blood_donors WHERE status = 'active'")->fetchColumn();
$stats['total_donations'] = $db->query("SELECT COUNT(*) FROM blood_donations WHERE status = 'collected'")->fetchColumn();
$stats['pending_requests'] = $db->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'pending'")->fetchColumn();
$stats['total_units'] = $db->query("SELECT SUM(units_available) FROM blood_inventory")->fetchColumn() ?: 0;

// Get blood inventory
$inventory = $db->query("SELECT * FROM blood_inventory ORDER BY blood_group")->fetchAll();

// Get recent donations
$recent_donations = $db->query("
    SELECT bd.*, CONCAT(bdr.first_name, ' ', bdr.last_name) as donor_name, bdr.donor_id as donor_code
    FROM blood_donations bd 
    JOIN blood_donors bdr ON bd.donor_id = bdr.id 
    ORDER BY bd.donation_date DESC 
    LIMIT 10
")->fetchAll();

// Get blood requests
$blood_requests = $db->query("
    SELECT br.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, p.patient_id as patient_code,
           CONCAT(d.first_name, ' ', d.last_name) as doctor_name
    FROM blood_requests br 
    JOIN patients p ON br.patient_id = p.id 
    LEFT JOIN doctors d ON br.requesting_doctor = d.id 
    ORDER BY br.required_date ASC
")->fetchAll();

// Get donors
$donors = $db->query("
    SELECT * FROM blood_donors 
    WHERE status = 'active' 
    ORDER BY next_eligible_date ASC
")->fetchAll();
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
        
        .stat-icon.donors { background: linear-gradient(135deg, #ff6b6b, #ee5a52); }
        .stat-icon.donations { background: linear-gradient(135deg, #4ecdc4, #44a08d); }
        .stat-icon.requests { background: linear-gradient(135deg, #45b7d1, #96c93d); }
        .stat-icon.units { background: linear-gradient(135deg, #f093fb, #f5576c); }
        
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
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.completed { background: #d1ecf1; color: #0c5460; }
        .badge.urgent { background: #f8d7da; color: #721c24; }
        .badge.normal { background: #e2e3e5; color: #383d41; }
        
        .blood-type {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 12px;
        }
        
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .inventory-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #dc3545;
        }
        
        .inventory-card h3 {
            color: #dc3545;
            margin-bottom: 0.5rem;
        }
        
        .units {
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
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-tint"></i> Blood Bank Management</h1>
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
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h3><?php echo number_format($stats['total_donors']); ?></h3>
                    <p>Active Donors</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon donations">
                    <i class="fas fa-hand-holding-heart"></i>
                </div>
                <div>
                    <h3><?php echo number_format($stats['total_donations']); ?></h3>
                    <p>Total Donations</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon requests">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div>
                    <h3><?php echo number_format($stats['pending_requests']); ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon units">
                    <i class="fas fa-flask"></i>
                </div>
                <div>
                    <h3><?php echo number_format($stats['total_units']); ?></h3>
                    <p>Units Available</p>
                </div>
            </div>
        </div>

        <!-- Blood Inventory -->
        <div class="tab-content active">
            <h2><i class="fas fa-warehouse"></i> Blood Inventory</h2>
            <div class="inventory-grid">
                <?php 
                $blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                foreach ($blood_groups as $group): 
                    $units = 0;
                    foreach ($inventory as $inv) {
                        if ($inv['blood_group'] === $group) {
                            $units = $inv['units_available'];
                            break;
                        }
                    }
                ?>
                <div class="inventory-card">
                    <h3><?php echo $group; ?></h3>
                    <div class="units"><?php echo $units; ?></div>
                    <small>Units Available</small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
            <button class="tab active" onclick="showTab('donors')">
                <i class="fas fa-users"></i> Donor Management
            </button>
            <button class="tab" onclick="showTab('donations')">
                <i class="fas fa-hand-holding-heart"></i> Record Donation
            </button>
            <button class="tab" onclick="showTab('requests')">
                <i class="fas fa-clipboard-list"></i> Blood Requests
            </button>
            <?php endif; ?>
            <?php if ($user_role === 'patient'): ?>
            <button class="tab active" onclick="showTab('my-donations')">
                <i class="fas fa-heart"></i> My Donations
            </button>
            <button class="tab" onclick="showTab('donation-history')">
                <i class="fas fa-history"></i> Donation History
            </button>
            <?php endif; ?>
        </div>

        <!-- Donor Management Tab -->
        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
        <div id="donors" class="tab-content active">
            <h2><i class="fas fa-user-plus"></i> Register New Donor</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_donor">
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
                        <label>Last Donation Date</label>
                        <input type="date" name="last_donation_date">
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
                </div>
                <div class="form-group">
                    <label>Medical History</label>
                    <textarea name="medical_history" rows="3"></textarea>
                </div>
                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i> Register Donor
                </button>
            </form>

            <h3 style="margin-top: 2rem;"><i class="fas fa-list"></i> Registered Donors</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Donor ID</th>
                            <th>Name</th>
                            <th>Blood Group</th>
                            <th>Phone</th>
                            <th>Last Donation</th>
                            <th>Next Eligible</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donors as $donor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($donor['donor_id']); ?></td>
                            <td><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></td>
                            <td><span class="blood-type"><?php echo htmlspecialchars($donor['blood_group']); ?></span></td>
                            <td><?php echo htmlspecialchars($donor['phone']); ?></td>
                            <td><?php echo $donor['last_donation_date'] ? date('M d, Y', strtotime($donor['last_donation_date'])) : 'Never'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($donor['next_eligible_date'])); ?></td>
                            <td><span class="badge <?php echo $donor['status']; ?>"><?php echo ucfirst($donor['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Record Donation Tab -->
        <div id="donations" class="tab-content">
            <h2><i class="fas fa-plus-circle"></i> Record Blood Donation</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_donation">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Select Donor *</label>
                        <select name="donor_id" required>
                            <option value="">Select Donor</option>
                            <?php foreach ($donors as $donor): ?>
                            <option value="<?php echo $donor['id']; ?>">
                                <?php echo htmlspecialchars($donor['donor_id'] . ' - ' . $donor['first_name'] . ' ' . $donor['last_name'] . ' (' . $donor['blood_group'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
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
                        <label>Units Collected *</label>
                        <input type="number" name="units_collected" step="0.1" min="0.1" max="2" required>
                    </div>
                    <div class="form-group">
                        <label>Donation Date *</label>
                        <input type="date" name="donation_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Collection Center</label>
                        <input type="text" name="collection_center" value="Main Hospital">
                    </div>
                    <div class="form-group">
                        <label>Hemoglobin Level</label>
                        <input type="number" name="hemoglobin_level" step="0.1" placeholder="g/dL">
                    </div>
                    <div class="form-group">
                        <label>Blood Pressure</label>
                        <input type="text" name="blood_pressure" placeholder="120/80">
                    </div>
                    <div class="form-group">
                        <label>Temperature</label>
                        <input type="number" name="temperature" step="0.1" placeholder="°F">
                    </div>
                    <div class="form-group">
                        <label>Weight (kg)</label>
                        <input type="number" name="weight" step="0.1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                <button type="submit" class="btn">
                    <i class="fas fa-plus-circle"></i> Record Donation
                </button>
            </form>

            <h3 style="margin-top: 2rem;"><i class="fas fa-history"></i> Recent Donations</h3>
            <div class="table-container">
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
                        <?php foreach ($recent_donations as $donation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($donation['donation_id']); ?></td>
                            <td><?php echo htmlspecialchars($donation['donor_name']); ?><br>
                                <small><?php echo htmlspecialchars($donation['donor_code']); ?></small></td>
                            <td><span class="blood-type"><?php echo htmlspecialchars($donation['blood_group']); ?></span></td>
                            <td><?php echo $donation['units_collected']; ?> units</td>
                            <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                            <td><span class="badge <?php echo $donation['status']; ?>"><?php echo ucfirst($donation['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Blood Requests Tab -->
        <div id="requests" class="tab-content">
            <h2><i class="fas fa-plus-circle"></i> Create Blood Request</h2>
            <form method="POST">
                <input type="hidden" name="action" value="blood_request">
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
                        <label>Units Requested *</label>
                        <input type="number" name="units_requested" step="0.1" min="0.1" required>
                    </div>
                    <div class="form-group">
                        <label>Urgency Level *</label>
                        <select name="urgency_level" required>
                            <option value="">Select Urgency</option>
                            <option value="urgent">Urgent</option>
                            <option value="normal">Normal</option>
                            <option value="routine">Routine</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Required Date *</label>
                        <input type="date" name="required_date" required>
                    </div>
                    <div class="form-group">
                        <label>Requesting Doctor</label>
                        <select name="requesting_doctor">
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
                        <label>Department</label>
                        <input type="text" name="department">
                    </div>
                </div>
                <div class="form-group">
                    <label>Medical Reason *</label>
                    <textarea name="medical_reason" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn">
                    <i class="fas fa-plus-circle"></i> Submit Request
                </button>
            </form>

            <h3 style="margin-top: 2rem;"><i class="fas fa-list"></i> Blood Requests</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Patient</th>
                            <th>Blood Group</th>
                            <th>Units</th>
                            <th>Urgency</th>
                            <th>Required Date</th>
                            <th>Doctor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blood_requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                            <td><?php echo htmlspecialchars($request['patient_name']); ?><br>
                                <small><?php echo htmlspecialchars($request['patient_code']); ?></small></td>
                            <td><span class="blood-type"><?php echo htmlspecialchars($request['blood_group']); ?></span></td>
                            <td><?php echo $request['units_requested']; ?> units</td>
                            <td><span class="badge <?php echo $request['urgency_level']; ?>"><?php echo ucfirst($request['urgency_level']); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($request['required_date'])); ?></td>
                            <td><?php echo htmlspecialchars($request['doctor_name'] ?: 'Not assigned'); ?></td>
                            <td><span class="badge <?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Patient Dashboard -->
        <?php if ($user_role === 'patient'): ?>
        <div id="my-donations" class="tab-content active">
            <h2><i class="fas fa-heart"></i> My Blood Donation Dashboard</h2>
            <?php
            // Get patient's donor record
            $patient_donor = $db->prepare("SELECT * FROM blood_donors WHERE email = ? OR phone = ?");
            $patient_info = $db->prepare("SELECT email, phone FROM patients WHERE id = ?");
            $patient_info->execute([$_SESSION['patient_id'] ?? 0]);
            $patient_data = $patient_info->fetch();
            
            if ($patient_data) {
                $patient_donor->execute([$patient_data['email'], $patient_data['phone']]);
                $donor_record = $patient_donor->fetch();
            }
            ?>
            
            <?php if ($donor_record): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon donations">
                            <i class="fas fa-tint"></i>
                        </div>
                        <div>
                            <h3><?php echo $donor_record['blood_group']; ?></h3>
                            <p>My Blood Group</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon donors">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div>
                            <h3><?php echo $donor_record['last_donation_date'] ? date('M d, Y', strtotime($donor_record['last_donation_date'])) : 'Never'; ?></h3>
                            <p>Last Donation</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon requests">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3><?php echo date('M d, Y', strtotime($donor_record['next_eligible_date'])); ?></h3>
                            <p>Next Eligible Date</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon units">
                            <i class="fas fa-award"></i>
                        </div>
                        <div>
                            <?php 
                            $total_donations = $db->prepare("SELECT COUNT(*) FROM blood_donations WHERE donor_id = ?");
                            $total_donations->execute([$donor_record['id']]);
                            $donation_count = $total_donations->fetchColumn();
                            ?>
                            <h3><?php echo $donation_count; ?></h3>
                            <p>Total Donations</p>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin: 2rem 0;">
                    <?php if (strtotime($donor_record['next_eligible_date']) <= time()): ?>
                        <div class="alert success">
                            <i class="fas fa-check-circle"></i> You are eligible to donate blood! Contact our blood bank to schedule your donation.
                        </div>
                    <?php else: ?>
                        <div class="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
                            <i class="fas fa-info-circle"></i> You will be eligible to donate again on <?php echo date('M d, Y', strtotime($donor_record['next_eligible_date'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert" style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
                    <i class="fas fa-info-circle"></i> You are not registered as a blood donor yet. Contact our blood bank to register and start saving lives!
                </div>
            <?php endif; ?>
        </div>

        <div id="donation-history" class="tab-content">
            <h2><i class="fas fa-history"></i> My Donation History</h2>
            <?php if ($donor_record): ?>
                <?php
                $my_donations = $db->prepare("
                    SELECT * FROM blood_donations 
                    WHERE donor_id = ? 
                    ORDER BY donation_date DESC
                ");
                $my_donations->execute([$donor_record['id']]);
                $donations = $my_donations->fetchAll();
                ?>
                
                <?php if ($donations): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Donation ID</th>
                                <th>Date</th>
                                <th>Units Donated</th>
                                <th>Collection Center</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donations as $donation): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($donation['donation_id']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                <td><?php echo $donation['units_collected']; ?> units</td>
                                <td><?php echo htmlspecialchars($donation['collection_center']); ?></td>
                                <td><span class="badge <?php echo $donation['status']; ?>"><?php echo ucfirst($donation['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; margin: 2rem 0;">No donation history found.</p>
                <?php endif; ?>
            <?php else: ?>
                <p style="text-align: center; color: #666; margin: 2rem 0;">You need to be registered as a donor to view donation history.</p>
            <?php endif; ?>
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
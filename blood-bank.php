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
                    $stmt = $db->prepare("INSERT INTO blood_donors (donor_id, first_name, last_name, email, phone, date_of_birth, gender, blood_type, address, emergency_contact, emergency_phone, last_donation_date, donation_count, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $donor_id = 'BD' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $donation_count = 0;
                    $status = 'active';
                    
                    $stmt->execute([
                        $donor_id, $_POST['first_name'], $_POST['last_name'], $_POST['email'], 
                        $_POST['phone'], $_POST['date_of_birth'], $_POST['gender'], $_POST['blood_type'],
                        $_POST['address'], $_POST['emergency_contact'], $_POST['emergency_phone'],
                        null, $donation_count, $status, $user_id
                    ]);
                    
                    $success_message = "Blood donor registered successfully!";
                }
                break;
                
            case 'add_donation':
                if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])) {
                    $stmt = $db->prepare("INSERT INTO blood_donations (donation_id, donor_id, blood_type, units_collected, donation_date, collection_site, staff_id, hemoglobin_level, blood_pressure, temperature, weight, medical_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $donation_id = 'DON' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $donation_id, $_POST['donor_id'], $_POST['blood_type'], $_POST['units_collected'],
                        $_POST['donation_date'], $_POST['collection_site'], $user_id,
                        $_POST['hemoglobin_level'], $_POST['blood_pressure'], $_POST['temperature'],
                        $_POST['weight'], $_POST['medical_notes'], 'collected'
                    ]);
                    
                    // Update donor's last donation date and count
                    $stmt = $db->prepare("UPDATE blood_donors SET last_donation_date = ?, donation_count = donation_count + 1 WHERE id = ?");
                    $stmt->execute([$_POST['donation_date'], $_POST['donor_id']]);
                    
                    // Update blood inventory
                    $stmt = $db->prepare("INSERT INTO blood_inventory (blood_type, units_available, expiry_date, source_donation_id, status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE units_available = units_available + VALUES(units_available)");
                    $expiry_date = date('Y-m-d', strtotime($_POST['donation_date'] . ' + 42 days'));
                    $stmt->execute([$_POST['blood_type'], $_POST['units_collected'], $expiry_date, $donation_id, 'available']);
                    
                    $success_message = "Blood donation recorded successfully!";
                }
                break;
                
            case 'blood_request':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $stmt = $db->prepare("INSERT INTO blood_requests (request_id, patient_id, blood_type, units_requested, urgency_level, requested_by, request_date, required_date, purpose, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $request_id = 'REQ' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $stmt->execute([
                        $request_id, $_POST['patient_id'], $_POST['blood_type'], $_POST['units_requested'],
                        $_POST['urgency_level'], $user_id, date('Y-m-d'), $_POST['required_date'],
                        $_POST['purpose'], 'pending'
                    ]);
                    
                    $success_message = "Blood request submitted successfully!";
                }
                break;
        }
    }
}

// Get statistics
$stats = [];
$stats['total_donors'] = $db->query("SELECT COUNT(*) as count FROM blood_donors WHERE status = 'active'")->fetch()['count'];
$stats['total_donations'] = $db->query("SELECT COUNT(*) as count FROM blood_donations")->fetch()['count'];
$stats['pending_requests'] = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'pending'")->fetch()['count'];
$stats['units_available'] = $db->query("SELECT SUM(units_available) as total FROM blood_inventory WHERE status = 'available' AND expiry_date > CURDATE()")->fetch()['total'] ?? 0;

// Get blood inventory
$inventory = $db->query("SELECT blood_type, SUM(units_available) as total_units, MIN(expiry_date) as earliest_expiry FROM blood_inventory WHERE status = 'available' AND expiry_date > CURDATE() GROUP BY blood_type ORDER BY blood_type")->fetchAll();

// Get recent donations
$recent_donations = $db->query("SELECT bd.*, CONCAT(bdr.first_name, ' ', bdr.last_name) as donor_name, CONCAT(u.first_name, ' ', u.last_name) as staff_name FROM blood_donations bd LEFT JOIN blood_donors bdr ON bd.donor_id = bdr.id LEFT JOIN users u ON bd.staff_id = u.id ORDER BY bd.donation_date DESC LIMIT 10")->fetchAll();

// Get pending requests
$pending_requests = $db->query("SELECT br.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name, CONCAT(u.first_name, ' ', u.last_name) as requested_by_name FROM blood_requests br LEFT JOIN patients p ON br.patient_id = p.id LEFT JOIN users u ON br.requested_by = u.id WHERE br.status = 'pending' ORDER BY br.urgency_level DESC, br.required_date ASC")->fetchAll();

// Get donors for dropdowns
$donors = $db->query("SELECT * FROM blood_donors WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll();

// Get patients for dropdowns
$patients = $db->query("SELECT * FROM patients ORDER BY first_name, last_name")->fetchAll();
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

        .stat-card.donors i { color: #e74c3c; }
        .stat-card.donations i { color: #3498db; }
        .stat-card.requests i { color: #f39c12; }
        .stat-card.inventory i { color: #27ae60; }

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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
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
        .badge.pending { background: #fff3cd; color: #856404; }
        .badge.urgent { background: #f8d7da; color: #721c24; }
        .badge.high { background: #ffeaa7; color: #d63031; }
        .badge.medium { background: #81ecec; color: #00b894; }
        .badge.low { background: #a29bfe; color: #6c5ce7; }

        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .blood-type-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #e74c3c;
        }

        .blood-type {
            font-size: 1.5rem;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 0.5rem;
        }

        .units-available {
            font-size: 1.2rem;
            color: #27ae60;
            margin-bottom: 0.5rem;
        }

        .expiry-info {
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
        <h1><i class="fas fa-tint"></i> Blood Bank Management</h1>
        <p>Comprehensive Blood Donation & Inventory System</p>
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
                <i class="fas fa-users"></i>
                <div class="stat-number"><?php echo $stats['total_donors']; ?></div>
                <div>Active Donors</div>
            </div>
            <div class="stat-card donations">
                <i class="fas fa-tint"></i>
                <div class="stat-number"><?php echo $stats['total_donations']; ?></div>
                <div>Total Donations</div>
            </div>
            <div class="stat-card requests">
                <i class="fas fa-hand-holding-medical"></i>
                <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                <div>Pending Requests</div>
            </div>
            <div class="stat-card inventory">
                <i class="fas fa-warehouse"></i>
                <div class="stat-number"><?php echo $stats['units_available']; ?></div>
                <div>Units Available</div>
            </div>
        </div>

        <!-- Blood Inventory -->
        <div class="tab-content active">
            <h3><i class="fas fa-warehouse"></i> Blood Inventory</h3>
            <div class="inventory-grid">
                <?php foreach ($inventory as $item): ?>
                    <div class="blood-type-card">
                        <div class="blood-type"><?php echo htmlspecialchars($item['blood_type']); ?></div>
                        <div class="units-available"><?php echo $item['total_units']; ?> Units</div>
                        <div class="expiry-info">Expires: <?php echo date('M d, Y', strtotime($item['earliest_expiry'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="showTab('inventory')">
                <i class="fas fa-warehouse"></i> Inventory
            </div>
            <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
            <div class="tab" onclick="showTab('donors')">
                <i class="fas fa-user-plus"></i> Donors
            </div>
            <?php endif; ?>
            <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])): ?>
            <div class="tab" onclick="showTab('donations')">
                <i class="fas fa-tint"></i> Donations
            </div>
            <?php endif; ?>
            <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
            <div class="tab" onclick="showTab('requests')">
                <i class="fas fa-hand-holding-medical"></i> Requests
            </div>
            <?php endif; ?>
        </div>

        <!-- Donor Management Tab -->
        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
        <div id="donors" class="tab-content">
            <h3><i class="fas fa-user-plus"></i> Donor Management</h3>
            
            <form method="POST" class="form-grid">
                <input type="hidden" name="action" value="add_donor">
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
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Address</label>
                    <textarea name="address" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-user-plus"></i> Register Donor
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Donations Tab -->
        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])): ?>
        <div id="donations" class="tab-content">
            <h3><i class="fas fa-tint"></i> Blood Donations</h3>
            
            <form method="POST" class="form-grid">
                <input type="hidden" name="action" value="add_donation">
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
                    <label>Units Collected *</label>
                    <input type="number" name="units_collected" step="0.1" min="0.1" max="2" required>
                </div>
                <div class="form-group">
                    <label>Donation Date *</label>
                    <input type="date" name="donation_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Collection Site</label>
                    <input type="text" name="collection_site" value="Main Hospital">
                </div>
                <div class="form-group">
                    <label>Hemoglobin Level (g/dL)</label>
                    <input type="number" name="hemoglobin_level" step="0.1" min="0">
                </div>
                <div class="form-group">
                    <label>Blood Pressure</label>
                    <input type="text" name="blood_pressure" placeholder="120/80">
                </div>
                <div class="form-group">
                    <label>Temperature (°F)</label>
                    <input type="number" name="temperature" step="0.1" min="90" max="110">
                </div>
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight" step="0.1" min="0">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Medical Notes</label>
                    <textarea name="medical_notes" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-tint"></i> Record Donation
                    </button>
                </div>
            </form>

            <h4>Recent Donations</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Donation ID</th>
                        <th>Donor</th>
                        <th>Blood Type</th>
                        <th>Units</th>
                        <th>Date</th>
                        <th>Staff</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_donations as $donation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($donation['donation_id']); ?></td>
                            <td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
                            <td><?php echo htmlspecialchars($donation['blood_type']); ?></td>
                            <td><?php echo $donation['units_collected']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                            <td><?php echo htmlspecialchars($donation['staff_name']); ?></td>
                            <td><span class="badge active"><?php echo ucfirst($donation['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Blood Requests Tab -->
        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
        <div id="requests" class="tab-content">
            <h3><i class="fas fa-hand-holding-medical"></i> Blood Requests</h3>
            
            <form method="POST" class="form-grid">
                <input type="hidden" name="action" value="blood_request">
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
                    <label>Units Requested *</label>
                    <input type="number" name="units_requested" step="0.1" min="0.1" required>
                </div>
                <div class="form-group">
                    <label>Required Date *</label>
                    <input type="date" name="required_date" min="<?php echo date('Y-m-d'); ?>" required>
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
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Purpose/Notes</label>
                    <textarea name="purpose" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">
                        <i class="fas fa-hand-holding-medical"></i> Submit Request
                    </button>
                </div>
            </form>

            <h4>Pending Requests</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Patient</th>
                        <th>Blood Type</th>
                        <th>Units</th>
                        <th>Required Date</th>
                        <th>Urgency</th>
                        <th>Requested By</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                            <td><?php echo htmlspecialchars($request['patient_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['blood_type']); ?></td>
                            <td><?php echo $request['units_requested']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($request['required_date'])); ?></td>
                            <td><span class="badge <?php echo $request['urgency_level']; ?>"><?php echo ucfirst($request['urgency_level']); ?></span></td>
                            <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                            <td><span class="badge pending"><?php echo ucfirst($request['status']); ?></span></td>
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
                // Show inventory by default
                tabContents[0].classList.add('active');
            }

            // Add active class to clicked tab
            event.target.closest('.tab').classList.add('active');
        }
    </script>
</body>
</html>
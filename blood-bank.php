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
            case 'add_donation':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $donor_name = $_POST['donor_name'];
                    $donor_phone = $_POST['donor_phone'];
                    $donor_email = $_POST['donor_email'];
                    $blood_type = $_POST['blood_type'];
                    $units_donated = $_POST['units_donated'];
                    $donation_date = $_POST['donation_date'];
                    $notes = $_POST['notes'];
                    
                    $stmt = $db->prepare("INSERT INTO blood_donations (donor_name, donor_phone, donor_email, blood_type, units_donated, donation_date, notes, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$donor_name, $donor_phone, $donor_email, $blood_type, $units_donated, $donation_date, $notes, $user_id]);
                    
                    // Update blood inventory
                    $stmt = $db->prepare("INSERT INTO blood_inventory (blood_type, units_available, last_updated) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE units_available = units_available + ?, last_updated = NOW()");
                    $stmt->execute([$blood_type, $units_donated, $units_donated]);
                    
                    $success_message = "Blood donation recorded successfully!";
                }
                break;
                
            case 'add_request':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $patient_id = $_POST['patient_id'];
                    $blood_type = $_POST['blood_type'];
                    $units_required = $_POST['units_required'];
                    $urgency_level = $_POST['urgency_level'];
                    $required_date = $_POST['required_date'];
                    $notes = $_POST['notes'];
                    
                    $stmt = $db->prepare("INSERT INTO blood_requests (patient_id, blood_type, units_required, urgency_level, required_date, notes, requested_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$patient_id, $blood_type, $units_required, $urgency_level, $required_date, $notes, $user_id]);
                    
                    $success_message = "Blood request submitted successfully!";
                }
                break;
                
            case 'fulfill_request':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $request_id = $_POST['request_id'];
                    $units_fulfilled = $_POST['units_fulfilled'];
                    
                    // Get request details
                    $stmt = $db->prepare("SELECT * FROM blood_requests WHERE id = ?");
                    $stmt->execute([$request_id]);
                    $request = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($request) {
                        // Update request status
                        $new_status = ($units_fulfilled >= $request['units_required']) ? 'fulfilled' : 'partial';
                        $stmt = $db->prepare("UPDATE blood_requests SET status = ?, units_fulfilled = ?, fulfilled_by = ?, fulfilled_at = NOW() WHERE id = ?");
                        $stmt->execute([$new_status, $units_fulfilled, $user_id, $request_id]);
                        
                        // Update blood inventory
                        $stmt = $db->prepare("UPDATE blood_inventory SET units_available = units_available - ?, last_updated = NOW() WHERE blood_type = ?");
                        $stmt->execute([$units_fulfilled, $request['blood_type']]);
                        
                        $success_message = "Blood request fulfilled successfully!";
                    }
                }
                break;
        }
    }
}

// Get statistics
$total_donations = $db->query("SELECT COUNT(*) FROM blood_donations")->fetchColumn();
$total_requests = $db->query("SELECT COUNT(*) FROM blood_requests")->fetchColumn();
$pending_requests = $db->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'pending'")->fetchColumn();
$critical_requests = $db->query("SELECT COUNT(*) FROM blood_requests WHERE urgency_level = 'critical' AND status = 'pending'")->fetchColumn();

// Get blood inventory
$inventory = $db->query("SELECT * FROM blood_inventory ORDER BY blood_type")->fetchAll(PDO::FETCH_ASSOC);

// Get recent donations
$recent_donations = $db->query("SELECT bd.*, u.first_name, u.last_name FROM blood_donations bd LEFT JOIN users u ON bd.recorded_by = u.id ORDER BY bd.created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// Get blood requests
$blood_requests = $db->query("SELECT br.*, p.first_name, p.last_name, p.patient_id, u.first_name as req_fname, u.last_name as req_lname FROM blood_requests br JOIN patients p ON br.patient_id = p.id LEFT JOIN users u ON br.requested_by = u.id ORDER BY br.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get patients for dropdown
$patients = $db->query("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Management - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content">
                <div class="page-header">
                    <h1><i class="fas fa-tint"></i> Blood Bank Management</h1>
                    <p>Manage blood donations, inventory, and requests</p>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tint"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_donations; ?></h3>
                            <p>Total Donations</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-hand-holding-medical"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_requests; ?></h3>
                            <p>Total Requests</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $pending_requests; ?></h3>
                            <p>Pending Requests</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $critical_requests; ?></h3>
                            <p>Critical Requests</p>
                        </div>
                    </div>
                </div>

                <!-- Blood Inventory -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-warehouse"></i> Blood Inventory</h3>
                    </div>
                    <div class="card-body">
                        <div class="blood-inventory-grid">
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $blood_type): ?>
                                <?php 
                                $units = 0;
                                foreach ($inventory as $item) {
                                    if ($item['blood_type'] === $blood_type) {
                                        $units = $item['units_available'];
                                        break;
                                    }
                                }
                                $status_class = $units < 5 ? 'critical' : ($units < 10 ? 'low' : 'good');
                                ?>
                                <div class="blood-type-card <?php echo $status_class; ?>">
                                    <h4><?php echo $blood_type; ?></h4>
                                    <p class="units"><?php echo $units; ?> units</p>
                                    <span class="status"><?php echo ucfirst($status_class); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Blood Donations -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-tint"></i> Blood Donations</h3>
                                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                                    <button class="btn btn-primary" onclick="openModal('addDonationModal')">
                                        <i class="fas fa-plus"></i> Record Donation
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Donor</th>
                                                <th>Blood Type</th>
                                                <th>Units</th>
                                                <th>Date</th>
                                                <th>Recorded By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_donations as $donation): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($donation['donor_name']); ?></strong><br>
                                                        <small><?php echo htmlspecialchars($donation['donor_phone']); ?></small>
                                                    </td>
                                                    <td><span class="blood-type-badge"><?php echo $donation['blood_type']; ?></span></td>
                                                    <td><?php echo $donation['units_donated']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($donation['first_name'] . ' ' . $donation['last_name']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Blood Requests -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-hand-holding-medical"></i> Blood Requests</h3>
                                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                                    <button class="btn btn-primary" onclick="openModal('addRequestModal')">
                                        <i class="fas fa-plus"></i> New Request
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Blood Type</th>
                                                <th>Units</th>
                                                <th>Urgency</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($blood_requests as $request): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong><br>
                                                        <small><?php echo htmlspecialchars($request['patient_id']); ?></small>
                                                    </td>
                                                    <td><span class="blood-type-badge"><?php echo $request['blood_type']; ?></span></td>
                                                    <td><?php echo $request['units_required']; ?></td>
                                                    <td><span class="urgency-badge urgency-<?php echo $request['urgency_level']; ?>"><?php echo ucfirst($request['urgency_level']); ?></span></td>
                                                    <td><span class="status-badge status-<?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span></td>
                                                    <td>
                                                        <?php if ($request['status'] === 'pending' && in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                                                            <button class="btn btn-sm btn-success" onclick="fulfillRequest(<?php echo $request['id']; ?>, '<?php echo $request['blood_type']; ?>', <?php echo $request['units_required']; ?>)">
                                                                <i class="fas fa-check"></i> Fulfill
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Donation Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
    <div id="addDonationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Record Blood Donation</h3>
                <span class="close" onclick="closeModal('addDonationModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_donation">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Donor Name *</label>
                        <input type="text" name="donor_name" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="donor_phone" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="donor_email">
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
                        <label>Units Donated *</label>
                        <input type="number" name="units_donated" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Donation Date *</label>
                        <input type="date" name="donation_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addDonationModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Donation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Request Modal -->
    <div id="addRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>New Blood Request</h3>
                <span class="close" onclick="closeModal('addRequestModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_request">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Patient *</label>
                        <select name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
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
                        <label>Units Required *</label>
                        <input type="number" name="units_required" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Urgency Level *</label>
                        <select name="urgency_level" required>
                            <option value="">Select Urgency</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Required Date *</label>
                        <input type="date" name="required_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addRequestModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Fulfill Request Modal -->
    <div id="fulfillRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Fulfill Blood Request</h3>
                <span class="close" onclick="closeModal('fulfillRequestModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="fulfill_request">
                <input type="hidden" name="request_id" id="fulfill_request_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Blood Type</label>
                        <input type="text" id="fulfill_blood_type" readonly>
                    </div>
                    <div class="form-group">
                        <label>Units Required</label>
                        <input type="number" id="fulfill_units_required" readonly>
                    </div>
                    <div class="form-group">
                        <label>Units to Fulfill *</label>
                        <input type="number" name="units_fulfilled" id="fulfill_units_fulfilled" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('fulfillRequestModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Fulfill Request</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="assets/js/script.js"></script>
    <script>
        function fulfillRequest(requestId, bloodType, unitsRequired) {
            document.getElementById('fulfill_request_id').value = requestId;
            document.getElementById('fulfill_blood_type').value = bloodType;
            document.getElementById('fulfill_units_required').value = unitsRequired;
            document.getElementById('fulfill_units_fulfilled').value = unitsRequired;
            openModal('fulfillRequestModal');
        }
    </script>

    <style>
        .blood-inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .blood-type-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .blood-type-card.critical {
            border-color: #dc3545;
            background: #fff5f5;
        }

        .blood-type-card.low {
            border-color: #ffc107;
            background: #fffdf5;
        }

        .blood-type-card.good {
            border-color: #28a745;
            background: #f8fff8;
        }

        .blood-type-card h4 {
            margin: 0 0 10px 0;
            font-size: 24px;
            font-weight: bold;
        }

        .blood-type-card .units {
            font-size: 18px;
            margin: 5px 0;
            font-weight: 600;
        }

        .blood-type-card .status {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 12px;
            text-transform: uppercase;
        }

        .blood-type-card.critical .status {
            background: #dc3545;
            color: white;
        }

        .blood-type-card.low .status {
            background: #ffc107;
            color: #333;
        }

        .blood-type-card.good .status {
            background: #28a745;
            color: white;
        }

        .blood-type-badge {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }

        .urgency-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .urgency-low { background: #d4edda; color: #155724; }
        .urgency-medium { background: #fff3cd; color: #856404; }
        .urgency-high { background: #f8d7da; color: #721c24; }
        .urgency-critical { background: #dc3545; color: white; }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-partial { background: #cce5ff; color: #004085; }
        .status-fulfilled { background: #d4edda; color: #155724; }
    </style>
</body>
</html>
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
                    $stmt = $db->prepare("
                        INSERT INTO blood_donations (donor_name, donor_phone, donor_email, blood_type, 
                                                   donation_date, volume_ml, hemoglobin_level, status, 
                                                   collected_by, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'available', ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['donor_name'], $_POST['donor_phone'], $_POST['donor_email'],
                        $_POST['blood_type'], $_POST['donation_date'], $_POST['volume_ml'],
                        $_POST['hemoglobin_level'], $user_id, $_POST['notes']
                    ]);
                    $success = "Blood donation recorded successfully!";
                }
                break;
                
            case 'add_request':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $stmt = $db->prepare("
                        INSERT INTO blood_requests (patient_id, blood_type, units_needed, urgency_level, 
                                                  request_date, required_by, reason, requested_by, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $_POST['patient_id'], $_POST['blood_type'], $_POST['units_needed'],
                        $_POST['urgency_level'], $_POST['request_date'], $_POST['required_by'],
                        $_POST['reason'], $user_id
                    ]);
                    $success = "Blood request submitted successfully!";
                }
                break;
                
            case 'fulfill_request':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $request_id = $_POST['request_id'];
                    $donation_id = $_POST['donation_id'];
                    
                    // Update request status
                    $stmt = $db->prepare("UPDATE blood_requests SET status = 'fulfilled', fulfilled_by = ?, fulfilled_date = NOW() WHERE id = ?");
                    $stmt->execute([$user_id, $request_id]);
                    
                    // Update donation status
                    $stmt = $db->prepare("UPDATE blood_donations SET status = 'used', used_date = NOW(), used_for_request = ? WHERE id = ?");
                    $stmt->execute([$request_id, $donation_id]);
                    
                    $success = "Blood request fulfilled successfully!";
                }
                break;
        }
    }
}

// Get blood inventory
$blood_inventory = $db->query("
    SELECT blood_type, 
           COUNT(*) as total_units,
           SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_units,
           SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_units,
           SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_units
    FROM blood_donations 
    GROUP BY blood_type 
    ORDER BY blood_type
")->fetchAll();

// Get recent donations
$recent_donations = $db->query("
    SELECT bd.*, u.first_name, u.last_name 
    FROM blood_donations bd
    LEFT JOIN users u ON bd.collected_by = u.id
    ORDER BY bd.donation_date DESC 
    LIMIT 10
")->fetchAll();

// Get pending requests
$pending_requests = $db->query("
    SELECT br.*, p.first_name, p.last_name, p.patient_id,
           u.first_name as req_fname, u.last_name as req_lname
    FROM blood_requests br
    JOIN patients p ON br.patient_id = p.id
    LEFT JOIN users u ON br.requested_by = u.id
    WHERE br.status = 'pending'
    ORDER BY br.urgency_level DESC, br.request_date ASC
")->fetchAll();

// Get available donations for requests
$available_donations = $db->query("
    SELECT * FROM blood_donations 
    WHERE status = 'available' 
    ORDER BY blood_type, donation_date ASC
")->fetchAll();

// Get patients for dropdown
$patients = $db->query("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name")->fetchAll();

// Get patient-specific data if user is a patient
$patient_donations = [];
$patient_requests = [];
if ($user_role === 'patient') {
    $patient_id = $db->query("SELECT id FROM patients WHERE user_id = ?", [$user_id])->fetch()['id'] ?? null;
    if ($patient_id) {
        $patient_donations = $db->query("
            SELECT * FROM blood_donations 
            WHERE donor_phone = (SELECT phone FROM patients WHERE id = ?) OR donor_email = (SELECT email FROM patients WHERE id = ?)
            ORDER BY donation_date DESC
        ", [$patient_id, $patient_id])->fetchAll();
        
        $patient_requests = $db->query("
            SELECT br.*, u.first_name as req_fname, u.last_name as req_lname
            FROM blood_requests br
            LEFT JOIN users u ON br.requested_by = u.id
            WHERE br.patient_id = ?
            ORDER BY br.request_date DESC
        ", [$patient_id])->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Management - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="wrapper">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content">
                <div class="page-header">
                    <h1><i class="fas fa-tint"></i> Blood Bank Management</h1>
                    <p>Manage blood donations, requests, and inventory</p>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Blood Inventory Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tint"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo array_sum(array_column($blood_inventory, 'available_units')); ?></h3>
                            <p>Available Units</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($recent_donations); ?></h3>
                            <p>Recent Donations</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($pending_requests); ?></h3>
                            <p>Pending Requests</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo array_sum(array_column($blood_inventory, 'used_units')); ?></h3>
                            <p>Units Used</p>
                        </div>
                    </div>
                </div>

                <?php if ($user_role === 'patient'): ?>
                    <!-- Patient View -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-tint"></i> My Donations</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($patient_donations)): ?>
                                        <p>No donation records found.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Blood Type</th>
                                                        <th>Volume</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($patient_donations as $donation): ?>
                                                        <tr>
                                                            <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                                            <td><span class="badge badge-primary"><?php echo $donation['blood_type']; ?></span></td>
                                                            <td><?php echo $donation['volume_ml']; ?> ml</td>
                                                            <td>
                                                                <span class="badge badge-<?php echo $donation['status'] === 'available' ? 'success' : ($donation['status'] === 'used' ? 'info' : 'warning'); ?>">
                                                                    <?php echo ucfirst($donation['status']); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-file-medical"></i> My Blood Requests</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($patient_requests)): ?>
                                        <p>No blood requests found.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Blood Type</th>
                                                        <th>Units</th>
                                                        <th>Urgency</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($patient_requests as $request): ?>
                                                        <tr>
                                                            <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                                            <td><span class="badge badge-primary"><?php echo $request['blood_type']; ?></span></td>
                                                            <td><?php echo $request['units_needed']; ?></td>
                                                            <td>
                                                                <span class="badge badge-<?php echo $request['urgency_level'] === 'critical' ? 'danger' : ($request['urgency_level'] === 'high' ? 'warning' : 'info'); ?>">
                                                                    <?php echo ucfirst($request['urgency_level']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-<?php echo $request['status'] === 'fulfilled' ? 'success' : ($request['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                                    <?php echo ucfirst($request['status']); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Admin/Staff View -->
                    <div class="row">
                        <!-- Blood Inventory -->
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3><i class="fas fa-warehouse"></i> Blood Inventory</h3>
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
                                                    <th>Blood Type</th>
                                                    <th>Total Units</th>
                                                    <th>Available</th>
                                                    <th>Used</th>
                                                    <th>Expired</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($blood_inventory as $inventory): ?>
                                                    <tr>
                                                        <td><span class="badge badge-primary"><?php echo $inventory['blood_type']; ?></span></td>
                                                        <td><?php echo $inventory['total_units']; ?></td>
                                                        <td><span class="badge badge-success"><?php echo $inventory['available_units']; ?></span></td>
                                                        <td><span class="badge badge-info"><?php echo $inventory['used_units']; ?></span></td>
                                                        <td><span class="badge badge-warning"><?php echo $inventory['expired_units']; ?></span></td>
                                                        <td>
                                                            <?php 
                                                            $status = $inventory['available_units'] > 5 ? 'Good' : ($inventory['available_units'] > 2 ? 'Low' : 'Critical');
                                                            $badge_class = $status === 'Good' ? 'success' : ($status === 'Low' ? 'warning' : 'danger');
                                                            ?>
                                                            <span class="badge badge-<?php echo $badge_class; ?>"><?php echo $status; ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Requests -->
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3><i class="fas fa-exclamation-triangle"></i> Pending Blood Requests</h3>
                                    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                                        <button class="btn btn-danger" onclick="openModal('addRequestModal')">
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
                                                    <th>Units Needed</th>
                                                    <th>Urgency</th>
                                                    <th>Required By</th>
                                                    <th>Requested By</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_requests as $request): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></strong><br>
                                                            <small><?php echo $request['patient_id']; ?></small>
                                                        </td>
                                                        <td><span class="badge badge-primary"><?php echo $request['blood_type']; ?></span></td>
                                                        <td><?php echo $request['units_needed']; ?></td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $request['urgency_level'] === 'critical' ? 'danger' : ($request['urgency_level'] === 'high' ? 'warning' : 'info'); ?>">
                                                                <?php echo ucfirst($request['urgency_level']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M d, Y', strtotime($request['required_by'])); ?></td>
                                                        <td><?php echo htmlspecialchars($request['req_fname'] . ' ' . $request['req_lname']); ?></td>
                                                        <td>
                                                            <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                                                                <button class="btn btn-sm btn-success" onclick="fulfillRequest(<?php echo $request['id']; ?>, '<?php echo $request['blood_type']; ?>')">
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

                        <!-- Recent Donations -->
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-hand-holding-heart"></i> Recent Donations</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Donor</th>
                                                    <th>Blood Type</th>
                                                    <th>Volume</th>
                                                    <th>Date</th>
                                                    <th>Collected By</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_donations as $donation): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($donation['donor_name']); ?></strong><br>
                                                            <small><?php echo $donation['donor_phone']; ?></small>
                                                        </td>
                                                        <td><span class="badge badge-primary"><?php echo $donation['blood_type']; ?></span></td>
                                                        <td><?php echo $donation['volume_ml']; ?> ml</td>
                                                        <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($donation['first_name'] . ' ' . $donation['last_name']); ?></td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $donation['status'] === 'available' ? 'success' : ($donation['status'] === 'used' ? 'info' : 'warning'); ?>">
                                                                <?php echo ucfirst($donation['status']); ?>
                                                            </span>
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
                <?php endif; ?>
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="donor_name">Donor Name</label>
                                <input type="text" id="donor_name" name="donor_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="donor_phone">Phone</label>
                                <input type="tel" id="donor_phone" name="donor_phone" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="donor_email">Email</label>
                                <input type="email" id="donor_email" name="donor_email" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="blood_type">Blood Type</label>
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
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="donation_date">Donation Date</label>
                                <input type="date" id="donation_date" name="donation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="volume_ml">Volume (ml)</label>
                                <input type="number" id="volume_ml" name="volume_ml" class="form-control" value="450" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="hemoglobin_level">Hemoglobin Level (g/dL)</label>
                                <input type="number" id="hemoglobin_level" name="hemoglobin_level" class="form-control" step="0.1" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addDonationModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Donation</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Request Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
    <div id="addRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create Blood Request</h3>
                <span class="close" onclick="closeModal('addRequestModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_request">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="patient_id">Patient</label>
                                <select id="patient_id" name="patient_id" class="form-control" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="req_blood_type">Blood Type</label>
                                <select id="req_blood_type" name="blood_type" class="form-control" required>
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
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="units_needed">Units Needed</label>
                                <input type="number" id="units_needed" name="units_needed" class="form-control" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="urgency_level">Urgency Level</label>
                                <select id="urgency_level" name="urgency_level" class="form-control" required>
                                    <option value="">Select Urgency</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="request_date">Request Date</label>
                                <input type="date" id="request_date" name="request_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="required_by">Required By</label>
                                <input type="date" id="required_by" name="required_by" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="reason">Reason</label>
                                <textarea id="reason" name="reason" class="form-control" rows="3" required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addRequestModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fulfill Request Modal -->
    <div id="fulfillRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Fulfill Blood Request</h3>
                <span class="close" onclick="closeModal('fulfillRequestModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="fulfill_request">
                <input type="hidden" id="fulfill_request_id" name="request_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="donation_id">Select Available Donation</label>
                        <select id="donation_id" name="donation_id" class="form-control" required>
                            <option value="">Select Donation</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('fulfillRequestModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Fulfill Request</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        function fulfillRequest(requestId, bloodType) {
            document.getElementById('fulfill_request_id').value = requestId;
            
            // Filter available donations by blood type
            const donationSelect = document.getElementById('donation_id');
            donationSelect.innerHTML = '<option value="">Select Donation</option>';
            
            <?php foreach ($available_donations as $donation): ?>
                if ('<?php echo $donation['blood_type']; ?>' === bloodType) {
                    const option = document.createElement('option');
                    option.value = '<?php echo $donation['id']; ?>';
                    option.textContent = '<?php echo $donation['blood_type']; ?> - <?php echo $donation['donor_name']; ?> (<?php echo date('M d, Y', strtotime($donation['donation_date'])); ?>)';
                    donationSelect.appendChild(option);
                }
            <?php endforeach; ?>
            
            openModal('fulfillRequestModal');
        }
    </script>
</body>
</html>
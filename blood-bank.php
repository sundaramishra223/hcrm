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
$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_donation':
                if (in_array($user_role, ['admin', 'nurse', 'lab_technician'])) {
                    $donor_id = $_POST['donor_id'];
                    $blood_type = $_POST['blood_type'];
                    $volume = $_POST['volume'];
                    $collection_date = $_POST['collection_date'];
                    $expiry_date = $_POST['expiry_date'];
                    $status = $_POST['status'];
                    $notes = $_POST['notes'];
                    
                    $stmt = $db->prepare("INSERT INTO blood_donations (donor_id, blood_type, volume_ml, collection_date, expiry_date, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$donor_id, $blood_type, $volume, $collection_date, $expiry_date, $status, $notes, $user_id]);
                    
                    $_SESSION['success'] = "Blood donation recorded successfully!";
                }
                break;
                
            case 'add_request':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $patient_id = $_POST['patient_id'];
                    $blood_type = $_POST['blood_type'];
                    $volume = $_POST['volume'];
                    $urgency = $_POST['urgency'];
                    $required_date = $_POST['required_date'];
                    $notes = $_POST['notes'];
                    
                    $stmt = $db->prepare("INSERT INTO blood_requests (patient_id, blood_type, volume_ml, urgency_level, required_date, notes, requested_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$patient_id, $blood_type, $volume, $urgency, $required_date, $notes, $user_id]);
                    
                    $_SESSION['success'] = "Blood request submitted successfully!";
                }
                break;
                
            case 'update_request_status':
                if (in_array($user_role, ['admin', 'lab_technician'])) {
                    $request_id = $_POST['request_id'];
                    $status = $_POST['status'];
                    $donation_id = $_POST['donation_id'] ?? null;
                    
                    $stmt = $db->prepare("UPDATE blood_requests SET status = ?, fulfilled_by_donation_id = ?, fulfilled_date = ? WHERE id = ?");
                    $fulfilled_date = $status === 'fulfilled' ? date('Y-m-d H:i:s') : null;
                    $stmt->execute([$status, $donation_id, $fulfilled_date, $request_id]);
                    
                    if ($status === 'fulfilled' && $donation_id) {
                        $stmt = $db->prepare("UPDATE blood_donations SET status = 'used' WHERE id = ?");
                        $stmt->execute([$donation_id]);
                    }
                    
                    $_SESSION['success'] = "Request status updated successfully!";
                }
                break;
        }
        header('Location: blood-bank.php');
        exit;
    }
}

// Get blood bank statistics
$stats = [];

// Total donations
$stmt = $db->prepare("SELECT COUNT(*) as total FROM blood_donations");
$stmt->execute();
$stats['total_donations'] = $stmt->fetch()['total'];

// Available units
$stmt = $db->prepare("SELECT COUNT(*) as available FROM blood_donations WHERE status = 'available' AND expiry_date > CURDATE()");
$stmt->execute();
$stats['available_units'] = $stmt->fetch()['available'];

// Pending requests
$stmt = $db->prepare("SELECT COUNT(*) as pending FROM blood_requests WHERE status = 'pending'");
$stmt->execute();
$stats['pending_requests'] = $stmt->fetch()['pending'];

// Blood type inventory
$stmt = $db->prepare("SELECT blood_type, COUNT(*) as count, SUM(volume_ml) as total_volume FROM blood_donations WHERE status = 'available' AND expiry_date > CURDATE() GROUP BY blood_type");
$stmt->execute();
$blood_inventory = $stmt->fetchAll();

// Recent donations
$stmt = $db->prepare("
    SELECT bd.*, p.first_name, p.last_name, p.patient_id 
    FROM blood_donations bd 
    JOIN patients p ON bd.donor_id = p.id 
    ORDER BY bd.collection_date DESC 
    LIMIT 10
");
$stmt->execute();
$recent_donations = $stmt->fetchAll();

// Blood requests
if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])) {
    $stmt = $db->prepare("
        SELECT br.*, p.first_name, p.last_name, p.patient_id, u.name as requested_by_name
        FROM blood_requests br 
        JOIN patients p ON br.patient_id = p.id 
        JOIN users u ON br.requested_by = u.id 
        ORDER BY br.created_at DESC
    ");
    $stmt->execute();
    $blood_requests = $stmt->fetchAll();
} else {
    // Patient can only see their own requests
    $stmt = $db->prepare("
        SELECT br.*, p.first_name, p.last_name, p.patient_id, u.name as requested_by_name
        FROM blood_requests br 
        JOIN patients p ON br.patient_id = p.id 
        JOIN users u ON br.requested_by = u.id 
        WHERE p.user_id = ?
        ORDER BY br.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $blood_requests = $stmt->fetchAll();
}

// Get patients for dropdowns
if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician'])) {
    $stmt = $db->prepare("SELECT id, first_name, last_name, patient_id, blood_type FROM patients ORDER BY first_name, last_name");
    $stmt->execute();
    $patients = $stmt->fetchAll();
}

// Get available donations for fulfilling requests
if (in_array($user_role, ['admin', 'lab_technician'])) {
    $stmt = $db->prepare("
        SELECT bd.*, p.first_name, p.last_name 
        FROM blood_donations bd 
        JOIN patients p ON bd.donor_id = p.id 
        WHERE bd.status = 'available' AND bd.expiry_date > CURDATE()
        ORDER BY bd.collection_date ASC
    ");
    $stmt->execute();
    $available_donations = $stmt->fetchAll();
}
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
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>

            <!-- Page Content -->
            <div class="page-content">
                <div class="page-header">
                    <h1><i class="fas fa-tint"></i> Blood Bank Management</h1>
                    <div class="page-actions">
                        <?php if (in_array($user_role, ['admin', 'nurse', 'lab_technician'])): ?>
                            <button class="btn btn-primary" onclick="openModal('addDonationModal')">
                                <i class="fas fa-plus"></i> Record Donation
                            </button>
                        <?php endif; ?>
                        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                            <button class="btn btn-danger" onclick="openModal('addRequestModal')">
                                <i class="fas fa-hand-paper"></i> Blood Request
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tint"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_donations']; ?></h3>
                            <p>Total Donations</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['available_units']; ?></h3>
                            <p>Available Units</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['pending_requests']; ?></h3>
                            <p>Pending Requests</p>
                        </div>
                    </div>
                </div>

                <!-- Blood Type Inventory -->
                <div class="content-section">
                    <h2><i class="fas fa-chart-bar"></i> Blood Type Inventory</h2>
                    <div class="blood-inventory-grid">
                        <?php 
                        $blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        foreach ($blood_types as $type): 
                            $found = false;
                            $count = 0;
                            $volume = 0;
                            foreach ($blood_inventory as $inv) {
                                if ($inv['blood_type'] === $type) {
                                    $found = true;
                                    $count = $inv['count'];
                                    $volume = $inv['total_volume'];
                                    break;
                                }
                            }
                        ?>
                            <div class="blood-type-card">
                                <div class="blood-type-header">
                                    <h3><?php echo $type; ?></h3>
                                </div>
                                <div class="blood-type-stats">
                                    <p><strong><?php echo $count; ?></strong> Units</p>
                                    <p><strong><?php echo number_format($volume); ?></strong> ml</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Blood Requests -->
                <div class="content-section">
                    <h2><i class="fas fa-hand-paper"></i> Blood Requests</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Patient</th>
                                    <th>Blood Type</th>
                                    <th>Volume (ml)</th>
                                    <th>Urgency</th>
                                    <th>Required Date</th>
                                    <th>Status</th>
                                    <th>Requested By</th>
                                    <?php if (in_array($user_role, ['admin', 'lab_technician'])): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blood_requests as $request): ?>
                                    <tr>
                                        <td>#BR<?php echo str_pad($request['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                        <td><span class="blood-type-badge"><?php echo $request['blood_type']; ?></span></td>
                                        <td><?php echo number_format($request['volume_ml']); ?></td>
                                        <td>
                                            <span class="urgency-badge urgency-<?php echo $request['urgency_level']; ?>">
                                                <?php echo ucfirst($request['urgency_level']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($request['required_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                        <?php if (in_array($user_role, ['admin', 'lab_technician'])): ?>
                                            <td>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="fulfillRequest(<?php echo $request['id']; ?>, '<?php echo $request['blood_type']; ?>')">
                                                        <i class="fas fa-check"></i> Fulfill
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Donations -->
                <div class="content-section">
                    <h2><i class="fas fa-history"></i> Recent Donations</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Donation ID</th>
                                    <th>Donor</th>
                                    <th>Blood Type</th>
                                    <th>Volume (ml)</th>
                                    <th>Collection Date</th>
                                    <th>Expiry Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_donations as $donation): ?>
                                    <tr>
                                        <td>#BD<?php echo str_pad($donation['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($donation['first_name'] . ' ' . $donation['last_name']); ?></td>
                                        <td><span class="blood-type-badge"><?php echo $donation['blood_type']; ?></span></td>
                                        <td><?php echo number_format($donation['volume_ml']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($donation['collection_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($donation['expiry_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $donation['status']; ?>">
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

    <!-- Add Donation Modal -->
    <?php if (in_array($user_role, ['admin', 'nurse', 'lab_technician'])): ?>
    <div id="addDonationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Record Blood Donation</h2>
                <span class="close" onclick="closeModal('addDonationModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_donation">
                <div class="form-group">
                    <label for="donor_id">Donor:</label>
                    <select id="donor_id" name="donor_id" required>
                        <option value="">Select Donor</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="blood_type">Blood Type:</label>
                    <select id="blood_type" name="blood_type" required>
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
                    <label for="volume">Volume (ml):</label>
                    <input type="number" id="volume" name="volume" min="100" max="500" value="450" required>
                </div>
                <div class="form-group">
                    <label for="collection_date">Collection Date:</label>
                    <input type="date" id="collection_date" name="collection_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="expiry_date">Expiry Date:</label>
                    <input type="date" id="expiry_date" name="expiry_date" value="<?php echo date('Y-m-d', strtotime('+35 days')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status" required>
                        <option value="available">Available</option>
                        <option value="testing">Testing</option>
                        <option value="quarantine">Quarantine</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addDonationModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Donation</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Request Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
    <div id="addRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Submit Blood Request</h2>
                <span class="close" onclick="closeModal('addRequestModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_request">
                <div class="form-group">
                    <label for="patient_id">Patient:</label>
                    <select id="patient_id" name="patient_id" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="blood_type_req">Blood Type:</label>
                    <select id="blood_type_req" name="blood_type" required>
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
                    <label for="volume_req">Volume (ml):</label>
                    <input type="number" id="volume_req" name="volume" min="100" max="1000" value="450" required>
                </div>
                <div class="form-group">
                    <label for="urgency">Urgency Level:</label>
                    <select id="urgency" name="urgency" required>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="required_date">Required Date:</label>
                    <input type="date" id="required_date" name="required_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="notes_req">Notes:</label>
                    <textarea id="notes_req" name="notes" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addRequestModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fulfill Request Modal -->
    <?php if (in_array($user_role, ['admin', 'lab_technician'])): ?>
    <div id="fulfillRequestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Fulfill Blood Request</h2>
                <span class="close" onclick="closeModal('fulfillRequestModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_request_status">
                <input type="hidden" id="fulfill_request_id" name="request_id">
                <div class="form-group">
                    <label for="donation_id">Available Donation:</label>
                    <select id="donation_id" name="donation_id" required>
                        <option value="">Select Donation</option>
                    </select>
                </div>
                <input type="hidden" name="status" value="fulfilled">
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('fulfillRequestModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Fulfill Request</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="assets/js/script.js"></script>
    <script>
        function fulfillRequest(requestId, bloodType) {
            document.getElementById('fulfill_request_id').value = requestId;
            
            // Filter available donations by blood type
            const donationSelect = document.getElementById('donation_id');
            donationSelect.innerHTML = '<option value="">Select Donation</option>';
            
            <?php if (isset($available_donations)): ?>
            const availableDonations = <?php echo json_encode($available_donations); ?>;
            availableDonations.forEach(donation => {
                if (donation.blood_type === bloodType) {
                    const option = document.createElement('option');
                    option.value = donation.id;
                    option.textContent = `#BD${donation.id.toString().padStart(4, '0')} - ${donation.first_name} ${donation.last_name} (${donation.volume_ml}ml)`;
                    donationSelect.appendChild(option);
                }
            });
            <?php endif; ?>
            
            openModal('fulfillRequestModal');
        }
    </script>

    <style>
        .blood-inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .blood-type-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #dc3545;
        }

        .blood-type-header h3 {
            margin: 0;
            color: #dc3545;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .blood-type-stats p {
            margin: 0.5rem 0;
            color: #666;
        }

        .blood-type-badge {
            background: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.875rem;
        }

        .urgency-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: bold;
        }

        .urgency-low { background: #d4edda; color: #155724; }
        .urgency-medium { background: #fff3cd; color: #856404; }
        .urgency-high { background: #f8d7da; color: #721c24; }
        .urgency-critical { background: #dc3545; color: white; }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: bold;
        }

        .status-available { background: #d4edda; color: #155724; }
        .status-testing { background: #fff3cd; color: #856404; }
        .status-quarantine { background: #f8d7da; color: #721c24; }
        .status-used { background: #e2e3e5; color: #495057; }
        .status-expired { background: #f8d7da; color: #721c24; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-fulfilled { background: #d4edda; color: #155724; }
        .status-cancelled { background: #e2e3e5; color: #495057; }
    </style>
</body>
</html>
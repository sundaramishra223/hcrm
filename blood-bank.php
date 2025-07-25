<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist', 'patient'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$user_id = $_SESSION['user_id'];

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'register_donor':
            if (in_array($user_role, ['admin', 'nurse', 'receptionist'])) {
                $donor_id = 'BD' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
                $patient_id = $_POST['patient_id'] ?? null;
                $donor_name = $_POST['donor_name'];
                $blood_group = $_POST['blood_group'];
                $phone = $_POST['phone'];
                $email = $_POST['email'] ?? null;
                $date_of_birth = $_POST['date_of_birth'];
                $gender = $_POST['gender'];
                $address = $_POST['address'];
                $medical_history = $_POST['medical_history'] ?? '';
                $last_donation = $_POST['last_donation'] ?? null;
                
                try {
                    $db->query("
                        INSERT INTO blood_donors (donor_id, patient_id, donor_name, blood_group, phone, email, 
                                                date_of_birth, gender, address, medical_history, last_donation_date, 
                                                is_active, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
                    ", [$donor_id, $patient_id, $donor_name, $blood_group, $phone, $email, 
                        $date_of_birth, $gender, $address, $medical_history, $last_donation, $user_id]);
                    
                    $success_message = "Blood donor registered successfully! Donor ID: $donor_id";
                } catch (Exception $e) {
                    $error_message = "Error registering donor: " . $e->getMessage();
                }
            }
            break;
            
        case 'record_donation':
            if (in_array($user_role, ['admin', 'nurse', 'doctor'])) {
                $donation_id = 'DON' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
                $donor_id = $_POST['donor_id'];
                $blood_group = $_POST['blood_group'];
                $units_collected = $_POST['units_collected'];
                $donation_date = $_POST['donation_date'];
                $hemoglobin_level = $_POST['hemoglobin_level'];
                $blood_pressure = $_POST['blood_pressure'];
                $notes = $_POST['notes'] ?? '';
                
                try {
                    // Record donation
                    $db->query("
                        INSERT INTO blood_donations (donation_id, donor_id, blood_group, units_collected, 
                                                   donation_date, hemoglobin_level, blood_pressure, status, 
                                                   notes, collected_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'collected', ?, ?, NOW())
                    ", [$donation_id, $donor_id, $blood_group, $units_collected, $donation_date, 
                        $hemoglobin_level, $blood_pressure, $notes, $user_id]);
                    
                    // Update blood inventory
                    $existing = $db->query("SELECT * FROM blood_inventory WHERE blood_group = ?", [$blood_group])->fetch();
                    if ($existing) {
                        $db->query("
                            UPDATE blood_inventory 
                            SET units_available = units_available + ?, last_updated = NOW() 
                            WHERE blood_group = ?
                        ", [$units_collected, $blood_group]);
                    } else {
                        $db->query("
                            INSERT INTO blood_inventory (blood_group, units_available, units_reserved, 
                                                       expiry_date, last_updated) 
                            VALUES (?, ?, 0, DATE_ADD(CURDATE(), INTERVAL 35 DAY), NOW())
                        ", [$blood_group, $units_collected]);
                    }
                    
                    // Update donor's last donation date
                    $db->query("UPDATE blood_donors SET last_donation_date = ? WHERE id = ?", 
                              [$donation_date, $donor_id]);
                    
                    $success_message = "Blood donation recorded successfully! Donation ID: $donation_id";
                } catch (Exception $e) {
                    $error_message = "Error recording donation: " . $e->getMessage();
                }
            }
            break;
            
        case 'request_blood':
            $request_id = 'REQ' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
            $patient_id = $_POST['patient_id'];
            $blood_group = $_POST['blood_group'];
            $units_required = $_POST['units_required'];
            $urgency = $_POST['urgency'];
            $required_date = $_POST['required_date'];
            $reason = $_POST['reason'];
            $doctor_id = $_POST['doctor_id'] ?? null;
            
            try {
                $db->query("
                    INSERT INTO blood_requests (request_id, patient_id, blood_group, units_required, 
                                              urgency, required_date, reason, doctor_id, status, 
                                              requested_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ", [$request_id, $patient_id, $blood_group, $units_required, $urgency, 
                    $required_date, $reason, $doctor_id, $user_id]);
                
                $success_message = "Blood request submitted successfully! Request ID: $request_id";
            } catch (Exception $e) {
                $error_message = "Error submitting blood request: " . $e->getMessage();
            }
            break;
            
        case 'update_request_status':
            if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                $request_id = $_POST['request_id'];
                $status = $_POST['status'];
                $notes = $_POST['notes'] ?? '';
                
                try {
                    $db->query("
                        UPDATE blood_requests 
                        SET status = ?, notes = ?, updated_by = ?, updated_at = NOW() 
                        WHERE id = ?
                    ", [$status, $notes, $user_id, $request_id]);
                    
                    // If approved, update inventory
                    if ($status === 'approved') {
                        $request = $db->query("SELECT * FROM blood_requests WHERE id = ?", [$request_id])->fetch();
                        if ($request) {
                            $db->query("
                                UPDATE blood_inventory 
                                SET units_available = units_available - ?, 
                                    units_reserved = units_reserved + ? 
                                WHERE blood_group = ?
                            ", [$request['units_required'], $request['units_required'], $request['blood_group']]);
                        }
                    }
                    
                    $success_message = "Blood request status updated successfully!";
                } catch (Exception $e) {
                    $error_message = "Error updating request status: " . $e->getMessage();
                }
            }
            break;
    }
}

// Get statistics
try {
    $stats = [];
    
    // Total donors
    $result = $db->query("SELECT COUNT(*) as count FROM blood_donors WHERE is_active = 1")->fetch();
    $stats['total_donors'] = $result['count'];
    
    // Total donations this month
    $result = $db->query("
        SELECT COUNT(*) as count FROM blood_donations 
        WHERE MONTH(donation_date) = MONTH(CURDATE()) AND YEAR(donation_date) = YEAR(CURDATE())
    ")->fetch();
    $stats['donations_this_month'] = $result['count'];
    
    // Pending requests
    $result = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'pending'")->fetch();
    $stats['pending_requests'] = $result['count'];
    
    // Total units available
    $result = $db->query("SELECT SUM(units_available) as total FROM blood_inventory")->fetch();
    $stats['total_units'] = $result['total'] ?? 0;
    
} catch (Exception $e) {
    $stats = ['total_donors' => 0, 'donations_this_month' => 0, 'pending_requests' => 0, 'total_units' => 0];
}

// Get blood inventory
try {
    $inventory = $db->query("
        SELECT *, 
               CASE 
                   WHEN units_available < 5 THEN 'critical'
                   WHEN units_available < 10 THEN 'low'
                   ELSE 'normal'
               END as stock_status
        FROM blood_inventory 
        ORDER BY blood_group
    ")->fetchAll();
} catch (Exception $e) {
    $inventory = [];
}

// Get recent donations
try {
    $recent_donations = $db->query("
        SELECT bd.*, bdo.donor_name, bdo.phone 
        FROM blood_donations bd
        LEFT JOIN blood_donors bdo ON bd.donor_id = bdo.id
        ORDER BY bd.donation_date DESC, bd.created_at DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $recent_donations = [];
}

// Get blood requests (role-based)
try {
    if ($user_role === 'patient') {
        $patient_id = $db->query("SELECT id FROM patients WHERE email = (SELECT email FROM users WHERE id = ?)", [$user_id])->fetch()['id'];
        $blood_requests = $db->query("
            SELECT br.*, p.first_name, p.last_name, d.doctor_name 
            FROM blood_requests br
            LEFT JOIN patients p ON br.patient_id = p.id
            LEFT JOIN doctors d ON br.doctor_id = d.id
            WHERE br.patient_id = ?
            ORDER BY br.created_at DESC
        ", [$patient_id])->fetchAll();
    } else {
        $blood_requests = $db->query("
            SELECT br.*, p.first_name, p.last_name, d.doctor_name 
            FROM blood_requests br
            LEFT JOIN patients p ON br.patient_id = p.id
            LEFT JOIN doctors d ON br.doctor_id = d.id
            ORDER BY br.created_at DESC
            LIMIT 20
        ")->fetchAll();
    }
} catch (Exception $e) {
    $blood_requests = [];
}

// Get donors
try {
    $donors = $db->query("
        SELECT * FROM blood_donors 
        WHERE is_active = 1 
        ORDER BY created_at DESC 
        LIMIT 20
    ")->fetchAll();
} catch (Exception $e) {
    $donors = [];
}

// Get patients for dropdowns
try {
    $patients = $db->query("SELECT id, patient_id, first_name, last_name FROM patients WHERE is_active = 1 ORDER BY first_name")->fetchAll();
} catch (Exception $e) {
    $patients = [];
}

// Get doctors for dropdowns
try {
    $doctors = $db->query("SELECT id, doctor_name FROM doctors WHERE is_active = 1 ORDER BY doctor_name")->fetchAll();
} catch (Exception $e) {
    $doctors = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Management - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-tint"></i> Blood Bank</h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])): ?>
                    <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                    <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                    <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                    <li><a href="insurance.php"><i class="fas fa-shield-alt"></i> Insurance</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                    <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                    <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'pharmacy_staff'])): ?>
                    <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <?php endif; ?>
                
                <li><a href="blood-bank.php" class="active"><i class="fas fa-tint"></i> Blood Bank</a></li>
                <li><a href="organ-donation.php"><i class="fas fa-heart"></i> Organ Donation</a></li>
                
                <?php if ($user_role === 'patient'): ?>
                    <li><a href="patient-portal.php"><i class="fas fa-user-circle"></i> My Portal</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin'])): ?>
                    <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                    <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <?php endif; ?>
                
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1><i class="fas fa-tint"></i> Blood Bank Management</h1>
                    <p>Manage blood donations, inventory, and requests</p>
                </div>
                
                <?php if (in_array($user_role, ['admin', 'nurse', 'receptionist'])): ?>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="showRegisterDonorModal()">
                        <i class="fas fa-plus"></i> Register Donor
                    </button>
                    <button class="btn btn-success" onclick="showRecordDonationModal()">
                        <i class="fas fa-tint"></i> Record Donation
                    </button>
                </div>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['patient', 'doctor'])): ?>
                <div class="header-actions">
                    <button class="btn btn-danger" onclick="showRequestBloodModal()">
                        <i class="fas fa-hand-holding-medical"></i> Request Blood
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Alert Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_donors']; ?></h3>
                        <p>Total Donors</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['donations_this_month']; ?></h3>
                        <p>Donations This Month</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_requests']; ?></h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_units']; ?></h3>
                        <p>Units Available</p>
                    </div>
                </div>
            </div>

            <!-- Blood Inventory -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-warehouse"></i> Blood Inventory</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($inventory)): ?>
                        <p class="text-muted text-center">No blood inventory data available.</p>
                    <?php else: ?>
                        <div class="inventory-grid">
                            <?php foreach ($inventory as $item): ?>
                                <div class="inventory-card <?php echo $item['stock_status']; ?>">
                                    <div class="blood-group"><?php echo htmlspecialchars($item['blood_group']); ?></div>
                                    <div class="units-info">
                                        <div class="available"><?php echo $item['units_available']; ?> Units</div>
                                        <div class="reserved">Reserved: <?php echo $item['units_reserved']; ?></div>
                                    </div>
                                    <div class="status-badge">
                                        <?php if ($item['stock_status'] === 'critical'): ?>
                                            <span class="badge badge-danger">Critical</span>
                                        <?php elseif ($item['stock_status'] === 'low'): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Normal</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="expiry-info">
                                        <small>Expires: <?php echo date('d M Y', strtotime($item['expiry_date'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Blood Requests -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-hand-holding-medical"></i> 
                        <?php echo $user_role === 'patient' ? 'My Blood Requests' : 'Blood Requests'; ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($blood_requests)): ?>
                        <p class="text-muted text-center">No blood requests found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <?php if ($user_role !== 'patient'): ?>
                                            <th>Patient</th>
                                        <?php endif; ?>
                                        <th>Blood Group</th>
                                        <th>Units</th>
                                        <th>Urgency</th>
                                        <th>Required Date</th>
                                        <th>Status</th>
                                        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($blood_requests as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                            <?php if ($user_role !== 'patient'): ?>
                                                <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                            <?php endif; ?>
                                            <td><span class="blood-group-badge"><?php echo htmlspecialchars($request['blood_group']); ?></span></td>
                                            <td><?php echo $request['units_required']; ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $request['urgency'] === 'urgent' ? 'danger' : ($request['urgency'] === 'high' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst($request['urgency']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($request['required_date'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $request['status'] === 'approved' ? 'success' : ($request['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </td>
                                            <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                                                <td>
                                                    <?php if ($request['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-success" onclick="updateRequestStatus(<?php echo $request['id']; ?>, 'approved')">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="updateRequestStatus(<?php echo $request['id']; ?>, 'rejected')">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
            <!-- Recent Donations -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Donations</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_donations)): ?>
                        <p class="text-muted text-center">No donations recorded yet.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Donation ID</th>
                                        <th>Donor</th>
                                        <th>Blood Group</th>
                                        <th>Units</th>
                                        <th>Date</th>
                                        <th>Hemoglobin</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_donations as $donation): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($donation['donation_id']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($donation['donor_name']); ?><br>
                                                <small><?php echo htmlspecialchars($donation['phone']); ?></small>
                                            </td>
                                            <td><span class="blood-group-badge"><?php echo htmlspecialchars($donation['blood_group']); ?></span></td>
                                            <td><?php echo $donation['units_collected']; ?></td>
                                            <td><?php echo date('d M Y', strtotime($donation['donation_date'])); ?></td>
                                            <td><?php echo $donation['hemoglobin_level']; ?> g/dL</td>
                                            <td>
                                                <span class="badge badge-<?php echo $donation['status'] === 'collected' ? 'success' : 'warning'; ?>">
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
            <?php endif; ?>
        </main>
    </div>

    <!-- Modals -->
    <?php if (in_array($user_role, ['admin', 'nurse', 'receptionist'])): ?>
    <!-- Register Donor Modal -->
    <div id="registerDonorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Register Blood Donor</h2>
                <span class="close" onclick="hideRegisterDonorModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="register_donor">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="patient_id">Patient (Optional)</label>
                            <select name="patient_id" class="form-control">
                                <option value="">Select Patient (if existing)</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="donor_name">Donor Name *</label>
                            <input type="text" name="donor_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="blood_group">Blood Group *</label>
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
                            <label for="phone">Phone *</label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth *</label>
                            <input type="date" name="date_of_birth" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <select name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <textarea name="address" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="medical_history">Medical History</label>
                        <textarea name="medical_history" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="last_donation">Last Donation Date (if any)</label>
                        <input type="date" name="last_donation" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideRegisterDonorModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register Donor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Record Donation Modal -->
    <div id="recordDonationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-tint"></i> Record Blood Donation</h2>
                <span class="close" onclick="hideRecordDonationModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="record_donation">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="donor_id">Donor *</label>
                            <select name="donor_id" class="form-control" required>
                                <option value="">Select Donor</option>
                                <?php foreach ($donors as $donor): ?>
                                    <option value="<?php echo $donor['id']; ?>">
                                        <?php echo htmlspecialchars($donor['donor_id'] . ' - ' . $donor['donor_name'] . ' (' . $donor['blood_group'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="blood_group">Blood Group *</label>
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
                            <label for="units_collected">Units Collected *</label>
                            <input type="number" name="units_collected" class="form-control" min="1" max="10" required>
                        </div>
                        <div class="form-group">
                            <label for="donation_date">Donation Date *</label>
                            <input type="date" name="donation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="hemoglobin_level">Hemoglobin Level (g/dL) *</label>
                            <input type="number" name="hemoglobin_level" class="form-control" step="0.1" min="10" max="20" required>
                        </div>
                        <div class="form-group">
                            <label for="blood_pressure">Blood Pressure *</label>
                            <input type="text" name="blood_pressure" class="form-control" placeholder="120/80" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideRecordDonationModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Donation</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($user_role, ['patient', 'doctor', 'admin'])): ?>
    <!-- Request Blood Modal -->
    <div id="requestBloodModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-hand-holding-medical"></i> Request Blood</h2>
                <span class="close" onclick="hideRequestBloodModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="request_blood">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="patient_id">Patient *</label>
                            <select name="patient_id" class="form-control" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="blood_group">Blood Group *</label>
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
                            <label for="units_required">Units Required *</label>
                            <input type="number" name="units_required" class="form-control" min="1" max="10" required>
                        </div>
                        <div class="form-group">
                            <label for="urgency">Urgency *</label>
                            <select name="urgency" class="form-control" required>
                                <option value="">Select Urgency</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="required_date">Required Date *</label>
                            <input type="date" name="required_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="doctor_id">Doctor</label>
                            <select name="doctor_id" class="form-control">
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="reason">Reason for Request *</label>
                        <textarea name="reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideRequestBloodModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Modal functions
        function showRegisterDonorModal() {
            document.getElementById('registerDonorModal').style.display = 'block';
        }

        function hideRegisterDonorModal() {
            document.getElementById('registerDonorModal').style.display = 'none';
        }

        function showRecordDonationModal() {
            document.getElementById('recordDonationModal').style.display = 'block';
        }

        function hideRecordDonationModal() {
            document.getElementById('recordDonationModal').style.display = 'none';
        }

        function showRequestBloodModal() {
            document.getElementById('requestBloodModal').style.display = 'block';
        }

        function hideRequestBloodModal() {
            document.getElementById('requestBloodModal').style.display = 'none';
        }

        function updateRequestStatus(requestId, status) {
            if (confirm('Are you sure you want to ' + status + ' this blood request?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_request_status">
                    <input type="hidden" name="request_id" value="${requestId}">
                    <input type="hidden" name="status" value="${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['registerDonorModal', 'recordDonationModal', 'requestBloodModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>

    <style>
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .inventory-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .inventory-card.critical {
            border-color: #dc3545;
            background: #fff5f5;
        }

        .inventory-card.low {
            border-color: #ffc107;
            background: #fffbf0;
        }

        .inventory-card.normal {
            border-color: #28a745;
            background: #f8fff8;
        }

        .blood-group {
            font-size: 2em;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 10px;
        }

        .units-info {
            margin-bottom: 15px;
        }

        .available {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
        }

        .reserved {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        .status-badge {
            margin-bottom: 10px;
        }

        .expiry-info {
            color: #666;
        }

        .blood-group-badge {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .inventory-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
            }
        }
    </style>
</body>
</html>
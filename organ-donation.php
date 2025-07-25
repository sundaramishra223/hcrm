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
            if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist', 'patient'])) {
                $donor_id = 'OD' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
                $patient_id = $_POST['patient_id'] ?? null;
                $donor_name = $_POST['donor_name'];
                $phone = $_POST['phone'];
                $email = $_POST['email'] ?? null;
                $date_of_birth = $_POST['date_of_birth'];
                $gender = $_POST['gender'];
                $blood_group = $_POST['blood_group'];
                $address = $_POST['address'];
                $organs_to_donate = $_POST['organs_to_donate'] ?? [];
                $medical_history = $_POST['medical_history'] ?? '';
                $consent_type = $_POST['consent_type'];
                $emergency_contact = $_POST['emergency_contact'];
                $emergency_phone = $_POST['emergency_phone'];
                
                try {
                    $db->query("
                        INSERT INTO organ_donors (donor_id, patient_id, donor_name, phone, email, date_of_birth, 
                                                gender, blood_group, address, organs_to_donate, medical_history, 
                                                consent_type, emergency_contact_name, emergency_contact_phone, 
                                                status, is_active, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'registered', 1, ?, NOW())
                    ", [$donor_id, $patient_id, $donor_name, $phone, $email, $date_of_birth, $gender, 
                        $blood_group, $address, implode(',', $organs_to_donate), $medical_history, 
                        $consent_type, $emergency_contact, $emergency_phone, $user_id]);
                    
                    $success_message = "Organ donor registered successfully! Donor ID: $donor_id";
                } catch (Exception $e) {
                    $error_message = "Error registering donor: " . $e->getMessage();
                }
            }
            break;
            
        case 'record_donation':
            if (in_array($user_role, ['admin', 'doctor'])) {
                $donation_id = 'ODON' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
                $donor_id = $_POST['donor_id'];
                $organ_type = $_POST['organ_type'];
                $donation_date = $_POST['donation_date'];
                $donation_time = $_POST['donation_time'];
                $hospital_name = $_POST['hospital_name'];
                $surgeon_name = $_POST['surgeon_name'];
                $organ_condition = $_POST['organ_condition'];
                $preservation_method = $_POST['preservation_method'];
                $notes = $_POST['notes'] ?? '';
                
                try {
                    // Record donation
                    $db->query("
                        INSERT INTO organ_donations (donation_id, donor_id, organ_type, donation_date, donation_time, 
                                                   hospital_name, surgeon_name, organ_condition, preservation_method, 
                                                   status, notes, recorded_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'harvested', ?, ?, NOW())
                    ", [$donation_id, $donor_id, $organ_type, $donation_date, $donation_time, 
                        $hospital_name, $surgeon_name, $organ_condition, $preservation_method, $notes, $user_id]);
                    
                    // Update organ inventory
                    $existing = $db->query("SELECT * FROM organ_inventory WHERE organ_type = ?", [$organ_type])->fetch();
                    if ($existing) {
                        $db->query("
                            UPDATE organ_inventory 
                            SET organs_available = organs_available + 1, last_updated = NOW() 
                            WHERE organ_type = ?
                        ", [$organ_type]);
                    } else {
                        $db->query("
                            INSERT INTO organ_inventory (organ_type, organs_available, organs_allocated, last_updated) 
                            VALUES (?, 1, 0, NOW())
                        ", [$organ_type]);
                    }
                    
                    $success_message = "Organ donation recorded successfully! Donation ID: $donation_id";
                } catch (Exception $e) {
                    $error_message = "Error recording donation: " . $e->getMessage();
                }
            }
            break;
            
        case 'request_organ':
            $request_id = 'OREQ' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
            $patient_id = $_POST['patient_id'];
            $organ_type = $_POST['organ_type'];
            $blood_group = $_POST['blood_group'];
            $urgency = $_POST['urgency'];
            $medical_condition = $_POST['medical_condition'];
            $doctor_id = $_POST['doctor_id'] ?? null;
            $required_date = $_POST['required_date'];
            $medical_notes = $_POST['medical_notes'] ?? '';
            
            try {
                $db->query("
                    INSERT INTO organ_requests (request_id, patient_id, organ_type, blood_group, urgency, 
                                              medical_condition, doctor_id, required_date, medical_notes, 
                                              status, requested_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ", [$request_id, $patient_id, $organ_type, $blood_group, $urgency, $medical_condition, 
                    $doctor_id, $required_date, $medical_notes, $user_id]);
                
                $success_message = "Organ request submitted successfully! Request ID: $request_id";
            } catch (Exception $e) {
                $error_message = "Error submitting organ request: " . $e->getMessage();
            }
            break;
            
        case 'update_request_status':
            if (in_array($user_role, ['admin', 'doctor'])) {
                $request_id = $_POST['request_id'];
                $status = $_POST['status'];
                $notes = $_POST['notes'] ?? '';
                
                try {
                    $db->query("
                        UPDATE organ_requests 
                        SET status = ?, notes = ?, updated_by = ?, updated_at = NOW() 
                        WHERE id = ?
                    ", [$status, $notes, $user_id, $request_id]);
                    
                    // If approved, update inventory
                    if ($status === 'approved') {
                        $request = $db->query("SELECT * FROM organ_requests WHERE id = ?", [$request_id])->fetch();
                        if ($request) {
                            $db->query("
                                UPDATE organ_inventory 
                                SET organs_available = organs_available - 1, 
                                    organs_allocated = organs_allocated + 1 
                                WHERE organ_type = ?
                            ", [$request['organ_type']]);
                        }
                    }
                    
                    $success_message = "Organ request status updated successfully!";
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
    $result = $db->query("SELECT COUNT(*) as count FROM organ_donors WHERE is_active = 1")->fetch();
    $stats['total_donors'] = $result['count'];
    
    // Total donations this month
    $result = $db->query("
        SELECT COUNT(*) as count FROM organ_donations 
        WHERE MONTH(donation_date) = MONTH(CURDATE()) AND YEAR(donation_date) = YEAR(CURDATE())
    ")->fetch();
    $stats['donations_this_month'] = $result['count'];
    
    // Pending requests
    $result = $db->query("SELECT COUNT(*) as count FROM organ_requests WHERE status = 'pending'")->fetch();
    $stats['pending_requests'] = $result['count'];
    
    // Total organs available
    $result = $db->query("SELECT SUM(organs_available) as total FROM organ_inventory")->fetch();
    $stats['total_organs'] = $result['total'] ?? 0;
    
} catch (Exception $e) {
    $stats = ['total_donors' => 0, 'donations_this_month' => 0, 'pending_requests' => 0, 'total_organs' => 0];
}

// Get organ inventory
try {
    $inventory = $db->query("
        SELECT *, 
               CASE 
                   WHEN organs_available = 0 THEN 'critical'
                   WHEN organs_available < 3 THEN 'low'
                   ELSE 'normal'
               END as stock_status
        FROM organ_inventory 
        ORDER BY organ_type
    ")->fetchAll();
} catch (Exception $e) {
    $inventory = [];
}

// Get recent donations
try {
    $recent_donations = $db->query("
        SELECT od.*, odo.donor_name, odo.phone 
        FROM organ_donations od
        LEFT JOIN organ_donors odo ON od.donor_id = odo.id
        ORDER BY od.donation_date DESC, od.created_at DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $recent_donations = [];
}

// Get organ requests (role-based)
try {
    if ($user_role === 'patient') {
        $patient_id = $db->query("SELECT id FROM patients WHERE email = (SELECT email FROM users WHERE id = ?)", [$user_id])->fetch()['id'];
        $organ_requests = $db->query("
            SELECT or_req.*, p.first_name, p.last_name, d.doctor_name 
            FROM organ_requests or_req
            LEFT JOIN patients p ON or_req.patient_id = p.id
            LEFT JOIN doctors d ON or_req.doctor_id = d.id
            WHERE or_req.patient_id = ?
            ORDER BY or_req.created_at DESC
        ", [$patient_id])->fetchAll();
    } else {
        $organ_requests = $db->query("
            SELECT or_req.*, p.first_name, p.last_name, d.doctor_name 
            FROM organ_requests or_req
            LEFT JOIN patients p ON or_req.patient_id = p.id
            LEFT JOIN doctors d ON or_req.doctor_id = d.id
            ORDER BY or_req.created_at DESC
            LIMIT 20
        ")->fetchAll();
    }
} catch (Exception $e) {
    $organ_requests = [];
}

// Get donors
try {
    $donors = $db->query("
        SELECT * FROM organ_donors 
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

// Organ types
$organ_types = [
    'heart' => 'Heart',
    'liver' => 'Liver', 
    'kidney' => 'Kidney',
    'lung' => 'Lung',
    'pancreas' => 'Pancreas',
    'cornea' => 'Cornea',
    'skin' => 'Skin',
    'bone' => 'Bone',
    'heart_valve' => 'Heart Valve',
    'blood_vessel' => 'Blood Vessel'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organ Donation Management - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-heart"></i> Organ Donation</h2>
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
                
                <li><a href="blood-bank.php"><i class="fas fa-tint"></i> Blood Bank</a></li>
                <li><a href="organ-donation.php" class="active"><i class="fas fa-heart"></i> Organ Donation</a></li>
                
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
                    <h1><i class="fas fa-heart"></i> Organ Donation Management</h1>
                    <p>Manage organ donations, inventory, and transplant requests</p>
                </div>
                
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="showRegisterDonorModal()">
                        <i class="fas fa-plus"></i> Register Donor
                    </button>
                    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                        <button class="btn btn-success" onclick="showRecordDonationModal()">
                            <i class="fas fa-heart"></i> Record Donation
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-danger" onclick="showRequestOrganModal()">
                        <i class="fas fa-hand-holding-medical"></i> Request Organ
                    </button>
                </div>
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
                        <i class="fas fa-heart"></i>
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
                        <h3><?php echo $stats['total_organs']; ?></h3>
                        <p>Organs Available</p>
                    </div>
                </div>
            </div>

            <!-- Organ Inventory -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-warehouse"></i> Organ Inventory</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($inventory)): ?>
                        <p class="text-muted text-center">No organ inventory data available.</p>
                    <?php else: ?>
                        <div class="inventory-grid">
                            <?php foreach ($inventory as $item): ?>
                                <div class="inventory-card <?php echo $item['stock_status']; ?>">
                                    <div class="organ-type">
                                        <i class="fas fa-heart"></i>
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $item['organ_type']))); ?>
                                    </div>
                                    <div class="organs-info">
                                        <div class="available"><?php echo $item['organs_available']; ?> Available</div>
                                        <div class="allocated">Allocated: <?php echo $item['organs_allocated']; ?></div>
                                    </div>
                                    <div class="status-badge">
                                        <?php if ($item['stock_status'] === 'critical'): ?>
                                            <span class="badge badge-danger">Critical</span>
                                        <?php elseif ($item['stock_status'] === 'low'): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Available</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="update-info">
                                        <small>Updated: <?php echo date('d M Y', strtotime($item['last_updated'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Organ Requests -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-hand-holding-medical"></i> 
                        <?php echo $user_role === 'patient' ? 'My Organ Requests' : 'Organ Requests'; ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($organ_requests)): ?>
                        <p class="text-muted text-center">No organ requests found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <?php if ($user_role !== 'patient'): ?>
                                            <th>Patient</th>
                                        <?php endif; ?>
                                        <th>Organ Type</th>
                                        <th>Blood Group</th>
                                        <th>Urgency</th>
                                        <th>Required Date</th>
                                        <th>Status</th>
                                        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($organ_requests as $request): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                            <?php if ($user_role !== 'patient'): ?>
                                                <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="organ-badge">
                                                    <i class="fas fa-heart"></i>
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $request['organ_type']))); ?>
                                                </span>
                                            </td>
                                            <td><span class="blood-group-badge"><?php echo htmlspecialchars($request['blood_group']); ?></span></td>
                                            <td>
                                                <span class="badge badge-<?php echo $request['urgency'] === 'critical' ? 'danger' : ($request['urgency'] === 'high' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst($request['urgency']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($request['required_date'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $request['status'] === 'approved' ? 'success' : ($request['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </td>
                                            <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
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
                                        <th>Organ Type</th>
                                        <th>Date</th>
                                        <th>Hospital</th>
                                        <th>Condition</th>
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
                                            <td>
                                                <span class="organ-badge">
                                                    <i class="fas fa-heart"></i>
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $donation['organ_type']))); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($donation['donation_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($donation['hospital_name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $donation['organ_condition'] === 'excellent' ? 'success' : ($donation['organ_condition'] === 'good' ? 'info' : 'warning'); ?>">
                                                    <?php echo ucfirst($donation['organ_condition']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $donation['status'] === 'harvested' ? 'success' : 'warning'; ?>">
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
    <!-- Register Donor Modal -->
    <div id="registerDonorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Register Organ Donor</h2>
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
                            <label for="phone">Phone *</label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth *</label>
                            <input type="date" name="date_of_birth" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <select name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
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
                    <div class="form-group">
                        <label for="address">Address *</label>
                        <textarea name="address" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="organs_to_donate">Organs to Donate *</label>
                        <div class="checkbox-grid">
                            <?php foreach ($organ_types as $key => $value): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="organs_to_donate[]" value="<?php echo $key; ?>">
                                    <?php echo $value; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="consent_type">Consent Type *</label>
                        <select name="consent_type" class="form-control" required>
                            <option value="">Select Consent Type</option>
                            <option value="living_donor">Living Donor</option>
                            <option value="deceased_donor">Deceased Donor</option>
                            <option value="family_consent">Family Consent</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact Name *</label>
                            <input type="text" name="emergency_contact" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="emergency_phone">Emergency Contact Phone *</label>
                            <input type="tel" name="emergency_phone" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="medical_history">Medical History</label>
                        <textarea name="medical_history" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideRegisterDonorModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register Donor</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
    <!-- Record Donation Modal -->
    <div id="recordDonationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-heart"></i> Record Organ Donation</h2>
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
                                        <?php echo htmlspecialchars($donor['donor_id'] . ' - ' . $donor['donor_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="organ_type">Organ Type *</label>
                            <select name="organ_type" class="form-control" required>
                                <option value="">Select Organ Type</option>
                                <?php foreach ($organ_types as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="donation_date">Donation Date *</label>
                            <input type="date" name="donation_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="donation_time">Donation Time *</label>
                            <input type="time" name="donation_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="hospital_name">Hospital Name *</label>
                            <input type="text" name="hospital_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="surgeon_name">Surgeon Name *</label>
                            <input type="text" name="surgeon_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="organ_condition">Organ Condition *</label>
                            <select name="organ_condition" class="form-control" required>
                                <option value="">Select Condition</option>
                                <option value="excellent">Excellent</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="preservation_method">Preservation Method *</label>
                            <select name="preservation_method" class="form-control" required>
                                <option value="">Select Method</option>
                                <option value="cold_storage">Cold Storage</option>
                                <option value="machine_perfusion">Machine Perfusion</option>
                                <option value="hypothermic_storage">Hypothermic Storage</option>
                            </select>
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

    <!-- Request Organ Modal -->
    <div id="requestOrganModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-hand-holding-medical"></i> Request Organ</h2>
                <span class="close" onclick="hideRequestOrganModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="request_organ">
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
                            <label for="organ_type">Organ Type *</label>
                            <select name="organ_type" class="form-control" required>
                                <option value="">Select Organ Type</option>
                                <?php foreach ($organ_types as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
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
                        <div class="form-group">
                            <label for="urgency">Urgency *</label>
                            <select name="urgency" class="form-control" required>
                                <option value="">Select Urgency</option>
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
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
                        <label for="medical_condition">Medical Condition *</label>
                        <textarea name="medical_condition" class="form-control" rows="3" required placeholder="Describe the medical condition requiring organ transplant"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="medical_notes">Medical Notes</label>
                        <textarea name="medical_notes" class="form-control" rows="2" placeholder="Additional medical information"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideRequestOrganModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

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

        function showRequestOrganModal() {
            document.getElementById('requestOrganModal').style.display = 'block';
        }

        function hideRequestOrganModal() {
            document.getElementById('requestOrganModal').style.display = 'none';
        }

        function updateRequestStatus(requestId, status) {
            if (confirm('Are you sure you want to ' + status + ' this organ request?')) {
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
            const modals = ['registerDonorModal', 'recordDonationModal', 'requestOrganModal'];
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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

        .organ-type {
            font-size: 1.2em;
            font-weight: bold;
            color: #e91e63;
            margin-bottom: 10px;
        }

        .organ-type i {
            margin-right: 8px;
        }

        .organs-info {
            margin-bottom: 15px;
        }

        .available {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
        }

        .allocated {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        .status-badge {
            margin-bottom: 10px;
        }

        .update-info {
            color: #666;
        }

        .organ-badge {
            background: #e91e63;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .blood-group-badge {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .checkbox-grid {
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
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .checkbox-item:hover {
            background-color: #f8f9fa;
        }

        .checkbox-item input[type="checkbox"] {
            margin: 0;
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
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 15px;
            }

            .checkbox-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
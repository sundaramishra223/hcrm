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
            case 'register_donor':
                if (in_array($user_role, ['admin', 'doctor', 'nurse', 'patient'])) {
                    $patient_id = $user_role === 'patient' ? 
                        $db->prepare("SELECT id FROM patients WHERE user_id = ?")->execute([$user_id])->fetch()['id'] : 
                        $_POST['patient_id'];
                    
                    $organs = $_POST['organs'] ?? [];
                    $medical_history = $_POST['medical_history'];
                    $emergency_contact = $_POST['emergency_contact'];
                    $emergency_phone = $_POST['emergency_phone'];
                    $consent_date = $_POST['consent_date'];
                    $notes = $_POST['notes'];
                    
                    // Insert donor registration
                    $stmt = $db->prepare("INSERT INTO organ_donors (patient_id, medical_history, emergency_contact_name, emergency_contact_phone, consent_date, notes, status, registered_by) VALUES (?, ?, ?, ?, ?, ?, 'active', ?)");
                    $stmt->execute([$patient_id, $medical_history, $emergency_contact, $emergency_phone, $consent_date, $notes, $user_id]);
                    
                    $donor_id = $db->lastInsertId();
                    
                    // Insert organ consents
                    foreach ($organs as $organ) {
                        $stmt = $db->prepare("INSERT INTO organ_consents (donor_id, organ_type, consent_status) VALUES (?, ?, 'active')");
                        $stmt->execute([$donor_id, $organ]);
                    }
                    
                    $_SESSION['success'] = "Organ donor registration completed successfully!";
                }
                break;
                
            case 'add_recipient':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $patient_id = $_POST['patient_id'];
                    $organ_needed = $_POST['organ_needed'];
                    $priority_level = $_POST['priority_level'];
                    $blood_type = $_POST['blood_type'];
                    $medical_condition = $_POST['medical_condition'];
                    $doctor_notes = $_POST['doctor_notes'];
                    $required_by = $_POST['required_by'];
                    
                    $stmt = $db->prepare("INSERT INTO organ_recipients (patient_id, organ_needed, priority_level, blood_type, medical_condition, doctor_notes, required_by_date, status, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'waiting', ?)");
                    $stmt->execute([$patient_id, $organ_needed, $priority_level, $blood_type, $medical_condition, $doctor_notes, $required_by, $user_id]);
                    
                    $_SESSION['success'] = "Recipient added to waiting list successfully!";
                }
                break;
                
            case 'update_recipient_status':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $recipient_id = $_POST['recipient_id'];
                    $status = $_POST['status'];
                    $notes = $_POST['notes'];
                    
                    $stmt = $db->prepare("UPDATE organ_recipients SET status = ?, updated_notes = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $notes, $recipient_id]);
                    
                    $_SESSION['success'] = "Recipient status updated successfully!";
                }
                break;
                
            case 'record_transplant':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $donor_id = $_POST['donor_id'];
                    $recipient_id = $_POST['recipient_id'];
                    $organ_type = $_POST['organ_type'];
                    $transplant_date = $_POST['transplant_date'];
                    $surgeon_id = $_POST['surgeon_id'];
                    $hospital = $_POST['hospital'];
                    $surgery_notes = $_POST['surgery_notes'];
                    
                    $stmt = $db->prepare("INSERT INTO organ_transplants (donor_id, recipient_id, organ_type, transplant_date, surgeon_id, hospital, surgery_notes, status, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)");
                    $stmt->execute([$donor_id, $recipient_id, $organ_type, $transplant_date, $surgeon_id, $hospital, $surgery_notes, $user_id]);
                    
                    // Update recipient status
                    $stmt = $db->prepare("UPDATE organ_recipients SET status = 'transplanted', transplant_date = ? WHERE id = ?");
                    $stmt->execute([$transplant_date, $recipient_id]);
                    
                    // Update donor organ status
                    $stmt = $db->prepare("UPDATE organ_consents SET consent_status = 'used' WHERE donor_id = ? AND organ_type = ?");
                    $stmt->execute([$donor_id, $organ_type]);
                    
                    $_SESSION['success'] = "Transplant recorded successfully!";
                }
                break;
        }
        header('Location: organ-donation.php');
        exit;
    }
}

// Get statistics
$stats = [];

// Total donors
$stmt = $db->prepare("SELECT COUNT(*) as total FROM organ_donors WHERE status = 'active'");
$stmt->execute();
$stats['total_donors'] = $stmt->fetch()['total'];

// Waiting recipients
$stmt = $db->prepare("SELECT COUNT(*) as waiting FROM organ_recipients WHERE status = 'waiting'");
$stmt->execute();
$stats['waiting_recipients'] = $stmt->fetch()['waiting'];

// Successful transplants
$stmt = $db->prepare("SELECT COUNT(*) as completed FROM organ_transplants WHERE status = 'completed'");
$stmt->execute();
$stats['completed_transplants'] = $stmt->fetch()['completed'];

// Available organs
$stmt = $db->prepare("SELECT COUNT(*) as available FROM organ_consents WHERE consent_status = 'active'");
$stmt->execute();
$stats['available_organs'] = $stmt->fetch()['available'];

// Get organ availability by type
$stmt = $db->prepare("
    SELECT organ_type, COUNT(*) as count 
    FROM organ_consents 
    WHERE consent_status = 'active' 
    GROUP BY organ_type
");
$stmt->execute();
$organ_availability = $stmt->fetchAll();

// Get donors
if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
    $stmt = $db->prepare("
        SELECT od.*, p.first_name, p.last_name, p.patient_id, p.blood_type,
               GROUP_CONCAT(oc.organ_type) as organs
        FROM organ_donors od 
        JOIN patients p ON od.patient_id = p.id 
        LEFT JOIN organ_consents oc ON od.id = oc.donor_id AND oc.consent_status = 'active'
        WHERE od.status = 'active'
        GROUP BY od.id
        ORDER BY od.consent_date DESC
    ");
    $stmt->execute();
    $donors = $stmt->fetchAll();
} else {
    // Patient can only see their own donor registration
    $stmt = $db->prepare("
        SELECT od.*, p.first_name, p.last_name, p.patient_id, p.blood_type,
               GROUP_CONCAT(oc.organ_type) as organs
        FROM organ_donors od 
        JOIN patients p ON od.patient_id = p.id 
        LEFT JOIN organ_consents oc ON od.id = oc.donor_id AND oc.consent_status = 'active'
        WHERE od.status = 'active' AND p.user_id = ?
        GROUP BY od.id
        ORDER BY od.consent_date DESC
    ");
    $stmt->execute([$user_id]);
    $donors = $stmt->fetchAll();
}

// Get recipients
if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
    $stmt = $db->prepare("
        SELECT or_table.*, p.first_name, p.last_name, p.patient_id, d.doctor_name
        FROM organ_recipients or_table
        JOIN patients p ON or_table.patient_id = p.id 
        LEFT JOIN doctors d ON or_table.added_by = d.user_id
        ORDER BY or_table.priority_level DESC, or_table.created_at ASC
    ");
    $stmt->execute();
    $recipients = $stmt->fetchAll();
} else {
    // Patient can only see their own recipient records
    $stmt = $db->prepare("
        SELECT or_table.*, p.first_name, p.last_name, p.patient_id, d.doctor_name
        FROM organ_recipients or_table
        JOIN patients p ON or_table.patient_id = p.id 
        LEFT JOIN doctors d ON or_table.added_by = d.user_id
        WHERE p.user_id = ?
        ORDER BY or_table.priority_level DESC, or_table.created_at ASC
    ");
    $stmt->execute([$user_id]);
    $recipients = $stmt->fetchAll();
}

// Get recent transplants
$stmt = $db->prepare("
    SELECT ot.*, 
           pd.first_name as donor_fname, pd.last_name as donor_lname,
           pr.first_name as recipient_fname, pr.last_name as recipient_lname,
           d.doctor_name as surgeon_name
    FROM organ_transplants ot
    JOIN organ_donors od ON ot.donor_id = od.id
    JOIN patients pd ON od.patient_id = pd.id
    JOIN organ_recipients ore ON ot.recipient_id = ore.id
    JOIN patients pr ON ore.patient_id = pr.id
    LEFT JOIN doctors d ON ot.surgeon_id = d.id
    ORDER BY ot.transplant_date DESC
    LIMIT 10
");
$stmt->execute();
$recent_transplants = $stmt->fetchAll();

// Get patients and doctors for dropdowns
if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
    $stmt = $db->prepare("SELECT id, first_name, last_name, patient_id, blood_type FROM patients ORDER BY first_name, last_name");
    $stmt->execute();
    $patients = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT id, doctor_name, specialization FROM doctors ORDER BY doctor_name");
    $stmt->execute();
    $doctors = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organ Donation Management - Hospital CRM</title>
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
                    <h1><i class="fas fa-heart"></i> Organ Donation Management</h1>
                    <div class="page-actions">
                        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'patient'])): ?>
                            <button class="btn btn-primary" onclick="openModal('registerDonorModal')">
                                <i class="fas fa-plus"></i> Register as Donor
                            </button>
                        <?php endif; ?>
                        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                            <button class="btn btn-warning" onclick="openModal('addRecipientModal')">
                                <i class="fas fa-user-plus"></i> Add Recipient
                            </button>
                            <button class="btn btn-success" onclick="openModal('recordTransplantModal')">
                                <i class="fas fa-heart-pulse"></i> Record Transplant
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
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_donors']; ?></h3>
                            <p>Active Donors</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['waiting_recipients']; ?></h3>
                            <p>Waiting Recipients</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-heart-pulse"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['completed_transplants']; ?></h3>
                            <p>Successful Transplants</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['available_organs']; ?></h3>
                            <p>Available Organs</p>
                        </div>
                    </div>
                </div>

                <!-- Organ Availability -->
                <div class="content-section">
                    <h2><i class="fas fa-chart-pie"></i> Organ Availability</h2>
                    <div class="organ-availability-grid">
                        <?php 
                        $organ_types = ['Heart', 'Liver', 'Kidney', 'Lung', 'Pancreas', 'Cornea', 'Skin', 'Bone'];
                        foreach ($organ_types as $type): 
                            $count = 0;
                            foreach ($organ_availability as $avail) {
                                if ($avail['organ_type'] === $type) {
                                    $count = $avail['count'];
                                    break;
                                }
                            }
                        ?>
                            <div class="organ-card">
                                <div class="organ-icon">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div class="organ-info">
                                    <h3><?php echo $type; ?></h3>
                                    <p><strong><?php echo $count; ?></strong> Available</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recipients Waiting List -->
                <div class="content-section">
                    <h2><i class="fas fa-list"></i> Recipients Waiting List</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Organ Needed</th>
                                    <th>Blood Type</th>
                                    <th>Priority</th>
                                    <th>Medical Condition</th>
                                    <th>Required By</th>
                                    <th>Status</th>
                                    <th>Wait Time</th>
                                    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                                        <th>Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recipients as $recipient): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?></td>
                                        <td><span class="organ-badge"><?php echo $recipient['organ_needed']; ?></span></td>
                                        <td><span class="blood-type-badge"><?php echo $recipient['blood_type']; ?></span></td>
                                        <td>
                                            <span class="priority-badge priority-<?php echo $recipient['priority_level']; ?>">
                                                <?php echo ucfirst($recipient['priority_level']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($recipient['medical_condition']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($recipient['required_by_date'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $recipient['status']; ?>">
                                                <?php echo ucfirst($recipient['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $wait_days = (time() - strtotime($recipient['created_at'])) / (60 * 60 * 24);
                                            echo floor($wait_days) . ' days';
                                            ?>
                                        </td>
                                        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                                            <td>
                                                <?php if ($recipient['status'] === 'waiting'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="updateRecipientStatus(<?php echo $recipient['id']; ?>, 'matched')">
                                                        <i class="fas fa-check"></i> Match
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

                <!-- Registered Donors -->
                <div class="content-section">
                    <h2><i class="fas fa-users"></i> Registered Donors</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Donor</th>
                                    <th>Blood Type</th>
                                    <th>Organs Consented</th>
                                    <th>Consent Date</th>
                                    <th>Emergency Contact</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donors as $donor): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></td>
                                        <td><span class="blood-type-badge"><?php echo $donor['blood_type']; ?></span></td>
                                        <td>
                                            <?php 
                                            if ($donor['organs']) {
                                                $organs = explode(',', $donor['organs']);
                                                foreach ($organs as $organ) {
                                                    echo '<span class="organ-badge">' . htmlspecialchars($organ) . '</span> ';
                                                }
                                            } else {
                                                echo 'No active consents';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($donor['consent_date'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($donor['emergency_contact_name']); ?><br>
                                            <small><?php echo htmlspecialchars($donor['emergency_contact_phone']); ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $donor['status']; ?>">
                                                <?php echo ucfirst($donor['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Transplants -->
                <div class="content-section">
                    <h2><i class="fas fa-history"></i> Recent Transplants</h2>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Transplant Date</th>
                                    <th>Organ</th>
                                    <th>Donor</th>
                                    <th>Recipient</th>
                                    <th>Surgeon</th>
                                    <th>Hospital</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transplants as $transplant): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($transplant['transplant_date'])); ?></td>
                                        <td><span class="organ-badge"><?php echo $transplant['organ_type']; ?></span></td>
                                        <td><?php echo htmlspecialchars($transplant['donor_fname'] . ' ' . $transplant['donor_lname']); ?></td>
                                        <td><?php echo htmlspecialchars($transplant['recipient_fname'] . ' ' . $transplant['recipient_lname']); ?></td>
                                        <td><?php echo htmlspecialchars($transplant['surgeon_name']); ?></td>
                                        <td><?php echo htmlspecialchars($transplant['hospital']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $transplant['status']; ?>">
                                                <?php echo ucfirst($transplant['status']); ?>
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

    <!-- Register Donor Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'patient'])): ?>
    <div id="registerDonorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Register as Organ Donor</h2>
                <span class="close" onclick="closeModal('registerDonorModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="register_donor">
                <?php if ($user_role !== 'patient'): ?>
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
                <?php endif; ?>
                <div class="form-group">
                    <label>Organs to Donate:</label>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="organs[]" value="Heart"> Heart</label>
                        <label><input type="checkbox" name="organs[]" value="Liver"> Liver</label>
                        <label><input type="checkbox" name="organs[]" value="Kidney"> Kidney</label>
                        <label><input type="checkbox" name="organs[]" value="Lung"> Lung</label>
                        <label><input type="checkbox" name="organs[]" value="Pancreas"> Pancreas</label>
                        <label><input type="checkbox" name="organs[]" value="Cornea"> Cornea</label>
                        <label><input type="checkbox" name="organs[]" value="Skin"> Skin</label>
                        <label><input type="checkbox" name="organs[]" value="Bone"> Bone</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="medical_history">Medical History:</label>
                    <textarea id="medical_history" name="medical_history" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="emergency_contact">Emergency Contact Name:</label>
                    <input type="text" id="emergency_contact" name="emergency_contact" required>
                </div>
                <div class="form-group">
                    <label for="emergency_phone">Emergency Contact Phone:</label>
                    <input type="tel" id="emergency_phone" name="emergency_phone" required>
                </div>
                <div class="form-group">
                    <label for="consent_date">Consent Date:</label>
                    <input type="date" id="consent_date" name="consent_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="notes">Additional Notes:</label>
                    <textarea id="notes" name="notes" rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('registerDonorModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register as Donor</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Recipient Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
    <div id="addRecipientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Recipient to Waiting List</h2>
                <span class="close" onclick="closeModal('addRecipientModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_recipient">
                <div class="form-group">
                    <label for="patient_id_rec">Patient:</label>
                    <select id="patient_id_rec" name="patient_id" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="organ_needed">Organ Needed:</label>
                    <select id="organ_needed" name="organ_needed" required>
                        <option value="">Select Organ</option>
                        <option value="Heart">Heart</option>
                        <option value="Liver">Liver</option>
                        <option value="Kidney">Kidney</option>
                        <option value="Lung">Lung</option>
                        <option value="Pancreas">Pancreas</option>
                        <option value="Cornea">Cornea</option>
                        <option value="Skin">Skin</option>
                        <option value="Bone">Bone</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="priority_level">Priority Level:</label>
                    <select id="priority_level" name="priority_level" required>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="blood_type_rec">Blood Type:</label>
                    <select id="blood_type_rec" name="blood_type" required>
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
                    <label for="medical_condition">Medical Condition:</label>
                    <textarea id="medical_condition" name="medical_condition" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="doctor_notes">Doctor Notes:</label>
                    <textarea id="doctor_notes" name="doctor_notes" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label for="required_by">Required By Date:</label>
                    <input type="date" id="required_by" name="required_by" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addRecipientModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">Add to Waiting List</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Record Transplant Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
    <div id="recordTransplantModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Record Organ Transplant</h2>
                <span class="close" onclick="closeModal('recordTransplantModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="record_transplant">
                <div class="form-group">
                    <label for="donor_id">Donor:</label>
                    <select id="donor_id" name="donor_id" required>
                        <option value="">Select Donor</option>
                        <?php foreach ($donors as $donor): ?>
                            <option value="<?php echo $donor['id']; ?>">
                                <?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="recipient_id">Recipient:</label>
                    <select id="recipient_id" name="recipient_id" required>
                        <option value="">Select Recipient</option>
                        <?php foreach ($recipients as $recipient): ?>
                            <?php if ($recipient['status'] === 'waiting'): ?>
                                <option value="<?php echo $recipient['id']; ?>">
                                    <?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name'] . ' - ' . $recipient['organ_needed']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="organ_type">Organ Type:</label>
                    <select id="organ_type" name="organ_type" required>
                        <option value="">Select Organ</option>
                        <option value="Heart">Heart</option>
                        <option value="Liver">Liver</option>
                        <option value="Kidney">Kidney</option>
                        <option value="Lung">Lung</option>
                        <option value="Pancreas">Pancreas</option>
                        <option value="Cornea">Cornea</option>
                        <option value="Skin">Skin</option>
                        <option value="Bone">Bone</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="transplant_date">Transplant Date:</label>
                    <input type="date" id="transplant_date" name="transplant_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="surgeon_id">Surgeon:</label>
                    <select id="surgeon_id" name="surgeon_id" required>
                        <option value="">Select Surgeon</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>">
                                <?php echo htmlspecialchars($doctor['doctor_name'] . ' - ' . $doctor['specialization']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="hospital">Hospital:</label>
                    <input type="text" id="hospital" name="hospital" required>
                </div>
                <div class="form-group">
                    <label for="surgery_notes">Surgery Notes:</label>
                    <textarea id="surgery_notes" name="surgery_notes" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('recordTransplantModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Transplant</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="assets/js/script.js"></script>
    <script>
        function updateRecipientStatus(recipientId, status) {
            if (confirm('Are you sure you want to update the recipient status to ' + status + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_recipient_status">
                    <input type="hidden" name="recipient_id" value="${recipientId}">
                    <input type="hidden" name="status" value="${status}">
                    <input type="hidden" name="notes" value="Status updated to ${status}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

    <style>
        .organ-availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .organ-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #e74c3c;
        }

        .organ-icon {
            margin-right: 1rem;
            font-size: 2rem;
            color: #e74c3c;
        }

        .organ-info h3 {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
        }

        .organ-info p {
            margin: 0.25rem 0 0 0;
            color: #666;
        }

        .organ-badge {
            background: #e74c3c;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: bold;
            margin-right: 0.25rem;
            display: inline-block;
            margin-bottom: 0.25rem;
        }

        .blood-type-badge {
            background: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.875rem;
        }

        .priority-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: bold;
        }

        .priority-low { background: #d4edda; color: #155724; }
        .priority-medium { background: #fff3cd; color: #856404; }
        .priority-high { background: #f8d7da; color: #721c24; }
        .priority-critical { background: #dc3545; color: white; }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: bold;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-waiting { background: #fff3cd; color: #856404; }
        .status-matched { background: #d1ecf1; color: #0c5460; }
        .status-transplanted { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d4edda; color: #155724; }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
        }

        .checkbox-group input[type="checkbox"] {
            margin-right: 0.5rem;
        }
    </style>
</body>
</html>
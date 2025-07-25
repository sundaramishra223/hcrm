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
            case 'register_donor':
                if (in_array($user_role, ['admin', 'doctor', 'nurse'])) {
                    $patient_id = $_POST['patient_id'];
                    $organ_type = $_POST['organ_type'];
                    $blood_type = $_POST['blood_type'];
                    $medical_history = $_POST['medical_history'];
                    $consent_date = $_POST['consent_date'];
                    $notes = $_POST['notes'];
                    
                    $stmt = $db->prepare("INSERT INTO organ_donors (patient_id, organ_type, blood_type, medical_history, consent_date, notes, registered_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$patient_id, $organ_type, $blood_type, $medical_history, $consent_date, $notes, $user_id]);
                    
                    $success_message = "Organ donor registered successfully!";
                }
                break;
                
            case 'register_recipient':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $patient_id = $_POST['patient_id'];
                    $organ_needed = $_POST['organ_needed'];
                    $blood_type = $_POST['blood_type'];
                    $priority_level = $_POST['priority_level'];
                    $medical_condition = $_POST['medical_condition'];
                    $doctor_notes = $_POST['doctor_notes'];
                    
                    $stmt = $db->prepare("INSERT INTO organ_recipients (patient_id, organ_needed, blood_type, priority_level, medical_condition, doctor_notes, registered_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$patient_id, $organ_needed, $blood_type, $priority_level, $medical_condition, $doctor_notes, $user_id]);
                    
                    $success_message = "Organ recipient registered successfully!";
                }
                break;
                
            case 'record_transplant':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $donor_id = $_POST['donor_id'];
                    $recipient_id = $_POST['recipient_id'];
                    $surgery_date = $_POST['surgery_date'];
                    $surgeon_id = $_POST['surgeon_id'];
                    $hospital_name = $_POST['hospital_name'];
                    $surgery_notes = $_POST['surgery_notes'];
                    
                    $stmt = $db->prepare("INSERT INTO organ_transplants (donor_id, recipient_id, surgery_date, surgeon_id, hospital_name, surgery_notes, recorded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$donor_id, $recipient_id, $surgery_date, $surgeon_id, $hospital_name, $surgery_notes, $user_id]);
                    
                    // Update donor and recipient status
                    $stmt = $db->prepare("UPDATE organ_donors SET status = 'donated', donation_date = ? WHERE id = ?");
                    $stmt->execute([$surgery_date, $donor_id]);
                    
                    $stmt = $db->prepare("UPDATE organ_recipients SET status = 'transplanted', transplant_date = ? WHERE id = ?");
                    $stmt->execute([$surgery_date, $recipient_id]);
                    
                    $success_message = "Transplant recorded successfully!";
                }
                break;
                
            case 'update_status':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $type = $_POST['type']; // 'donor' or 'recipient'
                    $record_id = $_POST['record_id'];
                    $new_status = $_POST['new_status'];
                    
                    if ($type === 'donor') {
                        $stmt = $db->prepare("UPDATE organ_donors SET status = ? WHERE id = ?");
                    } else {
                        $stmt = $db->prepare("UPDATE organ_recipients SET status = ? WHERE id = ?");
                    }
                    $stmt->execute([$new_status, $record_id]);
                    
                    $success_message = ucfirst($type) . " status updated successfully!";
                }
                break;
        }
    }
}

// Get statistics
$total_donors = $db->query("SELECT COUNT(*) FROM organ_donors")->fetchColumn();
$active_donors = $db->query("SELECT COUNT(*) FROM organ_donors WHERE status = 'active'")->fetchColumn();
$total_recipients = $db->query("SELECT COUNT(*) FROM organ_recipients")->fetchColumn();
$waiting_recipients = $db->query("SELECT COUNT(*) FROM organ_recipients WHERE status = 'waiting'")->fetchColumn();
$total_transplants = $db->query("SELECT COUNT(*) FROM organ_transplants")->fetchColumn();
$successful_transplants = $db->query("SELECT COUNT(*) FROM organ_transplants WHERE status = 'successful'")->fetchColumn();

// Get organ statistics
$organ_stats = $db->query("
    SELECT organ_type, 
           COUNT(*) as total_donors,
           SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_donors,
           SUM(CASE WHEN status = 'donated' THEN 1 ELSE 0 END) as donated
    FROM organ_donors 
    GROUP BY organ_type 
    ORDER BY organ_type
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent donors
$recent_donors = $db->query("
    SELECT od.*, p.first_name, p.last_name, p.patient_id, u.first_name as reg_fname, u.last_name as reg_lname 
    FROM organ_donors od
    JOIN patients p ON od.patient_id = p.id
    LEFT JOIN users u ON od.registered_by = u.id
    ORDER BY od.created_at DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get recipients waiting
$waiting_recipients_list = $db->query("
    SELECT or_table.*, p.first_name, p.last_name, p.patient_id, u.first_name as reg_fname, u.last_name as reg_lname 
    FROM organ_recipients or_table
    JOIN patients p ON or_table.patient_id = p.id
    LEFT JOIN users u ON or_table.registered_by = u.id
    WHERE or_table.status = 'waiting'
    ORDER BY or_table.priority_level DESC, or_table.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent transplants
$recent_transplants = $db->query("
    SELECT ot.*, 
           pd.first_name as donor_fname, pd.last_name as donor_lname, pd.patient_id as donor_patient_id,
           pr.first_name as recipient_fname, pr.last_name as recipient_lname, pr.patient_id as recipient_patient_id,
           od.organ_type,
           us.first_name as surgeon_fname, us.last_name as surgeon_lname
    FROM organ_transplants ot
    JOIN organ_donors od ON ot.donor_id = od.id
    JOIN patients pd ON od.patient_id = pd.id
    JOIN organ_recipients ore ON ot.recipient_id = ore.id
    JOIN patients pr ON ore.patient_id = pr.id
    LEFT JOIN users us ON ot.surgeon_id = us.id
    ORDER BY ot.surgery_date DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get patients for dropdown
$patients = $db->query("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);

// Get doctors for dropdown
$doctors = $db->query("SELECT u.id, u.first_name, u.last_name FROM users u WHERE u.role = 'doctor' ORDER BY u.first_name, u.last_name")->fetchAll(PDO::FETCH_ASSOC);

// Get compatible matches for transplant
$compatible_donors = $db->query("
    SELECT od.id, od.organ_type, od.blood_type, p.first_name, p.last_name, p.patient_id
    FROM organ_donors od
    JOIN patients p ON od.patient_id = p.id
    WHERE od.status = 'active'
    ORDER BY od.organ_type, od.blood_type
")->fetchAll(PDO::FETCH_ASSOC);

$compatible_recipients = $db->query("
    SELECT ore.id, ore.organ_needed, ore.blood_type, ore.priority_level, p.first_name, p.last_name, p.patient_id
    FROM organ_recipients ore
    JOIN patients p ON ore.patient_id = p.id
    WHERE ore.status = 'waiting'
    ORDER BY ore.priority_level DESC, ore.organ_needed, ore.blood_type
")->fetchAll(PDO::FETCH_ASSOC);
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
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="content">
                <div class="page-header">
                    <h1><i class="fas fa-heart"></i> Organ Donation Management</h1>
                    <p>Manage organ donors, recipients, and transplant coordination</p>
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
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_donors; ?></h3>
                            <p>Total Donors</p>
                            <small><?php echo $active_donors; ?> Active</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-injured"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_recipients; ?></h3>
                            <p>Total Recipients</p>
                            <small><?php echo $waiting_recipients; ?> Waiting</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-procedures"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_transplants; ?></h3>
                            <p>Total Transplants</p>
                            <small><?php echo $successful_transplants; ?> Successful</small>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $total_transplants > 0 ? round(($successful_transplants / $total_transplants) * 100, 1) : 0; ?>%</h3>
                            <p>Success Rate</p>
                        </div>
                    </div>
                </div>

                <!-- Organ Statistics -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Organ Availability</h3>
                    </div>
                    <div class="card-body">
                        <div class="organ-stats-grid">
                            <?php foreach (['Heart', 'Liver', 'Kidney', 'Lung', 'Pancreas', 'Cornea', 'Bone Marrow', 'Skin'] as $organ): ?>
                                <?php 
                                $active_count = 0;
                                $donated_count = 0;
                                foreach ($organ_stats as $stat) {
                                    if ($stat['organ_type'] === $organ) {
                                        $active_count = $stat['active_donors'];
                                        $donated_count = $stat['donated'];
                                        break;
                                    }
                                }
                                $status_class = $active_count > 2 ? 'good' : ($active_count > 0 ? 'low' : 'critical');
                                ?>
                                <div class="organ-card <?php echo $status_class; ?>">
                                    <h4><?php echo $organ; ?></h4>
                                    <p class="active"><?php echo $active_count; ?> Active</p>
                                    <p class="donated"><?php echo $donated_count; ?> Donated</p>
                                    <span class="status"><?php echo ucfirst($status_class); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Organ Donors -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-hand-holding-heart"></i> Organ Donors</h3>
                                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                                    <button class="btn btn-primary" onclick="openModal('registerDonorModal')">
                                        <i class="fas fa-plus"></i> Register Donor
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Organ</th>
                                                <th>Blood Type</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_donors as $donor): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></strong><br>
                                                        <small><?php echo htmlspecialchars($donor['patient_id']); ?></small>
                                                    </td>
                                                    <td><span class="organ-badge"><?php echo $donor['organ_type']; ?></span></td>
                                                    <td><span class="blood-type-badge"><?php echo $donor['blood_type']; ?></span></td>
                                                    <td><span class="status-badge status-<?php echo $donor['status']; ?>"><?php echo ucfirst($donor['status']); ?></span></td>
                                                    <td>
                                                        <?php if ($donor['status'] === 'active' && in_array($user_role, ['admin', 'doctor'])): ?>
                                                            <button class="btn btn-sm btn-warning" onclick="updateStatus('donor', <?php echo $donor['id']; ?>, 'inactive')">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        <?php elseif ($donor['status'] === 'inactive' && in_array($user_role, ['admin', 'doctor'])): ?>
                                                            <button class="btn btn-sm btn-success" onclick="updateStatus('donor', <?php echo $donor['id']; ?>, 'active')">
                                                                <i class="fas fa-play"></i>
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

                    <!-- Waiting Recipients -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-user-injured"></i> Waiting Recipients</h3>
                                <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                                    <button class="btn btn-primary" onclick="openModal('registerRecipientModal')">
                                        <i class="fas fa-plus"></i> Register Recipient
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Patient</th>
                                                <th>Organ Needed</th>
                                                <th>Blood Type</th>
                                                <th>Priority</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($waiting_recipients_list as $recipient): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?></strong><br>
                                                        <small><?php echo htmlspecialchars($recipient['patient_id']); ?></small>
                                                    </td>
                                                    <td><span class="organ-badge"><?php echo $recipient['organ_needed']; ?></span></td>
                                                    <td><span class="blood-type-badge"><?php echo $recipient['blood_type']; ?></span></td>
                                                    <td><span class="priority-badge priority-<?php echo $recipient['priority_level']; ?>"><?php echo ucfirst($recipient['priority_level']); ?></span></td>
                                                    <td>
                                                        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                                                            <button class="btn btn-sm btn-info" onclick="findMatch(<?php echo $recipient['id']; ?>, '<?php echo $recipient['organ_needed']; ?>', '<?php echo $recipient['blood_type']; ?>')">
                                                                <i class="fas fa-search"></i> Find Match
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

                <!-- Recent Transplants -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-procedures"></i> Recent Transplants</h3>
                        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                            <button class="btn btn-success" onclick="openModal('recordTransplantModal')">
                                <i class="fas fa-plus"></i> Record Transplant
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Organ</th>
                                        <th>Donor</th>
                                        <th>Recipient</th>
                                        <th>Surgery Date</th>
                                        <th>Surgeon</th>
                                        <th>Hospital</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_transplants as $transplant): ?>
                                        <tr>
                                            <td><span class="organ-badge"><?php echo $transplant['organ_type']; ?></span></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($transplant['donor_fname'] . ' ' . $transplant['donor_lname']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($transplant['donor_patient_id']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($transplant['recipient_fname'] . ' ' . $transplant['recipient_lname']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($transplant['recipient_patient_id']); ?></small>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($transplant['surgery_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($transplant['surgeon_fname'] . ' ' . $transplant['surgeon_lname']); ?></td>
                                            <td><?php echo htmlspecialchars($transplant['hospital_name']); ?></td>
                                            <td><span class="status-badge status-<?php echo $transplant['status']; ?>"><?php echo ucfirst($transplant['status']); ?></span></td>
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

    <!-- Register Donor Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
    <div id="registerDonorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Register Organ Donor</h3>
                <span class="close" onclick="closeModal('registerDonorModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="register_donor">
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
                        <label>Organ Type *</label>
                        <select name="organ_type" required>
                            <option value="">Select Organ</option>
                            <option value="Heart">Heart</option>
                            <option value="Liver">Liver</option>
                            <option value="Kidney">Kidney</option>
                            <option value="Lung">Lung</option>
                            <option value="Pancreas">Pancreas</option>
                            <option value="Cornea">Cornea</option>
                            <option value="Bone Marrow">Bone Marrow</option>
                            <option value="Skin">Skin</option>
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
                        <label>Medical History</label>
                        <textarea name="medical_history" rows="3" placeholder="Relevant medical history..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Consent Date *</label>
                        <input type="date" name="consent_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('registerDonorModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register Donor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Register Recipient Modal -->
    <div id="registerRecipientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Register Organ Recipient</h3>
                <span class="close" onclick="closeModal('registerRecipientModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="register_recipient">
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
                        <label>Organ Needed *</label>
                        <select name="organ_needed" required>
                            <option value="">Select Organ</option>
                            <option value="Heart">Heart</option>
                            <option value="Liver">Liver</option>
                            <option value="Kidney">Kidney</option>
                            <option value="Lung">Lung</option>
                            <option value="Pancreas">Pancreas</option>
                            <option value="Cornea">Cornea</option>
                            <option value="Bone Marrow">Bone Marrow</option>
                            <option value="Skin">Skin</option>
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
                        <label>Priority Level *</label>
                        <select name="priority_level" required>
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Medical Condition *</label>
                        <textarea name="medical_condition" rows="3" placeholder="Current medical condition requiring transplant..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Doctor Notes</label>
                        <textarea name="doctor_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('registerRecipientModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register Recipient</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Record Transplant Modal -->
    <div id="recordTransplantModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Record Transplant</h3>
                <span class="close" onclick="closeModal('recordTransplantModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="record_transplant">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Donor *</label>
                        <select name="donor_id" required>
                            <option value="">Select Donor</option>
                            <?php foreach ($compatible_donors as $donor): ?>
                                <option value="<?php echo $donor['id']; ?>">
                                    <?php echo htmlspecialchars($donor['patient_id'] . ' - ' . $donor['first_name'] . ' ' . $donor['last_name'] . ' (' . $donor['organ_type'] . ', ' . $donor['blood_type'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Recipient *</label>
                        <select name="recipient_id" required>
                            <option value="">Select Recipient</option>
                            <?php foreach ($compatible_recipients as $recipient): ?>
                                <option value="<?php echo $recipient['id']; ?>">
                                    <?php echo htmlspecialchars($recipient['patient_id'] . ' - ' . $recipient['first_name'] . ' ' . $recipient['last_name'] . ' (' . $recipient['organ_needed'] . ', ' . $recipient['blood_type'] . ', ' . ucfirst($recipient['priority_level']) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Surgery Date *</label>
                        <input type="date" name="surgery_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Surgeon *</label>
                        <select name="surgeon_id" required>
                            <option value="">Select Surgeon</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Hospital Name *</label>
                        <input type="text" name="hospital_name" required placeholder="Hospital where surgery was performed">
                    </div>
                    <div class="form-group">
                        <label>Surgery Notes</label>
                        <textarea name="surgery_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('recordTransplantModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Transplant</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Status Update Form -->
    <form id="statusUpdateForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="type" id="status_type">
        <input type="hidden" name="record_id" id="status_record_id">
        <input type="hidden" name="new_status" id="status_new_status">
    </form>

    <script src="assets/js/script.js"></script>
    <script>
        function updateStatus(type, recordId, newStatus) {
            if (confirm('Are you sure you want to update the status?')) {
                document.getElementById('status_type').value = type;
                document.getElementById('status_record_id').value = recordId;
                document.getElementById('status_new_status').value = newStatus;
                document.getElementById('statusUpdateForm').submit();
            }
        }

        function findMatch(recipientId, organNeeded, bloodType) {
            alert('Finding compatible donors for ' + organNeeded + ' with blood type ' + bloodType + '...\n\nThis feature would show a detailed compatibility analysis.');
        }
    </script>

    <style>
        .organ-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .organ-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .organ-card.critical {
            border-color: #dc3545;
            background: #fff5f5;
        }

        .organ-card.low {
            border-color: #ffc107;
            background: #fffdf5;
        }

        .organ-card.good {
            border-color: #28a745;
            background: #f8fff8;
        }

        .organ-card h4 {
            margin: 0 0 10px 0;
            font-size: 18px;
            font-weight: bold;
        }

        .organ-card .active {
            color: #28a745;
            font-weight: 600;
            margin: 5px 0;
        }

        .organ-card .donated {
            color: #007bff;
            font-weight: 600;
            margin: 5px 0;
        }

        .organ-card .status {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 12px;
            text-transform: uppercase;
        }

        .organ-card.critical .status {
            background: #dc3545;
            color: white;
        }

        .organ-card.low .status {
            background: #ffc107;
            color: #333;
        }

        .organ-card.good .status {
            background: #28a745;
            color: white;
        }

        .organ-badge {
            background: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }

        .blood-type-badge {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .priority-low { background: #d4edda; color: #155724; }
        .priority-medium { background: #fff3cd; color: #856404; }
        .priority-high { background: #f8d7da; color: #721c24; }
        .priority-critical { background: #dc3545; color: white; }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-donated { background: #cce5ff; color: #004085; }
        .status-waiting { background: #fff3cd; color: #856404; }
        .status-transplanted { background: #d4edda; color: #155724; }
        .status-successful { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
    </style>
</body>
</html>
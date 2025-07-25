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
                if (in_array($user_role, ['admin', 'doctor', 'patient'])) {
                    $stmt = $db->prepare("
                        INSERT INTO organ_donors (patient_id, organ_types, consent_date, consent_document, 
                                                emergency_contact_name, emergency_contact_phone, 
                                                medical_conditions, status, registered_by, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['patient_id'], implode(',', $_POST['organ_types']), $_POST['consent_date'],
                        $_POST['consent_document'], $_POST['emergency_contact_name'], $_POST['emergency_contact_phone'],
                        $_POST['medical_conditions'], $user_id, $_POST['notes']
                    ]);
                    $success = "Organ donor registered successfully!";
                }
                break;
                
            case 'add_recipient':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $stmt = $db->prepare("
                        INSERT INTO organ_recipients (patient_id, organ_needed, blood_type, urgency_level, 
                                                    listing_date, medical_condition, doctor_notes, 
                                                    status, added_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'waiting', ?)
                    ");
                    $stmt->execute([
                        $_POST['patient_id'], $_POST['organ_needed'], $_POST['blood_type'],
                        $_POST['urgency_level'], $_POST['listing_date'], $_POST['medical_condition'],
                        $_POST['doctor_notes'], $user_id
                    ]);
                    $success = "Organ recipient added to waiting list successfully!";
                }
                break;
                
            case 'match_organ':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $donor_id = $_POST['donor_id'];
                    $recipient_id = $_POST['recipient_id'];
                    $organ_type = $_POST['organ_type'];
                    
                    // Create organ match record
                    $stmt = $db->prepare("
                        INSERT INTO organ_matches (donor_id, recipient_id, organ_type, match_date, 
                                                 compatibility_score, status, matched_by) 
                        VALUES (?, ?, ?, NOW(), ?, 'pending', ?)
                    ");
                    $stmt->execute([$donor_id, $recipient_id, $organ_type, $_POST['compatibility_score'], $user_id]);
                    
                    $success = "Organ match created successfully!";
                }
                break;
                
            case 'update_transplant':
                if (in_array($user_role, ['admin', 'doctor'])) {
                    $match_id = $_POST['match_id'];
                    $transplant_date = $_POST['transplant_date'];
                    $hospital = $_POST['hospital'];
                    $surgeon = $_POST['surgeon'];
                    $status = $_POST['status'];
                    
                    // Update match with transplant details
                    $stmt = $db->prepare("
                        UPDATE organ_matches 
                        SET transplant_date = ?, hospital = ?, surgeon = ?, status = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$transplant_date, $hospital, $surgeon, $status, $match_id]);
                    
                    // Update recipient status if transplant completed
                    if ($status === 'completed') {
                        $stmt = $db->prepare("
                            UPDATE organ_recipients 
                            SET status = 'transplanted' 
                            WHERE id = (SELECT recipient_id FROM organ_matches WHERE id = ?)
                        ");
                        $stmt->execute([$match_id]);
                    }
                    
                    $success = "Transplant information updated successfully!";
                }
                break;
        }
    }
}

// Get statistics
$stats = $db->query("
    SELECT 
        (SELECT COUNT(*) FROM organ_donors WHERE status = 'active') as active_donors,
        (SELECT COUNT(*) FROM organ_recipients WHERE status = 'waiting') as waiting_recipients,
        (SELECT COUNT(*) FROM organ_matches WHERE status = 'pending') as pending_matches,
        (SELECT COUNT(*) FROM organ_matches WHERE status = 'completed') as completed_transplants
")->fetch();

// Get organ donors
$organ_donors = $db->query("
    SELECT od.*, p.first_name, p.last_name, p.patient_id, p.blood_type,
           u.first_name as reg_fname, u.last_name as reg_lname
    FROM organ_donors od
    JOIN patients p ON od.patient_id = p.id
    LEFT JOIN users u ON od.registered_by = u.id
    ORDER BY od.consent_date DESC
    LIMIT 20
")->fetchAll();

// Get organ recipients
$organ_recipients = $db->query("
    SELECT orec.*, p.first_name, p.last_name, p.patient_id,
           u.first_name as added_fname, u.last_name as added_lname
    FROM organ_recipients orec
    JOIN patients p ON orec.patient_id = p.id
    LEFT JOIN users u ON orec.added_by = u.id
    WHERE orec.status = 'waiting'
    ORDER BY orec.urgency_level DESC, orec.listing_date ASC
")->fetchAll();

// Get recent matches
$recent_matches = $db->query("
    SELECT om.*, 
           pd.first_name as donor_fname, pd.last_name as donor_lname, pd.patient_id as donor_pid,
           pr.first_name as recipient_fname, pr.last_name as recipient_lname, pr.patient_id as recipient_pid,
           u.first_name as matched_fname, u.last_name as matched_lname
    FROM organ_matches om
    JOIN organ_donors od ON om.donor_id = od.id
    JOIN patients pd ON od.patient_id = pd.id
    JOIN organ_recipients orec ON om.recipient_id = orec.id
    JOIN patients pr ON orec.patient_id = pr.id
    LEFT JOIN users u ON om.matched_by = u.id
    ORDER BY om.match_date DESC
    LIMIT 10
")->fetchAll();

// Get patients for dropdown
$patients = $db->query("SELECT id, patient_id, first_name, last_name, blood_type FROM patients ORDER BY first_name")->fetchAll();

// Get patient-specific data if user is a patient
$patient_donor_info = null;
$patient_recipient_info = null;
if ($user_role === 'patient') {
    $patient_id = $db->query("SELECT id FROM patients WHERE user_id = ?", [$user_id])->fetch()['id'] ?? null;
    if ($patient_id) {
        $patient_donor_info = $db->query("
            SELECT od.*, u.first_name as reg_fname, u.last_name as reg_lname
            FROM organ_donors od
            LEFT JOIN users u ON od.registered_by = u.id
            WHERE od.patient_id = ?
        ", [$patient_id])->fetch();
        
        $patient_recipient_info = $db->query("
            SELECT orec.*, u.first_name as added_fname, u.last_name as added_lname
            FROM organ_recipients orec
            LEFT JOIN users u ON orec.added_by = u.id
            WHERE orec.patient_id = ?
        ", [$patient_id])->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organ Donation Management - Hospital CRM</title>
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
                    <h1><i class="fas fa-heart"></i> Organ Donation Management</h1>
                    <p>Manage organ donors, recipients, and transplant coordination</p>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Statistics Overview -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['active_donors']; ?></h3>
                            <p>Active Donors</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['waiting_recipients']; ?></h3>
                            <p>Waiting Recipients</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['pending_matches']; ?></h3>
                            <p>Pending Matches</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['completed_transplants']; ?></h3>
                            <p>Completed Transplants</p>
                        </div>
                    </div>
                </div>

                <?php if ($user_role === 'patient'): ?>
                    <!-- Patient View -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3><i class="fas fa-heart"></i> My Donor Registration</h3>
                                    <?php if (!$patient_donor_info): ?>
                                        <button class="btn btn-primary" onclick="openModal('registerDonorModal')">
                                            <i class="fas fa-plus"></i> Register as Donor
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php if ($patient_donor_info): ?>
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <label>Consent Date:</label>
                                                <span><?php echo date('M d, Y', strtotime($patient_donor_info['consent_date'])); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <label>Organ Types:</label>
                                                <span><?php echo htmlspecialchars($patient_donor_info['organ_types']); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <label>Status:</label>
                                                <span class="badge badge-<?php echo $patient_donor_info['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($patient_donor_info['status']); ?>
                                                </span>
                                            </div>
                                            <div class="info-item">
                                                <label>Emergency Contact:</label>
                                                <span><?php echo htmlspecialchars($patient_donor_info['emergency_contact_name']); ?> - <?php echo $patient_donor_info['emergency_contact_phone']; ?></span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center">You are not registered as an organ donor.</p>
                                        <p class="text-center text-muted">Consider registering to help save lives.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-hourglass-half"></i> My Recipient Status</h3>
                                </div>
                                <div class="card-body">
                                    <?php if ($patient_recipient_info): ?>
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <label>Organ Needed:</label>
                                                <span><?php echo htmlspecialchars($patient_recipient_info['organ_needed']); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <label>Listing Date:</label>
                                                <span><?php echo date('M d, Y', strtotime($patient_recipient_info['listing_date'])); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <label>Urgency Level:</label>
                                                <span class="badge badge-<?php echo $patient_recipient_info['urgency_level'] === 'critical' ? 'danger' : ($patient_recipient_info['urgency_level'] === 'high' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst($patient_recipient_info['urgency_level']); ?>
                                                </span>
                                            </div>
                                            <div class="info-item">
                                                <label>Status:</label>
                                                <span class="badge badge-<?php echo $patient_recipient_info['status'] === 'waiting' ? 'warning' : ($patient_recipient_info['status'] === 'transplanted' ? 'success' : 'info'); ?>">
                                                    <?php echo ucfirst($patient_recipient_info['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center">You are not on the organ recipient waiting list.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Admin/Staff View -->
                    <div class="row">
                        <!-- Organ Donors -->
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3><i class="fas fa-heart"></i> Organ Donors</h3>
                                    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
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
                                                    <th>Blood Type</th>
                                                    <th>Organ Types</th>
                                                    <th>Consent Date</th>
                                                    <th>Emergency Contact</th>
                                                    <th>Status</th>
                                                    <th>Registered By</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($organ_donors as $donor): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></strong><br>
                                                            <small><?php echo $donor['patient_id']; ?></small>
                                                        </td>
                                                        <td><span class="badge badge-primary"><?php echo $donor['blood_type']; ?></span></td>
                                                        <td><?php echo htmlspecialchars($donor['organ_types']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($donor['consent_date'])); ?></td>
                                                        <td>
                                                            <?php echo htmlspecialchars($donor['emergency_contact_name']); ?><br>
                                                            <small><?php echo $donor['emergency_contact_phone']; ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $donor['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                                <?php echo ucfirst($donor['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($donor['reg_fname'] . ' ' . $donor['reg_lname']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Waiting Recipients -->
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h3><i class="fas fa-hourglass-half"></i> Waiting Recipients</h3>
                                    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                                        <button class="btn btn-danger" onclick="openModal('addRecipientModal')">
                                            <i class="fas fa-plus"></i> Add Recipient
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
                                                    <th>Urgency</th>
                                                    <th>Listing Date</th>
                                                    <th>Medical Condition</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($organ_recipients as $recipient): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?></strong><br>
                                                            <small><?php echo $recipient['patient_id']; ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($recipient['organ_needed']); ?></td>
                                                        <td><span class="badge badge-primary"><?php echo $recipient['blood_type']; ?></span></td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $recipient['urgency_level'] === 'critical' ? 'danger' : ($recipient['urgency_level'] === 'high' ? 'warning' : 'info'); ?>">
                                                                <?php echo ucfirst($recipient['urgency_level']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M d, Y', strtotime($recipient['listing_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($recipient['medical_condition']); ?></td>
                                                        <td>
                                                            <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                                                                <button class="btn btn-sm btn-success" onclick="findMatch(<?php echo $recipient['id']; ?>, '<?php echo $recipient['organ_needed']; ?>', '<?php echo $recipient['blood_type']; ?>')">
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

                        <!-- Recent Matches -->
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-exchange-alt"></i> Recent Organ Matches</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Donor</th>
                                                    <th>Recipient</th>
                                                    <th>Organ</th>
                                                    <th>Match Date</th>
                                                    <th>Compatibility</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_matches as $match): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($match['donor_fname'] . ' ' . $match['donor_lname']); ?></strong><br>
                                                            <small><?php echo $match['donor_pid']; ?></small>
                                                        </td>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($match['recipient_fname'] . ' ' . $match['recipient_lname']); ?></strong><br>
                                                            <small><?php echo $match['recipient_pid']; ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($match['organ_type']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($match['match_date'])); ?></td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $match['compatibility_score'] >= 80 ? 'success' : ($match['compatibility_score'] >= 60 ? 'warning' : 'danger'); ?>">
                                                                <?php echo $match['compatibility_score']; ?>%
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-<?php echo $match['status'] === 'completed' ? 'success' : ($match['status'] === 'pending' ? 'warning' : 'info'); ?>">
                                                                <?php echo ucfirst($match['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($match['status'] === 'pending' && in_array($user_role, ['admin', 'doctor'])): ?>
                                                                <button class="btn btn-sm btn-primary" onclick="updateTransplant(<?php echo $match['id']; ?>)">
                                                                    <i class="fas fa-edit"></i> Update
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
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Register Donor Modal -->
    <div id="registerDonorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Register Organ Donor</h3>
                <span class="close" onclick="closeModal('registerDonorModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="register_donor">
                <div class="modal-body">
                    <div class="row">
                        <?php if ($user_role !== 'patient'): ?>
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
                        <?php else: ?>
                            <input type="hidden" name="patient_id" value="<?php echo $patient_id ?? ''; ?>">
                        <?php endif; ?>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="consent_date">Consent Date</label>
                                <input type="date" id="consent_date" name="consent_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Organ Types (Select all that apply)</label>
                                <div class="checkbox-group">
                                    <label><input type="checkbox" name="organ_types[]" value="Heart"> Heart</label>
                                    <label><input type="checkbox" name="organ_types[]" value="Liver"> Liver</label>
                                    <label><input type="checkbox" name="organ_types[]" value="Kidney"> Kidney</label>
                                    <label><input type="checkbox" name="organ_types[]" value="Lung"> Lung</label>
                                    <label><input type="checkbox" name="organ_types[]" value="Pancreas"> Pancreas</label>
                                    <label><input type="checkbox" name="organ_types[]" value="Cornea"> Cornea</label>
                                    <label><input type="checkbox" name="organ_types[]" value="Skin"> Skin</label>
                                    <label><input type="checkbox" name="organ_types[]" value="Bone"> Bone</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="emergency_contact_name">Emergency Contact Name</label>
                                <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="consent_document">Consent Document</label>
                                <input type="text" id="consent_document" name="consent_document" class="form-control" placeholder="Document reference">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="medical_conditions">Medical Conditions</label>
                                <textarea id="medical_conditions" name="medical_conditions" class="form-control" rows="2"></textarea>
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
                    <button type="button" class="btn btn-secondary" onclick="closeModal('registerDonorModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Register Donor</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Recipient Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
    <div id="addRecipientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Organ Recipient</h3>
                <span class="close" onclick="closeModal('addRecipientModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_recipient">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rec_patient_id">Patient</label>
                                <select id="rec_patient_id" name="patient_id" class="form-control" required>
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
                                <label for="organ_needed">Organ Needed</label>
                                <select id="organ_needed" name="organ_needed" class="form-control" required>
                                    <option value="">Select Organ</option>
                                    <option value="Heart">Heart</option>
                                    <option value="Liver">Liver</option>
                                    <option value="Kidney">Kidney</option>
                                    <option value="Lung">Lung</option>
                                    <option value="Pancreas">Pancreas</option>
                                    <option value="Cornea">Cornea</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="rec_blood_type">Blood Type</label>
                                <select id="rec_blood_type" name="blood_type" class="form-control" required>
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
                                <label for="rec_urgency_level">Urgency Level</label>
                                <select id="rec_urgency_level" name="urgency_level" class="form-control" required>
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
                                <label for="listing_date">Listing Date</label>
                                <input type="date" id="listing_date" name="listing_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="medical_condition">Medical Condition</label>
                                <input type="text" id="medical_condition" name="medical_condition" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="doctor_notes">Doctor Notes</label>
                                <textarea id="doctor_notes" name="doctor_notes" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addRecipientModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Add to Waiting List</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="assets/js/script.js"></script>
    <script>
        function findMatch(recipientId, organNeeded, bloodType) {
            alert(`Finding potential matches for ${organNeeded} transplant (Blood Type: ${bloodType})`);
            // In a real implementation, this would open a modal with potential donor matches
        }

        function updateTransplant(matchId) {
            alert(`Update transplant details for match ID: ${matchId}`);
            // In a real implementation, this would open a modal to update transplant information
        }
    </script>

    <style>
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
        }

        .info-grid {
            display: grid;
            gap: 15px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-item label {
            font-weight: 600;
            color: #333;
        }
    </style>
</body>
</html>
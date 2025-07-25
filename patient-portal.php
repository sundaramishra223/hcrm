<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if ($user_role !== 'patient') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$user_id = $_SESSION['user_id'];

// Get current page from URL parameter
$current_page = $_GET['page'] ?? 'overview';

// Get patient information
try {
    $patient = $db->query("
        SELECT p.*, u.email, u.username 
        FROM patients p 
        LEFT JOIN users u ON u.email = p.email 
        WHERE u.id = ?
    ", [$user_id])->fetch();
    
    if (!$patient) {
        header('Location: index.php');
        exit;
    }
    
    $patient_id = $patient['id'];
} catch (Exception $e) {
    header('Location: index.php');
    exit;
}

// Get patient's appointments
try {
    $appointments = $db->query("
        SELECT a.*, d.doctor_name, d.specialization, d.consultation_fee
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
        LIMIT 10
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $appointments = [];
}

// Get patient's bills
try {
    $bills = $db->query("
        SELECT b.*, a.appointment_date, d.doctor_name
        FROM billing b
        LEFT JOIN appointments a ON b.appointment_id = a.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        WHERE b.patient_id = ?
        ORDER BY b.bill_date DESC
        LIMIT 10
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $bills = [];
}

// Get patient's insurance policies
try {
    $insurance_policies = $db->query("
        SELECT pip.*, ic.company_name, ic.contact_phone, ic.contact_email
        FROM patient_insurance_policies pip
        LEFT JOIN insurance_companies ic ON pip.insurance_company_id = ic.id
        WHERE pip.patient_id = ? AND pip.is_active = 1
        ORDER BY pip.expiry_date DESC
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $insurance_policies = [];
}

// Get patient's insurance claims
try {
    $insurance_claims = $db->query("
        SELECT ic.*, comp.company_name, b.bill_id, b.total_amount
        FROM insurance_claims ic
        LEFT JOIN insurance_companies comp ON ic.insurance_company_id = comp.id
        LEFT JOIN billing b ON ic.bill_id = b.id
        WHERE ic.patient_id = ?
        ORDER BY ic.created_at DESC
        LIMIT 10
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $insurance_claims = [];
}

// Get patient's prescriptions
try {
    $prescriptions = $db->query("
        SELECT p.*, d.doctor_name, u.username as prescribed_by_name
        FROM prescriptions p
        LEFT JOIN doctors d ON p.doctor_id = d.id
        LEFT JOIN users u ON p.prescribed_by = u.id
        WHERE p.patient_id = ?
        ORDER BY p.created_at DESC
        LIMIT 10
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $prescriptions = [];
}

// Get prescription details
$prescription_details = [];
if (!empty($prescriptions)) {
    foreach ($prescriptions as $prescription) {
        try {
            $details = $db->query("
                SELECT pd.*, ph.medicine_name, ph.generic_name
                FROM prescription_details pd
                LEFT JOIN pharmacy ph ON pd.medicine_id = ph.id
                WHERE pd.prescription_id = ?
            ", [$prescription['id']])->fetchAll();
            $prescription_details[$prescription['id']] = $details;
        } catch (Exception $e) {
            $prescription_details[$prescription['id']] = [];
        }
    }
}

// Get patient's lab reports
try {
    $lab_reports = $db->query("
        SELECT lr.*, l.test_name, l.test_category, l.normal_range, l.unit,
               d.doctor_name, u.username as conducted_by_name
        FROM laboratory_results lr
        LEFT JOIN laboratory l ON lr.test_id = l.id
        LEFT JOIN doctors d ON lr.doctor_id = d.id
        LEFT JOIN users u ON lr.conducted_by = u.id
        WHERE lr.patient_id = ?
        ORDER BY lr.test_date DESC
        LIMIT 10
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $lab_reports = [];
}

// Get assigned doctors (doctors who have treated this patient)
try {
    $assigned_doctors = $db->query("
        SELECT DISTINCT d.*, COUNT(a.id) as appointment_count
        FROM doctors d
        LEFT JOIN appointments a ON d.id = a.doctor_id
        WHERE a.patient_id = ?
        GROUP BY d.id
        ORDER BY appointment_count DESC
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $assigned_doctors = [];
}

// Calculate statistics for patient
$total_appointments = count($appointments);
$pending_bills = count(array_filter($bills, function($bill) { 
    return $bill['payment_status'] === 'pending'; 
}));
$active_policies = count($insurance_policies);
$pending_claims = count(array_filter($insurance_claims, function($claim) { 
    return in_array($claim['status'], ['pending', 'under_review']); 
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-user-circle"></i> Patient Portal</h2>
                <p><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                <small><?php echo htmlspecialchars($patient['patient_id']); ?></small>
            </div>
            <ul class="sidebar-menu">
                <li><a href="patient-portal.php?page=overview" class="<?php echo $current_page === 'overview' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Overview</a></li>
                <li><a href="patient-portal.php?page=appointments" class="<?php echo $current_page === 'appointments' ? 'active' : ''; ?>"><i class="fas fa-calendar-alt"></i> My Appointments</a></li>
                <li><a href="patient-portal.php?page=bills" class="<?php echo $current_page === 'bills' ? 'active' : ''; ?>"><i class="fas fa-file-invoice-dollar"></i> My Bills</a></li>
                <li><a href="patient-portal.php?page=insurance" class="<?php echo $current_page === 'insurance' ? 'active' : ''; ?>"><i class="fas fa-shield-alt"></i> My Insurance</a></li>
                <li><a href="patient-portal.php?page=prescriptions" class="<?php echo $current_page === 'prescriptions' ? 'active' : ''; ?>"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="patient-portal.php?page=reports" class="<?php echo $current_page === 'reports' ? 'active' : ''; ?>"><i class="fas fa-file-medical"></i> Lab Reports</a></li>
                <li><a href="patient-portal.php?page=doctors" class="<?php echo $current_page === 'doctors' ? 'active' : ''; ?>"><i class="fas fa-user-md"></i> My Doctors</a></li>
                <li><a href="patient-portal.php?page=profile" class="<?php echo $current_page === 'profile' ? 'active' : ''; ?>"><i class="fas fa-user-edit"></i> My Profile</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if ($current_page === 'overview'): ?>
            <!-- Overview Section -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-home"></i> Welcome, <?php echo htmlspecialchars($patient['first_name']); ?>!</h1>
                    <p>Your personal health dashboard</p>
                </div>
            </div>

            <!-- Patient Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_appointments; ?></h3>
                        <p>Total Appointments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pending_bills; ?></h3>
                        <p>Pending Bills</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $active_policies; ?></h3>
                        <p>Active Policies</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pending_claims; ?></h3>
                        <p>Pending Claims</p>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-check"></i> Recent Appointments</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($appointments)): ?>
                                <p class="text-muted">No appointments found.</p>
                            <?php else: ?>
                                <?php foreach (array_slice($appointments, 0, 3) as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-date">
                                            <strong><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></strong>
                                            <span><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span>
                                        </div>
                                        <div class="appointment-details">
                                            <h4><?php echo htmlspecialchars($appointment['doctor_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($appointment['specialization']); ?></p>
                                            <span class="badge badge-<?php echo $appointment['status'] === 'completed' ? 'success' : ($appointment['status'] === 'cancelled' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-pills"></i> Recent Prescriptions</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($prescriptions)): ?>
                                <p class="text-muted">No prescriptions found.</p>
                            <?php else: ?>
                                <?php foreach (array_slice($prescriptions, 0, 3) as $prescription): ?>
                                    <div class="prescription-item">
                                        <div class="prescription-header">
                                            <strong>Prescription #<?php echo htmlspecialchars($prescription['prescription_id']); ?></strong>
                                            <span class="badge badge-<?php echo $prescription['status'] === 'dispensed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($prescription['status']); ?>
                                            </span>
                                        </div>
                                        <p><strong>Doctor:</strong> <?php echo htmlspecialchars($prescription['doctor_name']); ?></p>
                                        <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($prescription['created_at'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($current_page === 'appointments'): ?>
            <!-- Appointments Section -->
            <div class="header">
                <h1><i class="fas fa-calendar-alt"></i> My Appointments</h1>
                <p>View all your appointments and their status</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <p class="text-muted text-center">No appointments found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Doctor</th>
                                        <th>Specialization</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></strong><br>
                                                <small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['specialization']); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $appointment['appointment_type'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $appointment['status'] === 'completed' ? 'success' : ($appointment['status'] === 'cancelled' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
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

            <?php elseif ($current_page === 'bills'): ?>
            <!-- Bills Section -->
            <div class="header">
                <h1><i class="fas fa-file-invoice-dollar"></i> My Bills</h1>
                <p>View your medical bills and payment status</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($bills)): ?>
                        <p class="text-muted text-center">No bills found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Bill ID</th>
                                        <th>Date</th>
                                        <th>Total Amount</th>
                                        <th>Paid Amount</th>
                                        <th>Balance</th>
                                        <th>Payment Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bills as $bill): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($bill['bill_id']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($bill['bill_date'])); ?></td>
                                            <td><?php echo formatCurrency($bill['total_amount']); ?></td>
                                            <td><?php echo formatCurrency($bill['paid_amount']); ?></td>
                                            <td><?php echo formatCurrency($bill['balance_amount']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $bill['payment_status'] === 'paid' ? 'success' : ($bill['payment_status'] === 'partial' ? 'warning' : 'danger'); ?>">
                                                    <?php echo ucfirst($bill['payment_status']); ?>
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

            <?php elseif ($current_page === 'insurance'): ?>
            <!-- Insurance Section -->
            <div class="header">
                <h1><i class="fas fa-shield-alt"></i> My Insurance</h1>
                <p>View your insurance policies and claims</p>
            </div>

            <!-- Insurance Policies -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-contract"></i> My Insurance Policies</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($insurance_policies)): ?>
                        <p class="text-muted text-center">No insurance policies found.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($insurance_policies as $policy): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="insurance-card">
                                        <div class="insurance-header">
                                            <h4><?php echo htmlspecialchars($policy['company_name']); ?></h4>
                                            <span class="badge badge-<?php echo (strtotime($policy['expiry_date']) > time()) ? 'success' : 'danger'; ?>">
                                                <?php echo (strtotime($policy['expiry_date']) > time()) ? 'Active' : 'Expired'; ?>
                                            </span>
                                        </div>
                                        <div class="insurance-details">
                                            <p><strong>Policy Number:</strong> <?php echo htmlspecialchars($policy['policy_number']); ?></p>
                                            <p><strong>Coverage:</strong> <?php echo formatCurrency($policy['coverage_amount']); ?></p>
                                            <p><strong>Valid Until:</strong> <?php echo date('d M Y', strtotime($policy['expiry_date'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($current_page === 'prescriptions'): ?>
            <!-- Prescriptions Section -->
            <div class="header">
                <h1><i class="fas fa-prescription-bottle-alt"></i> My Prescriptions</h1>
                <p>View your prescriptions and medications</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($prescriptions)): ?>
                        <p class="text-muted text-center">No prescriptions found.</p>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $prescription): ?>
                            <div class="prescription-card">
                                <div class="prescription-header">
                                    <div>
                                        <h4>Prescription #<?php echo htmlspecialchars($prescription['prescription_id']); ?></h4>
                                        <p><strong>Doctor:</strong> <?php echo htmlspecialchars($prescription['doctor_name']); ?></p>
                                        <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($prescription['created_at'])); ?></p>
                                    </div>
                                    <span class="badge badge-<?php echo $prescription['status'] === 'dispensed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($prescription['status']); ?>
                                    </span>
                                </div>

                                <?php if (isset($prescription_details[$prescription['id']]) && !empty($prescription_details[$prescription['id']])): ?>
                                    <div class="medicines-list">
                                        <h5><i class="fas fa-pills"></i> Prescribed Medicines:</h5>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Medicine</th>
                                                        <th>Dosage</th>
                                                        <th>Frequency</th>
                                                        <th>Duration</th>
                                                        <th>Instructions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($prescription_details[$prescription['id']] as $detail): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($detail['medicine_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($detail['dosage']); ?></td>
                                                            <td><?php echo htmlspecialchars($detail['frequency']); ?></td>
                                                            <td><?php echo htmlspecialchars($detail['duration']); ?></td>
                                                            <td><?php echo htmlspecialchars($detail['instructions']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($current_page === 'reports'): ?>
            <!-- Lab Reports Section -->
            <div class="header">
                <h1><i class="fas fa-file-medical"></i> My Lab Reports</h1>
                <p>View your laboratory test results</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($lab_reports)): ?>
                        <p class="text-muted text-center">No lab reports found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Test Date</th>
                                        <th>Test Name</th>
                                        <th>Category</th>
                                        <th>Result</th>
                                        <th>Normal Range</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lab_reports as $report): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($report['test_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($report['test_name']); ?></td>
                                            <td><?php echo htmlspecialchars($report['test_category']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($report['result_value']); ?></strong>
                                                <?php if ($report['unit']): ?>
                                                    <?php echo htmlspecialchars($report['unit']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($report['normal_range']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $report['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($report['status']); ?>
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

            <?php elseif ($current_page === 'doctors'): ?>
            <!-- My Doctors Section -->
            <div class="header">
                <h1><i class="fas fa-user-md"></i> My Doctors</h1>
                <p>Doctors who have treated you</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($assigned_doctors)): ?>
                        <p class="text-muted text-center">No doctors assigned yet.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($assigned_doctors as $doctor): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="doctor-card">
                                        <div class="doctor-info">
                                            <h4><?php echo htmlspecialchars($doctor['doctor_name']); ?></h4>
                                            <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                                            <p><strong>Qualification:</strong> <?php echo htmlspecialchars($doctor['qualification']); ?></p>
                                            <p><strong>Experience:</strong> <?php echo $doctor['experience_years']; ?> years</p>
                                            <p><strong>Appointments:</strong> <?php echo $doctor['appointment_count']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php elseif ($current_page === 'profile'): ?>
            <!-- Profile Section -->
            <div class="header">
                <h1><i class="fas fa-user-edit"></i> My Profile</h1>
                <p>View and manage your personal information</p>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="profile-section">
                                <h3>Personal Information</h3>
                                <div class="profile-item">
                                    <label>Patient ID:</label>
                                    <span><?php echo htmlspecialchars($patient['patient_id']); ?></span>
                                </div>
                                <div class="profile-item">
                                    <label>Full Name:</label>
                                    <span><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
                                </div>
                                <div class="profile-item">
                                    <label>Email:</label>
                                    <span><?php echo htmlspecialchars($patient['email']); ?></span>
                                </div>
                                <div class="profile-item">
                                    <label>Phone:</label>
                                    <span><?php echo htmlspecialchars($patient['phone']); ?></span>
                                </div>
                                <div class="profile-item">
                                    <label>Date of Birth:</label>
                                    <span><?php echo $patient['date_of_birth'] ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A'; ?></span>
                                </div>
                                <div class="profile-item">
                                    <label>Gender:</label>
                                    <span><?php echo ucfirst($patient['gender']); ?></span>
                                </div>
                                <div class="profile-item">
                                    <label>Blood Group:</label>
                                    <span><?php echo htmlspecialchars($patient['blood_group']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profile-section">
                                <h3>Contact Information</h3>
                                <div class="profile-item">
                                    <label>Address:</label>
                                    <span><?php echo htmlspecialchars($patient['address']); ?></span>
                                </div>
                                <div class="profile-item">
                                    <label>Emergency Contact:</label>
                                    <span><?php echo htmlspecialchars($patient['emergency_contact_name']); ?></span>
                                </div>
                                <div class="profile-item">
                                    <label>Emergency Phone:</label>
                                    <span><?php echo htmlspecialchars($patient['emergency_contact_phone']); ?></span>
                                </div>
                            </div>

                            <div class="profile-section">
                                <h3>Medical Information</h3>
                                <div class="profile-item">
                                    <label>Medical History:</label>
                                    <span><?php echo htmlspecialchars($patient['medical_history']) ?: 'None recorded'; ?></span>
                                </div>
                                <div class="profile-item">
                                    <label>Allergies:</label>
                                    <span><?php echo htmlspecialchars($patient['allergies']) ?: 'None recorded'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <style>
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 15px;
        }
        
        .appointment-item, .prescription-item {
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .appointment-date {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .prescription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .insurance-card, .doctor-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            height: 100%;
        }
        
        .insurance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .prescription-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .profile-section {
            margin-bottom: 30px;
        }
        
        .profile-item {
            display: flex;
            margin-bottom: 10px;
        }
        
        .profile-item label {
            font-weight: 600;
            min-width: 150px;
            color: #666;
        }
        
        .profile-item span {
            color: #333;
        }
        
        .medicines-list {
            margin-top: 15px;
        }
        
        .table-sm th, .table-sm td {
            padding: 8px;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .prescription-header, .insurance-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .profile-item {
                flex-direction: column;
            }
            
            .profile-item label {
                min-width: auto;
                margin-bottom: 5px;
            }
        }
    </style>
</body>
</html>
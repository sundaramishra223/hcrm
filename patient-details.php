<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/upload-handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'doctor', 'receptionist', 'nurse'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$patient_id = $_GET['id'] ?? null;

if (!$patient_id || !is_numeric($patient_id)) {
    header('Location: patients.php');
    exit;
}

// Get patient details
try {
    $patient = $db->query("SELECT * FROM patients WHERE id = ?", [$patient_id])->fetch();
    if (!$patient) {
        header('Location: patients.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: patients.php');
    exit;
}

// Get patient's appointments
try {
    $appointments = $db->query("
        SELECT a.*, d.doctor_name 
        FROM appointments a 
        LEFT JOIN doctors d ON a.doctor_id = d.id 
        WHERE a.patient_id = ? 
        ORDER BY a.appointment_date DESC, a.appointment_time DESC 
        LIMIT 10
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $appointments = [];
}

// Get patient's prescriptions
try {
    $prescriptions = $db->query("
        SELECT p.*, d.doctor_name 
        FROM prescriptions p 
        LEFT JOIN doctors d ON p.doctor_id = d.id 
        WHERE p.patient_id = ? 
        ORDER BY p.created_at DESC 
        LIMIT 10
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $prescriptions = [];
}

// Get patient's lab tests
try {
    $lab_tests = $db->query("
        SELECT lt.*, d.doctor_name 
        FROM lab_tests lt 
        LEFT JOIN doctors d ON lt.doctor_id = d.id 
        WHERE lt.patient_id = ? 
        ORDER BY lt.test_date DESC 
        LIMIT 10
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $lab_tests = [];
}

// Get patient's bills
try {
    $bills = $db->query("
        SELECT * FROM billing 
        WHERE patient_id = ? 
        ORDER BY bill_date DESC 
        LIMIT 10
    ", [$patient_id])->fetchAll();
} catch (Exception $e) {
    $bills = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-hospital"></i> Hospital CRM</h2>
                <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="patients.php" class="active"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-user"></i> Patient Details</h1>
                    <p><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> (<?php echo htmlspecialchars($patient['patient_id']); ?>)</p>
                </div>
                <div class="header-actions">
                    <a href="patients.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Patients
                    </a>
                    <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                        <a href="patients.php?edit=<?php echo $patient['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Patient
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-2">
                <!-- Patient Information -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user"></i> Basic Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <?php if ($patient['photo']): ?>
                                <img src="<?php echo ImageUploadHandler::getFileUrl($patient['photo'], 'patients'); ?>" 
                                     alt="Patient Photo" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 120px; height: 120px; border-radius: 50%; background: #ccc; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                                    <i class="fas fa-user" style="font-size: 40px; color: #666;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <table style="width: 100%;">
                            <tr><td><strong>Patient ID:</strong></td><td><?php echo htmlspecialchars($patient['patient_id']); ?></td></tr>
                            <tr><td><strong>Name:</strong></td><td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td></tr>
                            <tr><td><strong>Email:</strong></td><td><?php echo htmlspecialchars($patient['email']); ?></td></tr>
                            <tr><td><strong>Phone:</strong></td><td><?php echo htmlspecialchars($patient['phone']); ?></td></tr>
                            <tr><td><strong>Date of Birth:</strong></td><td><?php echo $patient['date_of_birth'] ? date('d M Y', strtotime($patient['date_of_birth'])) : 'N/A'; ?></td></tr>
                            <tr><td><strong>Age:</strong></td><td><?php echo calculateAge($patient['date_of_birth']); ?></td></tr>
                            <tr><td><strong>Gender:</strong></td><td><?php echo htmlspecialchars(ucfirst($patient['gender'])); ?></td></tr>
                            <tr><td><strong>Blood Group:</strong></td><td><?php echo htmlspecialchars($patient['blood_group'] ?: 'N/A'); ?></td></tr>
                            <tr><td><strong>Status:</strong></td><td>
                                <span class="badge badge-<?php echo $patient['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $patient['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td></tr>
                            <tr><td><strong>Registered:</strong></td><td><?php echo date('d M Y', strtotime($patient['created_at'])); ?></td></tr>
                        </table>
                    </div>
                </div>

                <!-- Contact & Emergency Information -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-address-book"></i> Contact Information</h3>
                    </div>
                    <div class="card-body">
                        <table style="width: 100%;">
                            <tr><td><strong>Address:</strong></td><td><?php echo htmlspecialchars($patient['address'] ?: 'N/A'); ?></td></tr>
                            <tr><td><strong>Emergency Contact:</strong></td><td><?php echo htmlspecialchars($patient['emergency_contact_name'] ?: 'N/A'); ?></td></tr>
                            <tr><td><strong>Emergency Phone:</strong></td><td><?php echo htmlspecialchars($patient['emergency_contact_phone'] ?: 'N/A'); ?></td></tr>
                        </table>
                        
                        <hr style="margin: 20px 0;">
                        
                        <h4><i class="fas fa-notes-medical"></i> Medical Information</h4>
                        <table style="width: 100%; margin-top: 10px;">
                            <tr><td><strong>Medical History:</strong></td><td><?php echo htmlspecialchars($patient['medical_history'] ?: 'No medical history recorded'); ?></td></tr>
                            <tr><td><strong>Allergies:</strong></td><td><?php echo htmlspecialchars($patient['allergies'] ?: 'No allergies recorded'); ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="grid grid-2">
                <!-- Recent Appointments -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt"></i> Recent Appointments</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($appointments)): ?>
                            <p class="text-muted text-center">No appointments found.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Doctor</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $appointment['status'] == 'completed' ? 'success' : ($appointment['status'] == 'cancelled' ? 'danger' : 'info'); ?>">
                                                        <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
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

                <!-- Recent Prescriptions -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-prescription-bottle-alt"></i> Recent Prescriptions</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($prescriptions)): ?>
                            <p class="text-muted text-center">No prescriptions found.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Doctor</th>
                                            <th>Diagnosis</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($prescriptions as $prescription): ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($prescription['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                                                <td><?php echo htmlspecialchars(substr($prescription['diagnosis'], 0, 30)) . (strlen($prescription['diagnosis']) > 30 ? '...' : ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-2">
                <!-- Recent Lab Tests -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-flask"></i> Recent Lab Tests</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lab_tests)): ?>
                            <p class="text-muted text-center">No lab tests found.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Test ID</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lab_tests as $test): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($test['test_id']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($test['test_date'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $test['status'] == 'completed' ? 'success' : ($test['status'] == 'cancelled' ? 'danger' : 'warning'); ?>">
                                                        <?php echo htmlspecialchars(ucfirst($test['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatCurrency($test['total_amount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Bills -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-invoice-dollar"></i> Recent Bills</h3>
                    </div>
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
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bills as $bill): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($bill['bill_id']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($bill['bill_date'])); ?></td>
                                                <td><?php echo formatCurrency($bill['total_amount']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $bill['payment_status'] == 'paid' ? 'success' : ($bill['payment_status'] == 'overdue' ? 'danger' : 'warning'); ?>">
                                                        <?php echo htmlspecialchars(ucfirst($bill['payment_status'])); ?>
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
        </main>
    </div>
</body>
</html>
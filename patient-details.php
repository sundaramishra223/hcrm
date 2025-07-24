<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/upload-handler.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get patient ID
$patient_id = $_GET['id'] ?? 0;

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
$appointments = $db->query(
    "SELECT a.*, d.doctor_name 
     FROM appointments a 
     LEFT JOIN doctors d ON a.doctor_id = d.id 
     WHERE a.patient_id = ? 
     ORDER BY a.appointment_date DESC, a.appointment_time DESC 
     LIMIT 10",
    [$patient_id]
)->fetchAll();

// Get patient's prescriptions
$prescriptions = $db->query(
    "SELECT p.*, d.doctor_name 
     FROM prescriptions p 
     LEFT JOIN doctors d ON p.doctor_id = d.id 
     WHERE p.patient_id = ? 
     ORDER BY p.created_at DESC 
     LIMIT 5",
    [$patient_id]
)->fetchAll();

// Get patient's lab tests
$lab_tests = $db->query(
    "SELECT lt.*, d.doctor_name 
     FROM lab_tests lt 
     LEFT JOIN doctors d ON lt.doctor_id = d.id 
     WHERE lt.patient_id = ? 
     ORDER BY lt.created_at DESC 
     LIMIT 5",
    [$patient_id]
)->fetchAll();

// Get patient's bills
$bills = $db->query(
    "SELECT * FROM billing 
     WHERE patient_id = ? 
     ORDER BY created_at DESC 
     LIMIT 5",
    [$patient_id]
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - Hospital CRM</title>
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
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1><i class="fas fa-user"></i> Patient Details</h1>
                    <p><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                </div>
                <div>
                    <a href="patients.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Patients
                    </a>
                    <a href="patients.php?edit=<?php echo $patient['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Edit Patient
                    </a>
                </div>
            </div>

            <!-- Patient Information -->
            <div class="grid grid-3">
                <!-- Basic Information -->
                <div class="card">
                    <h3><i class="fas fa-user"></i> Basic Information</h3>
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
                        <tr>
                            <td><strong>Patient ID:</strong></td>
                            <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?php echo htmlspecialchars($patient['email']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Phone:</strong></td>
                            <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Gender:</strong></td>
                            <td><?php echo htmlspecialchars(ucfirst($patient['gender'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Date of Birth:</strong></td>
                            <td><?php echo $patient['date_of_birth'] ? date('d M Y', strtotime($patient['date_of_birth'])) : 'Not specified'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Age:</strong></td>
                            <td><?php echo calculateAge($patient['date_of_birth']); ?> years</td>
                        </tr>
                        <tr>
                            <td><strong>Blood Group:</strong></td>
                            <td><?php echo htmlspecialchars($patient['blood_group']) ?: 'Not specified'; ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Contact Information -->
                <div class="card">
                    <h3><i class="fas fa-address-book"></i> Contact Information</h3>
                    <table style="width: 100%;">
                        <tr>
                            <td><strong>Address:</strong></td>
                            <td><?php echo nl2br(htmlspecialchars($patient['address'])) ?: 'Not specified'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Emergency Contact:</strong></td>
                            <td><?php echo htmlspecialchars($patient['emergency_contact_name']) ?: 'Not specified'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Emergency Phone:</strong></td>
                            <td><?php echo htmlspecialchars($patient['emergency_contact_phone']) ?: 'Not specified'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Registration Date:</strong></td>
                            <td><?php echo date('d M Y', strtotime($patient['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Last Updated:</strong></td>
                            <td><?php echo date('d M Y H:i', strtotime($patient['updated_at'])); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Medical Information -->
                <div class="card">
                    <h3><i class="fas fa-heartbeat"></i> Medical Information</h3>
                    <div class="form-group">
                        <strong>Medical History:</strong>
                        <p><?php echo nl2br(htmlspecialchars($patient['medical_history'])) ?: 'No medical history recorded'; ?></p>
                    </div>
                    <div class="form-group">
                        <strong>Allergies:</strong>
                        <p><?php echo nl2br(htmlspecialchars($patient['allergies'])) ?: 'No known allergies'; ?></p>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="grid grid-2">
                <!-- Recent Appointments -->
                <div class="card">
                    <h3><i class="fas fa-calendar-alt"></i> Recent Appointments</h3>
                    <?php if (empty($appointments)): ?>
                        <p class="text-muted">No appointments found.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Doctor</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['appointment_type']))); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $appointment['status'] == 'scheduled' ? 'info' : 
                                                    ($appointment['status'] == 'completed' ? 'success' : 
                                                    ($appointment['status'] == 'cancelled' ? 'danger' : 'warning')); 
                                            ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['status']))); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Recent Prescriptions -->
                <div class="card">
                    <h3><i class="fas fa-prescription-bottle-alt"></i> Recent Prescriptions</h3>
                    <?php if (empty($prescriptions)): ?>
                        <p class="text-muted">No prescriptions found.</p>
                    <?php else: ?>
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
                                        <td><?php echo htmlspecialchars(substr($prescription['diagnosis'], 0, 50)) . (strlen($prescription['diagnosis']) > 50 ? '...' : ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-2">
                <!-- Recent Lab Tests -->
                <div class="card">
                    <h3><i class="fas fa-flask"></i> Recent Lab Tests</h3>
                    <?php if (empty($lab_tests)): ?>
                        <p class="text-muted">No lab tests found.</p>
                    <?php else: ?>
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
                                            <span class="badge badge-<?php 
                                                echo $test['status'] == 'completed' ? 'success' : 
                                                    ($test['status'] == 'pending' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $test['status']))); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatCurrency($test['final_amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Recent Bills -->
                <div class="card">
                    <h3><i class="fas fa-file-invoice-dollar"></i> Recent Bills</h3>
                    <?php if (empty($bills)): ?>
                        <p class="text-muted">No bills found.</p>
                    <?php else: ?>
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
                                            <span class="badge badge-<?php 
                                                echo $bill['payment_status'] == 'paid' ? 'success' : 
                                                    ($bill['payment_status'] == 'pending' ? 'warning' : 
                                                    ($bill['payment_status'] == 'overdue' ? 'danger' : 'info')); 
                                            ?>">
                                                <?php echo htmlspecialchars(ucfirst($bill['payment_status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
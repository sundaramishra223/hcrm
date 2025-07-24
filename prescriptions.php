<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get prescriptions
$prescriptions_query = "
    SELECT p.*, 
           pt.first_name as patient_first_name, pt.last_name as patient_last_name, pt.patient_id,
           d.doctor_name 
    FROM prescriptions p 
    LEFT JOIN patients pt ON p.patient_id = pt.id 
    LEFT JOIN doctors d ON p.doctor_id = d.id 
    ORDER BY p.created_at DESC 
    LIMIT 50
";
$prescriptions = $db->query($prescriptions_query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions Management - Hospital CRM</title>
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
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="prescriptions.php" class="active"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
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
                    <h1><i class="fas fa-prescription-bottle-alt"></i> Prescriptions Management</h1>
                    <p>Manage patient prescriptions and medications</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Prescriptions List -->
            <div class="card">
                <h3><i class="fas fa-list"></i> Recent Prescriptions</h3>
                
                <?php if (empty($prescriptions)): ?>
                    <p class="text-muted text-center">No prescriptions found.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Prescription ID</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Diagnosis</th>
                                    <th>Follow-up Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prescriptions as $prescription): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prescription['prescription_id']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($prescription['patient_id'] . ' - ' . $prescription['patient_first_name'] . ' ' . $prescription['patient_last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($prescription['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars(substr($prescription['diagnosis'], 0, 50)) . (strlen($prescription['diagnosis']) > 50 ? '...' : ''); ?></td>
                                        <td><?php echo $prescription['follow_up_date'] ? date('d M Y', strtotime($prescription['follow_up_date'])) : 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
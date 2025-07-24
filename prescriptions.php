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
if (!in_array($user_role, ['admin', 'doctor', 'nurse', 'pharmacy_staff'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get prescriptions with patient and doctor details
try {
    $prescriptions = $db->query("
        SELECT p.*, 
               CONCAT(pat.first_name, ' ', pat.last_name) as patient_name,
               pat.patient_id,
               d.doctor_name,
               u.username as prescribed_by_name
        FROM prescriptions p
        LEFT JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN doctors d ON p.doctor_id = d.id
        LEFT JOIN users u ON p.prescribed_by = u.id
        ORDER BY p.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $prescriptions = [];
}

// Get prescription details for each prescription
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - Hospital CRM</title>
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
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="prescriptions.php" class="active"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <?php if ($user_role === 'admin'): ?>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <?php endif; ?>
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
                    <h1><i class="fas fa-prescription-bottle-alt"></i> Prescription Management</h1>
                    <p>Manage patient prescriptions and medications</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Prescription Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-prescription-bottle-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($prescriptions); ?></h3>
                        <p>Total Prescriptions</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count(array_filter($prescriptions, function($p) { return $p['status'] === 'dispensed'; })); ?></h3>
                        <p>Dispensed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count(array_filter($prescriptions, function($p) { return $p['status'] === 'pending'; })); ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count(array_filter($prescriptions, function($p) { return date('Y-m-d', strtotime($p['created_at'])) === date('Y-m-d'); })); ?></h3>
                        <p>Today's Prescriptions</p>
                    </div>
                </div>
            </div>

            <!-- Prescriptions List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Prescriptions</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($prescriptions)): ?>
                        <p class="text-muted text-center">No prescriptions found.</p>
                    <?php else: ?>
                        <div class="prescriptions-list">
                            <?php foreach ($prescriptions as $prescription): ?>
                                <div class="prescription-card">
                                    <div class="prescription-header">
                                        <div class="prescription-info">
                                            <h4>Prescription #<?php echo htmlspecialchars($prescription['prescription_id']); ?></h4>
                                            <p><strong>Patient:</strong> <?php echo htmlspecialchars($prescription['patient_name']); ?> (<?php echo htmlspecialchars($prescription['patient_id']); ?>)</p>
                                            <p><strong>Doctor:</strong> <?php echo htmlspecialchars($prescription['doctor_name']); ?></p>
                                            <p><strong>Date:</strong> <?php echo date('d M Y', strtotime($prescription['created_at'])); ?></p>
                                        </div>
                                        <div class="prescription-status">
                                            <?php
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'dispensed' => 'success',
                                                'cancelled' => 'danger'
                                            ];
                                            $statusColor = $statusColors[$prescription['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-<?php echo $statusColor; ?>">
                                                <?php echo ucfirst($prescription['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($prescription['notes'])): ?>
                                        <div class="prescription-notes">
                                            <strong>Notes:</strong> <?php echo htmlspecialchars($prescription['notes']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Prescription Medicines -->
                                    <?php if (isset($prescription_details[$prescription['id']]) && !empty($prescription_details[$prescription['id']])): ?>
                                        <div class="medicines-list">
                                            <h5><i class="fas fa-pills"></i> Prescribed Medicines:</h5>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Medicine</th>
                                                            <th>Generic Name</th>
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
                                                                <td><?php echo htmlspecialchars($detail['generic_name']); ?></td>
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
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <style>
    .prescriptions-list {
        max-height: 800px;
        overflow-y: auto;
    }
    
    .prescription-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        background: #fff;
    }
    
    .prescription-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .prescription-info h4 {
        color: #2c3e50;
        margin-bottom: 10px;
    }
    
    .prescription-info p {
        margin: 5px 0;
        color: #666;
    }
    
    .prescription-notes {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        color: #495057;
    }
    
    .medicines-list {
        margin-top: 15px;
    }
    
    .medicines-list h5 {
        color: #2c3e50;
        margin-bottom: 10px;
    }
    
    .table-sm th,
    .table-sm td {
        padding: 8px;
        font-size: 0.9em;
    }
    
    .badge {
        font-size: 0.8em;
        padding: 5px 10px;
    }
    </style>
</body>
</html>
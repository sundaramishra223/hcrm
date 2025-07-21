<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check if user is a patient
if ($user_role !== 'patient') {
    showErrorAlert('Access Denied: This page is only for patients.');
    header('Location: dashboard.php');
    exit();
}

// Get patient's bed information
$stmt = $pdo->prepare("SELECT b.*, p.name as patient_name, p.phone as patient_phone, p.email as patient_email
                       FROM beds b 
                       JOIN patients p ON b.patient_id = p.id 
                       WHERE p.user_id = ?");
$stmt->execute([$user_id]);
$bed_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get patient's basic info
$stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bed - HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .bed-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .info-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .info-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 1.1em;
            padding: 8px 16px;
        }
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            min-height: 100vh;
        }
        .nav-link {
            color: #ecf0f1 !important;
            transition: all 0.3s;
        }
        .nav-link:hover {
            background-color: #34495e;
            color: #3498db !important;
        }
        .nav-link.active {
            background-color: #3498db !important;
            color: white !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white"><i class="fas fa-hospital"></i> HMS</h4>
                        <p class="text-muted">Patient Portal</p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="my-bed.php">
                                <i class="fas fa-bed"></i> My Bed
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-bills.php">
                                <i class="fas fa-file-invoice-dollar"></i> My Bills
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-prescriptions.php">
                                <i class="fas fa-pills"></i> My Prescriptions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my-medical-history.php">
                                <i class="fas fa-notes-medical"></i> Medical History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="ambulance-management.php">
                                <i class="fas fa-ambulance"></i> Ambulance
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-bed"></i> My Bed Information</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="text-muted">Welcome, <?php echo htmlspecialchars($patient['name']); ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($bed_info): ?>
                    <!-- Bed Information -->
                    <div class="bed-info-card">
                        <div class="row">
                            <div class="col-md-8">
                                <h2><i class="fas fa-bed"></i> Ward <?php echo htmlspecialchars($bed_info['ward_number']); ?> - Bed <?php echo htmlspecialchars($bed_info['bed_number']); ?></h2>
                                <p class="lead mb-0">You are currently assigned to this bed</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="badge bg-success status-badge">
                                    <i class="fas fa-check-circle"></i> Assigned
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Bed Details -->
                        <div class="col-md-6 mb-4">
                            <div class="card info-card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Bed Details</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Ward Number:</strong></td>
                                            <td><?php echo htmlspecialchars($bed_info['ward_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Bed Number:</strong></td>
                                            <td><?php echo htmlspecialchars($bed_info['bed_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Bed Type:</strong></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($bed_info['bed_type']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo ucfirst($bed_info['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Admission Information -->
                        <div class="col-md-6 mb-4">
                            <div class="card info-card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Admission Information</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Admission Date:</strong></td>
                                            <td><?php echo date('F d, Y', strtotime($bed_info['admission_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Expected Discharge:</strong></td>
                                            <td><?php echo date('F d, Y', strtotime($bed_info['expected_discharge'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Days Admitted:</strong></td>
                                            <td>
                                                <?php 
                                                $admission = new DateTime($bed_info['admission_date']);
                                                $today = new DateTime();
                                                $interval = $admission->diff($today);
                                                echo $interval->days . ' days';
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Remaining Days:</strong></td>
                                            <td>
                                                <?php 
                                                $discharge = new DateTime($bed_info['expected_discharge']);
                                                $remaining = $today->diff($discharge);
                                                if ($remaining->invert) {
                                                    echo '<span class="text-danger">Overdue</span>';
                                                } else {
                                                    echo $remaining->days . ' days';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Patient Information -->
                        <div class="col-md-6 mb-4">
                            <div class="card info-card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-user"></i> Patient Information</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Name:</strong></td>
                                            <td><?php echo htmlspecialchars($bed_info['patient_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Phone:</strong></td>
                                            <td><?php echo htmlspecialchars($bed_info['patient_phone']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td><?php echo htmlspecialchars($bed_info['patient_email']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Patient ID:</strong></td>
                                            <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <?php if ($bed_info['notes']): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card info-card">
                                    <div class="card-header bg-warning text-dark">
                                        <h5 class="mb-0"><i class="fas fa-sticky-note"></i> Bed Notes</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($bed_info['notes'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card info-card">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="fas fa-tools"></i> Quick Actions</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 mb-2">
                                            <a href="my-bills.php" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-file-invoice-dollar"></i> View Bills
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <a href="my-prescriptions.php" class="btn btn-outline-success w-100">
                                                <i class="fas fa-pills"></i> View Prescriptions
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <a href="my-medical-history.php" class="btn btn-outline-info w-100">
                                                <i class="fas fa-notes-medical"></i> Medical History
                                            </a>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <a href="ambulance-management.php" class="btn btn-outline-warning w-100">
                                                <i class="fas fa-ambulance"></i> Request Ambulance
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- No Bed Assigned -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card info-card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-bed fa-4x text-muted mb-3"></i>
                                    <h3 class="text-muted">No Bed Assigned</h3>
                                    <p class="text-muted">You are not currently assigned to any bed. Please contact the hospital staff for bed assignment.</p>
                                    <div class="mt-4">
                                        <a href="dashboard.php" class="btn btn-primary">
                                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
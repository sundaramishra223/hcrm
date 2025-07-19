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

// Check if user has access to bed management
$allowed_roles = ['admin', 'nurse', 'receptionist', 'doctor'];
if (!in_array($user_role, $allowed_roles)) {
    showErrorAlert('Access Denied: You do not have permission to access bed management.');
    header('Location: dashboard.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_bed':
                $ward_number = $_POST['ward_number'];
                $bed_number = $_POST['bed_number'];
                $bed_type = $_POST['bed_type'];
                $status = 'available';
                
                $stmt = $pdo->prepare("INSERT INTO beds (ward_number, bed_number, bed_type, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                if ($stmt->execute([$ward_number, $bed_number, $bed_type, $status])) {
                    showSuccessAlert('Bed added successfully!');
                } else {
                    showErrorAlert('Failed to add bed. Please try again.');
                }
                break;
                
            case 'assign_patient':
                $bed_id = $_POST['bed_id'];
                $patient_id = $_POST['patient_id'];
                $admission_date = $_POST['admission_date'];
                $expected_discharge = $_POST['expected_discharge'];
                $notes = $_POST['notes'];
                
                // Update bed status
                $stmt = $pdo->prepare("UPDATE beds SET status = 'occupied', patient_id = ?, admission_date = ?, expected_discharge = ?, notes = ? WHERE id = ?");
                if ($stmt->execute([$patient_id, $admission_date, $expected_discharge, $notes, $bed_id])) {
                    showSuccessAlert('Patient assigned to bed successfully!');
                } else {
                    showErrorAlert('Failed to assign patient. Please try again.');
                }
                break;
                
            case 'discharge_patient':
                $bed_id = $_POST['bed_id'];
                
                $stmt = $pdo->prepare("UPDATE beds SET status = 'available', patient_id = NULL, admission_date = NULL, expected_discharge = NULL, notes = NULL WHERE id = ?");
                if ($stmt->execute([$bed_id])) {
                    showSuccessAlert('Patient discharged successfully!');
                } else {
                    showErrorAlert('Failed to discharge patient. Please try again.');
                }
                break;
                
            case 'update_status':
                $bed_id = $_POST['bed_id'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE beds SET status = ? WHERE id = ?");
                if ($stmt->execute([$status, $bed_id])) {
                    showSuccessAlert('Bed status updated successfully!');
                } else {
                    showErrorAlert('Failed to update bed status. Please try again.');
                }
                break;
        }
    }
}

// Get bed statistics
$stats = [];
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_beds,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_beds,
    SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied_beds,
    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_beds
FROM beds");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all beds
$stmt = $pdo->query("SELECT b.*, p.name as patient_name, p.phone as patient_phone 
                     FROM beds b 
                     LEFT JOIN patients p ON b.patient_id = p.id 
                     ORDER BY b.ward_number, b.bed_number");
$beds = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available patients for assignment
$stmt = $pdo->query("SELECT id, name, phone FROM patients WHERE id NOT IN (SELECT patient_id FROM beds WHERE patient_id IS NOT NULL)");
$available_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bed Management - HMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .bed-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .bed-card:hover {
            transform: translateY(-2px);
        }
        .status-available { background-color: #d4edda; border-left: 4px solid #28a745; }
        .status-occupied { background-color: #f8d7da; border-left: 4px solid #dc3545; }
        .status-maintenance { background-color: #fff3cd; border-left: 4px solid #ffc107; }
        .nav-tabs .nav-link.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="beds.php">
                                <i class="fas fa-bed"></i> Bed Management
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-bed"></i> Bed Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBedModal">
                        <i class="fas fa-plus"></i> Add New Bed
                    </button>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h4><?php echo $stats['total_beds']; ?></h4>
                            <p class="mb-0">Total Beds</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                            <h4><?php echo $stats['available_beds']; ?></h4>
                            <p class="mb-0">Available</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
                            <h4><?php echo $stats['occupied_beds']; ?></h4>
                            <p class="mb-0">Occupied</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                            <h4><?php echo $stats['maintenance_beds']; ?></h4>
                            <p class="mb-0">Maintenance</p>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="bedTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                            All Beds
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button" role="tab">
                            Available
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="occupied-tab" data-bs-toggle="tab" data-bs-target="#occupied" type="button" role="tab">
                            Occupied
                        </button>
                    </li>
                </ul>

                <!-- Tab content -->
                <div class="tab-content" id="bedTabsContent">
                    <!-- All Beds -->
                    <div class="tab-pane fade show active" id="all" role="tabpanel">
                        <div class="row mt-3">
                            <?php foreach ($beds as $bed): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card bed-card status-<?php echo $bed['status']; ?>">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                Ward <?php echo htmlspecialchars($bed['ward_number']); ?> - Bed <?php echo htmlspecialchars($bed['bed_number']); ?>
                                            </h5>
                                            <p class="card-text">
                                                <strong>Type:</strong> <?php echo htmlspecialchars($bed['bed_type']); ?><br>
                                                <strong>Status:</strong> 
                                                <span class="badge bg-<?php echo $bed['status'] === 'available' ? 'success' : ($bed['status'] === 'occupied' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($bed['status']); ?>
                                                </span>
                                            </p>
                                            
                                            <?php if ($bed['patient_name']): ?>
                                                <p class="card-text">
                                                    <strong>Patient:</strong> <?php echo htmlspecialchars($bed['patient_name']); ?><br>
                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($bed['patient_phone']); ?><br>
                                                    <strong>Admitted:</strong> <?php echo date('M d, Y', strtotime($bed['admission_date'])); ?><br>
                                                    <strong>Expected Discharge:</strong> <?php echo date('M d, Y', strtotime($bed['expected_discharge'])); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="btn-group" role="group">
                                                <?php if ($bed['status'] === 'available'): ?>
                                                    <button class="btn btn-sm btn-primary" onclick="assignPatient(<?php echo $bed['id']; ?>)">
                                                        <i class="fas fa-user-plus"></i> Assign Patient
                                                    </button>
                                                <?php elseif ($bed['status'] === 'occupied'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="dischargePatient(<?php echo $bed['id']; ?>)">
                                                        <i class="fas fa-user-minus"></i> Discharge
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-sm btn-info" onclick="updateStatus(<?php echo $bed['id']; ?>, '<?php echo $bed['status']; ?>')">
                                                    <i class="fas fa-edit"></i> Update Status
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Available Beds -->
                    <div class="tab-pane fade" id="available" role="tabpanel">
                        <div class="row mt-3">
                            <?php foreach ($beds as $bed): ?>
                                <?php if ($bed['status'] === 'available'): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card bed-card status-available">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    Ward <?php echo htmlspecialchars($bed['ward_number']); ?> - Bed <?php echo htmlspecialchars($bed['bed_number']); ?>
                                                </h5>
                                                <p class="card-text">
                                                    <strong>Type:</strong> <?php echo htmlspecialchars($bed['bed_type']); ?><br>
                                                    <strong>Status:</strong> <span class="badge bg-success">Available</span>
                                                </p>
                                                <button class="btn btn-primary" onclick="assignPatient(<?php echo $bed['id']; ?>)">
                                                    <i class="fas fa-user-plus"></i> Assign Patient
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Occupied Beds -->
                    <div class="tab-pane fade" id="occupied" role="tabpanel">
                        <div class="row mt-3">
                            <?php foreach ($beds as $bed): ?>
                                <?php if ($bed['status'] === 'occupied'): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card bed-card status-occupied">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    Ward <?php echo htmlspecialchars($bed['ward_number']); ?> - Bed <?php echo htmlspecialchars($bed['bed_number']); ?>
                                                </h5>
                                                <p class="card-text">
                                                    <strong>Type:</strong> <?php echo htmlspecialchars($bed['bed_type']); ?><br>
                                                    <strong>Status:</strong> <span class="badge bg-danger">Occupied</span><br>
                                                    <strong>Patient:</strong> <?php echo htmlspecialchars($bed['patient_name']); ?><br>
                                                    <strong>Phone:</strong> <?php echo htmlspecialchars($bed['patient_phone']); ?><br>
                                                    <strong>Admitted:</strong> <?php echo date('M d, Y', strtotime($bed['admission_date'])); ?><br>
                                                    <strong>Expected Discharge:</strong> <?php echo date('M d, Y', strtotime($bed['expected_discharge'])); ?>
                                                </p>
                                                <button class="btn btn-success" onclick="dischargePatient(<?php echo $bed['id']; ?>)">
                                                    <i class="fas fa-user-minus"></i> Discharge Patient
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Bed Modal -->
    <div class="modal fade" id="addBedModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Bed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_bed">
                        
                        <div class="mb-3">
                            <label for="ward_number" class="form-label">Ward Number</label>
                            <input type="number" class="form-control" id="ward_number" name="ward_number" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bed_number" class="form-label">Bed Number</label>
                            <input type="number" class="form-control" id="bed_number" name="bed_number" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bed_type" class="form-label">Bed Type</label>
                            <select class="form-control" id="bed_type" name="bed_type" required>
                                <option value="">Select Bed Type</option>
                                <option value="General">General</option>
                                <option value="Semi-Private">Semi-Private</option>
                                <option value="Private">Private</option>
                                <option value="ICU">ICU</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Bed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Patient Modal -->
    <div class="modal fade" id="assignPatientModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Patient to Bed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_patient">
                        <input type="hidden" name="bed_id" id="assign_bed_id">
                        
                        <div class="mb-3">
                            <label for="patient_id" class="form-label">Select Patient</label>
                            <select class="form-control" id="patient_id" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($available_patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['name']); ?> (<?php echo htmlspecialchars($patient['phone']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admission_date" class="form-label">Admission Date</label>
                            <input type="date" class="form-control" id="admission_date" name="admission_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="expected_discharge" class="form-label">Expected Discharge Date</label>
                            <input type="date" class="form-control" id="expected_discharge" name="expected_discharge" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Bed Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="bed_id" id="update_bed_id">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Bed Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function assignPatient(bedId) {
            document.getElementById('assign_bed_id').value = bedId;
            new bootstrap.Modal(document.getElementById('assignPatientModal')).show();
        }
        
        function dischargePatient(bedId) {
            if (confirm('Are you sure you want to discharge this patient?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="discharge_patient">
                    <input type="hidden" name="bed_id" value="${bedId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function updateStatus(bedId, currentStatus) {
            document.getElementById('update_bed_id').value = bedId;
            document.getElementById('status').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }
        
        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('admission_date').value = today;
            
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            document.getElementById('expected_discharge').value = nextWeek.toISOString().split('T')[0];
        });
    </script>
</body>
</html>
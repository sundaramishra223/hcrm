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
if (!in_array($user_role, ['admin', 'doctor', 'receptionist', 'intern_doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';
$error_message = '';

// Handle form submission for new appointment
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_appointment') {
    try {
        // Basic validation
        if (empty($_POST['patient_id']) || empty($_POST['doctor_id']) || empty($_POST['appointment_date']) || empty($_POST['appointment_time'])) {
            $error_message = "Patient, Doctor, Date and Time are required!";
        } else {
            // Generate appointment ID
            $appointment_count = $db->query("SELECT COUNT(*) as count FROM appointments")->fetch()['count'];
            $appointment_id = 'APT' . str_pad($appointment_count + 1, 4, '0', STR_PAD_LEFT);
            
            // Insert appointment
            $db->query(
                "INSERT INTO appointments (appointment_id, patient_id, doctor_id, appointment_date, appointment_time, appointment_type, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $appointment_id,
                    $_POST['patient_id'],
                    $_POST['doctor_id'],
                    $_POST['appointment_date'],
                    $_POST['appointment_time'],
                    $_POST['appointment_type'] ?? 'consultation',
                    $_POST['reason'] ?? '',
                    $_SESSION['user_id']
                ]
            );
            
            $message = "Appointment booked successfully! Appointment ID: $appointment_id";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle appointment status update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $db->query(
            "UPDATE appointments SET status = ? WHERE id = ?",
            [$_POST['status'], $_POST['appointment_id']]
        );
        $message = "Appointment status updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get appointments list
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$appointments_query = "
    SELECT a.*, 
           p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id,
           d.doctor_name 
    FROM appointments a 
    LEFT JOIN patients p ON a.patient_id = p.id 
    LEFT JOIN doctors d ON a.doctor_id = d.id 
    WHERE 1=1";
$count_query = "
    SELECT COUNT(*) as count 
    FROM appointments a 
    LEFT JOIN patients p ON a.patient_id = p.id 
    LEFT JOIN doctors d ON a.doctor_id = d.id 
    WHERE 1=1";
$params = [];

if (!empty($search)) {
    $search_condition = " AND (a.appointment_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR d.doctor_name LIKE ?)";
    $appointments_query .= $search_condition;
    $count_query .= $search_condition;
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $appointments_query .= " AND a.status = ?";
    $count_query .= " AND a.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $appointments_query .= " AND DATE(a.appointment_date) = ?";
    $count_query .= " AND DATE(a.appointment_date) = ?";
    $params[] = $date_filter;
}

$total_appointments = $db->query($count_query, $params)->fetch()['count'];
$total_pages = ceil($total_appointments / $limit);

$appointments_query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT $limit OFFSET $offset";
$appointments = $db->query($appointments_query, $params)->fetchAll();

// Get patients and doctors for form
$patients = $db->query("SELECT id, patient_id, first_name, last_name FROM patients WHERE is_active = 1 ORDER BY first_name")->fetchAll();
$doctors = $db->query("SELECT id, doctor_name FROM doctors WHERE is_active = 1 ORDER BY doctor_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Management - Hospital CRM</title>
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
                <li><a href="appointments.php" class="active"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="blood-bank.php"><i class="fas fa-tint"></i> Blood Bank</a></li>
                <li><a href="organ-donation.php"><i class="fas fa-heart"></i> Organ Donation</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-calendar-alt"></i> Appointments Management</h1>
                    <p>Manage patient appointments and scheduling</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
            <!-- Book New Appointment -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-plus"></i> Book New Appointment</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add_appointment">
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="patient_id">Patient *</label>
                                <select id="patient_id" name="patient_id" class="form-control" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>">
                                            <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="doctor_id">Doctor *</label>
                                <select id="doctor_id" name="doctor_id" class="form-control" required>
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['id']; ?>">
                                            <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="appointment_date">Appointment Date *</label>
                                <input type="date" id="appointment_date" name="appointment_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="appointment_time">Appointment Time *</label>
                                <input type="time" id="appointment_time" name="appointment_time" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="appointment_type">Appointment Type</label>
                                <select id="appointment_type" name="appointment_type" class="form-control">
                                    <option value="consultation">Consultation</option>
                                    <option value="follow_up">Follow-up</option>
                                    <option value="emergency">Emergency</option>
                                    <option value="routine_checkup">Routine Checkup</option>
                                    <option value="vaccination">Vaccination</option>
                                    <option value="surgery">Surgery</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="reason">Reason</label>
                                <input type="text" id="reason" name="reason" class="form-control" placeholder="Brief reason for visit">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-calendar-plus"></i> Book Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Appointments List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Appointments List (<?php echo $total_appointments; ?>)</h3>
                </div>
                <div class="card-body">
                    <!-- Search and Filters -->
                    <div class="search-box">
                        <form method="GET">
                            <div class="grid grid-4">
                                <div class="form-group">
                                    <input type="text" name="search" placeholder="Search appointments..." class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group">
                                    <select name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="scheduled" <?php echo $status_filter == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        <option value="no_show" <?php echo $status_filter == 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date_filter); ?>">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="appointments.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (empty($appointments)): ?>
                        <p class="text-muted text-center">No appointments found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Appointment ID</th>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Reason</th>
                                        <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist'])): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($appointment['appointment_id']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($appointment['patient_id'] . ' - ' . $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $appointment['appointment_type']))); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $appointment['status'] == 'scheduled' ? 'info' : 
                                                        ($appointment['status'] == 'completed' ? 'success' : 
                                                        ($appointment['status'] == 'cancelled' ? 'danger' : 'warning')); 
                                                ?>">
                                                    <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['reason']); ?></td>
                                            <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist'])): ?>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                        <select name="status" class="form-control" style="width: auto; display: inline-block; font-size: 12px;" onchange="this.form.submit()">
                                                            <option value="scheduled" <?php echo $appointment['status'] == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                            <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                            <option value="no_show" <?php echo $appointment['status'] == 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                                        </select>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="text-center mt-3">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $date_filter ? '&date=' . urlencode($date_filter) : ''; ?>" 
                                       class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>" 
                                       style="margin: 0 2px; padding: 5px 10px; font-size: 12px;">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-focus on patient field
        document.getElementById('patient_id')?.focus();
    </script>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';

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

// Handle form submission for new appointment
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_appointment') {
    try {
        // Basic validation
        if (empty($_POST['patient_id']) || empty($_POST['doctor_id']) || empty($_POST['appointment_date']) || empty($_POST['appointment_time'])) {
            $message = "❌ Patient, Doctor, Date and Time are required!";
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
            
            $message = "✅ Appointment booked successfully! Appointment ID: $appointment_id";
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Get appointments list
$search = $_GET['search'] ?? '';
$appointments_query = "
    SELECT a.*, 
           p.first_name as patient_first_name, p.last_name as patient_last_name, p.patient_id,
           d.doctor_name 
    FROM appointments a 
    LEFT JOIN patients p ON a.patient_id = p.id 
    LEFT JOIN doctors d ON a.doctor_id = d.id 
    WHERE 1=1";
$params = [];

if (!empty($search)) {
    $appointments_query .= " AND (a.appointment_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR d.doctor_name LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

$appointments_query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$appointments = $db->query($appointments_query, $params)->fetchAll();

// Get patients and doctors for form
$patients = $db->query("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name")->fetchAll();
$doctors = $db->query("SELECT id, doctor_name FROM doctors WHERE is_active = 1 ORDER BY doctor_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Management - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .search-box { margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-scheduled { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calendar-alt"></i> Appointments Management</h1>
            <p>Manage patient appointments and scheduling</p>
            <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
        <div class="card">
            <h2><i class="fas fa-calendar-plus"></i> Book New Appointment</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_appointment">
                
                <div class="grid">
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
                
                <div class="grid">
                    <div class="form-group">
                        <label for="appointment_date">Appointment Date *</label>
                        <input type="date" id="appointment_date" name="appointment_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="appointment_time">Appointment Time *</label>
                        <input type="time" id="appointment_time" name="appointment_time" class="form-control" required>
                    </div>
                </div>
                
                <div class="grid">
                    <div class="form-group">
                        <label for="appointment_type">Appointment Type</label>
                        <select id="appointment_type" name="appointment_type" class="form-control">
                            <option value="consultation">Consultation</option>
                            <option value="follow_up">Follow-up</option>
                            <option value="emergency">Emergency</option>
                            <option value="routine_checkup">Routine Checkup</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reason">Reason</label>
                        <input type="text" id="reason" name="reason" class="form-control" placeholder="Brief reason for visit">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="search-box">
                <form method="GET">
                    <div class="grid">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search appointments..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if ($search): ?>
                                <a href="simple-appointments.php" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <h2><i class="fas fa-list"></i> Appointments List (<?php echo count($appointments); ?>)</h2>
            
            <?php if (empty($appointments)): ?>
                <p>No appointments found.</p>
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
                                        <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                            <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['reason']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
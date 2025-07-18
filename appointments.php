<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$user_role = $_SESSION['role'];
$message = '';

// Check permissions
if (!in_array($user_role, ['admin', 'doctor', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

// Handle status updates
if ($_POST && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_status') {
            $appointment_id = $_POST['appointment_id'];
            $new_status = $_POST['status'];
            
            $db->query(
                "UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?",
                [$new_status, $appointment_id]
            );
            
            $message = "Appointment status updated successfully!";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_status = $_GET['status'] ?? '';
$filter_doctor = $_GET['doctor'] ?? '';

// Build query based on user role
$sql = "SELECT a.*, 
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.phone as patient_phone,
        p.patient_id,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        d.specialization,
        dept.name as department_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN departments dept ON d.department_id = dept.id
        WHERE a.hospital_id = 1";

$params = [];

// Role-based filtering
if ($user_role === 'doctor') {
    // Doctors can only see their own appointments
    $doctor_info = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch();
    if ($doctor_info) {
        $sql .= " AND a.doctor_id = ?";
        $params[] = $doctor_info['id'];
    }
}

// Apply filters
if ($filter_date) {
    $sql .= " AND a.appointment_date = ?";
    $params[] = $filter_date;
}

if ($filter_status) {
    $sql .= " AND a.status = ?";
    $params[] = $filter_status;
}

if ($filter_doctor && $user_role !== 'doctor') {
    $sql .= " AND a.doctor_id = ?";
    $params[] = $filter_doctor;
}

$sql .= " ORDER BY a.appointment_date, a.appointment_time";

$appointments = $db->query($sql, $params)->fetchAll();

// Get available doctors for filter (admin/receptionist only)
$doctors = [];
if (in_array($user_role, ['admin', 'receptionist'])) {
    $doctors = $db->query("
        SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
        FROM doctors 
        WHERE hospital_id = 1 AND is_available = 1
        ORDER BY first_name, last_name
    ")->fetchAll();
}

// Get appointment statistics
$stats = [];
try {
    if ($user_role === 'doctor') {
        $doctor_info = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch();
        if ($doctor_info) {
            $stats['today'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()", [$doctor_info['id']])->fetch()['count'];
            $stats['pending'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'scheduled'", [$doctor_info['id']])->fetch()['count'];
            $stats['completed'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'completed' AND appointment_date = CURDATE()", [$doctor_info['id']])->fetch()['count'];
        }
    } else {
        $stats['today'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()")->fetch()['count'];
        $stats['pending'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled'")->fetch()['count'];
        $stats['completed'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed' AND appointment_date = CURDATE()")->fetch()['count'];
    }
} catch (Exception $e) {
    $stats = ['today' => 0, 'pending' => 0, 'completed' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Hospital CRM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #004685;
            font-size: 24px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn-primary {
            background: #004685;
            color: white;
        }
        
        .btn-primary:hover {
            background: #003366;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            font-size: 28px;
            color: #004685;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters h3 {
            color: #004685;
            margin-bottom: 15px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .filter-form .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .appointments-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .table td {
            font-size: 14px;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-confirmed {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .badge-completed {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .badge-cancelled {
            background: #ffebee;
            color: #c62828;
        }
        
        .badge-no-show {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .appointment-time {
            font-weight: 600;
            color: #004685;
        }
        
        .patient-info {
            line-height: 1.4;
        }
        
        .patient-info .name {
            font-weight: 600;
            color: #333;
        }
        
        .patient-info .details {
            font-size: 12px;
            color: #666;
        }
        
        .doctor-info {
            line-height: 1.4;
        }
        
        .doctor-info .name {
            font-weight: 600;
            color: #333;
        }
        
        .doctor-info .specialization {
            font-size: 12px;
            color: #666;
        }
        
        .status-select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 12px;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table th, .table td {
                padding: 8px;
            }
            
            .table .hide-mobile {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <?php echo $user_role === 'doctor' ? 'My Appointments' : 'Appointments Management'; ?>
            </h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <a href="book-appointment.php" class="btn btn-primary">+ Book Appointment</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['today']; ?></h3>
                <p>Today's Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['pending']; ?></h3>
                <p>Pending Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['completed']; ?></h3>
                <p>Completed Today</p>
            </div>
        </div>
        
        <div class="filters">
            <h3>Filter Appointments</h3>
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Statuses</option>
                        <option value="scheduled" <?php echo $filter_status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="no_show" <?php echo $filter_status === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                    </select>
                </div>
                
                <?php if (!empty($doctors)): ?>
                <div class="form-group">
                    <label for="doctor">Doctor</label>
                    <select name="doctor" id="doctor">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" 
                                    <?php echo $filter_doctor == $doctor['id'] ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="appointments.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
        
        <div class="appointments-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Patient</th>
                        <?php if ($user_role !== 'doctor'): ?>
                            <th>Doctor</th>
                        <?php endif; ?>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="hide-mobile">Chief Complaint</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="<?php echo $user_role === 'doctor' ? '6' : '7'; ?>" style="text-align: center; padding: 30px; color: #666;">
                                No appointments found for the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td class="appointment-time">
                                    <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                                    <br>
                                    <small style="color: #666;">
                                        <?php echo date('M d', strtotime($appointment['appointment_date'])); ?>
                                    </small>
                                </td>
                                <td class="patient-info">
                                    <div class="name"><?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                    <div class="details">
                                        ID: <?php echo htmlspecialchars($appointment['patient_id']); ?><br>
                                        <?php echo htmlspecialchars($appointment['patient_phone']); ?>
                                    </div>
                                </td>
                                <?php if ($user_role !== 'doctor'): ?>
                                <td class="doctor-info">
                                    <div class="name">Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></div>
                                    <div class="specialization"><?php echo htmlspecialchars($appointment['specialization'] ?? 'General'); ?></div>
                                </td>
                                <?php endif; ?>
                                <td><?php echo ucfirst(str_replace('_', ' ', $appointment['type'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                    </span>
                                </td>
                                <td class="hide-mobile" style="max-width: 200px;">
                                    <?php echo htmlspecialchars(substr($appointment['chief_complaint'] ?? 'N/A', 0, 50)); ?>
                                    <?php if (strlen($appointment['chief_complaint'] ?? '') > 50): ?>...<?php endif; ?>
                                </td>
                                <td>
                                    <?php if (in_array($appointment['status'], ['scheduled', 'confirmed'])): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                            <select name="status" class="status-select" onchange="this.form.submit()">
                                                <option value="scheduled" <?php echo $appointment['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                <option value="confirmed" <?php echo $appointment['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $appointment['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="no_show" <?php echo $appointment['status'] === 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                            </select>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

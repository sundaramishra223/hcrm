<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$patient_id = $_SESSION['user_id'];

// Get patient details
$patient = $db->query("SELECT * FROM patients WHERE user_id = ?", [$patient_id])->fetch();

if (!$patient) {
    header('Location: dashboard.php');
    exit;
}

// Get patient's appointments
$sql = "SELECT a.*, 
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        d.specialization,
        d.consultation_fee
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$appointments = $db->query($sql, [$patient['id']])->fetchAll();

// Get appointment statistics
$stats = [];
try {
    $stats['total_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?", [$patient['id']])->fetch()['count'];
    $stats['upcoming_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND appointment_date >= CURDATE() AND status IN ('scheduled', 'confirmed')", [$patient['id']])->fetch()['count'];
    $stats['completed_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'completed'", [$patient['id']])->fetch()['count'];
    $stats['cancelled_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'cancelled'", [$patient['id']])->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_appointments' => 0, 'upcoming_appointments' => 0, 'completed_appointments' => 0, 'cancelled_appointments' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Hospital CRM</title>
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
            color: #004685;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
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
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-scheduled { background: #ffc107; color: black; }
        .status-confirmed { background: #17a2b8; color: white; }
        .status-in_progress { background: #007bff; color: white; }
        .status-completed { background: #28a745; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
        .status-no_show { background: #6c757d; color: white; }
        
        .no-appointments {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-appointments i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-calendar-check"></i> My Appointments</h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_appointments']); ?></h3>
                <p>Total Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['upcoming_appointments']); ?></h3>
                <p>Upcoming Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['completed_appointments']); ?></h3>
                <p>Completed Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['cancelled_appointments']); ?></h3>
                <p>Cancelled Appointments</p>
            </div>
        </div>

        <!-- Appointments Table -->
        <div class="appointments-table">
            <?php if (empty($appointments)): ?>
                <div class="no-appointments">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Appointments Found</h3>
                    <p>You don't have any appointments yet.</p>
                    <a href="book-appointment.php" class="btn btn-primary">Book Your First Appointment</a>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Fee</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></strong>
                                    <br><small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($appointment['doctor_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($appointment['specialization']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $appointment['appointment_type'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                    </span>
                                </td>
                                <td>₹<?php echo number_format($appointment['consultation_fee'], 2); ?></td>
                                <td><?php echo htmlspecialchars($appointment['reason'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
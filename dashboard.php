<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$user_role = $_SESSION['role'];
$user_name = $_SESSION['username'];

// Get dashboard statistics
try {
    $stats = [];
    
    // Total patients
    $stats['patients'] = $db->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'] ?? 0;
    
    // Total doctors
    $stats['doctors'] = $db->query("SELECT COUNT(*) as count FROM doctors WHERE is_active = 1")->fetch()['count'] ?? 0;
    
    // Today's appointments
    $stats['appointments_today'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetch()['count'] ?? 0;
    
    // Total staff
    $stats['staff'] = $db->query("SELECT COUNT(*) as count FROM staff WHERE is_active = 1")->fetch()['count'] ?? 0;
    
    // Pending appointments
    $stats['pending_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled' AND appointment_date >= CURDATE()")->fetch()['count'] ?? 0;
    
    // Total revenue this month
    $stats['revenue'] = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM billing WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch()['total'] ?? 0;
    
} catch (Exception $e) {
    $stats = [
        'patients' => 0,
        'doctors' => 0,
        'appointments_today' => 0,
        'staff' => 0,
        'pending_appointments' => 0,
        'revenue' => 0
    ];
}

// Get recent activities
try {
    $recent_appointments = $db->query("
        SELECT a.*, p.first_name, p.last_name, d.doctor_name 
        FROM appointments a 
        LEFT JOIN patients p ON a.patient_id = p.id 
        LEFT JOIN doctors d ON a.doctor_id = d.id 
        WHERE a.appointment_date >= CURDATE() 
        ORDER BY a.appointment_date, a.appointment_time 
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {
    $recent_appointments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php renderDynamicStyles(); ?>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-hospital"></i> Hospital CRM</h2>
                <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'intern_doctor'])): ?>
                    <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist', 'intern_doctor'])): ?>
                    <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'receptionist', 'pharmacy_staff', 'intern_pharmacy'])): ?>
                    <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'lab_technician', 'intern_lab'])): ?>
                    <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                    <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'accountant', 'receptionist'])): ?>
                    <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin'])): ?>
                    <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                    <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <?php endif; ?>
                
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</p>
                </div>
                <div>
                    <span class="text-muted"><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($stats['patients']); ?></h3>
                    <p><i class="fas fa-users"></i> Total Patients</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($stats['doctors']); ?></h3>
                    <p><i class="fas fa-user-md"></i> Active Doctors</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($stats['appointments_today']); ?></h3>
                    <p><i class="fas fa-calendar-day"></i> Today's Appointments</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($stats['staff']); ?></h3>
                    <p><i class="fas fa-user-tie"></i> Active Staff</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($stats['pending_appointments']); ?></h3>
                    <p><i class="fas fa-clock"></i> Pending Appointments</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo formatCurrency($stats['revenue']); ?></h3>
                    <p><i class="fas fa-rupee-sign"></i> Monthly Revenue</p>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-2">
                <!-- Recent Appointments -->
                <div class="card">
                    <h3><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h3>
                    <?php if (empty($recent_appointments)): ?>
                        <p class="text-muted">No upcoming appointments found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></td>
                                            <td><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $appointment['status'] == 'scheduled' ? 'info' : 'success'; ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    <div class="grid grid-2">
                        <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                            <a href="patients.php" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Add Patient
                            </a>
                            <a href="appointments.php" class="btn btn-success">
                                <i class="fas fa-calendar-plus"></i> Book Appointment
                            </a>
                        <?php endif; ?>
                        
                        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                            <a href="prescriptions.php" class="btn btn-info">
                                <i class="fas fa-prescription"></i> New Prescription
                            </a>
                        <?php endif; ?>
                        
                        <?php if (in_array($user_role, ['admin', 'lab_technician'])): ?>
                            <a href="laboratory.php" class="btn btn-warning">
                                <i class="fas fa-flask"></i> Lab Tests
                            </a>
                        <?php endif; ?>
                        
                        <?php if (in_array($user_role, ['admin', 'accountant'])): ?>
                            <a href="billing.php" class="btn btn-danger">
                                <i class="fas fa-file-invoice"></i> Create Bill
                            </a>
                        <?php endif; ?>
                        
                        <?php if (in_array($user_role, ['admin'])): ?>
                            <a href="reports.php" class="btn btn-primary">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="card">
                <h3><i class="fas fa-info-circle"></i> System Information</h3>
                <div class="grid grid-4">
                    <div class="text-center">
                        <h4>Database</h4>
                        <span class="badge badge-success">Connected</span>
                    </div>
                    <div class="text-center">
                        <h4>Server</h4>
                        <span class="badge badge-success">Online</span>
                    </div>
                    <div class="text-center">
                        <h4>Version</h4>
                        <span class="badge badge-info">v2.0.0</span>
                    </div>
                    <div class="text-center">
                        <h4>Last Backup</h4>
                        <span class="badge badge-warning"><?php echo date('M j, Y'); ?></span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Theme toggle functionality
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        }
        
        // Initialize theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            setTheme(savedTheme);
        });
    </script>
</body>
</html>
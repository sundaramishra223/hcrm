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
    $result = $db->query("SELECT COUNT(*) as count FROM patients WHERE is_active = 1")->fetch();
    $stats['patients'] = $result['count'];
    
    // Active doctors
    $result = $db->query("SELECT COUNT(*) as count FROM doctors WHERE is_active = 1")->fetch();
    $stats['doctors'] = $result['count'];
    
    // Today's appointments
    $result = $db->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetch();
    $stats['appointments_today'] = $result['count'];
    
    // Active staff
    $result = $db->query("SELECT COUNT(*) as count FROM staff WHERE is_active = 1")->fetch();
    $stats['staff'] = $result['count'];
    
    // Pending appointments
    $result = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled' AND appointment_date >= CURDATE()")->fetch();
    $stats['pending_appointments'] = $result['count'];
    
    // Monthly revenue (current month)
    $result = $db->query("SELECT COALESCE(SUM(paid_amount), 0) as revenue FROM billing WHERE MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE())")->fetch();
    $stats['revenue'] = $result['revenue'];
    
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

// Get upcoming appointments
try {
    $upcoming_appointments = $db->query("
        SELECT a.*, 
               p.first_name as patient_first_name, p.last_name as patient_last_name,
               d.doctor_name 
        FROM appointments a 
        LEFT JOIN patients p ON a.patient_id = p.id 
        LEFT JOIN doctors d ON a.doctor_id = d.id 
        WHERE a.appointment_date >= CURDATE() 
        ORDER BY a.appointment_date ASC, a.appointment_time ASC 
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {
    $upcoming_appointments = [];
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
                
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                    <li><a href="insurance.php"><i class="fas fa-shield-alt"></i> Insurance</a></li>
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
                    <h1><i class="fas fa-home"></i> Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</p>
                </div>
                <div class="header-actions">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($user_name); ?></div>
                            <div style="font-size: 0.875rem; color: #64748b;"><?php echo htmlspecialchars($_SESSION['role_display']); ?></div>
                        </div>
                    </div>
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

            <!-- Quick Actions -->
            <div class="quick-actions">
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <a href="patients.php" class="quick-action">
                        <i class="fas fa-user-plus"></i>
                        <h4>Add Patient</h4>
                        <p>Register new patient</p>
                    </a>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist', 'intern_doctor'])): ?>
                    <a href="appointments.php" class="quick-action">
                        <i class="fas fa-calendar-plus"></i>
                        <h4>Book Appointment</h4>
                        <p>Schedule new appointment</p>
                    </a>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                    <a href="prescriptions.php" class="quick-action">
                        <i class="fas fa-prescription-bottle-alt"></i>
                        <h4>New Prescription</h4>
                        <p>Create prescription</p>
                    </a>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <a href="billing.php" class="quick-action">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <h4>Generate Bill</h4>
                        <p>Create new bill</p>
                    </a>
                    <a href="insurance.php" class="quick-action">
                        <i class="fas fa-shield-alt"></i>
                        <h4>Insurance Claims</h4>
                        <p>Manage insurance</p>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Upcoming Appointments -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($upcoming_appointments)): ?>
                        <p class="text-muted text-center">No upcoming appointments found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></td>
                                            <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $appointment['status'] == 'scheduled' ? 'info' : ($appointment['status'] == 'completed' ? 'success' : 'warning'); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Information -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> System Information</h3>
                </div>
                <div class="card-body">
                    <div class="grid grid-2">
                        <div>
                            <p><strong>System Version:</strong> Hospital CRM v2.0</p>
                            <p><strong>Database:</strong> MySQL <?php echo $db->query("SELECT VERSION() as version")->fetch()['version'] ?? 'Unknown'; ?></p>
                            <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                        </div>
                        <div>
                            <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                            <p><strong>Your Role:</strong> <?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
                            <p><strong>Last Login:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
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
            updateThemeToggle(theme);
        }

        function toggleTheme() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            setTheme(newTheme);
        }

        function updateThemeToggle(activeTheme) {
            const themeToggle = document.querySelector('.theme-toggle');
            if (themeToggle) {
                themeToggle.innerHTML = activeTheme === 'light' 
                    ? '<i class="fas fa-moon"></i>' 
                    : '<i class="fas fa-sun"></i>';
            }
        }
        
        // Initialize theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            setTheme(savedTheme);
        });
    </script>
</body>
</html>
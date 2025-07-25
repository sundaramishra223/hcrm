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

// Get dashboard statistics based on role
try {
    $stats = [];
    
    if (in_array($user_role, ['admin', 'receptionist'])) {
        // Full statistics for admin and receptionist
        $result = $db->query("SELECT COUNT(*) as count FROM patients WHERE is_active = 1")->fetch();
        $stats['patients'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM doctors WHERE is_active = 1")->fetch();
        $stats['doctors'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetch();
        $stats['appointments_today'] = $result['count'];
        
        if ($user_role === 'admin') {
            // Only admin sees staff and revenue
            $result = $db->query("SELECT COUNT(*) as count FROM staff WHERE is_active = 1")->fetch();
            $stats['staff'] = $result['count'];
            
            $result = $db->query("SELECT COALESCE(SUM(paid_amount), 0) as revenue FROM billing WHERE MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE())")->fetch();
            $stats['revenue'] = $result['revenue'];
        }
        
        $result = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled' AND appointment_date >= CURDATE()")->fetch();
        $stats['pending_appointments'] = $result['count'];
        
    } elseif (in_array($user_role, ['doctor', 'intern_doctor'])) {
        // Doctor-specific statistics
        $result = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?) AND DATE(appointment_date) = CURDATE()", [$_SESSION['user_id']])->fetch();
        $stats['my_appointments_today'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?) AND status = 'scheduled' AND appointment_date >= CURDATE()", [$_SESSION['user_id']])->fetch();
        $stats['my_pending_appointments'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = (SELECT id FROM doctors WHERE user_id = ?) AND MONTH(created_at) = MONTH(CURDATE())", [$_SESSION['user_id']])->fetch();
        $stats['my_prescriptions_month'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM patients WHERE is_active = 1")->fetch();
        $stats['total_patients'] = $result['count'];
        
    } elseif ($user_role === 'patient') {
        // Patient-specific statistics
        $patient_id = $db->query("SELECT id FROM patients WHERE email = (SELECT email FROM users WHERE id = ?)", [$_SESSION['user_id']])->fetch()['id'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?", [$patient_id])->fetch();
        $stats['my_appointments'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND appointment_date >= CURDATE()", [$patient_id])->fetch();
        $stats['upcoming_appointments'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM billing WHERE patient_id = ? AND payment_status = 'pending'", [$patient_id])->fetch();
        $stats['pending_bills'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = ?", [$patient_id])->fetch();
        $stats['my_prescriptions'] = $result['count'];
        
    } elseif (in_array($user_role, ['pharmacy_staff', 'intern_pharmacy'])) {
        // Pharmacy staff statistics
        $result = $db->query("SELECT COUNT(*) as count FROM pharmacy_sales WHERE DATE(sale_date) = CURDATE()")->fetch();
        $stats['sales_today'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM pharmacy WHERE stock_quantity <= reorder_level")->fetch();
        $stats['low_stock'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'pending'")->fetch();
        $stats['pending_prescriptions'] = $result['count'];
        
        $result = $db->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM pharmacy_sales WHERE MONTH(sale_date) = MONTH(CURDATE())")->fetch();
        $stats['pharmacy_revenue'] = $result['revenue'];
        
    } elseif (in_array($user_role, ['lab_technician', 'intern_lab'])) {
        // Lab technician statistics
        $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE DATE(test_date) = CURDATE()")->fetch();
        $stats['tests_today'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'")->fetch();
        $stats['pending_tests'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'completed' AND MONTH(test_date) = MONTH(CURDATE())")->fetch();
        $stats['completed_month'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM laboratory WHERE is_active = 1")->fetch();
        $stats['available_tests'] = $result['count'];
    }
    
} catch (Exception $e) {
    $stats = [];
}

// Get role-appropriate upcoming appointments
try {
    if ($user_role === 'patient') {
        $patient_id = $db->query("SELECT id FROM patients WHERE email = (SELECT email FROM users WHERE id = ?)", [$_SESSION['user_id']])->fetch()['id'];
        $upcoming_appointments = $db->query("
            SELECT a.*, 
                   d.doctor_name 
            FROM appointments a 
            LEFT JOIN doctors d ON a.doctor_id = d.id 
            WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() 
            ORDER BY a.appointment_date ASC, a.appointment_time ASC 
            LIMIT 5
        ", [$patient_id])->fetchAll();
    } elseif (in_array($user_role, ['doctor', 'intern_doctor'])) {
        $upcoming_appointments = $db->query("
            SELECT a.*, 
                   p.first_name as patient_first_name, p.last_name as patient_last_name
            FROM appointments a 
            LEFT JOIN patients p ON a.patient_id = p.id 
            WHERE a.doctor_id = (SELECT id FROM doctors WHERE user_id = ?) AND a.appointment_date >= CURDATE() 
            ORDER BY a.appointment_date ASC, a.appointment_time ASC 
            LIMIT 5
        ", [$_SESSION['user_id']])->fetchAll();
    } else {
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
    }
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
                
                <?php if ($user_role === 'patient'): ?>
                    <li><a href="patient-portal.php"><i class="fas fa-user-circle"></i> My Portal</a></li>
                <?php endif; ?>
                
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
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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

            <!-- Role-based Statistics Cards -->
            <div class="stats-grid">
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['patients'] ?? 0); ?></h3>
                        <p><i class="fas fa-users"></i> Total Patients</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['doctors'] ?? 0); ?></h3>
                        <p><i class="fas fa-user-md"></i> Active Doctors</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['appointments_today'] ?? 0); ?></h3>
                        <p><i class="fas fa-calendar-day"></i> Today's Appointments</p>
                    </div>
                    <?php if ($user_role === 'admin'): ?>
                        <div class="stat-card">
                            <h3><?php echo number_format($stats['staff'] ?? 0); ?></h3>
                            <p><i class="fas fa-user-tie"></i> Active Staff</p>
                        </div>
                    <?php endif; ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_appointments'] ?? 0); ?></h3>
                        <p><i class="fas fa-clock"></i> Pending Appointments</p>
                    </div>
                    <?php if ($user_role === 'admin'): ?>
                        <div class="stat-card">
                            <h3><?php echo formatCurrency($stats['revenue'] ?? 0); ?></h3>
                            <p><i class="fas fa-rupee-sign"></i> Monthly Revenue</p>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif (in_array($user_role, ['doctor', 'intern_doctor'])): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_appointments_today'] ?? 0); ?></h3>
                        <p><i class="fas fa-calendar-day"></i> My Appointments Today</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_pending_appointments'] ?? 0); ?></h3>
                        <p><i class="fas fa-clock"></i> My Pending Appointments</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_prescriptions_month'] ?? 0); ?></h3>
                        <p><i class="fas fa-prescription-bottle-alt"></i> Prescriptions This Month</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_patients'] ?? 0); ?></h3>
                        <p><i class="fas fa-users"></i> Total Patients</p>
                    </div>
                    
                <?php elseif ($user_role === 'patient'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_appointments'] ?? 0); ?></h3>
                        <p><i class="fas fa-calendar-alt"></i> My Appointments</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['upcoming_appointments'] ?? 0); ?></h3>
                        <p><i class="fas fa-calendar-check"></i> Upcoming Appointments</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_bills'] ?? 0); ?></h3>
                        <p><i class="fas fa-file-invoice-dollar"></i> Pending Bills</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_prescriptions'] ?? 0); ?></h3>
                        <p><i class="fas fa-prescription-bottle-alt"></i> My Prescriptions</p>
                    </div>
                    
                <?php elseif (in_array($user_role, ['pharmacy_staff', 'intern_pharmacy'])): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['sales_today'] ?? 0); ?></h3>
                        <p><i class="fas fa-shopping-cart"></i> Sales Today</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['low_stock'] ?? 0); ?></h3>
                        <p><i class="fas fa-exclamation-triangle"></i> Low Stock Items</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_prescriptions'] ?? 0); ?></h3>
                        <p><i class="fas fa-clock"></i> Pending Prescriptions</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo formatCurrency($stats['pharmacy_revenue'] ?? 0); ?></h3>
                        <p><i class="fas fa-rupee-sign"></i> Monthly Revenue</p>
                    </div>
                    
                <?php elseif (in_array($user_role, ['lab_technician', 'intern_lab'])): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['tests_today'] ?? 0); ?></h3>
                        <p><i class="fas fa-flask"></i> Tests Today</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_tests'] ?? 0); ?></h3>
                        <p><i class="fas fa-clock"></i> Pending Tests</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['completed_month'] ?? 0); ?></h3>
                        <p><i class="fas fa-check-circle"></i> Completed This Month</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['available_tests'] ?? 0); ?></h3>
                        <p><i class="fas fa-list"></i> Available Tests</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Role-based Quick Actions -->
            <div class="quick-actions">
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <a href="patients.php" class="quick-action">
                        <i class="fas fa-user-plus"></i>
                        <h4>Add Patient</h4>
                        <p>Register new patient</p>
                    </a>
                    <a href="appointments.php" class="quick-action">
                        <i class="fas fa-calendar-plus"></i>
                        <h4>Book Appointment</h4>
                        <p>Schedule new appointment</p>
                    </a>
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
                
                <?php if (in_array($user_role, ['doctor', 'intern_doctor'])): ?>
                    <a href="appointments.php" class="quick-action">
                        <i class="fas fa-calendar-check"></i>
                        <h4>My Appointments</h4>
                        <p>View scheduled appointments</p>
                    </a>
                    <a href="prescriptions.php" class="quick-action">
                        <i class="fas fa-prescription-bottle-alt"></i>
                        <h4>New Prescription</h4>
                        <p>Create prescription</p>
                    </a>
                    <a href="laboratory.php" class="quick-action">
                        <i class="fas fa-flask"></i>
                        <h4>Order Lab Test</h4>
                        <p>Create lab order</p>
                    </a>
                <?php endif; ?>
                
                <?php if ($user_role === 'patient'): ?>
                    <a href="patient-portal.php" class="quick-action">
                        <i class="fas fa-user-circle"></i>
                        <h4>My Portal</h4>
                        <p>View my health records</p>
                    </a>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['pharmacy_staff', 'intern_pharmacy'])): ?>
                    <a href="pharmacy.php" class="quick-action">
                        <i class="fas fa-pills"></i>
                        <h4>Manage Inventory</h4>
                        <p>View pharmacy stock</p>
                    </a>
                    <a href="prescriptions.php" class="quick-action">
                        <i class="fas fa-prescription-bottle-alt"></i>
                        <h4>Dispense Medicine</h4>
                        <p>Process prescriptions</p>
                    </a>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['lab_technician', 'intern_lab'])): ?>
                    <a href="laboratory.php" class="quick-action">
                        <i class="fas fa-flask"></i>
                        <h4>Lab Tests</h4>
                        <p>Manage lab orders</p>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Upcoming Appointments -->
            <?php if (!empty($upcoming_appointments)): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> 
                        <?php echo $user_role === 'patient' ? 'My Upcoming Appointments' : 
                                   (in_array($user_role, ['doctor', 'intern_doctor']) ? 'My Upcoming Appointments' : 'Upcoming Appointments'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <?php if ($user_role === 'patient'): ?>
                                        <th>Doctor</th>
                                    <?php elseif (in_array($user_role, ['doctor', 'intern_doctor'])): ?>
                                        <th>Patient</th>
                                    <?php else: ?>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($appointment['appointment_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></td>
                                        <?php if ($user_role === 'patient'): ?>
                                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                        <?php elseif (in_array($user_role, ['doctor', 'intern_doctor'])): ?>
                                            <td><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></td>
                                        <?php else: ?>
                                            <td><?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                        <?php endif; ?>
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
                </div>
            </div>
            <?php endif; ?>

            <!-- System Information (Admin only) -->
            <?php if ($user_role === 'admin'): ?>
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
            <?php endif; ?>
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
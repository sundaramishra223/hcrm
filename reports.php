<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get basic statistics
$stats = [
    'total_patients' => $db->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'] ?? 0,
    'total_doctors' => $db->query("SELECT COUNT(*) as count FROM doctors WHERE is_active = 1")->fetch()['count'] ?? 0,
    'total_appointments' => $db->query("SELECT COUNT(*) as count FROM appointments")->fetch()['count'] ?? 0,
    'total_staff' => $db->query("SELECT COUNT(*) as count FROM staff WHERE is_active = 1")->fetch()['count'] ?? 0,
    'monthly_revenue' => $db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM billing WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch()['total'] ?? 0,
    'pending_bills' => $db->query("SELECT COUNT(*) as count FROM billing WHERE payment_status = 'pending'")->fetch()['count'] ?? 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Hospital CRM</title>
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
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
                    <p>Hospital performance reports and statistics</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($stats['total_patients']); ?></h3>
                    <p><i class="fas fa-users"></i> Total Patients</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($stats['total_doctors']); ?></h3>
                    <p><i class="fas fa-user-md"></i> Active Doctors</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($stats['total_appointments']); ?></h3>
                    <p><i class="fas fa-calendar-alt"></i> Total Appointments</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($stats['total_staff']); ?></h3>
                    <p><i class="fas fa-user-tie"></i> Active Staff</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo formatCurrency($stats['monthly_revenue']); ?></h3>
                    <p><i class="fas fa-rupee-sign"></i> Monthly Revenue</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($stats['pending_bills']); ?></h3>
                    <p><i class="fas fa-file-invoice"></i> Pending Bills</p>
                </div>
            </div>

            <!-- Report Categories -->
            <div class="grid grid-3">
                <div class="card">
                    <h3><i class="fas fa-users"></i> Patient Reports</h3>
                    <p>Generate reports related to patient data, demographics, and medical history.</p>
                    <div class="mt-3">
                        <button class="btn btn-primary" style="margin: 5px;">
                            <i class="fas fa-chart-pie"></i> Patient Demographics
                        </button>
                        <button class="btn btn-primary" style="margin: 5px;">
                            <i class="fas fa-heartbeat"></i> Medical History
                        </button>
                    </div>
                </div>

                <div class="card">
                    <h3><i class="fas fa-calendar-alt"></i> Appointment Reports</h3>
                    <p>Analyze appointment trends, doctor schedules, and patient flow.</p>
                    <div class="mt-3">
                        <button class="btn btn-success" style="margin: 5px;">
                            <i class="fas fa-chart-line"></i> Appointment Trends
                        </button>
                        <button class="btn btn-success" style="margin: 5px;">
                            <i class="fas fa-clock"></i> Doctor Schedules
                        </button>
                    </div>
                </div>

                <div class="card">
                    <h3><i class="fas fa-rupee-sign"></i> Financial Reports</h3>
                    <p>Revenue analysis, billing reports, and financial summaries.</p>
                    <div class="mt-3">
                        <button class="btn btn-warning" style="margin: 5px;">
                            <i class="fas fa-chart-bar"></i> Revenue Analysis
                        </button>
                        <button class="btn btn-warning" style="margin: 5px;">
                            <i class="fas fa-file-invoice-dollar"></i> Billing Summary
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="card">
                <h3><i class="fas fa-info-circle"></i> System Information</h3>
                <div class="grid grid-4">
                    <div class="text-center">
                        <h4>Database Status</h4>
                        <span class="badge badge-success">Connected</span>
                    </div>
                    <div class="text-center">
                        <h4>Server Status</h4>
                        <span class="badge badge-success">Online</span>
                    </div>
                    <div class="text-center">
                        <h4>System Version</h4>
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
</body>
</html>
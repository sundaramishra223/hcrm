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

$stats = [];
try {
    if ($user_role === 'admin') {
        $stats['total_patients'] = $db->query("SELECT COUNT(*) as count FROM patients WHERE hospital_id = 1")->fetch()['count'];
        $stats['total_doctors'] = $db->query("SELECT COUNT(*) as count FROM doctors")->fetch()['count'];
        $stats['total_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()")->fetch()['count'];
        $stats['total_revenue'] = $db->query("SELECT SUM(total_amount) as revenue FROM bills WHERE DATE(created_at) = CURDATE()")->fetch()['revenue'] ?? 0;
        $stats['pending_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE payment_status != 'paid'")->fetch()['count'];
        $stats['available_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'available'")->fetch()['count'];
        $stats['total_beds'] = $db->query("SELECT COUNT(*) as count FROM beds")->fetch()['count'];
        $stats['occupied_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch()['count'];
        $stats['total_staff'] = $db->query("SELECT COUNT(*) as count FROM staff")->fetch()['count'];
        $stats['today_visits'] = $db->query("SELECT COUNT(*) as count FROM patient_visits WHERE visit_date = CURDATE()")->fetch()['count'];
    } elseif ($user_role === 'doctor') {
        $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
        $stats['my_patients'] = $db->query("SELECT COUNT(*) as count FROM patients WHERE assigned_doctor_id = ?", [$doctor_id])->fetch()['count'];
        $stats['today_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()", [$doctor_id])->fetch()['count'];
        $stats['pending_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'scheduled'", [$doctor_id])->fetch()['count'];
        $stats['total_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?", [$doctor_id])->fetch()['count'];
        $stats['available_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'available'")->fetch()['count'];
        $stats['occupied_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch()['count'];
    } elseif ($user_role === 'nurse') {
        $stats['assigned_patients'] = $db->query("SELECT COUNT(*) as count FROM patient_visits WHERE assigned_nurse_id = (SELECT id FROM staff WHERE user_id = ?) AND visit_date = CURDATE()", [$_SESSION['user_id']])->fetch()['count'];
        $stats['today_vitals'] = $db->query("SELECT COUNT(*) as count FROM patient_vitals WHERE recorded_by = (SELECT id FROM staff WHERE user_id = ?) AND DATE(recorded_at) = CURDATE()", [$_SESSION['user_id']])->fetch()['count'];
        $stats['available_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'available'")->fetch()['count'];
        $stats['occupied_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch()['count'];
    } elseif ($user_role === 'patient') {
        $patient_id = $db->query("SELECT id FROM patients WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
        $stats['my_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?", [$patient_id])->fetch()['count'];
        $stats['my_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = ?", [$patient_id])->fetch()['count'];
        $stats['my_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE patient_id = ?", [$patient_id])->fetch()['count'];
        $stats['pending_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE patient_id = ? AND payment_status != 'paid'", [$patient_id])->fetch()['count'];
        $stats['my_bed'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE patient_id = ?", [$patient_id])->fetch()['count'];
    } elseif ($user_role === 'receptionist') {
        $stats['today_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()")->fetch()['count'];
        $stats['pending_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled'")->fetch()['count'];
        $stats['today_registrations'] = $db->query("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE() AND hospital_id = 1")->fetch()['count'];
        $stats['pending_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE payment_status != 'paid'")->fetch()['count'];
        $stats['available_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'available'")->fetch()['count'];
        $stats['occupied_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch()['count'];
    } elseif ($user_role === 'lab_technician') {
        $stats['pending_tests'] = $db->query("SELECT COUNT(*) as count FROM lab_order_tests WHERE status IN ('pending', 'in_progress')")->fetch()['count'];
        $stats['completed_tests'] = $db->query("SELECT COUNT(*) as count FROM lab_order_tests WHERE status = 'completed' AND DATE(completed_at) = CURDATE()")->fetch()['count'];
        $stats['total_tests'] = $db->query("SELECT COUNT(*) as count FROM lab_order_tests")->fetch()['count'];
        $stats['abnormal_results'] = $db->query("SELECT COUNT(*) as count FROM lab_order_tests WHERE is_abnormal = 1 AND DATE(completed_at) = CURDATE()")->fetch()['count'];
    } elseif ($user_role === 'pharmacy_staff') {
        $stats['total_medicines'] = $db->query("SELECT COUNT(*) as count FROM medicines")->fetch()['count'];
        $stats['low_stock_medicines'] = $db->query("SELECT COUNT(*) as count FROM medicines WHERE stock_quantity <= min_stock_level")->fetch()['count'];
        $stats['today_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE DATE(created_at) = CURDATE()")->fetch()['count'];
        $stats['pending_dispensing'] = $db->query("SELECT COUNT(*) as count FROM prescription_medicines WHERE dispensed_quantity < quantity")->fetch()['count'];
    } elseif ($user_role === 'intern_doctor') {
        $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
        $stats['my_patients'] = $db->query("SELECT COUNT(*) as count FROM patients WHERE assigned_doctor_id = ?", [$doctor_id])->fetch()['count'];
        $stats['today_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()", [$doctor_id])->fetch()['count'];
        $stats['pending_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'scheduled'", [$doctor_id])->fetch()['count'];
        $stats['total_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?", [$doctor_id])->fetch()['count'];
        $stats['available_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'available'")->fetch()['count'];
        $stats['occupied_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch()['count'];
    } elseif ($user_role === 'intern_nurse') {
        $stats['assigned_patients'] = $db->query("SELECT COUNT(*) as count FROM patient_visits WHERE assigned_nurse_id = (SELECT id FROM staff WHERE user_id = ?) AND visit_date = CURDATE()", [$_SESSION['user_id']])->fetch()['count'];
        $stats['today_vitals'] = $db->query("SELECT COUNT(*) as count FROM patient_vitals WHERE recorded_by = (SELECT id FROM staff WHERE user_id = ?) AND DATE(recorded_at) = CURDATE()", [$_SESSION['user_id']])->fetch()['count'];
        $stats['total_vitals'] = $db->query("SELECT COUNT(*) as count FROM patient_vitals WHERE recorded_by = (SELECT id FROM staff WHERE user_id = ?)", [$_SESSION['user_id']])->fetch()['count'];
        $stats['available_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'available'")->fetch()['count'];
        $stats['occupied_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch()['count'];
    } elseif ($user_role === 'intern_lab') {
        $stats['pending_tests'] = $db->query("SELECT COUNT(*) as count FROM lab_order_tests WHERE status IN ('pending', 'in_progress')")->fetch()['count'];
        $stats['completed_tests'] = $db->query("SELECT COUNT(*) as count FROM lab_order_tests WHERE status = 'completed' AND DATE(completed_at) = CURDATE()")->fetch()['count'];
        $stats['total_tests'] = $db->query("SELECT COUNT(*) as count FROM lab_order_tests")->fetch()['count'];
    } elseif ($user_role === 'intern_pharmacy') {
        $stats['total_medicines'] = $db->query("SELECT COUNT(*) as count FROM medicines")->fetch()['count'];
        $stats['low_stock_medicines'] = $db->query("SELECT COUNT(*) as count FROM medicines WHERE stock_quantity <= min_stock_level")->fetch()['count'];
        $stats['today_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE DATE(created_at) = CURDATE()")->fetch()['count'];
    }
} catch (Exception $e) {
    $stats = [];
}

// Get recent activities
$recent_activities = [];
try {
    if ($user_role === 'admin') {
        $recent_activities = $db->query("
            SELECT 'appointment' as type, a.appointment_date as date, 
                   CONCAT('New appointment: ', p.first_name, ' ', p.last_name) as description
            FROM appointments a 
            JOIN patients p ON a.patient_id = p.id 
            ORDER BY a.created_at DESC LIMIT 5
        ")->fetchAll();
    }
} catch (Exception $e) {
    $recent_activities = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    include_once 'includes/site-config.php';
    renderSiteHead('Dashboard');
    ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                    <li><a href="billing.php"><i class="fas fa-money-bill-wave"></i> Billing</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'pharmacy_staff', 'intern_pharmacy'])): ?>
                    <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab', 'doctor', 'intern_doctor'])): ?>
                    <li><a href="lab-test-management.php"><i class="fas fa-flask"></i> Lab Tests</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'nurse', 'intern_nurse'])): ?>
                    <li><a href="patient-vitals.php"><i class="fas fa-heartbeat"></i> Patient Vitals</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist', 'intern_doctor', 'intern_nurse'])): ?>
                    <li><a href="patient-monitoring.php"><i class="fas fa-user-injured"></i> Patient Monitoring</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist', 'intern_doctor', 'intern_nurse'])): ?>
                    <li><a href="patient-conversion.php"><i class="fas fa-exchange-alt"></i> Patient Conversion</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin'])): ?>
                    <li><a href="user-management.php"><i class="fas fa-users-cog"></i> User Management</a></li>
                    <li><a href="staff.php"><i class="fas fa-user-nurse"></i> Staff</a></li>
                    <li><a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                    <li><a href="intern-management.php"><i class="fas fa-graduation-cap"></i> Intern Management</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'nurse', 'receptionist', 'doctor', 'intern_doctor', 'intern_nurse'])): ?>
                    <li><a href="patient-monitoring.php"><i class="fas fa-user-injured"></i> Patient Monitoring</a></li>
                    <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                    <li><a href="beds.php"><i class="fas fa-bed"></i> Bed Management</a></li>
                <?php endif; ?>
                
                <?php if ($user_role === 'patient'): ?>
                    <li><a href="my-bed.php"><i class="fas fa-bed"></i> My Bed</a></li>
                <?php endif; ?>
                
                <?php 
                // Smart ambulance access based on role requirements
                $ambulance_roles = ['admin', 'receptionist', 'doctor', 'nurse', 'patient', 'intern_doctor', 'intern_nurse'];
                if (in_array($user_role, $ambulance_roles)): 
                ?>
                    <li><a href="ambulance-management.php"><i class="fas fa-ambulance"></i> <?php echo $user_role === 'patient' ? 'My Ambulance Bookings' : 'Ambulance Management'; ?></a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician', 'pharmacy_staff'])): ?>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <?php endif; ?>
                
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                
                <?php if (in_array($user_role, ['admin'])): ?>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <?php endif; ?>
                
                <!-- Debug Info (Remove after testing) -->
                <li style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px;">
                    <strong>Debug:</strong><br>
                    Role: <?php echo htmlspecialchars($user_role); ?><br>
                    Display: <?php echo htmlspecialchars($_SESSION['role_display']); ?><br>
                    User ID: <?php echo htmlspecialchars($_SESSION['user_id']); ?>
                </li>
                
                <?php if (in_array($user_role, ['patient'])): ?>
                    <li><a href="my-appointments.php"><i class="fas fa-calendar-check"></i> My Appointments</a></li>
                    <li><a href="my-prescriptions.php"><i class="fas fa-prescription"></i> My Prescriptions</a></li>
                    <li><a href="my-bills.php"><i class="fas fa-money-bill-wave"></i> My Bills</a></li>
                    <li><a href="my-medical-history.php"><i class="fas fa-file-medical"></i> Medical History</a></li>
                <?php endif; ?>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
                    <p>Here's what's happening today</p>
                </div>
                <div class="header-right">
                    <div class="theme-controls">
                        <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                            <i class="fas fa-moon"></i>
                        </button>
                        <button class="color-toggle" id="colorToggle" title="Change Colors">
                            <i class="fas fa-palette"></i>
                        </button>
                    </div>
                    <div class="user-info">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['role_display']); ?></span>
                    </div>
                    <a href="logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <?php if ($user_role === 'admin'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_patients'] ?? 0); ?></h3>
                        <p>Total Patients</p>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_doctors'] ?? 0); ?></h3>
                        <p>Total Doctors</p>
                        <i class="fas fa-user-md stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_appointments'] ?? 0); ?></h3>
                        <p>Today's Appointments</p>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3>₹<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                        <p>Today's Revenue</p>
                        <i class="fas fa-rupee-sign stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_bills'] ?? 0); ?></h3>
                        <p>Pending Bills</p>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['available_beds'] ?? 0); ?></h3>
                        <p>Available Beds</p>
                        <i class="fas fa-bed stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_beds'] ?? 0); ?></h3>
                        <p>Total Beds</p>
                        <i class="fas fa-bed stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['occupied_beds'] ?? 0); ?></h3>
                        <p>Occupied Beds</p>
                        <i class="fas fa-user-in-bed stat-icon"></i>
                    </div>
                <?php elseif ($user_role === 'doctor'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_patients'] ?? 0); ?></h3>
                        <p>My Patients</p>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['today_appointments'] ?? 0); ?></h3>
                        <p>Today's Appointments</p>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_appointments'] ?? 0); ?></h3>
                        <p>Pending Appointments</p>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_prescriptions'] ?? 0); ?></h3>
                        <p>Total Prescriptions</p>
                        <i class="fas fa-prescription stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['available_beds'] ?? 0); ?></h3>
                        <p>Available Beds</p>
                        <i class="fas fa-bed stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['occupied_beds'] ?? 0); ?></h3>
                        <p>Occupied Beds</p>
                        <i class="fas fa-user-in-bed stat-icon"></i>
                    </div>
                <?php elseif ($user_role === 'nurse'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['assigned_patients'] ?? 0); ?></h3>
                        <p>Assigned Patients</p>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['today_vitals'] ?? 0); ?></h3>
                        <p>Vitals Recorded Today</p>
                        <i class="fas fa-heartbeat stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['available_beds'] ?? 0); ?></h3>
                        <p>Available Beds</p>
                        <i class="fas fa-bed stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['occupied_beds'] ?? 0); ?></h3>
                        <p>Occupied Beds</p>
                        <i class="fas fa-user-in-bed stat-icon"></i>
                    </div>
                <?php elseif ($user_role === 'patient'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_appointments'] ?? 0); ?></h3>
                        <p>My Appointments</p>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_prescriptions'] ?? 0); ?></h3>
                        <p>My Prescriptions</p>
                        <i class="fas fa-prescription stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_bills'] ?? 0); ?></h3>
                        <p>My Bills</p>
                        <i class="fas fa-money-bill-wave stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_bills'] ?? 0); ?></h3>
                        <p>Pending Bills</p>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_bed'] ?? 0); ?></h3>
                        <p>My Bed</p>
                        <i class="fas fa-bed stat-icon"></i>
                    </div>
                <?php elseif ($user_role === 'receptionist'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['today_appointments'] ?? 0); ?></h3>
                        <p>Today's Appointments</p>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_appointments'] ?? 0); ?></h3>
                        <p>Pending Appointments</p>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['today_registrations'] ?? 0); ?></h3>
                        <p>Today's Registrations</p>
                        <i class="fas fa-user-plus stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_bills'] ?? 0); ?></h3>
                        <p>Pending Bills</p>
                        <i class="fas fa-money-bill-wave stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['available_beds'] ?? 0); ?></h3>
                        <p>Available Beds</p>
                        <i class="fas fa-bed stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['occupied_beds'] ?? 0); ?></h3>
                        <p>Occupied Beds</p>
                        <i class="fas fa-user-in-bed stat-icon"></i>
                    </div>
                <?php elseif ($user_role === 'lab_technician'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_tests'] ?? 0); ?></h3>
                        <p>Pending Tests</p>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['completed_tests'] ?? 0); ?></h3>
                        <p>Completed Today</p>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_tests'] ?? 0); ?></h3>
                        <p>Total Tests</p>
                        <i class="fas fa-flask stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['abnormal_results'] ?? 0); ?></h3>
                        <p>Abnormal Results</p>
                        <i class="fas fa-exclamation-triangle stat-icon"></i>
                    </div>
                <?php elseif ($user_role === 'pharmacy_staff'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_medicines'] ?? 0); ?></h3>
                        <p>Total Medicines</p>
                        <i class="fas fa-pills stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['low_stock_medicines'] ?? 0); ?></h3>
                        <p>Low Stock Items</p>
                        <i class="fas fa-exclamation-triangle stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['today_prescriptions'] ?? 0); ?></h3>
                        <p>Today's Prescriptions</p>
                        <i class="fas fa-prescription stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_dispensing'] ?? 0); ?></h3>
                        <p>Pending Dispensing</p>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                <?php elseif ($user_role === 'intern_doctor'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_patients'] ?? 0); ?></h3>
                        <p>My Patients</p>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['today_appointments'] ?? 0); ?></h3>
                        <p>Today's Appointments</p>
                        <i class="fas fa-calendar-check stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_appointments'] ?? 0); ?></h3>
                        <p>Pending Appointments</p>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_prescriptions'] ?? 0); ?></h3>
                        <p>Total Prescriptions</p>
                        <i class="fas fa-prescription stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['available_beds'] ?? 0); ?></h3>
                        <p>Available Beds</p>
                        <i class="fas fa-bed stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['occupied_beds'] ?? 0); ?></h3>
                        <p>Occupied Beds</p>
                        <i class="fas fa-user-in-bed stat-icon"></i>
                    </div>
                <?php elseif ($user_role === 'intern_nurse'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['assigned_patients'] ?? 0); ?></h3>
                        <p>Assigned Patients</p>
                        <i class="fas fa-users stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['today_vitals'] ?? 0); ?></h3>
                        <p>Vitals Recorded Today</p>
                        <i class="fas fa-heartbeat stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_vitals'] ?? 0); ?></h3>
                        <p>Total Vitals Recorded</p>
                        <i class="fas fa-chart-line stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['available_beds'] ?? 0); ?></h3>
                        <p>Available Beds</p>
                        <i class="fas fa-bed stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['occupied_beds'] ?? 0); ?></h3>
                        <p>Occupied Beds</p>
                        <i class="fas fa-user-in-bed stat-icon"></i>
                    </div>
                <?php elseif ($user_role === 'intern_lab'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_tests'] ?? 0); ?></h3>
                        <p>Pending Tests</p>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['completed_tests'] ?? 0); ?></h3>
                        <p>Completed Today</p>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_tests'] ?? 0); ?></h3>
                        <p>Total Tests</p>
                        <i class="fas fa-flask stat-icon"></i>
                    </div>
                <?php elseif ($user_role === 'intern_pharmacy'): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_medicines'] ?? 0); ?></h3>
                        <p>Total Medicines</p>
                        <i class="fas fa-pills stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['low_stock_medicines'] ?? 0); ?></h3>
                        <p>Low Stock Items</p>
                        <i class="fas fa-exclamation-triangle stat-icon"></i>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['today_prescriptions'] ?? 0); ?></h3>
                        <p>Today's Prescriptions</p>
                        <i class="fas fa-prescription stat-icon"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Charts Section -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Analytics Overview</h3>
                    <select id="chartRange" class="form-control" style="width: auto;">
                        <option value="7">Last 7 Days</option>
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                    </select>
                </div>
                <canvas id="dashboardChart" height="100"></canvas>
            </div>

            <!-- Recent Activities -->
            <?php if ($user_role === 'admin' && !empty($recent_activities)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Activities</h3>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activities as $activity): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-primary"><?php echo ucfirst($activity['type']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($activity['date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist', 'intern_doctor'])): ?>
                        <a href="book-appointment.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Book Appointment
                        </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                        <a href="patients.php?action=add" class="btn btn-success">
                            <i class="fas fa-user-plus"></i> Add Patient
                        </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($user_role, ['admin', 'receptionist', 'pharmacy_staff', 'intern_pharmacy'])): ?>
                        <a href="billing.php?action=create" class="btn btn-warning">
                            <i class="fas fa-file-invoice"></i> Create Bill
                        </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician', 'pharmacy_staff'])): ?>
                        <a href="reports.php" class="btn btn-info">
                            <i class="fas fa-chart-line"></i> View Reports
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>

    <script>
        // Enhanced Theme and Color Controls
        const themeToggle = document.getElementById('themeToggle');
        const colorToggle = document.getElementById('colorToggle');
        const html = document.documentElement;
        const themeIcon = themeToggle.querySelector('i');
        const colorIcon = colorToggle.querySelector('i');

        // Available themes
        const themes = ['light', 'dark', 'medical'];
        let currentThemeIndex = 0;

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        currentThemeIndex = themes.indexOf(savedTheme);
        if (currentThemeIndex === -1) currentThemeIndex = 0;
        
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        // Theme toggle (cycles through themes)
        themeToggle.addEventListener('click', () => {
            currentThemeIndex = (currentThemeIndex + 1) % themes.length;
            const newTheme = themes[currentThemeIndex];
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            
            // Add visual feedback
            themeToggle.style.transform = 'scale(0.9)';
            setTimeout(() => {
                themeToggle.style.transform = 'scale(1)';
            }, 150);
        });

        // Color toggle (cycles between light and medical themes)
        colorToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'medical' ? 'light' : 'medical';
            
            // Add immediate visual feedback
            colorToggle.style.transform = 'scale(0.9)';
            colorToggle.style.backgroundColor = 'var(--primary-color)';
            colorToggle.style.color = 'white';
            
            setTimeout(() => {
                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateThemeIcon(newTheme);
                
                // Add color cycling animation
                colorToggle.classList.add('active');
                
                // Reset button styling
                colorToggle.style.transform = 'scale(1)';
                colorToggle.style.backgroundColor = '';
                colorToggle.style.color = '';
                
                setTimeout(() => {
                    colorToggle.classList.remove('active');
                }, 2000);
                
                // Update theme index
                currentThemeIndex = themes.indexOf(newTheme);
                
                // Show confirmation message
                const message = newTheme === 'medical' ? 'Medical Theme Activated! 🏥' : 'Light Theme Activated! ☀️';
                showThemeNotification(message);
            }, 150);
        });

        function updateThemeIcon(theme) {
            // Remove any existing animations
            themeIcon.style.animation = '';
            colorIcon.style.animation = '';
            
            switch(theme) {
                case 'light':
                    themeIcon.className = 'fas fa-sun';
                    colorIcon.className = 'fas fa-palette';
                    break;
                case 'dark':
                    themeIcon.className = 'fas fa-moon';
                    colorIcon.className = 'fas fa-palette';
                    break;
                case 'medical':
                    themeIcon.className = 'fas fa-sun';
                    colorIcon.className = 'fas fa-heart';
                    break;
                default:
                    themeIcon.className = 'fas fa-sun';
                    colorIcon.className = 'fas fa-palette';
            }
            
            // Add subtle animation feedback
            themeIcon.style.animation = 'fadeIn 0.3s ease-out';
            colorIcon.style.animation = 'fadeIn 0.3s ease-out';
        }

        // Theme notification function
        function showThemeNotification(message) {
            // Remove existing notification if any
            const existingNotification = document.querySelector('.theme-notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'theme-notification';
            notification.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <span>${message}</span>
            `;
            
            // Add to body
            document.body.appendChild(notification);
            
            // Show with animation
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            // Auto hide after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');

        function checkMobile() {
            if (window.innerWidth <= 768) {
                mobileMenuToggle.style.display = 'block';
                sidebar.classList.remove('open');
            } else {
                mobileMenuToggle.style.display = 'none';
                sidebar.classList.remove('open');
            }
        }

        mobileMenuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        window.addEventListener('resize', checkMobile);
        checkMobile();

        // Chart.js Dashboard Chart
        const ctx = document.getElementById('dashboardChart').getContext('2d');
        const chartRange = document.getElementById('chartRange');

        let dashboardChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Appointments',
                    data: [12, 19, 15, 25, 22, 18, 24],
                    borderColor: '#004685',
                    backgroundColor: 'rgba(0, 70, 133, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Revenue',
                    data: [5000, 8000, 6000, 12000, 10000, 7000, 11000],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: null,
                    intersect: false,
                },
                onClick: (e) => {
                    // Disable chart click behavior
                    e.stopPropagation();
                    return false;
                },
                onHover: (e) => {
                    // Disable hover behavior completely
                    e.native.target.style.cursor = 'default';
                    return false;
                },
                plugins: {
                    tooltip: {
                        enabled: false
                    }
                }
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Days'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Appointments'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Revenue (₹)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Weekly Overview'
                    }
                }
            }
        });

        // Update chart based on range selection
        chartRange.addEventListener('change', function() {
            const days = parseInt(this.value);
            const labels = [];
            const appointmentData = [];
            const revenueData = [];

            for (let i = days - 1; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('en-US', { weekday: 'short' }));
                
                // Generate random data for demo
                appointmentData.push(Math.floor(Math.random() * 30) + 10);
                revenueData.push(Math.floor(Math.random() * 15000) + 5000);
            }

            dashboardChart.data.labels = labels;
            dashboardChart.data.datasets[0].data = appointmentData;
            dashboardChart.data.datasets[1].data = revenueData;
            dashboardChart.update();
        });

        // Add stat card icons
        document.querySelectorAll('.stat-card').forEach(card => {
            const icon = card.querySelector('.stat-icon');
            if (icon) {
                icon.style.position = 'absolute';
                icon.style.top = '1rem';
                icon.style.right = '1rem';
                icon.style.fontSize = '2rem';
                icon.style.opacity = '0.1';
                icon.style.color = 'var(--primary-color)';
            }
        });
    </script>

    <style>
        .mobile-menu-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: none;
        }

        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2rem;
            opacity: 0.1;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
    
    <!-- Theme Toggle -->
    <div class="theme-toggle">
        <div class="theme-option" data-theme="light" onclick="setTheme('light')" title="Light Theme"></div>
        <div class="theme-option" data-theme="dark" onclick="setTheme('dark')" title="Dark Theme"></div>
        <div class="theme-option" data-theme="medical" onclick="setTheme('medical')" title="Medical Theme"></div>
    </div>
    
    <script>
        // Theme Management
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            updateThemeToggle(theme);
        }
        
        function updateThemeToggle(activeTheme) {
            document.querySelectorAll('.theme-option').forEach(option => {
                option.classList.remove('active');
                if (option.dataset.theme === activeTheme) {
                    option.classList.add('active');
                }
            });
        }
        
        // Initialize theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            setTheme(savedTheme);
        });
    </script>
</body>
</html>

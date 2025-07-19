<?php
session_start();
require_once 'config/database.php';

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
        $stats['total_doctors'] = $db->query("SELECT COUNT(*) as count FROM doctors WHERE hospital_id = 1")->fetch()['count'];
        $stats['total_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE hospital_id = 1 AND appointment_date = CURDATE()")->fetch()['count'];
        $stats['total_revenue'] = $db->query("SELECT SUM(total_amount) as revenue FROM bills WHERE hospital_id = 1 AND DATE(created_at) = CURDATE()")->fetch()['revenue'] ?? 0;
        $stats['pending_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE hospital_id = 1 AND payment_status != 'paid'")->fetch()['count'];
        $stats['available_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE hospital_id = 1 AND status = 'available'")->fetch()['count'];
        $stats['total_staff'] = $db->query("SELECT COUNT(*) as count FROM staff WHERE hospital_id = 1")->fetch()['count'];
        $stats['today_visits'] = $db->query("SELECT COUNT(*) as count FROM patient_visits WHERE hospital_id = 1 AND visit_date = CURDATE()")->fetch()['count'];
    } elseif ($user_role === 'doctor') {
        $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
        $stats['my_patients'] = $db->query("SELECT COUNT(*) as count FROM patients WHERE assigned_doctor_id = ?", [$doctor_id])->fetch()['count'];
        $stats['today_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()", [$doctor_id])->fetch()['count'];
        $stats['pending_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND status = 'scheduled'", [$doctor_id])->fetch()['count'];
        $stats['total_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?", [$doctor_id])->fetch()['count'];
    } elseif ($user_role === 'nurse') {
        $stats['assigned_patients'] = $db->query("SELECT COUNT(*) as count FROM patient_visits WHERE assigned_nurse_id = (SELECT id FROM staff WHERE user_id = ?) AND visit_date = CURDATE()", [$_SESSION['user_id']])->fetch()['count'];
        $stats['today_vitals'] = $db->query("SELECT COUNT(*) as count FROM patient_vitals WHERE recorded_by = (SELECT id FROM staff WHERE user_id = ?) AND DATE(recorded_at) = CURDATE()", [$_SESSION['user_id']])->fetch()['count'];
    } elseif ($user_role === 'patient') {
        $patient_id = $db->query("SELECT id FROM patients WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
        $stats['my_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?", [$patient_id])->fetch()['count'];
        $stats['my_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = ?", [$patient_id])->fetch()['count'];
        $stats['my_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE patient_id = ?", [$patient_id])->fetch()['count'];
        $stats['pending_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE patient_id = ? AND payment_status != 'paid'", [$patient_id])->fetch()['count'];
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
            WHERE a.hospital_id = 1 
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
    <title>Dashboard - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="billing.php"><i class="fas fa-money-bill-wave"></i> Billing</a></li>
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="staff.php"><i class="fas fa-user-nurse"></i> Staff</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                        <i class="fas fa-moon"></i>
                    </button>
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
                    <a href="book-appointment.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Book Appointment
                    </a>
                    <a href="patients.php?action=add" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> Add Patient
                    </a>
                    <a href="billing.php?action=create" class="btn btn-warning">
                        <i class="fas fa-file-invoice"></i> Create Bill
                    </a>
                    <a href="reports.php" class="btn btn-info">
                        <i class="fas fa-chart-line"></i> View Reports
                    </a>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        const icon = themeToggle.querySelector('i');

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
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
                    mode: 'index',
                    intersect: false,
                },
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
</body>
</html>

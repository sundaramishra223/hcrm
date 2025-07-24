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
if (!in_array($user_role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get report data
try {
    // Monthly revenue report
    $monthly_revenue = $db->query("
        SELECT 
            DATE_FORMAT(bill_date, '%Y-%m') as month,
            SUM(total_amount) as total_revenue,
            SUM(paid_amount) as paid_revenue,
            COUNT(*) as bill_count
        FROM billing 
        WHERE bill_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(bill_date, '%Y-%m')
        ORDER BY month DESC
    ")->fetchAll();

    // Patient registration trends
    $patient_trends = $db->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as patient_count
        FROM patients 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ")->fetchAll();

    // Appointment statistics
    $appointment_stats = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM appointments 
        WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY status
    ")->fetchAll();

    // Doctor performance (appointments)
    $doctor_performance = $db->query("
        SELECT 
            d.doctor_name,
            COUNT(a.id) as appointment_count,
            AVG(b.total_amount) as avg_revenue
        FROM doctors d
        LEFT JOIN appointments a ON d.id = a.doctor_id AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        LEFT JOIN billing b ON a.id = b.appointment_id
        WHERE d.is_active = 1
        GROUP BY d.id, d.doctor_name
        ORDER BY appointment_count DESC
    ")->fetchAll();

    // Department wise revenue
    $dept_revenue = $db->query("
        SELECT 
            d.specialization as department,
            SUM(b.total_amount) as revenue,
            COUNT(b.id) as bill_count
        FROM billing b
        LEFT JOIN appointments a ON b.appointment_id = a.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        WHERE b.bill_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY d.specialization
        ORDER BY revenue DESC
    ")->fetchAll();

    // Lab test trends
    $lab_trends = $db->query("
        SELECT 
            l.test_name,
            COUNT(lr.id) as test_count,
            SUM(l.price) as revenue
        FROM laboratory_results lr
        LEFT JOIN laboratory l ON lr.test_id = l.id
        WHERE lr.test_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY l.id, l.test_name
        ORDER BY test_count DESC
        LIMIT 10
    ")->fetchAll();

    // Pharmacy sales
    $pharmacy_sales = $db->query("
        SELECT 
            p.medicine_name,
            SUM(ps.quantity) as total_sold,
            SUM(ps.total_amount) as revenue
        FROM pharmacy_sales ps
        LEFT JOIN pharmacy p ON ps.medicine_id = p.id
        WHERE ps.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY p.id, p.medicine_name
        ORDER BY revenue DESC
        LIMIT 10
    ")->fetchAll();

} catch (Exception $e) {
    $monthly_revenue = [];
    $patient_trends = [];
    $appointment_stats = [];
    $doctor_performance = [];
    $dept_revenue = [];
    $lab_trends = [];
    $pharmacy_sales = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <?php if ($user_role === 'admin'): ?>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <?php endif; ?>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
                    <p>Hospital performance insights and analytics</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Monthly Revenue Trend (Last 12 Months)</h3>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="100"></canvas>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="row">
                <!-- Appointment Status -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-check"></i> Appointment Status (Last 30 Days)</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="appointmentChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Patient Registration Trend -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-plus"></i> Patient Registrations</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="patientChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Doctor Performance -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-md"></i> Doctor Performance (Last 30 Days)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($doctor_performance)): ?>
                        <p class="text-muted text-center">No doctor performance data available.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Doctor Name</th>
                                        <th>Appointments</th>
                                        <th>Average Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($doctor_performance as $doctor): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($doctor['doctor_name']); ?></td>
                                            <td><?php echo $doctor['appointment_count']; ?></td>
                                            <td><?php echo formatCurrency($doctor['avg_revenue'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Department Revenue -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-building"></i> Department Revenue (Last 30 Days)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($dept_revenue)): ?>
                        <p class="text-muted text-center">No department revenue data available.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Revenue</th>
                                        <th>Bills Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dept_revenue as $dept): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dept['department'] ?? 'General'); ?></td>
                                            <td><?php echo formatCurrency($dept['revenue']); ?></td>
                                            <td><?php echo $dept['bill_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lab Tests & Pharmacy Sales -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-flask"></i> Top Lab Tests (Last 30 Days)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($lab_trends)): ?>
                                <p class="text-muted text-center">No lab test data available.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($lab_trends as $test): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($test['test_name']); ?>
                                            <div>
                                                <span class="badge badge-primary"><?php echo $test['test_count']; ?> tests</span>
                                                <span class="badge badge-success"><?php echo formatCurrency($test['revenue']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-pills"></i> Top Medicine Sales (Last 30 Days)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pharmacy_sales)): ?>
                                <p class="text-muted text-center">No pharmacy sales data available.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($pharmacy_sales as $medicine): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?php echo htmlspecialchars($medicine['medicine_name']); ?>
                                            <div>
                                                <span class="badge badge-info"><?php echo $medicine['total_sold']; ?> units</span>
                                                <span class="badge badge-success"><?php echo formatCurrency($medicine['revenue']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_reverse(array_column($monthly_revenue, 'month'))); ?>,
            datasets: [{
                label: 'Total Revenue',
                data: <?php echo json_encode(array_reverse(array_column($monthly_revenue, 'total_revenue'))); ?>,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }, {
                label: 'Paid Revenue',
                data: <?php echo json_encode(array_reverse(array_column($monthly_revenue, 'paid_revenue'))); ?>,
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Appointment Status Chart
    const appointmentCtx = document.getElementById('appointmentChart').getContext('2d');
    const appointmentChart = new Chart(appointmentCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($appointment_stats, 'status')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($appointment_stats, 'count')); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 205, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Patient Registration Chart
    const patientCtx = document.getElementById('patientChart').getContext('2d');
    const patientChart = new Chart(patientCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_reverse(array_column($patient_trends, 'month'))); ?>,
            datasets: [{
                label: 'New Patients',
                data: <?php echo json_encode(array_reverse(array_column($patient_trends, 'patient_count'))); ?>,
                backgroundColor: 'rgba(153, 102, 255, 0.8)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    </script>

    <style>
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -15px;
    }
    
    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
        padding: 0 15px;
        margin-bottom: 20px;
    }
    
    .col-md-3 {
        flex: 0 0 25%;
        max-width: 25%;
        padding: 0 15px;
        margin-bottom: 20px;
    }
    
    .list-group {
        list-style: none;
        padding: 0;
    }
    
    .list-group-item {
        padding: 12px 15px;
        margin-bottom: 1px;
        background-color: #fff;
        border: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .list-group-item:first-child {
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
    }
    
    .list-group-item:last-child {
        border-bottom-left-radius: 4px;
        border-bottom-right-radius: 4px;
    }
    
    .badge {
        display: inline-block;
        padding: 4px 8px;
        font-size: 0.75em;
        font-weight: 600;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: 4px;
        margin-left: 5px;
    }
    
    .badge-primary { background-color: #007bff; color: white; }
    .badge-success { background-color: #28a745; color: white; }
    .badge-info { background-color: #17a2b8; color: white; }
    
    @media (max-width: 768px) {
        .col-md-6, .col-md-3 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
    </style>
</body>
</html>
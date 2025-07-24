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
if (!in_array($user_role, ['admin', 'doctor', 'lab_technician', 'intern_lab'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get laboratory tests
try {
    $lab_tests = $db->query("SELECT * FROM laboratory WHERE is_active = 1 ORDER BY test_category, test_name")->fetchAll();
} catch (Exception $e) {
    $lab_tests = [];
}

// Get recent lab test orders
try {
    $recent_tests = $db->query("
        SELECT lt.*, 
               p.patient_id, p.first_name, p.last_name,
               d.doctor_name 
        FROM lab_tests lt 
        LEFT JOIN patients p ON lt.patient_id = p.id 
        LEFT JOIN doctors d ON lt.doctor_id = d.id 
        ORDER BY lt.created_at DESC 
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $recent_tests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Management - Hospital CRM</title>
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
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php" class="active"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-flask"></i> Laboratory Management</h1>
                    <p>Manage laboratory tests and reports</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="grid grid-2">
                <!-- Available Laboratory Tests -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Available Laboratory Tests</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lab_tests)): ?>
                            <p class="text-muted text-center">No laboratory tests found.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Test Name</th>
                                            <th>Category</th>
                                            <th>Normal Range</th>
                                            <th>Unit</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $current_category = '';
                                        foreach ($lab_tests as $test): 
                                            if ($current_category != $test['test_category']):
                                                $current_category = $test['test_category'];
                                        ?>
                                                <tr style="background: #f8f9fa;">
                                                    <td colspan="6"><strong><?php echo htmlspecialchars($current_category); ?></strong></td>
                                                </tr>
                                        <?php endif; ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                                                <td><?php echo htmlspecialchars($test['test_category']); ?></td>
                                                <td><?php echo htmlspecialchars($test['normal_range']); ?></td>
                                                <td><?php echo htmlspecialchars($test['unit']); ?></td>
                                                <td><?php echo formatCurrency($test['price']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $test['is_active'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $test['is_active'] ? 'Active' : 'Inactive'; ?>
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

                <!-- Recent Lab Test Orders -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Recent Lab Test Orders</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_tests)): ?>
                            <p class="text-muted text-center">No recent lab tests found.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Test ID</th>
                                            <th>Patient</th>
                                            <th>Doctor</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_tests as $test): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($test['test_id']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($test['patient_id'] . ' - ' . $test['first_name'] . ' ' . $test['last_name']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($test['doctor_name'] ?: 'N/A'); ?></td>
                                                <td><?php echo date('d M Y', strtotime($test['test_date'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $test['status'] == 'completed' ? 'success' : 
                                                            ($test['status'] == 'cancelled' ? 'danger' : 
                                                            ($test['status'] == 'in_progress' ? 'warning' : 'info')); 
                                                    ?>">
                                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $test['status']))); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatCurrency($test['total_amount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Laboratory Statistics -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Laboratory Statistics</h3>
                </div>
                <div class="card-body">
                    <div class="stats-grid">
                        <?php
                        try {
                            $stats = [];
                            
                            // Total tests available
                            $result = $db->query("SELECT COUNT(*) as count FROM laboratory WHERE is_active = 1")->fetch();
                            $stats['total_tests'] = $result['count'];
                            
                            // Tests today
                            $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE DATE(test_date) = CURDATE()")->fetch();
                            $stats['tests_today'] = $result['count'];
                            
                            // Pending tests
                            $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'")->fetch();
                            $stats['pending_tests'] = $result['count'];
                            
                            // Completed tests this month
                            $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'completed' AND MONTH(test_date) = MONTH(CURDATE()) AND YEAR(test_date) = YEAR(CURDATE())")->fetch();
                            $stats['completed_month'] = $result['count'];
                            
                            // Revenue this month
                            $result = $db->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM lab_tests WHERE status = 'completed' AND MONTH(test_date) = MONTH(CURDATE()) AND YEAR(test_date) = YEAR(CURDATE())")->fetch();
                            $stats['revenue_month'] = $result['revenue'];
                            
                        } catch (Exception $e) {
                            $stats = [
                                'total_tests' => 0,
                                'tests_today' => 0,
                                'pending_tests' => 0,
                                'completed_month' => 0,
                                'revenue_month' => 0
                            ];
                        }
                        ?>
                        <div class="stat-card">
                            <h3><?php echo number_format($stats['total_tests']); ?></h3>
                            <p><i class="fas fa-flask"></i> Available Tests</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo number_format($stats['tests_today']); ?></h3>
                            <p><i class="fas fa-calendar-day"></i> Tests Today</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo number_format($stats['pending_tests']); ?></h3>
                            <p><i class="fas fa-clock"></i> Pending Tests</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo number_format($stats['completed_month']); ?></h3>
                            <p><i class="fas fa-check-circle"></i> Completed This Month</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo formatCurrency($stats['revenue_month']); ?></h3>
                            <p><i class="fas fa-rupee-sign"></i> Monthly Revenue</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'doctor', 'lab_technician'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get lab tests
$lab_tests = $db->query("SELECT * FROM laboratory ORDER BY test_name")->fetchAll();
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
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1><i class="fas fa-flask"></i> Laboratory Management</h1>
                    <p>Manage laboratory tests and results</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Available Tests -->
            <div class="card">
                <h3><i class="fas fa-list"></i> Available Laboratory Tests</h3>
                
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
                                <?php foreach ($lab_tests as $test): ?>
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
        </main>
    </div>
</body>
</html>
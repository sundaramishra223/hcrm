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

// Get equipment list
$equipment = $db->query("SELECT * FROM equipment ORDER BY equipment_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Management - Hospital CRM</title>
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
                <li><a href="equipment.php" class="active"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1><i class="fas fa-tools"></i> Equipment Management</h1>
                    <p>Manage hospital equipment and assets</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Equipment List -->
            <div class="card">
                <h3><i class="fas fa-list"></i> Hospital Equipment</h3>
                
                <?php if (empty($equipment)): ?>
                    <p class="text-muted text-center">No equipment found.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Equipment ID</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Manufacturer</th>
                                    <th>Model</th>
                                    <th>Location</th>
                                    <th>Purchase Price</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipment as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['equipment_id']); ?></td>
                                        <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                                        <td><?php echo htmlspecialchars($item['manufacturer']); ?></td>
                                        <td><?php echo htmlspecialchars($item['model_number']); ?></td>
                                        <td><?php echo htmlspecialchars($item['location']); ?></td>
                                        <td><?php echo formatCurrency($item['purchase_price']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $item['status'] == 'active' ? 'success' : 
                                                    ($item['status'] == 'maintenance' ? 'warning' : 
                                                    ($item['status'] == 'repair' ? 'danger' : 'secondary')); 
                                            ?>">
                                                <?php echo htmlspecialchars(ucfirst($item['status'])); ?>
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
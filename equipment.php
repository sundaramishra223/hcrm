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
if (!in_array($user_role, ['admin', 'nurse', 'doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get equipment list
try {
    $equipment = $db->query("SELECT * FROM equipment ORDER BY equipment_name")->fetchAll();
} catch (Exception $e) {
    $equipment = [];
}
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
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <?php if ($user_role === 'admin'): ?>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <?php endif; ?>
                <li><a href="equipment.php" class="active"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-tools"></i> Equipment Management</h1>
                    <p>Manage hospital equipment and medical devices</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Equipment Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($equipment); ?></h3>
                        <p>Total Equipment</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count(array_filter($equipment, function($e) { return $e['status'] === 'active'; })); ?></h3>
                        <p>Active Equipment</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-wrench"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count(array_filter($equipment, function($e) { return $e['status'] === 'maintenance'; })); ?></h3>
                        <p>Under Maintenance</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count(array_filter($equipment, function($e) { return $e['status'] === 'repair'; })); ?></h3>
                        <p>Needs Repair</p>
                    </div>
                </div>
            </div>

            <!-- Equipment List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Equipment Inventory</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($equipment)): ?>
                        <p class="text-muted text-center">No equipment found in inventory.</p>
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
                                        <th>Purchase Date</th>
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
                                            <td><?php echo $item['purchase_date'] ? date('d M Y', strtotime($item['purchase_date'])) : 'N/A'; ?></td>
                                            <td>
                                                <?php
                                                $statusColors = [
                                                    'active' => 'success',
                                                    'maintenance' => 'warning',
                                                    'repair' => 'danger',
                                                    'retired' => 'secondary'
                                                ];
                                                $statusColor = $statusColors[$item['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge badge-<?php echo $statusColor; ?>">
                                                    <?php echo ucfirst($item['status']); ?>
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

            <!-- Equipment by Category -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Equipment by Category</h3>
                </div>
                <div class="card-body">
                    <?php
                    $categories = [];
                    foreach ($equipment as $item) {
                        $cat = $item['category'];
                        if (!isset($categories[$cat])) {
                            $categories[$cat] = 0;
                        }
                        $categories[$cat]++;
                    }
                    ?>
                    
                    <?php if (empty($categories)): ?>
                        <p class="text-muted text-center">No equipment categories found.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($categories as $category => $count): ?>
                                <div class="col-md-3 mb-3">
                                    <div class="category-card">
                                        <h4><?php echo $count; ?></h4>
                                        <p><?php echo htmlspecialchars($category); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
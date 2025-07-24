<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'accountant', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get bills
$bills_query = "
    SELECT b.*, p.first_name, p.last_name, p.patient_id 
    FROM billing b 
    LEFT JOIN patients p ON b.patient_id = p.id 
    ORDER BY b.created_at DESC 
    LIMIT 50
";
$bills = $db->query($bills_query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Management - Hospital CRM</title>
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
                <li><a href="billing.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1><i class="fas fa-file-invoice-dollar"></i> Billing Management</h1>
                    <p>Manage patient bills and payments</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Bills List -->
            <div class="card">
                <h3><i class="fas fa-list"></i> Recent Bills</h3>
                
                <?php if (empty($bills)): ?>
                    <p class="text-muted text-center">No bills found.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Bill ID</th>
                                    <th>Patient</th>
                                    <th>Bill Date</th>
                                    <th>Total Amount</th>
                                    <th>Paid Amount</th>
                                    <th>Balance</th>
                                    <th>Payment Status</th>
                                    <th>Payment Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bills as $bill): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bill['bill_id']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($bill['patient_id'] . ' - ' . $bill['first_name'] . ' ' . $bill['last_name']); ?>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($bill['bill_date'])); ?></td>
                                        <td><?php echo formatCurrency($bill['total_amount']); ?></td>
                                        <td><?php echo formatCurrency($bill['paid_amount']); ?></td>
                                        <td><?php echo formatCurrency($bill['balance_amount']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $bill['payment_status'] == 'paid' ? 'success' : 
                                                    ($bill['payment_status'] == 'pending' ? 'warning' : 
                                                    ($bill['payment_status'] == 'overdue' ? 'danger' : 'info')); 
                                            ?>">
                                                <?php echo htmlspecialchars(ucfirst($bill['payment_status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $bill['payment_method'] ?? 'N/A'))); ?></td>
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
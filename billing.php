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
if (!in_array($user_role, ['admin', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Get recent bills
try {
    $bills = $db->query("
        SELECT b.*, 
               p.patient_id, p.first_name, p.last_name 
        FROM billing b 
        LEFT JOIN patients p ON b.patient_id = p.id 
        ORDER BY b.created_at DESC 
        LIMIT 20
    ")->fetchAll();
} catch (Exception $e) {
    $bills = [];
}

// Get billing statistics
try {
    $stats = [];
    
    // Total bills this month
    $result = $db->query("SELECT COUNT(*) as count FROM billing WHERE MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE())")->fetch();
    $stats['total_bills'] = $result['count'];
    
    // Total revenue this month
    $result = $db->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM billing WHERE MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE())")->fetch();
    $stats['total_revenue'] = $result['revenue'];
    
    // Paid amount this month
    $result = $db->query("SELECT COALESCE(SUM(paid_amount), 0) as paid FROM billing WHERE MONTH(bill_date) = MONTH(CURDATE()) AND YEAR(bill_date) = YEAR(CURDATE())")->fetch();
    $stats['paid_amount'] = $result['paid'];
    
    // Pending amount
    $result = $db->query("SELECT COALESCE(SUM(balance_amount), 0) as pending FROM billing WHERE payment_status IN ('pending', 'partial')")->fetch();
    $stats['pending_amount'] = $result['pending'];
    
    // Overdue bills
    $result = $db->query("SELECT COUNT(*) as count FROM billing WHERE payment_status = 'overdue'")->fetch();
    $stats['overdue_bills'] = $result['count'];
    
} catch (Exception $e) {
    $stats = [
        'total_bills' => 0,
        'total_revenue' => 0,
        'paid_amount' => 0,
        'pending_amount' => 0,
        'overdue_bills' => 0
    ];
}
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
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php" class="active"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
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
                    <h1><i class="fas fa-file-invoice-dollar"></i> Billing Management</h1>
                    <p>Manage patient bills and payments</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Billing Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($stats['total_bills']); ?></h3>
                    <p><i class="fas fa-file-invoice"></i> Total Bills This Month</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo formatCurrency($stats['total_revenue']); ?></h3>
                    <p><i class="fas fa-chart-line"></i> Total Revenue</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo formatCurrency($stats['paid_amount']); ?></h3>
                    <p><i class="fas fa-check-circle"></i> Paid Amount</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo formatCurrency($stats['pending_amount']); ?></h3>
                    <p><i class="fas fa-clock"></i> Pending Amount</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($stats['overdue_bills']); ?></h3>
                    <p><i class="fas fa-exclamation-triangle"></i> Overdue Bills</p>
                </div>
            </div>

            <!-- Recent Bills -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Recent Bills</h3>
                </div>
                <div class="card-body">
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
                                        <th>Due Date</th>
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
                                            <td>
                                                <?php if ($bill['due_date']): ?>
                                                    <?php 
                                                    $due_date = new DateTime($bill['due_date']);
                                                    $today = new DateTime();
                                                    if ($due_date < $today && $bill['payment_status'] != 'paid'): ?>
                                                        <span class="badge badge-danger"><?php echo date('d M Y', strtotime($bill['due_date'])); ?></span>
                                                    <?php else: ?>
                                                        <?php echo date('d M Y', strtotime($bill['due_date'])); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <div class="quick-action">
                            <i class="fas fa-plus-circle"></i>
                            <h4>Generate New Bill</h4>
                            <p>Create a new bill for patient services</p>
                        </div>
                        <div class="quick-action">
                            <i class="fas fa-search"></i>
                            <h4>Search Bills</h4>
                            <p>Search bills by patient or bill ID</p>
                        </div>
                        <div class="quick-action">
                            <i class="fas fa-money-bill-wave"></i>
                            <h4>Record Payment</h4>
                            <p>Record payment for existing bills</p>
                        </div>
                        <div class="quick-action">
                            <i class="fas fa-file-export"></i>
                            <h4>Export Reports</h4>
                            <p>Export billing reports and statements</p>
                        </div>
                        <div class="quick-action">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h4>Overdue Bills</h4>
                            <p>View and manage overdue payments</p>
                        </div>
                        <div class="quick-action">
                            <i class="fas fa-chart-pie"></i>
                            <h4>Revenue Analytics</h4>
                            <p>View detailed revenue analytics</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
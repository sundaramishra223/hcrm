<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$patient_id = $_SESSION['user_id'];

// Get patient details
$patient = $db->query("SELECT * FROM patients WHERE user_id = ?", [$patient_id])->fetch();

if (!$patient) {
    header('Location: dashboard.php');
    exit;
}

// Get patient's bills
$sql = "SELECT b.*, 
        CASE 
            WHEN b.bill_type = 'consultation' THEN 'Consultation'
            WHEN b.bill_type = 'lab' THEN 'Laboratory'
            WHEN b.bill_type = 'pharmacy' THEN 'Pharmacy'
            WHEN b.bill_type = 'equipment' THEN 'Equipment'
            WHEN b.bill_type = 'bed' THEN 'Bed Charges'
            WHEN b.bill_type = 'comprehensive' THEN 'Comprehensive'
            ELSE b.bill_type
        END as bill_type_display
        FROM bills b
        WHERE b.patient_id = ?
        ORDER BY b.created_at DESC";

$bills = $db->query($sql, [$patient['id']])->fetchAll();

// Get bill statistics
$stats = [];
try {
    $stats['total_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE patient_id = ?", [$patient['id']])->fetch()['count'];
    $stats['paid_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE patient_id = ? AND payment_status = 'paid'", [$patient['id']])->fetch()['count'];
    $stats['pending_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE patient_id = ? AND payment_status = 'pending'", [$patient['id']])->fetch()['count'];
    $stats['total_amount'] = $db->query("SELECT SUM(total_amount) as total FROM bills WHERE patient_id = ?", [$patient['id']])->fetch()['total'] ?? 0;
    $stats['paid_amount'] = $db->query("SELECT SUM(paid_amount) as total FROM bills WHERE patient_id = ? AND payment_status = 'paid'", [$patient['id']])->fetch()['total'] ?? 0;
    $stats['pending_amount'] = $db->query("SELECT SUM(balance_amount) as total FROM bills WHERE patient_id = ? AND payment_status IN ('pending', 'partial')", [$patient['id']])->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $stats = ['total_bills' => 0, 'paid_bills' => 0, 'pending_bills' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'pending_amount' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bills - Hospital CRM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #004685;
            font-size: 24px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn-primary {
            background: #004685;
            color: white;
        }
        
        .btn-primary:hover {
            background: #003366;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: black;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #004685;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
        
        .bills-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending { background: #ffc107; color: black; }
        .status-partial { background: #fd7e14; color: white; }
        .status-paid { background: #28a745; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
        
        .amount {
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        
        .amount.paid { color: #28a745; }
        .amount.pending { color: #dc3545; }
        
        .no-bills {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-bills i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .bill-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 12px;
        }
        
        .bill-details strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-money-bill-wave"></i> My Bills</h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_bills']); ?></h3>
                <p>Total Bills</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['paid_bills']); ?></h3>
                <p>Paid Bills</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['pending_bills']); ?></h3>
                <p>Pending Bills</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($stats['total_amount'], 2); ?></h3>
                <p>Total Amount</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($stats['paid_amount'], 2); ?></h3>
                <p>Paid Amount</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($stats['pending_amount'], 2); ?></h3>
                <p>Pending Amount</p>
            </div>
        </div>

        <!-- Bills Table -->
        <div class="bills-table">
            <?php if (empty($bills)): ?>
                <div class="no-bills">
                    <i class="fas fa-receipt"></i>
                    <h3>No Bills Found</h3>
                    <p>You don't have any bills yet.</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Bill Number</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Total Amount</th>
                            <th>Paid Amount</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Payment Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($bill['bill_number']); ?></strong>
                                    <div class="bill-details">
                                        <strong>Bill ID:</strong> <?php echo $bill['id']; ?><br>
                                        <strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($bill['created_at'])); ?>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($bill['bill_date'])); ?></td>
                                <td><?php echo htmlspecialchars($bill['bill_type_display']); ?></td>
                                <td class="amount">₹<?php echo number_format($bill['total_amount'], 2); ?></td>
                                <td class="amount paid">₹<?php echo number_format($bill['paid_amount'], 2); ?></td>
                                <td class="amount <?php echo $bill['balance_amount'] > 0 ? 'pending' : 'paid'; ?>">
                                    ₹<?php echo number_format($bill['balance_amount'], 2); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $bill['payment_status']; ?>">
                                        <?php echo ucfirst($bill['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo ucfirst($bill['payment_method'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
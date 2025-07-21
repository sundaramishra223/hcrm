<?php
session_start();
require_once 'config/database.php';

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
$message = '';

// Handle bill creation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_bill') {
    try {
        $db->getConnection()->beginTransaction();
        
        // Generate bill number
        $bill_number = 'BILL' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
        
        // Calculate totals
        $subtotal = 0;
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['name']) && !empty($item['quantity']) && !empty($item['price'])) {
                    $subtotal += (float)$item['quantity'] * (float)$item['price'];
                }
            }
        }
        
        $discount = (float)($_POST['discount_amount'] ?? 0);
        $tax_rate = (float)($_POST['tax_rate'] ?? 0); // Optional tax rate
        $tax_amount = $tax_rate > 0 ? ($subtotal - $discount) * ($tax_rate / 100) : 0;
        $total_amount = $subtotal - $discount + $tax_amount;
        
        // Insert bill
        $bill_sql = "INSERT INTO bills (patient_id, visit_id, bill_number, bill_date, bill_type, subtotal, discount_amount, tax_amount, total_amount, balance_amount, payment_status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
        
        $db->query($bill_sql, [
            $_POST['patient_id'],
            $_POST['visit_id'] ?: null,
            $bill_number,
            $_POST['bill_date'],
            $_POST['bill_type'],
            $subtotal,
            $discount,
            $tax_amount,
            $total_amount,
            $total_amount, // Initially balance = total
            $_POST['notes'],
            $_SESSION['user_id']
        ]);
        
        $bill_id = $db->lastInsertId();
        
        // Insert bill items
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['name']) && !empty($item['quantity']) && !empty($item['price'])) {
                    $item_total = (float)$item['quantity'] * (float)$item['price'];
                    $item_discount = (float)($item['discount'] ?? 0);
                    $item_final = $item_total - $item_discount;
                    
                    $item_sql = "INSERT INTO bill_items (bill_id, item_type, item_name, item_code, quantity, unit_price, total_price, discount_amount, final_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $db->query($item_sql, [
                        $bill_id,
                        $item['type'],
                        $item['name'],
                        $item['code'] ?? '',
                        $item['quantity'],
                        $item['price'],
                        $item_total,
                        $item_discount,
                        $item_final
                    ]);
                }
            }
        }
        
        $db->getConnection()->commit();
        $message = "Bill created successfully! Bill Number: " . $bill_number;
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle payment recording
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    try {
        $db->getConnection()->beginTransaction();
        
        $bill_id = $_POST['bill_id'];
        $payment_amount = (float)$_POST['payment_amount'];
        $payment_method = $_POST['payment_method'];
        $payment_reference = $_POST['payment_reference'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        // Get current bill details
        $bill = $db->query("SELECT * FROM bills WHERE id = ?", [$bill_id])->fetch();
        
        if ($bill) {
            $new_paid_amount = $bill['paid_amount'] + $payment_amount;
            $new_balance = $bill['total_amount'] - $new_paid_amount;
            
            // Determine payment status
            $payment_status = 'partial';
            if ($new_balance <= 0) {
                $payment_status = 'paid';
                $new_balance = 0;
            } elseif ($new_paid_amount == 0) {
                $payment_status = 'pending';
            }
            
            // Update bill
            $db->query(
                "UPDATE bills SET paid_amount = ?, balance_amount = ?, payment_status = ?, payment_method = ?, updated_at = NOW() WHERE id = ?",
                [$new_paid_amount, $new_balance, $payment_status, $payment_method, $bill_id]
            );
            
            // Record payment in bill_payments table (if exists)
            try {
                $payment_sql = "INSERT INTO bill_payments (bill_id, payment_amount, payment_method, payment_reference, payment_date, notes, recorded_by) VALUES (?, ?, ?, ?, NOW(), ?, ?)";
                $db->query($payment_sql, [
                    $bill_id,
                    $payment_amount,
                    $payment_method,
                    $payment_reference,
                    $notes,
                    $_SESSION['user_id']
                ]);
            } catch (Exception $e) {
                // If bill_payments table doesn't exist, continue without recording payment history
                error_log("Bill payments table not found: " . $e->getMessage());
            }
            
            $db->getConnection()->commit();
            $message = "Payment recorded successfully!";
        }
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Get bills with search and filters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

$sql = "SELECT b.*, 
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.patient_id,
        p.phone as patient_phone
        FROM bills b
        JOIN patients p ON b.patient_id = p.id
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (b.bill_number LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_fill(0, 4, $search_param);
}

if ($filter_status) {
    $sql .= " AND b.payment_status = ?";
    $params[] = $filter_status;
}

if ($filter_date_from) {
    $sql .= " AND b.bill_date >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $sql .= " AND b.bill_date <= ?";
    $params[] = $filter_date_to;
}

$sql .= " ORDER BY b.created_at DESC";

$bills = $db->query($sql, $params)->fetchAll();

// Get patients for bill creation
$patients = $db->query("
    SELECT id, patient_id, CONCAT(first_name, ' ', last_name) as full_name, phone 
    FROM patients 
    ORDER BY first_name, last_name
")->fetchAll();

// Get billing statistics
$stats = [];
try {
    $stats['total_bills'] = $db->query("SELECT COUNT(*) as count FROM bills")->fetch()['count'];
    $stats['pending_amount'] = $db->query("SELECT SUM(balance_amount) as amount FROM bills WHERE payment_status != 'paid'")->fetch()['amount'] ?? 0;
    $stats['today_revenue'] = $db->query("SELECT SUM(paid_amount) as amount FROM bills WHERE DATE(updated_at) = CURDATE()")->fetch()['amount'] ?? 0;
    $stats['monthly_revenue'] = $db->query("SELECT SUM(paid_amount) as amount FROM bills WHERE MONTH(updated_at) = MONTH(CURDATE()) AND YEAR(updated_at) = YEAR(CURDATE())")->fetch()['amount'] ?? 0;
} catch (Exception $e) {
    $stats = ['total_bills' => 0, 'pending_amount' => 0, 'today_revenue' => 0, 'monthly_revenue' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Management - Hospital CRM</title>
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
            color: #333;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            font-size: 24px;
            color: #004685;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
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
            font-size: 14px;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-partial {
            background: #cce5ff;
            color: #004085;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-refunded {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            margin: 20px auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e1e1e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            color: #004685;
            margin: 0;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .bill-items {
            border: 1px solid #e1e1e1;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 1fr 1fr 80px 80px 80px auto;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .item-row input, .item-row select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 14px;
        }
        
        .bill-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .summary-row.total {
            font-weight: 600;
            font-size: 16px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .item-row {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .table .hide-mobile {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Billing Management</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button onclick="openBillModal()" class="btn btn-primary">+ Create New Bill</button>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_bills']); ?></h3>
                <p>Total Bills</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($stats['pending_amount'], 2); ?></h3>
                <p>Pending Amount</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($stats['today_revenue'], 2); ?></h3>
                <p>Today's Revenue</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($stats['monthly_revenue'], 2); ?></h3>
                <p>Monthly Revenue</p>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Search Bills</label>
                    <input type="text" name="search" id="search" placeholder="Search by bill number, patient name..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="partial" <?php echo $filter_status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="paid" <?php echo $filter_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="refunded" <?php echo $filter_status === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">To Date</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="billing.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
        
        <div class="bills-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Bill Number</th>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Paid Amount</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: #666;">
                                No bills found for the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($bill['bill_number']); ?></strong>
                                    <br>
                                    <small style="color: #666;"><?php echo ucfirst($bill['bill_type']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($bill['patient_name']); ?></strong>
                                    <br>
                                    <small style="color: #666;">
                                        ID: <?php echo htmlspecialchars($bill['patient_id']); ?><br>
                                        <?php echo htmlspecialchars($bill['patient_phone']); ?>
                                    </small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($bill['bill_date'])); ?></td>
                                <td><strong>₹<?php echo number_format($bill['total_amount'], 2); ?></strong></td>
                                <td>₹<?php echo number_format($bill['paid_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($bill['balance_amount'], 2); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $bill['payment_status']; ?>">
                                        <?php echo ucfirst($bill['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($bill['payment_status'] !== 'paid'): ?>
                                        <button onclick="openPaymentModal(<?php echo $bill['id']; ?>, '<?php echo $bill['bill_number']; ?>', <?php echo $bill['balance_amount']; ?>)" 
                                                class="btn btn-success btn-sm">Record Payment</button>
                                    <?php endif; ?>
                                    <a href="bill-details.php?id=<?php echo $bill['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Create Bill Modal -->
    <div id="billModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Bill</h2>
                <button type="button" class="close" onclick="closeBillModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="billForm">
                    <input type="hidden" name="action" value="create_bill">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="patient_id">Patient *</label>
                            <select id="patient_id" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bill_date">Bill Date *</label>
                            <input type="date" id="bill_date" name="bill_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bill_type">Bill Type *</label>
                            <select id="bill_type" name="bill_type" required>
                                <option value="consultation">Consultation</option>
                                <option value="pharmacy">Pharmacy</option>
                                <option value="lab">Laboratory</option>
                                <option value="admission">Admission</option>
                                <option value="equipment">Equipment</option>
                                <option value="miscellaneous">Miscellaneous</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="visit_id">Visit ID (Optional)</label>
                            <input type="text" id="visit_id" name="visit_id">
                        </div>
                    </div>
                    
                    <div class="bill-items">
                        <h4 style="margin-bottom: 15px; color: #004685;">Bill Items</h4>
                        <div class="item-row" style="font-weight: 600; color: #666;">
                            <div>Item Name</div>
                            <div>Type</div>
                            <div>Qty</div>
                            <div>Price</div>
                            <div>Total</div>
                            <div>Action</div>
                        </div>
                        <div id="billItems">
                            <div class="item-row">
                                <input type="text" name="items[0][name]" placeholder="Item name" required>
                                <select name="items[0][type]">
                                    <option value="consultation">Consultation</option>
                                    <option value="medicine">Medicine</option>
                                    <option value="test">Test</option>
                                    <option value="equipment">Equipment</option>
                                    <option value="bed">Bed</option>
                                    <option value="miscellaneous">Other</option>
                                </select>
                                <input type="number" name="items[0][quantity]" placeholder="1" min="1" step="1" value="1" required>
                                <input type="number" name="items[0][price]" placeholder="0.00" min="0" step="0.01" required>
                                <input type="number" class="item-total" readonly>
                                <button type="button" onclick="removeItem(this)" class="btn btn-sm" style="background: #dc3545; color: white;">×</button>
                            </div>
                        </div>
                        <button type="button" onclick="addItem()" class="btn btn-secondary">+ Add Item</button>
                    </div>
                    
                    <div class="bill-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="subtotal">₹0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Discount:</span>
                            <span>
                                ₹<input type="number" name="discount_amount" id="discount_amount" value="0" min="0" step="0.01" 
                                        style="width: 80px; border: 1px solid #ddd; padding: 2px;">
                            </span>
                        </div>
                        <div class="summary-row">
                            <span>Tax:</span>
                            <span>
                                <input type="number" name="tax_rate" id="tax_rate" value="0" min="0" max="100" step="0.01" 
                                       style="width: 50px; border: 1px solid #ddd; padding: 2px;" 
                                       placeholder="0" onchange="calculateTotal()">% = 
                                <span id="tax_amount">₹0.00</span>
                            </span>
                        </div>
                        <div class="summary-row total">
                            <span>Total Amount:</span>
                            <span id="total_amount">₹0.00</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Additional notes or comments..."></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeBillModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Bill</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Record Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Record Payment</h2>
                <button type="button" class="close" onclick="closePaymentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="bill_id" id="payment_bill_id">
                    
                    <div class="form-group">
                        <label>Bill Number: <strong id="payment_bill_number"></strong></label>
                    </div>
                    
                    <div class="form-group">
                        <label>Outstanding Balance: <strong id="payment_balance"></strong></label>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="payment_amount">Payment Amount *</label>
                            <input type="number" id="payment_amount" name="payment_amount" min="0" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="payment_method">Payment Method *</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="online">Online Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="insurance">Insurance</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="payment_reference">Payment Reference</label>
                            <input type="text" id="payment_reference" name="payment_reference" placeholder="Transaction ID, Cheque No., etc.">
                        </div>
                        <div class="form-group">
                            <label for="payment_notes">Notes</label>
                            <textarea id="payment_notes" name="notes" rows="2" placeholder="Additional notes..."></textarea>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closePaymentModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Show message if exists
        <?php if ($message): ?>
            alert('<?php echo addslashes($message); ?>');
        <?php endif; ?>
        
        let itemCount = 1;
        
        function openBillModal() {
            document.getElementById('billModal').style.display = 'block';
            calculateTotal();
        }
        
        function closeBillModal() {
            document.getElementById('billModal').style.display = 'none';
        }
        
        function openPaymentModal(billId, billNumber, balance) {
            document.getElementById('payment_bill_id').value = billId;
            document.getElementById('payment_bill_number').textContent = billNumber;
            document.getElementById('payment_balance').textContent = '₹' + balance.toFixed(2);
            document.getElementById('payment_amount').value = balance.toFixed(2);
            document.getElementById('paymentModal').style.display = 'block';
        }
        
        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }
        
        function addItem() {
            const itemsContainer = document.getElementById('billItems');
            const newItem = document.createElement('div');
            newItem.className = 'item-row';
            newItem.innerHTML = `
                <input type="text" name="items[${itemCount}][name]" placeholder="Item name" required>
                <select name="items[${itemCount}][type]">
                    <option value="consultation">Consultation</option>
                    <option value="medicine">Medicine</option>
                    <option value="test">Test</option>
                    <option value="equipment">Equipment</option>
                    <option value="bed">Bed</option>
                    <option value="miscellaneous">Other</option>
                </select>
                <input type="number" name="items[${itemCount}][quantity]" placeholder="1" min="1" step="1" value="1" required>
                <input type="number" name="items[${itemCount}][price]" placeholder="0.00" min="0" step="0.01" required>
                <input type="number" class="item-total" readonly>
                <button type="button" onclick="removeItem(this)" class="btn btn-sm" style="background: #dc3545; color: white;">×</button>
            `;
            itemsContainer.appendChild(newItem);
            itemCount++;
            
            // Add event listeners to new inputs
            const quantityInput = newItem.querySelector('input[name*="[quantity]"]');
            const priceInput = newItem.querySelector('input[name*="[price]"]');
                                    quantityInput.addEventListener('input', calculateTotal);
                        priceInput.addEventListener('input', calculateTotal);
        }
        
        function removeItem(button) {
            button.closest('.item-row').remove();
            calculateTotal();
        }
        
        function calculateTotal() {
            let subtotal = 0;
            
            document.querySelectorAll('.item-row').forEach(row => {
                const quantityInput = row.querySelector('input[name*="[quantity]"]');
                const priceInput = row.querySelector('input[name*="[price]"]');
                const totalInput = row.querySelector('.item-total');
                
                if (quantityInput && priceInput && totalInput) {
                    const quantity = parseFloat(quantityInput.value) || 0;
                    const price = parseFloat(priceInput.value) || 0;
                    const total = quantity * price;
                    
                    totalInput.value = total.toFixed(2);
                    subtotal += total;
                }
            });
            
            const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
            const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
            const taxableAmount = subtotal - discount;
            const taxAmount = taxableAmount * (taxRate / 100);
            const totalAmount = taxableAmount + taxAmount;
            
            document.getElementById('subtotal').textContent = '₹' + subtotal.toFixed(2);
            document.getElementById('tax_amount').textContent = '₹' + taxAmount.toFixed(2);
            document.getElementById('total_amount').textContent = '₹' + totalAmount.toFixed(2);
        }
        
        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Initial calculation
            calculateTotal();
            
            // Add listeners to existing inputs
            document.querySelectorAll('input[name*="[quantity]"], input[name*="[price]"]').forEach(input => {
                input.addEventListener('input', calculateTotal);
            });
            
            document.getElementById('discount_amount').addEventListener('input', calculateTotal);
            document.getElementById('tax_rate').addEventListener('input', calculateTotal);
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const billModal = document.getElementById('billModal');
            const paymentModal = document.getElementById('paymentModal');
            
            if (event.target === billModal) {
                closeBillModal();
            }
            if (event.target === paymentModal) {
                closePaymentModal();
            }
        }
    </script>
</body>
</html>

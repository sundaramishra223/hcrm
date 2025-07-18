<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
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
$message = '';

// Get lab technician ID if user is lab tech
$lab_tech_id = null;
if ($user_role === 'lab_technician') {
    $lab_tech_info = $db->query("SELECT id FROM staff WHERE user_id = ? AND staff_type = 'lab_technician'", [$_SESSION['user_id']])->fetch();
    $lab_tech_id = $lab_tech_info['id'] ?? null;
}

// Handle lab order creation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    try {
        $db->getConnection()->beginTransaction();
        
        // Generate order number
        $order_number = 'LAB' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
        
        // Calculate total cost
        $total_cost = 0;
        if (isset($_POST['tests']) && is_array($_POST['tests'])) {
            foreach ($_POST['tests'] as $test_id) {
                if (!empty($test_id)) {
                    $test = $db->query("SELECT cost FROM lab_tests WHERE id = ?", [$test_id])->fetch();
                    $total_cost += $test['cost'] ?? 0;
                }
            }
        }
        
        // Insert lab order
        $order_sql = "INSERT INTO lab_orders (patient_id, doctor_id, order_number, order_date, expected_date, priority, status, total_cost, clinical_notes, created_by) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)";
        
        $db->query($order_sql, [
            $_POST['patient_id'],
            $_POST['doctor_id'],
            $order_number,
            $_POST['order_date'],
            $_POST['expected_date'] ?: null,
            $_POST['priority'],
            $total_cost,
            $_POST['clinical_notes'],
            $_SESSION['user_id']
        ]);
        
        $order_id = $db->lastInsertId();
        
        // Insert lab order tests
        if (isset($_POST['tests']) && is_array($_POST['tests'])) {
            foreach ($_POST['tests'] as $test_id) {
                if (!empty($test_id)) {
                    $test = $db->query("SELECT * FROM lab_tests WHERE id = ?", [$test_id])->fetch();
                    if ($test) {
                        $test_sql = "INSERT INTO lab_order_tests (lab_order_id, lab_test_id, test_name, cost, status) VALUES (?, ?, ?, ?, 'pending')";
                        $db->query($test_sql, [$order_id, $test_id, $test['name'], $test['cost']]);
                    }
                }
            }
        }
        
        $db->getConnection()->commit();
        $message = "Lab order created successfully! Order Number: " . $order_number;
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle result submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'submit_result') {
    try {
        $db->getConnection()->beginTransaction();
        
        $order_test_id = $_POST['order_test_id'];
        $result_value = $_POST['result_value'];
        $result_unit = $_POST['result_unit'];
        $normal_range = $_POST['normal_range'];
        $result_status = $_POST['result_status'];
        $technician_notes = $_POST['technician_notes'];
        
        // Update lab order test with result
        $result_sql = "UPDATE lab_order_tests SET 
                       result_value = ?, 
                       result_unit = ?, 
                       normal_range = ?, 
                       result_status = ?, 
                       technician_notes = ?, 
                       status = 'completed',
                       completed_by = ?,
                       completed_at = NOW()
                       WHERE id = ?";
        
        $db->query($result_sql, [
            $result_value,
            $result_unit,
            $normal_range,
            $result_status,
            $technician_notes,
            $lab_tech_id ?: $_SESSION['user_id'],
            $order_test_id
        ]);
        
        // Check if all tests in the order are completed
        $order_id = $db->query("SELECT lab_order_id FROM lab_order_tests WHERE id = ?", [$order_test_id])->fetch()['lab_order_id'];
        $pending_tests = $db->query("SELECT COUNT(*) as count FROM lab_order_tests WHERE lab_order_id = ? AND status != 'completed'", [$order_id])->fetch()['count'];
        
        if ($pending_tests == 0) {
            $db->query("UPDATE lab_orders SET status = 'completed', completed_at = NOW() WHERE id = ?", [$order_id]);
        }
        
        $db->getConnection()->commit();
        $message = "Test result submitted successfully!";
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle order status update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $order_id = $_POST['order_id'];
        $new_status = $_POST['new_status'];
        
        if ($new_status === 'in_progress') {
            $db->query("UPDATE lab_orders SET status = ?, started_at = NOW() WHERE id = ?", [$new_status, $order_id]);
        } else {
            $db->query("UPDATE lab_orders SET status = ? WHERE id = ?", [$new_status, $order_id]);
        }
        
        $message = "Order status updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get lab orders with search and filters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_priority = $_GET['priority'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

$sql = "SELECT lo.*, 
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.patient_id,
        p.phone as patient_phone,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        d.specialization,
        (SELECT COUNT(*) FROM lab_order_tests WHERE lab_order_id = lo.id) as test_count,
        (SELECT COUNT(*) FROM lab_order_tests WHERE lab_order_id = lo.id AND status = 'completed') as completed_tests
        FROM lab_orders lo
        JOIN patients p ON lo.patient_id = p.id
        JOIN doctors d ON lo.doctor_id = d.id
        WHERE p.hospital_id = 1";

$params = [];

// Role-based filtering
if ($user_role === 'doctor') {
    $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
    $sql .= " AND lo.doctor_id = ?";
    $params[] = $doctor_id;
}

if ($search) {
    $sql .= " AND (lo.order_number LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($filter_status) {
    $sql .= " AND lo.status = ?";
    $params[] = $filter_status;
}

if ($filter_priority) {
    $sql .= " AND lo.priority = ?";
    $params[] = $filter_priority;
}

if ($filter_date_from) {
    $sql .= " AND lo.order_date >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $sql .= " AND lo.order_date <= ?";
    $params[] = $filter_date_to;
}

$sql .= " ORDER BY lo.created_at DESC";

$lab_orders = $db->query($sql, $params)->fetchAll();

// Get pending tests for lab technicians
$pending_tests = [];
if ($user_role === 'lab_technician') {
    $pending_tests = $db->query("
        SELECT lot.*, lo.order_number, lo.order_date, lo.priority,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.patient_id, lt.normal_range as test_normal_range
        FROM lab_order_tests lot
        JOIN lab_orders lo ON lot.lab_order_id = lo.id
        JOIN patients p ON lo.patient_id = p.id
        JOIN lab_tests lt ON lot.lab_test_id = lt.id
        WHERE lot.status IN ('pending', 'in_progress')
        AND lo.status IN ('pending', 'in_progress')
        ORDER BY lo.priority DESC, lo.order_date ASC
        LIMIT 20
    ")->fetchAll();
}

// Get patients for order creation
$patients = $db->query("
    SELECT id, patient_id, CONCAT(first_name, ' ', last_name) as full_name, phone 
    FROM patients 
    WHERE hospital_id = 1 
    ORDER BY first_name, last_name
")->fetchAll();

// Get doctors for order creation
$doctors = $db->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as full_name, specialization
    FROM doctors 
    WHERE hospital_id = 1 AND is_available = 1
    ORDER BY first_name, last_name
")->fetchAll();

// Get available lab tests
$lab_tests = $db->query("
    SELECT * FROM lab_tests 
    WHERE hospital_id = 1 AND is_active = 1 
    ORDER BY category, name
")->fetchAll();

// Get lab statistics
$stats = [];
try {
    $stats['total_orders'] = $db->query("SELECT COUNT(*) as count FROM lab_orders")->fetch()['count'];
    $stats['pending_orders'] = $db->query("SELECT COUNT(*) as count FROM lab_orders WHERE status IN ('pending', 'in_progress')")->fetch()['count'];
    $stats['completed_today'] = $db->query("SELECT COUNT(*) as count FROM lab_orders WHERE status = 'completed' AND DATE(completed_at) = CURDATE()")->fetch()['count'];
    $stats['total_revenue'] = $db->query("SELECT SUM(total_cost) as revenue FROM lab_orders WHERE status = 'completed'")->fetch()['revenue'] ?? 0;
} catch (Exception $e) {
    $stats = ['total_orders' => 0, 'pending_orders' => 0, 'completed_today' => 0, 'total_revenue' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Management - Hospital CRM</title>
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
        
        .tabs {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .tab-buttons {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .tab-button {
            padding: 15px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }
        
        .tab-button.active {
            background: white;
            color: #004685;
            border-bottom: 2px solid #004685;
        }
        
        .tab-content {
            padding: 25px;
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
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
        
        .orders-table {
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
        
        .badge-in_progress {
            background: #cce5ff;
            color: #004085;
        }
        
        .badge-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .priority-high {
            color: #dc3545;
            font-weight: 600;
        }
        
        .priority-medium {
            color: #ffc107;
            font-weight: 600;
        }
        
        .priority-low {
            color: #28a745;
            font-weight: 600;
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
            max-width: 800px;
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
        
        .test-selection {
            border: 1px solid #e1e1e1;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .test-category {
            margin-bottom: 15px;
        }
        
        .test-category h4 {
            color: #004685;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .test-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            margin-bottom: 5px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .test-item label {
            display: flex;
            align-items: center;
            flex: 1;
            margin: 0;
        }
        
        .test-item input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        
        .test-cost {
            font-weight: 600;
            color: #004685;
        }
        
        .pending-tests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .test-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .test-card h4 {
            color: #004685;
            margin-bottom: 10px;
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .tab-buttons {
                flex-direction: column;
            }
            
            .pending-tests-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Laboratory Management</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                    <button onclick="openOrderModal()" class="btn btn-primary">+ Create Lab Order</button>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_orders']); ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['pending_orders']); ?></h3>
                <p>Pending Orders</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['completed_today']); ?></h3>
                <p>Completed Today</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="showTab('orders')">Lab Orders</button>
                <?php if ($user_role === 'lab_technician'): ?>
                    <button class="tab-button" onclick="showTab('pending-tests')">Pending Tests</button>
                <?php endif; ?>
            </div>
            
            <div id="orders" class="tab-content active">
                <div class="filters">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="search">Search Orders</label>
                            <input type="text" name="search" id="search" placeholder="Search by order number, patient name..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select name="priority" id="priority">
                                <option value="">All Priorities</option>
                                <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Low</option>
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
                            <a href="laboratory.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
                
                <div class="orders-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Tests</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Cost</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lab_orders)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 30px; color: #666;">
                                        No lab orders found for the selected criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lab_orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                            <br>
                                            <small style="color: #666;"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['patient_name']); ?></strong>
                                            <br>
                                            <small style="color: #666;">
                                                ID: <?php echo htmlspecialchars($order['patient_id']); ?><br>
                                                <?php echo htmlspecialchars($order['patient_phone']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong>Dr. <?php echo htmlspecialchars($order['doctor_name']); ?></strong>
                                            <br>
                                            <small style="color: #666;"><?php echo htmlspecialchars($order['specialization']); ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-pending"><?php echo $order['test_count']; ?> tests</span>
                                            <br>
                                            <small style="color: #666;">
                                                <?php echo $order['completed_tests']; ?>/<?php echo $order['test_count']; ?> completed
                                            </small>
                                        </td>
                                        <td>
                                            <span class="priority-<?php echo $order['priority']; ?>">
                                                <?php echo ucfirst($order['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </td>
                                        <td><strong>₹<?php echo number_format($order['total_cost'], 2); ?></strong></td>
                                        <td>
                                            <?php if ($order['status'] === 'pending' && $user_role === 'lab_technician'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="new_status" value="in_progress">
                                                    <button type="submit" class="btn btn-warning btn-sm">Start</button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="lab-order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($user_role === 'lab_technician'): ?>
            <div id="pending-tests" class="tab-content">
                <h3 style="color: #004685; margin-bottom: 20px;">Pending Test Results</h3>
                
                <?php if (empty($pending_tests)): ?>
                    <div style="text-align: center; padding: 50px; color: #666;">
                        <h3>No pending tests</h3>
                        <p>All tests are up to date!</p>
                    </div>
                <?php else: ?>
                    <div class="pending-tests-grid">
                        <?php foreach ($pending_tests as $test): ?>
                            <div class="test-card">
                                <h4><?php echo htmlspecialchars($test['test_name']); ?></h4>
                                
                                <div style="margin-bottom: 15px;">
                                    <strong>Order:</strong> <?php echo htmlspecialchars($test['order_number']); ?><br>
                                    <strong>Patient:</strong> <?php echo htmlspecialchars($test['patient_name']); ?> (<?php echo htmlspecialchars($test['patient_id']); ?>)<br>
                                    <strong>Priority:</strong> <span class="priority-<?php echo $test['priority']; ?>"><?php echo ucfirst($test['priority']); ?></span><br>
                                    <strong>Order Date:</strong> <?php echo date('M d, Y', strtotime($test['order_date'])); ?>
                                </div>
                                
                                <button onclick="openResultModal(<?php echo $test['id']; ?>, '<?php echo htmlspecialchars($test['test_name']); ?>', '<?php echo htmlspecialchars($test['test_normal_range']); ?>')" 
                                        class="btn btn-success btn-sm" style="width: 100%;">
                                    Submit Result
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create Lab Order Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Lab Order</h2>
                <button type="button" class="close" onclick="closeOrderModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="orderForm">
                    <input type="hidden" name="action" value="create_order">
                    
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
                            <label for="doctor_id">Doctor *</label>
                            <select id="doctor_id" name="doctor_id" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="order_date">Order Date *</label>
                            <input type="date" id="order_date" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="expected_date">Expected Date</label>
                            <input type="date" id="expected_date" name="expected_date" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priority *</label>
                        <select id="priority" name="priority" required>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    
                    <div class="test-selection">
                        <h4 style="margin-bottom: 15px; color: #004685;">Select Tests</h4>
                        <div id="testsList">
                            <?php
                            $current_category = '';
                            foreach ($lab_tests as $test):
                                if ($test['category'] !== $current_category):
                                    if ($current_category !== '') echo '</div>';
                                    $current_category = $test['category'];
                                    echo '<div class="test-category"><h4>' . htmlspecialchars($current_category) . '</h4>';
                                endif;
                            ?>
                                <div class="test-item">
                                    <label>
                                        <input type="checkbox" name="tests[]" value="<?php echo $test['id']; ?>" onchange="calculateTotal()">
                                        <?php echo htmlspecialchars($test['name']); ?>
                                    </label>
                                    <span class="test-cost" data-cost="<?php echo $test['cost']; ?>">₹<?php echo number_format($test['cost'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($current_category !== '') echo '</div>'; ?>
                        </div>
                        
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e1e1e1; text-align: right;">
                            <strong>Total Cost: <span id="totalCost">₹0.00</span></strong>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="clinical_notes">Clinical Notes</label>
                        <textarea id="clinical_notes" name="clinical_notes" rows="4" placeholder="Clinical indication, symptoms, etc."></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeOrderModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Submit Result Modal -->
    <div id="resultModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Submit Test Result</h2>
                <button type="button" class="close" onclick="closeResultModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="submit_result">
                    <input type="hidden" name="order_test_id" id="result_order_test_id">
                    
                    <div class="form-group">
                        <label>Test Name: <strong id="result_test_name"></strong></label>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="result_value">Result Value *</label>
                            <input type="text" id="result_value" name="result_value" required>
                        </div>
                        <div class="form-group">
                            <label for="result_unit">Unit</label>
                            <input type="text" id="result_unit" name="result_unit" placeholder="mg/dL, cells/μL, etc.">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="normal_range">Normal Range</label>
                        <input type="text" id="normal_range" name="normal_range">
                    </div>
                    
                    <div class="form-group">
                        <label for="result_status">Result Status *</label>
                        <select id="result_status" name="result_status" required>
                            <option value="normal">Normal</option>
                            <option value="abnormal">Abnormal</option>
                            <option value="borderline">Borderline</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="technician_notes">Technician Notes</label>
                        <textarea id="technician_notes" name="technician_notes" rows="3" placeholder="Additional observations or comments..."></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeResultModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-success">Submit Result</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function openOrderModal() {
            document.getElementById('orderModal').style.display = 'block';
            calculateTotal();
        }
        
        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
        
        function openResultModal(orderTestId, testName, normalRange) {
            document.getElementById('result_order_test_id').value = orderTestId;
            document.getElementById('result_test_name').textContent = testName;
            document.getElementById('normal_range').value = normalRange || '';
            document.getElementById('resultModal').style.display = 'block';
        }
        
        function closeResultModal() {
            document.getElementById('resultModal').style.display = 'none';
        }
        
        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('input[name="tests[]"]:checked').forEach(checkbox => {
                const costElement = checkbox.closest('.test-item').querySelector('.test-cost');
                const cost = parseFloat(costElement.dataset.cost) || 0;
                total += cost;
            });
            
            document.getElementById('totalCost').textContent = '₹' + total.toFixed(2);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const orderModal = document.getElementById('orderModal');
            const resultModal = document.getElementById('resultModal');
            
            if (event.target === orderModal) {
                closeOrderModal();
            }
            if (event.target === resultModal) {
                closeResultModal();
            }
        }
    </script>
</body>
</html>

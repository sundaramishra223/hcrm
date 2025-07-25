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
if (!in_array($user_role, ['admin', 'doctor', 'lab_technician', 'intern_lab', 'intern_doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_order':
                    if (in_array($user_role, ['admin', 'doctor', 'intern_doctor'])) {
                        $patient_id = $_POST['patient_id'];
                        $test_ids = $_POST['test_ids']; // Array of selected test IDs
                        $doctor_id = $_POST['doctor_id'] ?? null;
                        $notes = $_POST['notes'] ?? '';
                        $priority = $_POST['priority'] ?? 'normal';
                        
                        // Generate unique test order ID
                        $test_order_id = 'LT' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
                        
                        $total_amount = 0;
                        
                        // Calculate total amount
                        foreach ($test_ids as $test_id) {
                            $test = $db->query("SELECT price FROM laboratory WHERE id = ?", [$test_id])->fetch();
                            $total_amount += $test['price'];
                        }
                        
                        // Create lab test orders
                        foreach ($test_ids as $test_id) {
                            $db->query("
                                INSERT INTO lab_tests (test_id, patient_id, doctor_id, test_date, status, notes, priority, total_amount, created_by, created_at) 
                                VALUES (?, ?, ?, CURDATE(), 'pending', ?, ?, ?, ?, NOW())
                            ", [$test_order_id, $patient_id, $doctor_id, $notes, $priority, $total_amount, $_SESSION['user_id']]);
                        }
                        
                        $success_message = "Lab test order created successfully! Order ID: $test_order_id";
                    }
                    break;
                    
                case 'update_status':
                    if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])) {
                        $test_id = $_POST['test_id'];
                        $status = $_POST['status'];
                        $result_value = $_POST['result_value'] ?? null;
                        $result_notes = $_POST['result_notes'] ?? '';
                        
                        $db->query("
                            UPDATE lab_tests 
                            SET status = ?, updated_at = NOW() 
                            WHERE id = ?
                        ", [$status, $test_id]);
                        
                        // If completing the test, add result
                        if ($status === 'completed' && $result_value) {
                            // Check if result already exists
                            $existing = $db->query("SELECT id FROM laboratory_results WHERE test_id = ?", [$test_id])->fetch();
                            
                            if ($existing) {
                                $db->query("
                                    UPDATE laboratory_results 
                                    SET result_value = ?, notes = ?, test_date = CURDATE(), conducted_by = ?, updated_at = NOW()
                                    WHERE test_id = ?
                                ", [$result_value, $result_notes, $_SESSION['user_id'], $test_id]);
                            } else {
                                $db->query("
                                    INSERT INTO laboratory_results (test_id, patient_id, doctor_id, result_value, notes, test_date, status, conducted_by, created_at) 
                                    SELECT ?, patient_id, doctor_id, ?, ?, CURDATE(), 'completed', ?, NOW()
                                    FROM lab_tests WHERE id = ?
                                ", [$test_id, $result_value, $result_notes, $_SESSION['user_id'], $test_id]);
                            }
                        }
                        
                        $success_message = "Test status updated successfully!";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get laboratory tests
try {
    $lab_tests = $db->query("SELECT * FROM laboratory WHERE is_active = 1 ORDER BY test_category, test_name")->fetchAll();
} catch (Exception $e) {
    $lab_tests = [];
}

// Get patients for dropdown
try {
    $patients = $db->query("SELECT id, patient_id, first_name, last_name FROM patients WHERE is_active = 1 ORDER BY first_name, last_name")->fetchAll();
} catch (Exception $e) {
    $patients = [];
}

// Get doctors for dropdown
try {
    $doctors = $db->query("SELECT id, doctor_name FROM doctors WHERE is_active = 1 ORDER BY doctor_name")->fetchAll();
} catch (Exception $e) {
    $doctors = [];
}

// Get lab test orders based on role
try {
    if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])) {
        // Lab staff can see all orders
        $lab_orders = $db->query("
            SELECT lt.*, 
                   p.patient_id, p.first_name, p.last_name,
                   d.doctor_name,
                   l.test_name, l.test_category, l.normal_range, l.unit, l.price,
                   u.username as created_by_name
            FROM lab_tests lt 
            LEFT JOIN patients p ON lt.patient_id = p.id 
            LEFT JOIN doctors d ON lt.doctor_id = d.id 
            LEFT JOIN laboratory l ON lt.test_id = l.id
            LEFT JOIN users u ON lt.created_by = u.id
            ORDER BY lt.created_at DESC 
            LIMIT 20
        ")->fetchAll();
    } else {
        // Doctors can only see their orders
        $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'] ?? null;
        $lab_orders = $db->query("
            SELECT lt.*, 
                   p.patient_id, p.first_name, p.last_name,
                   d.doctor_name,
                   l.test_name, l.test_category, l.normal_range, l.unit, l.price,
                   u.username as created_by_name
            FROM lab_tests lt 
            LEFT JOIN patients p ON lt.patient_id = p.id 
            LEFT JOIN doctors d ON lt.doctor_id = d.id 
            LEFT JOIN laboratory l ON lt.test_id = l.id
            LEFT JOIN users u ON lt.created_by = u.id
            WHERE lt.doctor_id = ?
            ORDER BY lt.created_at DESC 
            LIMIT 20
        ", [$doctor_id])->fetchAll();
    }
} catch (Exception $e) {
    $lab_orders = [];
}

// Get statistics
try {
    $stats = [];
    
    if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])) {
        // Lab staff statistics
        $result = $db->query("SELECT COUNT(*) as count FROM laboratory WHERE is_active = 1")->fetch();
        $stats['total_tests'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE DATE(test_date) = CURDATE()")->fetch();
        $stats['tests_today'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'pending'")->fetch();
        $stats['pending_tests'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'in_progress'")->fetch();
        $stats['in_progress_tests'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE status = 'completed' AND MONTH(test_date) = MONTH(CURDATE())")->fetch();
        $stats['completed_month'] = $result['count'];
        
        if ($user_role === 'admin') {
            $result = $db->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM lab_tests WHERE status = 'completed' AND MONTH(test_date) = MONTH(CURDATE())")->fetch();
            $stats['revenue_month'] = $result['revenue'];
        }
    } else {
        // Doctor statistics
        $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'] ?? null;
        
        $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND DATE(test_date) = CURDATE()", [$doctor_id])->fetch();
        $stats['my_orders_today'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status = 'pending'", [$doctor_id])->fetch();
        $stats['my_pending_tests'] = $result['count'];
        
        $result = $db->query("SELECT COUNT(*) as count FROM lab_tests WHERE doctor_id = ? AND status = 'completed' AND MONTH(test_date) = MONTH(CURDATE())", [$doctor_id])->fetch();
        $stats['my_completed_month'] = $result['count'];
    }
    
} catch (Exception $e) {
    $stats = [];
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
                
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'intern_doctor'])): ?>
                    <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist', 'intern_doctor'])): ?>
                    <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'receptionist', 'pharmacy_staff', 'intern_pharmacy'])): ?>
                    <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <?php endif; ?>
                
                <li><a href="laboratory.php" class="active"><i class="fas fa-flask"></i> Laboratory</a></li>
                
                <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                    <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                    <li><a href="insurance.php"><i class="fas fa-shield-alt"></i> Insurance</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin'])): ?>
                    <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                    <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <?php endif; ?>
                
                <li><a href="blood-bank.php"><i class="fas fa-tint"></i> Blood Bank</a></li>
                <li><a href="organ-donation.php"><i class="fas fa-heart"></i> Organ Donation</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-flask"></i> Laboratory Management</h1>
                    <p>Manage laboratory tests and orders</p>
                </div>
                <div class="header-actions">
                    <?php if (in_array($user_role, ['admin', 'doctor', 'intern_doctor'])): ?>
                        <button class="btn btn-primary" onclick="showCreateOrderModal()">
                            <i class="fas fa-plus"></i> Create Lab Order
                        </button>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Laboratory Statistics -->
            <div class="stats-grid">
                <?php if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])): ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total_tests'] ?? 0); ?></h3>
                        <p><i class="fas fa-flask"></i> Available Tests</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['tests_today'] ?? 0); ?></h3>
                        <p><i class="fas fa-calendar-day"></i> Orders Today</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['pending_tests'] ?? 0); ?></h3>
                        <p><i class="fas fa-clock"></i> Pending Tests</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['in_progress_tests'] ?? 0); ?></h3>
                        <p><i class="fas fa-play-circle"></i> In Progress</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['completed_month'] ?? 0); ?></h3>
                        <p><i class="fas fa-check-circle"></i> Completed This Month</p>
                    </div>
                    <?php if ($user_role === 'admin'): ?>
                        <div class="stat-card">
                            <h3><?php echo formatCurrency($stats['revenue_month'] ?? 0); ?></h3>
                            <p><i class="fas fa-rupee-sign"></i> Monthly Revenue</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_orders_today'] ?? 0); ?></h3>
                        <p><i class="fas fa-calendar-day"></i> My Orders Today</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_pending_tests'] ?? 0); ?></h3>
                        <p><i class="fas fa-clock"></i> My Pending Tests</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['my_completed_month'] ?? 0); ?></h3>
                        <p><i class="fas fa-check-circle"></i> My Completed This Month</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-2">
                <!-- Lab Test Orders -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list-alt"></i> 
                            <?php echo in_array($user_role, ['admin', 'lab_technician', 'intern_lab']) ? 'Recent Lab Orders' : 'My Lab Orders'; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lab_orders)): ?>
                            <p class="text-muted text-center">No lab orders found.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Patient</th>
                                            <th>Test</th>
                                            <?php if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])): ?>
                                                <th>Doctor</th>
                                            <?php endif; ?>
                                            <th>Date</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                            <?php if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])): ?>
                                                <th>Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lab_orders as $order): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($order['test_id']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($order['patient_id'] . ' - ' . $order['first_name'] . ' ' . $order['last_name']); ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($order['test_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($order['test_category']); ?></small>
                                                </td>
                                                <?php if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])): ?>
                                                    <td><?php echo htmlspecialchars($order['doctor_name'] ?: 'N/A'); ?></td>
                                                <?php endif; ?>
                                                <td><?php echo date('d M Y', strtotime($order['test_date'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $order['priority'] === 'urgent' ? 'danger' : ($order['priority'] === 'high' ? 'warning' : 'info'); ?>">
                                                        <?php echo ucfirst($order['priority']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php 
                                                        echo $order['status'] == 'completed' ? 'success' : 
                                                            ($order['status'] == 'cancelled' ? 'danger' : 
                                                            ($order['status'] == 'in_progress' ? 'warning' : 'info')); 
                                                    ?>">
                                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatCurrency($order['price']); ?></td>
                                                <?php if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])): ?>
                                                    <td>
                                                        <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                                                            <button class="btn btn-sm btn-primary" onclick="showUpdateStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')">
                                                                <i class="fas fa-edit"></i> Update
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Available Laboratory Tests -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Available Laboratory Tests</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lab_tests)): ?>
                            <p class="text-muted text-center">No laboratory tests found.</p>
                        <?php else: ?>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Test Name</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Normal Range</th>
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
                                                    <td colspan="4"><strong><?php echo htmlspecialchars($current_category); ?></strong></td>
                                                </tr>
                                        <?php endif; ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                                                <td><?php echo htmlspecialchars($test['test_category']); ?></td>
                                                <td><?php echo formatCurrency($test['price']); ?></td>
                                                <td><?php echo htmlspecialchars($test['normal_range']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Lab Order Modal -->
    <?php if (in_array($user_role, ['admin', 'doctor', 'intern_doctor'])): ?>
    <div id="createOrderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Create Lab Order</h3>
                <span class="close" onclick="hideCreateOrderModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_order">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="patient_id">Select Patient:</label>
                        <select name="patient_id" id="patient_id" class="form-control" required>
                            <option value="">-- Select Patient --</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['patient_id'] . ' - ' . $patient['first_name'] . ' ' . $patient['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="doctor_id">Select Doctor:</label>
                        <select name="doctor_id" id="doctor_id" class="form-control" required>
                            <option value="">-- Select Doctor --</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    <?php echo htmlspecialchars($doctor['doctor_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Tests:</label>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                            <?php 
                            $current_category = '';
                            foreach ($lab_tests as $test): 
                                if ($current_category != $test['test_category']):
                                    $current_category = $test['test_category'];
                                    echo '<h5 style="color: #666; margin-top: 15px; margin-bottom: 5px;">' . htmlspecialchars($current_category) . '</h5>';
                                endif;
                            ?>
                                <div class="form-check">
                                    <input type="checkbox" name="test_ids[]" value="<?php echo $test['id']; ?>" 
                                           id="test_<?php echo $test['id']; ?>" class="form-check-input test-checkbox"
                                           data-price="<?php echo $test['price']; ?>">
                                    <label for="test_<?php echo $test['id']; ?>" class="form-check-label">
                                        <?php echo htmlspecialchars($test['test_name']); ?> - <?php echo formatCurrency($test['price']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priority:</label>
                        <select name="priority" id="priority" class="form-control">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Any special instructions..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <strong>Total Amount: <span id="totalAmount">₹0.00</span></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideCreateOrderModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Order</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Update Status Modal -->
    <?php if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])): ?>
    <div id="updateStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Update Test Status</h3>
                <span class="close" onclick="hideUpdateStatusModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="test_id" id="update_test_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="update_status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="resultFields" style="display: none;">
                        <label for="result_value">Result Value:</label>
                        <input type="text" name="result_value" id="result_value" class="form-control" placeholder="Enter test result">
                    </div>
                    
                    <div class="form-group" id="resultNotesField" style="display: none;">
                        <label for="result_notes">Result Notes:</label>
                        <textarea name="result_notes" id="result_notes" class="form-control" rows="3" placeholder="Any observations or notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideUpdateStatusModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Modal functions
        function showCreateOrderModal() {
            document.getElementById('createOrderModal').style.display = 'block';
        }

        function hideCreateOrderModal() {
            document.getElementById('createOrderModal').style.display = 'none';
        }

        function showUpdateStatusModal(testId, currentStatus) {
            document.getElementById('update_test_id').value = testId;
            document.getElementById('update_status').value = currentStatus;
            document.getElementById('updateStatusModal').style.display = 'block';
        }

        function hideUpdateStatusModal() {
            document.getElementById('updateStatusModal').style.display = 'none';
        }

        // Calculate total amount
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.test-checkbox');
            const totalAmountSpan = document.getElementById('totalAmount');
            
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    let total = 0;
                    checkboxes.forEach(cb => {
                        if (cb.checked) {
                            total += parseFloat(cb.dataset.price);
                        }
                    });
                    totalAmountSpan.textContent = '₹' + total.toFixed(2);
                });
            });

            // Show/hide result fields based on status
            document.getElementById('update_status').addEventListener('change', function() {
                const resultFields = document.getElementById('resultFields');
                const resultNotesField = document.getElementById('resultNotesField');
                
                if (this.value === 'completed') {
                    resultFields.style.display = 'block';
                    resultNotesField.style.display = 'block';
                } else {
                    resultFields.style.display = 'none';
                    resultNotesField.style.display = 'none';
                }
            });
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createOrderModal');
            const updateModal = document.getElementById('updateStatusModal');
            
            if (event.target === createModal) {
                hideCreateOrderModal();
            }
            if (event.target === updateModal) {
                hideUpdateStatusModal();
            }
        }
    </script>

    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: none;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #333;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
            text-align: right;
        }

        .form-check {
            margin-bottom: 8px;
        }

        .form-check-input {
            margin-right: 8px;
        }

        .form-check-label {
            cursor: pointer;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
    </style>
</body>
</html>
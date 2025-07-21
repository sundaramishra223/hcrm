<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is lab tech
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['lab_tech', 'intern_lab_tech'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$user_role = $_SESSION['role'];
$message = '';

// Handle test result upload
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'upload_result') {
    try {
        $test_order_id = $_POST['test_order_id'];
        $result_value = $_POST['result_value'];
        $result_unit = $_POST['result_unit'] ?? '';
        $reference_range = $_POST['reference_range'] ?? '';
        $is_abnormal = isset($_POST['is_abnormal']) ? 1 : 0;
        $notes = $_POST['notes'] ?? '';
        
        // Update test result
        $db->query(
            "UPDATE lab_order_tests SET 
                result_value = ?, result_unit = ?, reference_range = ?, 
                is_abnormal = ?, notes = ?, status = 'completed', 
                completed_at = NOW(), completed_by = ? 
             WHERE id = ?",
            [$result_value, $result_unit, $reference_range, $is_abnormal, $notes, $_SESSION['user_id'], $test_order_id]
        );
        
        // Log activity
        $db->query(
            "INSERT INTO audit_logs (user_id, action, table_name, record_id, notes) 
             VALUES (?, ?, 'lab_order_tests', ?, ?)",
            [$_SESSION['user_id'], 'test_result_uploaded', $test_order_id, "Test result uploaded: $result_value $result_unit"]
        );
        
        $message = "Test result uploaded successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle test status update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $test_order_id = $_POST['test_order_id'];
        $status = $_POST['status'];
        $notes = $_POST['notes'] ?? '';
        
        $db->query(
            "UPDATE lab_order_tests SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?",
            [$status, $notes, $test_order_id]
        );
        
        $message = "Test status updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get pending lab orders
$pending_orders = [];
try {
    $pending_orders = $db->query(
        "SELECT lot.*, lt.test_name, lt.test_code, lt.cost, lt.expected_duration,
                lo.order_number, lo.order_date, lo.priority,
                p.patient_id as patient_number, p.gender, p.date_of_birth,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name
         FROM lab_order_tests lot
         JOIN lab_orders lo ON lot.lab_order_id = lo.id
         JOIN lab_tests lt ON lot.lab_test_id = lt.id
         JOIN patients p ON lo.patient_id = p.id
         WHERE lot.status IN ('pending', 'in_progress')
         ORDER BY lo.priority DESC, lo.order_date ASC"
    )->fetchAll();
} catch (Exception $e) {
    $pending_orders = [];
}

// Get completed tests
$completed_tests = [];
try {
    $completed_tests = $db->query(
        "SELECT lot.*, lt.test_name, lt.test_code,
                lo.order_number, lo.order_date,
                p.patient_id as patient_number,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name
         FROM lab_order_tests lot
         JOIN lab_orders lo ON lot.lab_order_id = lo.id
         JOIN lab_tests lt ON lot.lab_test_id = lt.id
         JOIN patients p ON lo.patient_id = p.id
         WHERE lot.status = 'completed'
         ORDER BY lot.completed_at DESC
         LIMIT 50"
    )->fetchAll();
} catch (Exception $e) {
    $completed_tests = [];
}

// Get lab statistics
$lab_stats = [];
try {
    $lab_stats = $db->query(
        "SELECT 
            COUNT(*) as total_tests,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_tests,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tests,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tests,
            COUNT(CASE WHEN status = 'completed' AND DATE(completed_at) = CURDATE() THEN 1 END) as today_completed
         FROM lab_order_tests"
    )->fetch();
} catch (Exception $e) {
    $lab_stats = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Technician Dashboard - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-flask"></i> Lab Technician</h2>
                <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="lab-technician.php" class="active"><i class="fas fa-flask"></i> Lab Tests</a></li>
                <li><a href="lab-reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
                <li><a href="lab-equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="lab-inventory.php"><i class="fas fa-boxes"></i> Inventory</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Lab Technician Dashboard</h1>
                    <p>Manage laboratory tests and results</p>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                        <i class="fas fa-moon"></i>
                    </button>
                    <div class="user-info">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['role_display']); ?></span>
                    </div>
                    <a href="logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Lab Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($lab_stats['total_tests'] ?? 0); ?></h3>
                    <p>Total Tests</p>
                    <i class="fas fa-flask stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($lab_stats['pending_tests'] ?? 0); ?></h3>
                    <p>Pending Tests</p>
                    <i class="fas fa-clock stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($lab_stats['in_progress_tests'] ?? 0); ?></h3>
                    <p>In Progress</p>
                    <i class="fas fa-spinner stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($lab_stats['today_completed'] ?? 0); ?></h3>
                    <p>Completed Today</p>
                    <i class="fas fa-check-circle stat-icon"></i>
                </div>
            </div>

            <!-- Pending Lab Orders -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Pending Lab Tests</h3>
                </div>
                
                <?php if (!empty($pending_orders)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Test</th>
                                    <th>Patient ID</th>
                                    <th>Age/Gender</th>
                                    <th>Order Date</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['test_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($order['test_code']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['patient_number']); ?></td>
                                        <td>
                                            <?php 
                                            $age = $order['date_of_birth'] ? date_diff(date_create($order['date_of_birth']), date_create('today'))->y : 'N/A';
                                            echo $age . ' / ' . ucfirst($order['gender'] ?? 'N/A');
                                            ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['priority'] === 'high' ? 'danger' : ($order['priority'] === 'medium' ? 'warning' : 'info'); ?>">
                                                <?php echo ucfirst($order['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $order['status'] === 'pending' ? 'warning' : 'info'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="showResultModal(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-upload"></i> Upload Result
                                            </button>
                                            <button class="btn btn-sm btn-secondary" onclick="showStatusModal(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-edit"></i> Update Status
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">No pending lab tests.</p>
                <?php endif; ?>
            </div>

            <!-- Recently Completed Tests -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recently Completed Tests</h3>
                </div>
                
                <?php if (!empty($completed_tests)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Test</th>
                                    <th>Patient ID</th>
                                    <th>Result</th>
                                    <th>Status</th>
                                    <th>Completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completed_tests as $test): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($test['order_number']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($test['test_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($test['test_code']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($test['patient_number']); ?></td>
                                        <td>
                                            <?php if ($test['result_value']): ?>
                                                <span class="<?php echo $test['is_abnormal'] ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo htmlspecialchars($test['result_value'] . ' ' . $test['result_unit']); ?>
                                                </span>
                                                <?php if ($test['is_abnormal']): ?>
                                                    <br><small class="text-danger">Abnormal</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No result</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-success">Completed</span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($test['completed_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">No completed tests to display.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Upload Result Modal -->
    <div id="resultModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Upload Test Result</h3>
                <span class="close" onclick="closeModal('resultModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" class="result-form">
                    <input type="hidden" name="action" value="upload_result">
                    <input type="hidden" name="test_order_id" id="testOrderId">
                    
                    <div class="form-group">
                        <label class="form-label">Test Name</label>
                        <input type="text" id="testName" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Result Value</label>
                        <input type="text" name="result_value" class="form-control" required 
                               placeholder="Enter test result value">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <input type="text" name="result_unit" class="form-control" 
                               placeholder="e.g., mg/dL, mmol/L, etc.">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Reference Range</label>
                        <input type="text" name="reference_range" class="form-control" 
                               placeholder="e.g., 70-100 mg/dL">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" name="is_abnormal" value="1">
                            Mark as Abnormal
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes or observations..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('resultModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Result</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Test Status</h3>
                <span class="close" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" class="status-form">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="test_order_id" id="statusTestOrderId">
                    
                    <div class="form-group">
                        <label class="form-label">Test Name</label>
                        <input type="text" id="statusTestName" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Status update notes..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        const icon = themeToggle.querySelector('i');

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }

        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');

        function checkMobile() {
            if (window.innerWidth <= 768) {
                mobileMenuToggle.style.display = 'block';
                sidebar.classList.remove('open');
            } else {
                mobileMenuToggle.style.display = 'none';
                sidebar.classList.remove('open');
            }
        }

        mobileMenuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        window.addEventListener('resize', checkMobile);
        checkMobile();

        // Modal functions
        function showResultModal(testOrderId) {
            // Get test details via AJAX or use data attributes
            document.getElementById('testOrderId').value = testOrderId;
            document.getElementById('testName').value = 'Test ' + testOrderId; // Replace with actual test name
            document.getElementById('resultModal').style.display = 'block';
        }

        function showStatusModal(testOrderId) {
            document.getElementById('statusTestOrderId').value = testOrderId;
            document.getElementById('statusTestName').value = 'Test ' + testOrderId; // Replace with actual test name
            document.getElementById('statusModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>

    <style>
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: var(--bg-card);
            margin: 10% auto;
            padding: 0;
            border-radius: var(--border-radius-lg);
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
        }

        .close {
            color: var(--text-secondary);
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--text-primary);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .mobile-menu-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: none;
        }

        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2rem;
            opacity: 0.1;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
</body>
</html>
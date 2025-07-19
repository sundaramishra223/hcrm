<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
$allowed_roles = ['admin', 'doctor', 'lab_technician', 'receptionist', 'intern_doctor', 'intern_lab'];

if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle form submissions
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'add_test':
                $sql = "INSERT INTO lab_tests (test_name, test_code, category, normal_range, unit, price, description, preparation_instructions, sample_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $db->query($sql, [
                    $_POST['test_name'],
                    $_POST['test_code'],
                    $_POST['category'],
                    $_POST['normal_range'],
                    $_POST['unit'],
                    $_POST['price'],
                    $_POST['description'],
                    $_POST['preparation_instructions'],
                    $_POST['sample_type']
                ]);
                $message = "Lab test added successfully!";
                break;

            case 'create_order':
                // Create lab order
                $order_sql = "INSERT INTO lab_orders (patient_id, doctor_id, order_date, status, priority, notes, created_by) VALUES (?, ?, ?, 'pending', ?, ?, ?)";
                $order_id = $db->query($order_sql, [
                    $_POST['patient_id'],
                    $_POST['doctor_id'],
                    $_POST['order_date'],
                    $_POST['priority'],
                    $_POST['notes'],
                    $_SESSION['user_id']
                ]);
                $last_order_id = $db->lastInsertId();

                // Add test items to order
                if (isset($_POST['selected_tests']) && is_array($_POST['selected_tests'])) {
                    foreach ($_POST['selected_tests'] as $test_id) {
                        $test_sql = "INSERT INTO lab_order_tests (order_id, test_id, status, created_at) VALUES (?, ?, 'pending', NOW())";
                        $db->query($test_sql, [$last_order_id, $test_id]);
                    }
                }
                $message = "Lab order created successfully!";
                break;

            case 'update_result':
                $sql = "UPDATE lab_order_tests SET result_value = ?, reference_range = ?, status = 'completed', is_abnormal = ?, notes = ?, completed_at = NOW(), completed_by = ? WHERE id = ?";
                $is_abnormal = isset($_POST['is_abnormal']) ? 1 : 0;
                $db->query($sql, [
                    $_POST['result_value'],
                    $_POST['reference_range'],
                    $is_abnormal,
                    $_POST['notes'],
                    $_SESSION['user_id'],
                    $_POST['test_order_id']
                ]);
                $message = "Test result updated successfully!";
                break;

            case 'update_status':
                $sql = "UPDATE lab_orders SET status = ?, updated_at = NOW() WHERE id = ?";
                $db->query($sql, [$_POST['status'], $_POST['order_id']]);
                $message = "Order status updated successfully!";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$patient_filter = $_GET['patient'] ?? '';
$test_filter = $_GET['test'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query for lab orders
$sql = "SELECT lo.*, 
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.patient_id as patient_number,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        COUNT(lot.id) as total_tests,
        SUM(CASE WHEN lot.status = 'completed' THEN 1 ELSE 0 END) as completed_tests
        FROM lab_orders lo
        LEFT JOIN patients p ON lo.patient_id = p.id
        LEFT JOIN doctors d ON lo.doctor_id = d.id
        LEFT JOIN lab_order_tests lot ON lo.id = lot.lab_order_id
        WHERE 1=1";

$params = [];

if ($status_filter) {
    $sql .= " AND lo.status = ?";
    $params[] = $status_filter;
}

if ($patient_filter) {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ?)";
    $search_param = "%$patient_filter%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($date_filter) {
    $sql .= " AND DATE(lo.order_date) = ?";
    $params[] = $date_filter;
}

$sql .= " GROUP BY lo.id ORDER BY lo.created_at DESC";

$lab_orders = $db->query($sql, $params)->fetchAll();

// Get available tests
$available_tests = $db->query("SELECT * FROM lab_tests ORDER BY category, test_name")->fetchAll();

// Get patients
$patients = $db->query("SELECT id, patient_id, CONCAT(first_name, ' ', last_name) as full_name FROM patients ORDER BY first_name")->fetchAll();

// Get doctors
$doctors = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM doctors ORDER BY first_name")->fetchAll();

// Get statistics
$stats = [];
try {
    $stats['total_orders'] = $db->query("SELECT COUNT(*) as count FROM lab_orders")->fetch()['count'];
    $stats['pending_orders'] = $db->query("SELECT COUNT(*) as count FROM lab_orders WHERE status = 'pending'")->fetch()['count'];
    $stats['completed_orders'] = $db->query("SELECT COUNT(*) as count FROM lab_orders WHERE status = 'completed'")->fetch()['count'];
    $stats['today_orders'] = $db->query("SELECT COUNT(*) as count FROM lab_orders WHERE DATE(order_date) = CURDATE()")->fetch()['count'];
    $stats['abnormal_results'] = $db->query("SELECT COUNT(*) as count FROM lab_order_tests WHERE is_abnormal = 1 AND DATE(completed_at) = CURDATE()")->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_orders' => 0, 'pending_orders' => 0, 'completed_orders' => 0, 'today_orders' => 0, 'abnormal_results' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Test Management - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .test-category-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .category-blood { background: #fee2e2; color: #dc2626; }
        .category-urine { background: #fef3c7; color: #d97706; }
        .category-imaging { background: #dbeafe; color: #2563eb; }
        .category-microbiology { background: #dcfce7; color: #16a34a; }
        .category-biochemistry { background: #f3e8ff; color: #9333ea; }
        
        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .priority-urgent { background: #fee2e2; color: #dc2626; animation: pulse 2s infinite; }
        .priority-high { background: #fed7aa; color: #ea580c; }
        .priority-normal { background: #e0e7ff; color: #4338ca; }
        .priority-low { background: #f0f9ff; color: #0284c7; }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-in-progress { background: #dbeafe; color: #2563eb; }
        .status-completed { background: #dcfce7; color: #16a34a; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .test-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: var(--transition-all);
        }
        
        .test-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .test-progress {
            width: 100%;
            height: 8px;
            background: var(--bg-tertiary);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .test-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            transition: width 0.3s ease;
        }
        
        .filters-panel {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-hospital"></i> Hospital CRM</h2>
                <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="lab-test-management.php" class="active"><i class="fas fa-flask"></i> Lab Tests</a></li>
                <li><a href="laboratory.php"><i class="fas fa-microscope"></i> Laboratory</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="ambulance-management.php"><i class="fas fa-ambulance"></i> Ambulance</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1><i class="fas fa-flask"></i> Lab Test Management</h1>
                    <p>Manage laboratory tests, orders, and results</p>
                </div>
                <div class="header-right">
                    <div class="theme-controls">
                        <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                            <i class="fas fa-moon"></i>
                        </button>
                        <button class="color-toggle" id="colorToggle" title="Change Colors">
                            <i class="fas fa-palette"></i>
                        </button>
                    </div>
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
                <div class="alert alert-success animate-fade-in">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card animate-fade-in">
                    <h3><?php echo number_format($stats['total_orders']); ?></h3>
                    <p>Total Orders</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.1s;">
                    <h3><?php echo number_format($stats['pending_orders']); ?></h3>
                    <p>Pending Orders</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.2s;">
                    <h3><?php echo number_format($stats['completed_orders']); ?></h3>
                    <p>Completed Orders</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.3s;">
                    <h3><?php echo number_format($stats['today_orders']); ?></h3>
                    <p>Today's Orders</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.4s;">
                    <h3><?php echo number_format($stats['abnormal_results']); ?></h3>
                    <p>Abnormal Results</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-panel">
                <form method="GET" class="filter-grid">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="patient">Patient</label>
                        <input type="text" id="patient" name="patient" value="<?php echo htmlspecialchars($patient_filter); ?>" placeholder="Search patient..." class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="date">Order Date</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary w-full">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-4 mb-6">
                <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist', 'intern_doctor'])): ?>
                    <button class="btn btn-primary" onclick="showCreateOrderModal()">
                        <i class="fas fa-plus"></i> Create Lab Order
                    </button>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin'])): ?>
                    <button class="btn btn-secondary" onclick="showAddTestModal()">
                        <i class="fas fa-flask"></i> Add New Test
                    </button>
                <?php endif; ?>
            </div>

            <!-- Lab Orders -->
            <div class="test-grid">
                <?php if (empty($lab_orders)): ?>
                    <div class="card text-center" style="grid-column: 1 / -1;">
                        <div class="card-body">
                            <i class="fas fa-flask" style="font-size: 4rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                            <h3>No lab orders found</h3>
                            <p>Try adjusting your filters or create a new lab order</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($lab_orders as $order): ?>
                        <div class="test-card animate-fade-in">
                            <div class="d-flex justify-between items-start mb-4">
                                <div>
                                    <h4>Order #<?php echo htmlspecialchars($order['id']); ?></h4>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($order['patient_name']); ?></p>
                                    <p class="text-muted text-sm">ID: <?php echo htmlspecialchars($order['patient_number']); ?></p>
                                </div>
                                <span class="status-badge status-<?php echo str_replace(' ', '-', $order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-between text-sm mb-2">
                                    <span>Progress</span>
                                    <span><?php echo $order['completed_tests']; ?>/<?php echo $order['total_tests']; ?> tests</span>
                                </div>
                                <div class="test-progress">
                                    <div class="test-progress-bar" style="width: <?php echo $order['total_tests'] > 0 ? ($order['completed_tests'] / $order['total_tests']) * 100 : 0; ?>%"></div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <p class="text-sm text-muted mb-1">Doctor: <?php echo htmlspecialchars($order['doctor_name']); ?></p>
                                <p class="text-sm text-muted mb-1">Order Date: <?php echo date('M d, Y', strtotime($order['order_date'])); ?></p>
                                <?php if ($order['priority']): ?>
                                    <span class="priority-badge priority-<?php echo $order['priority']; ?>">
                                        <?php echo ucfirst($order['priority']); ?> Priority
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-outline btn-sm" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <?php if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])): ?>
                                    <button class="btn btn-primary btn-sm" onclick="updateResults(<?php echo $order['id']; ?>)">
                                        <i class="fas fa-edit"></i> Update Results
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Order Modal -->
    <div class="modal" id="createOrderModal">
        <div class="modal-dialog" style="max-width: 800px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Lab Order</h5>
                    <button type="button" class="btn-close" onclick="closeModal('createOrderModal')">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_order">
                        
                        <div class="form-group">
                            <label for="patient_id">Patient</label>
                            <select name="patient_id" id="patient_id" class="form-control" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="doctor_id">Doctor</label>
                            <select name="doctor_id" id="doctor_id" class="form-control" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        <?php echo htmlspecialchars($doctor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="order_date">Order Date</label>
                            <input type="date" name="order_date" id="order_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select name="priority" id="priority" class="form-control">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Select Tests</label>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem;">
                                <?php
                                $current_category = '';
                                foreach ($available_tests as $test):
                                    if ($test['category'] !== $current_category):
                                        if ($current_category !== '') echo '</div>';
                                        $current_category = $test['category'];
                                        echo '<h6 class="mt-3 mb-2">' . htmlspecialchars(ucfirst($current_category)) . '</h6><div>';
                                    endif;
                                ?>
                                    <div class="form-check mb-2">
                                        <input type="checkbox" name="selected_tests[]" value="<?php echo $test['id']; ?>" id="test_<?php echo $test['id']; ?>" class="form-check-input">
                                        <label for="test_<?php echo $test['id']; ?>" class="form-check-label">
                                            <?php echo htmlspecialchars($test['test_name']); ?>
                                            <small class="text-muted">($<?php echo number_format($test['price'], 2); ?>)</small>
                                        </label>
                                    </div>
                                <?php 
                                endforeach;
                                if ($current_category !== '') echo '</div>';
                                ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Additional notes or instructions..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('createOrderModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Test Modal -->
    <div class="modal" id="addTestModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Test</h5>
                    <button type="button" class="btn-close" onclick="closeModal('addTestModal')">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_test">
                        
                        <div class="form-group">
                            <label for="test_name">Test Name</label>
                            <input type="text" name="test_name" id="test_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="test_code">Test Code</label>
                            <input type="text" name="test_code" id="test_code" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select name="category" id="category" class="form-control" required>
                                <option value="blood">Blood Test</option>
                                <option value="urine">Urine Test</option>
                                <option value="imaging">Imaging</option>
                                <option value="microbiology">Microbiology</option>
                                <option value="biochemistry">Biochemistry</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="normal_range">Normal Range</label>
                            <input type="text" name="normal_range" id="normal_range" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="unit">Unit</label>
                            <input type="text" name="unit" id="unit" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price</label>
                            <input type="number" name="price" id="price" class="form-control" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="sample_type">Sample Type</label>
                            <input type="text" name="sample_type" id="sample_type" class="form-control" placeholder="e.g., Blood, Urine, Saliva">
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="preparation_instructions">Preparation Instructions</label>
                            <textarea name="preparation_instructions" id="preparation_instructions" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addTestModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Test</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showCreateOrderModal() {
            document.getElementById('createOrderModal').classList.add('show');
        }
        
        function showAddTestModal() {
            document.getElementById('addTestModal').classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function viewOrderDetails(orderId) {
            window.location.href = `laboratory.php?order_id=${orderId}`;
        }
        
        function updateResults(orderId) {
            window.location.href = `laboratory.php?order_id=${orderId}#results`;
        }
        
        // Theme controls
        const themeToggle = document.getElementById('themeToggle');
        const colorToggle = document.getElementById('colorToggle');
        const html = document.documentElement;
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            const icon = themeToggle.querySelector('i');
            icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });
        
        colorToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'medical' ? 'light' : 'medical';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            const icon = colorToggle.querySelector('i');
            icon.className = newTheme === 'medical' ? 'fas fa-check' : 'fas fa-palette';
        });
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        
        const themeIcon = themeToggle.querySelector('i');
        const colorIcon = colorToggle.querySelector('i');
        
        if (savedTheme === 'dark') {
            themeIcon.className = 'fas fa-sun';
        } else if (savedTheme === 'medical') {
            colorIcon.className = 'fas fa-check';
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
    </script>
</body>
</html>
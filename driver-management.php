<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle form submissions
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'add_driver':
                // Create user account first
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                $user_sql = "INSERT INTO users (username, email, password_hash, role_id, is_active) VALUES (?, ?, ?, 8, 1)";
                $db->query($user_sql, [
                    $_POST['username'],
                    $_POST['email'],
                    $password_hash
                ]);
                
                $user_id = $db->lastInsertId();
                
                // Create staff record
                $staff_sql = "INSERT INTO staff (user_id, employee_id, first_name, last_name, staff_type, phone, address, date_of_birth, gender, is_active) VALUES (?, ?, ?, ?, 'driver', ?, ?, ?, ?, 1)";
                $db->query($staff_sql, [
                    $user_id,
                    $_POST['employee_id'],
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['phone'],
                    $_POST['address'],
                    $_POST['date_of_birth'],
                    $_POST['gender']
                ]);
                
                $message = "Driver added successfully!";
                break;
                
            case 'update_driver':
                $driver_id = $_POST['driver_id'];
                
                // Update user info
                if (!empty($_POST['password'])) {
                    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $db->query("UPDATE users SET username = ?, email = ?, password_hash = ? WHERE id = (SELECT user_id FROM staff WHERE id = ?)", 
                               [$_POST['username'], $_POST['email'], $password_hash, $driver_id]);
                } else {
                    $db->query("UPDATE users SET username = ?, email = ? WHERE id = (SELECT user_id FROM staff WHERE id = ?)", 
                               [$_POST['username'], $_POST['email'], $driver_id]);
                }
                
                // Update staff info
                $db->query("UPDATE staff SET first_name = ?, last_name = ?, phone = ?, address = ?, date_of_birth = ?, gender = ? WHERE id = ?", [
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['phone'],
                    $_POST['address'],
                    $_POST['date_of_birth'],
                    $_POST['gender'],
                    $driver_id
                ]);
                
                $message = "Driver updated successfully!";
                break;
                
            case 'toggle_status':
                $driver_id = $_POST['driver_id'];
                $new_status = $_POST['new_status'];
                
                $db->query("UPDATE staff SET is_active = ? WHERE id = ?", [$new_status, $driver_id]);
                $db->query("UPDATE users SET is_active = ? WHERE id = (SELECT user_id FROM staff WHERE id = ?)", [$new_status, $driver_id]);
                
                $status_text = $new_status ? 'activated' : 'deactivated';
                $message = "Driver $status_text successfully!";
                break;
                
            case 'delete_driver':
                $driver_id = $_POST['driver_id'];
                
                // Get user_id before deleting staff record
                $user_id = $db->query("SELECT user_id FROM staff WHERE id = ?", [$driver_id])->fetch()['user_id'];
                
                // Delete staff record
                $db->query("DELETE FROM staff WHERE id = ?", [$driver_id]);
                
                // Delete user account
                $db->query("DELETE FROM users WHERE id = ?", [$user_id]);
                
                $message = "Driver deleted successfully!";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get all drivers
$drivers = $db->query("
    SELECT s.*, u.username, u.email, u.is_active as user_active
    FROM staff s
    JOIN users u ON s.user_id = u.id
    WHERE s.staff_type = 'driver'
    ORDER BY s.first_name, s.last_name
")->fetchAll();

// Get driver statistics
$stats = [
    'total_drivers' => count($drivers),
    'active_drivers' => count(array_filter($drivers, function($d) { return $d['is_active'] && $d['user_active']; })),
    'inactive_drivers' => count(array_filter($drivers, function($d) { return !$d['is_active'] || !$d['user_active']; })),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    include_once 'includes/site-config.php';
    renderSiteHead('Driver Management');
    ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
    <style>
        .driver-management {
            padding: 20px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }
        
        .page-header {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            text-align: center;
        }
        
        .stat-card .icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--text-primary);
        }
        
        .stat-card .label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .drivers-table {
            background: var(--bg-card);
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .table tr:hover {
            background: var(--bg-secondary);
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .btn-primary { background: var(--primary-color); color: white; }
        .btn-success { background: var(--secondary-color); color: white; }
        .btn-warning { background: var(--accent-color); color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover { transform: translateY(-1px); opacity: 0.9; }
        
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
            background: var(--bg-card);
            margin: 20px auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            font-size: 14px;
            background: var(--bg-card);
            color: var(--text-primary);
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
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="driver-management">
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-users-cog"></i> Driver Management</h1>
                        <p>Manage ambulance drivers and their accounts</p>
                    </div>
                    <button onclick="openAddModal()" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Driver
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="number"><?php echo $stats['total_drivers']; ?></div>
                        <div class="label">Total Drivers</div>
                    </div>
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-user-check"></i></div>
                        <div class="number"><?php echo $stats['active_drivers']; ?></div>
                        <div class="label">Active Drivers</div>
                    </div>
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-user-times"></i></div>
                        <div class="number"><?php echo $stats['inactive_drivers']; ?></div>
                        <div class="label">Inactive Drivers</div>
                    </div>
                </div>
                
                <!-- Drivers Table -->
                <div class="drivers-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Login Details</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($drivers)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                                        No drivers found. <button onclick="openAddModal()" class="btn btn-primary">Add your first driver</button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($drivers as $driver): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($driver['employee_id']); ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($driver['first_name'] . ' ' . $driver['last_name']); ?></strong>
                                            <br><small><?php echo ucfirst($driver['gender']); ?> • <?php echo date('M j, Y', strtotime($driver['date_of_birth'])); ?></small>
                                        </td>
                                        <td>
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($driver['phone']); ?>
                                            <br><small><?php echo htmlspecialchars($driver['address']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($driver['username']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($driver['email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo ($driver['is_active'] && $driver['user_active']) ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo ($driver['is_active'] && $driver['user_active']) ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button onclick="editDriver(<?php echo htmlspecialchars(json_encode($driver)); ?>)" class="btn btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo ($driver['is_active'] && $driver['user_active']) ? 0 : 1; ?>">
                                                    <button type="submit" class="btn <?php echo ($driver['is_active'] && $driver['user_active']) ? 'btn-secondary' : 'btn-success'; ?>">
                                                        <i class="fas fa-<?php echo ($driver['is_active'] && $driver['user_active']) ? 'ban' : 'check'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this driver?')">
                                                    <input type="hidden" name="action" value="delete_driver">
                                                    <input type="hidden" name="driver_id" value="<?php echo $driver['id']; ?>">
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit Driver Modal -->
    <div id="driverModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Driver</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" id="driverForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add_driver">
                    <input type="hidden" name="driver_id" id="driverId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="employee_id">Employee ID *</label>
                            <input type="text" id="employee_id" name="employee_id" required>
                        </div>
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone *</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth *</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" required>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <select id="gender" name="gender" required>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password <span id="passwordNote">(leave blank to keep current)</span></label>
                        <input type="password" id="password" name="password">
                    </div>
                </div>
                
                <div style="padding: 20px; border-top: 1px solid var(--border-color); text-align: right;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary" style="margin-right: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Driver</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
    
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Driver';
            document.getElementById('formAction').value = 'add_driver';
            document.getElementById('submitBtn').textContent = 'Add Driver';
            document.getElementById('passwordNote').style.display = 'none';
            document.getElementById('password').required = true;
            document.getElementById('driverForm').reset();
            document.getElementById('driverModal').style.display = 'block';
        }
        
        function editDriver(driver) {
            document.getElementById('modalTitle').textContent = 'Edit Driver';
            document.getElementById('formAction').value = 'update_driver';
            document.getElementById('submitBtn').textContent = 'Update Driver';
            document.getElementById('passwordNote').style.display = 'inline';
            document.getElementById('password').required = false;
            document.getElementById('driverId').value = driver.id;
            
            // Fill form
            document.getElementById('employee_id').value = driver.employee_id;
            document.getElementById('username').value = driver.username;
            document.getElementById('first_name').value = driver.first_name;
            document.getElementById('last_name').value = driver.last_name;
            document.getElementById('email').value = driver.email;
            document.getElementById('phone').value = driver.phone;
            document.getElementById('date_of_birth').value = driver.date_of_birth;
            document.getElementById('gender').value = driver.gender;
            document.getElementById('address').value = driver.address || '';
            
            document.getElementById('driverModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('driverModal').style.display = 'none';
        }
    </script>
</body>
</html>
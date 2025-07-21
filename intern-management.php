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

// Handle intern assignment
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'assign_intern') {
    try {
        $intern_id = $_POST['intern_id'];
        $senior_id = $_POST['senior_id'];
        $intern_type = $_POST['intern_type'];
        
        if ($intern_type === 'doctor') {
            $db->query("UPDATE doctors SET senior_doctor_id = ? WHERE id = ?", [$senior_id, $intern_id]);
        } elseif ($intern_type === 'nurse') {
            $db->query("UPDATE staff SET senior_staff_id = ? WHERE id = ?", [$senior_id, $intern_id]);
        } elseif ($intern_type === 'lab') {
            $db->query("UPDATE staff SET senior_staff_id = ? WHERE id = ?", [$senior_id, $intern_id]);
        } elseif ($intern_type === 'pharmacy') {
            $db->query("UPDATE staff SET senior_staff_id = ? WHERE id = ?", [$senior_id, $intern_id]);
        }
        
        $message = "Intern assigned to senior successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle intern status update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_intern_status') {
    try {
        $intern_id = $_POST['intern_id'];
        $intern_type = $_POST['intern_type'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($intern_type === 'doctor') {
            $db->query("UPDATE doctors SET is_available = ? WHERE id = ?", [$is_active, $intern_id]);
            $db->query("UPDATE users SET is_active = ? WHERE id = (SELECT user_id FROM doctors WHERE id = ?)", [$is_active, $intern_id]);
        } else {
            $db->query("UPDATE staff SET is_active = ? WHERE id = ?", [$is_active, $intern_id]);
            $db->query("UPDATE users SET is_active = ? WHERE id = (SELECT user_id FROM staff WHERE id = ?)", [$is_active, $intern_id]);
        }
        
        $message = "Intern status updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get all interns
$intern_doctors = $db->query("
    SELECT d.*, u.email, u.is_active,
           CONCAT(d.first_name, ' ', d.last_name) as full_name,
           CONCAT(sd.first_name, ' ', sd.last_name) as senior_name,
           dept.name as department_name
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN doctors sd ON d.senior_doctor_id = sd.id
    LEFT JOIN departments dept ON d.department_id = dept.id
    WHERE d.is_intern = 1
    ORDER BY d.first_name, d.last_name
")->fetchAll();

$intern_nurses = $db->query("
    SELECT s.*, u.email, u.is_active,
           CONCAT(s.first_name, ' ', s.last_name) as full_name,
           CONCAT(ss.first_name, ' ', ss.last_name) as senior_name,
           dept.name as department_name
    FROM staff s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN staff ss ON s.senior_staff_id = ss.id
    LEFT JOIN departments dept ON s.department_id = dept.id
    WHERE s.staff_type = 'intern_nurse'
    ORDER BY s.first_name, s.last_name
")->fetchAll();

$intern_lab = $db->query("
    SELECT s.*, u.email, u.is_active,
           CONCAT(s.first_name, ' ', s.last_name) as full_name,
           CONCAT(ss.first_name, ' ', ss.last_name) as senior_name,
           dept.name as department_name
    FROM staff s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN staff ss ON s.senior_staff_id = ss.id
    LEFT JOIN departments dept ON s.department_id = dept.id
    WHERE s.staff_type = 'intern_lab'
    ORDER BY s.first_name, s.last_name
")->fetchAll();

$intern_pharmacy = $db->query("
    SELECT s.*, u.email, u.is_active,
           CONCAT(s.first_name, ' ', s.last_name) as full_name,
           CONCAT(ss.first_name, ' ', ss.last_name) as senior_name,
           dept.name as department_name
    FROM staff s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN staff ss ON s.senior_staff_id = ss.id
    LEFT JOIN departments dept ON s.department_id = dept.id
    WHERE s.staff_type = 'intern_pharmacy'
    ORDER BY s.first_name, s.last_name
")->fetchAll();

// Get senior staff for assignment
$senior_doctors = $db->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as full_name, specialization
    FROM doctors 
    WHERE is_intern = 0 AND is_available = 1
    ORDER BY first_name, last_name
")->fetchAll();

$senior_staff = $db->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as full_name, staff_type
    FROM staff 
    WHERE is_intern = 0 AND is_active = 1
    ORDER BY first_name, last_name
")->fetchAll();

// Get intern statistics
$intern_stats = [
    'total_interns' => count($intern_doctors) + count($intern_nurses) + count($intern_lab) + count($intern_pharmacy),
    'intern_doctors' => count($intern_doctors),
    'intern_nurses' => count($intern_nurses),
    'intern_lab' => count($intern_lab),
    'intern_pharmacy' => count($intern_pharmacy),
    'active_interns' => 0,
    'assigned_interns' => 0
];

foreach ($intern_doctors as $intern) {
    if ($intern['is_active']) $intern_stats['active_interns']++;
    if ($intern['senior_doctor_id']) $intern_stats['assigned_interns']++;
}
foreach ($intern_nurses as $intern) {
    if ($intern['is_active']) $intern_stats['active_interns']++;
    if ($intern['senior_staff_id']) $intern_stats['assigned_interns']++;
}
foreach ($intern_lab as $intern) {
    if ($intern['is_active']) $intern_stats['active_interns']++;
    if ($intern['senior_staff_id']) $intern_stats['assigned_interns']++;
}
foreach ($intern_pharmacy as $intern) {
    if ($intern['is_active']) $intern_stats['active_interns']++;
    if ($intern['senior_staff_id']) $intern_stats['assigned_interns']++;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intern Management - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="billing.php"><i class="fas fa-money-bill-wave"></i> Billing</a></li>
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="staff.php"><i class="fas fa-user-nurse"></i> Staff</a></li>
                <li><a href="intern-management.php" class="active"><i class="fas fa-graduation-cap"></i> Intern Management</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Intern Management System</h1>
                    <p>Manage and supervise intern staff across all departments</p>
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

            <!-- Intern Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($intern_stats['total_interns']); ?></h3>
                    <p>Total Interns</p>
                    <i class="fas fa-graduation-cap stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($intern_stats['active_interns']); ?></h3>
                    <p>Active Interns</p>
                    <i class="fas fa-user-check stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($intern_stats['assigned_interns']); ?></h3>
                    <p>Assigned to Senior</p>
                    <i class="fas fa-user-tie stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($intern_stats['intern_doctors']); ?></h3>
                    <p>Intern Doctors</p>
                    <i class="fas fa-user-md stat-icon"></i>
                </div>
            </div>

            <!-- Intern Categories -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Intern Doctors</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($intern_doctors)): ?>
                        <p class="text-muted">No intern doctors found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Employee ID</th>
                                        <th>Department</th>
                                        <th>Senior Doctor</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($intern_doctors as $intern): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($intern['full_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($intern['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($intern['employee_id']); ?></td>
                                            <td><?php echo htmlspecialchars($intern['department_name'] ?? 'Not Assigned'); ?></td>
                                            <td>
                                                <?php if ($intern['senior_name']): ?>
                                                    <span class="badge badge-success"><?php echo htmlspecialchars($intern['senior_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($intern['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="assignIntern(<?php echo $intern['id']; ?>, 'doctor')">
                                                    <i class="fas fa-user-plus"></i> Assign
                                                </button>
                                                <button class="btn btn-sm btn-secondary" onclick="toggleStatus(<?php echo $intern['id']; ?>, 'doctor', <?php echo $intern['is_active'] ? 'false' : 'true'; ?>)">
                                                    <i class="fas fa-toggle-on"></i> Toggle
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Intern Nurses</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($intern_nurses)): ?>
                        <p class="text-muted">No intern nurses found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Employee ID</th>
                                        <th>Department</th>
                                        <th>Senior Nurse</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($intern_nurses as $intern): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($intern['full_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($intern['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($intern['employee_id']); ?></td>
                                            <td><?php echo htmlspecialchars($intern['department_name'] ?? 'Not Assigned'); ?></td>
                                            <td>
                                                <?php if ($intern['senior_name']): ?>
                                                    <span class="badge badge-success"><?php echo htmlspecialchars($intern['senior_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($intern['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="assignIntern(<?php echo $intern['id']; ?>, 'nurse')">
                                                    <i class="fas fa-user-plus"></i> Assign
                                                </button>
                                                <button class="btn btn-sm btn-secondary" onclick="toggleStatus(<?php echo $intern['id']; ?>, 'nurse', <?php echo $intern['is_active'] ? 'false' : 'true'; ?>)">
                                                    <i class="fas fa-toggle-on"></i> Toggle
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Intern Lab Technicians</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($intern_lab)): ?>
                        <p class="text-muted">No intern lab technicians found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Employee ID</th>
                                        <th>Department</th>
                                        <th>Senior Lab Tech</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($intern_lab as $intern): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($intern['full_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($intern['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($intern['employee_id']); ?></td>
                                            <td><?php echo htmlspecialchars($intern['department_name'] ?? 'Not Assigned'); ?></td>
                                            <td>
                                                <?php if ($intern['senior_name']): ?>
                                                    <span class="badge badge-success"><?php echo htmlspecialchars($intern['senior_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($intern['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="assignIntern(<?php echo $intern['id']; ?>, 'lab')">
                                                    <i class="fas fa-user-plus"></i> Assign
                                                </button>
                                                <button class="btn btn-sm btn-secondary" onclick="toggleStatus(<?php echo $intern['id']; ?>, 'lab', <?php echo $intern['is_active'] ? 'false' : 'true'; ?>)">
                                                    <i class="fas fa-toggle-on"></i> Toggle
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Intern Pharmacy Staff</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($intern_pharmacy)): ?>
                        <p class="text-muted">No intern pharmacy staff found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Employee ID</th>
                                        <th>Department</th>
                                        <th>Senior Pharmacy</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($intern_pharmacy as $intern): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($intern['full_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($intern['email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($intern['employee_id']); ?></td>
                                            <td><?php echo htmlspecialchars($intern['department_name'] ?? 'Not Assigned'); ?></td>
                                            <td>
                                                <?php if ($intern['senior_name']): ?>
                                                    <span class="badge badge-success"><?php echo htmlspecialchars($intern['senior_name']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($intern['is_active']): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="assignIntern(<?php echo $intern['id']; ?>, 'pharmacy')">
                                                    <i class="fas fa-user-plus"></i> Assign
                                                </button>
                                                <button class="btn btn-sm btn-secondary" onclick="toggleStatus(<?php echo $intern['id']; ?>, 'pharmacy', <?php echo $intern['is_active'] ? 'false' : 'true'; ?>)">
                                                    <i class="fas fa-toggle-on"></i> Toggle
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Assignment Modal -->
    <div class="modal" id="assignmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Intern to Senior</h3>
                <span class="close">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="assign_intern">
                <input type="hidden" name="intern_id" id="intern_id">
                <input type="hidden" name="intern_type" id="intern_type">
                
                <div class="form-group">
                    <label>Select Senior Staff:</label>
                    <select name="senior_id" id="senior_select" required>
                        <option value="">Choose senior staff...</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Assign</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function assignIntern(internId, internType) {
            document.getElementById('intern_id').value = internId;
            document.getElementById('intern_type').value = internType;
            
            const seniorSelect = document.getElementById('senior_select');
            seniorSelect.innerHTML = '<option value="">Choose senior staff...</option>';
            
            <?php if (!empty($senior_doctors)): ?>
                if (internType === 'doctor') {
                    <?php foreach ($senior_doctors as $senior): ?>
                        seniorSelect.innerHTML += '<option value="<?php echo $senior['id']; ?>"><?php echo htmlspecialchars($senior['full_name']); ?> (<?php echo htmlspecialchars($senior['specialization']); ?>)</option>';
                    <?php endforeach; ?>
                }
            <?php endif; ?>
            
            <?php if (!empty($senior_staff)): ?>
                if (internType !== 'doctor') {
                    <?php foreach ($senior_staff as $senior): ?>
                        if (internType === 'nurse' && '<?php echo $senior['staff_type']; ?>' === 'nurse') {
                            seniorSelect.innerHTML += '<option value="<?php echo $senior['id']; ?>"><?php echo htmlspecialchars($senior['full_name']); ?> (Senior Nurse)</option>';
                        } else if (internType === 'lab' && '<?php echo $senior['staff_type']; ?>' === 'lab_technician') {
                            seniorSelect.innerHTML += '<option value="<?php echo $senior['id']; ?>"><?php echo htmlspecialchars($senior['full_name']); ?> (Senior Lab Tech)</option>';
                        } else if (internType === 'pharmacy' && '<?php echo $senior['staff_type']; ?>' === 'pharmacy_staff') {
                            seniorSelect.innerHTML += '<option value="<?php echo $senior['id']; ?>"><?php echo htmlspecialchars($senior['full_name']); ?> (Senior Pharmacy)</option>';
                        }
                    <?php endforeach; ?>
                }
            <?php endif; ?>
            
            document.getElementById('assignmentModal').style.display = 'block';
        }
        
        function toggleStatus(internId, internType, isActive) {
            if (confirm('Are you sure you want to ' + (isActive ? 'activate' : 'deactivate') + ' this intern?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_intern_status">
                    <input type="hidden" name="intern_id" value="${internId}">
                    <input type="hidden" name="intern_type" value="${internType}">
                    <input type="hidden" name="is_active" value="${isActive ? '1' : '0'}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function closeModal() {
            document.getElementById('assignmentModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('assignmentModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Close modal when clicking X
        document.querySelector('.close').onclick = function() {
            closeModal();
        }
    </script>
</body>
</html>
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
$allowed_roles = ['admin', 'doctor', 'nurse', 'receptionist', 'intern_doctor', 'intern_nurse'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle status updates
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $patient_id = $_POST['patient_id'];
        $new_status = $_POST['new_status'];
        $notes = $_POST['notes'];
        
        $db->query("UPDATE patients SET status = ?, last_status_update = NOW() WHERE id = ?", [$new_status, $patient_id]);
        
        // Log status change
        $log_sql = "INSERT INTO patient_status_logs (patient_id, status, notes, updated_by, updated_at) VALUES (?, ?, ?, ?, NOW())";
        $db->query($log_sql, [$patient_id, $new_status, $notes, $_SESSION['user_id']]);
        
        $message = "Patient status updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT p.*, 
        CONCAT(p.first_name, ' ', p.last_name) as full_name,
        ba.id as bed_assignment_id,
        ba.assigned_date,
        ba.expected_discharge,
        b.bed_number,
        b.ward_number,
        r.name as room_name,
        d.name as department_name,
        CONCAT(do.first_name, ' ', do.last_name) as doctor_name,
        CONCAT(s.first_name, ' ', s.last_name) as nurse_name,
        (SELECT status FROM patient_status_logs WHERE patient_id = p.id ORDER BY updated_at DESC LIMIT 1) as current_status
        FROM patients p
        LEFT JOIN bed_assignments ba ON p.id = ba.patient_id AND ba.status = 'active'
        LEFT JOIN beds b ON ba.bed_id = b.id
        LEFT JOIN rooms r ON b.room_id = r.id
        LEFT JOIN departments d ON r.department_id = d.id
        LEFT JOIN doctors do ON p.assigned_doctor_id = do.id
        LEFT JOIN staff s ON ba.assigned_nurse_id = s.id
        WHERE 1=1";

$params = [];

if ($status_filter) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($department_filter) {
    $sql .= " AND d.id = ?";
    $params[] = $department_filter;
}

if ($search) {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$sql .= " ORDER BY p.status DESC, p.first_name, p.last_name";

$patients = $db->query($sql, $params)->fetchAll();

// Get departments for filter
$departments = $db->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

// Get statistics
$stats = [];
try {
    $stats['total_patients'] = $db->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'];
    $stats['in_patients'] = $db->query("SELECT COUNT(*) as count FROM patients WHERE status = 'inpatient'")->fetch()['count'];
    $stats['out_patients'] = $db->query("SELECT COUNT(*) as count FROM patients WHERE status = 'outpatient'")->fetch()['count'];
    $stats['discharged'] = $db->query("SELECT COUNT(*) as count FROM patients WHERE status = 'discharged'")->fetch()['count'];
    $stats['admitted_today'] = $db->query("SELECT COUNT(*) as count FROM bed_assignments WHERE DATE(assigned_date) = CURDATE()")->fetch()['count'];
    $stats['discharged_today'] = $db->query("SELECT COUNT(*) as count FROM bed_assignments WHERE DATE(discharge_date) = CURDATE() AND status = 'discharged'")->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_patients' => 0, 'in_patients' => 0, 'out_patients' => 0, 'discharged' => 0, 'admitted_today' => 0, 'discharged_today' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Monitoring - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-inpatient { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-outpatient { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-discharged { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-emergency { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; animation: pulse 2s infinite; }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .patient-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary-color);
            transition: all var(--transition);
        }
        
        .patient-card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }
        
        .patient-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .patient-info {
            flex: 1;
        }
        
        .patient-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: var(--shadow);
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .filters {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        .btn-filter {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all var(--transition);
        }
        
        .btn-filter:hover {
            background: var(--secondary-color);
        }
        
        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
                <li><a href="patient-monitoring.php" class="active"><i class="fas fa-user-injured"></i> Patient Monitoring</a></li>
                <li><a href="beds.php"><i class="fas fa-bed"></i> Bed Management</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="ambulance-management.php"><i class="fas fa-ambulance"></i> Ambulance</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1><i class="fas fa-user-injured"></i> Patient Monitoring</h1>
                    <p>Track patient status and hospital occupancy</p>
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
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($stats['total_patients']); ?></h3>
                    <p>Total Patients</p>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                    <h3><?php echo number_format($stats['in_patients']); ?></h3>
                    <p>In Patients</p>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                    <h3><?php echo number_format($stats['out_patients']); ?></h3>
                    <p>Out Patients</p>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
                    <h3><?php echo number_format($stats['discharged']); ?></h3>
                    <p>Discharged</p>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);">
                    <h3><?php echo number_format($stats['admitted_today']); ?></h3>
                    <p>Admitted Today</p>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                    <h3><?php echo number_format($stats['discharged_today']); ?></h3>
                    <p>Discharged Today</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" class="filter-row">
                    <div class="filter-group">
                        <label for="search">Search Patients</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, ID, or Phone">
                    </div>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Status</option>
                            <option value="inpatient" <?php echo $status_filter === 'inpatient' ? 'selected' : ''; ?>>In Patient</option>
                            <option value="outpatient" <?php echo $status_filter === 'outpatient' ? 'selected' : ''; ?>>Out Patient</option>
                            <option value="discharged" <?php echo $status_filter === 'discharged' ? 'selected' : ''; ?>>Discharged</option>
                            <option value="emergency" <?php echo $status_filter === 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="department">Department</label>
                        <select id="department" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Patients List -->
            <div class="patients-list">
                <?php if (empty($patients)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-injured"></i>
                        <h3>No patients found</h3>
                        <p>Try adjusting your filters or search criteria</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($patients as $patient): ?>
                        <div class="patient-card">
                            <div class="patient-header">
                                <div class="patient-info">
                                    <h3><?php echo htmlspecialchars($patient['full_name']); ?></h3>
                                    <p><strong>ID:</strong> <?php echo htmlspecialchars($patient['patient_id']); ?></p>
                                </div>
                                <div class="patient-actions">
                                    <span class="status-badge status-<?php echo $patient['status'] ?? 'outpatient'; ?>">
                                        <?php echo ucfirst($patient['status'] ?? 'outpatient'); ?>
                                    </span>
                                    <button class="btn btn-primary btn-sm" onclick="updateStatus(<?php echo $patient['id']; ?>, '<?php echo $patient['status'] ?? 'outpatient'; ?>')">
                                        <i class="fas fa-edit"></i> Update Status
                                    </button>
                                </div>
                            </div>
                            
                            <div class="patient-details">
                                <div class="detail-item">
                                    <span class="detail-label">Phone</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($patient['phone']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Age</span>
                                    <span class="detail-value">
                                        <?php 
                                        if ($patient['date_of_birth']) {
                                            $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y;
                                            echo $age . ' years';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Gender</span>
                                    <span class="detail-value"><?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Blood Group</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($patient['blood_group'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Assigned Doctor</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($patient['doctor_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Department</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($patient['department_name'] ?? 'N/A'); ?></span>
                                </div>
                                <?php if ($patient['bed_number']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Bed</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($patient['bed_number']); ?> (Ward <?php echo htmlspecialchars($patient['ward_number']); ?>)</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Admitted</span>
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($patient['assigned_date'])); ?></span>
                                    </div>
                                    <?php if ($patient['expected_discharge']): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Expected Discharge</span>
                                            <span class="detail-value"><?php echo date('M d, Y', strtotime($patient['expected_discharge'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Patient Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="patient_id" id="patientId">
                        
                        <div class="form-group">
                            <label for="newStatus">New Status</label>
                            <select name="new_status" id="newStatus" class="form-control" required>
                                <option value="inpatient">In Patient</option>
                                <option value="outpatient">Out Patient</option>
                                <option value="discharged">Discharged</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Add any notes about the status change..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            const icon = themeToggle.querySelector('i');
            icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        const icon = themeToggle.querySelector('i');
        icon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        
        // Status update modal
        function updateStatus(patientId, currentStatus) {
            document.getElementById('patientId').value = patientId;
            document.getElementById('newStatus').value = currentStatus;
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }
    </script>
</body>
</html>
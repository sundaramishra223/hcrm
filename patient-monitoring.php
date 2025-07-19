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

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$department_filter = $_GET['department'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query based on filters
$sql = "SELECT p.*, 
        CONCAT(p.first_name, ' ', p.last_name) as full_name,
        TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        d.specialization,
        ba.id as bed_assignment_id,
        ba.assigned_date,
        ba.notes as bed_notes,
        b.bed_number,
        b.bed_type,
        COALESCE(b.daily_rate, 0) as daily_rate,
        COALESCE(b.room_number, 'General Ward') as room_name,
        COALESCE(dept.name, 'General') as department_name,
        (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND appointment_date >= CURDATE()) as upcoming_appointments,
        (SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id AND status = 'active') as active_prescriptions,
        (SELECT COUNT(*) FROM lab_orders WHERE patient_id = p.id AND status IN ('pending', 'in_progress')) as pending_lab_tests,
        (SELECT vital_signs FROM patient_vitals WHERE patient_id = p.id ORDER BY recorded_at DESC LIMIT 1) as latest_vitals,
        (SELECT recorded_at FROM patient_vitals WHERE patient_id = p.id ORDER BY recorded_at DESC LIMIT 1) as last_vitals_time
        FROM patients p
        LEFT JOIN doctors d ON p.assigned_doctor_id = d.id
        LEFT JOIN bed_assignments ba ON p.id = ba.patient_id AND ba.status = 'active'
        LEFT JOIN beds b ON ba.bed_id = b.id
        LEFT JOIN departments dept ON b.department_id = dept.id
        WHERE 1=1";

$params = [];

// Apply filters
if ($type_filter !== 'all') {
    $sql .= " AND p.patient_type = ?";
    $params[] = $type_filter;
}

if ($department_filter !== 'all') {
    $sql .= " AND dept.id = ?";
    $params[] = $department_filter;
}

if ($search) {
    $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ? OR p.phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

// Status-based filtering
switch ($status_filter) {
    case 'critical':
        $sql .= " AND p.patient_type = 'inpatient' AND ba.priority = 'high'";
        break;
    case 'stable':
        $sql .= " AND p.patient_type = 'inpatient' AND (ba.priority IS NULL OR ba.priority != 'high')";
        break;
    case 'discharged_today':
        $sql .= " AND p.id IN (SELECT patient_id FROM bed_assignments WHERE status = 'discharged' AND DATE(discharge_date) = CURDATE())";
        break;
    case 'admitted_today':
        $sql .= " AND ba.status = 'active' AND DATE(ba.assigned_date) = CURDATE()";
        break;
}

$sql .= " ORDER BY 
    CASE WHEN p.patient_type = 'inpatient' THEN 0 ELSE 1 END,
    CASE WHEN ba.priority = 'high' THEN 0 WHEN ba.priority = 'medium' THEN 1 ELSE 2 END,
    p.first_name, p.last_name";

$patients = $db->query($sql, $params)->fetchAll();

// Get statistics
$stats = [];
try {
    $stats['total_patients'] = $db->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'];
    $stats['inpatients'] = $db->query("SELECT COUNT(*) as count FROM patients p JOIN bed_assignments ba ON p.id = ba.patient_id WHERE ba.status = 'active'")->fetch()['count'];
    $stats['outpatients'] = $stats['total_patients'] - $stats['inpatients'];
    $stats['critical_patients'] = $db->query("SELECT COUNT(*) as count FROM patients p JOIN bed_assignments ba ON p.id = ba.patient_id WHERE ba.status = 'active' AND ba.priority = 'high'")->fetch()['count'];
    $stats['admitted_today'] = $db->query("SELECT COUNT(*) as count FROM bed_assignments WHERE status = 'active' AND DATE(assigned_date) = CURDATE()")->fetch()['count'];
    $stats['discharged_today'] = $db->query("SELECT COUNT(*) as count FROM bed_assignments WHERE status = 'discharged' AND DATE(discharge_date) = CURDATE()")->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_patients' => 0, 'inpatients' => 0, 'outpatients' => 0, 'critical_patients' => 0, 'admitted_today' => 0, 'discharged_today' => 0];
}

// Get departments for filter
$departments = [];
try {
    $departments = $db->query("SELECT * FROM departments ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $departments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Monitoring - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .patient-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .patient-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: var(--transition-all);
            position: relative;
            overflow: hidden;
        }
        
        .patient-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .patient-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-inpatient {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .status-outpatient {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .priority-indicator {
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 0 var(--radius-lg) var(--radius-lg) 0;
        }
        
        .priority-high {
            background: linear-gradient(180deg, #ef4444, #dc2626);
        }
        
        .priority-medium {
            background: linear-gradient(180deg, #f59e0b, #d97706);
        }
        
        .priority-low, .priority-normal {
            background: linear-gradient(180deg, #10b981, #059669);
        }
        
        .patient-header {
            margin-bottom: 1rem;
            padding-left: 1rem;
        }
        
        .patient-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .patient-id {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .patient-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.875rem;
        }
        
        .patient-metrics {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: rgba(59, 130, 246, 0.05);
            border-radius: var(--radius-lg);
            border: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .metric {
            text-align: center;
            flex: 1;
        }
        
        .metric-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .metric-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }
        
        .patient-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .vitals-indicator {
            margin-top: 0.75rem;
            padding: 0.5rem;
            border-radius: var(--radius-lg);
            font-size: 0.75rem;
        }
        
        .vitals-recent {
            background: rgba(16, 185, 129, 0.05);
            border: 1px solid rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .vitals-old {
            background: rgba(245, 158, 11, 0.05);
            border: 1px solid rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .vitals-none {
            background: rgba(107, 114, 128, 0.05);
            border: 1px solid rgba(107, 114, 128, 0.1);
            color: var(--text-secondary);
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
        
        .real-time-update {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-card);
            padding: 1rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: var(--transition-all);
        }
        
        .real-time-update.show {
            opacity: 1;
            transform: translateY(0);
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
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="beds.php"><i class="fas fa-bed"></i> Bed Management</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="ambulance-management.php"><i class="fas fa-ambulance"></i> Ambulance</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1><i class="fas fa-user-injured"></i> Patient Monitoring</h1>
                    <p>Real-time patient status tracking and management</p>
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

            <!-- Real-time Update Notification -->
            <div class="real-time-update" id="updateNotification">
                <i class="fas fa-sync-alt"></i> Updating patient data...
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card animate-fade-in">
                    <h3><?php echo number_format($stats['total_patients']); ?></h3>
                    <p>Total Patients</p>
                    <div class="stat-trend">
                        <i class="fas fa-users text-primary"></i>
                    </div>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.1s;">
                    <h3 class="text-danger"><?php echo number_format($stats['inpatients']); ?></h3>
                    <p>Inpatients</p>
                    <div class="stat-trend">
                        <i class="fas fa-bed text-danger"></i>
                    </div>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.2s;">
                    <h3 class="text-success"><?php echo number_format($stats['outpatients']); ?></h3>
                    <p>Outpatients</p>
                    <div class="stat-trend">
                        <i class="fas fa-walking text-success"></i>
                    </div>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.3s;">
                    <h3 class="text-warning"><?php echo number_format($stats['critical_patients']); ?></h3>
                    <p>Critical Cases</p>
                    <div class="stat-trend">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                    </div>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.4s;">
                    <h3><?php echo number_format($stats['admitted_today']); ?></h3>
                    <p>Admitted Today</p>
                    <div class="stat-trend">
                        <i class="fas fa-plus-circle text-info"></i>
                    </div>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.5s;">
                    <h3><?php echo number_format($stats['discharged_today']); ?></h3>
                    <p>Discharged Today</p>
                    <div class="stat-trend">
                        <i class="fas fa-sign-out-alt text-secondary"></i>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-panel">
                <form method="GET" class="filter-grid">
                    <div class="form-group">
                        <label for="search">Search Patients</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, ID, phone..." class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="status">Status Filter</label>
                        <select id="status" name="status" class="form-control">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Patients</option>
                            <option value="critical" <?php echo $status_filter === 'critical' ? 'selected' : ''; ?>>Critical Cases</option>
                            <option value="stable" <?php echo $status_filter === 'stable' ? 'selected' : ''; ?>>Stable Inpatients</option>
                            <option value="admitted_today" <?php echo $status_filter === 'admitted_today' ? 'selected' : ''; ?>>Admitted Today</option>
                            <option value="discharged_today" <?php echo $status_filter === 'discharged_today' ? 'selected' : ''; ?>>Discharged Today</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="type">Patient Type</label>
                        <select id="type" name="type" class="form-control">
                            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="inpatient" <?php echo $type_filter === 'inpatient' ? 'selected' : ''; ?>>Inpatients</option>
                            <option value="outpatient" <?php echo $type_filter === 'outpatient' ? 'selected' : ''; ?>>Outpatients</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <select id="department" name="department" class="form-control">
                            <option value="all" <?php echo $department_filter === 'all' ? 'selected' : ''; ?>>All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary w-full">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Patients Grid -->
            <div class="patient-grid">
                <?php if (empty($patients)): ?>
                    <div class="card text-center" style="grid-column: 1 / -1;">
                        <div class="card-body">
                            <i class="fas fa-user-injured" style="font-size: 4rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                            <h3>No patients found</h3>
                            <p>Try adjusting your filters or search criteria</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($patients as $patient): ?>
                        <div class="patient-card animate-fade-in" data-patient-id="<?php echo $patient['id']; ?>">
                            <!-- Priority Indicator -->
                            <?php if ($patient['patient_type'] === 'inpatient'): ?>
                                <div class="priority-indicator priority-<?php echo $patient['priority'] ?? 'normal'; ?>"></div>
                            <?php endif; ?>
                            
                            <!-- Status Badge -->
                            <div class="patient-status status-<?php echo $patient['patient_type']; ?>">
                                <?php echo ucfirst($patient['patient_type']); ?>
                            </div>
                            
                            <!-- Patient Header -->
                            <div class="patient-header">
                                <h4 class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></h4>
                                <p class="patient-id">ID: <?php echo htmlspecialchars($patient['patient_id']); ?></p>
                            </div>
                            
                            <!-- Patient Details -->
                            <div class="patient-details">
                                <div class="detail-item">
                                    <span class="detail-label">Age</span>
                                    <span class="detail-value"><?php echo $patient['age'] ?? 'N/A'; ?> years</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Gender</span>
                                    <span class="detail-value"><?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Doctor</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($patient['doctor_name'] ?? 'Not assigned'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Phone</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($patient['phone'] ?? 'N/A'); ?></span>
                                </div>
                                <?php if ($patient['patient_type'] === 'inpatient' && $patient['bed_number']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">Bed</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($patient['bed_number'] . ' (' . $patient['bed_type'] . ')'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Admitted</span>
                                        <span class="detail-value"><?php echo date('M d, Y', strtotime($patient['assigned_date'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Patient Metrics -->
                            <div class="patient-metrics">
                                <div class="metric">
                                    <div class="metric-value"><?php echo $patient['upcoming_appointments']; ?></div>
                                    <div class="metric-label">Appointments</div>
                                </div>
                                <div class="metric">
                                    <div class="metric-value"><?php echo $patient['active_prescriptions']; ?></div>
                                    <div class="metric-label">Prescriptions</div>
                                </div>
                                <div class="metric">
                                    <div class="metric-value"><?php echo $patient['pending_lab_tests']; ?></div>
                                    <div class="metric-label">Lab Tests</div>
                                </div>
                            </div>
                            
                            <!-- Vitals Indicator -->
                            <?php if ($patient['last_vitals_time']): ?>
                                <?php
                                $hours_ago = (time() - strtotime($patient['last_vitals_time'])) / 3600;
                                $vitals_class = $hours_ago <= 24 ? 'vitals-recent' : 'vitals-old';
                                ?>
                                <div class="vitals-indicator <?php echo $vitals_class; ?>">
                                    <i class="fas fa-heartbeat"></i>
                                    Last vitals: <?php echo date('M d, H:i', strtotime($patient['last_vitals_time'])); ?>
                                    <?php if ($hours_ago > 24): ?>
                                        (<?php echo round($hours_ago); ?> hours ago)
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="vitals-indicator vitals-none">
                                    <i class="fas fa-exclamation-circle"></i>
                                    No vitals recorded
                                </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="patient-actions">
                                <a href="patient-details.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'intern_doctor', 'intern_nurse'])): ?>
                                    <a href="patient-vitals.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-heartbeat"></i> Vitals
                                    </a>
                                <?php endif; ?>
                                <?php if ($patient['patient_type'] === 'inpatient' && in_array($user_role, ['admin', 'nurse', 'intern_nurse'])): ?>
                                    <button class="btn btn-warning btn-sm" onclick="updatePriority(<?php echo $patient['id']; ?>)">
                                        <i class="fas fa-flag"></i> Priority
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Theme controls
        const themeToggle = document.getElementById('themeToggle');
        const colorToggle = document.getElementById('colorToggle');
        const html = document.documentElement;
        
        // Theme functionality
        const themes = ['light', 'dark', 'medical'];
        let currentThemeIndex = 0;
        
        const savedTheme = localStorage.getItem('theme') || 'light';
        currentThemeIndex = themes.indexOf(savedTheme);
        if (currentThemeIndex === -1) currentThemeIndex = 0;
        
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);
        
        themeToggle.addEventListener('click', () => {
            currentThemeIndex = (currentThemeIndex + 1) % themes.length;
            const newTheme = themes[currentThemeIndex];
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
        
        colorToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'medical' ? 'light' : 'medical';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            currentThemeIndex = themes.indexOf(newTheme);
        });
        
        function updateThemeIcon(theme) {
            const themeIcon = themeToggle.querySelector('i');
            const colorIcon = colorToggle.querySelector('i');
            
            switch(theme) {
                case 'light':
                    themeIcon.className = 'fas fa-sun';
                    colorIcon.className = 'fas fa-palette';
                    break;
                case 'dark':
                    themeIcon.className = 'fas fa-moon';
                    colorIcon.className = 'fas fa-palette';
                    break;
                case 'medical':
                    themeIcon.className = 'fas fa-sun';
                    colorIcon.className = 'fas fa-heart';
                    break;
            }
        }
        
        // Real-time updates simulation
        function showUpdateNotification() {
            const notification = document.getElementById('updateNotification');
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            showUpdateNotification();
            // In a real implementation, you would fetch updated data via AJAX
        }, 30000);
        
        // Priority update function
        function updatePriority(patientId) {
            const priorities = ['low', 'normal', 'medium', 'high'];
            const currentPriority = prompt('Enter priority (low, normal, medium, high):');
            
            if (priorities.includes(currentPriority)) {
                // In a real implementation, send AJAX request to update priority
                alert(`Priority updated to: ${currentPriority}`);
                // Refresh the page or update the UI
                location.reload();
            } else {
                alert('Invalid priority level');
            }
        }
        
        // Auto-refresh patient data every 2 minutes
        setInterval(() => {
            const cards = document.querySelectorAll('.patient-card');
            cards.forEach(card => {
                card.style.opacity = '0.8';
                setTimeout(() => {
                    card.style.opacity = '1';
                }, 500);
            });
        }, 120000);
    </script>
</body>
</html>
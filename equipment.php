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
if (!in_array($user_role, ['admin', 'nurse', 'receptionist', 'doctor', 'intern_doctor', 'intern_nurse'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle equipment addition
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_equipment') {
    try {
        $equipment_sql = "INSERT INTO equipment (name, category, model, serial_number, manufacturer, purchase_date, warranty_expiry, cost, location, status, maintenance_schedule, specifications, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'operational', ?, ?, ?, NOW())";
        
        $db->query($equipment_sql, [
            $_POST['name'],
            $_POST['category'],
            $_POST['model'],
            $_POST['serial_number'],
            $_POST['manufacturer'],
            $_POST['purchase_date'] ?: null,
            $_POST['warranty_expiry'] ?: null,
            $_POST['cost'] ?: 0,
            $_POST['location'],
            $_POST['maintenance_schedule'] ?: 'monthly',
            $_POST['specifications'] ?: '',
            $_POST['notes'] ?: ''
        ]);
        
        $message = "Equipment added successfully!";
    } catch (Exception $e) {
        $message = "Error adding equipment: " . $e->getMessage();
    }
}

// Handle equipment update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_equipment') {
    try {
        $equipment_sql = "UPDATE equipment SET name = ?, category = ?, model = ?, serial_number = ?, manufacturer = ?, purchase_date = ?, warranty_expiry = ?, cost = ?, location = ?, status = ?, maintenance_schedule = ?, specifications = ?, notes = ?, updated_at = NOW() WHERE id = ?";
        
        $db->query($equipment_sql, [
            $_POST['name'],
            $_POST['category'],
            $_POST['model'],
            $_POST['serial_number'],
            $_POST['manufacturer'],
            $_POST['purchase_date'] ?: null,
            $_POST['warranty_expiry'] ?: null,
            $_POST['cost'] ?: 0,
            $_POST['location'],
            $_POST['status'],
            $_POST['maintenance_schedule'],
            $_POST['specifications'] ?: '',
            $_POST['notes'] ?: '',
            $_POST['equipment_id']
        ]);
        
        $message = "Equipment updated successfully!";
    } catch (Exception $e) {
        $message = "Error updating equipment: " . $e->getMessage();
    }
}

// Handle maintenance record
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_maintenance') {
    try {
        $maintenance_sql = "INSERT INTO equipment_maintenance (equipment_id, maintenance_type, maintenance_date, performed_by, cost, notes, next_maintenance_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $db->query($maintenance_sql, [
            $_POST['equipment_id'],
            $_POST['maintenance_type'],
            $_POST['maintenance_date'],
            $_SESSION['user_id'],
            $_POST['cost'] ?: 0,
            $_POST['notes'] ?: '',
            $_POST['next_maintenance_date'] ?: null
        ]);
        
        // Update equipment status if needed
        if ($_POST['maintenance_type'] === 'repair') {
            $db->query("UPDATE equipment SET status = 'operational', last_maintenance = ?, updated_at = NOW() WHERE id = ?", 
                      [$_POST['maintenance_date'], $_POST['equipment_id']]);
        }
        
        $message = "Maintenance record added successfully!";
    } catch (Exception $e) {
        $message = "Error adding maintenance record: " . $e->getMessage();
    }
}

// Get filters
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$location_filter = $_GET['location'] ?? '';
$search = $_GET['search'] ?? '';

// Build equipment query
$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM equipment_maintenance em WHERE em.equipment_id = e.id) as maintenance_count,
        (SELECT MAX(maintenance_date) FROM equipment_maintenance em WHERE em.equipment_id = e.id) as last_maintenance_date
        FROM equipment e WHERE 1=1";

$params = [];

if ($category_filter) {
    $sql .= " AND e.category = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    $sql .= " AND e.status = ?";
    $params[] = $status_filter;
}

if ($location_filter) {
    $sql .= " AND e.location LIKE ?";
    $params[] = "%$location_filter%";
}

if ($search) {
    $sql .= " AND (e.name LIKE ? OR e.model LIKE ? OR e.serial_number LIKE ? OR e.manufacturer LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$sql .= " ORDER BY e.name";

$equipment_list = $db->query($sql, $params)->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT DISTINCT category FROM equipment WHERE category IS NOT NULL ORDER BY category")->fetchAll();

// Get locations for filter
$locations = $db->query("SELECT DISTINCT location FROM equipment WHERE location IS NOT NULL ORDER BY location")->fetchAll();

// Get statistics
$stats = [];
try {
    $stats['total_equipment'] = $db->query("SELECT COUNT(*) as count FROM equipment")->fetch()['count'];
    $stats['operational'] = $db->query("SELECT COUNT(*) as count FROM equipment WHERE status = 'operational'")->fetch()['count'];
    $stats['maintenance'] = $db->query("SELECT COUNT(*) as count FROM equipment WHERE status = 'maintenance'")->fetch()['count'];
    $stats['out_of_order'] = $db->query("SELECT COUNT(*) as count FROM equipment WHERE status = 'out_of_order'")->fetch()['count'];
    $stats['this_month_maintenance'] = $db->query("SELECT COUNT(*) as count FROM equipment_maintenance WHERE MONTH(maintenance_date) = MONTH(CURRENT_DATE) AND YEAR(maintenance_date) = YEAR(CURRENT_DATE)")->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_equipment' => 0, 'operational' => 0, 'maintenance' => 0, 'out_of_order' => 0, 'this_month_maintenance' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Management - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .equipment-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: var(--transition-all);
            position: relative;
            overflow: hidden;
        }
        
        .equipment-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .equipment-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-operational {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-maintenance {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .status-out_of_order {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .equipment-category {
            display: inline-block;
            background: var(--primary-color);
            color: var(--text-white);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .equipment-details {
            margin-top: 1rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .detail-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--text-primary);
            font-weight: 600;
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
        
        .maintenance-indicator {
            margin-top: 1rem;
            padding: 0.75rem;
            border-radius: var(--radius-lg);
            background: rgba(59, 130, 246, 0.05);
            border: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .maintenance-overdue {
            background: rgba(239, 68, 68, 0.05);
            border-color: rgba(239, 68, 68, 0.1);
        }
        
        .maintenance-due-soon {
            background: rgba(245, 158, 11, 0.05);
            border-color: rgba(245, 158, 11, 0.1);
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
                <li><a href="equipment.php" class="active"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="beds.php"><i class="fas fa-bed"></i> Bed Management</a></li>
                <li><a href="patient-monitoring.php"><i class="fas fa-user-injured"></i> Patient Monitoring</a></li>
                <li><a href="ambulance-management.php"><i class="fas fa-ambulance"></i> Ambulance</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1><i class="fas fa-tools"></i> Equipment Management</h1>
                    <p>Manage hospital equipment and maintenance</p>
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
                    <h3><?php echo number_format($stats['total_equipment']); ?></h3>
                    <p>Total Equipment</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.1s;">
                    <h3><?php echo number_format($stats['operational']); ?></h3>
                    <p>Operational</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.2s;">
                    <h3><?php echo number_format($stats['maintenance']); ?></h3>
                    <p>Under Maintenance</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.3s;">
                    <h3><?php echo number_format($stats['out_of_order']); ?></h3>
                    <p>Out of Order</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.4s;">
                    <h3><?php echo number_format($stats['this_month_maintenance']); ?></h3>
                    <p>This Month Maintenance</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-panel">
                <form method="GET" class="filter-grid">
                    <div class="form-group">
                        <label for="search">Search Equipment</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, model, serial number..." class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($cat['category'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="operational" <?php echo $status_filter === 'operational' ? 'selected' : ''; ?>>Operational</option>
                            <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="out_of_order" <?php echo $status_filter === 'out_of_order' ? 'selected' : ''; ?>>Out of Order</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <select id="location" name="location" class="form-control">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location_filter === $loc['location'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['location']); ?>
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

            <!-- Action Buttons -->
            <div class="d-flex gap-4 mb-6">
                <?php if (in_array($user_role, ['admin', 'nurse', 'receptionist'])): ?>
                    <button class="btn btn-primary" onclick="showAddEquipmentModal()">
                        <i class="fas fa-plus"></i> Add Equipment
                    </button>
                <?php endif; ?>
            </div>

            <!-- Equipment Grid -->
            <div class="equipment-grid">
                <?php if (empty($equipment_list)): ?>
                    <div class="card text-center" style="grid-column: 1 / -1;">
                        <div class="card-body">
                            <i class="fas fa-tools" style="font-size: 4rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                            <h3>No equipment found</h3>
                            <p>Try adjusting your filters or add new equipment</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($equipment_list as $equipment): ?>
                        <div class="equipment-card animate-fade-in">
                            <div class="equipment-status status-<?php echo str_replace(' ', '_', $equipment['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $equipment['status'])); ?>
                            </div>
                            
                            <div class="equipment-category">
                                <?php echo htmlspecialchars(ucfirst($equipment['category'] ?? 'General')); ?>
                            </div>
                            
                            <h4><?php echo htmlspecialchars($equipment['name']); ?></h4>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($equipment['model']); ?></p>
                            
                            <div class="equipment-details">
                                <div class="detail-row">
                                    <span class="detail-label">Serial Number:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($equipment['serial_number'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Manufacturer:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($equipment['manufacturer'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Location:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($equipment['location'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Purchase Date:</span>
                                    <span class="detail-value"><?php echo $equipment['purchase_date'] ? date('M d, Y', strtotime($equipment['purchase_date'])) : 'N/A'; ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Maintenance Count:</span>
                                    <span class="detail-value"><?php echo $equipment['maintenance_count']; ?> times</span>
                                </div>
                            </div>
                            
                            <?php if ($equipment['last_maintenance_date']): ?>
                                <div class="maintenance-indicator">
                                    <small><strong>Last Maintenance:</strong> <?php echo date('M d, Y', strtotime($equipment['last_maintenance_date'])); ?></small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2 mt-4">
                                <button class="btn btn-outline btn-sm" onclick="viewEquipmentDetails(<?php echo $equipment['id']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <?php if (in_array($user_role, ['admin', 'nurse'])): ?>
                                    <button class="btn btn-primary btn-sm" onclick="editEquipment(<?php echo $equipment['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="addMaintenance(<?php echo $equipment['id']; ?>)">
                                        <i class="fas fa-wrench"></i> Maintenance
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Equipment Modal -->
    <div class="modal" id="addEquipmentModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Equipment</h5>
                    <button type="button" class="btn-close" onclick="closeModal('addEquipmentModal')">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_equipment">
                        
                        <div class="form-group">
                            <label for="name">Equipment Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select name="category" id="category" class="form-control" required>
                                <option value="">Select Category</option>
                                <option value="medical">Medical Equipment</option>
                                <option value="surgical">Surgical Equipment</option>
                                <option value="diagnostic">Diagnostic Equipment</option>
                                <option value="laboratory">Laboratory Equipment</option>
                                <option value="emergency">Emergency Equipment</option>
                                <option value="it">IT Equipment</option>
                                <option value="furniture">Furniture</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="model">Model</label>
                            <input type="text" name="model" id="model" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="serial_number">Serial Number</label>
                            <input type="text" name="serial_number" id="serial_number" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="manufacturer">Manufacturer</label>
                            <input type="text" name="manufacturer" id="manufacturer" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="purchase_date">Purchase Date</label>
                            <input type="date" name="purchase_date" id="purchase_date" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="warranty_expiry">Warranty Expiry</label>
                            <input type="date" name="warranty_expiry" id="warranty_expiry" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="cost">Cost</label>
                            <input type="number" name="cost" id="cost" class="form-control" step="0.01">
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" name="location" id="location" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="maintenance_schedule">Maintenance Schedule</label>
                            <select name="maintenance_schedule" id="maintenance_schedule" class="form-control">
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annually">Annually</option>
                                <option value="as_needed">As Needed</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="specifications">Specifications</label>
                            <textarea name="specifications" id="specifications" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addEquipmentModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showAddEquipmentModal() {
            document.getElementById('addEquipmentModal').classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function viewEquipmentDetails(equipmentId) {
            // Implement equipment details view
            alert('Equipment Details - ID: ' + equipmentId);
        }
        
        function editEquipment(equipmentId) {
            // Implement equipment editing
            alert('Edit Equipment - ID: ' + equipmentId);
        }
        
        function addMaintenance(equipmentId) {
            // Implement maintenance record
            alert('Add Maintenance - ID: ' + equipmentId);
        }
        
        // Theme controls
        const themeToggle = document.getElementById('themeToggle');
        const colorToggle = document.getElementById('colorToggle');
        const html = document.documentElement;
        
        // Theme functionality (same as dashboard)
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
        
        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
    </script>
</body>
</html>

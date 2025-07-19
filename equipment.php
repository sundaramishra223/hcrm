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
if (!in_array($user_role, ['admin', 'nurse', 'receptionist', 'doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle bed assignment
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'assign_bed') {
    try {
        $bed_id = $_POST['bed_id'];
        $patient_id = $_POST['patient_id'];
        $admission_date = $_POST['admission_date'];
        $notes = $_POST['notes'];
        
        // Check if bed is available
        $bed_status = $db->query("SELECT status FROM beds WHERE id = ?", [$bed_id])->fetch()['status'];
        
        if ($bed_status === 'available') {
            $db->getConnection()->beginTransaction();
            
            // Update bed status
            $db->query("UPDATE beds SET status = 'occupied', current_patient_id = ?, last_updated = NOW() WHERE id = ?", [$patient_id, $bed_id]);
            
            // Create bed assignment record
            $assignment_sql = "INSERT INTO bed_assignments (bed_id, patient_id, assigned_date, status, notes, assigned_by) VALUES (?, ?, ?, 'active', ?, ?)";
            $db->query($assignment_sql, [$bed_id, $patient_id, $admission_date, $notes, $_SESSION['user_id']]);
            
            $db->getConnection()->commit();
            $message = "Bed assigned successfully!";
        } else {
            $message = "Error: Bed is not available for assignment.";
        }
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle bed discharge
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'discharge_bed') {
    try {
        $assignment_id = $_POST['assignment_id'];
        $discharge_date = $_POST['discharge_date'];
        $discharge_notes = $_POST['discharge_notes'];
        
        $db->getConnection()->beginTransaction();
        
        // Get assignment details
        $assignment = $db->query("SELECT bed_id FROM bed_assignments WHERE id = ?", [$assignment_id])->fetch();
        
        // Update assignment record
        $db->query("UPDATE bed_assignments SET status = 'discharged', discharge_date = ?, discharge_notes = ? WHERE id = ?", 
                  [$discharge_date, $discharge_notes, $assignment_id]);
        
        // Update bed status
        $db->query("UPDATE beds SET status = 'maintenance', current_patient_id = NULL, last_updated = NOW() WHERE id = ?", [$assignment['bed_id']]);
        
        $db->getConnection()->commit();
        $message = "Patient discharged successfully! Bed marked for maintenance.";
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle equipment addition
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_equipment') {
    try {
        $equipment_sql = "INSERT INTO equipment (name, category, model, serial_number, manufacturer, purchase_date, warranty_expiry, cost, location, status, maintenance_schedule, specifications, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'operational', ?, ?, ?)";
        
        $db->query($equipment_sql, [
            $_POST['name'],
            $_POST['category'],
            $_POST['model'],
            $_POST['serial_number'],
            $_POST['manufacturer'],
            $_POST['purchase_date'],
            $_POST['warranty_expiry'],
            $_POST['cost'],
            $_POST['location'],
            $_POST['maintenance_schedule'],
            $_POST['specifications'],
            $_POST['notes']
        ]);
        
        $message = "Equipment added successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle equipment status update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_equipment_status') {
    try {
        $equipment_id = $_POST['equipment_id'];
        $new_status = $_POST['new_status'];
        $maintenance_notes = $_POST['maintenance_notes'];
        
        $db->query("UPDATE equipment SET status = ?, last_maintenance = NOW() WHERE id = ?", [$new_status, $equipment_id]);
        
        // Log maintenance record
        $maintenance_sql = "INSERT INTO equipment_maintenance (equipment_id, maintenance_type, maintenance_date, notes, performed_by) VALUES (?, 'status_update', NOW(), ?, ?)";
        $db->query($maintenance_sql, [$equipment_id, $maintenance_notes, $_SESSION['user_id']]);
        
        $message = "Equipment status updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get current view
$view = $_GET['view'] ?? 'beds';

// Get beds with patient information
$beds = $db->query("
    SELECT b.*, 
    ba.id as assignment_id,
    ba.assigned_date,
    ba.notes as assignment_notes,
    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
    p.patient_id,
    p.phone as patient_phone,
    r.name as room_name,
    d.name as department_name
    FROM beds b
    LEFT JOIN bed_assignments ba ON b.id = ba.bed_id AND ba.status = 'active'
    LEFT JOIN patients p ON ba.patient_id = p.id
    LEFT JOIN rooms r ON b.room_id = r.id
    LEFT JOIN departments d ON r.department_id = d.id
    ";
    ORDER BY d.name, r.name, b.bed_number
")->fetchAll();

// Get equipment
$equipment_search = $_GET['equipment_search'] ?? '';
$equipment_filter = $_GET['equipment_filter'] ?? '';

$equipment_sql = "SELECT e.*, 
                  (SELECT COUNT(*) FROM equipment_maintenance WHERE equipment_id = e.id) as maintenance_count,
                  (SELECT maintenance_date FROM equipment_maintenance WHERE equipment_id = e.id ORDER BY maintenance_date DESC LIMIT 1) as last_maintenance_date
                  FROM equipment e
                  ";

$equipment_params = [];

if ($equipment_search) {
    $equipment_sql .= " AND (e.name LIKE ? OR e.model LIKE ? OR e.serial_number LIKE ?)";
    $search_param = "%$equipment_search%";
    $equipment_params = [$search_param, $search_param, $search_param];
}

if ($equipment_filter) {
    $equipment_sql .= " AND e.category = ?";
    $equipment_params[] = $equipment_filter;
}

$equipment_sql .= " ORDER BY e.name";

$equipment = $db->query($equipment_sql, $equipment_params)->fetchAll();

// Get patients for bed assignment
$patients = $db->query("
    SELECT p.id, p.patient_id, CONCAT(p.first_name, ' ', p.last_name) as full_name, p.phone
    FROM patients p
    LEFT JOIN bed_assignments ba ON p.id = ba.patient_id AND ba.status = 'active'
    WHERE ba.id IS NULL
    ORDER BY p.first_name, p.last_name
")->fetchAll();

// Get equipment categories
$equipment_categories = $db->query("SELECT DISTINCT category FROM equipment ORDER BY category")->fetchAll();

// Get statistics
$stats = [];
try {
    $stats['total_beds'] = $db->query("SELECT COUNT(*) as count FROM beds")->fetch()['count'];
    $stats['occupied_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch()['count'];
    $stats['available_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'available'")->fetch()['count'];
    $stats['total_equipment'] = $db->query("SELECT COUNT(*) as count FROM equipment")->fetch()['count'];
    $stats['maintenance_due'] = $db->query("SELECT COUNT(*) as count FROM equipment WHERE status = 'maintenance'")->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_beds' => 0, 'occupied_beds' => 0, 'available_beds' => 0, 'total_equipment' => 0, 'maintenance_due' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment & Bed Management - Hospital CRM</title>
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .view-tabs {
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
            padding: 15px 30px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }
        
        .tab-button.active {
            background: white;
            color: #004685;
            border-bottom: 3px solid #004685;
        }
        
        .bed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 25px;
        }
        
        .bed-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .bed-card:hover {
            transform: translateY(-5px);
        }
        
        .bed-header {
            padding: 15px;
            color: white;
        }
        
        .bed-header.available {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .bed-header.occupied {
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
        }
        
        .bed-header.maintenance {
            background: linear-gradient(135deg, #ffc107, #ffb74d);
        }
        
        .bed-header.out-of-order {
            background: linear-gradient(135deg, #6c757d, #adb5bd);
        }
        
        .bed-header h4 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .bed-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .bed-body {
            padding: 15px;
        }
        
        .bed-info {
            margin-bottom: 15px;
        }
        
        .bed-info p {
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .bed-info strong {
            color: #004685;
        }
        
        .bed-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        .equipment-section {
            padding: 25px;
        }
        
        .equipment-filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
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
        
        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .equipment-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .equipment-card:hover {
            transform: translateY(-5px);
        }
        
        .equipment-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 15px;
        }
        
        .equipment-header.maintenance {
            background: linear-gradient(135deg, #ffc107, #e0a800);
        }
        
        .equipment-header.out-of-order {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        
        .equipment-header h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .equipment-header p {
            opacity: 0.9;
            font-size: 13px;
        }
        
        .equipment-body {
            padding: 15px;
        }
        
        .equipment-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item label {
            font-size: 11px;
            color: #666;
            margin-bottom: 2px;
        }
        
        .info-item span {
            font-weight: 500;
            color: #333;
            font-size: 13px;
        }
        
        .equipment-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
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
            max-width: 700px;
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
            
            .bed-grid, .equipment-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .equipment-info {
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Equipment & Bed Management</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <?php if ($user_role === 'admin'): ?>
                    <button onclick="openEquipmentModal()" class="btn btn-primary">+ Add Equipment</button>
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
                <h3><?php echo number_format($stats['total_beds']); ?></h3>
                <p>Total Beds</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['occupied_beds']); ?></h3>
                <p>Occupied Beds</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['available_beds']); ?></h3>
                <p>Available Beds</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_equipment']); ?></h3>
                <p>Total Equipment</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['maintenance_due']); ?></h3>
                <p>Maintenance Due</p>
            </div>
        </div>
        
        <div class="view-tabs">
            <div class="tab-buttons">
                <a href="?view=beds" class="tab-button <?php echo $view === 'beds' ? 'active' : ''; ?>">
                    Bed Management
                </a>
                <a href="?view=equipment" class="tab-button <?php echo $view === 'equipment' ? 'active' : ''; ?>">
                    Equipment Management
                </a>
            </div>
            
            <?php if ($view === 'beds'): ?>
            <div class="bed-grid">
                <?php if (empty($beds)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: #666;">
                        <h3>No beds found</h3>
                        <p>Contact administrator to set up bed configuration.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($beds as $bed): ?>
                        <div class="bed-card">
                            <div class="bed-header <?php echo $bed['status']; ?>">
                                <h4>Bed <?php echo htmlspecialchars($bed['bed_number']); ?></h4>
                                <p><?php echo htmlspecialchars($bed['room_name'] ?? 'Room ' . $bed['room_id']); ?> - <?php echo htmlspecialchars($bed['department_name'] ?? 'General Ward'); ?></p>
                            </div>
                            
                            <div class="bed-body">
                                <div class="bed-info">
                                    <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $bed['status'])); ?></p>
                                    <p><strong>Type:</strong> <?php echo ucfirst($bed['bed_type']); ?></p>
                                    
                                    <?php if ($bed['status'] === 'occupied' && $bed['patient_name']): ?>
                                        <p><strong>Patient:</strong> <?php echo htmlspecialchars($bed['patient_name']); ?></p>
                                        <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($bed['patient_id']); ?></p>
                                        <p><strong>Admitted:</strong> <?php echo date('M d, Y', strtotime($bed['assigned_date'])); ?></p>
                                        <?php if ($bed['patient_phone']): ?>
                                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($bed['patient_phone']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($bed['assignment_notes']): ?>
                                            <p><strong>Notes:</strong> <?php echo htmlspecialchars(substr($bed['assignment_notes'], 0, 50)); ?>
                                               <?php if (strlen($bed['assignment_notes']) > 50): ?>...<?php endif; ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="bed-actions">
                                    <?php if ($bed['status'] === 'available'): ?>
                                        <button onclick="openBedAssignModal(<?php echo $bed['id']; ?>, '<?php echo htmlspecialchars($bed['bed_number']); ?>', '<?php echo htmlspecialchars($bed['room_name']); ?>')" 
                                                class="btn btn-success btn-sm">Assign Patient</button>
                                    <?php elseif ($bed['status'] === 'occupied'): ?>
                                        <button onclick="openDischargeModal(<?php echo $bed['assignment_id']; ?>, '<?php echo htmlspecialchars($bed['patient_name']); ?>')" 
                                                class="btn btn-warning btn-sm">Discharge</button>
                                    <?php elseif ($bed['status'] === 'maintenance'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_bed_status">
                                            <input type="hidden" name="bed_id" value="<?php echo $bed['id']; ?>">
                                            <input type="hidden" name="new_status" value="available">
                                            <button type="submit" class="btn btn-success btn-sm">Mark Available</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <div class="equipment-section">
                <div class="equipment-filters">
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="view" value="equipment">
                        <div class="form-group">
                            <label for="equipment_search">Search Equipment</label>
                            <input type="text" name="equipment_search" id="equipment_search" 
                                   placeholder="Search by name, model, serial number..." 
                                   value="<?php echo htmlspecialchars($equipment_search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="equipment_filter">Category</label>
                            <select name="equipment_filter" id="equipment_filter">
                                <option value="">All Categories</option>
                                <?php foreach ($equipment_categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                            <?php echo $equipment_filter === $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="?view=equipment" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
                
                <div class="equipment-grid">
                    <?php if (empty($equipment)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: #666;">
                            <h3>No equipment found</h3>
                            <p>Add your first equipment to get started.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($equipment as $item): ?>
                            <div class="equipment-card">
                                <div class="equipment-header <?php echo $item['status']; ?>">
                                    <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($item['category']); ?> - <?php echo htmlspecialchars($item['model']); ?></p>
                                </div>
                                
                                <div class="equipment-body">
                                    <div class="equipment-info">
                                        <div class="info-item">
                                            <label>Serial Number</label>
                                            <span><?php echo htmlspecialchars($item['serial_number']); ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <label>Manufacturer</label>
                                            <span><?php echo htmlspecialchars($item['manufacturer']); ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <label>Location</label>
                                            <span><?php echo htmlspecialchars($item['location']); ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <label>Status</label>
                                            <span><?php echo ucfirst(str_replace('_', ' ', $item['status'])); ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <label>Purchase Date</label>
                                            <span><?php echo date('M Y', strtotime($item['purchase_date'])); ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <label>Cost</label>
                                            <span>₹<?php echo number_format($item['cost'], 2); ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($item['warranty_expiry'] && strtotime($item['warranty_expiry']) > time()): ?>
                                        <p style="text-align: center; margin-bottom: 15px; padding: 5px; background: #d4edda; color: #155724; border-radius: 5px; font-size: 12px;">
                                            Warranty valid until <?php echo date('M Y', strtotime($item['warranty_expiry'])); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="equipment-actions">
                                        <?php if ($user_role === 'admin'): ?>
                                            <button onclick="openMaintenanceModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>', '<?php echo $item['status']; ?>')" 
                                                    class="btn btn-warning btn-sm">Maintenance</button>
                                        <?php endif; ?>
                                        <button onclick="viewEquipment(<?php echo $item['id']; ?>)" class="btn btn-primary btn-sm">View Details</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bed Assignment Modal -->
    <div id="bedAssignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Bed to Patient</h2>
                <button type="button" class="close" onclick="closeBedAssignModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="assign_bed">
                    <input type="hidden" name="bed_id" id="assign_bed_id">
                    
                    <div class="form-group">
                        <label>Bed: <strong id="assign_bed_info"></strong></label>
                    </div>
                    
                    <div class="form-group">
                        <label for="patient_id">Patient *</label>
                        <select id="patient_id" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>">
                                    <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['patient_id'] . ') - ' . $patient['phone']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="admission_date">Admission Date *</label>
                        <input type="datetime-local" id="admission_date" name="admission_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="assign_notes">Notes</label>
                        <textarea id="assign_notes" name="notes" rows="3" placeholder="Admission notes or special requirements..."></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeBedAssignModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-success">Assign Bed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Discharge Modal -->
    <div id="dischargeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Discharge Patient</h2>
                <button type="button" class="close" onclick="closeDischargeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="discharge_bed">
                    <input type="hidden" name="assignment_id" id="discharge_assignment_id">
                    
                    <div class="form-group">
                        <label>Patient: <strong id="discharge_patient_name"></strong></label>
                    </div>
                    
                    <div class="form-group">
                        <label for="discharge_date">Discharge Date & Time *</label>
                        <input type="datetime-local" id="discharge_date" name="discharge_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="discharge_notes">Discharge Notes</label>
                        <textarea id="discharge_notes" name="discharge_notes" rows="4" placeholder="Discharge summary, follow-up instructions, etc."></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeDischargeModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-warning">Discharge Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Equipment Modal -->
    <div id="equipmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Equipment</h2>
                <button type="button" class="close" onclick="closeEquipmentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_equipment">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="equipment_name">Equipment Name *</label>
                            <input type="text" id="equipment_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="equipment_category">Category *</label>
                            <select id="equipment_category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Medical Devices">Medical Devices</option>
                                <option value="Diagnostic Equipment">Diagnostic Equipment</option>
                                <option value="Surgical Instruments">Surgical Instruments</option>
                                <option value="Monitoring Equipment">Monitoring Equipment</option>
                                <option value="Life Support">Life Support</option>
                                <option value="Laboratory Equipment">Laboratory Equipment</option>
                                <option value="Radiology Equipment">Radiology Equipment</option>
                                <option value="Furniture">Furniture</option>
                                <option value="IT Equipment">IT Equipment</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="equipment_model">Model *</label>
                            <input type="text" id="equipment_model" name="model" required>
                        </div>
                        <div class="form-group">
                            <label for="serial_number">Serial Number</label>
                            <input type="text" id="serial_number" name="serial_number">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="manufacturer">Manufacturer *</label>
                            <input type="text" id="manufacturer" name="manufacturer" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Location *</label>
                            <input type="text" id="location" name="location" placeholder="e.g., ICU, Ward 1, Lab" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="purchase_date">Purchase Date</label>
                            <input type="date" id="purchase_date" name="purchase_date">
                        </div>
                        <div class="form-group">
                            <label for="warranty_expiry">Warranty Expiry</label>
                            <input type="date" id="warranty_expiry" name="warranty_expiry">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cost">Cost (₹)</label>
                            <input type="number" id="cost" name="cost" min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="maintenance_schedule">Maintenance Schedule</label>
                            <select id="maintenance_schedule" name="maintenance_schedule">
                                <option value="">Select Schedule</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="bi-annually">Bi-annually</option>
                                <option value="annually">Annually</option>
                                <option value="as-needed">As Needed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="specifications">Specifications</label>
                        <textarea id="specifications" name="specifications" rows="3" placeholder="Technical specifications, features, etc."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="equipment_notes">Notes</label>
                        <textarea id="equipment_notes" name="notes" rows="2" placeholder="Additional notes..."></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeEquipmentModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Equipment Maintenance Modal -->
    <div id="maintenanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Equipment Maintenance</h2>
                <button type="button" class="close" onclick="closeMaintenanceModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_equipment_status">
                    <input type="hidden" name="equipment_id" id="maintenance_equipment_id">
                    
                    <div class="form-group">
                        <label>Equipment: <strong id="maintenance_equipment_name"></strong></label>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_status">New Status *</label>
                        <select id="new_status" name="new_status" required>
                            <option value="operational">Operational</option>
                            <option value="maintenance">Under Maintenance</option>
                            <option value="out-of-order">Out of Order</option>
                            <option value="retired">Retired</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="maintenance_notes">Maintenance Notes *</label>
                        <textarea id="maintenance_notes" name="maintenance_notes" rows="4" placeholder="Describe the maintenance work, issues found, parts replaced, etc." required></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeMaintenanceModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Show pop-up messages
        <?php if ($message): ?>
            <?php if (strpos($message, 'Error:') === 0): ?>
                showError('<?php echo addslashes($message); ?>');
            <?php else: ?>
                showSuccess('<?php echo addslashes($message); ?>');
            <?php endif; ?>
        <?php endif; ?>
        
        function openBedAssignModal(bedId, bedNumber, roomName) {
            document.getElementById('assign_bed_id').value = bedId;
            document.getElementById('assign_bed_info').textContent = 'Bed ' + bedNumber + ' - ' + roomName;
            document.getElementById('bedAssignModal').style.display = 'block';
        }
        
        function closeBedAssignModal() {
            document.getElementById('bedAssignModal').style.display = 'none';
        }
        
        function openDischargeModal(assignmentId, patientName) {
            document.getElementById('discharge_assignment_id').value = assignmentId;
            document.getElementById('discharge_patient_name').textContent = patientName;
            document.getElementById('dischargeModal').style.display = 'block';
        }
        
        function closeDischargeModal() {
            document.getElementById('dischargeModal').style.display = 'none';
        }
        
        function openEquipmentModal() {
            document.getElementById('equipmentModal').style.display = 'block';
        }
        
        function closeEquipmentModal() {
            document.getElementById('equipmentModal').style.display = 'none';
        }
        
        function openMaintenanceModal(equipmentId, equipmentName, currentStatus) {
            document.getElementById('maintenance_equipment_id').value = equipmentId;
            document.getElementById('maintenance_equipment_name').textContent = equipmentName;
            document.getElementById('new_status').value = currentStatus;
            document.getElementById('maintenanceModal').style.display = 'block';
        }
        
        function closeMaintenanceModal() {
            document.getElementById('maintenanceModal').style.display = 'none';
        }
        
        function viewEquipment(equipmentId) {
            window.location.href = 'equipment-details.php?id=' + equipmentId;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['bedAssignModal', 'dischargeModal', 'equipmentModal', 'maintenanceModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>

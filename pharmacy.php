<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'pharmacy_staff', 'doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Get pharmacy staff ID if user is pharmacy staff
$pharmacy_staff_id = null;
if ($user_role === 'pharmacy_staff') {
    $staff_info = $db->query("SELECT id FROM staff WHERE user_id = ? AND staff_type = 'pharmacy_staff'", [$_SESSION['user_id']])->fetch();
    $pharmacy_staff_id = $staff_info['id'] ?? null;
}

// Handle medicine addition/update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_medicine') {
    try {
        $medicine_sql = "INSERT INTO medicines (hospital_id, name, generic_name, manufacturer, category, dosage_form, strength, unit_price, pack_size, batch_number, expiry_date, stock_quantity, min_stock_level, prescription_required, side_effects, contraindications, storage_conditions, notes) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($medicine_sql, [
            $_POST['name'],
            $_POST['generic_name'],
            $_POST['manufacturer'],
            $_POST['category'],
            $_POST['dosage_form'],
            $_POST['strength'],
            $_POST['unit_price'],
            $_POST['pack_size'],
            $_POST['batch_number'],
            $_POST['expiry_date'],
            $_POST['stock_quantity'],
            $_POST['min_stock_level'],
            isset($_POST['prescription_required']) ? 1 : 0,
            $_POST['side_effects'],
            $_POST['contraindications'],
            $_POST['storage_conditions'],
            $_POST['notes']
        ]);
        
        $message = "Medicine added successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle stock update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    try {
        $medicine_id = $_POST['medicine_id'];
        $new_stock = $_POST['new_stock'];
        $update_type = $_POST['update_type'];
        $notes = $_POST['notes'];
        
        // Get current stock
        $current_stock = $db->query("SELECT stock_quantity FROM medicines WHERE id = ?", [$medicine_id])->fetch()['stock_quantity'];
        
        if ($update_type === 'add') {
            $final_stock = $current_stock + $new_stock;
        } elseif ($update_type === 'subtract') {
            $final_stock = max(0, $current_stock - $new_stock);
        } else {
            $final_stock = $new_stock;
        }
        
        // Update stock
        $db->query("UPDATE medicines SET stock_quantity = ?, updated_at = NOW() WHERE id = ?", [$final_stock, $medicine_id]);
        
        // Record stock movement
        $movement_sql = "INSERT INTO medicine_stock_movements (medicine_id, movement_type, quantity, previous_stock, new_stock, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $db->query($movement_sql, [
            $medicine_id,
            $update_type,
            $new_stock,
            $current_stock,
            $final_stock,
            $notes,
            $_SESSION['user_id']
        ]);
        
        $message = "Stock updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle prescription dispensing
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'dispense_prescription') {
    try {
        $db->getConnection()->beginTransaction();
        
        $prescription_id = $_POST['prescription_id'];
        $medicines = $_POST['medicines'];
        
        foreach ($medicines as $medicine_data) {
            if (isset($medicine_data['dispense']) && $medicine_data['dispense']) {
                $medicine_id = $medicine_data['medicine_id'];
                $dispensed_quantity = (int)$medicine_data['dispensed_quantity'];
                
                // Check stock availability
                $current_stock = $db->query("SELECT stock_quantity FROM medicines WHERE id = ?", [$medicine_id])->fetch()['stock_quantity'];
                
                if ($current_stock >= $dispensed_quantity) {
                    // Update stock
                    $new_stock = $current_stock - $dispensed_quantity;
                    $db->query("UPDATE medicines SET stock_quantity = ? WHERE id = ?", [$new_stock, $medicine_id]);
                    
                    // Record dispensing
                    $dispense_sql = "INSERT INTO prescription_dispensing (prescription_id, medicine_id, dispensed_quantity, dispensed_by, dispensed_at) VALUES (?, ?, ?, ?, NOW())";
                    $db->query($dispense_sql, [$prescription_id, $medicine_id, $dispensed_quantity, $pharmacy_staff_id ?: $_SESSION['user_id']]);
                    
                    // Record stock movement
                    $movement_sql = "INSERT INTO medicine_stock_movements (medicine_id, movement_type, quantity, previous_stock, new_stock, notes, created_by) VALUES (?, 'subtract', ?, ?, ?, ?, ?)";
                    $db->query($movement_sql, [
                        $medicine_id,
                        $dispensed_quantity,
                        $current_stock,
                        $new_stock,
                        "Dispensed for prescription #" . $prescription_id,
                        $_SESSION['user_id']
                    ]);
                    
                    // Update prescription medicine status
                    $db->query("UPDATE prescription_medicines SET dispensed_quantity = dispensed_quantity + ?, dispensed_at = NOW() WHERE prescription_id = ? AND medicine_id = ?", 
                              [$dispensed_quantity, $prescription_id, $medicine_id]);
                }
            }
        }
        
        // Update prescription status if all medicines dispensed
        $pending_medicines = $db->query("SELECT COUNT(*) as count FROM prescription_medicines WHERE prescription_id = ? AND (dispensed_quantity < quantity OR dispensed_quantity IS NULL)", [$prescription_id])->fetch()['count'];
        
        if ($pending_medicines == 0) {
            $db->query("UPDATE prescriptions SET dispensed_at = NOW(), status = 'dispensed' WHERE id = ?", [$prescription_id]);
        }
        
        $db->getConnection()->commit();
        $message = "Prescription dispensed successfully!";
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Get medicines with search and filters
$search = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_stock = $_GET['stock'] ?? '';

$sql = "SELECT m.*, 
        (CASE WHEN m.stock_quantity <= m.min_stock_level THEN 'low' 
              WHEN m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring' 
              ELSE 'normal' END) as stock_status
        FROM medicines m
        WHERE m.hospital_id = 1 AND m.is_active = 1";

$params = [];

if ($search) {
    $sql .= " AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.manufacturer LIKE ? OR m.batch_number LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

if ($filter_category) {
    $sql .= " AND m.category = ?";
    $params[] = $filter_category;
}

if ($filter_stock === 'low') {
    $sql .= " AND m.stock_quantity <= m.min_stock_level";
} elseif ($filter_stock === 'expiring') {
    $sql .= " AND m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($filter_stock === 'out') {
    $sql .= " AND m.stock_quantity = 0";
}

$sql .= " ORDER BY m.name";

$medicines = $db->query($sql, $params)->fetchAll();

// Get pending prescriptions
$pending_prescriptions = [];
if (in_array($user_role, ['admin', 'pharmacy_staff'])) {
    $pending_prescriptions = $db->query("
        SELECT p.*, 
        CONCAT(pt.first_name, ' ', pt.last_name) as patient_name,
        pt.patient_id,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        (SELECT COUNT(*) FROM prescription_medicines WHERE prescription_id = p.id) as medicine_count,
        (SELECT COUNT(*) FROM prescription_medicines pm WHERE pm.prescription_id = p.id AND (pm.dispensed_quantity >= pm.quantity)) as dispensed_count
        FROM prescriptions p
        JOIN patients pt ON p.patient_id = pt.id
        JOIN doctors d ON p.doctor_id = d.id
        WHERE p.status IN ('active', 'partial')
        ORDER BY p.created_at DESC
        LIMIT 20
    ")->fetchAll();
}

// Get medicine categories
$categories = $db->query("SELECT DISTINCT category FROM medicines WHERE hospital_id = 1 AND is_active = 1 ORDER BY category")->fetchAll();

// Get pharmacy statistics
$stats = [];
try {
    $stats['total_medicines'] = $db->query("SELECT COUNT(*) as count FROM medicines WHERE hospital_id = 1 AND is_active = 1")->fetch()['count'];
    $stats['low_stock'] = $db->query("SELECT COUNT(*) as count FROM medicines WHERE hospital_id = 1 AND is_active = 1 AND stock_quantity <= min_stock_level")->fetch()['count'];
    $stats['expiring_soon'] = $db->query("SELECT COUNT(*) as count FROM medicines WHERE hospital_id = 1 AND is_active = 1 AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch()['count'];
    $stats['total_value'] = $db->query("SELECT SUM(stock_quantity * unit_price) as value FROM medicines WHERE hospital_id = 1 AND is_active = 1")->fetch()['value'] ?? 0;
} catch (Exception $e) {
    $stats = ['total_medicines' => 0, 'low_stock' => 0, 'expiring_soon' => 0, 'total_value' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management - Hospital CRM</title>
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
        
        .stat-card.warning h3 {
            color: #dc3545;
        }
        
        .tabs {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
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
            grid-template-columns: 2fr 1fr 1fr auto;
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
        
        .medicines-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .medicine-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .medicine-card:hover {
            transform: translateY(-5px);
        }
        
        .medicine-header {
            background: linear-gradient(135deg, #2196f3, #64b5f6);
            color: white;
            padding: 15px;
        }
        
        .medicine-header.low-stock {
            background: linear-gradient(135deg, #dc3545, #f8d7da);
        }
        
        .medicine-header.expiring {
            background: linear-gradient(135deg, #ff9800, #ffb74d);
        }
        
        .medicine-header h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .medicine-header p {
            opacity: 0.9;
            font-size: 13px;
        }
        
        .medicine-body {
            padding: 15px;
        }
        
        .medicine-info {
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
        
        .stock-indicator {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 600;
        }
        
        .stock-normal {
            background: #d4edda;
            color: #155724;
        }
        
        .stock-low {
            background: #f8d7da;
            color: #721c24;
        }
        
        .stock-expiring {
            background: #fff3cd;
            color: #856404;
        }
        
        .medicine-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        .prescriptions-table {
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
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-partial {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-dispensed {
            background: #f3e5f5;
            color: #7b1fa2;
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
            
            .medicines-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .medicine-info {
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
            <h1>Pharmacy Management</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <?php if (in_array($user_role, ['admin', 'pharmacy_staff'])): ?>
                    <button onclick="openMedicineModal()" class="btn btn-primary">+ Add Medicine</button>
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
                <h3><?php echo number_format($stats['total_medicines']); ?></h3>
                <p>Total Medicines</p>
            </div>
            <div class="stat-card <?php echo $stats['low_stock'] > 0 ? 'warning' : ''; ?>">
                <h3><?php echo number_format($stats['low_stock']); ?></h3>
                <p>Low Stock Items</p>
            </div>
            <div class="stat-card <?php echo $stats['expiring_soon'] > 0 ? 'warning' : ''; ?>">
                <h3><?php echo number_format($stats['expiring_soon']); ?></h3>
                <p>Expiring Soon</p>
            </div>
            <div class="stat-card">
                <h3>₹<?php echo number_format($stats['total_value'], 2); ?></h3>
                <p>Inventory Value</p>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="showTab('medicines')">Medicine Inventory</button>
                <?php if (in_array($user_role, ['admin', 'pharmacy_staff'])): ?>
                    <button class="tab-button" onclick="showTab('prescriptions')">Pending Prescriptions</button>
                <?php endif; ?>
            </div>
            
            <div id="medicines" class="tab-content active">
                <div class="filters">
                    <form method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="search">Search Medicines</label>
                            <input type="text" name="search" id="search" placeholder="Search by name, generic name, manufacturer..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select name="category" id="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                            <?php echo $filter_category === $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock">Stock Status</label>
                            <select name="stock" id="stock">
                                <option value="">All Items</option>
                                <option value="low" <?php echo $filter_stock === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                <option value="expiring" <?php echo $filter_stock === 'expiring' ? 'selected' : ''; ?>>Expiring Soon</option>
                                <option value="out" <?php echo $filter_stock === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="pharmacy.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
                
                <div class="medicines-grid">
                    <?php if (empty($medicines)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: #666;">
                            <h3>No medicines found</h3>
                            <p>Add your first medicine to get started.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($medicines as $medicine): ?>
                            <div class="medicine-card">
                                <div class="medicine-header <?php echo $medicine['stock_status']; ?>">
                                    <h4><?php echo htmlspecialchars($medicine['name']); ?></h4>
                                    <p><?php echo htmlspecialchars($medicine['generic_name'] ?? 'Generic name not specified'); ?></p>
                                </div>
                                
                                <div class="medicine-body">
                                    <div class="medicine-info">
                                        <div class="info-item">
                                            <label>Manufacturer</label>
                                            <span><?php echo htmlspecialchars($medicine['manufacturer'] ?? 'N/A'); ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <label>Category</label>
                                            <span><?php echo htmlspecialchars($medicine['category']); ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <label>Strength</label>
                                            <span><?php echo htmlspecialchars($medicine['strength']); ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <label>Form</label>
                                            <span><?php echo htmlspecialchars($medicine['dosage_form']); ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <label>Price</label>
                                            <span>₹<?php echo number_format($medicine['unit_price'], 2); ?></span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <label>Expiry</label>
                                            <span><?php echo date('M Y', strtotime($medicine['expiry_date'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="stock-indicator stock-<?php echo $medicine['stock_status']; ?>">
                                        Stock: <?php echo $medicine['stock_quantity']; ?> units
                                        <?php if ($medicine['stock_status'] === 'low'): ?>
                                            (Low Stock!)
                                        <?php elseif ($medicine['stock_status'] === 'expiring'): ?>
                                            (Expiring Soon!)
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="medicine-actions">
                                        <?php if (in_array($user_role, ['admin', 'pharmacy_staff'])): ?>
                                            <button onclick="openStockModal(<?php echo $medicine['id']; ?>, '<?php echo htmlspecialchars($medicine['name']); ?>', <?php echo $medicine['stock_quantity']; ?>)" 
                                                    class="btn btn-warning btn-sm">Update Stock</button>
                                        <?php endif; ?>
                                        <a href="medicine-details.php?id=<?php echo $medicine['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (in_array($user_role, ['admin', 'pharmacy_staff'])): ?>
            <div id="prescriptions" class="tab-content">
                <h3 style="color: #004685; margin-bottom: 20px;">Pending Prescriptions</h3>
                
                <div class="prescriptions-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Prescription #</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date</th>
                                <th>Medicines</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_prescriptions)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px; color: #666;">
                                        No pending prescriptions found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_prescriptions as $prescription): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($prescription['prescription_number']); ?></strong>
                                            <br>
                                            <small style="color: #666;"><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($prescription['patient_name']); ?></strong>
                                            <br>
                                            <small style="color: #666;">ID: <?php echo htmlspecialchars($prescription['patient_id']); ?></small>
                                        </td>
                                        <td>
                                            <strong>Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></strong>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></td>
                                        <td>
                                            <span class="badge badge-active"><?php echo $prescription['medicine_count']; ?> medicines</span>
                                            <br>
                                            <small style="color: #666;">
                                                <?php echo $prescription['dispensed_count']; ?>/<?php echo $prescription['medicine_count']; ?> dispensed
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $prescription['status']; ?>">
                                                <?php echo ucfirst($prescription['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button onclick="openDispenseModal(<?php echo $prescription['id']; ?>)" class="btn btn-success btn-sm">Dispense</button>
                                            <a href="prescription-details.php?id=<?php echo $prescription['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Medicine Modal -->
    <div id="medicineModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Medicine</h2>
                <button type="button" class="close" onclick="closeMedicineModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_medicine">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Medicine Name *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="generic_name">Generic Name</label>
                            <input type="text" id="generic_name" name="generic_name">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="manufacturer">Manufacturer *</label>
                            <input type="text" id="manufacturer" name="manufacturer" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <select id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Antibiotics">Antibiotics</option>
                                <option value="Analgesics">Analgesics</option>
                                <option value="Antacids">Antacids</option>
                                <option value="Antidiabetics">Antidiabetics</option>
                                <option value="Antihypertensives">Antihypertensives</option>
                                <option value="Vitamins">Vitamins & Supplements</option>
                                <option value="Cardiac">Cardiac Medications</option>
                                <option value="Respiratory">Respiratory</option>
                                <option value="Dermatology">Dermatology</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dosage_form">Dosage Form *</label>
                            <select id="dosage_form" name="dosage_form" required>
                                <option value="">Select Form</option>
                                <option value="Tablet">Tablet</option>
                                <option value="Capsule">Capsule</option>
                                <option value="Syrup">Syrup</option>
                                <option value="Injection">Injection</option>
                                <option value="Drops">Drops</option>
                                <option value="Cream">Cream/Ointment</option>
                                <option value="Powder">Powder</option>
                                <option value="Inhaler">Inhaler</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="strength">Strength *</label>
                            <input type="text" id="strength" name="strength" placeholder="e.g., 500mg, 10ml" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="unit_price">Unit Price (₹) *</label>
                            <input type="number" id="unit_price" name="unit_price" min="0" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="pack_size">Pack Size</label>
                            <input type="text" id="pack_size" name="pack_size" placeholder="e.g., 10 tablets, 100ml">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="batch_number">Batch Number</label>
                            <input type="text" id="batch_number" name="batch_number">
                        </div>
                        <div class="form-group">
                            <label for="expiry_date">Expiry Date</label>
                            <input type="date" id="expiry_date" name="expiry_date" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="stock_quantity">Initial Stock Quantity *</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="min_stock_level">Minimum Stock Level</label>
                            <input type="number" id="min_stock_level" name="min_stock_level" min="0" value="10">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="prescription_required" value="1" style="width: auto; margin-right: 10px;">
                            Prescription Required
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="side_effects">Side Effects</label>
                        <textarea id="side_effects" name="side_effects" rows="3" placeholder="Common side effects..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="contraindications">Contraindications</label>
                        <textarea id="contraindications" name="contraindications" rows="3" placeholder="When not to use..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="storage_conditions">Storage Conditions</label>
                            <input type="text" id="storage_conditions" name="storage_conditions" placeholder="e.g., Store in cool, dry place">
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes" rows="2" placeholder="Additional notes..."></textarea>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeMedicineModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Medicine</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Update Stock Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Update Stock</h2>
                <button type="button" class="close" onclick="closeStockModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="medicine_id" id="stock_medicine_id">
                    
                    <div class="form-group">
                        <label>Medicine: <strong id="stock_medicine_name"></strong></label>
                    </div>
                    
                    <div class="form-group">
                        <label>Current Stock: <strong id="stock_current"></strong> units</label>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="update_type">Update Type *</label>
                            <select id="update_type" name="update_type" required>
                                <option value="add">Add to Stock</option>
                                <option value="subtract">Remove from Stock</option>
                                <option value="set">Set Exact Stock</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="new_stock">Quantity *</label>
                            <input type="number" id="new_stock" name="new_stock" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="stock_notes">Notes</label>
                        <textarea id="stock_notes" name="notes" rows="3" placeholder="Reason for stock update..."></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeStockModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Stock</button>
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
        
        function openMedicineModal() {
            document.getElementById('medicineModal').style.display = 'block';
        }
        
        function closeMedicineModal() {
            document.getElementById('medicineModal').style.display = 'none';
        }
        
        function openStockModal(medicineId, medicineName, currentStock) {
            document.getElementById('stock_medicine_id').value = medicineId;
            document.getElementById('stock_medicine_name').textContent = medicineName;
            document.getElementById('stock_current').textContent = currentStock;
            document.getElementById('stockModal').style.display = 'block';
        }
        
        function closeStockModal() {
            document.getElementById('stockModal').style.display = 'none';
        }
        
        function openDispenseModal(prescriptionId) {
            // This would open a dispensing modal - simplified for this example
            window.location.href = 'dispense-prescription.php?id=' + prescriptionId;
        }
        
        function viewMedicine(medicineId) {
            window.location.href = 'medicine-details.php?id=' + medicineId;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const medicineModal = document.getElementById('medicineModal');
            const stockModal = document.getElementById('stockModal');
            
            if (event.target === medicineModal) {
                closeMedicineModal();
            }
            if (event.target === stockModal) {
                closeStockModal();
            }
        }
    </script>
    
    <!-- Theme Toggle -->
    <div class="theme-toggle">
        <div class="theme-option" data-theme="light" onclick="setTheme('light')" title="Light Theme"></div>
        <div class="theme-option" data-theme="dark" onclick="setTheme('dark')" title="Dark Theme"></div>
        <div class="theme-option" data-theme="medical" onclick="setTheme('medical')" title="Medical Theme"></div>
    </div>
    
    <script>
        // Theme Management
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            updateThemeToggle(theme);
        }
        
        function updateThemeToggle(activeTheme) {
            document.querySelectorAll('.theme-option').forEach(option => {
                option.classList.remove('active');
                if (option.dataset.theme === activeTheme) {
                    option.classList.add('active');
                }
            });
        }
        
        // Initialize theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            setTheme(savedTheme);
        });
    </script>
</body>
</html>

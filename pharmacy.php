<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'receptionist', 'pharmacist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';
$message_type = '';

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_medicine':
            try {
                if (empty($_POST['medicine_name'])) {
                    throw new Exception("Medicine name is required!");
                }
                
                $db->query(
                    "INSERT INTO pharmacy (medicine_name, generic_name, category, manufacturer, unit_price, stock_quantity, minimum_stock, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        sanitizeInput($_POST['medicine_name']),
                        sanitizeInput($_POST['generic_name'] ?? ''),
                        sanitizeInput($_POST['category'] ?? ''),
                        sanitizeInput($_POST['manufacturer'] ?? ''),
                        floatval($_POST['unit_price'] ?? 0),
                        intval($_POST['stock_quantity'] ?? 0),
                        intval($_POST['minimum_stock'] ?? 10),
                        sanitizeInput($_POST['description'] ?? '')
                    ]
                );
                
                $message = "Medicine added successfully!";
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = $e->getMessage();
                $message_type = 'error';
            }
            break;
    }
}

// Get medicines list
$search = $_GET['search'] ?? '';
$medicines_query = "SELECT * FROM pharmacy WHERE 1=1";
$params = [];

if (!empty($search)) {
    $medicines_query .= " AND (medicine_name LIKE ? OR generic_name LIKE ? OR category LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

$medicines_query .= " ORDER BY medicine_name";
$medicines = $db->query($medicines_query, $params)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management - Hospital CRM</title>
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
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="pharmacy.php" class="active"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <div>
                    <h1><i class="fas fa-pills"></i> Pharmacy Management</h1>
                    <p>Manage medicine inventory and sales</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (in_array($user_role, ['admin', 'pharmacist'])): ?>
            <!-- Add Medicine Form -->
            <div class="card">
                <h3><i class="fas fa-plus"></i> Add New Medicine</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add_medicine">
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label for="medicine_name">Medicine Name *</label>
                            <input type="text" id="medicine_name" name="medicine_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="generic_name">Generic Name</label>
                            <input type="text" id="generic_name" name="generic_name" class="form-control">
                        </div>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="manufacturer">Manufacturer</label>
                            <input type="text" id="manufacturer" name="manufacturer" class="form-control">
                        </div>
                    </div>
                    
                    <div class="grid grid-3">
                        <div class="form-group">
                            <label for="unit_price">Unit Price (₹)</label>
                            <input type="number" id="unit_price" name="unit_price" class="form-control" min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label for="stock_quantity">Stock Quantity</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" min="0">
                        </div>
                        <div class="form-group">
                            <label for="minimum_stock">Minimum Stock</label>
                            <input type="number" id="minimum_stock" name="minimum_stock" class="form-control" min="0" value="10">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Medicine
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- Medicines List -->
            <div class="card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3><i class="fas fa-list"></i> Medicine Inventory (<?php echo count($medicines); ?>)</h3>
                    
                    <form method="GET" class="d-flex" style="gap: 10px;">
                        <input type="text" name="search" placeholder="Search medicines..." class="form-control" 
                               value="<?php echo htmlspecialchars($search); ?>" style="width: 300px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($search): ?>
                            <a href="pharmacy.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if (empty($medicines)): ?>
                    <p class="text-muted text-center">No medicines found.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Medicine Name</th>
                                    <th>Generic Name</th>
                                    <th>Category</th>
                                    <th>Manufacturer</th>
                                    <th>Unit Price</th>
                                    <th>Stock</th>
                                    <th>Min Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicines as $medicine): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($medicine['medicine_name']); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['generic_name']); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['category']); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['manufacturer']); ?></td>
                                        <td><?php echo formatCurrency($medicine['unit_price']); ?></td>
                                        <td>
                                            <span class="<?php echo $medicine['stock_quantity'] <= $medicine['minimum_stock'] ? 'text-danger' : ''; ?>">
                                                <?php echo $medicine['stock_quantity']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $medicine['minimum_stock']; ?></td>
                                        <td>
                                            <?php if ($medicine['stock_quantity'] <= $medicine['minimum_stock']): ?>
                                                <span class="badge badge-danger">Low Stock</span>
                                            <?php elseif ($medicine['stock_quantity'] > $medicine['minimum_stock'] * 2): ?>
                                                <span class="badge badge-success">In Stock</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Medium Stock</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'receptionist', 'pharmacy_staff', 'intern_pharmacy'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';
$error_message = '';

// Handle form submission for new medicine
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_medicine') {
    try {
        // Basic validation
        if (empty($_POST['medicine_name'])) {
            $error_message = "Medicine name is required!";
        } else {
            // Insert medicine
            $db->query(
                "INSERT INTO pharmacy (medicine_name, generic_name, category, manufacturer, batch_number, expiry_date, unit_price, stock_quantity, min_stock_level, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $_POST['medicine_name'],
                    $_POST['generic_name'] ?? '',
                    $_POST['category'] ?? '',
                    $_POST['manufacturer'] ?? '',
                    $_POST['batch_number'] ?? '',
                    $_POST['expiry_date'] ?? null,
                    $_POST['unit_price'] ?? 0,
                    $_POST['stock_quantity'] ?? 0,
                    $_POST['min_stock_level'] ?? 10,
                    $_POST['description'] ?? '',
                    $_SESSION['user_id']
                ]
            );
            
            $message = "Medicine added successfully!";
            
            // Clear form data
            $_POST = [];
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle stock update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    try {
        $db->query(
            "UPDATE pharmacy SET stock_quantity = ? WHERE id = ?",
            [$_POST['new_stock'], $_POST['medicine_id']]
        );
        $message = "Stock updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get medicines list
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$medicines_query = "SELECT * FROM pharmacy WHERE 1=1";
$count_query = "SELECT COUNT(*) as count FROM pharmacy WHERE 1=1";
$params = [];

if (!empty($search)) {
    $search_condition = " AND (medicine_name LIKE ? OR generic_name LIKE ? OR manufacturer LIKE ?)";
    $medicines_query .= $search_condition;
    $count_query .= $search_condition;
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($category_filter)) {
    $medicines_query .= " AND category = ?";
    $count_query .= " AND category = ?";
    $params[] = $category_filter;
}

if ($stock_filter === 'low') {
    $medicines_query .= " AND stock_quantity <= min_stock_level";
    $count_query .= " AND stock_quantity <= min_stock_level";
} elseif ($stock_filter === 'out') {
    $medicines_query .= " AND stock_quantity = 0";
    $count_query .= " AND stock_quantity = 0";
}

$total_medicines = $db->query($count_query, $params)->fetch()['count'];
$total_pages = ceil($total_medicines / $limit);

$medicines_query .= " ORDER BY medicine_name ASC LIMIT $limit OFFSET $offset";
$medicines = $db->query($medicines_query, $params)->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT DISTINCT category FROM pharmacy WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll();
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
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="blood-bank.php"><i class="fas fa-tint"></i> Blood Bank</a></li>
                <li><a href="organ-donation.php"><i class="fas fa-heart"></i> Organ Donation</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-pills"></i> Pharmacy Management</h1>
                    <p>Manage medicines and inventory</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (in_array($user_role, ['admin', 'pharmacy_staff'])): ?>
            <!-- Add New Medicine -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plus"></i> Add New Medicine</h3>
                </div>
                <div class="card-body">
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
                        
                        <div class="grid grid-3">
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category" class="form-control">
                                    <option value="">Select Category</option>
                                    <option value="Analgesic">Analgesic</option>
                                    <option value="Antibiotic">Antibiotic</option>
                                    <option value="Antiviral">Antiviral</option>
                                    <option value="Antifungal">Antifungal</option>
                                    <option value="Antihistamine">Antihistamine</option>
                                    <option value="Anti-inflammatory">Anti-inflammatory</option>
                                    <option value="Cardiovascular">Cardiovascular</option>
                                    <option value="Diabetes">Diabetes</option>
                                    <option value="Respiratory">Respiratory</option>
                                    <option value="Gastrointestinal">Gastrointestinal</option>
                                    <option value="Neurological">Neurological</option>
                                    <option value="Vitamin">Vitamin</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="manufacturer">Manufacturer</label>
                                <input type="text" id="manufacturer" name="manufacturer" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="batch_number">Batch Number</label>
                                <input type="text" id="batch_number" name="batch_number" class="form-control">
                            </div>
                        </div>
                        
                        <div class="grid grid-4">
                            <div class="form-group">
                                <label for="unit_price">Unit Price (₹)</label>
                                <input type="number" id="unit_price" name="unit_price" class="form-control" min="0" step="0.01">
                            </div>
                            <div class="form-group">
                                <label for="stock_quantity">Stock Quantity</label>
                                <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" min="0">
                            </div>
                            <div class="form-group">
                                <label for="min_stock_level">Min Stock Level</label>
                                <input type="number" id="min_stock_level" name="min_stock_level" class="form-control" min="0" value="10">
                            </div>
                            <div class="form-group">
                                <label for="expiry_date">Expiry Date</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control">
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
            </div>
            <?php endif; ?>

            <!-- Medicines List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Medicines Inventory (<?php echo $total_medicines; ?>)</h3>
                </div>
                <div class="card-body">
                    <!-- Search and Filters -->
                    <div class="search-box">
                        <form method="GET">
                            <div class="grid grid-4">
                                <div class="form-group">
                                    <input type="text" name="search" placeholder="Search medicines..." class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group">
                                    <select name="category" class="form-control">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                                    <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['category']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <select name="stock" class="form-control">
                                        <option value="">All Stock</option>
                                        <option value="low" <?php echo $stock_filter == 'low' ? 'selected' : ''; ?>>Low Stock</option>
                                        <option value="out" <?php echo $stock_filter == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="pharmacy.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
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
                                        <th>Status</th>
                                        <th>Expiry</th>
                                        <?php if (in_array($user_role, ['admin', 'pharmacy_staff'])): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
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
                                                <?php 
                                                $stock_status = '';
                                                if ($medicine['stock_quantity'] == 0) {
                                                    $stock_status = 'danger';
                                                } elseif ($medicine['stock_quantity'] <= $medicine['min_stock_level']) {
                                                    $stock_status = 'warning';
                                                } else {
                                                    $stock_status = 'success';
                                                }
                                                ?>
                                                <span class="badge badge-<?php echo $stock_status; ?>">
                                                    <?php echo $medicine['stock_quantity']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($medicine['stock_quantity'] == 0): ?>
                                                    <span class="badge badge-danger">Out of Stock</span>
                                                <?php elseif ($medicine['stock_quantity'] <= $medicine['min_stock_level']): ?>
                                                    <span class="badge badge-warning">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($medicine['expiry_date']): ?>
                                                    <?php 
                                                    $expiry_date = new DateTime($medicine['expiry_date']);
                                                    $today = new DateTime();
                                                    $diff = $today->diff($expiry_date);
                                                    
                                                    if ($expiry_date < $today): ?>
                                                        <span class="badge badge-danger">Expired</span>
                                                    <?php elseif ($diff->days <= 30): ?>
                                                        <span class="badge badge-warning">Expires Soon</span>
                                                    <?php else: ?>
                                                        <?php echo date('d M Y', strtotime($medicine['expiry_date'])); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <?php if (in_array($user_role, ['admin', 'pharmacy_staff'])): ?>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_stock">
                                                        <input type="hidden" name="medicine_id" value="<?php echo $medicine['id']; ?>">
                                                        <div style="display: flex; gap: 5px;">
                                                            <input type="number" name="new_stock" value="<?php echo $medicine['stock_quantity']; ?>" 
                                                                   style="width: 70px; padding: 2px; font-size: 12px;" min="0">
                                                            <button type="submit" class="btn btn-primary" style="padding: 2px 8px; font-size: 12px;">
                                                                <i class="fas fa-save"></i>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="text-center mt-3">
                                <?php 
                                $query_params = [];
                                if ($search) $query_params[] = 'search=' . urlencode($search);
                                if ($category_filter) $query_params[] = 'category=' . urlencode($category_filter);
                                if ($stock_filter) $query_params[] = 'stock=' . urlencode($stock_filter);
                                $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
                                ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i . $query_string; ?>" 
                                       class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>" 
                                       style="margin: 0 2px; padding: 5px 10px; font-size: 12px;">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-focus on medicine name field
        document.getElementById('medicine_name')?.focus();
    </script>
</body>
</html>
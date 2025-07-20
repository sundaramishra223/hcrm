<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'pharmacy_staff'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle category deletion
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    try {
        $category_id = $_POST['category_id'];
        
        // Check if category is being used
        $usage_count = $db->query(
            "SELECT COUNT(*) as count FROM medicines m 
             JOIN medicine_categories mc ON m.category = mc.name 
             WHERE mc.id = ? AND m.hospital_id = 1",
            [$category_id]
        )->fetch()['count'];
        
        if ($usage_count > 0) {
            $message = "Cannot delete category - it's being used by $usage_count medicine(s).";
        } else {
            $db->query("DELETE FROM medicine_categories WHERE id = ? AND hospital_id = 1", [$category_id]);
            $message = "Category deleted successfully!";
        }
    } catch (Exception $e) {
        $message = "Error deleting category: " . $e->getMessage();
    }
}

// Handle category status toggle
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    try {
        $category_id = $_POST['category_id'];
        $new_status = $_POST['new_status'];
        
        $db->query("UPDATE medicine_categories SET is_active = ? WHERE id = ? AND hospital_id = 1", [$new_status, $category_id]);
        $status_text = $new_status ? 'activated' : 'deactivated';
        $message = "Category $status_text successfully!";
    } catch (Exception $e) {
        $message = "Error updating category: " . $e->getMessage();
    }
}

// Get all categories
$categories = $db->query(
    "SELECT mc.*, 
     COUNT(m.id) as medicine_count
     FROM medicine_categories mc
     LEFT JOIN medicines m ON m.category = mc.name AND m.hospital_id = 1 AND m.is_active = 1
     WHERE mc.hospital_id = 1
     GROUP BY mc.id
     ORDER BY mc.name"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Hospital CRM</title>
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
            max-width: 1000px;
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
            transition: all 0.3s;
        }
        
        .btn-primary { background: #004685; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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
        
        .categories-table {
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
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
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
        
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏷️ Manage Medicine Categories</h1>
            <div>
                <a href="pharmacy.php" class="btn btn-secondary">← Back to Pharmacy</a>
                <a href="pharmacy.php" class="btn btn-success">+ Add New Category</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') === 0 || strpos($message, 'Cannot') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="categories-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Medicines Count</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                No categories found. <a href="pharmacy.php">Add your first category</a>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($category['description'] ?: 'No description'); ?>
                                </td>
                                <td>
                                    <span class="badge" style="background: #e7f3ff; color: #004085;">
                                        <?php echo $category['medicine_count']; ?> medicines
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $category['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($category['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $category['is_active'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">
                                                <?php echo $category['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <?php if ($category['medicine_count'] == 0): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?')">
                                                <input type="hidden" name="action" value="delete_category">
                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; text-align: center; color: #666;">
            <small>💡 Categories with medicines cannot be deleted. Deactivate them instead.</small>
        </div>
    </div>
</body>
</html>
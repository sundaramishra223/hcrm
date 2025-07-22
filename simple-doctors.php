<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'doctor', 'intern_doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle form submission for new doctor
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_doctor') {
    try {
        // Basic validation
        if (empty($_POST['doctor_name']) || empty($_POST['email'])) {
            $message = "❌ Doctor name and email are required!";
        } else {
            // Check if email already exists
            $existing_user = $db->query("SELECT id FROM users WHERE email = ?", [$_POST['email']])->fetch();
            if ($existing_user) {
                $message = "❌ Email already exists! Please use a different email.";
            } else {
                // Insert doctor
                $db->query(
                    "INSERT INTO doctors (doctor_name, specialization, qualification, phone, email, consultation_fee, experience_years) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $_POST['doctor_name'],
                        $_POST['specialization'] ?? '',
                        $_POST['qualification'] ?? '',
                        $_POST['phone'] ?? '',
                        $_POST['email'],
                        $_POST['consultation_fee'] ?? 0,
                        $_POST['experience_years'] ?? 0
                    ]
                );
                
                // Create user account if password provided
                if (!empty($_POST['password'])) {
                    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $db->query(
                        "INSERT INTO users (username, email, password_hash, role_id, first_name, last_name) VALUES (?, ?, ?, 2, ?, ?)",
                        [$_POST['email'], $_POST['email'], $password_hash, $_POST['doctor_name'], '']
                    );
                }
                
                $message = "✅ Doctor added successfully!";
            }
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Get doctors list
$search = $_GET['search'] ?? '';
$doctors_query = "SELECT * FROM doctors WHERE 1=1";
$params = [];

if (!empty($search)) {
    $doctors_query .= " AND (doctor_name LIKE ? OR specialization LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

$doctors_query .= " ORDER BY created_at DESC";
$doctors = $db->query($doctors_query, $params)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors Management - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .message { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .search-box { margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-md"></i> Doctors Management</h1>
            <p>Manage doctor records and information</p>
            <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (in_array($user_role, ['admin'])): ?>
        <div class="card">
            <h2><i class="fas fa-user-plus"></i> Add New Doctor</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_doctor">
                
                <div class="grid">
                    <div class="form-group">
                        <label for="doctor_name">Doctor Name *</label>
                        <input type="text" id="doctor_name" name="doctor_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="specialization">Specialization</label>
                        <input type="text" id="specialization" name="specialization" class="form-control">
                    </div>
                </div>
                
                <div class="grid">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" class="form-control">
                    </div>
                </div>
                
                <div class="grid">
                    <div class="form-group">
                        <label for="qualification">Qualification</label>
                        <input type="text" id="qualification" name="qualification" class="form-control" placeholder="MBBS, MD, etc.">
                    </div>
                    <div class="form-group">
                        <label for="experience_years">Experience (Years)</label>
                        <input type="number" id="experience_years" name="experience_years" class="form-control" min="0">
                    </div>
                </div>
                
                <div class="grid">
                    <div class="form-group">
                        <label for="consultation_fee">Consultation Fee (₹)</label>
                        <input type="number" id="consultation_fee" name="consultation_fee" class="form-control" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="password">Login Password (Optional)</label>
                        <input type="password" id="password" name="password" class="form-control">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Doctor
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="search-box">
                <form method="GET">
                    <div class="grid">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search doctors..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if ($search): ?>
                                <a href="simple-doctors.php" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <h2><i class="fas fa-list"></i> Doctors List (<?php echo count($doctors); ?>)</h2>
            
            <?php if (empty($doctors)): ?>
                <p>No doctors found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Specialization</th>
                                <th>Qualification</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Experience</th>
                                <th>Fee</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doctor['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['qualification']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['phone']); ?></td>
                                    <td><?php echo $doctor['experience_years']; ?> years</td>
                                    <td>₹<?php echo number_format($doctor['consultation_fee'], 2); ?></td>
                                    <td>
                                        <span style="color: <?php echo $doctor['is_active'] ? 'green' : 'red'; ?>">
                                            <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
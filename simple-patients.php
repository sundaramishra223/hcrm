<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle form submission for new patient
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_patient') {
    try {
        // Basic validation
        if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email'])) {
            $message = "❌ First name, last name, and email are required!";
        } else {
            // Check if email already exists
            $existing_user = $db->query("SELECT id FROM users WHERE email = ?", [$_POST['email']])->fetch();
            if ($existing_user) {
                $message = "❌ Email already exists! Please use a different email.";
            } else {
                // Generate patient ID
                $patient_count = $db->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'];
                $patient_id = 'PAT' . str_pad($patient_count + 1, 4, '0', STR_PAD_LEFT);
                
                // Insert patient
                $db->query(
                    "INSERT INTO patients (patient_id, first_name, last_name, email, phone, address, date_of_birth, gender, blood_group, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $patient_id,
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $_POST['phone'] ?? '',
                        $_POST['address'] ?? '',
                        $_POST['date_of_birth'] ?? null,
                        $_POST['gender'] ?? 'male',
                        $_POST['blood_group'] ?? '',
                        $_POST['emergency_contact_name'] ?? '',
                        $_POST['emergency_contact_phone'] ?? ''
                    ]
                );
                
                // Create user account if password provided
                if (!empty($_POST['password'])) {
                    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $db->query(
                        "INSERT INTO users (username, email, password_hash, role_id, first_name, last_name) VALUES (?, ?, ?, 4, ?, ?)",
                        [$_POST['email'], $_POST['email'], $password_hash, $_POST['first_name'], $_POST['last_name']]
                    );
                }
                
                $message = "✅ Patient added successfully! Patient ID: $patient_id";
            }
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Get patients list
$search = $_GET['search'] ?? '';
$patients_query = "SELECT * FROM patients WHERE 1=1";
$params = [];

if (!empty($search)) {
    $patients_query .= " AND (first_name LIKE ? OR last_name LIKE ? OR patient_id LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

$patients_query .= " ORDER BY created_at DESC";
$patients = $db->query($patients_query, $params)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients Management - Hospital CRM</title>
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
            <h1><i class="fas fa-users"></i> Patients Management</h1>
            <p>Manage patient records and information</p>
            <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fas fa-user-plus"></i> Add New Patient</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_patient">
                
                <div class="grid">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required>
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
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" class="form-control">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid">
                    <div class="form-group">
                        <label for="blood_group">Blood Group</label>
                        <select id="blood_group" name="blood_group" class="form-control">
                            <option value="">Select Blood Group</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="password">Login Password (Optional)</label>
                        <input type="password" id="password" name="password" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="grid">
                    <div class="form-group">
                        <label for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="emergency_contact_phone">Emergency Contact Phone</label>
                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Patient
                </button>
            </form>
        </div>

        <div class="card">
            <div class="search-box">
                <form method="GET">
                    <div class="grid">
                        <div class="form-group">
                            <input type="text" name="search" placeholder="Search patients..." class="form-control" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if ($search): ?>
                                <a href="simple-patients.php" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <h2><i class="fas fa-list"></i> Patients List (<?php echo count($patients); ?>)</h2>
            
            <?php if (empty($patients)): ?>
                <p>No patients found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Blood Group</th>
                                <th>Gender</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($patient['blood_group']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($patient['gender'])); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($patient['created_at'])); ?></td>
                                    <td>
                                        <a href="patient-details.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
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
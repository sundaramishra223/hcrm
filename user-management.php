<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle user creation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    try {
        $db->getConnection()->beginTransaction();
        
        $user_type = $_POST['user_type'];
        $username = strtolower(str_replace(' ', '.', $_POST['first_name'] . '.' . $_POST['last_name']));
        $email = $_POST['email'];
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Role mapping for all user types
        $role_map = [
            'admin' => 1,
            'doctor' => 2,
            'nurse' => 3,
            'patient' => 4,
            'receptionist' => 5,
            'lab_technician' => 6,
            'pharmacy_staff' => 7,
            'intern_doctor' => 8,
            'intern_nurse' => 9,
            'intern_lab' => 10,
            'intern_pharmacy' => 11
        ];
        
        $role_id = $role_map[$user_type];
        
        // Create user account
        $user_sql = "INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, ?)";
        $db->query($user_sql, [$username, $email, $password_hash, $role_id]);
        $user_id = $db->lastInsertId();
        
        // Handle different user types
        if ($user_type === 'doctor' || $user_type === 'intern_doctor') {
            // Add doctor
            $doctor_count = $db->query("SELECT COUNT(*) as count FROM doctors WHERE hospital_id = 1")->fetch()['count'];
            $employee_id = 'DOC' . str_pad($doctor_count + 1, 3, '0', STR_PAD_LEFT);
            
            $doctor_sql = "INSERT INTO doctors (user_id, hospital_id, department_id, employee_id, first_name, middle_name, last_name, specialization, qualification, experience_years, registration_number, phone, emergency_contact, address, date_of_birth, gender, blood_group, consultation_fee, joined_date, is_intern) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $db->query($doctor_sql, [
                $user_id,
                $_POST['department_id'] ?? null,
                $employee_id,
                $_POST['first_name'],
                $_POST['middle_name'],
                $_POST['last_name'],
                $_POST['specialization'] ?? '',
                $_POST['qualification'] ?? '',
                $_POST['experience_years'] ?? 0,
                $_POST['registration_number'] ?? '',
                $_POST['phone'],
                $_POST['emergency_contact'],
                $_POST['address'],
                $_POST['date_of_birth'],
                $_POST['gender'],
                $_POST['blood_group'],
                $_POST['consultation_fee'] ?? 0,
                $_POST['joined_date'],
                $user_type === 'intern_doctor' ? 1 : 0
            ]);
            
            $message = "Doctor added successfully! Employee ID: " . $employee_id . ", Username: " . $username;
            
        } elseif ($user_type === 'patient') {
            // Add patient
            $stmt = $db->query("CALL GetNextPatientId(1, @next_id)");
            $result = $db->query("SELECT @next_id as patient_id")->fetch();
            $patient_id = $result['patient_id'];
            
            $patient_sql = "INSERT INTO patients (user_id, hospital_id, patient_id, first_name, middle_name, last_name, phone, emergency_contact, email, address, date_of_birth, gender, blood_group, marital_status, occupation, medical_history, allergies) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $db->query($patient_sql, [
                $user_id,
                $patient_id,
                $_POST['first_name'],
                $_POST['middle_name'],
                $_POST['last_name'],
                $_POST['phone'],
                $_POST['emergency_contact'],
                $_POST['email'],
                $_POST['address'],
                $_POST['date_of_birth'],
                $_POST['gender'],
                $_POST['blood_group'],
                $_POST['marital_status'] ?? 'single',
                $_POST['occupation'] ?? '',
                $_POST['medical_history'] ?? '',
                $_POST['allergies'] ?? ''
            ]);
            
            $message = "Patient added successfully! Patient ID: " . $patient_id . ", Username: " . $username;
            
        } else {
            // Add staff member
            $staff_count = $db->query("SELECT COUNT(*) as count FROM staff WHERE hospital_id = 1")->fetch()['count'];
            $employee_id = strtoupper(substr($user_type, 0, 3)) . str_pad($staff_count + 1, 3, '0', STR_PAD_LEFT);
            
            $staff_sql = "INSERT INTO staff (user_id, hospital_id, department_id, employee_id, first_name, middle_name, last_name, staff_type, phone, emergency_contact, address, date_of_birth, gender, blood_group, date_of_joining, salary, qualification, is_intern) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $db->query($staff_sql, [
                $user_id,
                $_POST['department_id'] ?? null,
                $employee_id,
                $_POST['first_name'],
                $_POST['middle_name'],
                $_POST['last_name'],
                $user_type,
                $_POST['phone'],
                $_POST['emergency_contact'],
                $_POST['address'],
                $_POST['date_of_birth'],
                $_POST['gender'],
                $_POST['blood_group'],
                $_POST['joined_date'],
                $_POST['salary'] ?? 0,
                $_POST['qualification'] ?? '',
                in_array($user_type, ['intern_nurse', 'intern_lab', 'intern_pharmacy']) ? 1 : 0
            ]);
            
            $message = "Staff member added successfully! Employee ID: " . $employee_id . ", Username: " . $username;
        }
        
        $db->getConnection()->commit();
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Get departments for dropdown
$departments = $db->query("SELECT id, name FROM departments WHERE hospital_id = 1 AND is_active = 1")->fetchAll();

// Get all users with their details
$search = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? '';

$sql = "SELECT u.*, r.role_name, r.role_display_name,
        CASE 
            WHEN d.id IS NOT NULL THEN CONCAT(d.first_name, ' ', d.last_name)
            WHEN s.id IS NOT NULL THEN CONCAT(s.first_name, ' ', s.last_name)
            WHEN p.id IS NOT NULL THEN CONCAT(p.first_name, ' ', p.last_name)
        END as full_name,
        CASE 
            WHEN d.id IS NOT NULL THEN d.employee_id
            WHEN s.id IS NOT NULL THEN s.employee_id
            WHEN p.id IS NOT NULL THEN p.patient_id
        END as identifier
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN doctors d ON u.id = d.user_id
        LEFT JOIN staff s ON u.id = s.user_id
        LEFT JOIN patients p ON u.id = p.user_id
        WHERE u.id != 1"; // Exclude current admin

$params = [];

if ($search) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR d.first_name LIKE ? OR s.first_name LIKE ? OR p.first_name LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param, $search_param];
}

if ($filter_type) {
    $sql .= " AND r.role_name = ?";
    $params[] = $filter_type;
}

$sql .= " ORDER BY u.created_at DESC";

$users = $db->query($sql, $params)->fetchAll();

// Get user statistics
$stats = [];
try {
    $stats['total_users'] = $db->query("SELECT COUNT(*) as count FROM users WHERE id != 1")->fetch()['count'];
    $stats['active_users'] = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND id != 1")->fetch()['count'];
    $stats['doctors'] = $db->query("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name IN ('doctor', 'intern_doctor')")->fetch()['count'];
    $stats['staff'] = $db->query("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name IN ('nurse', 'receptionist', 'lab_technician', 'pharmacy_staff', 'intern_nurse', 'intern_lab', 'intern_pharmacy')")->fetch()['count'];
    $stats['patients'] = $db->query("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'patient'")->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_users' => 0, 'active_users' => 0, 'doctors' => 0, 'staff' => 0, 'patients' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Hospital CRM</title>
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
            max-width: 1400px;
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
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
            color: #004685;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
        }
        
        .search-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-form input, .search-form select {
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .search-form input {
            flex: 1;
            min-width: 200px;
        }
        
        .users-table {
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
        }
        
        .table tr:hover {
            background: #f8f9fa;
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
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #004685;
        }
        
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .role-admin { background: #dc3545; color: white; }
        .role-doctor { background: #007bff; color: white; }
        .role-nurse { background: #28a745; color: white; }
        .role-patient { background: #6c757d; color: white; }
        .role-receptionist { background: #ffc107; color: black; }
        .role-lab_technician { background: #17a2b8; color: white; }
        .role-pharmacy_staff { background: #6f42c1; color: white; }
        .role-intern_doctor { background: #fd7e14; color: white; }
        .role-intern_nurse { background: #20c997; color: white; }
        .role-intern_lab { background: #e83e8c; color: white; }
        .role-intern_pharmacy { background: #6f42c1; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-users"></i> User Management</h1>
            <button class="btn btn-primary" onclick="openModal()">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_users']); ?></h3>
                <p>Total Users</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['active_users']); ?></h3>
                <p>Active Users</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['doctors']); ?></h3>
                <p>Doctors</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['staff']); ?></h3>
                <p>Staff Members</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['patients']); ?></h3>
                <p>Patients</p>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form class="search-form" method="GET">
                <input type="text" name="search" placeholder="Search by name, email, or ID..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <select name="type">
                    <option value="">All Types</option>
                    <option value="admin" <?php echo $filter_type === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="doctor" <?php echo $filter_type === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                    <option value="nurse" <?php echo $filter_type === 'nurse' ? 'selected' : ''; ?>>Nurse</option>
                    <option value="patient" <?php echo $filter_type === 'patient' ? 'selected' : ''; ?>>Patient</option>
                    <option value="receptionist" <?php echo $filter_type === 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                    <option value="lab_technician" <?php echo $filter_type === 'lab_technician' ? 'selected' : ''; ?>>Lab Technician</option>
                    <option value="pharmacy_staff" <?php echo $filter_type === 'pharmacy_staff' ? 'selected' : ''; ?>>Pharmacy Staff</option>
                    <option value="intern_doctor" <?php echo $filter_type === 'intern_doctor' ? 'selected' : ''; ?>>Intern Doctor</option>
                    <option value="intern_nurse" <?php echo $filter_type === 'intern_nurse' ? 'selected' : ''; ?>>Intern Nurse</option>
                    <option value="intern_lab" <?php echo $filter_type === 'intern_lab' ? 'selected' : ''; ?>>Intern Lab</option>
                    <option value="intern_pharmacy" <?php echo $filter_type === 'intern_pharmacy' ? 'selected' : ''; ?>>Intern Pharmacy</option>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="user-management.php" class="btn btn-secondary">Clear</a>
            </form>
        </div>

        <!-- Users Table -->
        <div class="users-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last Login</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></strong>
                                <br><small><?php echo htmlspecialchars($user['username']); ?></small>
                            </td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role_name']; ?>">
                                    <?php echo htmlspecialchars($user['role_display_name']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['identifier'] ?? 'N/A'); ?></td>
                            <td>
                                <span style="color: <?php echo $user['is_active'] ? '#28a745' : '#dc3545'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New User</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="user_type">User Type *</label>
                        <select name="user_type" id="user_type" required onchange="toggleFields()">
                            <option value="">Select User Type</option>
                            <option value="admin">Admin</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="patient">Patient</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="lab_technician">Lab Technician</option>
                            <option value="pharmacy_staff">Pharmacy Staff</option>
                            <option value="intern_doctor">Intern Doctor</option>
                            <option value="intern_nurse">Intern Nurse</option>
                            <option value="intern_lab">Intern Lab Tech</option>
                            <option value="intern_pharmacy">Intern Pharmacy</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" name="middle_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" name="last_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone *</label>
                        <input type="tel" name="phone" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_contact">Emergency Contact</label>
                        <input type="tel" name="emergency_contact">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" name="date_of_birth">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select name="gender">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="blood_group">Blood Group</label>
                        <select name="blood_group">
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
                        <label for="address">Address</label>
                        <textarea name="address" rows="3"></textarea>
                    </div>
                    
                    <!-- Doctor specific fields -->
                    <div class="form-group doctor-field" style="display: none;">
                        <label for="department_id">Department</label>
                        <select name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group doctor-field" style="display: none;">
                        <label for="specialization">Specialization</label>
                        <input type="text" name="specialization">
                    </div>
                    
                    <div class="form-group doctor-field" style="display: none;">
                        <label for="qualification">Qualification</label>
                        <input type="text" name="qualification">
                    </div>
                    
                    <div class="form-group doctor-field" style="display: none;">
                        <label for="experience_years">Experience (Years)</label>
                        <input type="number" name="experience_years" min="0">
                    </div>
                    
                    <div class="form-group doctor-field" style="display: none;">
                        <label for="registration_number">Registration Number</label>
                        <input type="text" name="registration_number">
                    </div>
                    
                    <div class="form-group doctor-field" style="display: none;">
                        <label for="consultation_fee">Consultation Fee</label>
                        <input type="number" name="consultation_fee" min="0" step="0.01">
                    </div>
                    
                    <!-- Staff specific fields -->
                    <div class="form-group staff-field" style="display: none;">
                        <label for="department_id_staff">Department</label>
                        <select name="department_id">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group staff-field" style="display: none;">
                        <label for="qualification_staff">Qualification</label>
                        <input type="text" name="qualification">
                    </div>
                    
                    <div class="form-group staff-field" style="display: none;">
                        <label for="salary">Salary</label>
                        <input type="number" name="salary" min="0" step="0.01">
                    </div>
                    
                    <!-- Patient specific fields -->
                    <div class="form-group patient-field" style="display: none;">
                        <label for="marital_status">Marital Status</label>
                        <select name="marital_status">
                            <option value="single">Single</option>
                            <option value="married">Married</option>
                            <option value="divorced">Divorced</option>
                            <option value="widowed">Widowed</option>
                        </select>
                    </div>
                    
                    <div class="form-group patient-field" style="display: none;">
                        <label for="occupation">Occupation</label>
                        <input type="text" name="occupation">
                    </div>
                    
                    <div class="form-group patient-field" style="display: none;">
                        <label for="medical_history">Medical History</label>
                        <textarea name="medical_history" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group patient-field" style="display: none;">
                        <label for="allergies">Allergies</label>
                        <textarea name="allergies" rows="3"></textarea>
                    </div>
                    
                    <!-- Common fields -->
                    <div class="form-group">
                        <label for="joined_date">Joined Date</label>
                        <input type="date" name="joined_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }
        
        function toggleFields() {
            const userType = document.getElementById('user_type').value;
            
            // Hide all specific fields
            document.querySelectorAll('.doctor-field, .staff-field, .patient-field').forEach(field => {
                field.style.display = 'none';
            });
            
            // Show relevant fields based on user type
            if (userType === 'doctor' || userType === 'intern_doctor') {
                document.querySelectorAll('.doctor-field').forEach(field => {
                    field.style.display = 'block';
                });
            } else if (['nurse', 'receptionist', 'lab_technician', 'pharmacy_staff', 'intern_nurse', 'intern_lab', 'intern_pharmacy'].includes(userType)) {
                document.querySelectorAll('.staff-field').forEach(field => {
                    field.style.display = 'block';
                });
            } else if (userType === 'patient') {
                document.querySelectorAll('.patient-field').forEach(field => {
                    field.style.display = 'block';
                });
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addUserModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
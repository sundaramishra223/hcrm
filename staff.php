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

// Handle staff member creation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    try {
        $db->getConnection()->beginTransaction();
        
        // Create user account first
        $username = strtolower(str_replace(' ', '.', $_POST['first_name'] . '.' . $_POST['last_name']));
        $email = $_POST['email'];
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Get role ID based on staff type
        $role_map = [
            'nurse' => 4,
            'receptionist' => 8,
            'lab_technician' => 6,
            'pharmacy_staff' => 7,
            'maintenance' => 9,
            'security' => 9,
            'accountant' => 9
        ];
        
        $role_id = $role_map[$_POST['staff_type']] ?? 9;
        
        $user_sql = "INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, ?)";
        $db->query($user_sql, [$username, $email, $password_hash, $role_id]);
        $user_id = $db->lastInsertId();
        
        // Generate employee ID
        $staff_count = $db->query("SELECT COUNT(*) as count FROM staff WHERE hospital_id = 1")->fetch()['count'];
        $employee_id = strtoupper(substr($_POST['staff_type'], 0, 3)) . str_pad($staff_count + 1, 3, '0', STR_PAD_LEFT);
        
        // Insert staff member
        $staff_sql = "INSERT INTO staff (user_id, hospital_id, employee_id, first_name, middle_name, last_name, staff_type, phone, emergency_contact, address, date_of_birth, gender, blood_group, date_of_joining, salary, qualification, shift_timing, notes) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($staff_sql, [
            $user_id,
            $employee_id,
            $_POST['first_name'],
            $_POST['middle_name'],
            $_POST['last_name'],
            $_POST['staff_type'],
            $_POST['phone'],
            $_POST['emergency_contact'],
            $_POST['address'],
            $_POST['date_of_birth'],
            $_POST['gender'],
            $_POST['blood_group'],
            $_POST['date_of_joining'],
            $_POST['salary'],
            $_POST['qualification'],
            $_POST['shift_timing'],
            $_POST['notes']
        ]);
        
        $db->getConnection()->commit();
        $message = "Staff member added successfully! Employee ID: " . $employee_id . ", Username: " . $username;
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle status toggle
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    try {
        $staff_id = $_POST['staff_id'];
        $new_status = $_POST['new_status'];
        
        $db->query("UPDATE staff SET is_active = ? WHERE id = ?", [$new_status, $staff_id]);
        $db->query("UPDATE users SET is_active = ? WHERE id = (SELECT user_id FROM staff WHERE id = ?)", [$new_status, $staff_id]);
        
        $message = "Staff status updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get staff members with search and filters
$search = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_shift = $_GET['shift'] ?? '';

$sql = "SELECT s.*, 
        CONCAT(s.first_name, ' ', s.last_name) as full_name,
        u.email, u.is_active,
        TIMESTAMPDIFF(YEAR, s.date_of_joining, CURDATE()) as years_of_service
        FROM staff s
        JOIN users u ON s.user_id = u.id
        WHERE s.hospital_id = 1";

$params = [];

if ($search) {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.employee_id LIKE ? OR s.phone LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

if ($filter_type) {
    $sql .= " AND s.staff_type = ?";
    $params[] = $filter_type;
}

if ($filter_shift) {
    $sql .= " AND s.shift_timing = ?";
    $params[] = $filter_shift;
}

$sql .= " ORDER BY s.first_name, s.last_name";

$staff_members = $db->query($sql, $params)->fetchAll();

// Get staff statistics
$stats = [];
try {
    $stats['total_staff'] = $db->query("SELECT COUNT(*) as count FROM staff WHERE hospital_id = 1")->fetch()['count'];
    $stats['active_staff'] = $db->query("SELECT COUNT(*) as count FROM staff s JOIN users u ON s.user_id = u.id WHERE s.hospital_id = 1 AND u.is_active = 1")->fetch()['count'];
    $stats['nurses'] = $db->query("SELECT COUNT(*) as count FROM staff WHERE hospital_id = 1 AND staff_type = 'nurse'")->fetch()['count'];
    $stats['receptionists'] = $db->query("SELECT COUNT(*) as count FROM staff WHERE hospital_id = 1 AND staff_type = 'receptionist'")->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_staff' => 0, 'active_staff' => 0, 'nurses' => 0, 'receptionists' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Hospital CRM</title>
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
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .staff-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .staff-card:hover {
            transform: translateY(-5px);
        }
        
        .staff-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .staff-header.nurse {
            background: linear-gradient(135deg, #e91e63, #f06292);
        }
        
        .staff-header.receptionist {
            background: linear-gradient(135deg, #ff9800, #ffb74d);
        }
        
        .staff-header.lab_technician {
            background: linear-gradient(135deg, #9c27b0, #ba68c8);
        }
        
        .staff-header.pharmacy_staff {
            background: linear-gradient(135deg, #2196f3, #64b5f6);
        }
        
        .staff-header h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .staff-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .staff-body {
            padding: 20px;
        }
        
        .staff-info {
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
            font-size: 12px;
            color: #666;
            margin-bottom: 2px;
        }
        
        .info-item span {
            font-weight: 500;
            color: #333;
        }
        
        .staff-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-inactive {
            background: #ffebee;
            color: #c62828;
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
            
            .staff-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .staff-info {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Staff Management</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button onclick="openModal()" class="btn btn-primary">+ Add New Staff</button>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_staff']); ?></h3>
                <p>Total Staff</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['active_staff']); ?></h3>
                <p>Active Staff</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['nurses']); ?></h3>
                <p>Nurses</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['receptionists']); ?></h3>
                <p>Receptionists</p>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Search Staff</label>
                    <input type="text" name="search" id="search" placeholder="Search by name, employee ID, or phone..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="type">Staff Type</label>
                    <select name="type" id="type">
                        <option value="">All Types</option>
                        <option value="nurse" <?php echo $filter_type === 'nurse' ? 'selected' : ''; ?>>Nurse</option>
                        <option value="receptionist" <?php echo $filter_type === 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                        <option value="lab_technician" <?php echo $filter_type === 'lab_technician' ? 'selected' : ''; ?>>Lab Technician</option>
                        <option value="pharmacy_staff" <?php echo $filter_type === 'pharmacy_staff' ? 'selected' : ''; ?>>Pharmacy Staff</option>
                        <option value="maintenance" <?php echo $filter_type === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="security" <?php echo $filter_type === 'security' ? 'selected' : ''; ?>>Security</option>
                        <option value="accountant" <?php echo $filter_type === 'accountant' ? 'selected' : ''; ?>>Accountant</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="shift">Shift</label>
                    <select name="shift" id="shift">
                        <option value="">All Shifts</option>
                        <option value="morning" <?php echo $filter_shift === 'morning' ? 'selected' : ''; ?>>Morning</option>
                        <option value="evening" <?php echo $filter_shift === 'evening' ? 'selected' : ''; ?>>Evening</option>
                        <option value="night" <?php echo $filter_shift === 'night' ? 'selected' : ''; ?>>Night</option>
                        <option value="full_time" <?php echo $filter_shift === 'full_time' ? 'selected' : ''; ?>>Full Time</option>
                        <option value="part_time" <?php echo $filter_shift === 'part_time' ? 'selected' : ''; ?>>Part Time</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="staff.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
        
        <div class="staff-grid">
            <?php if (empty($staff_members)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: #666;">
                    <h3>No staff members found</h3>
                    <p>Add your first staff member to get started.</p>
                </div>
            <?php else: ?>
                <?php foreach ($staff_members as $staff): ?>
                    <div class="staff-card">
                        <div class="staff-header <?php echo $staff['staff_type']; ?>">
                            <h3><?php echo htmlspecialchars($staff['full_name']); ?></h3>
                            <p><?php echo ucfirst(str_replace('_', ' ', $staff['staff_type'])); ?></p>
                        </div>
                        
                        <div class="staff-body">
                            <div class="staff-info">
                                <div class="info-item">
                                    <label>Employee ID</label>
                                    <span><?php echo htmlspecialchars($staff['employee_id']); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <label>Phone</label>
                                    <span><?php echo htmlspecialchars($staff['phone']); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <label>Shift</label>
                                    <span><?php echo ucfirst(str_replace('_', ' ', $staff['shift_timing'] ?? 'Not set')); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <label>Experience</label>
                                    <span><?php echo $staff['years_of_service']; ?> years</span>
                                </div>
                                
                                <div class="info-item">
                                    <label>Salary</label>
                                    <span>₹<?php echo number_format($staff['salary'], 0); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <label>Status</label>
                                    <span class="status-badge <?php echo $staff['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($staff['qualification']): ?>
                                <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 5px;">Qualification</label>
                                    <span style="font-size: 14px; color: #333;"><?php echo htmlspecialchars($staff['qualification']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="staff-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $staff['is_active'] ? '0' : '1'; ?>">
                                    <button type="submit" class="btn <?php echo $staff['is_active'] ? 'btn-danger' : 'btn-success'; ?> btn-sm">
                                        <?php echo $staff['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <a href="staff-details.php?id=<?php echo $staff['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Staff Modal -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Staff Member</h2>
                <button type="button" class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_staff">
                    
                    <h3 style="color: #004685; margin-bottom: 15px;">Personal Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label for="emergency_contact">Emergency Contact</label>
                            <input type="tel" id="emergency_contact" name="emergency_contact">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth">
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"></textarea>
                    </div>
                    
                    <h3 style="color: #004685; margin: 20px 0 15px;">Employment Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="staff_type">Staff Type *</label>
                            <select id="staff_type" name="staff_type" required>
                                <option value="">Select Staff Type</option>
                                <option value="nurse">Nurse</option>
                                <option value="receptionist">Receptionist</option>
                                <option value="lab_technician">Lab Technician</option>
                                <option value="pharmacy_staff">Pharmacy Staff</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="security">Security</option>
                                <option value="accountant">Accountant</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="shift_timing">Shift Timing *</label>
                            <select id="shift_timing" name="shift_timing" required>
                                <option value="">Select Shift</option>
                                <option value="morning">Morning (6 AM - 2 PM)</option>
                                <option value="evening">Evening (2 PM - 10 PM)</option>
                                <option value="night">Night (10 PM - 6 AM)</option>
                                <option value="full_time">Full Time (9 AM - 6 PM)</option>
                                <option value="part_time">Part Time</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="qualification">Qualification *</label>
                        <textarea id="qualification" name="qualification" placeholder="Educational qualifications and certifications..." required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="salary">Monthly Salary (₹) *</label>
                            <input type="number" id="salary" name="salary" min="0" step="100" required>
                        </div>
                        <div class="form-group">
                            <label for="blood_group">Blood Group</label>
                            <select id="blood_group" name="blood_group">
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
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_joining">Date of Joining *</label>
                        <input type="date" id="date_of_joining" name="date_of_joining" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Any additional information..."></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 30px;">
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Staff Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('staffModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('staffModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('staffModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>

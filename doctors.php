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

// Handle form submission for new doctor
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_doctor') {
    try {
        // Start transaction
        $db->getConnection()->beginTransaction();
        
        // Create user account first
        $username = strtolower(str_replace(' ', '.', $_POST['first_name'] . '.' . $_POST['last_name']));
        $email = $_POST['email'];
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $user_sql = "INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, 2)";
        $db->query($user_sql, [$username, $email, $password_hash]);
        $user_id = $db->lastInsertId();
        
        // Generate employee ID
        $employee_count = $db->query("SELECT COUNT(*) as count FROM doctors WHERE hospital_id = 1")->fetch()['count'];
        $employee_id = 'DOC' . str_pad($employee_count + 1, 3, '0', STR_PAD_LEFT);
        
        // Insert doctor
        $doctor_sql = "INSERT INTO doctors (user_id, hospital_id, department_id, employee_id, first_name, middle_name, last_name, specialization, qualification, experience_years, registration_number, phone, emergency_contact, address, date_of_birth, gender, blood_group, consultation_fee, joined_date) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($doctor_sql, [
            $user_id,
            $_POST['department_id'] ?: null,
            $employee_id,
            $_POST['first_name'],
            $_POST['middle_name'],
            $_POST['last_name'],
            $_POST['specialization'],
            $_POST['qualification'],
            $_POST['experience_years'],
            $_POST['registration_number'],
            $_POST['phone'],
            $_POST['emergency_contact'],
            $_POST['address'],
            $_POST['date_of_birth'],
            $_POST['gender'],
            $_POST['blood_group'],
            $_POST['consultation_fee'],
            $_POST['joined_date']
        ]);
        
        $db->getConnection()->commit();
        $message = "Doctor added successfully! Employee ID: " . $employee_id . ", Username: " . $username;
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle status toggle
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    try {
        $doctor_id = $_POST['doctor_id'];
        $new_status = $_POST['new_status'];
        
        $db->query("UPDATE doctors SET is_available = ? WHERE id = ?", [$new_status, $doctor_id]);
        $db->query("UPDATE users SET is_active = ? WHERE id = (SELECT user_id FROM doctors WHERE id = ?)", [$new_status, $doctor_id]);
        
        $message = "Doctor status updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get doctors with search
$search = $_GET['search'] ?? '';
$filter_department = $_GET['department'] ?? '';

$sql = "SELECT d.*, 
        CONCAT(d.first_name, ' ', d.last_name) as full_name,
        dept.name as department_name,
        u.email, u.is_active,
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id) as total_appointments,
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id AND appointment_date = CURDATE()) as today_appointments
        FROM doctors d
        LEFT JOIN departments dept ON d.department_id = dept.id
        JOIN users u ON d.user_id = u.id
        WHERE d.hospital_id = 1";

$params = [];

if ($search) {
    $sql .= " AND (d.first_name LIKE ? OR d.last_name LIKE ? OR d.employee_id LIKE ? OR d.specialization LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

if ($filter_department) {
    $sql .= " AND d.department_id = ?";
    $params[] = $filter_department;
}

$sql .= " ORDER BY d.first_name, d.last_name";

$doctors = $db->query($sql, $params)->fetchAll();

// Get departments for form and filter
$departments = $db->query("SELECT * FROM departments WHERE hospital_id = 1 ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Management - Hospital CRM</title>
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
        
        .search-filters {
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
        
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .doctor-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .doctor-card:hover {
            transform: translateY(-5px);
        }
        
        .doctor-header {
            background: linear-gradient(135deg, #004685, #0066cc);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .doctor-header h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .doctor-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .doctor-body {
            padding: 20px;
        }
        
        .doctor-info {
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
        
        .doctor-stats {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat .number {
            font-size: 18px;
            font-weight: 600;
            color: #004685;
        }
        
        .stat .label {
            font-size: 12px;
            color: #666;
        }
        
        .doctor-actions {
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
            
            .doctors-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .doctor-info {
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
            <h1>Doctor Management</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button onclick="openModal()" class="btn btn-primary">+ Add New Doctor</button>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="search-filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Search Doctors</label>
                    <input type="text" name="search" id="search" placeholder="Search by name, employee ID, or specialization..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="department">Department</label>
                    <select name="department" id="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo $filter_department == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                
                <div class="form-group">
                    <?php if ($search || $filter_department): ?>
                        <a href="doctors.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="doctors-grid">
            <?php if (empty($doctors)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: #666;">
                    <h3>No doctors found</h3>
                    <p>Add your first doctor to get started.</p>
                </div>
            <?php else: ?>
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card">
                        <div class="doctor-header">
                            <h3>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                            <p><?php echo htmlspecialchars($doctor['specialization'] ?? 'General Practitioner'); ?></p>
                        </div>
                        
                        <div class="doctor-body">
                            <div class="doctor-info">
                                <div class="info-item">
                                    <label>Employee ID</label>
                                    <span><?php echo htmlspecialchars($doctor['employee_id']); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <label>Department</label>
                                    <span><?php echo htmlspecialchars($doctor['department_name'] ?? 'General'); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <label>Experience</label>
                                    <span><?php echo $doctor['experience_years']; ?> years</span>
                                </div>
                                
                                <div class="info-item">
                                    <label>Consultation Fee</label>
                                    <span>₹<?php echo number_format($doctor['consultation_fee'], 2); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <label>Phone</label>
                                    <span><?php echo htmlspecialchars($doctor['phone']); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <label>Status</label>
                                    <span class="status-badge <?php echo $doctor['is_available'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $doctor['is_available'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="doctor-stats">
                                <div class="stat">
                                    <div class="number"><?php echo $doctor['total_appointments']; ?></div>
                                    <div class="label">Total Appointments</div>
                                </div>
                                <div class="stat">
                                    <div class="number"><?php echo $doctor['today_appointments']; ?></div>
                                    <div class="label">Today</div>
                                </div>
                            </div>
                            
                            <div class="doctor-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $doctor['is_available'] ? '0' : '1'; ?>">
                                    <button type="submit" class="btn <?php echo $doctor['is_available'] ? 'btn-danger' : 'btn-success'; ?> btn-sm">
                                        <?php echo $doctor['is_available'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <a href="doctor-details.php?id=<?php echo $doctor['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Doctor Modal -->
    <div id="doctorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Doctor</h2>
                <button type="button" class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_doctor">
                    
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
                        <textarea id="address" name="address"></textarea>
                    </div>
                    
                    <h3 style="color: #004685; margin: 20px 0 15px;">Professional Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialization">Specialization *</label>
                            <input type="text" id="specialization" name="specialization" required>
                        </div>
                        <div class="form-group">
                            <label for="department_id">Department</label>
                            <select id="department_id" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="qualification">Qualification *</label>
                        <textarea id="qualification" name="qualification" placeholder="MBBS, MD, etc." required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="experience_years">Experience (Years) *</label>
                            <input type="number" id="experience_years" name="experience_years" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="consultation_fee">Consultation Fee (₹) *</label>
                            <input type="number" id="consultation_fee" name="consultation_fee" min="0" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="registration_number">Registration Number</label>
                            <input type="text" id="registration_number" name="registration_number">
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
                        <label for="joined_date">Joining Date *</label>
                        <input type="date" id="joined_date" name="joined_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div style="text-align: right; margin-top: 30px;">
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Doctor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('doctorModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('doctorModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('doctorModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>

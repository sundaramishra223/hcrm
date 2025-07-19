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
if (!in_array($user_role, ['admin', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$search = $_GET['search'] ?? '';

// Handle form submission for new patient
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_patient') {
    try {
        // Generate patient ID manually
        $year = date('Y');
        $stmt = $db->query("SELECT COUNT(*) as count FROM patients WHERE YEAR(created_at) = ?", [$year]);
        $count = $stmt->fetch()['count'] + 1;
        $patient_id = "P" . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        // Insert patient
        $sql = "INSERT INTO patients (patient_id, first_name, middle_name, last_name, phone, emergency_contact, email, address, date_of_birth, gender, blood_group, marital_status, occupation, medical_history, allergies, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $db->query($sql, [
            $patient_id,
            $_POST['first_name'],
            $_POST['middle_name'] ?? '',
            $_POST['last_name'],
            $_POST['phone'],
            $_POST['emergency_contact'] ?? '',
            $_POST['email'] ?? '',
            $_POST['address'] ?? '',
            $_POST['date_of_birth'] ?? null,
            $_POST['gender'],
            $_POST['blood_group'] ?? '',
            $_POST['marital_status'] ?? '',
            $_POST['occupation'] ?? '',
            $_POST['medical_history'] ?? '',
            $_POST['allergies'] ?? ''
        ]);
        
        showSuccessPopup("Patient added successfully! ID: " . $patient_id, "patients.php");
    } catch (Exception $e) {
        showErrorPopup("Error adding patient: " . $e->getMessage());
    }
}

// Get patients with search
$sql = "SELECT p.*, 
        TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
        (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) as appointment_count
        FROM patients p";

if ($search) {
    $sql .= " WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ? OR p.patient_id LIKE ?)";
    $search_param = "%$search%";
    $patients = $db->query($sql, [$search_param, $search_param, $search_param, $search_param])->fetchAll();
} else {
    $patients = $db->query($sql)->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management - Hospital CRM</title>
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
        }
        
        .search-form input {
            flex: 1;
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .patients-table {
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
            margin: 50px auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
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
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-primary {
            background: #004685;
            color: white;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .table {
                font-size: 14px;
            }
            
            .table th, .table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Patient Management</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button onclick="openModal()" class="btn btn-primary">+ Add New Patient</button>
            </div>
        </div>
        

        
        <div class="search-section">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search patients by name, phone, or patient ID..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search): ?>
                    <a href="patients.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="patients-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Blood Group</th>
                        <th>Appointments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px; color: #666;">
                                <?php echo $search ? 'No patients found matching your search.' : 'No patients registered yet.'; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($patient['patient_id']); ?></span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong>
                                    <?php if ($patient['email']): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars($patient['email']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                <td><?php echo $patient['age'] ?? 'N/A'; ?></td>
                                <td><?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($patient['blood_group'] ?? 'N/A'); ?></td>
                                <td><span class="badge badge-success"><?php echo $patient['appointment_count']; ?></span></td>
                                <td>
                                    <a href="patient-details.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">View</a>
                                    <a href="book-appointment.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success" style="font-size: 12px; padding: 5px 10px;">Book</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Patient Modal -->
    <div id="patientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Patient</h2>
                <button type="button" class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_patient">
                    
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
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address"></textarea>
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
                    
                    <div class="form-row">
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
                        <div class="form-group">
                            <label for="marital_status">Marital Status</label>
                            <select id="marital_status" name="marital_status">
                                <option value="">Select Status</option>
                                <option value="single">Single</option>
                                <option value="married">Married</option>
                                <option value="divorced">Divorced</option>
                                <option value="widowed">Widowed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="occupation">Occupation</label>
                        <input type="text" id="occupation" name="occupation">
                    </div>
                    
                    <div class="form-group">
                        <label for="medical_history">Medical History</label>
                        <textarea id="medical_history" name="medical_history" placeholder="Previous illnesses, surgeries, chronic conditions..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="allergies">Allergies</label>
                        <textarea id="allergies" name="allergies" placeholder="Drug allergies, food allergies, environmental allergies..."></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('patientModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('patientModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('patientModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>

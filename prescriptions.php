<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Get doctor info if user is doctor
$doctor_id = null;
if ($user_role === 'doctor') {
    $doctor_info = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch();
    $doctor_id = $doctor_info['id'] ?? null;
}

// Handle prescription creation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'create_prescription') {
    try {
        $db->getConnection()->beginTransaction();
        
        // Generate prescription number
        $prescription_number = 'RX' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
        
        // Insert prescription
        $prescription_sql = "INSERT INTO prescriptions (patient_id, doctor_id, visit_id, prescription_number, diagnosis, instructions, follow_up_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
        
        $db->query($prescription_sql, [
            $_POST['patient_id'],
            $_POST['doctor_id'] ?: $doctor_id,
            $_POST['visit_id'] ?: null,
            $prescription_number,
            $_POST['diagnosis'],
            $_POST['instructions'],
            $_POST['follow_up_date'] ?: null
        ]);
        
        $prescription_id = $db->lastInsertId();
        
        // Insert medicines
        if (isset($_POST['medicines']) && is_array($_POST['medicines'])) {
            foreach ($_POST['medicines'] as $medicine) {
                if (!empty($medicine['medicine_id']) && !empty($medicine['dosage'])) {
                    // Get medicine details and price
                    $med_details = $db->query("SELECT unit_price FROM medicines WHERE id = ?", [$medicine['medicine_id']])->fetch();
                    $unit_price = $med_details['unit_price'] ?? 0;
                    $total_price = $unit_price * (int)$medicine['quantity'];
                    
                    $medicine_sql = "INSERT INTO prescription_medicines (prescription_id, medicine_id, dosage, frequency, duration, quantity, unit_price, total_price, instructions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $db->query($medicine_sql, [
                        $prescription_id,
                        $medicine['medicine_id'],
                        $medicine['dosage'],
                        $medicine['frequency'],
                        $medicine['duration'],
                        $medicine['quantity'],
                        $unit_price,
                        $total_price,
                        $medicine['instructions'] ?? ''
                    ]);
                }
            }
        }
        
        $db->getConnection()->commit();
        $message = "Prescription created successfully! Prescription Number: " . $prescription_number;
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle prescription status update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $db->query(
            "UPDATE prescriptions SET status = ?, updated_at = NOW() WHERE id = ?",
            [$_POST['status'], $_POST['prescription_id']]
        );
        $message = "Prescription status updated successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get prescriptions with search and filters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_doctor = $_GET['doctor'] ?? '';

$sql = "SELECT p.*, 
        CONCAT(pt.first_name, ' ', pt.last_name) as patient_name,
        pt.patient_id,
        pt.phone as patient_phone,
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        d.specialization,
        (SELECT COUNT(*) FROM prescription_medicines WHERE prescription_id = p.id) as medicine_count
        FROM prescriptions p
        JOIN patients pt ON p.patient_id = pt.id
        JOIN doctors d ON p.doctor_id = d.id
        WHERE pt.hospital_id = 1";

$params = [];

// Role-based filtering
if ($user_role === 'doctor' && $doctor_id) {
    $sql .= " AND p.doctor_id = ?";
    $params[] = $doctor_id;
}

if ($search) {
    $sql .= " AND (p.prescription_number LIKE ? OR pt.first_name LIKE ? OR pt.last_name LIKE ? OR pt.patient_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($filter_status) {
    $sql .= " AND p.status = ?";
    $params[] = $filter_status;
}

if ($filter_doctor && $user_role === 'admin') {
    $sql .= " AND p.doctor_id = ?";
    $params[] = $filter_doctor;
}

$sql .= " ORDER BY p.created_at DESC";

$prescriptions = $db->query($sql, $params)->fetchAll();

// Get patients for prescription creation
$patients_sql = "SELECT id, patient_id, CONCAT(first_name, ' ', last_name) as full_name, phone FROM patients WHERE hospital_id = 1";
if ($user_role === 'doctor' && $doctor_id) {
    // Get patients who have appointments with this doctor
    $patients_sql = "SELECT DISTINCT p.id, p.patient_id, CONCAT(p.first_name, ' ', p.last_name) as full_name, p.phone 
                     FROM patients p 
                     JOIN appointments a ON p.id = a.patient_id 
                     WHERE p.hospital_id = 1 AND a.doctor_id = ?
                     ORDER BY p.first_name, p.last_name";
    $patients = $db->query($patients_sql, [$doctor_id])->fetchAll();
} else {
    $patients = $db->query($patients_sql . " ORDER BY first_name, last_name")->fetchAll();
}

// Get doctors for filter (admin only)
$doctors = [];
if ($user_role === 'admin') {
    $doctors = $db->query("
        SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
        FROM doctors 
        WHERE hospital_id = 1 AND is_available = 1
        ORDER BY first_name, last_name
    ")->fetchAll();
}

// Get medicines for prescription creation
$medicines = $db->query("
    SELECT id, name, generic_name, dosage_form, strength, unit_price
    FROM medicines 
    WHERE hospital_id = 1 AND is_active = 1
    ORDER BY name
")->fetchAll();

// Get prescription statistics
$stats = [];
try {
    if ($user_role === 'doctor' && $doctor_id) {
        $stats['total_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?", [$doctor_id])->fetch()['count'];
        $stats['active_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND status = 'active'", [$doctor_id])->fetch()['count'];
        $stats['todays_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND DATE(created_at) = CURDATE()", [$doctor_id])->fetch()['count'];
    } else {
        $stats['total_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions")->fetch()['count'];
        $stats['active_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE status = 'active'")->fetch()['count'];
        $stats['todays_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE DATE(created_at) = CURDATE()")->fetch()['count'];
    }
} catch (Exception $e) {
    $stats = ['total_prescriptions' => 0, 'active_prescriptions' => 0, 'todays_prescriptions' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Management - Hospital CRM</title>
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
        
        .badge-completed {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .badge-cancelled {
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
            max-width: 900px;
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
        
        .medicine-section {
            border: 1px solid #e1e1e1;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .medicine-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 80px 80px auto;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .medicine-row input, .medicine-row select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 14px;
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
            
            .medicine-row {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .table .hide-mobile {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $user_role === 'doctor' ? 'My Prescriptions' : 'Prescription Management'; ?></h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button onclick="openPrescriptionModal()" class="btn btn-primary">+ Create Prescription</button>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_prescriptions']); ?></h3>
                <p>Total Prescriptions</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['active_prescriptions']); ?></h3>
                <p>Active Prescriptions</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['todays_prescriptions']); ?></h3>
                <p>Today's Prescriptions</p>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Search Prescriptions</label>
                    <input type="text" name="search" id="search" placeholder="Search by prescription number, patient name..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <?php if ($user_role === 'admin' && !empty($doctors)): ?>
                <div class="form-group">
                    <label for="doctor">Doctor</label>
                    <select name="doctor" id="doctor">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" 
                                    <?php echo $filter_doctor == $doctor['id'] ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="prescriptions.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
        
        <div class="prescriptions-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Prescription #</th>
                        <th>Patient</th>
                        <?php if ($user_role === 'admin'): ?>
                            <th>Doctor</th>
                        <?php endif; ?>
                        <th>Date</th>
                        <th>Medicines</th>
                        <th class="hide-mobile">Diagnosis</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($prescriptions)): ?>
                        <tr>
                            <td colspan="<?php echo $user_role === 'admin' ? '8' : '7'; ?>" style="text-align: center; padding: 30px; color: #666;">
                                No prescriptions found for the selected criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($prescription['prescription_number']); ?></strong>
                                    <br>
                                    <small style="color: #666;">
                                        <?php echo date('M d, Y', strtotime($prescription['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($prescription['patient_name']); ?></strong>
                                    <br>
                                    <small style="color: #666;">
                                        ID: <?php echo htmlspecialchars($prescription['patient_id']); ?><br>
                                        <?php echo htmlspecialchars($prescription['patient_phone']); ?>
                                    </small>
                                </td>
                                <?php if ($user_role === 'admin'): ?>
                                <td>
                                    <strong>Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></strong>
                                    <br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($prescription['specialization']); ?></small>
                                </td>
                                <?php endif; ?>
                                <td><?php echo date('M d, Y', strtotime($prescription['created_at'])); ?></td>
                                <td>
                                    <span class="badge badge-active"><?php echo $prescription['medicine_count']; ?> medicines</span>
                                    <?php if ($prescription['follow_up_date']): ?>
                                        <br><small style="color: #666;">Follow-up: <?php echo date('M d', strtotime($prescription['follow_up_date'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="hide-mobile" style="max-width: 200px;">
                                    <?php echo htmlspecialchars(substr($prescription['diagnosis'] ?? 'N/A', 0, 50)); ?>
                                    <?php if (strlen($prescription['diagnosis'] ?? '') > 50): ?>...<?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $prescription['status']; ?>">
                                        <?php echo ucfirst($prescription['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="prescription-details.php?id=<?php echo $prescription['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                    <?php if ($prescription['status'] === 'active'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="prescription_id" value="<?php echo $prescription['id']; ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-success btn-sm">Complete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Create Prescription Modal -->
    <div id="prescriptionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Prescription</h2>
                <button type="button" class="close" onclick="closePrescriptionModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="prescriptionForm">
                    <input type="hidden" name="action" value="create_prescription">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="patient_id">Patient *</label>
                            <select id="patient_id" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($user_role === 'admin'): ?>
                        <div class="form-group">
                            <label for="doctor_id">Doctor *</label>
                            <select id="doctor_id" name="doctor_id" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="doctor_id" value="<?php echo $doctor_id; ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="visit_id">Visit ID (Optional)</label>
                            <input type="text" id="visit_id" name="visit_id">
                        </div>
                        <div class="form-group">
                            <label for="follow_up_date">Follow-up Date</label>
                            <input type="date" id="follow_up_date" name="follow_up_date" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="diagnosis">Diagnosis *</label>
                        <textarea id="diagnosis" name="diagnosis" rows="3" placeholder="Patient diagnosis..." required></textarea>
                    </div>
                    
                    <div class="medicine-section">
                        <h4 style="margin-bottom: 15px; color: #004685;">Prescribed Medicines</h4>
                        <div class="medicine-row" style="font-weight: 600; color: #666;">
                            <div>Medicine</div>
                            <div>Dosage</div>
                            <div>Frequency</div>
                            <div>Duration</div>
                            <div>Qty</div>
                            <div>Action</div>
                        </div>
                        <div id="medicineList">
                            <div class="medicine-row">
                                <select name="medicines[0][medicine_id]" required>
                                    <option value="">Select Medicine</option>
                                    <?php foreach ($medicines as $medicine): ?>
                                        <option value="<?php echo $medicine['id']; ?>">
                                            <?php echo htmlspecialchars($medicine['name'] . ' - ' . $medicine['strength']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="medicines[0][dosage]" placeholder="1 tablet" required>
                                <select name="medicines[0][frequency]" required>
                                    <option value="">Select Frequency</option>
                                    <option value="Once daily">Once daily</option>
                                    <option value="Twice daily">Twice daily</option>
                                    <option value="Three times daily">Three times daily</option>
                                    <option value="Four times daily">Four times daily</option>
                                    <option value="As needed">As needed</option>
                                    <option value="Before meals">Before meals</option>
                                    <option value="After meals">After meals</option>
                                </select>
                                <input type="text" name="medicines[0][duration]" placeholder="7 days" required>
                                <input type="number" name="medicines[0][quantity]" placeholder="10" min="1" required>
                                <button type="button" onclick="removeMedicine(this)" class="btn btn-sm" style="background: #dc3545; color: white;">×</button>
                            </div>
                        </div>
                        <button type="button" onclick="addMedicine()" class="btn btn-secondary">+ Add Medicine</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="instructions">Instructions</label>
                        <textarea id="instructions" name="instructions" rows="4" placeholder="Special instructions for the patient..."></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closePrescriptionModal()" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Prescription</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        let medicineCount = 1;
        
        function openPrescriptionModal() {
            document.getElementById('prescriptionModal').style.display = 'block';
        }
        
        function closePrescriptionModal() {
            document.getElementById('prescriptionModal').style.display = 'none';
        }
        
        function addMedicine() {
            const medicineList = document.getElementById('medicineList');
            const newMedicine = document.createElement('div');
            newMedicine.className = 'medicine-row';
            newMedicine.innerHTML = `
                <select name="medicines[${medicineCount}][medicine_id]" required>
                    <option value="">Select Medicine</option>
                    <?php foreach ($medicines as $medicine): ?>
                        <option value="<?php echo $medicine['id']; ?>">
                            <?php echo htmlspecialchars($medicine['name'] . ' - ' . $medicine['strength']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="medicines[${medicineCount}][dosage]" placeholder="1 tablet" required>
                <select name="medicines[${medicineCount}][frequency]" required>
                    <option value="">Select Frequency</option>
                    <option value="Once daily">Once daily</option>
                    <option value="Twice daily">Twice daily</option>
                    <option value="Three times daily">Three times daily</option>
                    <option value="Four times daily">Four times daily</option>
                    <option value="As needed">As needed</option>
                    <option value="Before meals">Before meals</option>
                    <option value="After meals">After meals</option>
                </select>
                <input type="text" name="medicines[${medicineCount}][duration]" placeholder="7 days" required>
                <input type="number" name="medicines[${medicineCount}][quantity]" placeholder="10" min="1" required>
                <button type="button" onclick="removeMedicine(this)" class="btn btn-sm" style="background: #dc3545; color: white;">×</button>
            `;
            medicineList.appendChild(newMedicine);
            medicineCount++;
        }
        
        function removeMedicine(button) {
            button.closest('.medicine-row').remove();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('prescriptionModal');
            if (event.target === modal) {
                closePrescriptionModal();
            }
        }
    </script>
</body>
</html>

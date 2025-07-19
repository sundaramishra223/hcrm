<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
$patient_id = $_GET['id'] ?? null;

if (!$patient_id) {
    header('Location: patients.php');
    exit;
}

$db = new Database();

// Check permissions and get patient data
$patient_sql = "SELECT p.*, 
                TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
                FROM patients p
                WHERE p.id = ?";

$patient = $db->query($patient_sql, [$patient_id])->fetch();

if (!$patient) {
    header('Location: patients.php');
    exit;
}

// Role-based access control
if ($user_role === 'patient') {
    // Patients can only view their own data
    $user_patient = $db->query("SELECT id FROM patients WHERE user_id = ?", [$_SESSION['user_id']])->fetch();
    if (!$user_patient || $user_patient['id'] != $patient_id) {
        header('Location: dashboard.php');
        exit;
    }
} elseif ($user_role === 'doctor') {
    // Doctors can only view patients they have appointments with
    $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
    $has_appointment = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND doctor_id = ?", [$patient_id, $doctor_id])->fetch()['count'];
    if ($has_appointment == 0) {
        header('Location: dashboard.php');
        exit;
    }
}

// Get recent appointments
$appointments = $db->query("
    SELECT a.*, 
    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
    d.specialization
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 10
", [$patient_id])->fetchAll();

// Get recent prescriptions
$prescriptions = $db->query("
    SELECT p.*,
    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
    (SELECT COUNT(*) FROM prescription_medicines WHERE prescription_id = p.id) as medicine_count
    FROM prescriptions p
    JOIN doctors d ON p.doctor_id = d.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
", [$patient_id])->fetchAll();

// Get recent vitals
$vitals = $db->query("
    SELECT v.*,
    CONCAT(u.username) as recorded_by_name
    FROM patient_vitals v
    JOIN users u ON v.recorded_by = u.id
    WHERE v.patient_id = ?
    ORDER BY v.recorded_at DESC
    LIMIT 5
", [$patient_id])->fetchAll();

// Get bills
$bills = $db->query("
    SELECT * FROM bills
    WHERE patient_id = ?
    ORDER BY bill_date DESC
    LIMIT 10
", [$patient_id])->fetchAll();

// Get lab orders
$lab_orders = $db->query("
    SELECT lo.*,
    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
    (SELECT COUNT(*) FROM lab_order_tests WHERE lab_order_id = lo.id) as test_count
    FROM lab_orders lo
    JOIN doctors d ON lo.doctor_id = d.id
    WHERE lo.patient_id = ?
    ORDER BY lo.order_date DESC
    LIMIT 5
", [$patient_id])->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></title>
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .patient-overview {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .patient-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .patient-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #004685, #0066cc);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 600;
            margin: 0 auto 20px;
        }
        
        .patient-name {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .patient-id {
            color: #666;
            margin-bottom: 20px;
        }
        
        .patient-info {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-item label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .info-item span {
            font-weight: 500;
            color: #333;
        }
        
        .tabs {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tab-buttons {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .tab-button {
            padding: 15px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s;
            flex: 1;
            text-align: center;
        }
        
        .tab-button.active {
            background: white;
            color: #004685;
            border-bottom: 2px solid #004685;
        }
        
        .tab-content {
            padding: 25px;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .records-list {
            list-style: none;
        }
        
        .record-item {
            padding: 15px;
            border-bottom: 1px solid #e1e1e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .record-item:last-child {
            border-bottom: none;
        }
        
        .record-main {
            flex: 1;
        }
        
        .record-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .record-details {
            font-size: 14px;
            color: #666;
        }
        
        .record-date {
            font-size: 12px;
            color: #999;
            font-weight: 500;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-scheduled {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-completed {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .vital-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .vital-value {
            font-size: 20px;
            font-weight: 600;
            color: #004685;
        }
        
        .vital-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .patient-overview {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .tab-buttons {
                flex-direction: column;
            }
            
            .vitals-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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
            <h1>Patient Details</h1>
            <div>
                <a href="<?php echo $user_role === 'patient' ? 'dashboard.php' : 'patients.php'; ?>" class="btn btn-secondary">← Back</a>
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <a href="book-appointment.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">Book Appointment</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="patient-overview">
            <div class="patient-card">
                <div class="patient-avatar">
                    <?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?>
                </div>
                <div class="patient-name">
                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                </div>
                <div class="patient-id">
                    Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?>
                </div>
                
                <div style="text-align: left; margin-top: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #666;">Age:</span>
                        <span style="font-weight: 500;"><?php echo $patient['age'] ?? 'N/A'; ?> years</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #666;">Gender:</span>
                        <span style="font-weight: 500;"><?php echo ucfirst($patient['gender'] ?? 'N/A'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #666;">Blood Group:</span>
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($patient['blood_group'] ?? 'N/A'); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #666;">Phone:</span>
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($patient['phone']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="patient-info">
                <h3 style="color: #004685; margin-bottom: 20px;">Patient Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Full Name</label>
                        <span><?php echo htmlspecialchars(trim($patient['first_name'] . ' ' . ($patient['middle_name'] ?? '') . ' ' . $patient['last_name'])); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>Email</label>
                        <span><?php echo htmlspecialchars($patient['email'] ?? 'Not provided'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>Date of Birth</label>
                        <span><?php echo $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'Not provided'; ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>Emergency Contact</label>
                        <span><?php echo htmlspecialchars($patient['emergency_contact'] ?? 'Not provided'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>Marital Status</label>
                        <span><?php echo ucfirst($patient['marital_status'] ?? 'Not specified'); ?></span>
                    </div>
                    
                    <div class="info-item">
                        <label>Occupation</label>
                        <span><?php echo htmlspecialchars($patient['occupation'] ?? 'Not provided'); ?></span>
                    </div>
                </div>
                
                <?php if ($patient['address']): ?>
                <div style="margin-top: 20px;">
                    <label style="font-size: 12px; color: #666; font-weight: 500;">Address</label>
                    <div style="margin-top: 5px; font-weight: 500; color: #333;">
                        <?php echo htmlspecialchars($patient['address']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['medical_history'] || $patient['allergies']): ?>
                <div style="margin-top: 20px;">
                    <?php if ($patient['medical_history']): ?>
                    <div style="margin-bottom: 15px;">
                        <label style="font-size: 12px; color: #666; font-weight: 500;">Medical History</label>
                        <div style="margin-top: 5px; color: #333; background: #f8f9fa; padding: 10px; border-radius: 5px;">
                            <?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($patient['allergies']): ?>
                    <div>
                        <label style="font-size: 12px; color: #666; font-weight: 500;">Allergies</label>
                        <div style="margin-top: 5px; color: #721c24; background: #f8d7da; padding: 10px; border-radius: 5px; border: 1px solid #f5c6cb;">
                            <?php echo nl2br(htmlspecialchars($patient['allergies'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="showTab('appointments')">Appointments</button>
                <button class="tab-button" onclick="showTab('prescriptions')">Prescriptions</button>
                <button class="tab-button" onclick="showTab('vitals')">Vitals</button>
                <button class="tab-button" onclick="showTab('lab-orders')">Lab Tests</button>
                <button class="tab-button" onclick="showTab('bills')">Bills</button>
            </div>
            
            <div id="appointments" class="tab-content active">
                <h3 style="color: #004685; margin-bottom: 20px;">Recent Appointments</h3>
                <?php if (empty($appointments)): ?>
                    <div class="empty-state">
                        <h3>No appointments found</h3>
                        <p>This patient hasn't had any appointments yet.</p>
                    </div>
                <?php else: ?>
                    <ul class="records-list">
                        <?php foreach ($appointments as $appointment): ?>
                            <li class="record-item">
                                <div class="record-main">
                                    <div class="record-title">
                                        Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                    </div>
                                    <div class="record-details">
                                        <?php echo htmlspecialchars($appointment['specialization']); ?> • 
                                        <?php echo ucfirst(str_replace('_', ' ', $appointment['type'])); ?>
                                        <?php if ($appointment['chief_complaint']): ?>
                                            <br><?php echo htmlspecialchars(substr($appointment['chief_complaint'], 0, 100)); ?>
                                            <?php if (strlen($appointment['chief_complaint']) > 100): ?>...<?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="record-date">
                                        <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
                                        <?php echo date('H:i', strtotime($appointment['appointment_time'])); ?>
                                    </div>
                                    <div style="margin-top: 5px;">
                                        <span class="badge badge-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <div id="prescriptions" class="tab-content">
                <h3 style="color: #004685; margin-bottom: 20px;">Recent Prescriptions</h3>
                <?php if (empty($prescriptions)): ?>
                    <div class="empty-state">
                        <h3>No prescriptions found</h3>
                        <p>This patient hasn't been prescribed any medications yet.</p>
                    </div>
                <?php else: ?>
                    <ul class="records-list">
                        <?php foreach ($prescriptions as $prescription): ?>
                            <li class="record-item">
                                <div class="record-main">
                                    <div class="record-title">
                                        <?php echo htmlspecialchars($prescription['prescription_number']); ?>
                                    </div>
                                    <div class="record-details">
                                        By Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?> • 
                                        <?php echo $prescription['medicine_count']; ?> medicines
                                        <?php if ($prescription['diagnosis']): ?>
                                            <br><strong>Diagnosis:</strong> <?php echo htmlspecialchars(substr($prescription['diagnosis'], 0, 100)); ?>
                                            <?php if (strlen($prescription['diagnosis']) > 100): ?>...<?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="record-date">
                                        <?php echo date('M d, Y', strtotime($prescription['created_at'])); ?>
                                    </div>
                                    <div style="margin-top: 5px;">
                                        <span class="badge badge-<?php echo $prescription['status']; ?>">
                                            <?php echo ucfirst($prescription['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <div id="vitals" class="tab-content">
                <h3 style="color: #004685; margin-bottom: 20px;">Recent Vitals</h3>
                <?php if (empty($vitals)): ?>
                    <div class="empty-state">
                        <h3>No vitals recorded</h3>
                        <p>No vital signs have been recorded for this patient yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($vitals as $vital): ?>
                        <div style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h4 style="color: #333;">Recorded on <?php echo date('M d, Y', strtotime($vital['recorded_at'])); ?></h4>
                                <small style="color: #666;">by <?php echo htmlspecialchars($vital['recorded_by_name']); ?></small>
                            </div>
                            
                            <div class="vitals-grid">
                                <?php if ($vital['height_cm']): ?>
                                    <div class="vital-card">
                                        <div class="vital-value"><?php echo $vital['height_cm']; ?> cm</div>
                                        <div class="vital-label">Height</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vital['weight_kg']): ?>
                                    <div class="vital-card">
                                        <div class="vital-value"><?php echo $vital['weight_kg']; ?> kg</div>
                                        <div class="vital-label">Weight</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vital['temperature_f']): ?>
                                    <div class="vital-card">
                                        <div class="vital-value"><?php echo $vital['temperature_f']; ?>°F</div>
                                        <div class="vital-label">Temperature</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vital['blood_pressure_systolic'] && $vital['blood_pressure_diastolic']): ?>
                                    <div class="vital-card">
                                        <div class="vital-value"><?php echo $vital['blood_pressure_systolic']; ?>/<?php echo $vital['blood_pressure_diastolic']; ?></div>
                                        <div class="vital-label">Blood Pressure</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vital['heart_rate']): ?>
                                    <div class="vital-card">
                                        <div class="vital-value"><?php echo $vital['heart_rate']; ?> bpm</div>
                                        <div class="vital-label">Heart Rate</div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($vital['oxygen_saturation']): ?>
                                    <div class="vital-card">
                                        <div class="vital-value"><?php echo $vital['oxygen_saturation']; ?>%</div>
                                        <div class="vital-label">Oxygen Saturation</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($vital['notes']): ?>
                                <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 5px;">
                                    <strong>Notes:</strong> <?php echo htmlspecialchars($vital['notes']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div id="lab-orders" class="tab-content">
                <h3 style="color: #004685; margin-bottom: 20px;">Lab Test Orders</h3>
                <?php if (empty($lab_orders)): ?>
                    <div class="empty-state">
                        <h3>No lab orders found</h3>
                        <p>No laboratory tests have been ordered for this patient yet.</p>
                    </div>
                <?php else: ?>
                    <ul class="records-list">
                        <?php foreach ($lab_orders as $order): ?>
                            <li class="record-item">
                                <div class="record-main">
                                    <div class="record-title">
                                        Order #<?php echo htmlspecialchars($order['order_number']); ?>
                                    </div>
                                    <div class="record-details">
                                        Ordered by Dr. <?php echo htmlspecialchars($order['doctor_name']); ?> • 
                                        <?php echo $order['test_count']; ?> tests • 
                                        <?php echo ucfirst($order['priority']); ?> priority
                                        <?php if ($order['clinical_notes']): ?>
                                            <br><?php echo htmlspecialchars(substr($order['clinical_notes'], 0, 100)); ?>
                                            <?php if (strlen($order['clinical_notes']) > 100): ?>...<?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="record-date">
                                        <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                                    </div>
                                    <div style="margin-top: 5px;">
                                        <span class="badge badge-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
            <div id="bills" class="tab-content">
                <h3 style="color: #004685; margin-bottom: 20px;">Billing History</h3>
                <?php if (empty($bills)): ?>
                    <div class="empty-state">
                        <h3>No bills found</h3>
                        <p>No bills have been generated for this patient yet.</p>
                    </div>
                <?php else: ?>
                    <ul class="records-list">
                        <?php foreach ($bills as $bill): ?>
                            <li class="record-item">
                                <div class="record-main">
                                    <div class="record-title">
                                        <?php echo htmlspecialchars($bill['bill_number']); ?>
                                    </div>
                                    <div class="record-details">
                                        <?php echo ucfirst($bill['bill_type']); ?> • 
                                        Total: ₹<?php echo number_format($bill['total_amount'], 2); ?> • 
                                        Balance: ₹<?php echo number_format($bill['balance_amount'], 2); ?>
                                        <?php if ($bill['notes']): ?>
                                            <br><?php echo htmlspecialchars(substr($bill['notes'], 0, 100)); ?>
                                            <?php if (strlen($bill['notes']) > 100): ?>...<?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="record-date">
                                        <?php echo date('M d, Y', strtotime($bill['bill_date'])); ?>
                                    </div>
                                    <div style="margin-top: 5px;">
                                        <span class="badge badge-<?php echo $bill['payment_status']; ?>">
                                            <?php echo ucfirst($bill['payment_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

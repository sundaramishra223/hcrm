<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$patient_id = $_SESSION['user_id'];

// Get patient details
$patient = $db->query("SELECT * FROM patients WHERE user_id = ?", [$patient_id])->fetch();

if (!$patient) {
    header('Location: dashboard.php');
    exit;
}

// Get patient's medical history
$medical_history = [];

// Get vitals history
$vitals = $db->query("
    SELECT pv.*, 
    CASE 
        WHEN pv.recorded_by_type = 'doctor' THEN CONCAT(d.first_name, ' ', d.last_name)
        WHEN pv.recorded_by_type = 'nurse' THEN CONCAT(s.first_name, ' ', s.last_name)
        ELSE 'Unknown'
    END as recorded_by_name
    FROM patient_vitals pv
    LEFT JOIN doctors d ON pv.recorded_by = d.id AND pv.recorded_by_type = 'doctor'
    LEFT JOIN staff s ON pv.recorded_by = s.id AND pv.recorded_by_type = 'nurse'
    WHERE pv.patient_id = ?
    ORDER BY pv.recorded_at DESC
", [$patient['id']])->fetchAll();

// Get appointments history
$appointments = $db->query("
    SELECT a.*, 
    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
    d.specialization
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
", [$patient['id']])->fetchAll();

// Get prescriptions history
$prescriptions = $db->query("
    SELECT p.*, 
    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
    d.specialization
    FROM prescriptions p
    JOIN doctors d ON p.doctor_id = d.id
    WHERE p.patient_id = ?
    ORDER BY p.prescription_date DESC
", [$patient['id']])->fetchAll();

// Get lab results history
$lab_results = $db->query("
    SELECT lo.*, 
    CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
    COUNT(lot.id) as test_count,
    SUM(CASE WHEN lot.status = 'completed' THEN 1 ELSE 0 END) as completed_tests
    FROM lab_orders lo
    JOIN doctors d ON lo.doctor_id = d.id
    LEFT JOIN lab_order_tests lot ON lo.id = lot.lab_order_id
    WHERE lo.patient_id = ?
    GROUP BY lo.id
    ORDER BY lo.order_date DESC
", [$patient['id']])->fetchAll();

// Get medical history statistics
$stats = [];
try {
    $stats['total_vitals'] = count($vitals);
    $stats['total_appointments'] = count($appointments);
    $stats['total_prescriptions'] = count($prescriptions);
    $stats['total_lab_orders'] = count($lab_results);
    $stats['completed_appointments'] = count(array_filter($appointments, function($a) { return $a['status'] === 'completed'; }));
    $stats['active_prescriptions'] = count(array_filter($prescriptions, function($p) { return $p['status'] === 'active'; }));
} catch (Exception $e) {
    $stats = ['total_vitals' => 0, 'total_appointments' => 0, 'total_prescriptions' => 0, 'total_lab_orders' => 0, 'completed_appointments' => 0, 'active_prescriptions' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Medical History - Hospital CRM</title>
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
        
        .history-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .section-header h2 {
            color: #004685;
            font-size: 18px;
            margin: 0;
        }
        
        .section-content {
            padding: 20px;
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
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-completed { background: #28a745; color: white; }
        .status-active { background: #007bff; color: white; }
        .status-pending { background: #ffc107; color: black; }
        .status-cancelled { background: #dc3545; color: white; }
        
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .vital-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #004685;
        }
        
        .vital-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .vital-date {
            font-size: 12px;
            color: #666;
        }
        
        .vital-recorded-by {
            font-size: 12px;
            color: #004685;
            font-weight: 500;
        }
        
        .vital-values {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }
        
        .vital-item {
            text-align: center;
        }
        
        .vital-label {
            font-size: 11px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .vital-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-top: 2px;
        }
        
        .vital-unit {
            font-size: 10px;
            color: #999;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-data i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .patient-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .patient-info h3 {
            color: #004685;
            margin-bottom: 15px;
        }
        
        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .detail-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-file-medical"></i> My Medical History</h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Patient Information -->
        <div class="patient-info">
            <h3>Patient Information</h3>
            <div class="patient-details">
                <div class="detail-item">
                    <span class="detail-label">Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Patient ID</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['patient_id']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Age</span>
                    <span class="detail-value"><?php echo $patient['date_of_birth'] ? date_diff(date_create($patient['date_of_birth']), date_create('today'))->y . ' years' : 'N/A'; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Blood Group</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['blood_group'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Medical History</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['medical_history'] ?? 'None recorded'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Allergies</span>
                    <span class="detail-value"><?php echo htmlspecialchars($patient['allergies'] ?? 'None recorded'); ?></span>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_vitals']); ?></h3>
                <p>Vital Records</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_appointments']); ?></h3>
                <p>Total Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_prescriptions']); ?></h3>
                <p>Total Prescriptions</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_lab_orders']); ?></h3>
                <p>Lab Orders</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['completed_appointments']); ?></h3>
                <p>Completed Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['active_prescriptions']); ?></h3>
                <p>Active Prescriptions</p>
            </div>
        </div>

        <!-- Vitals History -->
        <div class="history-section">
            <div class="section-header">
                <h2><i class="fas fa-heartbeat"></i> Vital Signs History</h2>
            </div>
            <div class="section-content">
                <?php if (empty($vitals)): ?>
                    <div class="no-data">
                        <i class="fas fa-heartbeat"></i>
                        <h3>No Vital Records Found</h3>
                        <p>No vital signs have been recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="vitals-grid">
                        <?php foreach ($vitals as $vital): ?>
                            <div class="vital-card">
                                <div class="vital-header">
                                    <span class="vital-date"><?php echo date('M d, Y H:i', strtotime($vital['recorded_at'])); ?></span>
                                    <span class="vital-recorded-by">By: <?php echo htmlspecialchars($vital['recorded_by_name']); ?></span>
                                </div>
                                <div class="vital-values">
                                    <?php if ($vital['temperature']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Temperature</div>
                                            <div class="vital-value"><?php echo $vital['temperature']; ?>°C</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($vital['blood_pressure']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Blood Pressure</div>
                                            <div class="vital-value"><?php echo htmlspecialchars($vital['blood_pressure']); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($vital['heart_rate']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Heart Rate</div>
                                            <div class="vital-value"><?php echo $vital['heart_rate']; ?> <span class="vital-unit">bpm</span></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($vital['weight']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Weight</div>
                                            <div class="vital-value"><?php echo $vital['weight']; ?> <span class="vital-unit">kg</span></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($vital['height']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">Height</div>
                                            <div class="vital-value"><?php echo $vital['height']; ?> <span class="vital-unit">cm</span></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($vital['bmi']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">BMI</div>
                                            <div class="vital-value"><?php echo $vital['bmi']; ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($vital['oxygen_saturation']): ?>
                                        <div class="vital-item">
                                            <div class="vital-label">O2 Saturation</div>
                                            <div class="vital-value"><?php echo $vital['oxygen_saturation']; ?>%</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($vital['notes']): ?>
                                    <div style="margin-top: 10px; font-size: 12px; color: #666;">
                                        <strong>Notes:</strong> <?php echo htmlspecialchars($vital['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Appointments History -->
        <div class="history-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-check"></i> Appointments History</h2>
            </div>
            <div class="section-content">
                <?php if (empty($appointments)): ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No Appointments Found</h3>
                        <p>No appointments have been scheduled yet.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Doctor</th>
                                <th>Specialization</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></strong>
                                        <br><small><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['specialization']); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $appointment['appointment_type'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['reason'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lab Results History -->
        <div class="history-section">
            <div class="section-header">
                <h2><i class="fas fa-flask"></i> Laboratory Results</h2>
            </div>
            <div class="section-content">
                <?php if (empty($lab_results)): ?>
                    <div class="no-data">
                        <i class="fas fa-flask"></i>
                        <h3>No Lab Results Found</h3>
                        <p>No laboratory tests have been ordered yet.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Date</th>
                                <th>Doctor</th>
                                <th>Tests</th>
                                <th>Status</th>
                                <th>Total Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lab_results as $lab): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($lab['order_number']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($lab['order_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($lab['doctor_name']); ?></td>
                                    <td><?php echo $lab['test_count']; ?> tests (<?php echo $lab['completed_tests']; ?> completed)</td>
                                    <td>
                                        <span class="status-badge status-<?php echo $lab['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $lab['status'])); ?>
                                        </span>
                                    </td>
                                    <td>₹<?php echo number_format($lab['total_cost'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
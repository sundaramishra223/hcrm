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

// Get patient's prescriptions
$sql = "SELECT p.*, 
        CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        d.specialization,
        COUNT(pm.id) as medicine_count,
        SUM(pm.quantity) as total_medicines
        FROM prescriptions p
        JOIN doctors d ON p.doctor_id = d.id
        LEFT JOIN prescription_medicines pm ON p.id = pm.prescription_id
        WHERE p.patient_id = ?
        GROUP BY p.id
        ORDER BY p.prescription_date DESC";

$prescriptions = $db->query($sql, [$patient['id']])->fetchAll();

// Get prescription statistics
$stats = [];
try {
    $stats['total_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = ?", [$patient['id']])->fetch()['count'];
    $stats['active_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = ? AND status = 'active'", [$patient['id']])->fetch()['count'];
    $stats['completed_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = ? AND status = 'completed'", [$patient['id']])->fetch()['count'];
    $stats['total_medicines'] = $db->query("SELECT SUM(pm.quantity) as total FROM prescriptions p JOIN prescription_medicines pm ON p.id = pm.prescription_id WHERE p.patient_id = ?", [$patient['id']])->fetch()['total'] ?? 0;
} catch (Exception $e) {
    $stats = ['total_prescriptions' => 0, 'active_prescriptions' => 0, 'completed_prescriptions' => 0, 'total_medicines' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions - Hospital CRM</title>
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
            color: black;
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
        
        .prescriptions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        
        .prescription-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .prescription-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .prescription-header h3 {
            color: #004685;
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .prescription-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }
        
        .prescription-body {
            padding: 15px;
        }
        
        .prescription-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 14px;
            color: #333;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-active { background: #28a745; color: white; }
        .status-completed { background: #6c757d; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
        
        .medicines-list {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }
        
        .medicines-list h4 {
            color: #333;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .medicine-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .medicine-item:last-child {
            border-bottom: none;
        }
        
        .medicine-name {
            font-weight: 600;
            color: #333;
        }
        
        .medicine-details {
            font-size: 12px;
            color: #666;
        }
        
        .no-prescriptions {
            text-align: center;
            padding: 40px;
            color: #666;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .no-prescriptions i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .diagnosis-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
        }
        
        .diagnosis-box h4 {
            color: #856404;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .diagnosis-box p {
            color: #856404;
            font-size: 13px;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-prescription"></i> My Prescriptions</h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Statistics -->
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
                <h3><?php echo number_format($stats['completed_prescriptions']); ?></h3>
                <p>Completed Prescriptions</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_medicines']); ?></h3>
                <p>Total Medicines</p>
            </div>
        </div>

        <!-- Prescriptions -->
        <?php if (empty($prescriptions)): ?>
            <div class="no-prescriptions">
                <i class="fas fa-prescription-bottle"></i>
                <h3>No Prescriptions Found</h3>
                <p>You don't have any prescriptions yet.</p>
            </div>
        <?php else: ?>
            <div class="prescriptions-grid">
                <?php foreach ($prescriptions as $prescription): ?>
                    <div class="prescription-card">
                        <div class="prescription-header">
                            <h3>Prescription #<?php echo $prescription['id']; ?></h3>
                            <p>Prescribed by Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?> (<?php echo htmlspecialchars($prescription['specialization']); ?>)</p>
                        </div>
                        
                        <div class="prescription-body">
                            <div class="prescription-info">
                                <div class="info-item">
                                    <span class="info-label">Date</span>
                                    <span class="info-value"><?php echo date('M d, Y', strtotime($prescription['prescription_date'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status</span>
                                    <span class="status-badge status-<?php echo $prescription['status']; ?>">
                                        <?php echo ucfirst($prescription['status']); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Medicines</span>
                                    <span class="info-value"><?php echo $prescription['medicine_count']; ?> types</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Total Quantity</span>
                                    <span class="info-value"><?php echo $prescription['total_medicines']; ?> units</span>
                                </div>
                            </div>
                            
                            <?php if ($prescription['diagnosis']): ?>
                                <div class="diagnosis-box">
                                    <h4>Diagnosis</h4>
                                    <p><?php echo htmlspecialchars($prescription['diagnosis']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($prescription['notes']): ?>
                                <div class="diagnosis-box">
                                    <h4>Doctor's Notes</h4>
                                    <p><?php echo htmlspecialchars($prescription['notes']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Get medicines for this prescription -->
                            <?php 
                            $medicines = $db->query("
                                SELECT pm.*, m.name as medicine_name, m.strength, m.dosage_form
                                FROM prescription_medicines pm
                                JOIN medicines m ON pm.medicine_id = m.id
                                WHERE pm.prescription_id = ?
                                ORDER BY pm.id
                            ", [$prescription['id']])->fetchAll();
                            ?>
                            
                            <?php if (!empty($medicines)): ?>
                                <div class="medicines-list">
                                    <h4>Medicines</h4>
                                    <?php foreach ($medicines as $medicine): ?>
                                        <div class="medicine-item">
                                            <div>
                                                <div class="medicine-name"><?php echo htmlspecialchars($medicine['medicine_name']); ?></div>
                                                <div class="medicine-details">
                                                    <?php echo htmlspecialchars($medicine['strength']); ?> | 
                                                    <?php echo htmlspecialchars($medicine['dosage_form']); ?> | 
                                                    Qty: <?php echo $medicine['quantity']; ?>
                                                </div>
                                            </div>
                                            <div class="medicine-details">
                                                <strong>Dosage:</strong> <?php echo htmlspecialchars($medicine['dosage'] ?? 'N/A'); ?><br>
                                                <strong>Frequency:</strong> <?php echo htmlspecialchars($medicine['frequency'] ?? 'N/A'); ?><br>
                                                <strong>Duration:</strong> <?php echo htmlspecialchars($medicine['duration'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
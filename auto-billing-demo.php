<?php
session_start();
require_once 'config/database.php';
require_once 'auto-billing-system.php';

// Demo login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'admin';
}

$db = new Database();
$billing = new AutoBillingSystem($db);
$message = '';

// Handle demo actions
if ($_POST) {
    $patient_id = $_POST['patient_id'];
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_consultation':
            // Simulate consultation
            $appointment_id = $_POST['appointment_id'] ?? 1;
            if ($billing->addConsultation($patient_id, $appointment_id)) {
                $message = "✅ Consultation automatically added to bill!";
            } else {
                $message = "❌ Failed to add consultation";
            }
            break;
            
        case 'add_ambulance':
            // Simulate ambulance service
            $ambulance_booking_id = $_POST['booking_id'] ?? 1;
            if ($billing->addAmbulanceService($patient_id, $ambulance_booking_id)) {
                $message = "✅ Ambulance service automatically added to bill!";
            } else {
                $message = "❌ Failed to add ambulance service";
            }
            break;
            
        case 'add_bed':
            // Simulate bed assignment
            $bed_assignment_id = $_POST['assignment_id'] ?? 1;
            if ($billing->addBedService($patient_id, $bed_assignment_id)) {
                $message = "✅ Bed charges automatically added to bill!";
            } else {
                $message = "❌ Failed to add bed charges";
            }
            break;
            
        case 'add_lab_tests':
            // Simulate lab tests
            $lab_order_id = $_POST['lab_order_id'] ?? 1;
            if ($billing->addLabTests($patient_id, $lab_order_id)) {
                $message = "✅ Lab tests automatically added to bill!";
            } else {
                $message = "❌ Failed to add lab tests";
            }
            break;
            
        case 'add_equipment':
            // Simulate equipment usage
            $equipment_id = $_POST['equipment_id'] ?? 1;
            $usage_hours = $_POST['usage_hours'] ?? 2;
            $hourly_rate = $_POST['hourly_rate'] ?? 150;
            if ($billing->addEquipmentUsage($patient_id, $equipment_id, $usage_hours, $hourly_rate)) {
                $message = "✅ Equipment usage automatically added to bill!";
            } else {
                $message = "❌ Failed to add equipment usage";
            }
            break;
    }
}

// Get patients for demo
$patients = $db->query("SELECT id, patient_id, first_name, last_name FROM patients LIMIT 10")->fetchAll();

// Get sample data
$appointments = $db->query("SELECT id, patient_id, appointment_date FROM appointments LIMIT 5")->fetchAll();
$ambulance_bookings = $db->query("SELECT id, pickup_address, destination_address FROM ambulance_bookings LIMIT 5")->fetchAll();
$bed_assignments = $db->query("SELECT id, patient_id FROM bed_assignments LIMIT 5")->fetchAll();
$equipment = $db->query("SELECT id, name FROM equipment LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Billing Demo - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .demo-container {
            padding: 20px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }
        
        .demo-section {
            background: var(--bg-card);
            margin-bottom: 20px;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
        }
        
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .service-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        
        .service-card h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .btn {
            background: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
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
        
        .bill-summary {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .bill-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .total-row {
            font-weight: bold;
            font-size: 1.1rem;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="demo-container">
        <h1><i class="fas fa-credit-card"></i> Automatic Billing System Demo</h1>
        <p><strong>Bhai dekho kaise automatic billing work kar rha hai!</strong> Jab bhi koi service use hoti hai, automatically bill mein add ho jati hai.</p>
        
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, '✅') === 0 ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Patient Selection -->
        <div class="demo-section">
            <h2><i class="fas fa-user"></i> Select Patient for Billing</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                <?php foreach ($patients as $patient): ?>
                    <div class="patient-card" style="padding: 10px; background: var(--bg-secondary); border-radius: 5px; text-align: center;">
                        <strong><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></strong>
                        <br><small>ID: <?php echo htmlspecialchars($patient['patient_id']); ?></small>
                        <br><button onclick="selectPatient(<?php echo $patient['id']; ?>)" class="btn" style="margin-top: 5px; padding: 5px 10px;">Select</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Service Addition Forms -->
        <div class="demo-section">
            <h2><i class="fas fa-plus"></i> Add Services (Auto-Billing)</h2>
            <div class="service-grid">
                
                <!-- Consultation -->
                <div class="service-card">
                    <h3><i class="fas fa-stethoscope"></i> Consultation</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_consultation">
                        <div class="form-group">
                            <label>Patient ID</label>
                            <input type="number" name="patient_id" id="consultation_patient" required>
                        </div>
                        <div class="form-group">
                            <label>Appointment ID</label>
                            <select name="appointment_id">
                                <?php foreach ($appointments as $apt): ?>
                                    <option value="<?php echo $apt['id']; ?>">
                                        Appointment #<?php echo $apt['id']; ?> - <?php echo $apt['appointment_date']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn">Add Consultation (₹500)</button>
                    </form>
                </div>
                
                <!-- Ambulance -->
                <div class="service-card">
                    <h3><i class="fas fa-ambulance"></i> Ambulance Service</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_ambulance">
                        <div class="form-group">
                            <label>Patient ID</label>
                            <input type="number" name="patient_id" id="ambulance_patient" required>
                        </div>
                        <div class="form-group">
                            <label>Booking ID</label>
                            <select name="booking_id">
                                <?php foreach ($ambulance_bookings as $booking): ?>
                                    <option value="<?php echo $booking['id']; ?>">
                                        Booking #<?php echo $booking['id']; ?> - <?php echo substr($booking['pickup_address'], 0, 20); ?>...
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn">Add Ambulance (₹500-1200)</button>
                    </form>
                </div>
                
                <!-- Bed Assignment -->
                <div class="service-card">
                    <h3><i class="fas fa-bed"></i> Bed Charges</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_bed">
                        <div class="form-group">
                            <label>Patient ID</label>
                            <input type="number" name="patient_id" id="bed_patient" required>
                        </div>
                        <div class="form-group">
                            <label>Assignment ID</label>
                            <select name="assignment_id">
                                <?php foreach ($bed_assignments as $assignment): ?>
                                    <option value="<?php echo $assignment['id']; ?>">
                                        Assignment #<?php echo $assignment['id']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn">Add Bed Charges (₹500/day)</button>
                    </form>
                </div>
                
                <!-- Lab Tests -->
                <div class="service-card">
                    <h3><i class="fas fa-flask"></i> Lab Tests</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_lab_tests">
                        <div class="form-group">
                            <label>Patient ID</label>
                            <input type="number" name="patient_id" id="lab_patient" required>
                        </div>
                        <div class="form-group">
                            <label>Lab Order ID</label>
                            <input type="number" name="lab_order_id" value="1" required>
                        </div>
                        <button type="submit" class="btn">Add Lab Tests (₹150-800)</button>
                    </form>
                </div>
                
                <!-- Equipment Usage -->
                <div class="service-card">
                    <h3><i class="fas fa-tools"></i> Equipment Usage</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_equipment">
                        <div class="form-group">
                            <label>Patient ID</label>
                            <input type="number" name="patient_id" id="equipment_patient" required>
                        </div>
                        <div class="form-group">
                            <label>Equipment</label>
                            <select name="equipment_id">
                                <?php foreach ($equipment as $eq): ?>
                                    <option value="<?php echo $eq['id']; ?>">
                                        <?php echo htmlspecialchars($eq['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Usage Hours</label>
                            <input type="number" name="usage_hours" value="2" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>Rate per Hour (₹)</label>
                            <input type="number" name="hourly_rate" value="150" required>
                        </div>
                        <button type="submit" class="btn">Add Equipment Usage</button>
                    </form>
                </div>
                
                <!-- Manual Bill Item -->
                <div class="service-card">
                    <h3><i class="fas fa-plus-circle"></i> Manual Service</h3>
                    <form method="POST" action="billing.php">
                        <div class="form-group">
                            <label>Patient ID</label>
                            <input type="number" name="patient_id" id="manual_patient" required>
                        </div>
                        <div class="form-group">
                            <label>Service Name</label>
                            <input type="text" name="service_name" placeholder="e.g., X-Ray, Surgery" required>
                        </div>
                        <div class="form-group">
                            <label>Amount (₹)</label>
                            <input type="number" name="amount" value="200" required>
                        </div>
                        <button type="submit" class="btn">Add Manual Service</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- How it Works -->
        <div class="demo-section">
            <h2><i class="fas fa-info-circle"></i> How Automatic Billing Works</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div style="padding: 15px; background: #e3f2fd; border-radius: 8px;">
                    <h4>🏥 Service Used</h4>
                    <p>Patient takes any service: consultation, ambulance, bed, lab test, medicine, equipment</p>
                </div>
                <div style="padding: 15px; background: #f3e5f5; border-radius: 8px;">
                    <h4>⚡ Auto Detection</h4>
                    <p>System automatically detects when service is completed/used</p>
                </div>
                <div style="padding: 15px; background: #e8f5e8; border-radius: 8px;">
                    <h4>💰 Bill Creation</h4>
                    <p>Service is automatically added to patient's bill with correct pricing</p>
                </div>
                <div style="padding: 15px; background: #fff3e0; border-radius: 8px;">
                    <h4>📋 Final Bill</h4>
                    <p>Admin/Receptionist can review and collect payment from consolidated bill</p>
                </div>
            </div>
        </div>
        
        <!-- Live Bill Preview -->
        <div class="demo-section">
            <h2><i class="fas fa-receipt"></i> Live Bill Preview</h2>
            <div id="billPreview">
                <p>Select a patient and add services to see their bill here!</p>
            </div>
            <button onclick="loadBillPreview()" class="btn" style="width: auto;">Refresh Bill Preview</button>
        </div>
    </div>
    
    <script>
        let selectedPatientId = null;
        
        function selectPatient(patientId) {
            selectedPatientId = patientId;
            
            // Update all patient ID fields
            document.getElementById('consultation_patient').value = patientId;
            document.getElementById('ambulance_patient').value = patientId;
            document.getElementById('bed_patient').value = patientId;
            document.getElementById('lab_patient').value = patientId;
            document.getElementById('equipment_patient').value = patientId;
            document.getElementById('manual_patient').value = patientId;
            
            // Highlight selected patient
            document.querySelectorAll('.patient-card').forEach(card => {
                card.style.border = '1px solid var(--border-color)';
            });
            event.target.closest('.patient-card').style.border = '3px solid var(--primary-color)';
            
            loadBillPreview();
        }
        
        function loadBillPreview() {
            if (!selectedPatientId) return;
            
            // This would normally be an AJAX call to get bill data
            document.getElementById('billPreview').innerHTML = `
                <div class="bill-summary">
                    <h3>Patient ID: ${selectedPatientId} - Current Bill</h3>
                    <div class="bill-item">
                        <span>Loading bill items...</span>
                        <span>Please add services to see them here</span>
                    </div>
                    <div class="bill-item total-row">
                        <span>Total Amount</span>
                        <span>₹0.00</span>
                    </div>
                </div>
            `;
        }
    </script>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$user_role = $_SESSION['role'];
$message = '';
$error = '';

// Get pre-selected patient if coming from patient list
$selected_patient_id = $_GET['patient_id'] ?? '';

// Handle form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'book_appointment') {
    try {
        // Check for appointment conflicts with simple query
        $conflict_result = $db->query(
            "SELECT COUNT(*) as conflict_count FROM appointments 
             WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status != 'cancelled'",
            [
                $_POST['doctor_id'],
                $_POST['appointment_date'],
                $_POST['appointment_time']
            ]
        )->fetch();
        
        if ($conflict_result['conflict_count'] > 0) {
            $error = "Doctor is not available at the selected time. Please choose a different time slot.";
        } else {
            // Generate appointment number
            $appointment_number = 'APT' . date('Ymd') . sprintf('%04d', rand(1000, 9999));
            
            // Insert appointment with exact table columns
            $sql = "INSERT INTO appointments (hospital_id, patient_id, doctor_id, appointment_date, appointment_time, appointment_type, status, reason, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $db->query($sql, [
                1, // hospital_id
                $_POST['patient_id'],
                $_POST['doctor_id'],
                $_POST['appointment_date'],
                $_POST['appointment_time'],
                $_POST['appointment_type'] ?? 'consultation',
                'scheduled', // default status
                $_POST['chief_complaint'] ?? 'General consultation',
                $_POST['notes'] ?? 'Appointment booked online',
                $_SESSION['user_id']
            ]);
            
            $message = "✅ Appointment booked successfully for " . $_POST['appointment_date'] . " at " . $_POST['appointment_time'] . "!";
        }
    } catch (Exception $e) {
        $error = "Error booking appointment: " . $e->getMessage();
        // Debug info
        error_log("Appointment booking error: " . $e->getMessage());
        error_log("POST data: " . print_r($_POST, true));
    }
}

// Get available doctors
$doctors = $db->query("
    SELECT d.*, CONCAT(d.first_name, ' ', d.last_name) as full_name, dept.name as department_name 
    FROM doctors d 
    LEFT JOIN departments dept ON d.department_id = dept.id
    WHERE d.is_available = 1
    ORDER BY d.first_name, d.last_name
")->fetchAll();

// Get patients (for admin/receptionist)
$patients = [];
if (in_array($user_role, ['admin', 'receptionist'])) {
    $patients = $db->query("
        SELECT id, patient_id, CONCAT(first_name, ' ', last_name) as full_name, phone 
        FROM patients 
        ORDER BY first_name, last_name
    ")->fetchAll();
} else if ($user_role === 'patient') {
    // Get current patient's info
    $patients = $db->query("
        SELECT id, patient_id, CONCAT(first_name, ' ', last_name) as full_name, phone 
        FROM patients 
        WHERE user_id = ?
    ", [$_SESSION['user_id']])->fetchAll();
    
    if (!empty($patients)) {
        $selected_patient_id = $patients[0]['id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Hospital CRM</title>
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
            max-width: 800px;
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
        
        .appointment-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #004685;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .doctor-card {
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .doctor-card:hover {
            border-color: #004685;
            background: #f8f9ff;
        }
        
        .doctor-card.selected {
            border-color: #004685;
            background: #e3f2fd;
        }
        
        .doctor-card h4 {
            color: #004685;
            margin-bottom: 5px;
        }
        
        .doctor-card p {
            color: #666;
            font-size: 14px;
            margin: 2px 0;
        }
        
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .time-slot {
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .time-slot:hover {
            border-color: #004685;
            background: #f8f9ff;
        }
        
        .time-slot.selected {
            border-color: #004685;
            background: #004685;
            color: white;
        }
        
        .time-slot.unavailable {
            background: #f8f8f8;
            color: #ccc;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
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
        
        .appointment-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #004685;
        }
        
        .appointment-summary h3 {
            color: #004685;
            margin-bottom: 15px;
        }
        
        .appointment-summary p {
            margin: 5px 0;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .time-slots {
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Book Appointment</h1>
            <div>
                <a href="<?php echo $user_role === 'patient' ? 'dashboard.php' : 'patients.php'; ?>" class="btn btn-secondary">← Back</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="appointment-form">
            <form method="POST" id="appointmentForm">
                <input type="hidden" name="action" value="book_appointment">
                <input type="hidden" name="doctor_id" id="selected_doctor_id" value="">
                <input type="hidden" name="appointment_time" id="selected_time" value="">
                
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                <div class="form-group">
                    <label for="patient_id">Select Patient *</label>
                    <select name="patient_id" id="patient_id" required>
                        <option value="">Choose Patient</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" 
                                    <?php echo $selected_patient_id == $patient['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['patient_id'] . ') - ' . $patient['phone']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                    <input type="hidden" name="patient_id" value="<?php echo $selected_patient_id; ?>">
                    <div class="alert alert-success">
                        Booking appointment for: <strong><?php echo htmlspecialchars($patients[0]['full_name'] ?? 'Unknown'); ?></strong>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Select Doctor *</label>
                    <div id="doctorsList">
                        <?php foreach ($doctors as $doctor): ?>
                            <div class="doctor-card" onclick="selectDoctor(<?php echo $doctor['id']; ?>, this)">
                                <h4>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h4>
                                <p><strong>Specialization:</strong> <?php echo htmlspecialchars($doctor['specialization'] ?? 'General'); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($doctor['department_name'] ?? 'General'); ?></p>
                                <p><strong>Consultation Fee:</strong> ₹<?php echo number_format($doctor['consultation_fee'], 2); ?></p>
                                <p><strong>Experience:</strong> <?php echo $doctor['experience_years']; ?> years</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="appointment_date">Appointment Date *</label>
                        <input type="date" name="appointment_date" id="appointment_date" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" 
                               onchange="loadTimeSlots()" required>
                    </div>
                    <div class="form-group">
                        <label for="appointment_type">Appointment Type</label>
                        <select name="appointment_type" id="appointment_type">
                            <option value="consultation">Consultation</option>
                            <option value="followup">Follow-up</option>
                            <option value="emergency">Emergency</option>
                            <option value="video_call">Video Call</option>
                            <option value="home_visit">Home Visit</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Available Time Slots *</label>
                    <div class="time-slots" id="timeSlots">
                        <p style="color: #666; text-align: center; padding: 20px;">
                            Please select a doctor and date to view available time slots
                        </p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="chief_complaint">Chief Complaint</label>
                    <textarea name="chief_complaint" id="chief_complaint" 
                              placeholder="Describe the main reason for this appointment..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea name="notes" id="notes" 
                              placeholder="Any additional information or special requests..."></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 30px;">
                    <button type="button" onclick="window.history.back()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Book Appointment</button>
                </div>
            </form>
            
            <div class="appointment-summary" id="appointmentSummary" style="display: none;">
                <h3>Appointment Summary</h3>
                <p id="summaryPatient"></p>
                <p id="summaryDoctor"></p>
                <p id="summaryDateTime"></p>
                <p id="summaryType"></p>
                <p id="summaryFee"></p>
            </div>
        </div>
    </div>
    
    <script>
        let selectedDoctor = null;
        let selectedTime = null;
        let doctors = <?php echo json_encode($doctors); ?>;
        
        function selectDoctor(doctorId, element) {
            // Remove previous selection
            document.querySelectorAll('.doctor-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            element.classList.add('selected');
            
            // Store selected doctor
            selectedDoctor = doctors.find(d => d.id == doctorId);
            document.getElementById('selected_doctor_id').value = doctorId;
            
            // Load time slots if date is selected
            if (document.getElementById('appointment_date').value) {
                loadTimeSlots();
            }
            
            updateSummary();
        }
        
        function loadTimeSlots() {
            const doctorId = document.getElementById('selected_doctor_id').value;
            const date = document.getElementById('appointment_date').value;
            
            if (!doctorId || !date) {
                return;
            }
            
            // Generate time slots (9 AM to 5 PM, 30-minute intervals)
            const timeSlots = [
                '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
                '12:00', '12:30', '14:00', '14:30', '15:00', '15:30',
                '16:00', '16:30', '17:00'
            ];
            
            const timeSlotsContainer = document.getElementById('timeSlots');
            timeSlotsContainer.innerHTML = '';
            
            timeSlots.forEach(time => {
                const slot = document.createElement('div');
                slot.className = 'time-slot';
                slot.textContent = time;
                slot.onclick = () => selectTime(time, slot);
                timeSlotsContainer.appendChild(slot);
            });
        }
        
        function selectTime(time, element) {
            // Remove previous selection
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            // Add selection to clicked slot
            element.classList.add('selected');
            
            // Store selected time
            selectedTime = time;
            document.getElementById('selected_time').value = time;
            
            updateSummary();
            updateSubmitButton();
        }
        
        function updateSummary() {
            const patientSelect = document.getElementById('patient_id');
            const appointmentDate = document.getElementById('appointment_date').value;
            const appointmentType = document.getElementById('appointment_type').value;
            
            if (selectedDoctor && selectedTime && appointmentDate) {
                document.getElementById('appointmentSummary').style.display = 'block';
                
                // Update summary content
                const patientName = patientSelect ? patientSelect.options[patientSelect.selectedIndex].text : '<?php echo htmlspecialchars($patients[0]['full_name'] ?? 'Unknown'); ?>';
                
                document.getElementById('summaryPatient').innerHTML = '<strong>Patient:</strong> ' + patientName;
                document.getElementById('summaryDoctor').innerHTML = '<strong>Doctor:</strong> Dr. ' + selectedDoctor.full_name;
                document.getElementById('summaryDateTime').innerHTML = '<strong>Date & Time:</strong> ' + appointmentDate + ' at ' + selectedTime;
                document.getElementById('summaryType').innerHTML = '<strong>Type:</strong> ' + appointmentType.charAt(0).toUpperCase() + appointmentType.slice(1);
                document.getElementById('summaryFee').innerHTML = '<strong>Consultation Fee:</strong> ₹' + Number(selectedDoctor.consultation_fee).toFixed(2);
            } else {
                document.getElementById('appointmentSummary').style.display = 'none';
            }
        }
        
        function updateSubmitButton() {
            const patientId = document.getElementById('patient_id') ? document.getElementById('patient_id').value : '<?php echo $selected_patient_id; ?>';
            const doctorId = document.getElementById('selected_doctor_id').value;
            const appointmentDate = document.getElementById('appointment_date').value;
            const appointmentTime = document.getElementById('selected_time').value;
            
            const submitBtn = document.getElementById('submitBtn');
            
            if (patientId && doctorId && appointmentDate && appointmentTime) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Book Appointment';
            } else {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Please complete all required fields';
            }
        }
        
        // Event listeners
        document.getElementById('appointment_date').addEventListener('change', updateSummary);
        document.getElementById('appointment_type').addEventListener('change', updateSummary);
        
        if (document.getElementById('patient_id')) {
            document.getElementById('patient_id').addEventListener('change', updateSummary);
        }
        
        // Initialize
        updateSubmitButton();
    </script>
</body>
</html>

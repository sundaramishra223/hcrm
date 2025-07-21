<?php
session_start();
require_once 'config/database.php';
require_once 'includes/appointment-helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$appointmentHelper = new AppointmentHelper($db);
$message = '';

// Handle doctor assignment
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'assign_doctor') {
    try {
        $result = $appointmentHelper->assignPatientToDoctor($_POST['patient_id'], $_POST['doctor_id']);
        if ($result) {
            $message = "✅ Doctor assigned successfully! Consultation bill created automatically.";
        } else {
            $message = "❌ Failed to assign doctor.";
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Get patients without assigned doctors
$patients = $db->query("
    SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as full_name,
           CONCAT(d.first_name, ' ', d.last_name) as assigned_doctor_name
    FROM patients p 
    LEFT JOIN doctors d ON p.assigned_doctor_id = d.id
    ORDER BY p.created_at DESC
    LIMIT 10
")->fetchAll();

// Get available doctors with consultation fees
$doctors = $db->query("
    SELECT d.*, CONCAT(d.first_name, ' ', d.last_name) as full_name
    FROM doctors d 
    WHERE d.is_available = 1 AND d.consultation_fee > 0
    ORDER BY d.first_name, d.last_name
")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Auto-Billing Test - Hospital CRM</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; }
        .message { padding: 15px; margin: 15px 0; border-radius: 5px; background: #d4edda; color: #155724; }
        .form-group { margin: 15px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .fee { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏥 Auto-Billing Test Page</h1>
        
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <h2>📋 Assign Doctor to Patient (Auto-Bill Generation)</h2>
        <form method="POST">
            <input type="hidden" name="action" value="assign_doctor">
            
            <div class="form-group">
                <label>Select Patient:</label>
                <select name="patient_id" required>
                    <option value="">Choose Patient...</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo $patient['id']; ?>">
                            <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                            <?php if ($patient['assigned_doctor_name']): ?>
                                - Currently: Dr. <?php echo htmlspecialchars($patient['assigned_doctor_name']); ?>
                            <?php else: ?>
                                - No Doctor Assigned
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Select Doctor:</label>
                <select name="doctor_id" required>
                    <option value="">Choose Doctor...</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>">
                            Dr. <?php echo htmlspecialchars($doctor['full_name']); ?> - 
                            Fee: ₹<?php echo number_format($doctor['consultation_fee'], 2); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn">Assign Doctor & Auto-Generate Bill</button>
        </form>
        
        <h2>👩‍⚕️ Available Doctors & Fees</h2>
        <table>
            <thead>
                <tr>
                    <th>Doctor Name</th>
                    <th>Specialization</th>
                    <th>Consultation Fee</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($doctors as $doctor): ?>
                    <tr>
                        <td>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($doctor['specialization'] ?? 'General'); ?></td>
                        <td class="fee">₹<?php echo number_format($doctor['consultation_fee'], 2); ?></td>
                        <td>Available</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>🧾 How Auto-Billing Works</h2>
        <ul>
            <li>✅ When you assign a doctor to a patient</li>
            <li>✅ System automatically creates a consultation bill</li>
            <li>✅ Bill amount = Doctor's consultation fee</li>
            <li>✅ Bill status = Pending (ready for payment)</li>
            <li>✅ Bill includes consultation item details</li>
        </ul>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="dashboard.php" class="btn">← Back to Dashboard</a>
            <a href="billing.php" class="btn" style="background: #28a745;">View Bills</a>
            <a href="book-appointment.php" class="btn" style="background: #17a2b8;">Book Appointment</a>
        </div>
    </div>
</body>
</html>
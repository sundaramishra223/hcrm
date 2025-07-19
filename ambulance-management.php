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
if (!in_array($user_role, ['admin', 'receptionist', 'doctor', 'nurse'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';
$search = $_GET['search'] ?? '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_ambulance':
                    $sql = "INSERT INTO ambulances (vehicle_number, vehicle_type, driver_name, driver_phone, capacity, equipment, status, location, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $db->query($sql, [
                        $_POST['vehicle_number'],
                        $_POST['vehicle_type'],
                        $_POST['driver_name'],
                        $_POST['driver_phone'],
                        $_POST['capacity'],
                        $_POST['equipment'],
                        $_POST['status'],
                        $_POST['location']
                    ]);
                    showSuccessPopup("Ambulance added successfully!", "ambulance-management.php");
                    break;

                case 'book_ambulance':
                    $sql = "INSERT INTO ambulance_bookings (patient_id, ambulance_id, pickup_location, destination, pickup_time, emergency_type, patient_condition, contact_person, contact_phone, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())";
                    $db->query($sql, [
                        $_POST['patient_id'],
                        $_POST['ambulance_id'],
                        $_POST['pickup_location'],
                        $_POST['destination'],
                        $_POST['pickup_time'],
                        $_POST['emergency_type'],
                        $_POST['patient_condition'],
                        $_POST['contact_person'],
                        $_POST['contact_phone'],
                        $_SESSION['user_id']
                    ]);
                    
                    // Update ambulance status
                    $db->query("UPDATE ambulances SET status = 'assigned' WHERE id = ?", [$_POST['ambulance_id']]);
                    
                    showSuccessPopup("Ambulance booked successfully!", "ambulance-management.php");
                    break;

                case 'update_booking':
                    $sql = "UPDATE ambulance_bookings SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?";
                    $db->query($sql, [
                        $_POST['status'],
                        $_POST['notes'] ?? '',
                        $_POST['booking_id']
                    ]);
                    
                    // Update ambulance status based on booking status
                    if ($_POST['status'] === 'completed' || $_POST['status'] === 'cancelled') {
                        $db->query("UPDATE ambulances SET status = 'available' WHERE id = (SELECT ambulance_id FROM ambulance_bookings WHERE id = ?)", [$_POST['booking_id']]);
                    }
                    
                    showSuccessPopup("Booking updated successfully!", "ambulance-management.php");
                    break;

                case 'update_ambulance':
                    $sql = "UPDATE ambulances SET vehicle_number = ?, vehicle_type = ?, driver_name = ?, driver_phone = ?, capacity = ?, equipment = ?, status = ?, location = ?, updated_at = NOW() WHERE id = ?";
                    $db->query($sql, [
                        $_POST['vehicle_number'],
                        $_POST['vehicle_type'],
                        $_POST['driver_name'],
                        $_POST['driver_phone'],
                        $_POST['capacity'],
                        $_POST['equipment'],
                        $_POST['status'],
                        $_POST['location'],
                        $_POST['ambulance_id']
                    ]);
                    showSuccessPopup("Ambulance updated successfully!", "ambulance-management.php");
                    break;
            }
        } catch (Exception $e) {
            showErrorPopup("Error: " . $e->getMessage());
        }
    }
}

// Get ambulances
$ambulances = $db->query("
    SELECT a.*, 
    COUNT(ab.id) as total_bookings,
    COUNT(CASE WHEN ab.status = 'active' THEN 1 END) as active_bookings
    FROM ambulances a
    LEFT JOIN ambulance_bookings ab ON a.id = ab.ambulance_id
    GROUP BY a.id
    ORDER BY a.created_at DESC
")->fetchAll();

// Get ambulance bookings
$bookings = $db->query("
    SELECT ab.*, 
    a.vehicle_number, a.vehicle_type, a.driver_name, a.driver_phone,
    p.first_name, p.last_name, p.phone as patient_phone,
    CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM ambulance_bookings ab
    JOIN ambulances a ON ab.ambulance_id = a.id
    LEFT JOIN patients p ON ab.patient_id = p.id
    LEFT JOIN users u ON ab.created_by = u.id
    ORDER BY ab.created_at DESC
")->fetchAll();

// Get patients for booking
$patients = $db->query("SELECT id, first_name, last_name, phone, patient_id FROM patients ORDER BY first_name")->fetchAll();

// Get statistics
$stats = [];
try {
    $stats['total_ambulances'] = count($ambulances);
    $stats['available_ambulances'] = count(array_filter($ambulances, function($a) { return $a['status'] === 'available'; }));
    $stats['total_bookings'] = count($bookings);
    $stats['active_bookings'] = count(array_filter($bookings, function($b) { return $b['status'] === 'active'; }));
    $stats['today_bookings'] = $db->query("SELECT COUNT(*) as count FROM ambulance_bookings WHERE DATE(created_at) = CURDATE()")->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_ambulances' => 0, 'available_ambulances' => 0, 'total_bookings' => 0, 'active_bookings' => 0, 'today_bookings' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ambulance Management - Hospital CRM</title>
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
            max-width: 1400px;
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
            margin-left: 10px;
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
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 10px 10px 0 0;
            overflow: hidden;
            margin-bottom: 0;
        }
        
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .tab.active {
            background: #004685;
            color: white;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tab-pane {
            display: none;
            padding: 20px;
        }
        
        .tab-pane.active {
            display: block;
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
        
        .status-available { background: #28a745; color: white; }
        .status-assigned { background: #007bff; color: white; }
        .status-maintenance { background: #ffc107; color: black; }
        .status-out_of_service { background: #dc3545; color: white; }
        
        .status-scheduled { background: #17a2b8; color: white; }
        .status-active { background: #28a745; color: white; }
        .status-completed { background: #6c757d; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
        
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
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e1e1e1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #004685;
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
            font-size: 14px;
        }
        
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        
        .emergency-badge {
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .priority-high { border-left: 4px solid #dc3545; }
        .priority-medium { border-left: 4px solid #ffc107; }
        .priority-low { border-left: 4px solid #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-ambulance"></i> Ambulance Management</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button onclick="openModal('ambulanceModal')" class="btn btn-primary">+ Add Ambulance</button>
                <button onclick="openModal('bookingModal')" class="btn btn-success">+ Book Ambulance</button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_ambulances']); ?></h3>
                <p>Total Ambulances</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['available_ambulances']); ?></h3>
                <p>Available Ambulances</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_bookings']); ?></h3>
                <p>Total Bookings</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['active_bookings']); ?></h3>
                <p>Active Bookings</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['today_bookings']); ?></h3>
                <p>Today's Bookings</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('ambulances')">Ambulances</button>
            <button class="tab" onclick="showTab('bookings')">Bookings</button>
        </div>

        <!-- Ambulances Tab -->
        <div class="tab-content">
            <div id="ambulances" class="tab-pane active">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Vehicle Number</th>
                            <th>Type</th>
                            <th>Driver</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Bookings</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ambulances)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px; color: #666;">
                                    No ambulances registered yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ambulances as $ambulance): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ambulance['vehicle_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ambulance['vehicle_type']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($ambulance['driver_name']); ?>
                                        <br><small><?php echo htmlspecialchars($ambulance['driver_phone']); ?></small>
                                    </td>
                                    <td><?php echo $ambulance['capacity']; ?> patients</td>
                                    <td>
                                        <span class="status-badge status-<?php echo $ambulance['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $ambulance['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ambulance['location']); ?></td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo $ambulance['total_bookings']; ?> total</span>
                                        <?php if ($ambulance['active_bookings'] > 0): ?>
                                            <span class="badge badge-success"><?php echo $ambulance['active_bookings']; ?> active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="editAmbulance(<?php echo htmlspecialchars(json_encode($ambulance)); ?>)" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">Edit</button>
                                        <?php if ($ambulance['status'] === 'available'): ?>
                                            <button onclick="bookAmbulance(<?php echo $ambulance['id']; ?>)" class="btn btn-success" style="font-size: 12px; padding: 5px 10px;">Book</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bookings Tab -->
            <div id="bookings" class="tab-pane">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Patient</th>
                            <th>Ambulance</th>
                            <th>Pickup</th>
                            <th>Destination</th>
                            <th>Emergency Type</th>
                            <th>Status</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 30px; color: #666;">
                                    No ambulance bookings found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr class="priority-<?php echo $booking['emergency_type'] === 'critical' ? 'high' : ($booking['emergency_type'] === 'urgent' ? 'medium' : 'low'); ?>">
                                    <td><strong>#<?php echo $booking['id']; ?></strong></td>
                                    <td>
                                        <?php if ($booking['patient_id']): ?>
                                            <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                            <br><small><?php echo htmlspecialchars($booking['patient_phone']); ?></small>
                                        <?php else: ?>
                                            <span class="emergency-badge">EMERGENCY</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($booking['vehicle_number']); ?>
                                        <br><small><?php echo htmlspecialchars($booking['driver_name']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($booking['pickup_location']); ?>
                                        <br><small><?php echo date('M d, H:i', strtotime($booking['pickup_time'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($booking['destination']); ?></td>
                                    <td>
                                        <span class="emergency-badge"><?php echo ucfirst($booking['emergency_type']); ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($booking['contact_person']); ?>
                                        <br><small><?php echo htmlspecialchars($booking['contact_phone']); ?></small>
                                    </td>
                                    <td>
                                        <button onclick="updateBooking(<?php echo $booking['id']; ?>)" class="btn btn-primary" style="font-size: 12px; padding: 5px 10px;">Update</button>
                                        <button onclick="viewBooking(<?php echo htmlspecialchars(json_encode($booking)); ?>)" class="btn btn-secondary" style="font-size: 12px; padding: 5px 10px;">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Ambulance Modal -->
    <div id="ambulanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Ambulance</h2>
                <button type="button" class="close" onclick="closeModal('ambulanceModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_ambulance">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vehicle_number">Vehicle Number *</label>
                            <input type="text" id="vehicle_number" name="vehicle_number" required>
                        </div>
                        <div class="form-group">
                            <label for="vehicle_type">Vehicle Type *</label>
                            <select id="vehicle_type" name="vehicle_type" required>
                                <option value="">Select Type</option>
                                <option value="Basic Life Support">Basic Life Support</option>
                                <option value="Advanced Life Support">Advanced Life Support</option>
                                <option value="Neonatal">Neonatal</option>
                                <option value="Cardiac">Cardiac</option>
                                <option value="Trauma">Trauma</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="driver_name">Driver Name *</label>
                            <input type="text" id="driver_name" name="driver_name" required>
                        </div>
                        <div class="form-group">
                            <label for="driver_phone">Driver Phone *</label>
                            <input type="tel" id="driver_phone" name="driver_phone" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="capacity">Patient Capacity *</label>
                            <input type="number" id="capacity" name="capacity" min="1" max="10" value="2" required>
                        </div>
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" required>
                                <option value="available">Available</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="out_of_service">Out of Service</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Current Location</label>
                        <input type="text" id="location" name="location" placeholder="e.g., Hospital Garage, City Center">
                    </div>
                    
                    <div class="form-group">
                        <label for="equipment">Equipment</label>
                        <textarea id="equipment" name="equipment" placeholder="List of equipment available in this ambulance"></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeModal('ambulanceModal')" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Ambulance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Book Ambulance Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Book Ambulance</h2>
                <button type="button" class="close" onclick="closeModal('bookingModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="book_ambulance">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="patient_id">Patient</label>
                            <select id="patient_id" name="patient_id">
                                <option value="">Emergency (No Patient)</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="ambulance_id">Ambulance *</label>
                            <select id="ambulance_id" name="ambulance_id" required>
                                <option value="">Select Ambulance</option>
                                <?php foreach ($ambulances as $ambulance): ?>
                                    <?php if ($ambulance['status'] === 'available'): ?>
                                        <option value="<?php echo $ambulance['id']; ?>">
                                            <?php echo htmlspecialchars($ambulance['vehicle_number'] . ' - ' . $ambulance['driver_name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="pickup_location">Pickup Location *</label>
                            <input type="text" id="pickup_location" name="pickup_location" required>
                        </div>
                        <div class="form-group">
                            <label for="destination">Destination *</label>
                            <input type="text" id="destination" name="destination" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="pickup_time">Pickup Time *</label>
                            <input type="datetime-local" id="pickup_time" name="pickup_time" required>
                        </div>
                        <div class="form-group">
                            <label for="emergency_type">Emergency Type *</label>
                            <select id="emergency_type" name="emergency_type" required>
                                <option value="">Select Type</option>
                                <option value="critical">Critical</option>
                                <option value="urgent">Urgent</option>
                                <option value="routine">Routine</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_person">Contact Person *</label>
                            <input type="text" id="contact_person" name="contact_person" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_phone">Contact Phone *</label>
                            <input type="tel" id="contact_phone" name="contact_phone" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="patient_condition">Patient Condition</label>
                        <textarea id="patient_condition" name="patient_condition" placeholder="Describe patient's condition and any special requirements"></textarea>
                    </div>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeModal('bookingModal')" class="btn btn-secondary">Cancel</button>
                        <button type="submit" class="btn btn-success">Book Ambulance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab panes
            const tabPanes = document.querySelectorAll('.tab-pane');
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab pane
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function editAmbulance(ambulance) {
            // Populate form with ambulance data
            document.getElementById('vehicle_number').value = ambulance.vehicle_number;
            document.getElementById('vehicle_type').value = ambulance.vehicle_type;
            document.getElementById('driver_name').value = ambulance.driver_name;
            document.getElementById('driver_phone').value = ambulance.driver_phone;
            document.getElementById('capacity').value = ambulance.capacity;
            document.getElementById('status').value = ambulance.status;
            document.getElementById('location').value = ambulance.location;
            document.getElementById('equipment').value = ambulance.equipment;
            
            // Change form action
            const form = document.querySelector('#ambulanceModal form');
            const actionInput = form.querySelector('input[name="action"]');
            actionInput.value = 'update_ambulance';
            
            // Add ambulance ID
            let idInput = form.querySelector('input[name="ambulance_id"]');
            if (!idInput) {
                idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'ambulance_id';
                form.appendChild(idInput);
            }
            idInput.value = ambulance.id;
            
            // Change button text
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.textContent = 'Update Ambulance';
            
            openModal('ambulanceModal');
        }
        
        function bookAmbulance(ambulanceId) {
            document.getElementById('ambulance_id').value = ambulanceId;
            openModal('bookingModal');
        }
        
        function updateBooking(bookingId) {
            // Create update booking modal
            const status = prompt('Enter new status (scheduled/active/completed/cancelled):');
            if (status) {
                const notes = prompt('Enter notes (optional):');
                
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_booking">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                    <input type="hidden" name="status" value="${status}">
                    <input type="hidden" name="notes" value="${notes || ''}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function viewBooking(booking) {
            alert(`Booking Details:\n\nPatient: ${booking.first_name} ${booking.last_name}\nAmbulance: ${booking.vehicle_number}\nPickup: ${booking.pickup_location}\nDestination: ${booking.destination}\nStatus: ${booking.status}\nContact: ${booking.contact_person} (${booking.contact_phone})`);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
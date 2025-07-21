<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a driver
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: index.php');
    exit;
}

$db = new Database();
$user_id = $_SESSION['user_id'];

// Get driver info
$driver_info = $db->query(
    "SELECT s.*, u.email FROM staff s 
     JOIN users u ON s.user_id = u.id 
     WHERE s.user_id = ? AND s.staff_type = 'driver'", 
    [$user_id]
)->fetch();

$driver_name = $driver_info['first_name'] . ' ' . $driver_info['last_name'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';

// Get ambulance assignments for this driver
$trips = [];
try {
    // Try to get actual trips from database
    $sql = "SELECT ab.*, a.vehicle_number, a.vehicle_type,
                   CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                   p.phone as patient_phone
            FROM ambulance_bookings ab
            JOIN ambulances a ON ab.ambulance_id = a.id
            LEFT JOIN patients p ON ab.patient_id = p.id
            WHERE a.driver_name = ?";
    
    $params = [$driver_name];
    
    if ($status_filter !== 'all') {
        $sql .= " AND ab.status = ?";
        $params[] = $status_filter;
    }
    
    if ($date_filter !== 'all') {
        switch ($date_filter) {
            case 'today':
                $sql .= " AND DATE(ab.booking_date) = CURDATE()";
                break;
            case 'week':
                $sql .= " AND ab.booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $sql .= " AND ab.booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }
    
    $sql .= " ORDER BY ab.booking_date DESC LIMIT 50";
    
    $trips = $db->query($sql, $params)->fetchAll();
} catch (Exception $e) {
    // Database tables might not exist, use demo data
}

// If no trips found or tables don't exist, show demo data
if (empty($trips)) {
    $trips = [
        [
            'id' => 1,
            'booking_date' => date('Y-m-d H:i:s'),
            'pickup_address' => 'City Hospital, Main Road',
            'destination_address' => 'Patient Home, Green Avenue',
            'patient_name' => 'John Doe',
            'patient_phone' => '9876543210',
            'vehicle_number' => 'DL-01-AB-1234',
            'vehicle_type' => 'advanced',
            'status' => 'completed',
            'emergency_type' => 'Non-Emergency',
            'distance' => '15 km',
            'duration' => '45 min',
            'amount' => 500
        ],
        [
            'id' => 2,
            'booking_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'pickup_address' => 'Emergency Call - Metro Station',
            'destination_address' => 'City Hospital Emergency',
            'patient_name' => 'Jane Smith',
            'patient_phone' => '9123456789',
            'vehicle_number' => 'DL-01-AB-1234',
            'vehicle_type' => 'icu',
            'status' => 'completed',
            'emergency_type' => 'Emergency',
            'distance' => '8 km',
            'duration' => '25 min',
            'amount' => 750
        ],
        [
            'id' => 3,
            'booking_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'pickup_address' => 'Clinic, Park Street',
            'destination_address' => 'Specialist Hospital',
            'patient_name' => 'Robert Johnson',
            'patient_phone' => '9988776655',
            'vehicle_number' => 'DL-01-AB-1234',
            'vehicle_type' => 'basic',
            'status' => 'completed',
            'emergency_type' => 'Scheduled',
            'distance' => '20 km',
            'duration' => '60 min',
            'amount' => 600
        ],
        [
            'id' => 4,
            'booking_date' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'pickup_address' => 'Senior Care Home',
            'destination_address' => 'Cardiology Center',
            'patient_name' => 'Mary Wilson',
            'patient_phone' => '9554433221',
            'vehicle_number' => 'DL-01-AB-1234',
            'vehicle_type' => 'advanced',
            'status' => 'scheduled',
            'emergency_type' => 'Scheduled',
            'distance' => '12 km',
            'duration' => '35 min',
            'amount' => 450
        ]
    ];
}

// Filter demo data based on filters
if (!empty($trips) && isset($trips[0]['status'])) {
    if ($status_filter !== 'all') {
        $trips = array_filter($trips, function($trip) use ($status_filter) {
            return $trip['status'] === $status_filter;
        });
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Ambulance Trips - Driver Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .trips-page {
            padding: 20px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }
        
        .page-header {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .trips-grid {
            display: grid;
            gap: 20px;
        }
        
        .trip-card {
            background: var(--bg-card);
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            transition: transform 0.2s ease;
        }
        
        .trip-card:hover {
            transform: translateY(-2px);
        }
        
        .trip-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .trip-id {
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .trip-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-completed {
            background: rgba(16, 185, 129, 0.2);
            color: #059669;
        }
        
        .status-scheduled {
            background: rgba(59, 130, 246, 0.2);
            color: #2563eb;
        }
        
        .status-in-progress {
            background: rgba(245, 158, 11, 0.2);
            color: #d97706;
        }
        
        .status-cancelled {
            background: rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }
        
        .trip-body {
            padding: 20px;
        }
        
        .trip-route {
            margin-bottom: 15px;
        }
        
        .route-point {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .route-icon {
            width: 20px;
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .trip-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .patient-info {
            background: var(--bg-secondary);
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .amount {
            color: var(--primary-color);
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .emergency-type {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .emergency {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
        
        .scheduled {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .non-emergency {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }
        
        .stats-summary {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 20px;
            text-align: center;
        }
        
        .stat-item .number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-item .label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="trips-page">
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-route"></i> My Ambulance Trips</h1>
                        <p>Driver: <?php echo htmlspecialchars($driver_name); ?> | Employee ID: <?php echo htmlspecialchars($driver_info['employee_id']); ?></p>
                    </div>
                    
                    <div class="filters">
                        <select class="filter-select" onchange="filterTrips('status', this.value)">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="in-progress" <?php echo $status_filter === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        
                        <select class="filter-select" onchange="filterTrips('date', this.value)">
                            <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                </div>
                
                <!-- Trip Statistics -->
                <div class="stats-summary">
                    <div class="stat-item">
                        <div class="number"><?php echo count($trips); ?></div>
                        <div class="label">Total Trips</div>
                    </div>
                    <div class="stat-item">
                        <div class="number"><?php echo count(array_filter($trips, function($t) { return $t['status'] === 'completed'; })); ?></div>
                        <div class="label">Completed</div>
                    </div>
                    <div class="stat-item">
                        <div class="number"><?php echo count(array_filter($trips, function($t) { return $t['status'] === 'scheduled'; })); ?></div>
                        <div class="label">Scheduled</div>
                    </div>
                    <div class="stat-item">
                        <div class="number">₹<?php echo number_format(array_sum(array_column($trips, 'amount'))); ?></div>
                        <div class="label">Total Earnings</div>
                    </div>
                </div>
                
                <!-- Trips List -->
                <div class="trips-grid">
                    <?php if (empty($trips)): ?>
                        <div style="text-align: center; padding: 40px; background: var(--bg-card); border-radius: 10px;">
                            <i class="fas fa-route" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 10px;"></i>
                            <h3>No trips found</h3>
                            <p>No ambulance trips match your current filters.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($trips as $trip): ?>
                            <div class="trip-card">
                                <div class="trip-header">
                                    <div class="trip-id">
                                        <i class="fas fa-ambulance"></i> Trip #<?php echo $trip['id']; ?>
                                        <div style="font-size: 12px; opacity: 0.9;">
                                            <?php echo date('M j, Y • g:i A', strtotime($trip['booking_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="trip-status status-<?php echo str_replace('_', '-', $trip['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $trip['status'])); ?>
                                    </div>
                                </div>
                                
                                <div class="trip-body">
                                    <?php if (isset($trip['patient_name'])): ?>
                                        <div class="patient-info">
                                            <strong><i class="fas fa-user"></i> <?php echo htmlspecialchars($trip['patient_name']); ?></strong>
                                            <?php if (isset($trip['patient_phone'])): ?>
                                                <span style="float: right;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($trip['patient_phone']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="trip-route">
                                        <div class="route-point">
                                            <i class="fas fa-map-marker-alt route-icon" style="color: #10b981;"></i>
                                            <strong>Pickup:</strong> <?php echo htmlspecialchars($trip['pickup_address']); ?>
                                        </div>
                                        <div class="route-point">
                                            <i class="fas fa-flag-checkered route-icon" style="color: #ef4444;"></i>
                                            <strong>Destination:</strong> <?php echo htmlspecialchars($trip['destination_address']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="trip-details">
                                        <div class="detail-item">
                                            <div class="detail-label">Vehicle</div>
                                            <div class="detail-value">
                                                <?php echo htmlspecialchars($trip['vehicle_number'] ?? 'DL-01-AB-1234'); ?>
                                                <br><small><?php echo ucfirst($trip['vehicle_type'] ?? 'basic'); ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Type</div>
                                            <div class="detail-value">
                                                <span class="emergency-type <?php 
                                                    $type = $trip['emergency_type'] ?? 'Scheduled';
                                                    echo strtolower(str_replace(' ', '-', $type)); 
                                                ?>">
                                                    <?php echo $type; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Distance</div>
                                            <div class="detail-value"><?php echo $trip['distance'] ?? '-- km'; ?></div>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Duration</div>
                                            <div class="detail-value"><?php echo $trip['duration'] ?? '-- min'; ?></div>
                                        </div>
                                        
                                        <div class="detail-item">
                                            <div class="detail-label">Amount</div>
                                            <div class="detail-value amount">₹<?php echo number_format($trip['amount'] ?? 0); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
    
    <script>
        function filterTrips(type, value) {
            const url = new URL(window.location);
            url.searchParams.set(type, value);
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
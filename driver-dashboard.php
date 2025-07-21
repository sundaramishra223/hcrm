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

// Get driver statistics
$stats = [];
$stats['total_trips'] = 0;
$stats['this_month_trips'] = 0;
$stats['total_earnings'] = 0;
$stats['this_month_earnings'] = 0;

// Try to get actual stats if tables exist
try {
    $stats['total_trips'] = $db->query(
        "SELECT COUNT(*) as count FROM ambulance_bookings ab 
         JOIN ambulances a ON ab.ambulance_id = a.id 
         WHERE a.driver_name = ? AND ab.status = 'completed'",
        [$driver_info['first_name'] . ' ' . $driver_info['last_name']]
    )->fetch()['count'] ?? 0;
    
    $stats['this_month_trips'] = $db->query(
        "SELECT COUNT(*) as count FROM ambulance_bookings ab 
         JOIN ambulances a ON ab.ambulance_id = a.id 
         WHERE a.driver_name = ? AND ab.status = 'completed' 
         AND MONTH(ab.booking_date) = MONTH(CURRENT_DATE())",
        [$driver_info['first_name'] . ' ' . $driver_info['last_name']]
    )->fetch()['count'] ?? 0;
} catch (Exception $e) {
    // Tables might not exist, use default values
}

// Recent trips (mock data for demo)
$recent_trips = [
    ['date' => date('Y-m-d'), 'pickup' => 'City Hospital', 'destination' => 'Home', 'status' => 'Completed', 'amount' => 500],
    ['date' => date('Y-m-d', strtotime('-1 day')), 'pickup' => 'Emergency Call', 'destination' => 'Hospital', 'status' => 'Completed', 'amount' => 750],
    ['date' => date('Y-m-d', strtotime('-2 days')), 'pickup' => 'Clinic', 'destination' => 'Specialist Hospital', 'status' => 'Completed', 'amount' => 600],
];

$stats['total_earnings'] = array_sum(array_column($recent_trips, 'amount'));
$stats['this_month_earnings'] = $stats['total_earnings'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .driver-dashboard {
            padding: 20px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }
        
        .dashboard-header {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            text-align: center;
        }
        
        .stat-card .icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--text-primary);
        }
        
        .stat-card .label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .trips-table {
            background: var(--bg-card);
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .status-completed {
            color: #10b981;
            font-weight: 500;
        }
        
        .amount {
            font-weight: 600;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="driver-dashboard">
                <div class="dashboard-header">
                    <h1>🚗 Driver Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($driver_info['first_name'] . ' ' . $driver_info['last_name']); ?>!</p>
                    <small>Employee ID: <?php echo htmlspecialchars($driver_info['employee_id']); ?></small>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="icon">
                            <i class="fas fa-route"></i>
                        </div>
                        <div class="number"><?php echo $stats['total_trips']; ?></div>
                        <div class="label">Total Trips</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="number"><?php echo $stats['this_month_trips']; ?></div>
                        <div class="label">This Month Trips</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="number">₹<?php echo number_format($stats['total_earnings']); ?></div>
                        <div class="label">Total Earnings</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="number">₹<?php echo number_format($stats['this_month_earnings']); ?></div>
                        <div class="label">This Month Earnings</div>
                    </div>
                </div>
                
                <div class="trips-table">
                    <h3 style="padding: 20px; margin: 0; border-bottom: 1px solid var(--border-color);">
                        <i class="fas fa-history"></i> Recent Trips
                    </h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Pickup Location</th>
                                <th>Destination</th>
                                <th>Status</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_trips as $trip): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($trip['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($trip['pickup']); ?></td>
                                    <td><?php echo htmlspecialchars($trip['destination']); ?></td>
                                    <td><span class="status-completed"><?php echo $trip['status']; ?></span></td>
                                    <td><span class="amount">₹<?php echo number_format($trip['amount']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="my-salary.php" class="btn btn-primary">
                        <i class="fas fa-money-check-alt"></i> View Detailed Salary Report
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
</body>
</html>
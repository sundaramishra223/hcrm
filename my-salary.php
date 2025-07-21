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

// Demo salary data (in real scenario, this would come from payroll/salary tables)
$current_month = date('Y-m');
$last_month = date('Y-m', strtotime('-1 month'));

$salary_data = [
    'base_salary' => 25000,
    'trip_bonus_rate' => 100, // per trip
    'emergency_bonus_rate' => 250, // extra for emergency trips
    'fuel_allowance' => 3000,
    'overtime_rate' => 200, // per hour
];

// Calculate current month earnings
$current_month_trips = 15;
$current_month_emergency_trips = 3;
$current_month_overtime_hours = 8;

$current_earnings = [
    'base_salary' => $salary_data['base_salary'],
    'trip_bonus' => $current_month_trips * $salary_data['trip_bonus_rate'],
    'emergency_bonus' => $current_month_emergency_trips * $salary_data['emergency_bonus_rate'],
    'fuel_allowance' => $salary_data['fuel_allowance'],
    'overtime' => $current_month_overtime_hours * $salary_data['overtime_rate'],
    'deductions' => 2500, // PF, Insurance etc.
];

$current_earnings['gross_salary'] = $current_earnings['base_salary'] + $current_earnings['trip_bonus'] + 
                                   $current_earnings['emergency_bonus'] + $current_earnings['fuel_allowance'] + 
                                   $current_earnings['overtime'];
$current_earnings['net_salary'] = $current_earnings['gross_salary'] - $current_earnings['deductions'];

// Last month data for comparison
$last_month_earnings = [
    'gross_salary' => 28500,
    'net_salary' => 26000,
    'trips' => 12,
    'emergency_trips' => 2,
    'overtime_hours' => 5,
];

// Recent salary history
$salary_history = [
    [
        'month' => date('F Y'),
        'gross' => $current_earnings['gross_salary'],
        'net' => $current_earnings['net_salary'],
        'status' => 'Processing',
        'pay_date' => date('Y-m-t'), // Last day of current month
    ],
    [
        'month' => date('F Y', strtotime('-1 month')),
        'gross' => 28500,
        'net' => 26000,
        'status' => 'Paid',
        'pay_date' => date('Y-m-05', strtotime('-1 month')),
    ],
    [
        'month' => date('F Y', strtotime('-2 months')),
        'gross' => 27200,
        'net' => 24700,
        'status' => 'Paid',
        'pay_date' => date('Y-m-05', strtotime('-2 months')),
    ],
    [
        'month' => date('F Y', strtotime('-3 months')),
        'gross' => 26800,
        'net' => 24300,
        'status' => 'Paid',
        'pay_date' => date('Y-m-05', strtotime('-3 months')),
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Salary - Driver Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .salary-page {
            padding: 20px;
            background: var(--bg-secondary);
            min-height: 100vh;
        }
        
        .salary-header {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
        }
        
        .earnings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .earnings-card {
            background: var(--bg-card);
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .earning-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .earning-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
            color: var(--primary-color);
        }
        
        .earning-label {
            color: var(--text-secondary);
        }
        
        .earning-amount {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .positive {
            color: #10b981;
        }
        
        .negative {
            color: #ef4444;
        }
        
        .comparison-card {
            background: var(--bg-card);
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .comparison-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .comparison-item {
            text-align: center;
            padding: 15px;
            background: var(--bg-secondary);
            border-radius: 8px;
        }
        
        .comparison-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .comparison-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }
        
        .history-table {
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
        
        .status-paid {
            color: #10b981;
            font-weight: 500;
        }
        
        .status-processing {
            color: #f59e0b;
            font-weight: 500;
        }
        
        .amount-cell {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .rate-card {
            background: var(--bg-card);
            border-radius: 10px;
            box-shadow: var(--shadow-md);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .rate-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .rate-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            background: var(--bg-secondary);
            border-radius: 8px;
        }
        
        .trend-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 500;
        }
        
        .trend-up {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        
        .trend-down {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="salary-page">
                <div class="salary-header">
                    <h1><i class="fas fa-money-check-alt"></i> My Salary & Earnings</h1>
                    <p>Driver: <?php echo htmlspecialchars($driver_info['first_name'] . ' ' . $driver_info['last_name']); ?> | Employee ID: <?php echo htmlspecialchars($driver_info['employee_id']); ?></p>
                    <small>Current Month: <?php echo date('F Y'); ?></small>
                </div>
                
                <!-- Current Month Earnings -->
                <div class="earnings-grid">
                    <div class="earnings-card">
                        <div class="card-header">
                            <i class="fas fa-wallet"></i> Current Month Earnings
                        </div>
                        <div class="card-body">
                            <div class="earning-item">
                                <span class="earning-label">Base Salary</span>
                                <span class="earning-amount">₹<?php echo number_format($current_earnings['base_salary']); ?></span>
                            </div>
                            <div class="earning-item">
                                <span class="earning-label">Trip Bonus (<?php echo $current_month_trips; ?> trips)</span>
                                <span class="earning-amount positive">+₹<?php echo number_format($current_earnings['trip_bonus']); ?></span>
                            </div>
                            <div class="earning-item">
                                <span class="earning-label">Emergency Bonus (<?php echo $current_month_emergency_trips; ?> trips)</span>
                                <span class="earning-amount positive">+₹<?php echo number_format($current_earnings['emergency_bonus']); ?></span>
                            </div>
                            <div class="earning-item">
                                <span class="earning-label">Fuel Allowance</span>
                                <span class="earning-amount positive">+₹<?php echo number_format($current_earnings['fuel_allowance']); ?></span>
                            </div>
                            <div class="earning-item">
                                <span class="earning-label">Overtime (<?php echo $current_month_overtime_hours; ?> hrs)</span>
                                <span class="earning-amount positive">+₹<?php echo number_format($current_earnings['overtime']); ?></span>
                            </div>
                            <div class="earning-item">
                                <span class="earning-label">Deductions</span>
                                <span class="earning-amount negative">-₹<?php echo number_format($current_earnings['deductions']); ?></span>
                            </div>
                            <div class="earning-item">
                                <span class="earning-label">Gross Salary</span>
                                <span class="earning-amount">₹<?php echo number_format($current_earnings['gross_salary']); ?></span>
                            </div>
                            <div class="earning-item">
                                <span class="earning-label">Net Salary</span>
                                <span class="earning-amount">₹<?php echo number_format($current_earnings['net_salary']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="earnings-card">
                        <div class="card-header">
                            <i class="fas fa-chart-line"></i> Performance Metrics
                        </div>
                        <div class="card-body">
                            <div class="comparison-grid">
                                <div class="comparison-item">
                                    <div class="comparison-value"><?php echo $current_month_trips; ?></div>
                                    <div class="comparison-label">Total Trips</div>
                                    <div class="trend-indicator trend-up">
                                        <i class="fas fa-arrow-up"></i> +3 from last month
                                    </div>
                                </div>
                                
                                <div class="comparison-item">
                                    <div class="comparison-value"><?php echo $current_month_emergency_trips; ?></div>
                                    <div class="comparison-label">Emergency Trips</div>
                                    <div class="trend-indicator trend-up">
                                        <i class="fas fa-arrow-up"></i> +1 from last month
                                    </div>
                                </div>
                                
                                <div class="comparison-item">
                                    <div class="comparison-value"><?php echo $current_month_overtime_hours; ?></div>
                                    <div class="comparison-label">Overtime Hours</div>
                                    <div class="trend-indicator trend-up">
                                        <i class="fas fa-arrow-up"></i> +3 from last month
                                    </div>
                                </div>
                                
                                <div class="comparison-item">
                                    <div class="comparison-value">₹<?php echo number_format($current_earnings['net_salary'] - $last_month_earnings['net_salary']); ?></div>
                                    <div class="comparison-label">Increase vs Last Month</div>
                                    <div class="trend-indicator trend-up">
                                        <i class="fas fa-arrow-up"></i> Great performance!
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Rates -->
                <div class="rate-card">
                    <h3><i class="fas fa-calculator"></i> Payment Rates & Structure</h3>
                    <div class="rate-grid">
                        <div class="rate-item">
                            <span>Base Salary</span>
                            <span><strong>₹<?php echo number_format($salary_data['base_salary']); ?>/month</strong></span>
                        </div>
                        <div class="rate-item">
                            <span>Trip Bonus</span>
                            <span><strong>₹<?php echo $salary_data['trip_bonus_rate']; ?>/trip</strong></span>
                        </div>
                        <div class="rate-item">
                            <span>Emergency Bonus</span>
                            <span><strong>₹<?php echo $salary_data['emergency_bonus_rate']; ?>/emergency trip</strong></span>
                        </div>
                        <div class="rate-item">
                            <span>Overtime Rate</span>
                            <span><strong>₹<?php echo $salary_data['overtime_rate']; ?>/hour</strong></span>
                        </div>
                        <div class="rate-item">
                            <span>Fuel Allowance</span>
                            <span><strong>₹<?php echo number_format($salary_data['fuel_allowance']); ?>/month</strong></span>
                        </div>
                    </div>
                </div>
                
                <!-- Salary History -->
                <div class="history-table">
                    <h3 style="padding: 20px; margin: 0; border-bottom: 1px solid var(--border-color);">
                        <i class="fas fa-history"></i> Salary History
                    </h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Gross Salary</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Pay Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salary_history as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['month']); ?></td>
                                    <td class="amount-cell">₹<?php echo number_format($record['gross']); ?></td>
                                    <td class="amount-cell">₹<?php echo number_format($record['net']); ?></td>
                                    <td>
                                        <span class="status-<?php echo strtolower($record['status']); ?>">
                                            <?php echo $record['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($record['pay_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="my-ambulance-trips.php" class="btn btn-primary">
                        <i class="fas fa-route"></i> View My Trips
                    </a>
                    <a href="driver-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'accountant', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Get date filters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'overview';

// Helper function to get date range label
function getDateRangeLabel($from, $to) {
    if ($from === $to) {
        return date('M d, Y', strtotime($from));
    }
    return date('M d, Y', strtotime($from)) . ' - ' . date('M d, Y', strtotime($to));
}

try {
    // Overview Statistics
    $overview_stats = [];
    
    // Patient Statistics
    $overview_stats['total_patients'] = $db->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'];
    $overview_stats['new_patients'] = $db->query("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) BETWEEN ? AND ?", [$date_from, $date_to])->fetch()['count'];
    
    // Appointment Statistics
    $overview_stats['total_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) BETWEEN ? AND ?", [$date_from, $date_to])->fetch()['count'];
    $overview_stats['completed_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed' AND DATE(appointment_date) BETWEEN ? AND ?", [$date_from, $date_to])->fetch()['count'];
    $overview_stats['cancelled_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'cancelled' AND DATE(appointment_date) BETWEEN ? AND ?", [$date_from, $date_to])->fetch()['count'];
    
    // Revenue Statistics
    $revenue_data = $db->query("SELECT SUM(total_amount) as total_revenue, COUNT(*) as total_bills FROM bills WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ?", [$date_from, $date_to])->fetch();
    $overview_stats['total_revenue'] = $revenue_data['total_revenue'] ?? 0;
    $overview_stats['total_bills'] = $revenue_data['total_bills'] ?? 0;
    
    // Lab & Pharmacy Revenue
    $lab_revenue = $db->query("SELECT SUM(total_cost) as revenue FROM lab_orders WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?", [$date_from, $date_to])->fetch()['revenue'] ?? 0;
    $pharmacy_revenue = $db->query("SELECT SUM(stock_quantity * unit_price) as revenue FROM medicines")->fetch()['revenue'] ?? 0; // Inventory value
    
    $overview_stats['lab_revenue'] = $lab_revenue;
    $overview_stats['pharmacy_value'] = $pharmacy_revenue;
    
    // Staff & Resource Statistics
    $overview_stats['total_doctors'] = $db->query("SELECT COUNT(*) as count FROM doctors WHERE is_available = 1")->fetch()['count'];
    $overview_stats['total_staff'] = $db->query("SELECT COUNT(*) as count FROM staff WHERE is_active = 1")->fetch()['count'];
    $overview_stats['occupied_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch()['count'];
    $overview_stats['available_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'available'")->fetch()['count'];
    
    // Daily Revenue Trend (Last 30 days)
    $daily_revenue = $db->query("
        SELECT DATE(created_at) as date, SUM(total_amount) as revenue 
        FROM bills 
        WHERE payment_status = 'paid' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ")->fetchAll();
    
    // Monthly Patient Registration Trend (Last 12 months)
    $monthly_patients = $db->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
        FROM patients 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll();
    
    // Department-wise Appointment Statistics
    $dept_appointments = $db->query("
        SELECT d.name as department, COUNT(a.id) as appointment_count,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count
        FROM appointments a
        JOIN doctors doc ON a.doctor_id = doc.id
        JOIN departments d ON doc.department_id = d.id
        WHERE DATE(a.appointment_date) BETWEEN ? AND ?
        GROUP BY d.id, d.name
        ORDER BY appointment_count DESC
    ", [$date_from, $date_to])->fetchAll();
    
    // Top Performing Doctors
    $top_doctors = $db->query("
        SELECT CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
        d.specialization,
        COUNT(a.id) as appointment_count,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        0 as avg_rating
        FROM doctors d
        LEFT JOIN appointments a ON d.id = a.doctor_id AND DATE(a.appointment_date) BETWEEN ? AND ?
        WHERE d.is_available = 1
        GROUP BY d.id
        HAVING appointment_count > 0
        ORDER BY completed_count DESC, avg_rating DESC
        LIMIT 10
    ", [$date_from, $date_to])->fetchAll();
    
    // Payment Method Analysis
    $payment_methods = $db->query("
        SELECT payment_method, COUNT(*) as count, SUM(total_amount) as total
        FROM bills 
        WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY payment_method
        ORDER BY total DESC
    ", [$date_from, $date_to])->fetchAll();
    
    // Age Group Analysis
    $age_groups = $db->query("
        SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18 THEN 'Under 18'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50 THEN '31-50'
            WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 51 AND 70 THEN '51-70'
            ELSE 'Over 70'
        END as age_group,
        COUNT(*) as count
        FROM patients 
        WHERE date_of_birth IS NOT NULL
        GROUP BY age_group
        ORDER BY count DESC
    ")->fetchAll();
    
    // Equipment Status Summary
    $equipment_status = $db->query("
        SELECT status, COUNT(*) as count, SUM(cost) as total_value
        FROM equipment 
        GROUP BY status
        ORDER BY count DESC
    ")->fetchAll();
    
    // Recent Activities (for activity log)
    $recent_activities = $db->query("
        SELECT 'Appointment' as type, CONCAT('New appointment with Dr. ', d.first_name, ' ', d.last_name) as description, a.created_at as date
        FROM appointments a 
        JOIN doctors d ON a.doctor_id = d.id 
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 'Patient' as type, CONCAT('New patient registered: ', p.first_name, ' ', p.last_name) as description, p.created_at as date
        FROM patients p 
        WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        UNION ALL
        
        SELECT 'Bill' as type, CONCAT('Payment received: ₹', FORMAT(b.total_amount, 2)) as description, b.updated_at as date
        FROM bills b 
        WHERE b.payment_status = 'paid' AND b.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        
        ORDER BY date DESC
        LIMIT 15
    ")->fetchAll();

} catch (Exception $e) {
    showErrorPopup("Error loading reports: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Hospital CRM</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .date-filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: auto auto auto auto auto;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 14px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #004685, #0066cc);
        }
        
        .stat-card h3 {
            font-size: 28px;
            color: #004685;
            margin-bottom: 5px;
            font-weight: 700;
        }
        
        .stat-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .stat-card .trend {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .trend.positive {
            background: #d4edda;
            color: #155724;
        }
        
        .trend.negative {
            background: #f8d7da;
            color: #721c24;
        }
        
        .reports-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .chart-container h3 {
            color: #004685;
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 300px;
        }
        
        .summary-cards {
            display: grid;
            gap: 20px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .summary-card h4 {
            color: #004685;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .list-item:last-child {
            border-bottom: none;
        }
        
        .list-item .name {
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .list-item .value {
            color: #004685;
            font-weight: 600;
            font-size: 14px;
        }
        
        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e1e1e1;
        }
        
        .table-header h4 {
            color: #004685;
            font-size: 16px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .activity-feed {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .activity-feed h3 {
            color: #004685;
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 14px;
            color: white;
            font-weight: 600;
        }
        
        .activity-icon.appointment {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        
        .activity-icon.patient {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        
        .activity-icon.bill {
            background: linear-gradient(135deg, #ffc107, #e0a800);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-content .description {
            color: #333;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .activity-content .time {
            color: #666;
            font-size: 12px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 1200px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .tables-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reports & Analytics</h1>
            <div>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
                <button onclick="window.print()" class="btn btn-primary">Print Report</button>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="date-filters">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">To Date</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="form-group">
                    <label for="report_type">Report Type</label>
                    <select name="report_type" id="report_type">
                        <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                        <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                        <option value="patients" <?php echo $report_type === 'patients' ? 'selected' : ''; ?>>Patients</option>
                        <option value="doctors" <?php echo $report_type === 'doctors' ? 'selected' : ''; ?>>Doctors</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
                
                <div class="form-group">
                    <button type="button" onclick="setQuickDate('today')" class="btn btn-secondary">Today</button>
                    <button type="button" onclick="setQuickDate('week')" class="btn btn-secondary">This Week</button>
                    <button type="button" onclick="setQuickDate('month')" class="btn btn-secondary">This Month</button>
                </div>
            </form>
        </div>
        
        <div style="text-align: center; margin-bottom: 20px; color: #666;">
            <h3>Report Period: <?php echo getDateRangeLabel($date_from, $date_to); ?></h3>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($overview_stats['total_patients'] ?? 0); ?></h3>
                <p>Total Patients</p>
                <span class="trend positive">+<?php echo $overview_stats['new_patients'] ?? 0; ?> new</span>
            </div>
            
            <div class="stat-card">
                <h3><?php echo number_format($overview_stats['total_appointments'] ?? 0); ?></h3>
                <p>Total Appointments</p>
                <span class="trend positive"><?php echo round((($overview_stats['completed_appointments'] ?? 0) / max(1, $overview_stats['total_appointments'] ?? 1)) * 100); ?>% completed</span>
            </div>
            
            <div class="stat-card">
                <h3>₹<?php echo number_format($overview_stats['total_revenue'] ?? 0, 2); ?></h3>
                <p>Total Revenue</p>
                <span class="trend positive"><?php echo $overview_stats['total_bills'] ?? 0; ?> bills</span>
            </div>
            
            <div class="stat-card">
                <h3><?php echo number_format($overview_stats['total_doctors'] ?? 0); ?></h3>
                <p>Active Doctors</p>
                <span class="trend positive"><?php echo $overview_stats['total_staff'] ?? 0; ?> total staff</span>
            </div>
            
            <div class="stat-card">
                <h3><?php echo number_format($overview_stats['occupied_beds'] ?? 0); ?>/<?php echo number_format(($overview_stats['occupied_beds'] ?? 0) + ($overview_stats['available_beds'] ?? 0)); ?></h3>
                <p>Bed Occupancy</p>
                <span class="trend <?php echo ($overview_stats['occupied_beds'] ?? 0) > ($overview_stats['available_beds'] ?? 0) ? 'negative' : 'positive'; ?>">
                    <?php echo round((($overview_stats['occupied_beds'] ?? 0) / max(1, ($overview_stats['occupied_beds'] ?? 0) + ($overview_stats['available_beds'] ?? 0))) * 100); ?>% occupied
                </span>
            </div>
            
            <div class="stat-card">
                <h3>₹<?php echo number_format($overview_stats['lab_revenue'] ?? 0, 2); ?></h3>
                <p>Lab Revenue</p>
                <span class="trend positive">Lab Services</span>
            </div>
        </div>
        
        <div class="reports-grid">
            <div class="chart-container">
                <h3>Revenue Trend (Last 30 Days)</h3>
                <div class="chart-wrapper">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <div class="summary-cards">
                <div class="summary-card">
                    <h4>Payment Methods</h4>
                    <?php if (empty($payment_methods)): ?>
                        <p style="color: #666; text-align: center; padding: 20px;">No payment data available</p>
                    <?php else: ?>
                        <?php foreach ($payment_methods as $method): ?>
                            <div class="list-item">
                                <span class="name"><?php echo ucfirst($method['payment_method']); ?></span>
                                <span class="value">₹<?php echo number_format($method['total'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="summary-card">
                    <h4>Age Group Distribution</h4>
                    <?php if (empty($age_groups)): ?>
                        <p style="color: #666; text-align: center; padding: 20px;">No patient data available</p>
                    <?php else: ?>
                        <?php foreach ($age_groups as $group): ?>
                            <div class="list-item">
                                <span class="name"><?php echo $group['age_group']; ?></span>
                                <span class="value"><?php echo number_format($group['count']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <h3>Patient Registration Trend (Last 12 Months)</h3>
            <div class="chart-wrapper">
                <canvas id="patientChart"></canvas>
            </div>
        </div>
        
        <div class="tables-grid">
            <div class="table-container">
                <div class="table-header">
                    <h4>Department Performance</h4>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Appointments</th>
                            <th>Completed</th>
                            <th>Success Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dept_appointments)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #666; padding: 20px;">No department data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dept_appointments as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                    <td><?php echo number_format($dept['appointment_count']); ?></td>
                                    <td><?php echo number_format($dept['completed_count']); ?></td>
                                    <td><?php echo round(($dept['completed_count'] / max(1, $dept['appointment_count'])) * 100); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h4>Top Performing Doctors</h4>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Appointments</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_doctors)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #666; padding: 20px;">No doctor data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($top_doctors, 0, 5) as $doctor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doctor['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                                    <td><?php echo number_format($doctor['completed_count']); ?></td>
                                    <td>
                                        <?php if ($doctor['avg_rating']): ?>
                                            ⭐ <?php echo number_format($doctor['avg_rating'], 1); ?>
                                        <?php else: ?>
                                            No ratings
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="activity-feed">
            <h3>Recent Activity</h3>
            <?php if (empty($recent_activities)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">No recent activities</p>
            <?php else: ?>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo strtolower($activity['type']); ?>">
                            <?php echo strtoupper(substr($activity['type'], 0, 1)); ?>
                        </div>
                        <div class="activity-content">
                            <div class="description"><?php echo htmlspecialchars($activity['description']); ?></div>
                            <div class="time"><?php echo date('M d, Y g:i A', strtotime($activity['date'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = <?php echo json_encode($daily_revenue); ?>;
        
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueData.map(item => {
                    const date = new Date(item.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'Revenue (₹)',
                    data: revenueData.map(item => item.revenue || 0),
                    borderColor: '#004685',
                    backgroundColor: 'rgba(0, 70, 133, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: (e) => {
                    // Disable chart click behavior
                    e.stopPropagation();
                    return false;
                },
                onHover: (e) => {
                    // Keep hover behavior but prevent click
                    e.native.target.style.cursor = 'default';
                },
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Patient Registration Chart
        const patientCtx = document.getElementById('patientChart').getContext('2d');
        const patientData = <?php echo json_encode($monthly_patients); ?>;
        
        new Chart(patientCtx, {
            type: 'bar',
            data: {
                labels: patientData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'New Patients',
                    data: patientData.map(item => item.count),
                    backgroundColor: 'rgba(0, 133, 70, 0.8)',
                    borderColor: '#004685',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: (e) => {
                    // Disable chart click behavior
                    e.stopPropagation();
                    return false;
                },
                onHover: (e) => {
                    // Keep hover behavior but prevent click
                    e.native.target.style.cursor = 'default';
                },
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Quick date functions
        function setQuickDate(period) {
            const today = new Date();
            let fromDate, toDate = today.toISOString().split('T')[0];
            
            switch(period) {
                case 'today':
                    fromDate = toDate;
                    break;
                case 'week':
                    const weekAgo = new Date(today.setDate(today.getDate() - 7));
                    fromDate = weekAgo.toISOString().split('T')[0];
                    break;
                case 'month':
                    fromDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    break;
            }
            
            document.getElementById('date_from').value = fromDate;
            document.getElementById('date_to').value = toDate;
        }
    </script>
</body>
</html>

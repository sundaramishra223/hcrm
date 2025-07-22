<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$db = new Database();

// Handle AJAX requests for resolving alerts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'resolve_alert') {
        $alert_id = $_POST['alert_id'];
        $resolution_notes = $_POST['resolution_notes'] ?? '';
        
        $stmt = $db->prepare("UPDATE admin_monitoring SET resolved = 1, resolved_by = ?, resolved_at = datetime('now'), resolution_notes = ? WHERE id = ?");
        $result = $stmt->execute([$_SESSION['user_id'], $resolution_notes, $alert_id]);
        
        echo json_encode(['success' => $result]);
        exit;
    }
}

// Get comprehensive blood bank statistics
$blood_stats = [];
$donor_stats = [];
$recent_activities = [];

try {
    // Blood inventory by group
    $blood_inventory = $db->query("
        SELECT blood_group, 
               COUNT(*) as total_units,
               SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_units,
               SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used_units,
               SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_units,
               SUM(CASE WHEN status = 'available' AND expiry_date <= DATE('now', '+7 days') THEN 1 ELSE 0 END) as expiring_soon,
               SUM(CASE WHEN status = 'available' AND expiry_date <= DATE('now', '+3 days') THEN 1 ELSE 0 END) as critical_expiry,
               MIN(CASE WHEN status = 'available' THEN expiry_date END) as earliest_expiry,
               MAX(CASE WHEN status = 'available' THEN collection_date END) as latest_collection
        FROM blood_inventory 
        GROUP BY blood_group 
        ORDER BY blood_group
    ")->fetchAll();

    // Overall statistics
    $blood_stats['total_units'] = $db->query("SELECT COUNT(*) as count FROM blood_inventory")->fetch()['count'];
    $blood_stats['available_units'] = $db->query("SELECT COUNT(*) as count FROM blood_inventory WHERE status = 'available'")->fetch()['count'];
    $blood_stats['used_units'] = $db->query("SELECT COUNT(*) as count FROM blood_inventory WHERE status = 'used'")->fetch()['count'];
    $blood_stats['expired_units'] = $db->query("SELECT COUNT(*) as count FROM blood_inventory WHERE status = 'expired'")->fetch()['count'];
    $blood_stats['expiring_soon'] = $db->query("SELECT COUNT(*) as count FROM blood_inventory WHERE status = 'available' AND expiry_date <= DATE('now', '+7 days')")->fetch()['count'];
    $blood_stats['critical_expiry'] = $db->query("SELECT COUNT(*) as count FROM blood_inventory WHERE status = 'available' AND expiry_date <= DATE('now', '+3 days')")->fetch()['count'];

    // Donor statistics
    $donor_stats['total_donors'] = $db->query("SELECT COUNT(*) as count FROM blood_donors WHERE is_active = 1")->fetch()['count'];
    $donor_stats['today_donations'] = $db->query("SELECT COUNT(*) as count FROM blood_donation_sessions WHERE collection_date = DATE('now')")->fetch()['count'];
    $donor_stats['this_week_donations'] = $db->query("SELECT COUNT(*) as count FROM blood_donation_sessions WHERE collection_date >= DATE('now', '-7 days')")->fetch()['count'];
    $donor_stats['this_month_donations'] = $db->query("SELECT COUNT(*) as count FROM blood_donation_sessions WHERE collection_date >= DATE('now', '-30 days')")->fetch()['count'];

    // Blood requests
    $blood_stats['pending_requests'] = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'pending'")->fetch()['count'];
    $blood_stats['approved_requests'] = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'approved'")->fetch()['count'];
    $blood_stats['urgent_requests'] = $db->query("SELECT COUNT(*) as count FROM blood_requests WHERE status = 'pending' AND urgency = 'urgent'")->fetch()['count'];

    // Recent activities
    $recent_activities = $db->query("
        SELECT 'donation' as type, 
               bd.donor_name, 
               bds.blood_group, 
               bds.collection_date, 
               bds.collection_time,
               bds.volume_collected
        FROM blood_donation_sessions bds
        JOIN blood_donors bd ON bds.donor_id = bd.id
        WHERE bds.collection_date >= DATE('now', '-7 days')
        ORDER BY bds.collection_date DESC, bds.collection_time DESC
        LIMIT 10
    ")->fetchAll();

    // Blood usage records
    $recent_usage = $db->query("
        SELECT 'usage' as type,
               p.first_name || ' ' || p.last_name as patient_name,
               d.doctor_name,
               bur.blood_group,
               bur.component_type,
               bur.volume_used,
               bur.usage_date,
               bur.usage_time,
               bur.indication
        FROM blood_usage_records bur
        JOIN patients p ON bur.patient_id = p.id
        JOIN doctors d ON bur.doctor_id = d.id
        WHERE bur.usage_date >= DATE('now', '-7 days')
        ORDER BY bur.usage_date DESC, bur.usage_time DESC
        LIMIT 10
    ")->fetchAll();

    // Critical alerts
    $critical_alerts = $db->query("
        SELECT * FROM admin_monitoring 
        WHERE resolved = 0 AND monitoring_category IN ('blood', 'vitals') 
        ORDER BY 
            CASE priority 
                WHEN 'high' THEN 1 
                WHEN 'medium' THEN 2 
                WHEN 'low' THEN 3 
            END, 
            alert_date DESC, alert_time DESC
        LIMIT 15
    ")->fetchAll();

} catch (Exception $e) {
    $blood_inventory = [];
    $blood_stats = [];
    $donor_stats = [];
    $recent_activities = [];
    $recent_usage = [];
    $critical_alerts = [];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Bank Monitor - Hospital CRM</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <span>Hospital CRM</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="blood-bank-management.php"><i class="fas fa-tint"></i> Blood Bank</a></li>
                    <li><a href="admin-blood-bank-monitor.php" class="active"><i class="fas fa-chart-line"></i> Blood Monitor</a></li>
                    <li><a href="patient-monitoring.php"><i class="fas fa-user-injured"></i> Patient Monitor</a></li>
                    <li><a href="insurance-management.php"><i class="fas fa-shield-alt"></i> Insurance</a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1><i class="fas fa-tint"></i> Blood Bank Monitoring Dashboard</h1>
                    <p>Real-time blood inventory and donor management</p>
                </div>
                <div class="header-right">
                    <div class="header-actions">
                        <button class="btn btn-success" onclick="window.location.href='blood-donation-tracking.php'">
                            <i class="fas fa-hand-holding-heart"></i> Add Donation
                        </button>
                        <button class="btn btn-primary" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <a href="logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card blood-card">
                    <h3><?php echo number_format($blood_stats['available_units'] ?? 0); ?></h3>
                    <p>Available Units</p>
                    <i class="fas fa-tint stat-icon"></i>
                </div>
                <div class="stat-card donor-card">
                    <h3><?php echo number_format($donor_stats['total_donors'] ?? 0); ?></h3>
                    <p>Active Donors</p>
                    <i class="fas fa-hand-holding-heart stat-icon"></i>
                </div>
                <div class="stat-card <?php echo ($blood_stats['expiring_soon'] ?? 0) > 0 ? 'warning-card' : ''; ?>">
                    <h3><?php echo number_format($blood_stats['expiring_soon'] ?? 0); ?></h3>
                    <p>Expiring Soon</p>
                    <i class="fas fa-exclamation-triangle stat-icon"></i>
                </div>
                <div class="stat-card <?php echo ($blood_stats['critical_expiry'] ?? 0) > 0 ? 'alert-card' : ''; ?>">
                    <h3><?php echo number_format($blood_stats['critical_expiry'] ?? 0); ?></h3>
                    <p>Critical Expiry</p>
                    <i class="fas fa-exclamation-circle stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($blood_stats['pending_requests'] ?? 0); ?></h3>
                    <p>Pending Requests</p>
                    <i class="fas fa-clipboard-list stat-icon"></i>
                </div>
                <div class="stat-card success-card">
                    <h3><?php echo number_format($donor_stats['today_donations'] ?? 0); ?></h3>
                    <p>Today's Donations</p>
                    <i class="fas fa-calendar-day stat-icon"></i>
                </div>
            </div>

            <!-- Blood Inventory by Group -->
            <div class="dashboard-section">
                <h2><i class="fas fa-tint"></i> Blood Group Inventory</h2>
                <div class="blood-inventory-grid">
                    <?php if (!empty($blood_inventory)): ?>
                        <?php foreach ($blood_inventory as $blood): ?>
                        <div class="blood-group-card <?php echo $blood['critical_expiry'] > 0 ? 'critical' : ($blood['expiring_soon'] > 0 ? 'warning' : ''); ?>">
                            <div class="blood-group-header">
                                <h3><?php echo htmlspecialchars($blood['blood_group']); ?></h3>
                                <span class="blood-drop"><i class="fas fa-tint"></i></span>
                            </div>
                            <div class="blood-stats">
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo $blood['available_units']; ?></span>
                                    <span class="stat-label">Available</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo $blood['used_units']; ?></span>
                                    <span class="stat-label">Used</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-value"><?php echo $blood['expired_units']; ?></span>
                                    <span class="stat-label">Expired</span>
                                </div>
                            </div>
                            
                            <?php if ($blood['expiring_soon'] > 0): ?>
                            <div class="expiry-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo $blood['expiring_soon']; ?> units expiring within 7 days
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($blood['critical_expiry'] > 0): ?>
                            <div class="critical-warning">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $blood['critical_expiry']; ?> units expiring within 3 days!
                            </div>
                            <?php endif; ?>
                            
                            <div class="blood-details">
                                <small>
                                    Next Expiry: <?php echo $blood['earliest_expiry'] ? date('M d, Y', strtotime($blood['earliest_expiry'])) : 'N/A'; ?><br>
                                    Latest Collection: <?php echo $blood['latest_collection'] ? date('M d, Y', strtotime($blood['latest_collection'])) : 'N/A'; ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-info-circle"></i>
                            <p>No blood inventory data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="activities-section">
                <div class="activity-column">
                    <h2><i class="fas fa-hand-holding-heart"></i> Recent Donations</h2>
                    <div class="activity-list">
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item donation">
                                <div class="activity-icon">
                                    <i class="fas fa-hand-holding-heart"></i>
                                </div>
                                <div class="activity-content">
                                    <h4><?php echo htmlspecialchars($activity['donor_name']); ?></h4>
                                    <p>Donated <?php echo $activity['volume_collected']; ?>ml of <?php echo $activity['blood_group']; ?> blood</p>
                                    <small><?php echo date('M d, Y H:i', strtotime($activity['collection_date'] . ' ' . $activity['collection_time'])); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-activities">
                                <i class="fas fa-info-circle"></i>
                                <p>No recent donations</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="activity-column">
                    <h2><i class="fas fa-syringe"></i> Recent Blood Usage</h2>
                    <div class="activity-list">
                        <?php if (!empty($recent_usage)): ?>
                            <?php foreach ($recent_usage as $usage): ?>
                            <div class="activity-item usage">
                                <div class="activity-icon">
                                    <i class="fas fa-syringe"></i>
                                </div>
                                <div class="activity-content">
                                    <h4><?php echo htmlspecialchars($usage['patient_name']); ?></h4>
                                    <p><?php echo $usage['volume_used']; ?>ml of <?php echo $usage['blood_group']; ?> <?php echo $usage['component_type']; ?></p>
                                    <p><strong>Doctor:</strong> <?php echo htmlspecialchars($usage['doctor_name']); ?></p>
                                    <p><strong>Indication:</strong> <?php echo htmlspecialchars($usage['indication']); ?></p>
                                    <small><?php echo date('M d, Y H:i', strtotime($usage['usage_date'] . ' ' . $usage['usage_time'])); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-activities">
                                <i class="fas fa-info-circle"></i>
                                <p>No recent blood usage</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Critical Alerts -->
            <div class="dashboard-section">
                <h2><i class="fas fa-bell"></i> Critical Alerts</h2>
                <div class="alerts-grid">
                    <?php if (!empty($critical_alerts)): ?>
                        <?php foreach ($critical_alerts as $alert): ?>
                        <div class="alert-card <?php echo $alert['priority']; ?>" data-alert-id="<?php echo $alert['id']; ?>">
                            <div class="alert-icon">
                                <?php
                                $icons = [
                                    'vitals' => 'fa-heartbeat',
                                    'blood' => 'fa-tint',
                                    'inventory' => 'fa-pills',
                                    'equipment' => 'fa-tools',
                                    'insurance' => 'fa-shield-alt'
                                ];
                                echo '<i class="fas ' . ($icons[$alert['monitoring_category']] ?? 'fa-exclamation-triangle') . '"></i>';
                                ?>
                            </div>
                            <div class="alert-content">
                                <h4><?php echo ucfirst($alert['monitoring_category']); ?> Alert - <?php echo ucfirst($alert['priority']); ?> Priority</h4>
                                <p><?php echo htmlspecialchars($alert['alert_message']); ?></p>
                                <small><?php echo date('M d, Y H:i', strtotime($alert['alert_date'] . ' ' . $alert['alert_time'])); ?></small>
                            </div>
                            <div class="alert-actions">
                                <button class="btn btn-sm btn-success resolve-alert" onclick="resolveAlert(<?php echo $alert['id']; ?>)">
                                    <i class="fas fa-check"></i> Resolve
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-alerts">
                            <i class="fas fa-check-circle"></i>
                            <p>No critical alerts. All systems are running smoothly!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Refresh data
        function refreshData() {
            location.reload();
        }

        // Resolve alert function
        function resolveAlert(alertId) {
            const resolution_notes = prompt('Enter resolution notes (optional):');
            
            fetch('admin-blood-bank-monitor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=resolve_alert&alert_id=${alertId}&resolution_notes=${encodeURIComponent(resolution_notes || '')}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const alertCard = document.querySelector(`[data-alert-id="${alertId}"]`);
                    if (alertCard) {
                        alertCard.style.opacity = '0.5';
                        alertCard.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            alertCard.remove();
                        }, 500);
                    }
                    
                    // Show success message
                    showNotification('Alert resolved successfully!', 'success');
                } else {
                    showNotification('Failed to resolve alert. Please try again.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Auto refresh every 5 minutes
        setInterval(refreshData, 300000);

        // Add pulse animation to critical alerts
        document.addEventListener('DOMContentLoaded', function() {
            const criticalAlerts = document.querySelectorAll('.alert-card.high');
            criticalAlerts.forEach(alert => {
                alert.style.animation = 'pulse 2s infinite';
            });
        });
    </script>

    <style>
        .blood-group-card.critical {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            animation: pulse 2s infinite;
        }

        .blood-group-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
        }

        .expiry-warning, .critical-warning {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem;
            border-radius: 4px;
            margin: 0.5rem 0;
            font-size: 0.85rem;
        }

        .critical-warning {
            background: rgba(220, 53, 69, 0.9);
            color: white;
            font-weight: bold;
        }

        .activities-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin: 2rem 0;
        }

        .activity-column {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: var(--hover-bg);
            transform: translateX(5px);
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
            font-size: 1.2rem;
            color: white;
        }

        .activity-item.donation .activity-icon {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .activity-item.usage .activity-icon {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }

        .activity-content h4 {
            margin: 0 0 0.5rem 0;
            color: var(--text-color);
            font-size: 1rem;
        }

        .activity-content p {
            margin: 0.25rem 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .activity-content small {
            color: var(--text-muted);
        }

        .no-activities, .no-data {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .no-activities i, .no-data i {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .success-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--card-bg);
            color: var(--text-color);
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            border-left: 4px solid #28a745;
        }

        .notification.error {
            border-left: 4px solid #dc3545;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        @media (max-width: 768px) {
            .activities-section {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$user_name = $_SESSION['username'];
$user_role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - HCRM</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 16px;
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .module-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .module-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .module-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-right: 15px;
        }

        .module-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .module-links {
            display: grid;
            gap: 10px;
        }

        .module-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: background 0.3s ease;
        }

        .module-link:hover {
            background: #e9ecef;
        }

        .module-link i {
            margin-right: 10px;
            width: 20px;
        }

        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .quick-actions h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .action-btn:hover {
            transform: scale(1.05);
            color: white;
        }

        .action-btn i {
            margin-right: 10px;
        }

        /* Module specific colors */
        .patient-management { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .medical-services { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .blood-organ { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .insurance-billing { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
        .staff-management { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); }
        .system-admin { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            background: white;
            color: #333;
            padding: 10px 15px;
            border-radius: 25px;
            text-decoration: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .back-btn:hover {
            transform: scale(1.05);
            color: #333;
        }

        @media (max-width: 768px) {
            .modules-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>

    <div class="admin-container">
        <div class="header">
            <h1><i class="fas fa-crown"></i> Admin Control Panel</h1>
            <p>Welcome back, <?php echo htmlspecialchars($user_name); ?>! Manage your hospital system from here.</p>
        </div>

        <div class="modules-grid">
            <!-- Patient Management -->
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon patient-management">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="module-title">Patient Management</div>
                </div>
                <div class="module-links">
                    <a href="patients.php" class="module-link">
                        <i class="fas fa-user-plus"></i> Manage Patients
                    </a>
                    <a href="patient-monitoring.php" class="module-link">
                        <i class="fas fa-user-injured"></i> Patient Monitoring
                    </a>
                    <a href="patient-conversion.php" class="module-link">
                        <i class="fas fa-exchange-alt"></i> Patient Conversion
                    </a>
                </div>
            </div>

            <!-- Medical Services -->
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon medical-services">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <div class="module-title">Medical Services</div>
                </div>
                <div class="module-links">
                    <a href="appointments.php" class="module-link">
                        <i class="fas fa-calendar-alt"></i> Appointments
                    </a>
                    <a href="doctors.php" class="module-link">
                        <i class="fas fa-user-md"></i> Doctors
                    </a>
                    <a href="lab-test-management.php" class="module-link">
                        <i class="fas fa-flask"></i> Lab Tests
                    </a>
                    <a href="pharmacy.php" class="module-link">
                        <i class="fas fa-pills"></i> Pharmacy
                    </a>
                </div>
            </div>

            <!-- Blood Bank & Organ Management -->
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon blood-organ">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="module-title">Blood Bank & Organ Management</div>
                </div>
                <div class="module-links">
                    <a href="blood-bank-management.php" class="module-link">
                        <i class="fas fa-tint"></i> Blood Bank Management
                    </a>
                    <a href="blood-donation-tracking.php" class="module-link">
                        <i class="fas fa-hand-holding-heart"></i> Blood Donation Tracking
                    </a>
                    <a href="organ-donation-management.php" class="module-link">
                        <i class="fas fa-heart"></i> Organ Donation
                    </a>
                    <a href="organ-transplant-tracking.php" class="module-link">
                        <i class="fas fa-procedures"></i> Organ Transplant Tracking
                    </a>
                </div>
            </div>

            <!-- Insurance & Billing -->
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon insurance-billing">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="module-title">Insurance & Billing</div>
                </div>
                <div class="module-links">
                    <a href="insurance-management.php" class="module-link">
                        <i class="fas fa-shield-alt"></i> Insurance Management
                    </a>
                    <a href="billing.php" class="module-link">
                        <i class="fas fa-money-bill-wave"></i> Billing Management
                    </a>
                    <a href="auto-billing-system.php" class="module-link">
                        <i class="fas fa-robot"></i> Auto Billing System
                    </a>
                </div>
            </div>

            <!-- Staff Management -->
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon staff-management">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="module-title">Staff Management</div>
                </div>
                <div class="module-links">
                    <a href="user-management.php" class="module-link">
                        <i class="fas fa-users-cog"></i> User Management
                    </a>
                    <a href="staff.php" class="module-link">
                        <i class="fas fa-user-nurse"></i> Staff Management
                    </a>
                    <a href="attendance.php" class="module-link">
                        <i class="fas fa-clock"></i> Attendance
                    </a>
                    <a href="intern-management.php" class="module-link">
                        <i class="fas fa-graduation-cap"></i> Intern Management
                    </a>
                    <a href="shift-management.php" class="module-link">
                        <i class="fas fa-calendar-check"></i> Shift Management
                    </a>
                </div>
            </div>

            <!-- System Administration -->
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon system-admin">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="module-title">System Administration</div>
                </div>
                <div class="module-links">
                    <a href="settings.php" class="module-link">
                        <i class="fas fa-cog"></i> System Settings
                    </a>
                    <a href="site-settings.php" class="module-link">
                        <i class="fas fa-globe"></i> Site Settings
                    </a>
                    <a href="equipment.php" class="module-link">
                        <i class="fas fa-tools"></i> Equipment Management
                    </a>
                    <a href="beds.php" class="module-link">
                        <i class="fas fa-bed"></i> Bed Management
                    </a>
                    <a href="ambulance-management.php" class="module-link">
                        <i class="fas fa-ambulance"></i> Ambulance Management
                    </a>
                    <a href="driver-management.php" class="module-link">
                        <i class="fas fa-id-card"></i> Driver Management
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <div class="action-buttons">
                <a href="reports.php" class="action-btn">
                    <i class="fas fa-chart-bar"></i> View Reports
                </a>
                <a href="cleanup-logs.php?stats" class="action-btn">
                    <i class="fas fa-broom"></i> System Cleanup
                </a>
                <a href="test-setup.php" class="action-btn">
                    <i class="fas fa-cog"></i> System Test
                </a>
                <a href="profile.php" class="action-btn">
                    <i class="fas fa-user"></i> My Profile
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.querySelectorAll('.module-card').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and has access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr_manager'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$user_role = $_SESSION['role'];
$message = '';

// Handle attendance marking
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'mark_attendance') {
    try {
        $staff_id = $_POST['staff_id'];
        $attendance_date = $_POST['attendance_date'];
        $status = $_POST['status'];
        $check_in_time = $_POST['check_in_time'] ?? null;
        $check_out_time = $_POST['check_out_time'] ?? null;
        $notes = $_POST['notes'] ?? '';
        
        // Check if attendance already exists for this date
        $existing = $db->query(
            "SELECT id FROM staff_attendance WHERE staff_id = ? AND attendance_date = ?",
            [$staff_id, $attendance_date]
        )->fetch();
        
        if ($existing) {
            // Update existing attendance
            $db->query(
                "UPDATE staff_attendance SET 
                    status = ?, check_in_time = ?, check_out_time = ?, notes = ?, updated_at = NOW() 
                 WHERE id = ?",
                [$status, $check_in_time, $check_out_time, $notes, $existing['id']]
            );
        } else {
            // Create new attendance record
            $db->query(
                "INSERT INTO staff_attendance (staff_id, attendance_date, status, check_in_time, check_out_time, notes, marked_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$staff_id, $attendance_date, $status, $check_in_time, $check_out_time, $notes, $_SESSION['user_id']]
            );
        }
        
        $message = "Attendance marked successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Handle salary calculation
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'calculate_salary') {
    try {
        $staff_id = $_POST['staff_id'];
        $month = $_POST['month'];
        $year = $_POST['year'];
        
        // Get staff details
        $staff = $db->query("SELECT * FROM staff WHERE id = ?", [$staff_id])->fetch();
        if (!$staff) {
            throw new Exception("Staff not found");
        }
        
        // Calculate attendance for the month
        $attendance_stats = $db->query(
            "SELECT 
                COUNT(*) as total_days,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_days,
                COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_days
             FROM staff_attendance 
             WHERE staff_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?",
            [$staff_id, $month, $year]
        )->fetch();
        
        // Calculate salary
        $base_salary = $staff['base_salary'];
        $daily_rate = $base_salary / 30; // Assuming 30 days per month
        $present_salary = $attendance_stats['present_days'] * $daily_rate;
        $half_day_salary = $attendance_stats['half_days'] * ($daily_rate / 2);
        $total_salary = $present_salary + $half_day_salary;
        
        // Calculate deductions
        $absent_deduction = $attendance_stats['absent_days'] * $daily_rate;
        $net_salary = $total_salary - $absent_deduction;
        
        // Store salary record
        $db->query(
            "INSERT INTO staff_salary (staff_id, month, year, base_salary, present_days, absent_days, 
                                      half_days, leave_days, total_salary, deductions, net_salary, 
                                      calculated_by, calculated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$staff_id, $month, $year, $base_salary, $attendance_stats['present_days'], 
             $attendance_stats['absent_days'], $attendance_stats['half_days'], $attendance_stats['leave_days'],
             $total_salary, $absent_deduction, $net_salary, $_SESSION['user_id']]
        );
        
        $message = "Salary calculated successfully! Net Salary: ₹" . number_format($net_salary, 2);
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get all staff members
$staff_members = [];
try {
    $staff_members = $db->query(
        "SELECT s.*, CONCAT(s.first_name, ' ', s.last_name) as full_name,
                d.name as department_name, r.role_display_name
         FROM staff s 
         LEFT JOIN departments d ON s.department_id = d.id
         LEFT JOIN roles r ON s.role_id = r.id
         WHERE s.is_active = 1 
         ORDER BY s.first_name, s.last_name"
    )->fetchAll();
} catch (Exception $e) {
    $staff_members = [];
}

// Get attendance for selected staff and date range
$selected_staff_id = $_GET['staff_id'] ?? null;
$selected_month = $_GET['month'] ?? date('n');
$selected_year = $_GET['year'] ?? date('Y');

$attendance_records = [];
if ($selected_staff_id) {
    try {
        $attendance_records = $db->query(
            "SELECT sa.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name
             FROM staff_attendance sa
             JOIN staff s ON sa.staff_id = s.id
             WHERE sa.staff_id = ? AND MONTH(sa.attendance_date) = ? AND YEAR(sa.attendance_date) = ?
             ORDER BY sa.attendance_date DESC",
            [$selected_staff_id, $selected_month, $selected_year]
        )->fetchAll();
    } catch (Exception $e) {
        $attendance_records = [];
    }
}

// Get attendance statistics
$attendance_stats = [];
try {
    $attendance_stats = $db->query(
        "SELECT 
            COUNT(*) as total_records,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_day_count,
            COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_count
         FROM staff_attendance 
         WHERE MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?",
        [$selected_month, $selected_year]
    )->fetch();
} catch (Exception $e) {
    $attendance_stats = [];
}

// Get salary records
$salary_records = [];
try {
    $salary_records = $db->query(
        "SELECT ss.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name
         FROM staff_salary ss
         JOIN staff s ON ss.staff_id = s.id
         ORDER BY ss.year DESC, ss.month DESC
         LIMIT 50"
    )->fetchAll();
} catch (Exception $e) {
    $salary_records = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-hospital"></i> Hospital CRM</h2>
                <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="billing.php"><i class="fas fa-money-bill-wave"></i> Billing</a></li>
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="staff.php"><i class="fas fa-user-nurse"></i> Staff</a></li>
                <li><a href="attendance.php" class="active"><i class="fas fa-clock"></i> Attendance</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Attendance Management</h1>
                    <p>Track staff attendance and manage salaries</p>
                </div>
                <div class="header-right">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                        <i class="fas fa-moon"></i>
                    </button>
                    <div class="user-info">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['role_display']); ?></span>
                    </div>
                    <a href="logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Attendance Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo number_format($attendance_stats['total_records'] ?? 0); ?></h3>
                    <p>Total Records</p>
                    <i class="fas fa-calendar-check stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($attendance_stats['present_count'] ?? 0); ?></h3>
                    <p>Present</p>
                    <i class="fas fa-user-check stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($attendance_stats['absent_count'] ?? 0); ?></h3>
                    <p>Absent</p>
                    <i class="fas fa-user-times stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo number_format($attendance_stats['half_day_count'] ?? 0); ?></h3>
                    <p>Half Days</p>
                    <i class="fas fa-user-clock stat-icon"></i>
                </div>
            </div>

            <!-- Mark Attendance -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Mark Attendance</h3>
                </div>
                <form method="POST" class="attendance-form">
                    <input type="hidden" name="action" value="mark_attendance">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Select Staff</label>
                            <select name="staff_id" class="form-control" required>
                                <option value="">Select staff member...</option>
                                <?php foreach ($staff_members as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>">
                                        <?php echo htmlspecialchars($staff['full_name'] . ' (' . $staff['role_display_name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" name="attendance_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control" required onchange="toggleTimeInputs(this.value)">
                                <option value="">Select status...</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="half_day">Half Day</option>
                                <option value="leave">Leave</option>
                                <option value="holiday">Holiday</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row" id="timeInputs" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Check In Time</label>
                            <input type="time" name="check_in_time" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Check Out Time</label>
                            <input type="time" name="check_out_time" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Mark Attendance
                    </button>
                </form>
            </div>

            <!-- View Attendance -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">View Attendance</h3>
                </div>
                
                <form method="GET" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Select Staff</label>
                            <select name="staff_id" class="form-control" onchange="this.form.submit()">
                                <option value="">All Staff</option>
                                <?php foreach ($staff_members as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>" 
                                            <?php echo $selected_staff_id == $staff['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Month</label>
                            <select name="month" class="form-control" onchange="this.form.submit()">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $selected_month == $i ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-control" onchange="this.form.submit()">
                                <?php for ($year = date('Y') - 2; $year <= date('Y'); $year++): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $selected_year == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($attendance_records)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Staff</th>
                                    <th>Status</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Working Hours</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['staff_name']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo getStatusBadgeClass($record['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : '-'; ?></td>
                                        <td><?php echo $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : '-'; ?></td>
                                        <td>
                                            <?php 
                                            if ($record['check_in_time'] && $record['check_out_time']) {
                                                $hours = (strtotime($record['check_out_time']) - strtotime($record['check_in_time'])) / 3600;
                                                echo number_format($hours, 1) . ' hrs';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">No attendance records found for the selected criteria.</p>
                <?php endif; ?>
            </div>

            <!-- Salary Management -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Salary Management</h3>
                    <button class="btn btn-primary" onclick="showSalaryModal()">
                        <i class="fas fa-calculator"></i> Calculate Salary
                    </button>
                </div>
                
                <?php if (!empty($salary_records)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Staff</th>
                                    <th>Month/Year</th>
                                    <th>Present Days</th>
                                    <th>Absent Days</th>
                                    <th>Base Salary</th>
                                    <th>Deductions</th>
                                    <th>Net Salary</th>
                                    <th>Calculated On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salary_records as $salary): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($salary['staff_name']); ?></td>
                                        <td><?php echo date('F Y', mktime(0, 0, 0, $salary['month'], 1, $salary['year'])); ?></td>
                                        <td><?php echo $salary['present_days']; ?></td>
                                        <td><?php echo $salary['absent_days']; ?></td>
                                        <td>₹<?php echo number_format($salary['base_salary'], 2); ?></td>
                                        <td>₹<?php echo number_format($salary['deductions'], 2); ?></td>
                                        <td>
                                            <strong>₹<?php echo number_format($salary['net_salary'], 2); ?></strong>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($salary['calculated_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">No salary records found.</p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Calculate Salary Modal -->
    <div id="salaryModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Calculate Salary</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" class="salary-form">
                    <input type="hidden" name="action" value="calculate_salary">
                    
                    <div class="form-group">
                        <label class="form-label">Select Staff</label>
                        <select name="staff_id" class="form-control" required>
                            <option value="">Select staff member...</option>
                            <?php foreach ($staff_members as $staff): ?>
                                <option value="<?php echo $staff['id']; ?>">
                                    <?php echo htmlspecialchars($staff['full_name'] . ' - ₹' . number_format($staff['base_salary'], 2)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Month</label>
                            <select name="month" class="form-control" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo date('n') == $i ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-control" required>
                                <?php for ($year = date('Y') - 2; $year <= date('Y'); $year++): ?>
                                    <option value="<?php echo $year; ?>" <?php echo date('Y') == $year ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Calculate Salary</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;
        const icon = themeToggle.querySelector('i');

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }

        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');

        function checkMobile() {
            if (window.innerWidth <= 768) {
                mobileMenuToggle.style.display = 'block';
                sidebar.classList.remove('open');
            } else {
                mobileMenuToggle.style.display = 'none';
                sidebar.classList.remove('open');
            }
        }

        mobileMenuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        window.addEventListener('resize', checkMobile);
        checkMobile();

        // Toggle time inputs based on status
        function toggleTimeInputs(status) {
            const timeInputs = document.getElementById('timeInputs');
            if (['present', 'half_day'].includes(status)) {
                timeInputs.style.display = 'block';
            } else {
                timeInputs.style.display = 'none';
            }
        }

        // Modal functions
        function showSalaryModal() {
            document.getElementById('salaryModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('salaryModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('salaryModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>

    <style>
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-form {
            margin-bottom: 1.5rem;
        }

        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: var(--bg-card);
            margin: 10% auto;
            padding: 0;
            border-radius: var(--border-radius-lg);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
        }

        .close {
            color: var(--text-secondary);
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--text-primary);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .mobile-menu-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            display: none;
        }

        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2rem;
            opacity: 0.1;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
</body>
</html>

<?php
// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'present':
            return 'success';
        case 'absent':
            return 'danger';
        case 'half_day':
            return 'warning';
        case 'leave':
            return 'info';
        case 'holiday':
            return 'secondary';
        default:
            return 'secondary';
    }
}
?>
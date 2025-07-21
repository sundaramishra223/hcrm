<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle form submissions
if ($_POST) {
    try {
        switch ($_POST['action']) {
            case 'add_shift':
                $shift_sql = "INSERT INTO shifts (hospital_id, shift_name, start_time, end_time, is_active) VALUES (1, ?, ?, ?, 1)";
                $db->query($shift_sql, [
                    $_POST['shift_name'],
                    $_POST['start_time'],
                    $_POST['end_time']
                ]);
                $message = "Shift '{$_POST['shift_name']}' added successfully!";
                break;
                
            case 'update_shift':
                $update_sql = "UPDATE shifts SET shift_name = ?, start_time = ?, end_time = ?, is_active = ? WHERE id = ?";
                $db->query($update_sql, [
                    $_POST['shift_name'],
                    $_POST['start_time'],
                    $_POST['end_time'],
                    isset($_POST['is_active']) ? 1 : 0,
                    $_POST['shift_id']
                ]);
                $message = "Shift updated successfully!";
                break;
                
            case 'assign_staff_shift':
                // Update staff shift assignment
                $staff_sql = "UPDATE staff SET shift_id = ?, shift_timing = ? WHERE id = ?";
                $shift_info = $db->query("SELECT shift_name, start_time, end_time FROM shifts WHERE id = ?", [$_POST['shift_id']])->fetch();
                $timing = date('H:i', strtotime($shift_info['start_time'])) . ' - ' . date('H:i', strtotime($shift_info['end_time']));
                
                $db->query($staff_sql, [
                    $_POST['shift_id'],
                    $timing,
                    $_POST['staff_id']
                ]);
                $message = "Staff shift assigned successfully!";
                break;
                
            case 'create_weekly_schedule':
                $week_start = $_POST['week_start_date'];
                $staff_id = $_POST['staff_id'];
                
                // Delete existing schedule for this week
                $db->query("DELETE FROM staff_schedules WHERE staff_id = ? AND week_start_date = ?", [$staff_id, $week_start]);
                
                // Insert new schedule
                $schedule_sql = "INSERT INTO staff_schedules (staff_id, week_start_date, monday_shift_id, tuesday_shift_id, wednesday_shift_id, thursday_shift_id, friday_shift_id, saturday_shift_id, sunday_shift_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $db->query($schedule_sql, [
                    $staff_id,
                    $week_start,
                    $_POST['monday_shift'] ?: NULL,
                    $_POST['tuesday_shift'] ?: NULL,
                    $_POST['wednesday_shift'] ?: NULL,
                    $_POST['thursday_shift'] ?: NULL,
                    $_POST['friday_shift'] ?: NULL,
                    $_POST['saturday_shift'] ?: NULL,
                    $_POST['sunday_shift'] ?: NULL,
                    $_POST['schedule_notes'] ?: '',
                    $_SESSION['user_id']
                ]);
                $message = "Weekly schedule created successfully!";
                break;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get all shifts
$shifts = $db->query("SELECT * FROM shifts WHERE hospital_id = 1 ORDER BY start_time")->fetchAll();

// Get all staff with shift info
$staff = $db->query("
    SELECT s.*, sh.shift_name, sh.start_time, sh.end_time,
           CONCAT(s.first_name, ' ', s.last_name) as full_name
    FROM staff s
    LEFT JOIN shifts sh ON s.shift_id = sh.id
    WHERE s.is_active = 1
    ORDER BY s.staff_type, s.first_name
")->fetchAll();

// Get shift templates
$templates = $db->query("
    SELECT st.*, sh.shift_name, sh.start_time, sh.end_time
    FROM shift_templates st
    JOIN shifts sh ON st.default_shift_id = sh.id
    WHERE st.is_active = 1
    ORDER BY st.staff_type, st.template_name
")->fetchAll();

// Get current week schedules
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$schedules = $db->query("
    SELECT ss.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name, s.staff_type
    FROM staff_schedules ss
    JOIN staff s ON ss.staff_id = s.id
    WHERE ss.week_start_date = ?
    ORDER BY s.staff_type, s.first_name
", [$current_week_start])->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php 
    include_once 'includes/site-config.php';
    renderSiteHead('Shift Management');
    ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
    <style>
        .shift-management {
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
        }
        
        .management-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: var(--bg-card);
            border: 2px solid var(--border-color);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
            background: var(--bg-card);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow-md);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .shifts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .shift-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        
        .shift-card h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .shift-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
        }
        
        .shift-time {
            font-weight: bold;
            color: var(--accent-color);
        }
        
        .staff-list {
            margin-top: 15px;
        }
        
        .staff-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-section {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 5px;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 15px;
        }
        
        .day-header {
            background: var(--primary-color);
            color: white;
            padding: 10px;
            text-align: center;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .day-shift {
            padding: 8px;
            background: var(--bg-secondary);
            border-radius: 5px;
            text-align: center;
        }
        
        .btn {
            background: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        
        .btn-secondary {
            background: var(--secondary-color);
        }
        
        .btn-warning {
            background: var(--accent-color);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: var(--shadow-md);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="shift-management">
                <div class="page-header">
                    <h1><i class="fas fa-clock"></i> Shift Management</h1>
                    <p>Manage hospital shifts, staff assignments, and weekly schedules</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert <?php echo strpos($message, 'Error') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($shifts); ?></div>
                        <div class="stat-label">Total Shifts</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($staff); ?></div>
                        <div class="stat-label">Staff Members</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($staff, function($s) { return $s['shift_id']; })); ?></div>
                        <div class="stat-label">Assigned Staff</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($schedules); ?></div>
                        <div class="stat-label">This Week Schedules</div>
                    </div>
                </div>
                
                <!-- Management Tabs -->
                <div class="management-tabs">
                    <div class="tab-btn active" onclick="showTab('shifts')">
                        <i class="fas fa-clock"></i> Shifts
                    </div>
                    <div class="tab-btn" onclick="showTab('assignments')">
                        <i class="fas fa-users"></i> Staff Assignments
                    </div>
                    <div class="tab-btn" onclick="showTab('schedules')">
                        <i class="fas fa-calendar-week"></i> Weekly Schedules
                    </div>
                    <div class="tab-btn" onclick="showTab('templates')">
                        <i class="fas fa-copy"></i> Templates
                    </div>
                </div>
                
                <!-- Shifts Tab -->
                <div id="shifts" class="tab-content active">
                    <h2>Manage Shifts</h2>
                    
                    <div class="form-grid">
                        <!-- Add New Shift -->
                        <div class="form-section">
                            <h3><i class="fas fa-plus"></i> Add New Shift</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_shift">
                                <div class="form-group">
                                    <label>Shift Name</label>
                                    <input type="text" name="shift_name" required placeholder="e.g., Morning Shift">
                                </div>
                                <div class="form-group">
                                    <label>Start Time</label>
                                    <input type="time" name="start_time" required>
                                </div>
                                <div class="form-group">
                                    <label>End Time</label>
                                    <input type="time" name="end_time" required>
                                </div>
                                <button type="submit" class="btn">Add Shift</button>
                            </form>
                        </div>
                        
                        <!-- Current Shifts -->
                        <div class="form-section">
                            <h3><i class="fas fa-list"></i> Current Shifts</h3>
                            <?php foreach ($shifts as $shift): ?>
                                <div class="shift-info">
                                    <div>
                                        <strong><?php echo htmlspecialchars($shift['shift_name']); ?></strong>
                                        <br><span class="shift-time"><?php echo date('H:i', strtotime($shift['start_time'])) . ' - ' . date('H:i', strtotime($shift['end_time'])); ?></span>
                                    </div>
                                    <div>
                                        <button onclick="editShift(<?php echo htmlspecialchars(json_encode($shift)); ?>)" class="btn btn-warning" style="padding: 5px 10px;">Edit</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Staff Assignments Tab -->
                <div id="assignments" class="tab-content">
                    <h2>Staff Shift Assignments</h2>
                    
                    <div class="form-grid">
                        <!-- Assign Staff to Shift -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-plus"></i> Assign Staff to Shift</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="assign_staff_shift">
                                <div class="form-group">
                                    <label>Select Staff</label>
                                    <select name="staff_id" required>
                                        <option value="">Choose Staff Member</option>
                                        <?php foreach ($staff as $s): ?>
                                            <option value="<?php echo $s['id']; ?>">
                                                <?php echo htmlspecialchars($s['full_name'] . ' (' . ucfirst($s['staff_type']) . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Select Shift</label>
                                    <select name="shift_id" required>
                                        <option value="">Choose Shift</option>
                                        <?php foreach ($shifts as $shift): ?>
                                            <option value="<?php echo $shift['id']; ?>">
                                                <?php echo htmlspecialchars($shift['shift_name'] . ' (' . date('H:i', strtotime($shift['start_time'])) . ' - ' . date('H:i', strtotime($shift['end_time'])) . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn">Assign Shift</button>
                            </form>
                        </div>
                        
                        <!-- Current Staff Assignments -->
                        <div class="form-section">
                            <h3><i class="fas fa-users-cog"></i> Current Assignments</h3>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php 
                                $staff_by_type = [];
                                foreach ($staff as $s) {
                                    $staff_by_type[$s['staff_type']][] = $s;
                                }
                                ?>
                                <?php foreach ($staff_by_type as $type => $type_staff): ?>
                                    <h4 style="color: var(--primary-color); margin-top: 20px;"><?php echo ucfirst(str_replace('_', ' ', $type)); ?></h4>
                                    <?php foreach ($type_staff as $s): ?>
                                        <div class="staff-item">
                                            <div>
                                                <strong><?php echo htmlspecialchars($s['full_name']); ?></strong>
                                                <br><small><?php echo $s['shift_name'] ? $s['shift_name'] . ' (' . $s['shift_timing'] . ')' : 'No shift assigned'; ?></small>
                                            </div>
                                            <div>
                                                <?php if ($s['shift_name']): ?>
                                                    <span style="color: green;">✓ Assigned</span>
                                                <?php else: ?>
                                                    <span style="color: orange;">⚠ Unassigned</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Weekly Schedules Tab -->
                <div id="schedules" class="tab-content">
                    <h2>Weekly Schedules</h2>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-calendar-plus"></i> Create Weekly Schedule</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_weekly_schedule">
                            <div class="form-grid">
                                <div>
                                    <div class="form-group">
                                        <label>Staff Member</label>
                                        <select name="staff_id" required>
                                            <option value="">Choose Staff</option>
                                            <?php foreach ($staff as $s): ?>
                                                <option value="<?php echo $s['id']; ?>">
                                                    <?php echo htmlspecialchars($s['full_name'] . ' (' . ucfirst($s['staff_type']) . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Week Starting Date</label>
                                        <input type="date" name="week_start_date" value="<?php echo $current_week_start; ?>" required>
                                    </div>
                                </div>
                                <div>
                                    <div class="form-group">
                                        <label>Schedule Notes</label>
                                        <textarea name="schedule_notes" rows="3" placeholder="Any special notes for this schedule"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <h4>Daily Shifts</h4>
                            <div class="schedule-grid">
                                <div class="day-header">Monday</div>
                                <div class="day-header">Tuesday</div>
                                <div class="day-header">Wednesday</div>
                                <div class="day-header">Thursday</div>
                                <div class="day-header">Friday</div>
                                <div class="day-header">Saturday</div>
                                <div class="day-header">Sunday</div>
                                
                                <?php $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']; ?>
                                <?php foreach ($days as $day): ?>
                                    <div class="day-shift">
                                        <select name="<?php echo $day; ?>_shift">
                                            <option value="">Off</option>
                                            <?php foreach ($shifts as $shift): ?>
                                                <option value="<?php echo $shift['id']; ?>">
                                                    <?php echo htmlspecialchars($shift['shift_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="submit" class="btn" style="margin-top: 20px;">Create Schedule</button>
                        </form>
                    </div>
                </div>
                
                <!-- Templates Tab -->
                <div id="templates" class="tab-content">
                    <h2>Shift Templates</h2>
                    <p>Pre-configured shift templates for different staff types</p>
                    
                    <div class="shifts-grid">
                        <?php foreach ($templates as $template): ?>
                            <div class="shift-card">
                                <h3><?php echo htmlspecialchars($template['template_name']); ?></h3>
                                <div class="shift-info">
                                    <span>Staff Type</span>
                                    <span><strong><?php echo ucfirst(str_replace('_', ' ', $template['staff_type'])); ?></strong></span>
                                </div>
                                <div class="shift-info">
                                    <span>Default Shift</span>
                                    <span class="shift-time"><?php echo htmlspecialchars($template['shift_name']); ?></span>
                                </div>
                                <div class="shift-info">
                                    <span>Timing</span>
                                    <span><?php echo date('H:i', strtotime($template['start_time'])) . ' - ' . date('H:i', strtotime($template['end_time'])); ?></span>
                                </div>
                                <div class="shift-info">
                                    <span>Hours/Week</span>
                                    <span><strong><?php echo $template['hours_per_week']; ?> hrs</strong></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'includes/theme-system.php'; ?>
    
    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function editShift(shift) {
            // Simple edit functionality - you can enhance this with a modal
            const newName = prompt('Enter new shift name:', shift.shift_name);
            if (newName) {
                // You can add AJAX here to update the shift
                location.reload();
            }
        }
    </script>
</body>
</html>
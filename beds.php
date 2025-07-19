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
$allowed_roles = ['admin', 'nurse', 'receptionist', 'doctor', 'intern_doctor', 'intern_nurse'];

if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add_bed':
                $sql = "INSERT INTO beds (bed_number, bed_type, status, daily_rate, created_at) VALUES (?, ?, 'available', ?, NOW())";
                $db->query($sql, [
                    $_POST['bed_number'],
                    $_POST['bed_type'],
                    $_POST['daily_rate'] ?? 0
                ]);
                $message = "Bed added successfully!";
                break;
                
            case 'assign_patient':
                $db->getConnection()->beginTransaction();
                
                // Check if bed is available
                $bed = $db->query("SELECT status FROM beds WHERE id = ?", [$_POST['bed_id']])->fetch();
                if ($bed['status'] !== 'available') {
                    throw new Exception("Bed is not available for assignment");
                }
                
                // Update bed status
                $db->query("UPDATE beds SET status = 'occupied', current_patient_id = ?, last_updated = NOW() WHERE id = ?", 
                          [$_POST['patient_id'], $_POST['bed_id']]);
                
                // Create bed assignment record
                $assignment_sql = "INSERT INTO bed_assignments (bed_id, patient_id, assigned_date, status, notes, assigned_by) VALUES (?, ?, ?, 'active', ?, ?)";
                $db->query($assignment_sql, [
                    $_POST['bed_id'],
                    $_POST['patient_id'],
                    $_POST['admission_date'] ?? date('Y-m-d'),
                    $_POST['notes'] ?? '',
                    $_SESSION['user_id']
                ]);
                
                $db->getConnection()->commit();
                $message = "Patient assigned to bed successfully!";
                break;
                
            case 'discharge_patient':
                $db->getConnection()->beginTransaction();
                
                // Update bed assignment
                $db->query("UPDATE bed_assignments SET status = 'discharged', discharge_date = NOW() WHERE id = ?", 
                          [$_POST['assignment_id']]);
                
                // Get bed info and update status
                $assignment = $db->query("SELECT bed_id FROM bed_assignments WHERE id = ?", [$_POST['assignment_id']])->fetch();
                $db->query("UPDATE beds SET status = 'maintenance', current_patient_id = NULL, last_updated = NOW() WHERE id = ?", 
                          [$assignment['bed_id']]);
                
                $db->getConnection()->commit();
                $message = "Patient discharged successfully! Bed marked for maintenance.";
                break;
                
            case 'update_bed_status':
                $db->query("UPDATE beds SET status = ?, last_updated = NOW() WHERE id = ?", 
                          [$_POST['new_status'], $_POST['bed_id']]);
                $message = "Bed status updated successfully!";
                break;
        }
    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->getConnection()->rollBack();
        }
        $message = "Error: " . $e->getMessage();
    }
}

// Get beds with patient information
$beds = [];
try {
    $beds = $db->query("
        SELECT b.*, 
        ba.id as assignment_id,
        ba.assigned_date,
        ba.notes as assignment_notes,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.patient_id,
        p.phone as patient_phone
        FROM beds b
        LEFT JOIN bed_assignments ba ON b.id = ba.bed_id AND ba.status = 'active'
        LEFT JOIN patients p ON ba.patient_id = p.id
        ORDER BY b.bed_number
    ")->fetchAll();
} catch (Exception $e) {
    $beds = [];
}

// Get patients available for assignment
$available_patients = [];
try {
    $available_patients = $db->query("
        SELECT p.id, p.patient_id, CONCAT(p.first_name, ' ', p.last_name) as full_name, p.phone
        FROM patients p
        LEFT JOIN bed_assignments ba ON p.id = ba.patient_id AND ba.status = 'active'
        WHERE ba.id IS NULL
        ORDER BY p.first_name, p.last_name
    ")->fetchAll();
} catch (Exception $e) {
    $available_patients = [];
}

// Get statistics
$stats = [];
try {
    $stats['total_beds'] = $db->query("SELECT COUNT(*) as count FROM beds")->fetch()['count'];
    $stats['occupied_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'occupied'")->fetch()['count'];
    $stats['available_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'available'")->fetch()['count'];
    $stats['maintenance_beds'] = $db->query("SELECT COUNT(*) as count FROM beds WHERE status = 'maintenance'")->fetch()['count'];
    $stats['admitted_today'] = $db->query("SELECT COUNT(*) as count FROM bed_assignments WHERE status = 'active' AND DATE(assigned_date) = CURDATE()")->fetch()['count'];
    $stats['discharged_today'] = $db->query("SELECT COUNT(*) as count FROM bed_assignments WHERE status = 'discharged' AND DATE(discharge_date) = CURDATE()")->fetch()['count'];
} catch (Exception $e) {
    $stats = ['total_beds' => 0, 'occupied_beds' => 0, 'available_beds' => 0, 'maintenance_beds' => 0, 'admitted_today' => 0, 'discharged_today' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bed Management - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .bed-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: var(--transition-all);
            position: relative;
            overflow: hidden;
        }
        
        .bed-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .bed-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-available {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-occupied {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .status-maintenance {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .bed-header {
            margin-bottom: 1rem;
        }
        
        .bed-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .bed-type {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .bed-details {
            margin-bottom: 1rem;
        }
        
        .bed-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
    </style>
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
                <li><a href="beds.php" class="active"><i class="fas fa-bed"></i> Bed Management</a></li>
                <li><a href="patient-monitoring.php"><i class="fas fa-user-injured"></i> Patient Monitoring</a></li>
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="ambulance-management.php"><i class="fas fa-ambulance"></i> Ambulance</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1><i class="fas fa-bed"></i> Bed Management</h1>
                    <p>Manage hospital beds and patient assignments</p>
                </div>
                <div class="header-right">
                    <div class="theme-controls">
                        <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                            <i class="fas fa-moon"></i>
                        </button>
                        <button class="color-toggle" id="colorToggle" title="Change Colors">
                            <i class="fas fa-palette"></i>
                        </button>
                    </div>
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
                <div class="alert alert-success animate-fade-in">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card animate-fade-in">
                    <h3><?php echo number_format($stats['total_beds']); ?></h3>
                    <p>Total Beds</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.1s;">
                    <h3 class="text-success"><?php echo number_format($stats['available_beds']); ?></h3>
                    <p>Available</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.2s;">
                    <h3 class="text-danger"><?php echo number_format($stats['occupied_beds']); ?></h3>
                    <p>Occupied</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.3s;">
                    <h3 class="text-warning"><?php echo number_format($stats['maintenance_beds']); ?></h3>
                    <p>Maintenance</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.4s;">
                    <h3><?php echo number_format($stats['admitted_today']); ?></h3>
                    <p>Admitted Today</p>
                </div>
                <div class="stat-card animate-fade-in" style="animation-delay: 0.5s;">
                    <h3><?php echo number_format($stats['discharged_today']); ?></h3>
                    <p>Discharged Today</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-4 mb-6">
                <?php if (in_array($user_role, ['admin', 'nurse', 'receptionist'])): ?>
                    <button class="btn btn-primary" onclick="showAddBedModal()">
                        <i class="fas fa-plus"></i> Add Bed
                    </button>
                <?php endif; ?>
            </div>

            <!-- Beds Grid -->
            <div class="bed-grid">
                <?php if (empty($beds)): ?>
                    <div class="card text-center" style="grid-column: 1 / -1;">
                        <div class="card-body">
                            <i class="fas fa-bed" style="font-size: 4rem; opacity: 0.3; margin-bottom: 1rem;"></i>
                            <h3>No beds found</h3>
                            <p>Add your first bed to get started</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($beds as $bed): ?>
                        <div class="bed-card animate-fade-in">
                            <!-- Status Badge -->
                            <div class="bed-status status-<?php echo $bed['status']; ?>">
                                <?php echo ucfirst($bed['status']); ?>
                            </div>
                            
                            <!-- Bed Header -->
                            <div class="bed-header">
                                <h4 class="bed-number">Bed <?php echo htmlspecialchars($bed['bed_number']); ?></h4>
                                <p class="bed-type"><?php echo htmlspecialchars(ucfirst($bed['bed_type'])); ?></p>
                            </div>
                            
                            <!-- Bed Details -->
                            <div class="bed-details">
                                <?php if ($bed['status'] === 'occupied' && $bed['patient_name']): ?>
                                    <div class="detail-row mb-2">
                                        <strong>Patient:</strong> <?php echo htmlspecialchars($bed['patient_name']); ?>
                                    </div>
                                    <div class="detail-row mb-2">
                                        <strong>Patient ID:</strong> <?php echo htmlspecialchars($bed['patient_id']); ?>
                                    </div>
                                    <div class="detail-row mb-2">
                                        <strong>Admitted:</strong> <?php echo date('M d, Y', strtotime($bed['assigned_date'])); ?>
                                    </div>
                                    <?php if ($bed['patient_phone']): ?>
                                        <div class="detail-row mb-2">
                                            <strong>Phone:</strong> <?php echo htmlspecialchars($bed['patient_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($bed['assignment_notes']): ?>
                                        <div class="detail-row mb-2">
                                            <strong>Notes:</strong> <?php echo htmlspecialchars(substr($bed['assignment_notes'], 0, 50)); ?>
                                            <?php if (strlen($bed['assignment_notes']) > 50): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="detail-row mb-2">
                                        <strong>Daily Rate:</strong> ₹<?php echo number_format($bed['daily_rate'] ?? 0, 2); ?>
                                    </div>
                                    <div class="detail-row mb-2">
                                        <strong>Last Updated:</strong> <?php echo $bed['last_updated'] ? date('M d, Y', strtotime($bed['last_updated'])) : 'N/A'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="bed-actions">
                                <?php if ($bed['status'] === 'available'): ?>
                                    <button class="btn btn-success btn-sm" onclick="showAssignModal(<?php echo $bed['id']; ?>, '<?php echo htmlspecialchars($bed['bed_number']); ?>')">
                                        <i class="fas fa-user-plus"></i> Assign Patient
                                    </button>
                                <?php elseif ($bed['status'] === 'occupied'): ?>
                                    <button class="btn btn-warning btn-sm" onclick="showDischargeModal(<?php echo $bed['assignment_id']; ?>, '<?php echo htmlspecialchars($bed['patient_name']); ?>')">
                                        <i class="fas fa-sign-out-alt"></i> Discharge
                                    </button>
                                <?php elseif ($bed['status'] === 'maintenance'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="update_bed_status">
                                        <input type="hidden" name="bed_id" value="<?php echo $bed['id']; ?>">
                                        <input type="hidden" name="new_status" value="available">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check"></i> Mark Available
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Add Bed Modal -->
    <div class="modal" id="addBedModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Bed</h5>
                    <button type="button" class="btn-close" onclick="closeModal('addBedModal')">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_bed">
                        
                        <div class="form-group">
                            <label for="bed_number">Bed Number</label>
                            <input type="text" name="bed_number" id="bed_number" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="bed_type">Bed Type</label>
                            <select name="bed_type" id="bed_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="general">General</option>
                                <option value="icu">ICU</option>
                                <option value="private">Private</option>
                                <option value="emergency">Emergency</option>
                                <option value="pediatric">Pediatric</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="daily_rate">Daily Rate (₹)</label>
                            <input type="number" name="daily_rate" id="daily_rate" class="form-control" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addBedModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Bed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Patient Modal -->
    <div class="modal" id="assignModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Patient to Bed</h5>
                    <button type="button" class="btn-close" onclick="closeModal('assignModal')">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_patient">
                        <input type="hidden" name="bed_id" id="assign_bed_id">
                        
                        <div class="form-group">
                            <label>Bed: <strong id="assign_bed_info"></strong></label>
                        </div>
                        
                        <div class="form-group">
                            <label for="patient_id">Patient</label>
                            <select name="patient_id" id="patient_id" class="form-control" required>
                                <option value="">Select Patient</option>
                                <?php foreach ($available_patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['full_name'] . ' (' . $patient['patient_id'] . ') - ' . $patient['phone']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="admission_date">Admission Date</label>
                            <input type="date" name="admission_date" id="admission_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Admission notes or special requirements..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                        <button type="submit" class="btn btn-success">Assign Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Discharge Modal -->
    <div class="modal" id="dischargeModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Discharge Patient</h5>
                    <button type="button" class="btn-close" onclick="closeModal('dischargeModal')">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="discharge_patient">
                        <input type="hidden" name="assignment_id" id="discharge_assignment_id">
                        
                        <div class="form-group">
                            <label>Patient: <strong id="discharge_patient_name"></strong></label>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Patient will be discharged and bed will be marked for maintenance.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('dischargeModal')">Cancel</button>
                        <button type="submit" class="btn btn-warning">Discharge Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showAddBedModal() {
            document.getElementById('addBedModal').classList.add('show');
        }
        
        function showAssignModal(bedId, bedNumber) {
            document.getElementById('assign_bed_id').value = bedId;
            document.getElementById('assign_bed_info').textContent = 'Bed ' + bedNumber;
            document.getElementById('assignModal').classList.add('show');
        }
        
        function showDischargeModal(assignmentId, patientName) {
            document.getElementById('discharge_assignment_id').value = assignmentId;
            document.getElementById('discharge_patient_name').textContent = patientName;
            document.getElementById('dischargeModal').classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        // Theme controls (same as other pages)
        const themeToggle = document.getElementById('themeToggle');
        const colorToggle = document.getElementById('colorToggle');
        const html = document.documentElement;
        
        const themes = ['light', 'dark', 'medical'];
        let currentThemeIndex = 0;
        
        const savedTheme = localStorage.getItem('theme') || 'light';
        currentThemeIndex = themes.indexOf(savedTheme);
        if (currentThemeIndex === -1) currentThemeIndex = 0;
        
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);
        
        themeToggle.addEventListener('click', () => {
            currentThemeIndex = (currentThemeIndex + 1) % themes.length;
            const newTheme = themes[currentThemeIndex];
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });
        
        colorToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'medical' ? 'light' : 'medical';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
            currentThemeIndex = themes.indexOf(newTheme);
        });
        
        function updateThemeIcon(theme) {
            const themeIcon = themeToggle.querySelector('i');
            const colorIcon = colorToggle.querySelector('i');
            
            switch(theme) {
                case 'light':
                    themeIcon.className = 'fas fa-sun';
                    colorIcon.className = 'fas fa-palette';
                    break;
                case 'dark':
                    themeIcon.className = 'fas fa-moon';
                    colorIcon.className = 'fas fa-palette';
                    break;
                case 'medical':
                    themeIcon.className = 'fas fa-sun';
                    colorIcon.className = 'fas fa-heart';
                    break;
            }
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });
    </script>
</body>
</html>
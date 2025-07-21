<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$user_role = $_SESSION['role'];
$message = '';

// Check access permissions
$allowed_roles = ['admin', 'doctor', 'nurse', 'receptionist', 'intern_doctor', 'intern_nurse'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

// Handle patient type conversion
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'convert_patient') {
    try {
        $patient_id = $_POST['patient_id'];
        $new_type = $_POST['new_type'];
        $conversion_reason = $_POST['conversion_reason'];
        $bed_id = $_POST['bed_id'] ?? null;
        
        $db->getConnection()->beginTransaction();
        
        // Get current patient info
        $patient = $db->query("SELECT * FROM patients WHERE id = ?", [$patient_id])->fetch();
        if (!$patient) {
            throw new Exception("Patient not found");
        }
        
        $old_type = $patient['patient_type'];
        
        // Update patient type
        $db->query(
            "UPDATE patients SET patient_type = ? WHERE id = ?",
            [$new_type, $patient_id]
        );
        
        // Handle bed assignment/discharge
        if ($new_type === 'inpatient' && $bed_id) {
            // Check if bed is available
            $bed_status = $db->query("SELECT status FROM beds WHERE id = ?", [$bed_id])->fetch()['status'];
            if ($bed_status !== 'available') {
                throw new Exception("Selected bed is not available");
            }
            
            // Assign bed
            $db->query(
                "INSERT INTO bed_assignments (bed_id, patient_id, assigned_date, status, notes, assigned_by) 
                 VALUES (?, ?, NOW(), 'active', ?, ?)",
                [$bed_id, $patient_id, $conversion_reason, $_SESSION['user_id']]
            );
            
            // Update bed status
            $db->query(
                "UPDATE beds SET status = 'occupied' WHERE id = ?",
                [$bed_id]
            );
            
        } elseif ($old_type === 'inpatient' && $new_type === 'outpatient') {
            // Discharge from bed
            $current_assignment = $db->query(
                "SELECT * FROM bed_assignments WHERE patient_id = ? AND status = 'active'",
                [$patient_id]
            )->fetch();
            
            if ($current_assignment) {
                // Update bed assignment
                $db->query(
                    "UPDATE bed_assignments SET status = 'discharged', discharge_date = NOW() WHERE id = ?",
                    [$current_assignment['id']]
                );
                
                // Free up bed
                $db->query(
                    "UPDATE beds SET status = 'available' WHERE id = ?",
                    [$current_assignment['bed_id']]
                );
            }
        }
        
        // Log conversion activity
        $db->query(
            "INSERT INTO audit_logs (user_id, action, table_name, record_id, notes) 
             VALUES (?, ?, 'patients', ?, ?)",
            [$_SESSION['user_id'], 'patient_conversion', $patient_id, 
             "Converted from $old_type to $new_type. Reason: $conversion_reason"]
        );
        
        $db->getConnection()->commit();
        $message = "Patient successfully converted from " . ucfirst($old_type) . " to " . ucfirst($new_type);
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle bed assignment
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'assign_bed') {
    try {
        $patient_id = $_POST['patient_id'];
        $bed_id = $_POST['bed_id'];
        $notes = $_POST['notes'] ?? '';
        
        // Check if bed is available
        $bed_status = $db->query("SELECT status FROM beds WHERE id = ?", [$bed_id])->fetch()['status'];
        if ($bed_status !== 'available') {
            throw new Exception("Selected bed is not available");
        }
        
        // Check if patient already has an active bed assignment
        $existing_assignment = $db->query(
            "SELECT COUNT(*) as count FROM bed_assignments WHERE patient_id = ? AND status = 'active'",
            [$patient_id]
        )->fetch()['count'];
        
        if ($existing_assignment > 0) {
            throw new Exception("Patient already has an active bed assignment");
        }
        
        $db->getConnection()->beginTransaction();
        
        // Assign bed
        $db->query(
            "INSERT INTO bed_assignments (bed_id, patient_id, assigned_date, status, notes, assigned_by) 
             VALUES (?, ?, NOW(), 'active', ?, ?)",
            [$bed_id, $patient_id, $notes, $_SESSION['user_id']]
        );
        
        // Update bed status
        $db->query(
            "UPDATE beds SET status = 'occupied' WHERE id = ?",
            [$bed_id]
        );
        
        $db->getConnection()->commit();
        $message = "Bed assigned successfully!";
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle bed discharge
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'discharge_bed') {
    try {
        $assignment_id = $_POST['assignment_id'];
        
        $db->getConnection()->beginTransaction();
        
        // Get assignment details
        $assignment = $db->query(
            "SELECT * FROM bed_assignments WHERE id = ?",
            [$assignment_id]
        )->fetch();
        
        if (!$assignment) {
            throw new Exception("Bed assignment not found");
        }
        
        // Update bed assignment
        $db->query(
            "UPDATE bed_assignments SET status = 'discharged', discharge_date = NOW() WHERE id = ?",
            [$assignment_id]
        );
        
        // Free up bed
        $db->query(
            "UPDATE beds SET status = 'available' WHERE id = ?",
            [$assignment['bed_id']]
        );
        
        $db->getConnection()->commit();
        $message = "Patient discharged from bed successfully!";
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Get patients based on user role
$patients = [];
try {
    if ($user_role === 'admin') {
        $patients = $db->query(
            "SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as full_name,
                    d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                    ba.bed_id, ba.assigned_date as bed_assigned_date,
                    b.bed_number, b.bed_type
             FROM patients p 
             LEFT JOIN doctors d ON p.assigned_doctor_id = d.id 
             LEFT JOIN bed_assignments ba ON p.id = ba.patient_id AND ba.status = 'active'
             LEFT JOIN beds b ON ba.bed_id = b.id
             WHERE p.hospital_id = 1 
             ORDER BY p.patient_type DESC, p.first_name, p.last_name"
        )->fetchAll();
    } elseif ($user_role === 'doctor' || $user_role === 'intern_doctor') {
        $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
        $patients = $db->query(
            "SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as full_name,
                    ba.bed_id, ba.assigned_date as bed_assigned_date,
                    b.bed_number, b.bed_type
             FROM patients p 
             LEFT JOIN bed_assignments ba ON p.id = ba.patient_id AND ba.status = 'active'
             LEFT JOIN beds b ON ba.bed_id = b.id
             WHERE p.assigned_doctor_id = ? 
             ORDER BY p.patient_type DESC, p.first_name, p.last_name",
            [$doctor_id]
        )->fetchAll();
    } else {
        // Nurses and receptionists can see all patients
        $patients = $db->query(
            "SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as full_name,
                    d.first_name as doctor_first_name, d.last_name as doctor_last_name,
                    ba.bed_id, ba.assigned_date as bed_assigned_date,
                    b.bed_number, b.bed_type
             FROM patients p 
             LEFT JOIN doctors d ON p.assigned_doctor_id = d.id 
             LEFT JOIN bed_assignments ba ON p.id = ba.patient_id AND ba.status = 'active'
             LEFT JOIN beds b ON ba.bed_id = b.id
             WHERE p.hospital_id = 1 
             ORDER BY p.patient_type DESC, p.first_name, p.last_name"
        )->fetchAll();
    }
} catch (Exception $e) {
    $patients = [];
}

// Get available beds
$available_beds = [];
try {
    $available_beds = $db->query(
        "SELECT * FROM beds WHERE status = 'available' ORDER BY bed_number"
    )->fetchAll();
} catch (Exception $e) {
    $available_beds = [];
}

// Get bed assignments
$bed_assignments = [];
try {
    $bed_assignments = $db->query(
        "SELECT ba.*, p.first_name, p.last_name, p.patient_id as patient_number,
                b.bed_number, b.bed_type, b.daily_rate,
                CONCAT(d.first_name, ' ', d.last_name) as doctor_name
         FROM bed_assignments ba
         JOIN patients p ON ba.patient_id = p.id
         JOIN beds b ON ba.bed_id = b.id
         LEFT JOIN doctors d ON p.assigned_doctor_id = d.id
         WHERE ba.status = 'active'
         ORDER BY ba.assigned_date DESC"
    )->fetchAll();
} catch (Exception $e) {
    $bed_assignments = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Conversion - Hospital CRM</title>
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
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Patient Conversion Management</h1>
                    <p>Convert patients between inpatient and outpatient status</p>
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

            <!-- Patient Conversion Form -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Convert Patient Type</h3>
                </div>
                <form method="POST" class="conversion-form">
                    <input type="hidden" name="action" value="convert_patient">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Select Patient</label>
                            <select name="patient_id" class="form-control" required>
                                <option value="">Select a patient...</option>
                                <?php foreach ($patients as $patient): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['full_name'] . ' (ID: ' . $patient['patient_id'] . ') - ' . ucfirst($patient['patient_type'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Convert To</label>
                            <select name="new_type" class="form-control" required onchange="toggleBedSelection(this.value)">
                                <option value="">Select type...</option>
                                <option value="outpatient">Outpatient</option>
                                <option value="inpatient">Inpatient</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group" id="bedSelection" style="display: none;">
                        <label class="form-label">Assign Bed (for inpatient)</label>
                        <select name="bed_id" class="form-control">
                            <option value="">Select a bed...</option>
                            <?php foreach ($available_beds as $bed): ?>
                                <option value="<?php echo $bed['id']; ?>">
                                    <?php echo htmlspecialchars($bed['bed_number'] . ' (' . ucfirst($bed['bed_type']) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Conversion Reason</label>
                        <textarea name="conversion_reason" class="form-control" rows="3" 
                                  placeholder="Reason for conversion..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-exchange-alt"></i> Convert Patient
                    </button>
                </form>
            </div>

            <!-- Bed Management -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Bed Assignments</h3>
                    <button class="btn btn-primary" onclick="showAssignBedModal()">
                        <i class="fas fa-plus"></i> Assign Bed
                    </button>
                </div>
                
                <?php if (!empty($bed_assignments)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Bed</th>
                                    <th>Assigned Date</th>
                                    <th>Days Occupied</th>
                                    <th>Daily Rate</th>
                                    <th>Assigned Doctor</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bed_assignments as $assignment): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></strong><br>
                                            <small>ID: <?php echo htmlspecialchars($assignment['patient_number']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <?php echo htmlspecialchars($assignment['bed_number']); ?>
                                            </span><br>
                                            <small><?php echo ucfirst($assignment['bed_type']); ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($assignment['assigned_date'])); ?></td>
                                        <td>
                                            <?php 
                                            $days = (strtotime(date('Y-m-d')) - strtotime($assignment['assigned_date'])) / (24 * 60 * 60);
                                            echo max(1, $days) . ' days';
                                            ?>
                                        </td>
                                        <td>₹<?php echo number_format($assignment['daily_rate'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['doctor_name'] ?? 'Not Assigned'); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="discharge_bed">
                                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-warning" 
                                                        onclick="return confirm('Are you sure you want to discharge this patient?')">
                                                    <i class="fas fa-sign-out-alt"></i> Discharge
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">No active bed assignments.</p>
                <?php endif; ?>
            </div>

            <!-- Patient List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Patient Status Overview</h3>
                </div>
                
                <div class="patient-status-grid">
                    <div class="status-section">
                        <h4><i class="fas fa-user-injured"></i> Inpatients</h4>
                        <div class="patient-list">
                            <?php 
                            $inpatients = array_filter($patients, fn($p) => $p['patient_type'] === 'inpatient');
                            foreach ($inpatients as $patient): 
                            ?>
                                <div class="patient-item">
                                    <div class="patient-info">
                                        <strong><?php echo htmlspecialchars($patient['full_name']); ?></strong>
                                        <small>ID: <?php echo htmlspecialchars($patient['patient_id']); ?></small>
                                    </div>
                                    <div class="patient-status">
                                        <?php if ($patient['bed_id']): ?>
                                            <span class="badge badge-success">Bed <?php echo htmlspecialchars($patient['bed_number']); ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">No Bed Assigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($inpatients)): ?>
                                <p class="text-muted">No inpatients currently.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="status-section">
                        <h4><i class="fas fa-user"></i> Outpatients</h4>
                        <div class="patient-list">
                            <?php 
                            $outpatients = array_filter($patients, fn($p) => $p['patient_type'] === 'outpatient');
                            foreach ($outpatients as $patient): 
                            ?>
                                <div class="patient-item">
                                    <div class="patient-info">
                                        <strong><?php echo htmlspecialchars($patient['full_name']); ?></strong>
                                        <small>ID: <?php echo htmlspecialchars($patient['patient_id']); ?></small>
                                    </div>
                                    <div class="patient-status">
                                        <span class="badge badge-info">Outpatient</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($outpatients)): ?>
                                <p class="text-muted">No outpatients currently.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Assign Bed Modal -->
    <div id="assignBedModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Assign Bed</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" class="assign-bed-form">
                    <input type="hidden" name="action" value="assign_bed">
                    
                    <div class="form-group">
                        <label class="form-label">Select Patient</label>
                        <select name="patient_id" class="form-control" required>
                            <option value="">Select a patient...</option>
                            <?php foreach ($patients as $patient): ?>
                                <?php if ($patient['patient_type'] === 'inpatient' && !$patient['bed_id']): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['full_name'] . ' (ID: ' . $patient['patient_id'] . ')'); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Select Bed</label>
                        <select name="bed_id" class="form-control" required>
                            <option value="">Select a bed...</option>
                            <?php foreach ($available_beds as $bed): ?>
                                <option value="<?php echo $bed['id']; ?>">
                                    <?php echo htmlspecialchars($bed['bed_number'] . ' (' . ucfirst($bed['bed_type']) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Additional notes..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Bed</button>
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

        // Toggle bed selection
        function toggleBedSelection(patientType) {
            const bedSelection = document.getElementById('bedSelection');
            if (patientType === 'inpatient') {
                bedSelection.style.display = 'block';
            } else {
                bedSelection.style.display = 'none';
            }
        }

        // Modal functions
        function showAssignBedModal() {
            document.getElementById('assignBedModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('assignBedModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('assignBedModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>

    <style>
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .patient-status-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .status-section h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .patient-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .patient-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--border-radius);
            margin-bottom: 0.5rem;
            background: var(--bg-secondary);
        }

        .patient-info {
            display: flex;
            flex-direction: column;
        }

        .patient-info small {
            color: var(--text-secondary);
            font-size: 0.75rem;
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

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .patient-status-grid {
                grid-template-columns: 1fr;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
</body>
</html>
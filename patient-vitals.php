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
$allowed_roles = ['admin', 'doctor', 'nurse', 'intern_doctor', 'intern_nurse'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php');
    exit;
}

// Handle vitals submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_vitals') {
    try {
        $patient_id = $_POST['patient_id'];
        $recorded_by = $_SESSION['user_id'];
        $recorded_by_type = in_array($user_role, ['doctor', 'intern_doctor']) ? 'doctor' : 'nurse';
        
        // Validate access
        if ($user_role === 'doctor' || $user_role === 'intern_doctor') {
            $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
            $can_access = $db->query(
                "SELECT COUNT(*) as count FROM patients WHERE id = ? AND assigned_doctor_id = ?",
                [$patient_id, $doctor_id]
            )->fetch()['count'];
            
            if (!$can_access) {
                throw new Exception("You can only record vitals for your assigned patients");
            }
        }
        
        $vitals_sql = "INSERT INTO patient_vitals (patient_id, recorded_by, recorded_by_type, 
                                                  temperature, blood_pressure, heart_rate, respiratory_rate, 
                                                  weight, height, bmi, oxygen_saturation, notes) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($vitals_sql, [
            $patient_id,
            $recorded_by,
            $recorded_by_type,
            $_POST['temperature'] ?: null,
            $_POST['blood_pressure'] ?: null,
            $_POST['heart_rate'] ?: null,
            $_POST['respiratory_rate'] ?: null,
            $_POST['weight'] ?: null,
            $_POST['height'] ?: null,
            $_POST['bmi'] ?: null,
            $_POST['oxygen_saturation'] ?: null,
            $_POST['notes'] ?: null
        ]);
        
        $message = "Vitals recorded successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get patients based on user role
$patients = [];
try {
    if ($user_role === 'admin') {
        $patients = $db->query(
            "SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as full_name,
                    d.first_name as doctor_first_name, d.last_name as doctor_last_name
             FROM patients p 
             LEFT JOIN doctors d ON p.assigned_doctor_id = d.id 
             WHERE p.hospital_id = 1 
             ORDER BY p.first_name, p.last_name"
        )->fetchAll();
    } elseif ($user_role === 'doctor' || $user_role === 'intern_doctor') {
        $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
        $patients = $db->query(
            "SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as full_name
             FROM patients p 
             WHERE p.assigned_doctor_id = ? 
             ORDER BY p.first_name, p.last_name",
            [$doctor_id]
        )->fetchAll();
    } elseif ($user_role === 'nurse' || $user_role === 'intern_nurse') {
        // Nurses can see all patients but only record vitals for assigned ones
        $patients = $db->query(
            "SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as full_name,
                    d.first_name as doctor_first_name, d.last_name as doctor_last_name
             FROM patients p 
             LEFT JOIN doctors d ON p.assigned_doctor_id = d.id 
             WHERE p.hospital_id = 1 
             ORDER BY p.first_name, p.last_name"
        )->fetchAll();
    }
} catch (Exception $e) {
    $patients = [];
}

// Get vitals for selected patient
$selected_patient_id = $_GET['patient_id'] ?? null;
$vitals_history = [];
if ($selected_patient_id) {
    try {
        $vitals_history = $db->query(
            "SELECT pv.*, 
                    CASE 
                        WHEN pv.recorded_by_type = 'doctor' THEN CONCAT(d.first_name, ' ', d.last_name)
                        WHEN pv.recorded_by_type = 'nurse' THEN CONCAT(s.first_name, ' ', s.last_name)
                        ELSE 'Unknown'
                    END as recorded_by_name
             FROM patient_vitals pv
             LEFT JOIN doctors d ON pv.recorded_by = d.user_id AND pv.recorded_by_type = 'doctor'
             LEFT JOIN staff s ON pv.recorded_by = s.user_id AND pv.recorded_by_type = 'nurse'
             WHERE pv.patient_id = ?
             ORDER BY pv.recorded_at DESC",
            [$selected_patient_id]
        )->fetchAll();
    } catch (Exception $e) {
        $vitals_history = [];
    }
}

// Get patient details for selected patient
$selected_patient = null;
if ($selected_patient_id) {
    try {
        $selected_patient = $db->query(
            "SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) as full_name,
                    d.first_name as doctor_first_name, d.last_name as doctor_last_name
             FROM patients p 
             LEFT JOIN doctors d ON p.assigned_doctor_id = d.id 
             WHERE p.id = ?",
            [$selected_patient_id]
        )->fetch();
    } catch (Exception $e) {
        $selected_patient = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Vitals - Hospital CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <h1>Patient Vitals Management</h1>
                    <p>Record and track patient vital signs</p>
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

            <!-- Patient Selection -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Select Patient</h3>
                </div>
                <div class="form-group">
                    <select id="patientSelect" class="form-control" onchange="loadPatientVitals(this.value)">
                        <option value="">Select a patient...</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" 
                                    <?php echo $selected_patient_id == $patient['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['full_name'] . ' (ID: ' . $patient['patient_id'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($selected_patient): ?>
                <!-- Patient Info -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Patient Information</h3>
                    </div>
                    <div class="patient-info-grid">
                        <div class="info-item">
                            <label>Name:</label>
                            <span><?php echo htmlspecialchars($selected_patient['full_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Patient ID:</label>
                            <span><?php echo htmlspecialchars($selected_patient['patient_id']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Age:</label>
                            <span><?php echo $selected_patient['date_of_birth'] ? date_diff(date_create($selected_patient['date_of_birth']), date_create('today'))->y . ' years' : 'N/A'; ?></span>
                        </div>
                        <div class="info-item">
                            <label>Gender:</label>
                            <span><?php echo ucfirst($selected_patient['gender'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Blood Group:</label>
                            <span><?php echo htmlspecialchars($selected_patient['blood_group'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Assigned Doctor:</label>
                            <span><?php echo $selected_patient['doctor_first_name'] ? htmlspecialchars($selected_patient['doctor_first_name'] . ' ' . $selected_patient['doctor_last_name']) : 'Not Assigned'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Add Vitals Form -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Record New Vitals</h3>
                    </div>
                    <form method="POST" class="vitals-form">
                        <input type="hidden" name="action" value="add_vitals">
                        <input type="hidden" name="patient_id" value="<?php echo $selected_patient['id']; ?>">
                        
                        <div class="vitals-grid">
                            <div class="form-group">
                                <label class="form-label">Temperature (°C)</label>
                                <input type="number" name="temperature" class="form-control" step="0.1" min="30" max="45" 
                                       placeholder="e.g., 37.2">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Blood Pressure (mmHg)</label>
                                <input type="text" name="blood_pressure" class="form-control" 
                                       placeholder="e.g., 120/80">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Heart Rate (bpm)</label>
                                <input type="number" name="heart_rate" class="form-control" min="40" max="200" 
                                       placeholder="e.g., 72">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Respiratory Rate (breaths/min)</label>
                                <input type="number" name="respiratory_rate" class="form-control" min="8" max="40" 
                                       placeholder="e.g., 16">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" name="weight" class="form-control" step="0.1" min="0" max="500" 
                                       placeholder="e.g., 70.5">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Height (cm)</label>
                                <input type="number" name="height" class="form-control" step="0.1" min="50" max="300" 
                                       placeholder="e.g., 170">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">BMI</label>
                                <input type="number" name="bmi" class="form-control" step="0.1" min="10" max="100" 
                                       placeholder="e.g., 24.5">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Oxygen Saturation (%)</label>
                                <input type="number" name="oxygen_saturation" class="form-control" min="70" max="100" 
                                       placeholder="e.g., 98">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Additional observations or notes..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Record Vitals
                        </button>
                    </form>
                </div>

                <!-- Vitals History -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Vitals History</h3>
                    </div>
                    
                    <?php if (!empty($vitals_history)): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Temperature</th>
                                        <th>Blood Pressure</th>
                                        <th>Heart Rate</th>
                                        <th>Respiratory Rate</th>
                                        <th>Weight</th>
                                        <th>Height</th>
                                        <th>BMI</th>
                                        <th>O2 Sat</th>
                                        <th>Recorded By</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vitals_history as $vital): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i', strtotime($vital['recorded_at'])); ?></td>
                                            <td>
                                                <?php if ($vital['temperature']): ?>
                                                    <span class="<?php echo getVitalStatusClass('temperature', $vital['temperature']); ?>">
                                                        <?php echo $vital['temperature']; ?>°C
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($vital['blood_pressure'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($vital['heart_rate']): ?>
                                                    <span class="<?php echo getVitalStatusClass('heart_rate', $vital['heart_rate']); ?>">
                                                        <?php echo $vital['heart_rate']; ?> bpm
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $vital['respiratory_rate'] ? $vital['respiratory_rate'] . ' bpm' : '-'; ?></td>
                                            <td><?php echo $vital['weight'] ? $vital['weight'] . ' kg' : '-'; ?></td>
                                            <td><?php echo $vital['height'] ? $vital['height'] . ' cm' : '-'; ?></td>
                                            <td>
                                                <?php if ($vital['bmi']): ?>
                                                    <span class="<?php echo getVitalStatusClass('bmi', $vital['bmi']); ?>">
                                                        <?php echo $vital['bmi']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($vital['oxygen_saturation']): ?>
                                                    <span class="<?php echo getVitalStatusClass('oxygen_saturation', $vital['oxygen_saturation']); ?>">
                                                        <?php echo $vital['oxygen_saturation']; ?>%
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $vital['recorded_by_type'] === 'doctor' ? 'primary' : 'info'; ?>">
                                                    <?php echo htmlspecialchars($vital['recorded_by_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($vital['notes']): ?>
                                                    <button class="btn btn-sm btn-secondary" onclick="showNotes('<?php echo htmlspecialchars($vital['notes']); ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">No vitals recorded yet for this patient.</p>
                    <?php endif; ?>
                </div>

                <!-- Vitals Chart -->
                <?php if (!empty($vitals_history)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Vitals Trends</h3>
                        <select id="vitalType" class="form-control" style="width: auto;" onchange="updateChart()">
                            <option value="temperature">Temperature</option>
                            <option value="heart_rate">Heart Rate</option>
                            <option value="blood_pressure">Blood Pressure</option>
                            <option value="oxygen_saturation">Oxygen Saturation</option>
                        </select>
                    </div>
                    <canvas id="vitalsChart" height="100"></canvas>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" style="display: none;">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Notes Modal -->
    <div id="notesModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Notes</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p id="notesText"></p>
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

        // Load patient vitals
        function loadPatientVitals(patientId) {
            if (patientId) {
                window.location.href = 'patient-vitals.php?patient_id=' + patientId;
            }
        }

        // Show notes modal
        function showNotes(notes) {
            document.getElementById('notesText').textContent = notes;
            document.getElementById('notesModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('notesModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('notesModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }

        // Auto-calculate BMI
        document.querySelectorAll('input[name="weight"], input[name="height"]').forEach(input => {
            input.addEventListener('input', calculateBMI);
        });

        function calculateBMI() {
            const weight = parseFloat(document.querySelector('input[name="weight"]').value);
            const height = parseFloat(document.querySelector('input[name="height"]').value);
            
            if (weight && height) {
                const heightInMeters = height / 100;
                const bmi = weight / (heightInMeters * heightInMeters);
                document.querySelector('input[name="bmi"]').value = bmi.toFixed(1);
            }
        }

        // Vitals Chart
        <?php if (!empty($vitals_history)): ?>
        const vitalsData = <?php echo json_encode($vitals_history); ?>;
        let vitalsChart;

        function initChart() {
            const ctx = document.getElementById('vitalsChart').getContext('2d');
            vitalsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: vitalsData.map(v => new Date(v.recorded_at).toLocaleDateString()),
                    datasets: [{
                        label: 'Temperature (°C)',
                        data: vitalsData.map(v => v.temperature).filter(v => v !== null),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            });
        }

        function updateChart() {
            const vitalType = document.getElementById('vitalType').value;
            const labels = {
                'temperature': 'Temperature (°C)',
                'heart_rate': 'Heart Rate (bpm)',
                'blood_pressure': 'Blood Pressure (mmHg)',
                'oxygen_saturation': 'Oxygen Saturation (%)'
            };
            
            const colors = {
                'temperature': '#dc3545',
                'heart_rate': '#28a745',
                'blood_pressure': '#007bff',
                'oxygen_saturation': '#ffc107'
            };

            let data = [];
            if (vitalType === 'blood_pressure') {
                data = vitalsData.map(v => v.blood_pressure ? v.blood_pressure.split('/')[0] : null).filter(v => v !== null);
            } else {
                data = vitalsData.map(v => v[vitalType]).filter(v => v !== null);
            }

            vitalsChart.data.labels = vitalsData.map(v => new Date(v.recorded_at).toLocaleDateString());
            vitalsChart.data.datasets[0].label = labels[vitalType];
            vitalsChart.data.datasets[0].data = data;
            vitalsChart.data.datasets[0].borderColor = colors[vitalType];
            vitalsChart.data.datasets[0].backgroundColor = colors[vitalType].replace(')', ', 0.1)');
            vitalsChart.update();
        }

        // Initialize chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('vitalsChart')) {
                initChart();
            }
        });
        <?php endif; ?>
    </script>

    <style>
        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-item label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .info-item span {
            color: var(--text-primary);
            font-size: 1rem;
        }

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .vital-normal { color: var(--success-color); }
        .vital-warning { color: var(--warning-color); }
        .vital-danger { color: var(--danger-color); }

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
            margin: 15% auto;
            padding: 0;
            border-radius: var(--border-radius-lg);
            width: 80%;
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
            .vitals-grid {
                grid-template-columns: 1fr;
            }
            
            .patient-info-grid {
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
// Helper function to get vital status class
function getVitalStatusClass($vital_type, $value) {
    switch ($vital_type) {
        case 'temperature':
            if ($value < 35 || $value > 38) return 'vital-danger';
            if ($value < 36 || $value > 37.5) return 'vital-warning';
            return 'vital-normal';
            
        case 'heart_rate':
            if ($value < 60 || $value > 100) return 'vital-danger';
            if ($value < 70 || $value > 90) return 'vital-warning';
            return 'vital-normal';
            
        case 'bmi':
            if ($value < 18.5 || $value > 30) return 'vital-danger';
            if ($value < 20 || $value > 25) return 'vital-warning';
            return 'vital-normal';
            
        case 'oxygen_saturation':
            if ($value < 95) return 'vital-danger';
            if ($value < 97) return 'vital-warning';
            return 'vital-normal';
            
        default:
            return '';
    }
}
?>
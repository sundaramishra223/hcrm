<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$db = new Database();
$user_role = $_SESSION['role'];
$message = '';

// Get user profile data
$user_profile = null;
$role_specific_data = null;

try {
    // Get basic user info
    $user_profile = $db->query(
        "SELECT u.*, r.role_name, r.role_display_name 
         FROM users u 
         JOIN roles r ON u.role_id = r.id 
         WHERE u.id = ?", 
        [$_SESSION['user_id']]
    )->fetch();
    
    // Get role-specific data
    if ($user_role === 'doctor' || $user_role === 'intern_doctor') {
        $role_specific_data = $db->query(
            "SELECT d.*, dept.name as department_name,
                    CONCAT(sd.first_name, ' ', sd.last_name) as senior_doctor_name
             FROM doctors d
             LEFT JOIN departments dept ON d.department_id = dept.id
             LEFT JOIN doctors sd ON d.senior_doctor_id = sd.id
             WHERE d.user_id = ?",
            [$_SESSION['user_id']]
        )->fetch();
    } elseif ($user_role === 'patient') {
        $role_specific_data = $db->query(
            "SELECT p.*, CONCAT(d.first_name, ' ', d.last_name) as assigned_doctor_name
             FROM patients p
             LEFT JOIN doctors d ON p.assigned_doctor_id = d.id
             WHERE p.user_id = ?",
            [$_SESSION['user_id']]
        )->fetch();
    } else {
        // Staff members
        $role_specific_data = $db->query(
            "SELECT s.*, dept.name as department_name,
                    CONCAT(ss.first_name, ' ', ss.last_name) as senior_staff_name
             FROM staff s
             LEFT JOIN departments dept ON s.department_id = dept.id
             LEFT JOIN staff ss ON s.senior_staff_id = ss.id
             WHERE s.user_id = ?",
            [$_SESSION['user_id']]
        )->fetch();
    }
} catch (Exception $e) {
    $message = "Error loading profile: " . $e->getMessage();
}

// Handle profile update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        $db->getConnection()->beginTransaction();
        
        // Update basic user info
        $db->query(
            "UPDATE users SET username = ?, email = ? WHERE id = ?",
            [$_POST['username'], $_POST['email'], $_SESSION['user_id']]
        );
        
        // Update role-specific data
        if ($user_role === 'doctor' || $user_role === 'intern_doctor') {
            $db->query(
                "UPDATE doctors SET 
                    first_name = ?, last_name = ?, phone = ?, emergency_contact = ?, 
                    address = ?, date_of_birth = ?, gender = ?, blood_group = ?
                 WHERE user_id = ?",
                [
                    $_POST['first_name'], $_POST['last_name'], $_POST['phone'],
                    $_POST['emergency_contact'], $_POST['address'], $_POST['date_of_birth'],
                    $_POST['gender'], $_POST['blood_group'], $_SESSION['user_id']
                ]
            );
        } elseif ($user_role === 'patient') {
            $db->query(
                "UPDATE patients SET 
                    first_name = ?, last_name = ?, phone = ?, emergency_contact = ?, 
                    email = ?, address = ?, date_of_birth = ?, gender = ?, 
                    blood_group = ?, marital_status = ?, occupation = ?
                 WHERE user_id = ?",
                [
                    $_POST['first_name'], $_POST['last_name'], $_POST['phone'],
                    $_POST['emergency_contact'], $_POST['email'], $_POST['address'],
                    $_POST['date_of_birth'], $_POST['gender'], $_POST['blood_group'],
                    $_POST['marital_status'], $_POST['occupation'], $_SESSION['user_id']
                ]
            );
        } else {
            // Staff members
            $db->query(
                "UPDATE staff SET 
                    first_name = ?, last_name = ?, phone = ?, emergency_contact = ?, 
                    address = ?, date_of_birth = ?, gender = ?, blood_group = ?
                 WHERE user_id = ?",
                [
                    $_POST['first_name'], $_POST['last_name'], $_POST['phone'],
                    $_POST['emergency_contact'], $_POST['address'], $_POST['date_of_birth'],
                    $_POST['gender'], $_POST['blood_group'], $_SESSION['user_id']
                ]
            );
        }
        
        $db->getConnection()->commit();
        $message = "Profile updated successfully!";
        
        // Refresh session data
        $_SESSION['username'] = $_POST['username'];
        $_SESSION['email'] = $_POST['email'];
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

// Handle password change
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $current_hash = $db->query("SELECT password_hash FROM users WHERE id = ?", [$_SESSION['user_id']])->fetch()['password_hash'];
        
        if (!password_verify($current_password, $current_hash)) {
            throw new Exception("Current password is incorrect");
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match");
        }
        
        if (strlen($new_password) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }
        
        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $db->query("UPDATE users SET password_hash = ? WHERE id = ?", [$new_hash, $_SESSION['user_id']]);
        
        $message = "Password changed successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Get user statistics based on role
$user_stats = [];
try {
    if ($user_role === 'doctor' || $user_role === 'intern_doctor') {
        $doctor_id = $db->query("SELECT id FROM doctors WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
        $user_stats['total_patients'] = $db->query("SELECT COUNT(*) as count FROM patients WHERE assigned_doctor_id = ?", [$doctor_id])->fetch()['count'];
        $user_stats['today_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()", [$doctor_id])->fetch()['count'];
        $user_stats['total_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?", [$doctor_id])->fetch()['count'];
    } elseif ($user_role === 'patient') {
        $patient_id = $db->query("SELECT id FROM patients WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
        $user_stats['total_appointments'] = $db->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?", [$patient_id])->fetch()['count'];
        $user_stats['total_prescriptions'] = $db->query("SELECT COUNT(*) as count FROM prescriptions WHERE patient_id = ?", [$patient_id])->fetch()['count'];
        $user_stats['total_bills'] = $db->query("SELECT COUNT(*) as count FROM bills WHERE patient_id = ?", [$patient_id])->fetch()['count'];
    } elseif ($user_role === 'nurse' || $user_role === 'intern_nurse') {
        $staff_id = $db->query("SELECT id FROM staff WHERE user_id = ?", [$_SESSION['user_id']])->fetch()['id'];
        $user_stats['assigned_patients'] = $db->query("SELECT COUNT(*) as count FROM patient_visits WHERE assigned_nurse_id = ? AND visit_date = CURDATE()", [$staff_id])->fetch()['count'];
        $user_stats['today_vitals'] = $db->query("SELECT COUNT(*) as count FROM patient_vitals WHERE recorded_by = ? AND DATE(recorded_at) = CURDATE()", [$_SESSION['user_id']])->fetch()['count'];
    }
} catch (Exception $e) {
    $user_stats = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Hospital CRM</title>
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
                
                <?php if (in_array($user_role, ['admin', 'receptionist'])): ?>
                    <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'intern_doctor'])): ?>
                    <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'receptionist', 'intern_doctor'])): ?>
                    <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'receptionist', 'pharmacy_staff', 'intern_pharmacy'])): ?>
                    <li><a href="billing.php"><i class="fas fa-money-bill-wave"></i> Billing</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'pharmacy_staff', 'intern_pharmacy'])): ?>
                    <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'lab_technician', 'intern_lab'])): ?>
                    <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin', 'nurse', 'intern_nurse'])): ?>
                    <li><a href="patient-vitals.php"><i class="fas fa-heartbeat"></i> Patient Vitals</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin'])): ?>
                    <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                    <li><a href="staff.php"><i class="fas fa-user-nurse"></i> Staff</a></li>
                    <li><a href="attendance.php"><i class="fas fa-clock"></i> Attendance</a></li>
                    <li><a href="intern-management.php"><i class="fas fa-graduation-cap"></i> Intern Management</a></li>
                <?php endif; ?>
                
                <li><a href="profile.php" class="active"><i class="fas fa-user"></i> My Profile</a></li>
                
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse', 'lab_technician', 'pharmacy_staff'])): ?>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['admin'])): ?>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <?php endif; ?>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>My Profile</h1>
                    <p>Manage your account information and preferences</p>
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

            <!-- Profile Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($user_profile['username'] ?? 'N/A'); ?></h3>
                    <p>Username</p>
                    <i class="fas fa-user stat-icon"></i>
                </div>
                <div class="stat-card">
                    <div class="role-display">
                        <span class="role-badge role-<?php echo strtolower(str_replace(' ', '-', $user_profile['role_display_name'] ?? 'unknown')); ?>">
                            <i class="fas fa-id-badge"></i>
                            <?php echo htmlspecialchars($user_profile['role_display_name'] ?? 'N/A'); ?>
                        </span>
                    </div>
                    <p>Role</p>
                    <i class="fas fa-id-badge stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($user_profile['email'] ?? 'N/A'); ?></h3>
                    <p>Email</p>
                    <i class="fas fa-envelope stat-icon"></i>
                </div>
                <div class="stat-card">
                    <h3><?php echo $user_profile['last_login'] ? date('M d, Y', strtotime($user_profile['last_login'])) : 'Never'; ?></h3>
                    <p>Last Login</p>
                    <i class="fas fa-clock stat-icon"></i>
                </div>
            </div>

            <!-- User Statistics -->
            <?php if (!empty($user_stats)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">My Statistics</h3>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <?php foreach ($user_stats as $key => $value): ?>
                                <div class="stat-card">
                                    <h3><?php echo number_format($value); ?></h3>
                                    <p><?php echo ucwords(str_replace('_', ' ', $key)); ?></p>
                                    <i class="fas fa-chart-bar stat-icon"></i>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Profile Information -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Profile Information</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="profile-form">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_profile['username'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user_profile['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($role_specific_data['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($role_specific_data['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($role_specific_data['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Emergency Contact</label>
                                <input type="tel" name="emergency_contact" class="form-control" 
                                       value="<?php echo htmlspecialchars($role_specific_data['emergency_contact'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control" 
                                       value="<?php echo htmlspecialchars($role_specific_data['date_of_birth'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-control">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($role_specific_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($role_specific_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($role_specific_data['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Blood Group</label>
                                <select name="blood_group" class="form-control">
                                    <option value="">Select Blood Group</option>
                                    <option value="A+" <?php echo ($role_specific_data['blood_group'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo ($role_specific_data['blood_group'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo ($role_specific_data['blood_group'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo ($role_specific_data['blood_group'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                    <option value="AB+" <?php echo ($role_specific_data['blood_group'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo ($role_specific_data['blood_group'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                    <option value="O+" <?php echo ($role_specific_data['blood_group'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo ($role_specific_data['blood_group'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                                </select>
                            </div>
                            <?php if ($user_role === 'patient'): ?>
                                <div class="form-group">
                                    <label class="form-label">Marital Status</label>
                                    <select name="marital_status" class="form-control">
                                        <option value="">Select Status</option>
                                        <option value="single" <?php echo ($role_specific_data['marital_status'] ?? '') === 'single' ? 'selected' : ''; ?>>Single</option>
                                        <option value="married" <?php echo ($role_specific_data['marital_status'] ?? '') === 'married' ? 'selected' : ''; ?>>Married</option>
                                        <option value="divorced" <?php echo ($role_specific_data['marital_status'] ?? '') === 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                        <option value="widowed" <?php echo ($role_specific_data['marital_status'] ?? '') === 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($user_role === 'patient'): ?>
                            <div class="form-group">
                                <label class="form-label">Occupation</label>
                                <input type="text" name="occupation" class="form-control" 
                                       value="<?php echo htmlspecialchars($role_specific_data['occupation'] ?? ''); ?>">
                            </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($role_specific_data['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Change Password</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="password-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Role-Specific Information -->
            <?php if ($role_specific_data): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Role Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <?php if ($user_role === 'doctor' || $user_role === 'intern_doctor'): ?>
                                <div class="info-item">
                                    <label>Employee ID:</label>
                                    <span><?php echo htmlspecialchars($role_specific_data['employee_id'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Specialization:</label>
                                    <span><?php echo htmlspecialchars($role_specific_data['specialization'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Department:</label>
                                    <span><?php echo htmlspecialchars($role_specific_data['department_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Consultation Fee:</label>
                                    <span>₹<?php echo number_format($role_specific_data['consultation_fee'] ?? 0, 2); ?></span>
                                </div>
                                <?php if ($role_specific_data['senior_doctor_name']): ?>
                                    <div class="info-item">
                                        <label>Senior Doctor:</label>
                                        <span><?php echo htmlspecialchars($role_specific_data['senior_doctor_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($user_role === 'patient'): ?>
                                <div class="info-item">
                                    <label>Patient ID:</label>
                                    <span><?php echo htmlspecialchars($role_specific_data['patient_id'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Patient Type:</label>
                                    <span><?php echo ucfirst($role_specific_data['patient_type'] ?? 'outpatient'); ?></span>
                                </div>
                                <?php if ($role_specific_data['assigned_doctor_name']): ?>
                                    <div class="info-item">
                                        <label>Assigned Doctor:</label>
                                        <span><?php echo htmlspecialchars($role_specific_data['assigned_doctor_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="info-item">
                                    <label>Employee ID:</label>
                                    <span><?php echo htmlspecialchars($role_specific_data['employee_id'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Staff Type:</label>
                                    <span><?php echo ucwords(str_replace('_', ' ', $role_specific_data['staff_type'] ?? 'N/A')); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Department:</label>
                                    <span><?php echo htmlspecialchars($role_specific_data['department_name'] ?? 'N/A'); ?></span>
                                </div>
                                <?php if ($role_specific_data['senior_staff_name']): ?>
                                    <div class="info-item">
                                        <label>Senior Staff:</label>
                                        <span><?php echo htmlspecialchars($role_specific_data['senior_staff_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <style>
        .profile-form, .password-form {
            max-width: 800px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .info-item label {
            font-weight: 600;
            color: #333;
        }
        
        .info-item span {
            color: #666;
        }

        /* Professional Role Badge Styles */
        .role-display {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 10px 0;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .role-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .role-badge i {
            font-size: 16px;
        }

        /* Role-specific colors */
        .role-badge.role-administrator {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .role-badge.role-doctor {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .role-badge.role-intern-doctor {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #8b4513;
        }

        .role-badge.role-nurse {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #2c5530;
        }

        .role-badge.role-intern-nurse {
            background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%);
            color: #4a4a4a;
        }

        .role-badge.role-patient {
            background: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%);
            color: white;
        }

        .role-badge.role-receptionist {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .role-badge.role-lab-technician {
            background: linear-gradient(135deg, #a8caba 0%, #5d4e75 100%);
            color: white;
        }

        .role-badge.role-intern-lab {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #4a4a4a;
        }

        .role-badge.role-pharmacy-staff {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #8b4513;
        }

        .role-badge.role-intern-pharmacy {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #4a4a4a;
        }

        .role-badge.role-unknown {
            background: linear-gradient(135deg, #bdc3c7 0%, #2c3e50 100%);
            color: white;
        }

        /* Enhanced stat card for role display */
        .stat-card .role-display {
            margin: 0;
        }

        .stat-card .role-badge {
            font-size: 12px;
            padding: 6px 12px;
        }

        .stat-card .role-badge i {
            font-size: 14px;
        }
    </style>
</body>
</html>
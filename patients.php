<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/upload-handler.php';

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_role = $_SESSION['role'];
if (!in_array($user_role, ['admin', 'receptionist'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';
$error_message = '';

// Handle form submission for new patient
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_patient') {
    try {
        // Validate required fields
        if (empty($_POST['email']) || empty($_POST['password'])) {
            $error_message = "Email and password are required for patient registration!";
        }
        
        // Validate password strength using JavaScript validation pattern
        if (!$error_message && strlen($_POST['password']) < 6) {
            $error_message = "Password must be at least 6 characters long!";
        }
        
        if (!$error_message) {
            // Check if email already exists
            $existing_user = $db->query("SELECT id FROM users WHERE email = ?", [$_POST['email']])->fetch();
            if ($existing_user) {
                $error_message = "Email already exists! Please use a different email.";
            } else {
                // Generate patient ID
                $patient_count = $db->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'];
                $patient_id = 'PAT' . str_pad($patient_count + 1, 4, '0', STR_PAD_LEFT);
                
                // Handle photo upload
                $photo_filename = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                    $upload_result = uploadPatientPhoto($_FILES['photo'], $patient_id);
                    if ($upload_result['success']) {
                        $photo_filename = $upload_result['filename'];
                    } else {
                        $error_message = "Photo upload failed: " . $upload_result['message'];
                    }
                }
                
                if (!$error_message) {
                    // Begin transaction
                    $db->beginTransaction();
                    
                    try {
                        // Insert patient
                        $db->query(
                            "INSERT INTO patients (patient_id, first_name, last_name, email, phone, address, date_of_birth, gender, blood_group, emergency_contact_name, emergency_contact_phone, medical_history, allergies, photo, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $patient_id,
                                $_POST['first_name'],
                                $_POST['last_name'],
                                $_POST['email'],
                                $_POST['phone'] ?? '',
                                $_POST['address'] ?? '',
                                $_POST['date_of_birth'] ?? null,
                                $_POST['gender'] ?? 'male',
                                $_POST['blood_group'] ?? '',
                                $_POST['emergency_contact_name'] ?? '',
                                $_POST['emergency_contact_phone'] ?? '',
                                $_POST['medical_history'] ?? '',
                                $_POST['allergies'] ?? '',
                                $photo_filename,
                                $_SESSION['user_id']
                            ]
                        );
                        
                        // Create user account
                        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $db->query(
                            "INSERT INTO users (username, email, password_hash, role_id, first_name, last_name) VALUES (?, ?, ?, 4, ?, ?)",
                            [$_POST['email'], $_POST['email'], $password_hash, $_POST['first_name'], $_POST['last_name']]
                        );
                        
                        $db->commit();
                        $message = "Patient added successfully! Patient ID: $patient_id";
                        
                        // Clear form data
                        $_POST = [];
                    } catch (Exception $e) {
                        $db->rollback();
                        if ($photo_filename) {
                            deleteUploadedFile($photo_filename, 'patients');
                        }
                        throw $e;
                    }
                }
            }
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle patient update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_patient') {
    try {
        $patient_id = $_POST['patient_id'];
        $existing_photo = $_POST['existing_photo'];
        
        // Handle photo upload
        $photo_filename = $existing_photo;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
            // Delete old photo
            if ($existing_photo) {
                deleteUploadedFile($existing_photo, 'patients');
            }
            
            $upload_result = uploadPatientPhoto($_FILES['photo'], 'patient_' . $patient_id);
            if ($upload_result['success']) {
                $photo_filename = $upload_result['filename'];
            } else {
                $error_message = "Photo upload failed: " . $upload_result['message'];
            }
        }
        
        if (!$error_message) {
            // Update patient
            $db->query(
                "UPDATE patients SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, date_of_birth = ?, gender = ?, blood_group = ?, emergency_contact_name = ?, emergency_contact_phone = ?, medical_history = ?, allergies = ?, photo = ? WHERE id = ?",
                [
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['email'],
                    $_POST['phone'] ?? '',
                    $_POST['address'] ?? '',
                    $_POST['date_of_birth'] ?? null,
                    $_POST['gender'] ?? 'male',
                    $_POST['blood_group'] ?? '',
                    $_POST['emergency_contact_name'] ?? '',
                    $_POST['emergency_contact_phone'] ?? '',
                    $_POST['medical_history'] ?? '',
                    $_POST['allergies'] ?? '',
                    $photo_filename,
                    $patient_id
                ]
            );
            
            $message = "Patient updated successfully!";
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get patient for editing
$edit_patient = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_patient = $db->query("SELECT * FROM patients WHERE id = ?", [$_GET['edit']])->fetch();
}

// Get patients list
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$patients_query = "SELECT * FROM patients WHERE 1=1";
$count_query = "SELECT COUNT(*) as count FROM patients WHERE 1=1";
$params = [];

if (!empty($search)) {
    $search_condition = " AND (first_name LIKE ? OR last_name LIKE ? OR patient_id LIKE ? OR email LIKE ?)";
    $patients_query .= $search_condition;
    $count_query .= $search_condition;
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}

$total_patients = $db->query($count_query, $params)->fetch()['count'];
$total_pages = ceil($total_patients / $limit);

$patients_query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$patients = $db->query($patients_query, $params)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients Management - Hospital CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php renderDynamicStyles(); ?>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-hospital"></i> Hospital CRM</h2>
                <p><?php echo htmlspecialchars($_SESSION['role_display']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="patients.php" class="active"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="blood-bank.php"><i class="fas fa-tint"></i> Blood Bank</a></li>
                <li><a href="organ-donation.php"><i class="fas fa-heart"></i> Organ Donation</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-users"></i> Patients Management</h1>
                    <p>Manage patient records and information</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Add/Edit Patient Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> <?php echo $edit_patient ? 'Edit Patient' : 'Add New Patient'; ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?php echo $edit_patient ? 'update_patient' : 'add_patient'; ?>">
                        <?php if ($edit_patient): ?>
                            <input type="hidden" name="patient_id" value="<?php echo $edit_patient['id']; ?>">
                            <input type="hidden" name="existing_photo" value="<?php echo $edit_patient['photo']; ?>">
                        <?php endif; ?>
                        
                        <div class="grid grid-3">
                            <div class="form-group">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" required 
                                       value="<?php echo htmlspecialchars($edit_patient['first_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" required 
                                       value="<?php echo htmlspecialchars($edit_patient['last_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" required 
                                       value="<?php echo htmlspecialchars($edit_patient['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="grid grid-3">
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($edit_patient['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                       value="<?php echo $edit_patient['date_of_birth'] ?? ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" class="form-control">
                                    <option value="male" <?php echo ($edit_patient['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($edit_patient['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($edit_patient['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-3">
                            <div class="form-group">
                                <label for="blood_group">Blood Group</label>
                                <select id="blood_group" name="blood_group" class="form-control">
                                    <option value="">Select Blood Group</option>
                                    <option value="A+" <?php echo ($edit_patient['blood_group'] ?? '') == 'A+' ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo ($edit_patient['blood_group'] ?? '') == 'A-' ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo ($edit_patient['blood_group'] ?? '') == 'B+' ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo ($edit_patient['blood_group'] ?? '') == 'B-' ? 'selected' : ''; ?>>B-</option>
                                    <option value="AB+" <?php echo ($edit_patient['blood_group'] ?? '') == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo ($edit_patient['blood_group'] ?? '') == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                    <option value="O+" <?php echo ($edit_patient['blood_group'] ?? '') == 'O+' ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo ($edit_patient['blood_group'] ?? '') == 'O-' ? 'selected' : ''; ?>>O-</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="photo">Photo</label>
                                <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                                <?php if ($edit_patient && $edit_patient['photo']): ?>
                                    <small class="text-muted">Current photo: <?php echo htmlspecialchars($edit_patient['photo']); ?></small>
                                <?php endif; ?>
                            </div>
                            <?php if (!$edit_patient): ?>
                            <div class="form-group">
                                <label for="password">Login Password *</label>
                                <input type="password" id="password" name="password" class="form-control" required minlength="6">
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($edit_patient['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="emergency_contact_name">Emergency Contact Name</label>
                                <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($edit_patient['emergency_contact_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="emergency_contact_phone">Emergency Contact Phone</label>
                                <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($edit_patient['emergency_contact_phone'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="medical_history">Medical History</label>
                                <textarea id="medical_history" name="medical_history" class="form-control" rows="3"><?php echo htmlspecialchars($edit_patient['medical_history'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="allergies">Allergies</label>
                                <textarea id="allergies" name="allergies" class="form-control" rows="3"><?php echo htmlspecialchars($edit_patient['allergies'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-<?php echo $edit_patient ? 'save' : 'plus'; ?>"></i> 
                                <?php echo $edit_patient ? 'Update Patient' : 'Add Patient'; ?>
                            </button>
                            <?php if ($edit_patient): ?>
                                <a href="patients.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Patients List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Patients List (<?php echo $total_patients; ?>)</h3>
                </div>
                <div class="card-body">
                    <!-- Search -->
                    <div class="search-box">
                        <form method="GET">
                            <div class="grid grid-2">
                                <div class="form-group">
                                    <input type="text" name="search" placeholder="Search patients..." class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <?php if ($search): ?>
                                        <a href="patients.php" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (empty($patients)): ?>
                        <p class="text-muted text-center">No patients found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Patient ID</th>
                                        <th>Photo</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Blood Group</th>
                                        <th>Gender</th>
                                        <th>Age</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patients as $patient): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($patient['patient_id']); ?></td>
                                            <td>
                                                <?php if ($patient['photo']): ?>
                                                    <img src="<?php echo ImageUploadHandler::getThumbnailUrl($patient['photo'], 'patients'); ?>" 
                                                         alt="Patient Photo" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #ccc; display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-user" style="color: #666;"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['blood_group']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst($patient['gender'])); ?></td>
                                            <td><?php echo calculateAge($patient['date_of_birth']); ?></td>
                                            <td>
                                                <a href="patient-details.php?id=<?php echo $patient['id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="patients.php?edit=<?php echo $patient['id']; ?>" class="btn btn-warning" style="padding: 5px 10px; font-size: 12px;">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="text-center mt-3">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                                       class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>" 
                                       style="margin: 0 2px; padding: 5px 10px; font-size: 12px;">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-focus on first name field
        document.getElementById('first_name').focus();
    </script>
</body>
</html>
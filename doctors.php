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
if (!in_array($user_role, ['admin', 'doctor', 'intern_doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$db = new Database();
$message = '';
$error_message = '';

// Handle form submission for new doctor
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_doctor') {
    try {
        // Basic validation
        if (empty($_POST['doctor_name']) || empty($_POST['email'])) {
            $error_message = "Doctor name and email are required!";
        } else {
            // Check if email already exists
            $existing_user = $db->query("SELECT id FROM users WHERE email = ?", [$_POST['email']])->fetch();
            if ($existing_user) {
                $error_message = "Email already exists! Please use a different email.";
            } else {
                // Handle photo upload
                $photo_filename = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                    $upload_result = uploadDoctorPhoto($_FILES['photo'], 'doctor_' . time());
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
                        // Insert doctor
                        $db->query(
                            "INSERT INTO doctors (doctor_name, specialization, qualification, phone, email, address, consultation_fee, experience_years, schedule, photo, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [
                                $_POST['doctor_name'],
                                $_POST['specialization'] ?? '',
                                $_POST['qualification'] ?? '',
                                $_POST['phone'] ?? '',
                                $_POST['email'],
                                $_POST['address'] ?? '',
                                $_POST['consultation_fee'] ?? 0,
                                $_POST['experience_years'] ?? 0,
                                $_POST['schedule'] ?? '',
                                $photo_filename,
                                $_SESSION['user_id']
                            ]
                        );
                        
                        // Create user account if password provided
                        if (!empty($_POST['password'])) {
                            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                            $db->query(
                                "INSERT INTO users (username, email, password_hash, role_id, first_name, last_name) VALUES (?, ?, ?, 2, ?, ?)",
                                [$_POST['email'], $_POST['email'], $password_hash, $_POST['doctor_name'], '']
                            );
                        }
                        
                        $db->commit();
                        $message = "Doctor added successfully!";
                        
                        // Clear form data
                        $_POST = [];
                    } catch (Exception $e) {
                        $db->rollback();
                        if ($photo_filename) {
                            deleteUploadedFile($photo_filename, 'doctors');
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

// Get doctors list
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$doctors_query = "SELECT * FROM doctors WHERE 1=1";
$count_query = "SELECT COUNT(*) as count FROM doctors WHERE 1=1";
$params = [];

if (!empty($search)) {
    $search_condition = " AND (doctor_name LIKE ? OR specialization LIKE ? OR email LIKE ?)";
    $doctors_query .= $search_condition;
    $count_query .= $search_condition;
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param];
}

$total_doctors = $db->query($count_query, $params)->fetch()['count'];
$total_pages = ceil($total_doctors / $limit);

$doctors_query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$doctors = $db->query($doctors_query, $params)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors Management - Hospital CRM</title>
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
                <li><a href="patients.php"><i class="fas fa-users"></i> Patients</a></li>
                <li><a href="doctors.php" class="active"><i class="fas fa-user-md"></i> Doctors</a></li>
                <li><a href="appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>
                <li><a href="pharmacy.php"><i class="fas fa-pills"></i> Pharmacy</a></li>
                <li><a href="laboratory.php"><i class="fas fa-flask"></i> Laboratory</a></li>
                <li><a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a></li>
                <li><a href="billing.php"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                <li><a href="staff.php"><i class="fas fa-user-tie"></i> Staff</a></li>
                <li><a href="equipment.php"><i class="fas fa-tools"></i> Equipment</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1><i class="fas fa-user-md"></i> Doctors Management</h1>
                    <p>Manage doctor records and information</p>
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

            <?php if (in_array($user_role, ['admin'])): ?>
            <!-- Add New Doctor -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> Add New Doctor</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_doctor">
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="doctor_name">Doctor Name *</label>
                                <input type="text" id="doctor_name" name="doctor_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="specialization">Specialization</label>
                                <input type="text" id="specialization" name="specialization" class="form-control">
                            </div>
                        </div>
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" class="form-control">
                            </div>
                        </div>
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="qualification">Qualification</label>
                                <input type="text" id="qualification" name="qualification" class="form-control" placeholder="MBBS, MD, etc.">
                            </div>
                            <div class="form-group">
                                <label for="experience_years">Experience (Years)</label>
                                <input type="number" id="experience_years" name="experience_years" class="form-control" min="0">
                            </div>
                        </div>
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="consultation_fee">Consultation Fee (₹)</label>
                                <input type="number" id="consultation_fee" name="consultation_fee" class="form-control" min="0" step="0.01">
                            </div>
                            <div class="form-group">
                                <label for="photo">Photo</label>
                                <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label for="schedule">Schedule</label>
                                <textarea id="schedule" name="schedule" class="form-control" rows="3" placeholder="Mon-Fri: 9AM-5PM"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="password">Login Password (Optional)</label>
                                <input type="password" id="password" name="password" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Doctor
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Doctors List -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Doctors List (<?php echo $total_doctors; ?>)</h3>
                </div>
                <div class="card-body">
                    <!-- Search -->
                    <div class="search-box">
                        <form method="GET">
                            <div class="grid grid-2">
                                <div class="form-group">
                                    <input type="text" name="search" placeholder="Search doctors..." class="form-control" 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <?php if ($search): ?>
                                        <a href="doctors.php" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (empty($doctors)): ?>
                        <p class="text-muted text-center">No doctors found.</p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Photo</th>
                                        <th>Name</th>
                                        <th>Specialization</th>
                                        <th>Qualification</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Experience</th>
                                        <th>Fee</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <tr>
                                            <td>
                                                <?php if ($doctor['photo']): ?>
                                                    <img src="<?php echo ImageUploadHandler::getThumbnailUrl($doctor['photo'], 'doctors'); ?>" 
                                                         alt="Doctor Photo" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #ccc; display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-user-md" style="color: #666;"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($doctor['doctor_name']); ?></td>
                                            <td><?php echo htmlspecialchars($doctor['specialization']); ?></td>
                                            <td><?php echo htmlspecialchars($doctor['qualification']); ?></td>
                                            <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                            <td><?php echo htmlspecialchars($doctor['phone']); ?></td>
                                            <td><?php echo $doctor['experience_years']; ?> years</td>
                                            <td><?php echo formatCurrency($doctor['consultation_fee']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $doctor['is_active'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
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
        // Auto-focus on doctor name field
        document.getElementById('doctor_name')?.focus();
    </script>
</body>
</html>
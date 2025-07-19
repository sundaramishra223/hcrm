<?php
/**
 * Create Demo Patient with Proper Login Credentials
 * This creates a test patient that can login to the system
 */

require_once 'config/database.php';

try {
    $db = new Database();
    
    echo "<h2>🏥 Creating Demo Patient Account</h2>\n";
    
    // Start transaction
    $db->getConnection()->beginTransaction();
    
    // Get or create patient role
    $role = $db->query("SELECT id FROM roles WHERE role_name = 'patient'")->fetch();
    if (!$role) {
        $db->query("INSERT INTO roles (role_name, role_display_name, description) VALUES ('patient', 'Patient', 'Hospital Patient')");
        $role_id = $db->lastInsertId();
        echo "<p>✅ Created patient role with ID: $role_id</p>\n";
    } else {
        $role_id = $role['id'];
        echo "<p>✅ Using existing patient role with ID: $role_id</p>\n";
    }
    
    // Demo patient details
    $email = 'patient.demo@hospital.com';
    $password = 'Patient123!';
    $username = 'patient_demo';
    
    // Check if patient already exists
    $existing_user = $db->query("SELECT id FROM users WHERE email = ?", [$email])->fetch();
    if ($existing_user) {
        echo "<p>⚠️ Demo patient already exists with email: $email</p>\n";
        echo "<p>You can login with:</p>\n";
        echo "<p><strong>Email:</strong> $email</p>\n";
        echo "<p><strong>Password:</strong> $password</p>\n";
        $db->getConnection()->rollBack();
        return;
    }
    
    // Generate patient ID
    $year = date('Y');
    $stmt = $db->query("SELECT COUNT(*) as count FROM patients WHERE YEAR(created_at) = ? AND hospital_id = 1", [$year]);
    $count = $stmt->fetch()['count'] + 1;
    $patient_id = "P" . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Create user account
    $user_sql = "INSERT INTO users (username, email, password_hash, role_id, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
    $db->query($user_sql, [$username, $email, $hashed_password, $role_id]);
    $user_id = $db->lastInsertId();
    
    // Create patient record
    $patient_sql = "INSERT INTO patients (user_id, patient_id, first_name, middle_name, last_name, phone, emergency_contact, email, address, date_of_birth, gender, blood_group, marital_status, occupation, medical_history, allergies, hospital_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
    
    $db->query($patient_sql, [
        $user_id,
        $patient_id,
        'John',
        'William',
        'Doe',
        '+1-555-0123',
        '+1-555-0124',
        $email,
        '123 Main Street, Anytown, AT 12345',
        '1990-05-15',
        'male',
        'O+',
        'single',
        'Software Engineer',
        'No significant medical history',
        'No known allergies'
    ]);
    
    // Commit transaction
    $db->getConnection()->commit();
    
    echo "<h3>🎉 Demo Patient Created Successfully!</h3>\n";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>\n";
    echo "<h4>📋 Demo Patient Login Details:</h4>\n";
    echo "<p><strong>Patient ID:</strong> $patient_id</p>\n";
    echo "<p><strong>Name:</strong> John William Doe</p>\n";
    echo "<p><strong>Email:</strong> $email</p>\n";
    echo "<p><strong>Password:</strong> $password</p>\n";
    echo "<p><strong>Phone:</strong> +1-555-0123</p>\n";
    echo "<p><strong>Date of Birth:</strong> May 15, 1990</p>\n";
    echo "<p><strong>Blood Group:</strong> O+</p>\n";
    echo "</div>\n";
    
    echo "<h4>🔐 Login Instructions:</h4>\n";
    echo "<ol>\n";
    echo "<li>Go to the hospital login page</li>\n";
    echo "<li>Enter email: <strong>$email</strong></li>\n";
    echo "<li>Enter password: <strong>$password</strong></li>\n";
    echo "<li>Click 'Sign In'</li>\n";
    echo "</ol>\n";
    
    echo "<h4>✨ Features Available for Patient:</h4>\n";
    echo "<ul>\n";
    echo "<li>View personal dashboard</li>\n";
    echo "<li>View medical history</li>\n";
    echo "<li>View appointments</li>\n";
    echo "<li>View prescriptions</li>\n";
    echo "<li>View bills</li>\n";
    echo "<li>View assigned bed (if inpatient)</li>\n";
    echo "<li>Update profile information</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    $db->getConnection()->rollBack();
    echo "<h3 style='color: red;'>❌ Error Creating Demo Patient</h3>\n";
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Patient Creation - Hospital CRM</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h2, h3, h4 { color: #333; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        .info { color: #17a2b8; }
        ul, ol { margin-left: 20px; }
        p { margin-bottom: 10px; }
        strong { color: #2c3e50; }
    </style>
</head>
<body>
    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🔐 Test Patient Login</a>
        <a href="dashboard.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">← Back to Dashboard</a>
        <a href="patients.php" style="background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">View All Patients</a>
    </div>
</body>
</html>
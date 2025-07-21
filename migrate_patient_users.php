<?php
/**
 * Migration Script: Convert Patient Passwords to User Accounts
 * This script moves patient passwords from patients table to users table
 * Run this once to fix existing patient login issues
 */

require_once 'config/database.php';

try {
    $db = new Database();
    
    echo "<h2>🔄 Patient User Account Migration</h2>\n";
    echo "<p>Starting migration process...</p>\n";
    
    // Start transaction
    $db->getConnection()->beginTransaction();
    
    // Get patient role ID or create it
    $role = $db->query("SELECT id FROM roles WHERE role_name = 'patient'")->fetch();
    if (!$role) {
        echo "<p>📝 Creating patient role...</p>\n";
        $db->query("INSERT INTO roles (role_name, role_display_name, description) VALUES ('patient', 'Patient', 'Hospital Patient')");
        $role_id = $db->lastInsertId();
        echo "<p>✅ Patient role created with ID: $role_id</p>\n";
    } else {
        $role_id = $role['id'];
        echo "<p>✅ Found existing patient role with ID: $role_id</p>\n";
    }
    
    // Find patients who have passwords but no user_id
    $patients_with_passwords = $db->query(
        "SELECT * FROM patients WHERE password IS NOT NULL AND password != '' AND (user_id IS NULL OR user_id = 0)"
    )->fetchAll();
    
    $migrated_count = 0;
    $errors = [];
    
    echo "<p>📊 Found " . count($patients_with_passwords) . " patients to migrate</p>\n";
    
    foreach ($patients_with_passwords as $patient) {
        try {
            // Check if email exists
            if (empty($patient['email'])) {
                $errors[] = "Patient {$patient['patient_id']} has no email address - skipping";
                continue;
            }
            
            // Check if user with this email already exists
            $existing_user = $db->query("SELECT id FROM users WHERE email = ?", [$patient['email']])->fetch();
            if ($existing_user) {
                // Link existing user to patient
                $db->query("UPDATE patients SET user_id = ? WHERE id = ?", [$existing_user['id'], $patient['id']]);
                echo "<p>🔗 Linked patient {$patient['patient_id']} to existing user account</p>\n";
                $migrated_count++;
                continue;
            }
            
            // Create username from email
            $username = explode('@', $patient['email'])[0];
            $counter = 1;
            $original_username = $username;
            
            // Ensure username is unique
            while ($db->query("SELECT id FROM users WHERE username = ?", [$username])->fetch()) {
                $username = $original_username . $counter;
                $counter++;
            }
            
            // Create user account
            $user_sql = "INSERT INTO users (username, email, password_hash, role_id, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
            $db->query($user_sql, [$username, $patient['email'], $patient['password'], $role_id]);
            $user_id = $db->lastInsertId();
            
            // Link patient to user and clear password from patients table
            $db->query("UPDATE patients SET user_id = ?, password = NULL WHERE id = ?", [$user_id, $patient['id']]);
            
            echo "<p>✅ Migrated patient {$patient['patient_id']} (email: {$patient['email']}, username: $username)</p>\n";
            $migrated_count++;
            
        } catch (Exception $e) {
            $errors[] = "Error migrating patient {$patient['patient_id']}: " . $e->getMessage();
        }
    }
    
    // Check for patients without passwords who need accounts
    $patients_without_passwords = $db->query(
        "SELECT * FROM patients WHERE (password IS NULL OR password = '') AND (user_id IS NULL OR user_id = 0) AND email IS NOT NULL AND email != ''"
    )->fetchAll();
    
    echo "<p>📊 Found " . count($patients_without_passwords) . " patients without passwords who need accounts</p>\n";
    
    foreach ($patients_without_passwords as $patient) {
        try {
            // Check if user with this email already exists
            $existing_user = $db->query("SELECT id FROM users WHERE email = ?", [$patient['email']])->fetch();
            if ($existing_user) {
                // Link existing user to patient
                $db->query("UPDATE patients SET user_id = ? WHERE id = ?", [$existing_user['id'], $patient['id']]);
                echo "<p>🔗 Linked patient {$patient['patient_id']} to existing user account</p>\n";
                $migrated_count++;
                continue;
            }
            
            // Create username from email
            $username = explode('@', $patient['email'])[0];
            $counter = 1;
            $original_username = $username;
            
            // Ensure username is unique
            while ($db->query("SELECT id FROM users WHERE username = ?", [$username])->fetch()) {
                $username = $original_username . $counter;
                $counter++;
            }
            
            // Generate temporary password (they'll need to reset it)
            $temp_password = 'TempPass123!';
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Create user account
            $user_sql = "INSERT INTO users (username, email, password_hash, role_id, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
            $db->query($user_sql, [$username, $patient['email'], $hashed_password, $role_id]);
            $user_id = $db->lastInsertId();
            
            // Link patient to user
            $db->query("UPDATE patients SET user_id = ? WHERE id = ?", [$user_id, $patient['id']]);
            
            echo "<p>🆕 Created account for patient {$patient['patient_id']} (email: {$patient['email']}, username: $username, temp password: $temp_password)</p>\n";
            $migrated_count++;
            
        } catch (Exception $e) {
            $errors[] = "Error creating account for patient {$patient['patient_id']}: " . $e->getMessage();
        }
    }
    
    // Commit transaction
    $db->getConnection()->commit();
    
    echo "<h3>📈 Migration Summary</h3>\n";
    echo "<p>✅ Successfully migrated/created: $migrated_count patient accounts</p>\n";
    
    if (!empty($errors)) {
        echo "<h4>⚠️ Errors encountered:</h4>\n";
        foreach ($errors as $error) {
            echo "<p style='color: red;'>❌ $error</p>\n";
        }
    }
    
    echo "<h3>🎉 Migration completed successfully!</h3>\n";
    echo "<p><strong>Important Notes:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Patients can now login using their email address and password</li>\n";
    echo "<li>Passwords are now stored securely in the users table</li>\n";
    echo "<li>Patients with temporary passwords (TempPass123!) should change them after login</li>\n";
    echo "<li>All patient accounts are linked to user accounts via user_id</li>\n";
    echo "</ul>\n";
    
    // Display current status
    $total_patients = $db->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'];
    $patients_with_users = $db->query("SELECT COUNT(*) as count FROM patients WHERE user_id IS NOT NULL AND user_id != 0")->fetch()['count'];
    
    echo "<h4>📊 Current Status:</h4>\n";
    echo "<p>Total Patients: $total_patients</p>\n";
    echo "<p>Patients with User Accounts: $patients_with_users</p>\n";
    echo "<p>Coverage: " . round(($patients_with_users / $total_patients) * 100, 2) . "%</p>\n";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->getConnection()->rollBack();
    echo "<h3 style='color: red;'>❌ Migration Failed</h3>\n";
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
    echo "<p>Transaction has been rolled back. No changes were made.</p>\n";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Migration - Hospital CRM</title>
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
        ul { margin-left: 20px; }
        p { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div style="text-align: center; margin-top: 30px;">
        <a href="dashboard.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a>
        <a href="patients.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;">View Patients</a>
    </div>
</body>
</html>
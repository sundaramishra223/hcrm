<?php
require_once 'config/database.php';

echo "🔧 Fixing Login Passwords...\n";

try {
    $db = new Database();
    
    // Generate proper password hashes for all demo users
    $admin_hash = password_hash('admin', PASSWORD_DEFAULT);
    $doctor_hash = password_hash('admin', PASSWORD_DEFAULT); 
    $nurse_hash = password_hash('admin', PASSWORD_DEFAULT);
    $patient_hash = password_hash('admin', PASSWORD_DEFAULT);
    
    echo "Generated password hashes:\n";
    echo "Admin hash: $admin_hash\n";
    echo "Doctor hash: $doctor_hash\n";
    echo "Nurse hash: $nurse_hash\n";
    echo "Patient hash: $patient_hash\n\n";
    
    // Update admin user
    $result = $db->query(
        "UPDATE users SET password_hash = ? WHERE username = 'admin'", 
        [$admin_hash]
    );
    echo "✅ Updated admin password\n";
    
    // Update doctor1 user
    $result = $db->query(
        "UPDATE users SET password_hash = ? WHERE username = 'doctor1'", 
        [$doctor_hash]
    );
    echo "✅ Updated doctor1 password\n";
    
    // Update nurse1 user
    $result = $db->query(
        "UPDATE users SET password_hash = ? WHERE username = 'nurse1'", 
        [$nurse_hash]
    );
    echo "✅ Updated nurse1 password\n";
    
    // Update patient1 user
    $result = $db->query(
        "UPDATE users SET password_hash = ? WHERE username = 'patient1'", 
        [$patient_hash]
    );
    echo "✅ Updated patient1 password\n";
    
    // Verify the updates
    echo "\n🔍 Verifying password updates:\n";
    
    $users = $db->query("SELECT username, email, password_hash FROM users WHERE username IN ('admin', 'doctor1', 'nurse1', 'patient1')")->fetchAll();
    
    foreach ($users as $user) {
        $verify_result = password_verify('admin', $user['password_hash']) ? '✅ VALID' : '❌ INVALID';
        echo "User: {$user['username']} ({$user['email']}) - {$verify_result}\n";
    }
    
    echo "\n🎉 Password fix completed!\n";
    echo "Login Credentials:\n";
    echo "- admin@hospital.com / admin\n";
    echo "- doctor1@hospital.com / admin\n";
    echo "- nurse1@hospital.com / admin\n";
    echo "- patient1@hospital.com / admin\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
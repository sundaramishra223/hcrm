<?php
require_once 'config/database.php';

try {
    $db = new Database();
    
    // Hash the demo password "password"
    $hashed_password = password_hash('password', PASSWORD_DEFAULT);
    
    // Update all demo users with properly hashed password
    $users = [
        'admin@hospital.com',
        'dr.sharma@hospital.com', 
        'john.doe@email.com',
        'priya.nurse@hospital.com',
        'reception@hospital.com'
    ];
    
    foreach ($users as $email) {
        $db->query(
            "UPDATE users SET password_hash = ? WHERE email = ?",
            [$hashed_password, $email]
        );
        echo "Updated password for: $email\n";
    }
    
    echo "\nAll demo passwords updated successfully!\n";
    echo "Demo login password is: password\n";
    
} catch (Exception $e) {
    echo "Error updating passwords: " . $e->getMessage() . "\n";
}
?>

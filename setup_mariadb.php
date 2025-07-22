<?php
// MariaDB Setup Script for Hospital CRM
// Run this when MariaDB is properly configured

echo "🔧 Setting up MariaDB for Hospital CRM...\n";

try {
    // Connect to MariaDB
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS hospital_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "✅ Database 'hospital_crm' created\n";
    
    // Use the database
    $pdo->exec("USE hospital_crm");
    
    // Run the complete database setup from your GitHub repo
    $sql_file = 'COMPLETE_HOSPITAL_CRM_DATABASE.sql';
    
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        
        // Split SQL commands (basic splitting)
        $commands = explode(';', $sql_content);
        
        $executed = 0;
        foreach ($commands as $command) {
            $command = trim($command);
            if (!empty($command) && !preg_match('/^(--|#|\/\*)/', $command)) {
                try {
                    $pdo->exec($command);
                    $executed++;
                } catch (PDOException $e) {
                    // Skip comments and non-critical errors
                    if (!preg_match('/(Table.*already exists|Duplicate entry)/i', $e->getMessage())) {
                        echo "Warning: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        
        echo "✅ Executed $executed SQL commands\n";
    } else {
        echo "❌ SQL file not found: $sql_file\n";
        echo "Creating basic tables manually...\n";
        
        // Create basic tables
        $tables = [
            "CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                role_name VARCHAR(50) UNIQUE NOT NULL,
                role_display_name VARCHAR(100) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(50) DEFAULT 'patient',
                role_id INT,
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                phone VARCHAR(20),
                address TEXT,
                date_of_birth DATE,
                gender VARCHAR(10),
                is_active BOOLEAN DEFAULT 1,
                last_login DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (role_id) REFERENCES roles(id)
            )"
        ];
        
        foreach ($tables as $table_sql) {
            $pdo->exec($table_sql);
        }
        echo "✅ Basic tables created\n";
    }
    
    // Insert demo data
    $password_hash = password_hash('password', PASSWORD_DEFAULT);
    
    // Insert roles
    $roles = [
        [1, 'admin', 'Administrator', 'Full system access'],
        [2, 'doctor', 'Doctor', 'Medical practitioner'],
        [3, 'nurse', 'Nurse', 'Nursing staff'],
        [4, 'patient', 'Patient', 'Hospital patient'],
        [5, 'receptionist', 'Receptionist', 'Front desk staff']
    ];
    
    foreach ($roles as $role) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO roles (id, role_name, role_display_name, description) VALUES (?, ?, ?, ?)");
        $stmt->execute($role);
    }
    
    // Insert demo users
    $demo_users = [
        ['admin', 'admin@hospital.com', 'password', $password_hash, 'admin', 1, 'Admin', 'User'],
        ['dr.sharma', 'dr.sharma@hospital.com', 'password', $password_hash, 'doctor', 2, 'Dr. Sharma', 'Kumar'],
        ['john.doe', 'john.doe@email.com', 'password', $password_hash, 'doctor', 2, 'John', 'Doe'],
        ['priya.nurse', 'priya.nurse@hospital.com', 'password', $password_hash, 'nurse', 3, 'Priya', 'Sharma'],
        ['reception', 'reception@hospital.com', 'password', $password_hash, 'receptionist', 5, 'Reception', 'Desk']
    ];
    
    foreach ($demo_users as $user) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password, password_hash, role, role_id, first_name, last_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($user);
    }
    
    echo "✅ Demo users created\n";
    echo "\n🎉 MariaDB setup completed successfully!\n";
    echo "\n📋 Demo Login Credentials:\n";
    echo "Admin: admin@hospital.com / password\n";
    echo "Doctor: dr.sharma@hospital.com / password\n";
    echo "Nurse: priya.nurse@hospital.com / password\n";
    echo "Receptionist: reception@hospital.com / password\n";
    
    echo "\n🔄 To switch to MariaDB:\n";
    echo "1. Set \$use_mariadb = true in config/database.php\n";
    echo "2. Restart your PHP server\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
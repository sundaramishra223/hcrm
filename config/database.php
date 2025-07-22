<?php
// Database Configuration - Flexible for SQLite/MariaDB
$use_mariadb = false; // Set to true when MariaDB is properly running

if ($use_mariadb) {
    // MariaDB Configuration
    define('DB_HOST', 'localhost');
    define('DB_USERNAME', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'hospital_crm');
    define('DB_CHARSET', 'utf8mb4');
}

class Database {
    private $connection;
    
    public function __construct() {
        global $use_mariadb;
        
        try {
            if ($use_mariadb) {
                // MariaDB Connection
                $dsn_no_db = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
                $temp_conn = new PDO($dsn_no_db, DB_USERNAME, DB_PASSWORD);
                $temp_conn->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
                $temp_conn = null;
                
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $this->connection = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
            } else {
                // SQLite Connection (Current Working Setup)
                $this->connection = new PDO('sqlite:hospital_crm.db');
            }
            
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}
?>

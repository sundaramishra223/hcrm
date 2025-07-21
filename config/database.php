<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hospital_crm');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private $connection;
    
    public function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->connection = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            // Show popup instead of dying
            echo "<script>
                alert('Database connection failed: " . addslashes($e->getMessage()) . "');
                window.history.back();
            </script>";
            exit;
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
            // Show popup instead of throwing exception
            echo "<script>
                alert('Query failed: " . addslashes($e->getMessage()) . "');
                window.history.back();
            </script>";
            exit;
        }
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}
?>

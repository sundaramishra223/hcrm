<?php
/**
 * Log Cleanup Script
 * Standalone script for cleaning up old logs in HCRM system
 * Can be run manually or scheduled via cron job
 * 
 * Usage:
 * php cleanup-logs.php                    # Clean all logs with default retention
 * php cleanup-logs.php --type=security   # Clean only security logs
 * php cleanup-logs.php --days=30         # Clean all logs older than 30 days
 * php cleanup-logs.php --stats           # Show log statistics only
 * php cleanup-logs.php --help            # Show help
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/log-cleanup-helper.php';

class LogCleanupScript {
    private $db;
    private $cleanup_helper;
    
    public function __construct() {
        // Initialize database connection
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            $this->cleanup_helper = new LogCleanupHelper($this->db);
        } catch (Exception $e) {
            $this->output("ERROR: Database connection failed: " . $e->getMessage());
            exit(1);
        }
    }
    
    public function run($args) {
        $options = $this->parseArgs($args);
        
        if (isset($options['help'])) {
            $this->showHelp();
            return;
        }
        
        $this->output("HCRM Log Cleanup Script");
        $this->output("Started at: " . date('Y-m-d H:i:s'));
        $this->output(str_repeat("-", 50));
        
        if (isset($options['stats'])) {
            $this->showStatistics();
            return;
        }
        
        $this->performCleanup($options);
    }
    
    private function parseArgs($args) {
        $options = [];
        
        for ($i = 1; $i < count($args); $i++) {
            $arg = $args[$i];
            
            if (strpos($arg, '--') === 0) {
                $parts = explode('=', substr($arg, 2), 2);
                $key = $parts[0];
                $value = isset($parts[1]) ? $parts[1] : true;
                $options[$key] = $value;
            }
        }
        
        return $options;
    }
    
    private function showHelp() {
        echo "HCRM Log Cleanup Script\n";
        echo "Usage: php cleanup-logs.php [options]\n\n";
        echo "Options:\n";
        echo "  --help                  Show this help message\n";
        echo "  --stats                 Show log statistics only (no cleanup)\n";
        echo "  --type=<log_type>       Clean specific log type only\n";
        echo "                          Types: security, login, audit, email, patient_status, error_files\n";
        echo "  --days=<number>         Override retention period (days)\n";
        echo "  --dry-run               Show what would be cleaned without actually doing it\n";
        echo "  --verbose               Show detailed output\n\n";
        echo "Examples:\n";
        echo "  php cleanup-logs.php                    # Clean all logs with default retention\n";
        echo "  php cleanup-logs.php --type=security    # Clean only security logs\n";
        echo "  php cleanup-logs.php --days=30          # Clean all logs older than 30 days\n";
        echo "  php cleanup-logs.php --stats            # Show statistics only\n";
        echo "  php cleanup-logs.php --dry-run          # Preview cleanup actions\n";
    }
    
    private function showStatistics() {
        $this->output("Log Statistics:");
        $stats = $this->cleanup_helper->getLogStatistics();
        
        foreach ($stats as $table => $data) {
            if (isset($data['error'])) {
                $this->output("  {$table}: ERROR - " . $data['error']);
            } else {
                $this->output(sprintf(
                    "  %-20s: %6d total, %6d old (>%d days)",
                    $table,
                    $data['total_records'],
                    $data['old_records'],
                    $data['retention_days']
                ));
            }
        }
    }
    
    private function performCleanup($options) {
        $verbose = isset($options['verbose']);
        $dry_run = isset($options['dry_run']);
        $days = isset($options['days']) ? (int)$options['days'] : null;
        $type = isset($options['type']) ? $options['type'] : null;
        
        if ($dry_run) {
            $this->output("DRY RUN MODE - No actual cleanup will be performed");
            $this->output("");
        }
        
        if ($type) {
            // Clean specific log type
            $result = $this->cleanSpecificType($type, $days, $dry_run, $verbose);
            $this->displayResult($type, $result);
        } else {
            // Clean all log types
            $custom_retention = $days ? array_fill_keys([
                'security_logs', 'login_attempts', 'audit_logs', 
                'email_logs', 'patient_status_logs', 'error_logs'
            ], $days) : [];
            
            if ($dry_run) {
                $this->simulateCleanup($custom_retention, $verbose);
            } else {
                $results = $this->cleanup_helper->cleanAllLogs($custom_retention);
                $this->displayComprehensiveResults($results, $verbose);
            }
        }
        
        $this->output(str_repeat("-", 50));
        $this->output("Completed at: " . date('Y-m-d H:i:s'));
    }
    
    private function cleanSpecificType($type, $days, $dry_run, $verbose) {
        if ($dry_run) {
            return $this->simulateSpecificCleanup($type, $days, $verbose);
        }
        
        switch ($type) {
            case 'security':
                return $this->cleanup_helper->cleanSecurityLogs($days);
            case 'login':
                return $this->cleanup_helper->cleanLoginAttempts($days);
            case 'audit':
                return $this->cleanup_helper->cleanAuditLogs($days);
            case 'email':
                return $this->cleanup_helper->cleanEmailLogs($days);
            case 'patient_status':
                return $this->cleanup_helper->cleanPatientStatusLogs($days);
            case 'error_files':
                return $this->cleanup_helper->cleanErrorLogFiles($days);
            default:
                return ['success' => false, 'error' => "Unknown log type: {$type}"];
        }
    }
    
    private function simulateSpecificCleanup($type, $days, $verbose) {
        // Get statistics to show what would be cleaned
        $stats = $this->cleanup_helper->getLogStatistics();
        $table_map = [
            'security' => 'security_logs',
            'login' => 'login_attempts',
            'audit' => 'audit_logs',
            'email' => 'email_logs',
            'patient_status' => 'patient_status_logs'
        ];
        
        $table = $table_map[$type] ?? null;
        if ($table && isset($stats[$table])) {
            $would_delete = $stats[$table]['old_records'];
            return [
                'success' => true,
                'deleted_count' => $would_delete,
                'retention_days' => $days ?? $stats[$table]['retention_days'],
                'simulated' => true
            ];
        }
        
        return ['success' => false, 'error' => "Cannot simulate cleanup for {$type}"];
    }
    
    private function simulateCleanup($custom_retention, $verbose) {
        $this->output("Simulating cleanup with the following settings:");
        $stats = $this->cleanup_helper->getLogStatistics();
        $total_would_delete = 0;
        
        foreach ($stats as $table => $data) {
            if (isset($data['error'])) {
                $this->output("  {$table}: ERROR - " . $data['error']);
            } else {
                $would_delete = $data['old_records'];
                $total_would_delete += $would_delete;
                $this->output(sprintf(
                    "  %-20s: Would delete %6d records (>%d days)",
                    $table,
                    $would_delete,
                    $data['retention_days']
                ));
            }
        }
        
        $this->output("");
        $this->output("Total records that would be deleted: {$total_would_delete}");
    }
    
    private function displayResult($type, $result) {
        if ($result['success']) {
            $action = isset($result['simulated']) ? "Would delete" : "Deleted";
            $count = $result['deleted_count'] ?? $result['processed_files'] ?? 0;
            $this->output("{$action} {$count} {$type} log entries (older than {$result['retention_days']} days)");
        } else {
            $this->output("ERROR cleaning {$type} logs: " . $result['error']);
        }
    }
    
    private function displayComprehensiveResults($results, $verbose) {
        $this->output("Cleanup Results:");
        
        foreach ($results['details'] as $log_type => $result) {
            if ($verbose || !$result['success']) {
                $this->displayResult($log_type, $result);
            }
        }
        
        if (!$verbose) {
            $this->output("Total deleted: {$results['total_deleted']} records");
            if ($results['total_errors'] > 0) {
                $this->output("Errors encountered: {$results['total_errors']}");
            }
        }
        
        if ($results['success']) {
            $this->output("All cleanup operations completed successfully!");
        } else {
            $this->output("Some cleanup operations failed. Check error logs for details.");
        }
    }
    
    private function output($message) {
        echo $message . "\n";
        
        // Also log to system if not in CLI mode
        if (php_sapi_name() !== 'cli') {
            error_log("LOG_CLEANUP: " . $message);
        }
    }
}

// Run the script
if (php_sapi_name() === 'cli') {
    // Command line execution
    $script = new LogCleanupScript();
    $script->run($argv);
} else {
    // Web execution (for testing)
    echo "<pre>";
    $script = new LogCleanupScript();
    
    // Simulate command line arguments from GET parameters
    $args = ['cleanup-logs.php'];
    foreach ($_GET as $key => $value) {
        $args[] = "--{$key}" . ($value !== '' ? "={$value}" : '');
    }
    
    $script->run($args);
    echo "</pre>";
}
?>
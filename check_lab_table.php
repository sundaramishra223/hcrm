<?php
require_once 'config/database.php';

try {
    $db = new Database();
    
    echo "<h2>Lab Orders Table Structure</h2>";
    
    $columns = $db->query("DESCRIBE lab_orders")->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 20px;'>";
    echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Sample Lab Order (if any exists):</h3>";
    $sample = $db->query("SELECT * FROM lab_orders LIMIT 1")->fetch();
    if ($sample) {
        echo "<pre>" . print_r($sample, true) . "</pre>";
    } else {
        echo "<p>No lab orders found in database.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<a href="lab-test-management.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none;">Back to Lab Test Management</a>
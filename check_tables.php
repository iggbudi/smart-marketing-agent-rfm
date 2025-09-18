<?php
require_once 'config/database.php';

$db = getDB();

echo "=== Database Table Structures ===\n\n";

$tables = ['users', 'businesses', 'customers', 'transactions', 'rfm_analysis'];

foreach ($tables as $table) {
    echo "Table: {$table}\n";
    echo str_repeat("-", 50) . "\n";
    
    try {
        $result = $db->query("DESCRIBE {$table}");
        while ($row = $result->fetch()) {
            echo sprintf("%-20s %-15s %s\n", $row['Field'], $row['Type'], $row['Key']);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}
?>

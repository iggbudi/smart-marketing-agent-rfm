<?php
require_once 'config/database.php';

$db = getDB();

echo "Table: api_usage_logs\n";
echo str_repeat("-", 50) . "\n";

try {
    $result = $db->query("DESCRIBE api_usage_logs");
    while ($row = $result->fetch()) {
        echo sprintf("%-20s %-15s %s\n", $row['Field'], $row['Type'], $row['Key']);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTable: users\n";
echo str_repeat("-", 50) . "\n";

try {
    $result = $db->query("DESCRIBE users");
    while ($row = $result->fetch()) {
        echo sprintf("%-20s %-15s %s\n", $row['Field'], $row['Type'], $row['Key']);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

<?php
require_once 'config/database.php';

$db = getDB();

echo "=== All Tables in Database ===\n\n";

// Show all tables
$stmt = $db->query('SHOW TABLES');
while ($row = $stmt->fetch()) {
    echo "- " . $row[0] . "\n";
}

echo "\n=== Checking activity_logs table ===\n";
$stmt = $db->query("SHOW TABLES LIKE 'activity_logs'");
if ($stmt->rowCount() > 0) {
    echo "activity_logs table exists\n";
    
    $stmt = $db->query('DESCRIBE activity_logs');
    while ($row = $stmt->fetch()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "activity_logs table does NOT exist\n";
}

echo "\n=== Checking api_usage_logs table ===\n";
$stmt = $db->query("SHOW TABLES LIKE 'api_usage_logs'");
if ($stmt->rowCount() > 0) {
    echo "api_usage_logs table exists\n";
    
    $stmt = $db->query('DESCRIBE api_usage_logs');
    while ($row = $stmt->fetch()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "api_usage_logs table does NOT exist\n";
}
?>

<?php
// Database connection settings
$host = 'localhost';
$port = 3309;
$dbname = 'smart_marketing_rfm';
$username = 'root';
$password = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Database Connection Test</h2>";
    echo "<p style='color: green;'>✓ Connected to database: $dbname</p>";
    
    // Check if transactions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'transactions'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<p style='color: green;'>✓ Table 'transactions' exists</p>";
        
        // Get table structure
        echo "<h3>Table Structure:</h3>";
        $stmt = $pdo->query("DESCRIBE transactions");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
        echo "</tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Get row count
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Total rows in transactions table:</strong> " . $count['total'] . "</p>";
        
        // Get sample data (first 5 rows)
        if ($count['total'] > 0) {
            echo "<h3>Sample Data (First 5 rows):</h3>";
            $stmt = $pdo->query("SELECT * FROM transactions LIMIT 5");
            $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($sampleData)) {
                echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
                echo "<tr style='background-color: #f0f0f0;'>";
                foreach (array_keys($sampleData[0]) as $header) {
                    echo "<th>" . htmlspecialchars($header) . "</th>";
                }
                echo "</tr>";
                
                foreach ($sampleData as $row) {
                    echo "<tr>";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            }
        }
        
        // Check for customers table too
        $stmt = $pdo->query("SHOW TABLES LIKE 'customers'");
        $customersExists = $stmt->rowCount() > 0;
        
        if ($customersExists) {
            echo "<h3>Customers Table:</h3>";
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers");
            $customerCount = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p style='color: green;'>✓ Table 'customers' exists with " . $customerCount['total'] . " rows</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Table 'customers' does not exist</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Table 'transactions' does not exist</p>";
        
        // Show all tables in database
        echo "<h3>Available tables in database:</h3>";
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($tables)) {
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>" . htmlspecialchars($table) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>No tables found in database.</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<h2>Database Connection Error</h2>";
    echo "<p style='color: red;'>✗ Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    echo "<h3>Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Check if MySQL server is running on port 3309</li>";
    echo "<li>Verify database 'smart_marketing_rfm' exists</li>";
    echo "<li>Confirm username 'root' with empty password is correct</li>";
    echo "<li>Check if MySQL allows connections from localhost</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='customers.php'>Back to Customers Page</a></p>";
?>


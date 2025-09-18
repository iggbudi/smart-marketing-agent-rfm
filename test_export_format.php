<?php
// Test file to verify export format changes
echo "<h2>Export Format Test</h2>";

// Simulate customer data
$testCustomers = [
    [
        'customer_name' => 'John Doe',
        'phone' => '08123456789',
        'email' => 'john@example.com',
        'total_transactions' => 3,
        'total_spent' => 1500000.00,
        'last_transaction' => '2024-01-15',
        'created_at' => '2024-01-01'
    ],
    [
        'customer_name' => 'Jane Smith',
        'phone' => '08987654321',
        'email' => 'jane@example.com',
        'total_transactions' => 2,
        'total_spent' => 750000.00,
        'last_transaction' => '2024-02-20',
        'created_at' => '2024-01-05'
    ]
];

echo "<h3>Before (with number_format):</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Name</th><th>Total Spent (Formatted)</th></tr>";
foreach ($testCustomers as $customer) {
    echo "<tr>";
    echo "<td>" . $customer['customer_name'] . "</td>";
    echo "<td>Rp " . number_format($customer['total_spent'], 0, ',', '.') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>After (raw number for Excel):</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Name</th><th>Total Spent (Raw)</th></tr>";
foreach ($testCustomers as $customer) {
    echo "<tr>";
    echo "<td>" . $customer['customer_name'] . "</td>";
    echo "<td>" . $customer['total_spent'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Benefits of Raw Numbers in Excel:</h3>";
echo "<ul>";
echo "<li>✓ Can be used in calculations (SUM, AVERAGE, etc.)</li>";
echo "<li>✓ Can be sorted numerically</li>";
echo "<li>✓ Can be filtered by value ranges</li>";
echo "<li>✓ Excel can format as currency automatically</li>";
echo "<li>✓ No parsing issues with thousand separators</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Note:</strong> The export will now use raw numbers (1500000.00) instead of formatted strings (1,500,000) for better Excel compatibility.</p>";
echo "<p><a href='customers.php'>Back to Customers Page</a></p>";
?>


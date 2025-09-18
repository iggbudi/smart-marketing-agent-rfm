<?php
// Simple test to verify export functionality
echo "<h2>Test Export Functionality</h2>";

// Check if PhpSpreadsheet is available
if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    echo "<p style='color: green;'>✓ PhpSpreadsheet library is available</p>";
} else {
    echo "<p style='color: orange;'>⚠ PhpSpreadsheet library is not available - will use CSV fallback</p>";
}

// Check if vendor autoload exists
if (file_exists('vendor/autoload.php')) {
    echo "<p style='color: green;'>✓ Vendor autoload file exists</p>";
} else {
    echo "<p style='color: red;'>✗ Vendor autoload file not found</p>";
}

// Check if export file exists
if (file_exists('api/export-customers.php')) {
    echo "<p style='color: green;'>✓ Export file exists</p>";
} else {
    echo "<p style='color: red;'>✗ Export file not found</p>";
}

echo "<hr>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Login to the system as UMKM owner</li>";
echo "<li>Go to customers.php page</li>";
echo "<li>Click the 'Export Excel' button</li>";
echo "<li>File should download automatically</li>";
echo "</ol>";

echo "<p><a href='customers.php'>Go to Customers Page</a></p>";
?>


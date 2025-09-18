<?php
// Test file to verify Indonesian date format
echo "<h2>Test Format Tanggal Indonesia</h2>";

// Sample dates
$sampleDates = [
    '2024-01-15',
    '2024-02-20', 
    '2024-03-01',
    '2024-07-20',
    '2024-12-31'
];

echo "<h3>Format Tanggal Indonesia (dd/mm/yyyy):</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Tanggal Database</th><th>Format Indonesia</th>";
echo "</tr>";

foreach ($sampleDates as $date) {
    echo "<tr>";
    echo "<td>" . $date . "</td>";
    echo "<td>" . date('d/m/Y', strtotime($date)) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Contoh Data Pelanggan:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Nama</th><th>Transaksi Terakhir</th><th>Tanggal Registrasi</th>";
echo "</tr>";

$customers = [
    ['name' => 'John Doe', 'last_transaction' => '2024-01-15', 'created_at' => '2024-01-01'],
    ['name' => 'Jane Smith', 'last_transaction' => '2024-02-20', 'created_at' => '2024-01-05'],
    ['name' => 'Bob Wilson', 'last_transaction' => null, 'created_at' => '2024-01-10']
];

foreach ($customers as $customer) {
    echo "<tr>";
    echo "<td>" . $customer['name'] . "</td>";
    echo "<td>";
    if ($customer['last_transaction']) {
        echo date('d/m/Y', strtotime($customer['last_transaction']));
    } else {
        echo "<span style='color: #999;'>-</span>";
    }
    echo "</td>";
    echo "<td>" . date('d/m/Y', strtotime($customer['created_at'])) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Keuntungan Format dd/mm/yyyy:</h3>";
echo "<ul>";
echo "<li>✓ Format standar Indonesia</li>";
echo "<li>✓ Mudah dibaca (hari/bulan/tahun)</li>";
echo "<li>✓ Konsisten dengan format lokal</li>";
echo "<li>✓ Tidak membingungkan dengan format US (mm/dd/yyyy)</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Status:</strong> Format tanggal Indonesia (dd/mm/yyyy) sudah diterapkan di customers.php</p>";
echo "<p><a href='customers.php'>Kembali ke Halaman Pelanggan</a></p>";
?>


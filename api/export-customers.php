<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Check if PhpSpreadsheet is available
$hasPhpSpreadsheet = class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet');
if ($hasPhpSpreadsheet) {
    require_once '../vendor/autoload.php';
}

// Require UMKM owner access
requireAuth(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    die('Error: No business associated with your account. Please contact administrator.');
}

// Get customers for this business
$customers = [];
try {
    $stmt = $db->prepare("
        SELECT c.*, 
               COUNT(t.id) as total_transactions,
               COALESCE(SUM(t.amount), 0) as total_spent,
               MAX(t.transaction_date) as last_transaction
        FROM customers c 
        LEFT JOIN transactions t ON c.id = t.customer_id 
        WHERE c.business_id = ? 
        GROUP BY c.id 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$business['id']]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error loading customers: ' . $e->getMessage());
}

// Check if PhpSpreadsheet is available
if (!$hasPhpSpreadsheet) {
    // If PhpSpreadsheet is not available, create a simple CSV export
    $filename = 'customers_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, [
        'No',
        'Nama Pelanggan',
        'No HP',
        'Email',
        'Total Transaksi',
        'Total Belanja (Rp)',
        'Transaksi Terakhir',
        'Tanggal Registrasi'
    ]);
    
    // Add data
    foreach ($customers as $index => $customer) {
        fputcsv($output, [
            $index + 1,
            $customer['customer_name'],
            $customer['phone'],
            $customer['email'] ?: '-',
            $customer['total_transactions'],
            $customer['total_spent'],
            $customer['last_transaction'] ? date('d/m/Y', strtotime($customer['last_transaction'])) : '-',
            date('d/m/Y', strtotime($customer['created_at']))
        ]);
    }
    
    fclose($output);
    exit;
}

// If PhpSpreadsheet is available, create Excel file
try {
    // Create new Spreadsheet object
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator('Smart Marketing Agent')
        ->setLastModifiedBy('Smart Marketing Agent')
        ->setTitle('Data Pelanggan - ' . $business['business_name'])
        ->setSubject('Data Pelanggan')
        ->setDescription('Data pelanggan yang diekspor dari Smart Marketing Agent')
        ->setKeywords('pelanggan, customer, data')
        ->setCategory('Data Export');
    
    // Set headers
    $headers = [
        'No',
        'Nama Pelanggan',
        'No HP',
        'Email',
        'Total Transaksi',
        'Total Belanja (Rp)',
        'Transaksi Terakhir',
        'Tanggal Registrasi'
    ];
    
    // Add headers to sheet
    foreach ($headers as $colIndex => $header) {
        $column = chr(65 + $colIndex); // A, B, C, etc.
        $sheet->setCellValue($column . '1', $header);
        
        // Style headers
        $sheet->getStyle($column . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '007BFF'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
    }
    
    // Add data
    foreach ($customers as $index => $customer) {
        $row = $index + 2;
        
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $customer['customer_name']);
        $sheet->setCellValue('C' . $row, $customer['phone']);
        $sheet->setCellValue('D' . $row, $customer['email'] ?: '-');
        $sheet->setCellValue('E' . $row, $customer['total_transactions']);
        $sheet->setCellValue('F' . $row, $customer['total_spent']);
        $sheet->setCellValue('G' . $row, $customer['last_transaction'] ? date('d/m/Y', strtotime($customer['last_transaction'])) : '-');
        $sheet->setCellValue('H' . $row, date('d/m/Y', strtotime($customer['created_at'])));
    }
    
    // Auto-size columns
    foreach (range('A', 'H') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Add borders to all cells
    $lastRow = count($customers) + 1;
    $sheet->getStyle('A1:H' . $lastRow)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ]);
    
    // Center align numbers
    $sheet->getStyle('A1:A' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E1:E' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // Right align currency and format as currency
    $sheet->getStyle('F1:F' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('F2:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
    
    // Create filename
    $filename = 'customers_' . $business['business_name'] . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Create Excel file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    
} catch (Exception $e) {
    die('Error creating Excel file: ' . $e->getMessage());
}
?>

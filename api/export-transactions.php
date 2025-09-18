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

// Get transactions for this business
$transactions = [];
try {
    $stmt = $db->prepare("
        SELECT t.*, c.customer_name, c.phone
        FROM transactions t 
        JOIN customers c ON t.customer_id = c.id 
        WHERE t.business_id = ? 
        ORDER BY t.transaction_date DESC, t.created_at DESC
    ");
    $stmt->execute([$business['id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error loading transactions: ' . $e->getMessage());
}

// Check if PhpSpreadsheet is available
if (!$hasPhpSpreadsheet) {
    // If PhpSpreadsheet is not available, create a simple CSV export
    $filename = 'transactions_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, [
        'No',
        'Tanggal Transaksi',
        'Nama Pelanggan',
        'No HP',
        'Nama Produk',
        'Jumlah',
        'Harga Satuan (Rp)',
        'Total (Rp)'
    ]);
    
    // Add data
    foreach ($transactions as $index => $transaction) {
        fputcsv($output, [
            $index + 1,
            date('d/m/Y', strtotime($transaction['transaction_date'])),
            $transaction['customer_name'],
            $transaction['phone'],
            $transaction['product_name'] ?: '-',
            $transaction['quantity'],
            $transaction['amount'],
            $transaction['amount'] * $transaction['quantity']
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
        ->setTitle('Data Transaksi - ' . $business['business_name'])
        ->setSubject('Data Transaksi')
        ->setDescription('Data transaksi yang diekspor dari Smart Marketing Agent')
        ->setKeywords('transaksi, transaction, data')
        ->setCategory('Data Export');
    
    // Set headers
    $headers = [
        'No',
        'Tanggal Transaksi',
        'Nama Pelanggan',
        'No HP',
        'Nama Produk',
        'Jumlah',
        'Harga Satuan (Rp)',
        'Total (Rp)'
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
    foreach ($transactions as $index => $transaction) {
        $row = $index + 2;
        
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, date('d/m/Y', strtotime($transaction['transaction_date'])));
        $sheet->setCellValue('C' . $row, $transaction['customer_name']);
        $sheet->setCellValue('D' . $row, $transaction['phone']);
        $sheet->setCellValue('E' . $row, $transaction['product_name'] ?: '-');
        $sheet->setCellValue('F' . $row, $transaction['quantity']);
        $sheet->setCellValue('G' . $row, $transaction['amount']);
        $sheet->setCellValue('H' . $row, $transaction['amount'] * $transaction['quantity']);
    }
    
    // Auto-size columns
    foreach (range('A', 'H') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    
    // Add borders to all cells
    $lastRow = count($transactions) + 1;
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
    $sheet->getStyle('F1:F' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    
    // Right align currency
    $sheet->getStyle('G1:H' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('G2:H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');
    
    // Create filename
    $filename = 'transactions_' . $business['business_name'] . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    
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


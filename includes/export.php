<?php
/**
 * includes/export.php
 * Helper export terpusat: CSV & XLSX untuk customers & transactions (Fase 4.1).
 * Dipakai oleh api/export-customers.php dan api/export-transactions.php.
 * Logika format baris diekstrak agar dapat di-unit-test (tests/ExportTest.php).
 */

/**
 * Header kolom export customer.
 * @return string[]
 */
function exportCustomersHeaders()
{
    return [
        'No',
        'Nama Pelanggan',
        'No HP',
        'Email',
        'Total Transaksi',
        'Total Belanja (Rp)',
        'Transaksi Terakhir',
        'Tanggal Registrasi',
    ];
}

/**
 * Format satu baris customer untuk export (CSV & XLSX).
 * @return array
 */
function formatCustomerExportRow($index, $customer)
{
    return [
        $index + 1,
        $customer['customer_name'],
        $customer['phone'],
        $customer['email'] ?: '-',
        $customer['total_transactions'],
        $customer['total_spent'],
        $customer['last_transaction'] ? date('d/m/Y', strtotime($customer['last_transaction'])) : '-',
        date('d/m/Y', strtotime($customer['created_at'])),
    ];
}

/**
 * Tulis CSV customer (dengan BOM UTF-8) ke target stream.
 * @param array $customers
 * @param string|resource $target Path file atau stream (default php://output).
 */
function writeCustomersCsv($customers, $target = 'php://output')
{
    $output = is_resource($target) ? $target : fopen($target, 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, exportCustomersHeaders());
    foreach ($customers as $index => $customer) {
        fputcsv($output, formatCustomerExportRow($index, $customer));
    }
    if (!is_resource($target)) {
        fclose($output);
    }
}

/**
 * Bangun Spreadsheet XLSX berisi data customers (dengan styling header).
 * @param string $businessName
 * @param array  $customers
 * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
 */
function buildCustomersSpreadsheet($businessName, $customers)
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $spreadsheet->getProperties()
        ->setCreator('Smart Marketing Agent')
        ->setLastModifiedBy('Smart Marketing Agent')
        ->setTitle('Data Pelanggan - ' . $businessName)
        ->setSubject('Data Pelanggan')
        ->setDescription('Data pelanggan yang diekspor dari Smart Marketing Agent')
        ->setKeywords('pelanggan, customer, data')
        ->setCategory('Data Export');

    $headers = exportCustomersHeaders();
    foreach ($headers as $colIndex => $header) {
        styleExportHeaderCell($sheet, chr(65 + $colIndex), $header);
    }

    foreach ($customers as $index => $customer) {
        $row = $index + 2;
        $values = formatCustomerExportRow($index, $customer);
        foreach ($values as $colIndex => $value) {
            $sheet->setCellValue(chr(65 + $colIndex) . $row, $value);
        }
    }

    $lastRow = count($customers) + 1;
    foreach (range('A', 'H') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $sheet->getStyle('A1:H' . $lastRow)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ]);
    $sheet->getStyle('A1:A' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E1:E' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F1:F' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('F2:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');

    return $spreadsheet;
}

/**
 * Header kolom export transaksi.
 * @return string[]
 */
function exportTransactionsHeaders()
{
    return [
        'No',
        'Tanggal Transaksi',
        'Nama Pelanggan',
        'No HP',
        'Nama Produk',
        'Jumlah',
        'Harga Satuan (Rp)',
        'Total (Rp)',
    ];
}

/**
 * Format satu baris transaksi untuk export.
 * @return array
 */
function formatTransactionExportRow($index, $transaction)
{
    return [
        $index + 1,
        date('d/m/Y', strtotime($transaction['transaction_date'])),
        $transaction['customer_name'],
        $transaction['phone'],
        $transaction['product_name'] ?: '-',
        $transaction['quantity'],
        $transaction['amount'],
        $transaction['amount'] * $transaction['quantity'],
    ];
}

/**
 * Tulis CSV transaksi (dengan BOM UTF-8) ke target stream.
 * @param array $transactions
 * @param string|resource $target Path file atau stream (default php://output).
 */
function writeTransactionsCsv($transactions, $target = 'php://output')
{
    $output = is_resource($target) ? $target : fopen($target, 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, exportTransactionsHeaders());
    foreach ($transactions as $index => $transaction) {
        fputcsv($output, formatTransactionExportRow($index, $transaction));
    }
    if (!is_resource($target)) {
        fclose($output);
    }
}

/**
 * Bangun Spreadsheet XLSX berisi data transaksi (dengan styling header).
 * @param string $businessName
 * @param array  $transactions
 * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
 */
function buildTransactionsSpreadsheet($businessName, $transactions)
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $spreadsheet->getProperties()
        ->setCreator('Smart Marketing Agent')
        ->setLastModifiedBy('Smart Marketing Agent')
        ->setTitle('Data Transaksi - ' . $businessName)
        ->setSubject('Data Transaksi')
        ->setDescription('Data transaksi yang diekspor dari Smart Marketing Agent')
        ->setKeywords('transaksi, transaction, data')
        ->setCategory('Data Export');

    $headers = exportTransactionsHeaders();
    foreach ($headers as $colIndex => $header) {
        styleExportHeaderCell($sheet, chr(65 + $colIndex), $header);
    }

    foreach ($transactions as $index => $transaction) {
        $row = $index + 2;
        $values = formatTransactionExportRow($index, $transaction);
        foreach ($values as $colIndex => $value) {
            $sheet->setCellValue(chr(65 + $colIndex) . $row, $value);
        }
    }

    $lastRow = count($transactions) + 1;
    foreach (range('A', 'H') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    $sheet->getStyle('A1:H' . $lastRow)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ]);
    $sheet->getStyle('A1:A' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F1:F' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('G1:H' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('G2:H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');

    return $spreadsheet;
}

/**
 * Styling header cell: teks putih tebal di latar biru, tengah-tengah.
 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
 * @param string $column 'A'..'H'
 * @param string $header
 */
function styleExportHeaderCell($sheet, $column, $header)
{
    $sheet->setCellValue($column . '1', $header);
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

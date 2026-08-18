<?php
/**
 * src/Export/TransactionsExporter.php
 * Slice Export (transactions): header, format baris, CSV (BOM UTF-8), XLSX.
 * Format DIKUNCI oleh tests/ExportTest.php (AGENTS.md §8 Export).
 * Data diambil via TransactionRepository::allWithCustomer() di API.
 */

namespace App\Export;

class TransactionsExporter
{
    public static function headers(): array
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

    public static function formatRow($index, $transaction)
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

    public static function writeCsv(array $transactions, $target = 'php://output'): void
    {
        $output = is_resource($target) ? $target : fopen($target, 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, self::headers());
        foreach ($transactions as $index => $transaction) {
            fputcsv($output, self::formatRow($index, $transaction));
        }
        if (!is_resource($target)) {
            fclose($output);
        }
    }

    public static function buildSpreadsheet($businessName, array $transactions)
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

        $headers = self::headers();
        foreach ($headers as $colIndex => $header) {
            self::styleHeaderCell($sheet, chr(65 + $colIndex), $header);
        }

        foreach ($transactions as $index => $transaction) {
            $row = $index + 2;
            $values = self::formatRow($index, $transaction);
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

    private static function styleHeaderCell($sheet, $column, $header)
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
}

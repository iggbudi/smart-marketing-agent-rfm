<?php
/**
 * src/Export/CustomersExporter.php
 * Slice Export (customers): header, format baris, CSV (BOM UTF-8), XLSX.
 * Format DIKUNCI oleh tests/ExportTest.php (AGENTS.md §8 Export).
 * Data diambil via CustomerRepository::withStats() di API.
 */

namespace App\Export;

class CustomersExporter
{
    public static function headers(): array
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

    public static function formatRow($index, $customer)
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

    public static function writeCsv(array $customers, $target = 'php://output'): void
    {
        $output = is_resource($target) ? $target : fopen($target, 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, self::headers());
        foreach ($customers as $index => $customer) {
            fputcsv($output, self::formatRow($index, $customer));
        }
        if (!is_resource($target)) {
            fclose($output);
        }
    }

    public static function buildSpreadsheet($businessName, array $customers)
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

        $headers = self::headers();
        foreach ($headers as $colIndex => $header) {
            self::styleHeaderCell($sheet, chr(65 + $colIndex), $header);
        }

        foreach ($customers as $index => $customer) {
            $row = $index + 2;
            $values = self::formatRow($index, $customer);
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

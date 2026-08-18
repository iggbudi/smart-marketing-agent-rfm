<?php
/**
 * tests/ExportTest.php
 * Unit test helper export (includes/export.php):
 * format baris, output CSV (BOM UTF-8 + header + data), dan round-trip XLSX.
 */

use PHPUnit\Framework\TestCase;

class ExportTest extends TestCase
{
    private function sampleCustomer($overrides = [])
    {
        return array_merge([
            'customer_name' => 'Andi Wijaya',
            'phone' => '08111111111',
            'email' => 'andi@email.com',
            'total_transactions' => '3',
            'total_spent' => '450000',
            'last_transaction' => '2026-08-01',
            'created_at' => '2026-01-15 10:00:00',
        ], $overrides);
    }

    private function sampleTransaction($overrides = [])
    {
        return array_merge([
            'customer_name' => 'Sari Dewi',
            'phone' => '08222222222',
            'product_name' => 'Batik Kawung',
            'quantity' => '2',
            'amount' => '150000',
            'transaction_date' => '2026-08-05',
        ], $overrides);
    }

    // ---- format baris ----

    public function testFormatCustomerRow()
    {
        $row = formatCustomerExportRow(0, $this->sampleCustomer());
        $this->assertSame([1, 'Andi Wijaya', '08111111111', 'andi@email.com', '3', '450000', '01/08/2026', '15/01/2026'], $row);

        // index 4 -> nomor 5; tanggal hilang -> '-'
        $row = formatCustomerExportRow(4, $this->sampleCustomer(['email' => '', 'last_transaction' => null]));
        $this->assertSame(5, $row[0]);
        $this->assertSame('-', $row[3]);
        $this->assertSame('-', $row[6]);
    }

    public function testFormatTransactionRow()
    {
        $row = formatTransactionExportRow(0, $this->sampleTransaction());
        // total = amount * quantity = 300000
        $this->assertSame([1, '05/08/2026', 'Sari Dewi', '08222222222', 'Batik Kawung', '2', '150000', 300000], $row);

        // produk kosong -> '-'
        $row = formatTransactionExportRow(0, $this->sampleTransaction(['product_name' => null]));
        $this->assertSame('-', $row[4]);
    }

    // ---- CSV ----

    public function testCustomersCsvHasBomHeadersAndRows()
    {
        $file = tempnam(sys_get_temp_dir(), 'csv_cust_');
        writeCustomersCsv([$this->sampleCustomer()], $file);

        $raw = file_get_contents($file);
        $this->assertStringStartsWith(chr(0xEF) . chr(0xBB) . chr(0xBF), $raw, 'CSV harus berawalan BOM UTF-8');

        // Baca baris: lewati BOM pada sel pertama
        $lines = explode("\n", trim($raw));
        $header = str_getcsv(substr($lines[0], 3)); // buang BOM
        $this->assertSame(exportCustomersHeaders(), $header);

        $data = str_getcsv($lines[1]);
        $this->assertSame('1', $data[0]);
        $this->assertSame('Andi Wijaya', $data[1]);
        $this->assertSame('01/08/2026', $data[6]);
        unlink($file);
    }

    public function testTransactionsCsvHasBomHeadersAndRows()
    {
        $file = tempnam(sys_get_temp_dir(), 'csv_tx_');
        writeTransactionsCsv([$this->sampleTransaction()], $file);

        $raw = file_get_contents($file);
        $this->assertStringStartsWith(chr(0xEF) . chr(0xBB) . chr(0xBF), $raw);

        $lines = explode("\n", trim($raw));
        $header = str_getcsv(substr($lines[0], 3));
        $this->assertSame(exportTransactionsHeaders(), $header);

        $data = str_getcsv($lines[1]);
        $this->assertSame('Batik Kawung', $data[4]);
        $this->assertSame('300000', $data[7]); // total
        unlink($file);
    }

    // ---- XLSX round-trip ----

    public function testCustomersSpreadsheetRoundTrip()
    {
        $spreadsheet = buildCustomersSpreadsheet('Batik Semarang', [$this->sampleCustomer()]);
        $this->assertInstanceOf(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, $spreadsheet);
        $this->assertSame('Data Pelanggan - Batik Semarang', $spreadsheet->getProperties()->getTitle());

        $sheet = $spreadsheet->getActiveSheet();
        // Header
        $this->assertSame('Nama Pelanggan', $sheet->getCell('B1')->getValue());
        $this->assertSame('Total Belanja (Rp)', $sheet->getCell('F1')->getValue());
        // Data baris 2
        $this->assertSame(1, $sheet->getCell('A2')->getValue());
        $this->assertSame('Andi Wijaya', $sheet->getCell('B2')->getValue());
        $this->assertEquals(450000, $sheet->getCell('F2')->getValue()); // numeric string di-cast otomatis
        $this->assertSame('01/08/2026', $sheet->getCell('G2')->getValue());
    }

    public function testTransactionsSpreadsheetRoundTrip()
    {
        $spreadsheet = buildTransactionsSpreadsheet('Batik Semarang', [$this->sampleTransaction()]);
        $this->assertSame('Data Transaksi - Batik Semarang', $spreadsheet->getProperties()->getTitle());

        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('Nama Produk', $sheet->getCell('E1')->getValue());
        $this->assertSame('Sari Dewi', $sheet->getCell('C2')->getValue());
        $this->assertEquals(150000, $sheet->getCell('G2')->getValue()); // numeric string di-cast otomatis
        $this->assertSame(300000, $sheet->getCell('H2')->getValue()); // total
    }

    public function testSpreadsheetCanBeWrittenAsXlsx()
    {
        $spreadsheet = buildCustomersSpreadsheet('Batik Semarang', [$this->sampleCustomer()]);
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tmp);
        $this->assertFileExists($tmp);
        $this->assertGreaterThan(1000, filesize($tmp), 'File XLSX tidak boleh kosong');

        // Baca ulang & verifikasi isi
        $loaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        $this->assertSame('Andi Wijaya', $loaded->getActiveSheet()->getCell('B2')->getValue());
        unlink($tmp);
    }
}

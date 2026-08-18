<?php
/**
 * tests/ImportTest.php
 * Slice Import: impor CSV -> upsert customer per business + transaksi,
 * normalisasi tanggal/nominal, laporan per-baris — DB test.
 */

use App\Import\SpreadsheetImporter;
use PHPUnit\Framework\TestCase;

class ImportTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createBusiness(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO businesses (name, owner_name, email, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['ImportBiz ' . uniqid(), 'Owner', 'import' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    public function testImportCsvCreatesCustomersAndTransactions()
    {
        $biz = $this->createBusiness();
        $tmp = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($tmp, implode("\n", [
            "nama,email,hp,tanggal,nominal,produk,qty",
            "Andi Wijaya,andi@a.id,0811,01/08/2026,150000,Batik Kawung,2",
            "Sari Dewi,sari@a.id,0822,2026-08-05,\"1.500.000,50\",Batik Parang,1",
            "Andi Wijaya,andi@a.id,0811,10/08/2026,200000,Batik Megamendung,1",
        ]));

        $importer = new SpreadsheetImporter($this->db);
        $result = $importer->import($biz, $tmp, 'data.csv');
        unlink($tmp);

        $this->assertSame(3, $result['processed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame([], $result['errors']);

        // 2 customer (Andi di-upsert, bukan duplikat), 3 transaksi
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM customers WHERE business_id = ?");
        $stmt->execute([$biz]);
        $this->assertSame(2, (int)$stmt->fetchColumn());

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM transactions WHERE business_id = ?");
        $stmt->execute([$biz]);
        $this->assertSame(3, (int)$stmt->fetchColumn());

        // normalisasi nominal Indonesia "1.500.000,50" -> 1500000.50
        // (subquery di-scope business_id — nama customer bisa sama di business lain)
        $stmt = $this->db->prepare(
            "SELECT amount, quantity FROM transactions WHERE business_id = ? AND customer_id = (SELECT id FROM customers WHERE customer_name = 'Sari Dewi' AND business_id = ?)"
        );
        $stmt->execute([$biz, $biz]);
        $sari = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta(1500000.50, (float)$sari['amount'], 0.001);
        $this->assertSame(1, (int)$sari['quantity']);
    }

    public function testImportReportsPerRowFailures()
    {
        $biz = $this->createBusiness();
        $tmp = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($tmp, implode("\n", [
            "nama,tanggal,nominal",
            "Andi,2026-08-01,150000",
            ",2026-08-02,100000",          // nama kosong -> failed
            "Budi,invalid-date,100000",    // tanggal invalid -> failed
            "Sari,2026-08-03,abc",         // nominal invalid -> failed
        ]));

        $result = (new SpreadsheetImporter($this->db))->import($biz, $tmp, 'data.csv');
        unlink($tmp);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(3, $result['failed']);
        $this->assertCount(3, $result['errors']);
        // baris 1 = header; baris 2 = Andi (ok); baris 3 = nama kosong -> error pertama
        $this->assertStringContainsString('Baris 3', $result['errors'][0]);
    }

    public function testImportRejectsUnknownHeader()
    {
        $biz = $this->createBusiness();
        $tmp = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($tmp, "foo,bar\n1,2\n");

        $result = (new SpreadsheetImporter($this->db))->import($biz, $tmp, 'data.csv');
        unlink($tmp);

        $this->assertSame(0, $result['processed']);
        $this->assertStringContainsString('Nama Pelanggan', $result['message']);
    }
}

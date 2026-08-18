<?php
/**
 * tests/TransactionRepositoryTest.php
 * Slice Transactions: CRUD, search+pagination, agregat — terhadap DB test.
 */

use App\Transactions\TransactionRepository;
use PHPUnit\Framework\TestCase;

class TransactionRepositoryTest extends TestCase
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
        $stmt->execute(['TxRepo Biz ' . uniqid(), 'Owner', 'txrepo' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    private function createCustomer(int $businessId, string $name, string $phone): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO customers (business_id, customer_name, phone, email, created_at) VALUES (?, ?, ?, NULL, NOW())"
        );
        $stmt->execute([$businessId, $name, $phone]);
        return (int)$this->db->lastInsertId();
    }

    public function testAddValidatesRequiredFields()
    {
        $repo = new TransactionRepository($this->db);
        $biz = $this->createBusiness();
        $cust = $this->createCustomer($biz, 'Andi', '0811');

        $id = $repo->add($biz, $cust, '2026-08-01', 150000, 'Batik Kawung', 2);
        $this->assertGreaterThan(0, $id);

        $this->expectException(\InvalidArgumentException::class);
        $repo->add($biz, $cust, '', 0, '', 1); // tanggal kosong & amount 0
    }

    public function testDeleteIsScopedByBusiness()
    {
        $bizA = $this->createBusiness();
        $bizB = $this->createBusiness();
        $repo = new TransactionRepository($this->db);
        $custA = $this->createCustomer($bizA, 'Sari', '0822');
        $custB = $this->createCustomer($bizB, 'Dewi', '0833');

        $txA = $repo->add($bizA, $custA, '2026-08-01', 100000, 'Batik', 1);
        $txB = $repo->add($bizB, $custB, '2026-08-02', 200000, 'Batik', 1);

        $this->assertFalse($repo->delete($bizA, $txB)); // bukan milik A
        $this->assertSame(1, $repo->count($bizA));
        $this->assertTrue($repo->delete($bizA, $txA));
        $this->assertSame(0, $repo->count($bizA));
    }

    public function testSearchAggregatesAndRecent()
    {
        $biz = $this->createBusiness();
        $repo = new TransactionRepository($this->db);
        $cust = $this->createCustomer($biz, 'Budi Santoso', '0812');

        $repo->add($biz, $cust, '2026-08-01', 150000, 'Batik Kawung', 1);
        $repo->add($biz, $cust, '2026-08-10', 200000, 'Batik Parang', 1);

        $this->assertSame(2, $repo->count($biz));
        $this->assertEqualsWithDelta(350000.0, $repo->totalRevenue($biz), 0.01);
        $this->assertSame(1, $repo->countActiveCustomers($biz));

        // search oleh nama customer
        $this->assertSame(2, $repo->countSearch($biz, 'santoso'));
        $rows = $repo->search($biz, 'santoso', 20, 0);
        $this->assertCount(2, $rows);
        $this->assertSame('Budi Santoso', $rows[0]['customer_name']);

        // pagination
        $this->assertCount(1, $repo->search($biz, '', 1, 0));

        // recent membatasi jumlah
        $this->assertCount(2, $repo->recent($biz, 5));
        $this->assertCount(1, $repo->recent($biz, 1));

        // allWithCustomer untuk export
        $all = $repo->allWithCustomer($biz);
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('phone', $all[0]);
    }
}

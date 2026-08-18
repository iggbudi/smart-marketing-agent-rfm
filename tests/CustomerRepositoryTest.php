<?php
/**
 * tests/CustomerRepositoryTest.php
 * Slice Customers: CRUD, search+pagination, agregat — terhadap DB test.
 */

use App\Customers\CustomerRepository;
use PHPUnit\Framework\TestCase;

class CustomerRepositoryTest extends TestCase
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
        $stmt->execute(['CustRepo Biz ' . uniqid(), 'Owner', 'repo' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    private function createCustomer(int $businessId, string $name, string $phone, string $email = ''): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO customers (business_id, customer_name, phone, email, created_at) VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$businessId, $name, $phone, $email !== '' ? $email : null]);
        return (int)$this->db->lastInsertId();
    }

    private function createTransaction(int $businessId, int $customerId, string $date, float $amount): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO transactions (business_id, customer_id, transaction_date, amount, product_name, quantity, created_at)
             VALUES (?, ?, ?, ?, NULL, 1, NOW())"
        );
        $stmt->execute([$businessId, $customerId, $date, $amount]);
    }

    public function testAddRejectsEmptyNameOrPhone()
    {
        $repo = new CustomerRepository($this->db);
        $biz = $this->createBusiness();

        $this->expectException(\InvalidArgumentException::class);
        $repo->add($biz, '  ', '0811', '');
    }

    public function testDeleteIsScopedByBusiness()
    {
        $bizA = $this->createBusiness();
        $bizB = $this->createBusiness();
        $repo = new CustomerRepository($this->db);

        $id = $repo->add($bizA, 'Andi', '0811', 'andi@test.local');
        $this->assertGreaterThan(0, $id);

        // delete dengan business lain harus gagal (data milik A tidak terhapus)
        $this->assertFalse($repo->delete($bizB, $id));
        $this->assertSame(1, $repo->count($bizA));

        $this->assertTrue($repo->delete($bizA, $id));
        $this->assertSame(0, $repo->count($bizA));
    }

    public function testSearchAndAggregates()
    {
        $biz = $this->createBusiness();
        $repo = new CustomerRepository($this->db);

        $c1 = $this->createCustomer($biz, 'Budi Santoso', '0812');
        $this->createCustomer($biz, 'Sari Dewi', '0822');
        $this->createTransaction($biz, $c1, '2026-08-01', 150000);
        $this->createTransaction($biz, $c1, '2026-08-10', 200000);

        $this->assertSame(2, $repo->count($biz));
        $this->assertSame(1, $repo->countActive($biz));
        $this->assertEqualsWithDelta(350000.0, $repo->totalSales($biz), 0.01);

        // search memfilter nama/HP/email
        $this->assertSame(1, $repo->countSearch($biz, 'santoso'));
        $rows = $repo->search($biz, 'santoso', 20, 0);
        $this->assertCount(1, $rows);
        $this->assertSame('Budi Santoso', $rows[0]['customer_name']);
        $this->assertSame(2, $rows[0]['total_transactions']); // COUNT() dikembalikan int oleh PDO/MariaDB
        $this->assertEqualsWithDelta(350000.0, (float)$rows[0]['total_spent'], 0.01);

        // pagination: perPage membatasi baris, total tetap
        $this->assertSame(2, $repo->countSearch($biz, ''));
        $page1 = $repo->search($biz, '', 1, 0);
        $this->assertCount(1, $page1);
    }

    public function testWithStatsAndDropdown()
    {
        $biz = $this->createBusiness();
        $repo = new CustomerRepository($this->db);
        $this->createCustomer($biz, 'Dewi', '0813', 'dewi@test.local');

        $rows = $repo->withStats($biz);
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('total_spent', $rows[0]);
        $this->assertArrayHasKey('last_transaction', $rows[0]);

        $drop = $repo->listForDropdown($biz);
        $this->assertSame('Dewi', $drop[0]['customer_name']);
        $this->assertArrayHasKey('phone', $drop[0]);
    }
}

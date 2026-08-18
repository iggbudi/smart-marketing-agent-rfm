<?php
/**
 * tests/BusinessAdminTest.php
 * Slice Admin\BusinessAdmin: daftar bisnis (+counts), daftar owner UMKM, statistik bisnis.
 */

use App\Admin\BusinessAdmin;
use App\Customers\CustomerRepository;
use App\Transactions\TransactionRepository;
use PHPUnit\Framework\TestCase;

class BusinessAdminTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    public function testAllDanStatsMenghitungBusiness(): void
    {
        $base = (new BusinessAdmin($this->db))->getStats();

        // Seed owner + business + customer + transaksi
        $ownerEmail = 'owner_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active, email_verified)
             VALUES (?, ?, ?, 'umkm_owner', 1, 1)"
        )->execute([$ownerEmail, password_hash('x', PASSWORD_DEFAULT), 'Owner Biz']);
        $ownerId = (int)$this->db->lastInsertId();

        $this->db->prepare(
            "INSERT INTO businesses (user_id, name, business_type, address, owner_name, email)
             VALUES (?, 'Toko Uji', 'Retail', 'Jl. Uji', 'Owner Biz', ?)"
        )->execute([$ownerId, 'biz_' . uniqid() . '@test.local']);
        $biz = (int)$this->db->lastInsertId();

        $cust = (new CustomerRepository($this->db))->add($biz, 'Andi', '0811', '');
        (new TransactionRepository($this->db))->add($biz, $cust, date('Y-m-d'), 100000, 'Produk', 1);

        try {
            $stats = (new BusinessAdmin($this->db))->getStats();
            $this->assertSame($base['total_businesses'] + 1, $stats['total_businesses']);
            $this->assertSame($base['active_businesses'] + 1, $stats['active_businesses']);
            $this->assertSame($base['total_customers'] + 1, $stats['total_customers']);
            $this->assertSame($base['total_transactions'] + 1, $stats['total_transactions']);

            $all = (new BusinessAdmin($this->db))->all();
            $row = array_values(array_filter($all, fn($b) => (int)$b['id'] === $biz));
            $this->assertCount(1, $row, 'daftar bisnis harus memuat yang baru di-seed');
            $this->assertSame('Toko Uji', $row[0]['name']);
            $this->assertSame('Owner Biz', $row[0]['owner_name']);
            $this->assertSame('1', (string)$row[0]['customer_count']);
            $this->assertSame('1', (string)$row[0]['transaction_count']);

            $owners = (new BusinessAdmin($this->db))->umkmOwners();
            $found = array_values(array_filter($owners, fn($o) => (int)$o['id'] === $ownerId));
            $this->assertCount(1, $found, 'owner UMKM harus ada di daftar pilihan');
        } finally {
            $this->db->prepare("DELETE FROM transactions WHERE customer_id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM businesses WHERE id = ?")->execute([$biz]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$ownerId]);
        }
    }
}

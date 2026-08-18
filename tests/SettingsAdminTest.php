<?php
/**
 * tests/SettingsAdminTest.php
 * Slice Admin\SettingsAdmin: pengaturan sistem, info sistem, & statistik platform (baca)
 * untuk admin/settings.php.
 */

use App\Admin\SettingsAdmin;
use App\Customers\CustomerRepository;
use App\Transactions\TransactionRepository;
use PHPUnit\Framework\TestCase;

class SettingsAdminTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    public function testSettingsRoundtrip(): void
    {
        $key = 'test_' . bin2hex(random_bytes(4));
        $this->db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")
            ->execute([$key, 'abc']);
        try {
            $settings = (new SettingsAdmin($this->db))->settings();
            $this->assertSame('abc', $settings[$key] ?? null, 'setting harus terbaca');
        } finally {
            $this->db->prepare("DELETE FROM system_settings WHERE setting_key = ?")->execute([$key]);
        }
    }

    public function testSystemInfoMenyediakanKunci(): void
    {
        $info = (new SettingsAdmin($this->db))->systemInfo();
        foreach (['php_version', 'server_software', 'database_version', 'memory_limit', 'upload_max_filesize'] as $k) {
            $this->assertArrayHasKey($k, $info, "system info harus punya $k");
        }
    }

    public function testPlatformStatsMenghitung(): void
    {
        $admin = new SettingsAdmin($this->db);
        $base = $admin->platformStats();

        $ownerEmail = 'owner_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare("INSERT INTO users (email, password, full_name, role, is_active, email_verified)
                            VALUES (?, ?, ?, 'umkm_owner', 1, 1)")
            ->execute([$ownerEmail, password_hash('x', PASSWORD_DEFAULT), 'Owner S']);
        $ownerId = (int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO businesses (user_id, name, owner_name, email) VALUES (?, 'Type Uji', 'Owner S', ?)")
            ->execute([$ownerId, 's_' . uniqid() . '@test.local']);
        $biz = (int)$this->db->lastInsertId();
        $cust = (new CustomerRepository($this->db))->add($biz, 'Andi', '0811', '');
        (new TransactionRepository($this->db))->add($biz, $cust, date('Y-m-d'), 50000, 'X', 1);

        try {
            $stats = $admin->platformStats();
            $this->assertSame($base['total_users'] + 1, (int)$stats['total_users']);
            $this->assertSame($base['total_businesses'] + 1, (int)$stats['total_businesses']);
            $this->assertSame($base['total_customers'] + 1, (int)$stats['total_customers']);
            $this->assertSame($base['total_transactions'] + 1, (int)$stats['total_transactions']);
            $this->assertArrayHasKey('database_size', $stats);
        } finally {
            $this->db->prepare("DELETE FROM transactions WHERE customer_id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM businesses WHERE id = ?")->execute([$biz]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$ownerId]);
        }
    }
}

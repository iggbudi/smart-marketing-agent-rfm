<?php
/**
 * tests/UserAdminTest.php
 * Slice Admin\UserAdmin: statistik user & daftar user+business (untuk admin/users.php).
 */

use App\Admin\UserAdmin;
use PHPUnit\Framework\TestCase;

class UserAdminTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    public function testGetStatsDanAll(): void
    {
        $base = (new UserAdmin($this->db))->getStats();

        // Seed 1 umkm_owner + 1 business miliknya
        $email = 'owner_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active, email_verified)
             VALUES (?, ?, ?, 'umkm_owner', 1, 1)"
        )->execute([$email, password_hash('x', PASSWORD_DEFAULT), 'Owner Uji']);
        $uid = (int)$this->db->lastInsertId();
        $this->db->prepare(
            "INSERT INTO businesses (user_id, name, owner_name, email) VALUES (?, 'Biz Uji', 'Owner Uji', ?)"
        )->execute([$uid, 'biz_' . uniqid() . '@test.local']);

        try {
            $stats = (new UserAdmin($this->db))->getStats();
            $this->assertSame($base['total_users'] + 1, $stats['total_users']);
            $this->assertSame($base['umkm_owners'] + 1, $stats['umkm_owners']);
            $this->assertSame($base['super_admins'], $stats['super_admins']);

            $users = (new UserAdmin($this->db))->all();
            $row = array_values(array_filter($users, fn($u) => (int)$u['id'] === $uid));
            $this->assertCount(1, $row, 'daftar user harus memuat user yang baru di-seed');
            $this->assertSame('Biz Uji', $row[0]['business_name'], 'business_name harus ter-join');
        } finally {
            $this->db->prepare("DELETE FROM businesses WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
        }
    }
}

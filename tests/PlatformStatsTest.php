<?php
/**
 * tests/PlatformStatsTest.php
 * Slice Admin\PlatformStats: KPI platform (lintas-business), API usage, aktivitas, pertumbuhan bisnis.
 */

use App\Admin\PlatformStats;
use App\Customers\CustomerRepository;
use App\Transactions\TransactionRepository;
use PHPUnit\Framework\TestCase;

class PlatformStatsTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createOwner(): int
    {
        $email = 'owner_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active, email_verified)
             VALUES (?, ?, ?, 'umkm_owner', 1, 1)"
        )->execute([$email, password_hash('x', PASSWORD_DEFAULT), 'Owner Uji']);
        return (int)$this->db->lastInsertId();
    }

    public function testKpisMenghitungPlatform(): void
    {
        $base = (new PlatformStats($this->db))->getKpis();

        // Seed 1 owner + 1 business + 1 customer + 1 transaksi
        $owner = $this->createOwner();
        $this->db->prepare(
            "INSERT INTO businesses (user_id, name, owner_name, email) VALUES (?, 'Biz Uji', 'Owner Uji', ?)"
        )->execute([$owner, 'biz_' . uniqid() . '@test.local']);
        $biz = (int)$this->db->lastInsertId();

        $cust = (new CustomerRepository($this->db))->add($biz, 'Andi', '081100', '');
        (new TransactionRepository($this->db))->add($biz, $cust, date('Y-m-d'), 150000, 'Batik', 1);

        try {
            $kpis = (new PlatformStats($this->db))->getKpis();
            $this->assertSame($base['total_umkm'] + 1, $kpis['total_umkm']);
            $this->assertSame($base['total_businesses'] + 1, $kpis['total_businesses']);
            $this->assertSame($base['total_customers'] + 1, $kpis['total_customers']);
            $this->assertSame($base['total_transactions'] + 1, $kpis['total_transactions']);
            // revenue = SUM(amount), perilaku lama (abaikan qty)
            $this->assertEqualsWithDelta($base['total_revenue'] + 150000.0, (float)$kpis['total_revenue'], 0.01);

            // Pertumbuhan bisnis mencakup hari ini
            $growth = (new PlatformStats($this->db))->getBusinessGrowth(7);
            $today = array_filter($growth, fn($r) => $r['date'] === date('Y-m-d'));
            $this->assertNotEmpty($today, 'business growth harus memuat hari ini');

            // Aktivitas (struktur)
            $act = (new PlatformStats($this->db))->getRecentActivities(1);
            $this->assertIsArray($act);
        } finally {
            $this->db->prepare("DELETE FROM transactions WHERE customer_id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM businesses WHERE id = ?")->execute([$biz]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$owner]);
        }
    }

    public function testApiUsageTodayMengelompokkanBerdasarkanTipe(): void
    {
        $before = (new PlatformStats($this->db))->getApiUsageToday();
        $this->db->prepare(
            "INSERT INTO api_usage_logs (business_id, api_type, endpoint, status, created_at)
             VALUES (NULL, 'openai', '/x', 'success', NOW())"
        )->execute();
        $this->db->prepare(
            "INSERT INTO api_usage_logs (business_id, api_type, endpoint, status, created_at)
             VALUES (NULL, 'openai', '/y', 'success', NOW())"
        )->execute();
        try {
            $usage = (new PlatformStats($this->db))->getApiUsageToday();
            $this->assertGreaterThanOrEqual(($before['openai'] ?? 0) + 2, (int)($usage['openai'] ?? 0));
        } finally {
            $this->db->exec("DELETE FROM api_usage_logs WHERE api_type = 'openai' AND endpoint IN ('/x','/y')");
        }
    }
}

<?php
/**
 * tests/AnalyticsAdminTest.php
 * Slice Admin\AnalyticsAdmin: agregat platform analytics (baca) untuk admin/analytics.php.
 */

use App\Admin\AnalyticsAdmin;
use App\Customers\CustomerRepository;
use App\Transactions\TransactionRepository;
use PHPUnit\Framework\TestCase;

class AnalyticsAdminTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    public function testPlatformDanBusinessActivityMenghitung(): void
    {
        $admin = new AnalyticsAdmin($this->db);
        $base = $admin->platform();

        $ownerEmail = 'owner_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active, email_verified)
             VALUES (?, ?, ?, 'umkm_owner', 1, 1)"
        )->execute([$ownerEmail, password_hash('x', PASSWORD_DEFAULT), 'Owner An']);
        $ownerId = (int)$this->db->lastInsertId();

        $this->db->prepare(
            "INSERT INTO businesses (user_id, name, business_type, address, owner_name, email)
             VALUES (?, 'Analisa Uji', 'Retail', 'Jl. Uji', 'Owner An', ?)"
        )->execute([$ownerId, 'an_' . uniqid() . '@test.local']);
        $biz = (int)$this->db->lastInsertId();

        $cust = (new CustomerRepository($this->db))->add($biz, 'Andi', '0811', '');
        // Amount sangat besar agar bisnis ini pasti masuk top-10 businessActivity (LIMIT 10 by revenue)
        (new TransactionRepository($this->db))->add($biz, $cust, date('Y-m-d'), 1000000000, 'Produk', 1);

        try {
            $p = $admin->platform();
            $this->assertSame($base['total_users'] + 1, (int)$p['total_users']);
            $this->assertSame($base['total_businesses'] + 1, (int)$p['total_businesses']);
            $this->assertSame($base['total_customers'] + 1, (int)$p['total_customers']);
            $this->assertSame($base['total_transactions'] + 1, (int)$p['total_transactions']);
            $this->assertEqualsWithDelta($base['total_revenue'] + 1000000000.0, (float)$p['total_revenue'], 0.01);
            $this->assertArrayHasKey('active_sessions', $p);

            $act = $admin->businessActivity(10);
            $row = array_values(array_filter($act, fn($b) => $b['business_name'] === 'Analisa Uji'));
            $this->assertCount(1, $row, 'business activity memuat bisnis yang di-seed');
            $this->assertSame('1', (string)$row[0]['customers']);
            $this->assertSame('1', (string)$row[0]['transactions']);

            $growth = $admin->userGrowthRate();
            $this->assertIsFloat($growth, 'growth rate harus float');
        } finally {
            $this->db->prepare("DELETE FROM transactions WHERE customer_id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM businesses WHERE id = ?")->execute([$biz]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$ownerId]);
        }
    }

    public function testRfmDanTrendsMengembalikanStruktur(): void
    {
        $admin = new AnalyticsAdmin($this->db);
        $this->assertIsArray($admin->rfmSegments(), 'rfm segments harus array');
        $this->assertIsArray($admin->userTrends(30), 'user trends harus array');
        $this->assertIsArray($admin->transactionTrends(30), 'transaction trends harus array');
        $this->assertIsArray($admin->apiUsage(7, 10), 'api usage harus array');
        $this->assertIsArray($admin->recentActivities(5), 'recent activities harus array');
    }

    public function testRfmSegmentsMengelompokkan(): void
    {
        $admin = new AnalyticsAdmin($this->db);
        $ownerEmail = 'owner_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active, email_verified)
             VALUES (?, ?, ?, 'umkm_owner', 1, 1)"
        )->execute([$ownerEmail, password_hash('x', PASSWORD_DEFAULT), 'Owner R']);
        $ownerId = (int)$this->db->lastInsertId();
        $this->db->prepare(
            "INSERT INTO businesses (user_id, name, owner_name, email) VALUES (?, 'UBiz', 'Owner R', ?)"
        )->execute([$ownerId, 'r_' . uniqid() . '@test.local']);
        $biz = (int)$this->db->lastInsertId();
        $cust = (new CustomerRepository($this->db))->add($biz, 'Andi', '0811', '');
        $before = (int)((new AnalyticsAdmin($this->db))->rfmSegments()['Champions'] ?? 0);
        $this->db->prepare(
            "INSERT INTO rfm_analysis (business_id, customer_id, recency_score, frequency_score, monetary_score, rfm_segment, analysis_date, created_at)
             VALUES (?, ?, 5, 5, 5, 'Champions', CURDATE(), NOW())"
        )->execute([$biz, $cust]);
        try {
            $seg = new AnalyticsAdmin($this->db);
            $this->assertSame($before + 1, (int)($seg->rfmSegments()['Champions'] ?? 0), 'rfm segment Champions harus bertambah 1');
        } finally {
            $this->db->prepare("DELETE FROM rfm_analysis WHERE business_id = ?")->execute([$biz]);
            $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM businesses WHERE id = ?")->execute([$biz]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$ownerId]);
        }
    }
}

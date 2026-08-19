<?php
/**
 * tests/DashboardStatsTest.php
 * Slice Dashboard: agregat kartu, transaksi terbaru, distribusi RFM, tren revenue.
 */

use App\Customers\CustomerRepository;
use App\Dashboard\DashboardStats;
use App\Transactions\TransactionRepository;
use PHPUnit\Framework\TestCase;

class DashboardStatsTest extends TestCase
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
        $stmt->execute(['DashBiz ' . uniqid(), 'Owner', 'dash' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    public function testStatsRecentRfmAndTrend()
    {
        $biz = $this->createBusiness();
        $dash = new DashboardStats($this->db);

        // Seed: 2 customer, 2 transaksi, 1 baris rfm_analysis
        $custRepo = new CustomerRepository($this->db);
        $c1 = $custRepo->add($biz, 'Andi', '0811', '');
        $c2 = $custRepo->add($biz, 'Sari', '0822', '');
        $txRepo = new TransactionRepository($this->db);
        $txRepo->add($biz, $c1, date('Y-m-d'), 150000, 'Batik Kawung', 1);
        $txRepo->add($biz, $c2, date('Y-m-d', strtotime('-3 days')), 200000, 'Batik Parang', 2);

        $stmt = $this->db->prepare(
            "INSERT INTO rfm_analysis (business_id, customer_id, recency_score, frequency_score, monetary_score, rfm_segment, analysis_date, created_at)
             VALUES (?, ?, 5, 5, 5, 'Champions', CURDATE(), NOW())"
        );
        $stmt->execute([$biz, $c1]);

        $stats = $dash->getStats($biz);
        $this->assertSame(2, $stats['total_customers']);
        $this->assertSame(2, $stats['total_transactions']);
        // Perilaku lama (dashboard.php & transactions.php): revenue = SUM(amount) Harga SATUAN,
        // mengabaikan qty (bukan amount*qty) — dipertahankan apa adanya.
        $this->assertEqualsWithDelta(350000.0, (float)$stats['total_revenue'], 0.01);

        $recent = $dash->getRecentTransactions($biz, 1);
        $this->assertCount(1, $recent);
        $this->assertArrayHasKey('customer_name', $recent[0]);

        // COUNT() dikembalikan int oleh PDO/MariaDB (sama seperti CustomerRepositoryTest)
        $rfm = $dash->getRfmData($biz);
        $this->assertSame(['Champions' => 1], $rfm);

        $trend = $dash->getRevenueTrend($biz, 6);
        $this->assertNotEmpty($trend);
        $this->assertSame(date('Y-m'), $trend[0]['month']);
        $this->assertEqualsWithDelta(350000.0, (float)$trend[0]['revenue'], 0.01);
    }

    public function testEmptyBusinessHasZeroStats()
    {
        $biz = $this->createBusiness();
        $stats = (new DashboardStats($this->db))->getStats($biz);
        $this->assertSame(0, $stats['total_customers']);
        $this->assertSame(0, $stats['total_transactions']);
        $this->assertEqualsWithDelta(0.0, (float)$stats['total_revenue'], 0.01);

        $this->assertSame([], (new DashboardStats($this->db))->getRfmData($biz));
        $this->assertSame([], (new DashboardStats($this->db))->getRevenueTrend($biz, 6));
    }

    public function testGetAttentionCountMenghitungSegmentBerisiko(): void
    {
        $biz = $this->createBusiness();
        $custRepo = new CustomerRepository($this->db);
        $c1 = $custRepo->add($biz, 'Andi', '0811', '');
        $c2 = $custRepo->add($biz, 'Sari', '0822', '');
        $c3 = $custRepo->add($biz, 'Budi', '0833', '');

        $seed = function (int $cust, string $segment) use ($biz) {
            $stmt = $this->db->prepare(
                "INSERT INTO rfm_analysis (business_id, customer_id, recency_score, frequency_score, monetary_score, rfm_segment, analysis_date, created_at)
                 VALUES (?, ?, 3, 2, 2, ?, CURDATE(), NOW())"
            );
            $stmt->execute([$biz, $cust, $segment]);
        };
        $seed($c1, 'Champions');
        $seed($c2, 'At Risk');
        $seed($c3, 'About to Sleep');

        $count = (new DashboardStats($this->db))->getAttentionCount($biz);
        $this->assertSame(2, $count, 'hanya segmen berisiko (At Risk & About to Sleep) yang dihitung');
    }
}

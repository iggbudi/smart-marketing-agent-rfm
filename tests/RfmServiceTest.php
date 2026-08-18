<?php
/**
 * tests/RfmServiceTest.php
 * Slice RFM: rekalkulasi, first-run, hasil, ringkasan segmen — DB test.
 * Segmentasi harus cocok dgn \App\Rfm::segmentFromScores() (single source of truth).
 */

use App\Rfm\RfmService;
use PHPUnit\Framework\TestCase;

class RfmServiceTest extends TestCase
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
        $stmt->execute(['RfmBiz ' . uniqid(), 'Owner', 'rfm' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    private function createCustomer(int $businessId, string $name): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO customers (business_id, customer_name, phone, email, created_at) VALUES (?, ?, '081', NULL, NOW())"
        );
        $stmt->execute([$businessId, $name]);
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

    public function testEnsureCalculatedOnlyOnFirstRun()
    {
        $biz = $this->createBusiness();
        $svc = new RfmService($this->db);
        $c1 = $this->createCustomer($biz, 'Andi');
        $this->createTransaction($biz, $c1, date('Y-m-d'), 600000);

        $this->assertTrue($svc->ensureCalculated($biz), 'first-run harus menghitung');
        $this->assertFalse($svc->ensureCalculated($biz), 'run kedua tidak boleh menghitung ulang');
        $this->assertSame(1, $svc->count($biz));
    }

    public function testRecalculateProducesSegmentConsistentWithPureLogic()
    {
        $biz = $this->createBusiness();
        $svc = new RfmService($this->db);

        // Champions: belanja baru + sering (>=7 transaksi -> frequency 4) + besar
        $c1 = $this->createCustomer($biz, 'Andi');
        for ($i = 0; $i < 7; $i++) {
            $this->createTransaction($biz, $c1, date('Y-m-d', strtotime("-$i days")), 600000);
        }

        // At Risk: transaksi terakhir 400 hari lalu
        $c2 = $this->createCustomer($biz, 'Budi');
        $this->createTransaction($biz, $c2, date('Y-m-d', strtotime('-400 days')), 150000);

        $svc->recalculate($biz);

        $rows = $svc->results($biz);
        $this->assertCount(2, $rows);

        $byName = [];
        foreach ($rows as $r) {
            $byName[$r['name']] = $r;
        }
        $this->assertSame('Champions', $byName['Andi']['segment']);
        $this->assertSame('At Risk', $byName['Budi']['segment']);

        // konsistensi: skor tersimpan -> segmentFromScores() harus sama
        foreach ($rows as $r) {
            $expected = \App\Rfm::segmentFromScores((int)$r['recency_score'], (int)$r['frequency_score'], (int)$r['monetary_score']);
            $this->assertSame($expected, $r['segment']);
        }

        // ringkasan segmen
        $summary = $svc->segmentSummary($biz);
        $this->assertSame(1, (int)$summary['Champions']);
        $this->assertSame(1, (int)$summary['At Risk']);
    }

    public function testRecalculateIsScopedByBusiness()
    {
        $bizA = $this->createBusiness();
        $bizB = $this->createBusiness();
        $svc = new RfmService($this->db);

        $cA = $this->createCustomer($bizA, 'Andi');
        $this->createTransaction($bizA, $cA, date('Y-m-d'), 600000);
        $cB = $this->createCustomer($bizB, 'Sari');
        $this->createTransaction($bizB, $cB, date('Y-m-d'), 700000);

        $svc->recalculate($bizA);
        $this->assertSame(1, $svc->count($bizA));
        $this->assertSame(0, $svc->count($bizB), 'business lain tidak boleh terhitung');
    }
}

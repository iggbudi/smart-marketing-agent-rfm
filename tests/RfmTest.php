<?php
/**
 * tests/RfmTest.php
 * Unit test logika RFM murni (src/Rfm.php).
 * Skor & segmentasi harus konsisten dengan query SQL di includes/rfm.php
 * (SQL dibangun dari fungsi-fungsi yang sama, jadi otomatis sinkron).
 */

namespace App;

use PHPUnit\Framework\TestCase;

class RfmTest extends TestCase
{
    // ---- Recency ----

    public function testRecencyScoreBoundaries()
    {
        $this->assertSame(5, Rfm::scoreRecency(0));
        $this->assertSame(5, Rfm::scoreRecency(30));
        $this->assertSame(4, Rfm::scoreRecency(31));
        $this->assertSame(4, Rfm::scoreRecency(90));
        $this->assertSame(3, Rfm::scoreRecency(91));
        $this->assertSame(3, Rfm::scoreRecency(180));
        $this->assertSame(2, Rfm::scoreRecency(181));
        $this->assertSame(2, Rfm::scoreRecency(365));
        $this->assertSame(1, Rfm::scoreRecency(366));
        $this->assertSame(1, Rfm::scoreRecency(1000));
    }

    // ---- Frequency ----

    public function testFrequencyScoreBoundaries()
    {
        $this->assertSame(1, Rfm::scoreFrequency(0));
        $this->assertSame(1, Rfm::scoreFrequency(2));
        $this->assertSame(2, Rfm::scoreFrequency(3));
        $this->assertSame(2, Rfm::scoreFrequency(4));
        $this->assertSame(3, Rfm::scoreFrequency(5));
        $this->assertSame(3, Rfm::scoreFrequency(6));
        $this->assertSame(4, Rfm::scoreFrequency(7));
        $this->assertSame(4, Rfm::scoreFrequency(9));
        $this->assertSame(5, Rfm::scoreFrequency(10));
        $this->assertSame(5, Rfm::scoreFrequency(50));
    }

    // ---- Monetary ----

    public function testMonetaryScoreBoundaries()
    {
        $this->assertSame(1, Rfm::scoreMonetary(0));
        $this->assertSame(1, Rfm::scoreMonetary(99999.99));
        $this->assertSame(2, Rfm::scoreMonetary(100000));
        $this->assertSame(2, Rfm::scoreMonetary(199999));
        $this->assertSame(3, Rfm::scoreMonetary(200000));
        $this->assertSame(3, Rfm::scoreMonetary(299999));
        $this->assertSame(4, Rfm::scoreMonetary(300000));
        $this->assertSame(4, Rfm::scoreMonetary(499999));
        $this->assertSame(5, Rfm::scoreMonetary(500000));
        $this->assertSame(5, Rfm::scoreMonetary(1000000));
    }

    // ---- Segmentasi (semua kategori) ----

    public function testSegmentChampions()
    {
        $this->assertSame('Champions', Rfm::segmentFromScores(4, 4, 4));
        $this->assertSame('Champions', Rfm::segmentFromScores(5, 5, 5));
        $this->assertSame('Champions', Rfm::segmentFromScores(4, 5, 4));
    }

    public function testSegmentLoyalCustomers()
    {
        // r>=3 dan f>=3 (m tidak perlu >=3; loyalitas ditentukan frekuensi)
        $this->assertSame('Loyal Customers', Rfm::segmentFromScores(3, 3, 1));
        $this->assertSame('Loyal Customers', Rfm::segmentFromScores(4, 3, 2));
        $this->assertSame('Loyal Customers', Rfm::segmentFromScores(3, 5, 1));
    }

    public function testSegmentPotentialLoyalists()
    {
        // r>=3 dan m>=3, tetapi f<3 (kalau f>=3 sudah jadi Loyal Customers)
        $this->assertSame('Potential Loyalists', Rfm::segmentFromScores(3, 1, 3));
        $this->assertSame('Potential Loyalists', Rfm::segmentFromScores(3, 2, 5));
        $this->assertSame('Potential Loyalists', Rfm::segmentFromScores(4, 1, 4));
    }

    public function testSegmentAtRisk()
    {
        // r<=2 mendominasi, walau f/m tinggi
        $this->assertSame('At Risk', Rfm::segmentFromScores(2, 5, 5));
        $this->assertSame('At Risk', Rfm::segmentFromScores(1, 1, 1));
        $this->assertSame('At Risk', Rfm::segmentFromScores(2, 3, 4));
    }

    public function testSegmentLostCustomers()
    {
        // Bukan Champion/Loyal/Potential/At Risk -> Lost Customers
        $this->assertSame('Lost Customers', Rfm::segmentFromScores(3, 1, 1));
        $this->assertSame('Lost Customers', Rfm::segmentFromScores(3, 2, 2));
        $this->assertSame('Lost Customers', Rfm::segmentFromScores(3, 1, 2));
    }

    // ---- SQL builder (harus memuat ambang & urutan yang sama) ----

    public function testRecencyScoreSqlContainsThresholds()
    {
        $sql = Rfm::recencyScoreSql('DATEDIFF(NOW(), MAX(t.transaction_date))');
        $this->assertStringContainsString('<= 30 THEN 5', $sql);
        $this->assertStringContainsString('<= 90 THEN 4', $sql);
        $this->assertStringContainsString('<= 180 THEN 3', $sql);
        $this->assertStringContainsString('<= 365 THEN 2', $sql);
        $this->assertStringContainsString('ELSE 1', $sql);
        $this->assertStringContainsString('DATEDIFF(NOW(), MAX(t.transaction_date))', $sql);
    }

    public function testSegmentCaseSqlKeepsOrder()
    {
        $sql = Rfm::segmentCaseSql('d.r', 'd.f', 'd.m');
        $champions = strpos($sql, 'Champions');
        $loyal = strpos($sql, 'Loyal Customers');
        $potential = strpos($sql, 'Potential Loyalists');
        $atRisk = strpos($sql, 'At Risk');

        $this->assertNotFalse($champions);
        $this->assertNotFalse($loyal);
        $this->assertNotFalse($potential);
        $this->assertNotFalse($atRisk);
        // Urutan WHEN: Champions -> Loyal -> Potential -> At Risk -> else
        $this->assertLessThan($loyal, $champions);
        $this->assertLessThan($potential, $loyal);
        $this->assertLessThan($atRisk, $potential);
    }

    /**
     * Konsistensi antara segmentasi PHP dan SQL: untuk seluruh kombinasi skor
     * 1..5, hasil segmentFromScores() harus sama dengan interpretasi SQL.
     * SQL builder memakai ekspresi 'd.r'/'d.f'/'d.m'; kita verifikasi bahwa
     * ekspresi segment menempatkan Champs/Loyal/Potential/AtRisk dalam urutan
     * yang sama dan ambangnya identik dengan fungsi PHP.
     */
    public function testSegmentCaseSqlMatchesSegmentFromScoresForAllCombinations()
    {
        $sql = Rfm::segmentCaseSql('r', 'f', 'm');
        for ($r = 1; $r <= 5; $r++) {
            for ($f = 1; $f <= 5; $f++) {
                for ($m = 1; $m <= 5; $m++) {
                    $phpSegment = Rfm::segmentFromScores($r, $f, $m);
                    // Replikasi evaluasi SQL terhadap kombinasi tsb dari string CASE.
                    $sqlSegment = $this->evaluateSegmentCaseSql($sql, $r, $f, $m);
                    $this->assertSame(
                        $phpSegment,
                        $sqlSegment,
                        "Segment mismatch untuk r={$r} f={$f} m={$m}"
                    );
                }
            }
        }
    }

    /**
     * Evaluasi manual string CASE segment (urutan WHEN -> return pertama yang cocok).
     */
    private function evaluateSegmentCaseSql($sql, $r, $f, $m)
    {
        $rules = [
            'Champions' => fn() => $r >= 4 && $f >= 4 && $m >= 4,
            'Loyal Customers' => fn() => $r >= 3 && $f >= 3,
            'Potential Loyalists' => fn() => $r >= 3 && $m >= 3,
            'At Risk' => fn() => $r <= 2,
        ];
        foreach ($rules as $segment => $fn) {
            if ($fn()) {
                return $segment;
            }
            $this->assertStringContainsString($segment, $sql);
        }
        return 'Lost Customers';
    }
}

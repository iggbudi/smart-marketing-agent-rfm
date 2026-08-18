<?php
/**
 * src/Rfm.php
 * Logika RFM (Recency, Frequency, Monetary) murni & terpusat.
 *
 * Single source of truth untuk ambang skor & segmentasi.
 * SQL di src/Rfm/RfmService.php DIBANGUN dari fungsi-fungsi ini agar tidak ada
 * duplikasi logika antara PHP (unit test) dan SQL (query).
 */

namespace App;

class Rfm
{
    /**
     * Skor recency berdasarkan umur transaksi terakhir (hari).
     * Sama dengan ambang DATEDIFF pada query RfmService::recalculate().
     */
    public static function scoreRecency(int $daysSinceLastPurchase): int
    {
        if ($daysSinceLastPurchase <= 30) {
            return 5;
        }
        if ($daysSinceLastPurchase <= 90) {
            return 4;
        }
        if ($daysSinceLastPurchase <= 180) {
            return 3;
        }
        if ($daysSinceLastPurchase <= 365) {
            return 2;
        }
        return 1;
    }

    /**
     * Skor frequency berdasarkan jumlah transaksi.
     */
    public static function scoreFrequency(int $transactionCount): int
    {
        if ($transactionCount >= 10) {
            return 5;
        }
        if ($transactionCount >= 7) {
            return 4;
        }
        if ($transactionCount >= 5) {
            return 3;
        }
        if ($transactionCount >= 3) {
            return 2;
        }
        return 1;
    }

    /**
     * Skor monetary berdasarkan rata-rata nominal transaksi (Rp).
     */
    public static function scoreMonetary(float $averageAmount): int
    {
        if ($averageAmount >= 500000) {
            return 5;
        }
        if ($averageAmount >= 300000) {
            return 4;
        }
        if ($averageAmount >= 200000) {
            return 3;
        }
        if ($averageAmount >= 100000) {
            return 2;
        }
        return 1;
    }

    /**
     * Segmentasi dari skor R/F/M.
     * Urutan CASE penting: kondisi paling ketat dievaluasi lebih dulu
     * (sama dengan urutan WHEN pada query SQL).
     */
    public static function segmentFromScores(int $recency, int $frequency, int $monetary): string
    {
        if ($recency >= 4 && $frequency >= 4 && $monetary >= 4) {
            return 'Champions';
        }
        if ($recency >= 3 && $frequency >= 3) {
            return 'Loyal Customers';
        }
        if ($recency >= 3 && $monetary >= 3) {
            return 'Potential Loyalists';
        }
        if ($recency <= 2) {
            return 'At Risk';
        }
        return 'Lost Customers';
    }

    /**
     * Ekspresi SQL CASE untuk skor recency dari satu ekspresi DATEDIFF.
     * Contoh pemakaian: rfmScoreCaseSql('DATEDIFF(NOW(), MAX(t.transaction_date))')
     */
    public static function recencyScoreSql(string $dateDiffExpr): string
    {
        return "CASE\n"
            . "    WHEN {$dateDiffExpr} <= 30 THEN 5\n"
            . "    WHEN {$dateDiffExpr} <= 90 THEN 4\n"
            . "    WHEN {$dateDiffExpr} <= 180 THEN 3\n"
            . "    WHEN {$dateDiffExpr} <= 365 THEN 2\n"
            . "    ELSE 1\n"
            . "END";
    }

    /**
     * Ekspresi SQL CASE untuk skor frequency dari ekspresi COUNT.
     */
    public static function frequencyScoreSql(string $countExpr): string
    {
        return "CASE\n"
            . "    WHEN {$countExpr} >= 10 THEN 5\n"
            . "    WHEN {$countExpr} >= 7 THEN 4\n"
            . "    WHEN {$countExpr} >= 5 THEN 3\n"
            . "    WHEN {$countExpr} >= 3 THEN 2\n"
            . "    ELSE 1\n"
            . "END";
    }

    /**
     * Ekspresi SQL CASE untuk skor monetary dari ekspresi AVG.
     */
    public static function monetaryScoreSql(string $avgExpr): string
    {
        return "CASE\n"
            . "    WHEN {$avgExpr} >= 500000 THEN 5\n"
            . "    WHEN {$avgExpr} >= 300000 THEN 4\n"
            . "    WHEN {$avgExpr} >= 200000 THEN 3\n"
            . "    WHEN {$avgExpr} >= 100000 THEN 2\n"
            . "    ELSE 1\n"
            . "END";
    }

    /**
     * Ekspresi SQL CASE untuk segmentasi dari skor r/f/m (string SQL).
     * Urutan WHEN harus identik dengan segmentFromScores().
     */
    public static function segmentCaseSql(string $rExpr, string $fExpr, string $mExpr): string
    {
        return "CASE\n"
            . "    WHEN {$rExpr} >= 4 AND {$fExpr} >= 4 AND {$mExpr} >= 4 THEN 'Champions'\n"
            . "    WHEN {$rExpr} >= 3 AND {$fExpr} >= 3 THEN 'Loyal Customers'\n"
            . "    WHEN {$rExpr} >= 3 AND {$mExpr} >= 3 THEN 'Potential Loyalists'\n"
            . "    WHEN {$rExpr} <= 2 THEN 'At Risk'\n"
            . "    ELSE 'Lost Customers'\n"
            . "END";
    }
}

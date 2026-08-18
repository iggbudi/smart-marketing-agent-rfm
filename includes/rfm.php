<?php
/**
 * includes/rfm.php
 * Perhitungan RFM (Recency, Frequency, Monetary) terpusat.
 * Dipakai oleh analysis.php (dan dapat dipanggil dari cron/CLI).
 *
 * Skor & segmentasi dibangun dari App\Rfm (src/Rfm.php) agar logika PHP
 * (unit test) dan SQL tidak terduplikasi. Lihat tests/RfmTest.php.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Rfm;

/**
 * Hitung ulang RFM untuk satu business.
 *
 * Menghapus lalu mengisi ulang baris rfm_analysis untuk business tsb.
 * - Skor R/F/M dihitung sekali di subquery (menghilangkan duplikasi CASE).
 * - Segmentasi diturunkan dari skor di query luar.
 * - Mengisi last_purchase_date, total_transactions, total_spent.
 *
 * @param \PDO  $db
 * @param int   $businessId
 * @param int|null $userId  Untuk pencatatan aktivitas (opsional).
 * @return true
 */
function recalculateRFM(\PDO $db, $businessId, $userId = null)
{
    $stmt = $db->prepare("DELETE FROM rfm_analysis WHERE business_id = ?");
    $stmt->execute([$businessId]);

    $rExpr = Rfm::recencyScoreSql('DATEDIFF(NOW(), MAX(t.transaction_date))');
    $fExpr = Rfm::frequencyScoreSql('COUNT(t.id)');
    $mExpr = Rfm::monetaryScoreSql('AVG(t.amount)');
    $segmentCase = Rfm::segmentCaseSql('d.r', 'd.f', 'd.m');

    $query = "INSERT INTO rfm_analysis
        (business_id, customer_id, recency_score, frequency_score, monetary_score,
         rfm_segment, last_purchase_date, total_transactions, total_spent, analysis_date, created_at)
        SELECT d.business_id, d.customer_id, d.r, d.f, d.m,
        {$segmentCase},
        d.last_purchase_date, d.total_transactions, d.total_spent,
        CURDATE(), NOW()
        FROM (
            SELECT
                c.business_id,
                c.id AS customer_id,
                {$rExpr} AS r,
                {$fExpr} AS f,
                {$mExpr} AS m,
                MAX(t.transaction_date) AS last_purchase_date,
                COUNT(t.id) AS total_transactions,
                COALESCE(SUM(t.amount), 0) AS total_spent
            FROM customers c
            LEFT JOIN transactions t ON c.id = t.customer_id
            WHERE c.business_id = ?
            GROUP BY c.id, c.business_id
        ) d";

    $db->prepare($query)->execute([$businessId]);

    if ($userId !== null && function_exists('auth')) {
        auth()->logActivity($userId, 'rfm_calculation', 'RFM analysis calculated', $businessId);
    }

    return true;
}
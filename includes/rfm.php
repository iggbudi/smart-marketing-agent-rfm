<?php
/**
 * includes/rfm.php
 * Perhitungan RFM (Recency, Frequency, Monetary) terpusat.
 * Dipakai oleh analysis.php (dan dapat dipanggil dari cron/CLI).
 */

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

    $query = "INSERT INTO rfm_analysis
        (business_id, customer_id, recency_score, frequency_score, monetary_score,
         rfm_segment, last_purchase_date, total_transactions, total_spent, analysis_date, created_at)
        SELECT d.business_id, d.customer_id, d.r, d.f, d.m,
        CASE
            WHEN d.r >= 4 AND d.f >= 4 AND d.m >= 4 THEN 'Champions'
            WHEN d.r >= 3 AND d.f >= 3 THEN 'Loyal Customers'
            WHEN d.r >= 3 AND d.m >= 3 THEN 'Potential Loyalists'
            WHEN d.r <= 2 THEN 'At Risk'
            ELSE 'Lost Customers'
        END,
        d.last_purchase_date, d.total_transactions, d.total_spent,
        CURDATE(), NOW()
        FROM (
            SELECT
                c.business_id,
                c.id AS customer_id,
                CASE
                    WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 30 THEN 5
                    WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 90 THEN 4
                    WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 180 THEN 3
                    WHEN DATEDIFF(NOW(), MAX(t.transaction_date)) <= 365 THEN 2
                    ELSE 1
                END AS r,
                CASE
                    WHEN COUNT(t.id) >= 10 THEN 5
                    WHEN COUNT(t.id) >= 7 THEN 4
                    WHEN COUNT(t.id) >= 5 THEN 3
                    WHEN COUNT(t.id) >= 3 THEN 2
                    ELSE 1
                END AS f,
                CASE
                    WHEN AVG(t.amount) >= 500000 THEN 5
                    WHEN AVG(t.amount) >= 300000 THEN 4
                    WHEN AVG(t.amount) >= 200000 THEN 3
                    WHEN AVG(t.amount) >= 100000 THEN 2
                    ELSE 1
                END AS m,
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
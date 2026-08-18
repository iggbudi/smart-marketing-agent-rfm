<?php
/**
 * src/Rfm/RfmService.php
 * Slice vertikal "RFM Analysis": rekalkulasi & pembacaan rfm_analysis.
 * Logika skor/segmentasi tetap di src/Rfm.php (single source of truth);
 * SQL di sini DIBANGUN dari \App\Rfm::*Sql() (sama seperti includes/rfm.php lama).
 */

namespace App\Rfm;

class RfmService
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function count(int $businessId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM rfm_analysis WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Hitung ulang RFM untuk satu business (DELETE + INSERT ulang).
     * Skor R/F/M dihitung sekali di subquery; segmentasi diturunkan dari skor.
     */
    public function recalculate(int $businessId, ?int $userId = null): void
    {
        $this->db->prepare("DELETE FROM rfm_analysis WHERE business_id = ?")->execute([$businessId]);

        $rExpr = \App\Rfm::recencyScoreSql('DATEDIFF(NOW(), MAX(t.transaction_date))');
        $fExpr = \App\Rfm::frequencyScoreSql('COUNT(t.id)');
        $mExpr = \App\Rfm::monetaryScoreSql('AVG(t.amount)');
        $segmentCase = \App\Rfm::segmentCaseSql('d.r', 'd.f', 'd.m');

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

        $this->db->prepare($query)->execute([$businessId]);

        if ($userId !== null && function_exists('auth')) {
            auth()->logActivity($userId, 'rfm_calculation', 'RFM analysis calculated', $businessId);
        }
    }

    /** Rekalkulasi otomatis hanya saat belum ada data (first-run). @return bool true bila dihitung. */
    public function ensureCalculated(int $businessId, ?int $userId = null): bool
    {
        if ($this->count($businessId) > 0) {
            return false;
        }
        $this->recalculate($businessId, $userId);
        return true;
    }

    /** Baris analisis + data pelanggan (urutan skor terbaik dulu). */
    public function results(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.customer_name as name,
                c.email,
                r.recency_score,
                r.frequency_score,
                r.monetary_score,
                r.rfm_segment as segment,
                r.total_transactions,
                r.total_spent,
                r.last_purchase_date as last_transaction
            FROM rfm_analysis r
            JOIN customers c ON r.customer_id = c.id
            WHERE r.business_id = ?
            ORDER BY r.recency_score DESC, r.frequency_score DESC, r.monetary_score DESC
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Ringkasan jumlah per segmen: [segment => count]. */
    public function segmentSummary(int $businessId): array
    {
        $stmt = $this->db->prepare(
            "SELECT rfm_segment as segment, COUNT(*) as count FROM rfm_analysis WHERE business_id = ? GROUP BY rfm_segment"
        );
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}

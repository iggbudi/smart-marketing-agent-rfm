<?php
/**
 * src/Dashboard/DashboardStats.php
 * Slice vertikal "Dashboard": agregat + data grafik untuk dashboard.php.
 * Memakai repository Customers & Transactions agar query tidak diduplikasi.
 */

namespace App\Dashboard;

use App\Customers\CustomerRepository;
use App\Transactions\TransactionRepository;

class DashboardStats
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Kartu statistik: total_customers, total_transactions, total_revenue. */
    public function getStats(int $businessId): array
    {
        $customers = new CustomerRepository($this->db);
        $transactions = new TransactionRepository($this->db);
        return [
            'total_customers' => $customers->count($businessId),
            'total_transactions' => $transactions->count($businessId),
            'total_revenue' => $transactions->totalRevenue($businessId),
        ];
    }

    public function getRecentTransactions(int $businessId, int $limit = 10): array
    {
        return (new TransactionRepository($this->db))->recent($businessId, $limit);
    }

    /** Distribusi segmen RFM: [segment => count]. */
    public function getRfmData(int $businessId): array
    {
        $stmt = $this->db->prepare(
            "SELECT rfm_segment, COUNT(*) as count FROM rfm_analysis WHERE business_id = ? GROUP BY rfm_segment"
        );
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Jumlah pelanggan pada segmen berisiko (butuh perhatian / berpotensi hilang). */
    public function getAttentionCount(int $businessId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM rfm_analysis
             WHERE business_id = ?
               AND rfm_segment IN ('At Risk', 'About to Sleep', 'Customers Needing Attention', 'Cannot Lose Them', 'Lost Customers')"
        );
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    /** Tren revenue per bulan (N bulan terakhir). */
    public function getRevenueTrend(int $businessId, int $months = 6): array
    {
        $stmt = $this->db->prepare("
            SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(amount) as revenue
            FROM transactions
            WHERE business_id = ? AND transaction_date >= DATE_SUB(NOW(), INTERVAL " . (int)$months . " MONTH)
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

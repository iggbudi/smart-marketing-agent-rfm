<?php
/**
 * src/Admin/AnalyticsAdmin.php
 * Slice vertikal "Admin": agregat & tren analytics platform (baca) untuk admin/analytics.php.
 * Perilaku revenue = SUM(amount) (abaikan qty) — dipertahankan dari halaman lama.
 */

namespace App\Admin;

class AnalyticsAdmin
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Ringkasan platform: total user/bisnis/pelanggan/transaksi/revenue/sesi aktif. */
    public function platform(): array
    {
        return [
            'total_users'        => (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'total_businesses'   => (int)$this->db->query('SELECT COUNT(*) FROM businesses')->fetchColumn(),
            'total_customers'    => (int)$this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
            'total_transactions' => (int)$this->db->query('SELECT COUNT(*) FROM transactions')->fetchColumn(),
            'total_revenue'      => (float)$this->db->query('SELECT COALESCE(SUM(amount),0) FROM transactions')->fetchColumn(),
            'active_sessions'    => (int)$this->db->query('SELECT COUNT(*) FROM user_sessions WHERE expires_at > NOW()')->fetchColumn(),
        ];
    }

    /** Registrasi user per hari, N hari terakhir. */
    public function userTrends(int $days = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM users
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)
             GROUP BY DATE(created_at)
             ORDER BY date"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Top bisnis by revenue (dengan jumlah customer & transaksi). */
    public function businessActivity(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.name as business_name,
                    COUNT(DISTINCT c.id) as customers,
                    COUNT(t.id) as transactions,
                    COALESCE(SUM(t.amount), 0) as revenue
             FROM businesses b
             LEFT JOIN customers c ON b.id = c.business_id
             LEFT JOIN transactions t ON c.id = t.customer_id
             GROUP BY b.id, b.name
             ORDER BY revenue DESC
             LIMIT " . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Tren transaksi & revenue per hari, N hari terakhir. */
    public function transactionTrends(int $days = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(transaction_date) as date,
                    COUNT(*) as count,
                    SUM(amount) as revenue
             FROM transactions
             WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)
             GROUP BY DATE(transaction_date)
             ORDER BY date"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Distribusi segmen RFM: [segment => count]. */
    public function rfmSegments(): array
    {
        $stmt = $this->db->query(
            "SELECT rfm_segment, COUNT(*) as count FROM rfm_analysis GROUP BY rfm_segment ORDER BY count DESC"
        );
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Pemakaian API per endpoint, N hari terakhir (perilaku kolom dipertahankan). */
    public function apiUsage(int $days = 7, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT endpoint, COUNT(*) as usage_count,
                    AVG(COALESCE(tokens_used, 0)) as avg_tokens,
                    AVG(COALESCE(cost, 0)) as avg_response_time,
                    SUM(COALESCE(cost, 0)) as total_cost
             FROM api_usage_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)
             GROUP BY endpoint
             ORDER BY usage_count DESC
             LIMIT " . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Aktivitas terbaru + nama user. */
    public function recentActivities(int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT al.*, u.full_name as user_name, al.action as action_type
             FROM activity_logs al
             JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC
             LIMIT " . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Pertumbuhan jumlah user 30 hari terakhir vs 30 hari sebelumnya (%). */
    public function userGrowthRate(): float
    {
        $last = (int)$this->db->query(
            "SELECT COUNT(*) FROM users
             WHERE created_at >= DATE_SUB(DATE_SUB(NOW(), INTERVAL 30 DAY), INTERVAL 30 DAY)
               AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetchColumn();
        $current = (int)$this->db->query(
            "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        )->fetchColumn();
        return $last > 0 ? round((($current - $last) / $last) * 100, 1) : 0.0;
    }
}

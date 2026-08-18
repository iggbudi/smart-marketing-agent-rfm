<?php
/**
 * src/Admin/PlatformStats.php
 * Slice vertikal "Admin" (panel super_admin): agregat platform lintas-business
 * untuk admin/dashboard.php. Pola meniru src/Dashboard/DashboardStats.php.
 */

namespace App\Admin;

class PlatformStats
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** KPI platform. revenue = SUM(amount) (perilaku lama, abaikan qty). */
    public function getKpis(): array
    {
        return [
            'total_umkm'        => (int)$this->scalar("SELECT COUNT(*) FROM users WHERE role = 'umkm_owner' AND is_active = 1"),
            'total_businesses'  => (int)$this->scalar('SELECT COUNT(*) FROM businesses'),
            'total_customers'   => (int)$this->scalar('SELECT COUNT(*) FROM customers'),
            'total_transactions'=> (int)$this->scalar('SELECT COUNT(*) FROM transactions'),
            'total_revenue'     => (float)$this->scalar('SELECT COALESCE(SUM(amount),0) FROM transactions'),
            'active_today'      => (int)$this->scalar('SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE DATE(created_at) = CURDATE()'),
        ];
    }

    /** Pemakaian API hari ini: [api_type => count]. */
    public function getApiUsageToday(): array
    {
        $stmt = $this->db->query(
            "SELECT api_type, COUNT(*) as c FROM api_usage_logs WHERE DATE(created_at) = CURDATE() GROUP BY api_type"
        );
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Aktivitas terbaru + data user/bisnis. LIMIT inline (int) — gotcha PDO. */
    public function getRecentActivities(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, u.full_name, b.name as business_name
             FROM activity_logs a
             LEFT JOIN users u ON a.user_id = u.id
             LEFT JOIN businesses b ON a.business_id = b.id
             ORDER BY a.created_at DESC
             LIMIT " . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Pertumbuhan bisnis per hari (N hari terakhir). */
    public function getBusinessGrowth(int $days = 7): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM businesses
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)
             GROUP BY DATE(created_at)
             ORDER BY date"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function scalar(string $sql)
    {
        return $this->db->query($sql)->fetchColumn();
    }
}

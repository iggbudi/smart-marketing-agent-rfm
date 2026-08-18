<?php
/**
 * src/Admin/ApiManagementAdmin.php
 * Slice vertikal "Admin": statistik & log pemakaian API (baca) untuk admin/api-management.php.
 * POST (clear_logs / update_settings) tetap ditangani halaman — slice ini hanya data baca.
 * Perilaku kolom (avg_tokens, cost, error_rate) dipertahankan dari halaman lama.
 */

namespace App\Admin;

class ApiManagementAdmin
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Statistik API: total/today requests, avg tokens, cost & error rate 24 jam. */
    public function getStats(): array
    {
        $avgTokens = $this->db->query(
            "SELECT AVG(tokens_used) FROM api_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        )->fetchColumn();
        return [
            'total_requests' => (int)$this->db->query('SELECT COUNT(*) FROM api_usage_logs')->fetchColumn(),
            'today_requests' => (int)$this->db->query('SELECT COUNT(*) FROM api_usage_logs WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
            'avg_tokens'     => $avgTokens === null ? 0 : $avgTokens,
            'total_cost'     => (float)($this->db->query(
                "SELECT SUM(cost) FROM api_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            )->fetchColumn() ?? 0),
            'error_rate'     => (float)($this->db->query(
                "SELECT (COUNT(CASE WHEN status = 'error' THEN 1 END) * 100.0 / COUNT(*)) as error_rate
                 FROM api_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            )->fetchColumn() ?? 0),
        ];
    }

    /** Log API terbaru. */
    public function recentUsage(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT endpoint, api_type, status, tokens_used, cost, created_at
             FROM api_usage_logs
             ORDER BY created_at DESC
             LIMIT " . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Statistik per endpoint, N hari terakhir. */
    public function endpointStats(int $days = 7): array
    {
        $stmt = $this->db->prepare(
            "SELECT endpoint,
                    COUNT(*) as requests,
                    AVG(tokens_used) as avg_tokens,
                    COUNT(CASE WHEN status = 'error' THEN 1 END) as errors
             FROM api_usage_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)
             GROUP BY endpoint
             ORDER BY requests DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Penggunaan per jam hari ini — array 24 slot (0..23 => requests). */
    public function hourlyUsageToday(): array
    {
        $stmt = $this->db->query(
            "SELECT HOUR(created_at) as hour, COUNT(*) as requests
             FROM api_usage_logs
             WHERE DATE(created_at) = CURDATE()
             GROUP BY HOUR(created_at)
             ORDER BY hour"
        );
        $usage = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $out = [];
        for ($i = 0; $i < 24; $i++) {
            $out[$i] = (int)($usage[$i] ?? 0);
        }
        return $out;
    }

    /** Pengaturan API saat ini (system_settings). */
    public function settings(): array
    {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM system_settings");
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}

<?php
/**
 * tests/ApiManagementAdminTest.php
 * Slice Admin\ApiManagementAdmin: statistik & log pemakaian API (baca) untuk admin/api-management.php.
 */

use App\Admin\ApiManagementAdmin;
use PHPUnit\Framework\TestCase;

class ApiManagementAdminTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    public function testStatsHariIniDanHourly(): void
    {
        $admin = new ApiManagementAdmin($this->db);
        $base = $admin->getStats();

        // Seed 2 api log hari ini (1 success, 1 error)
        $this->db->prepare(
            "INSERT INTO api_usage_logs (business_id, api_type, endpoint, tokens_used, cost, status, created_at)
             VALUES (NULL, 'openai', '/gpt', 100, 0.01, 'success', NOW())"
        )->execute();
        $this->db->prepare(
            "INSERT INTO api_usage_logs (business_id, api_type, endpoint, tokens_used, cost, status, created_at)
             VALUES (NULL, 'openai', '/gpt', 0, 0.0, 'error', NOW())"
        )->execute();

        try {
            $stats = $admin->getStats();
            $this->assertSame($base['today_requests'] + 2, (int)$stats['today_requests']);
            $this->assertSame($base['total_requests'] + 2, (int)$stats['total_requests']);
            $this->assertArrayHasKey('error_rate', $stats);
            $this->assertArrayHasKey('total_cost', $stats);

            $this->assertNotEmpty($admin->recentUsage(5), 'recent usage harus ada');
            $this->assertIsArray($admin->endpointStats(7), 'endpoint stats harus array');

            $hour = (new \DateTime('now'))->format('G');
            $hourly = $admin->hourlyUsageToday();
            $this->assertCount(24, $hourly, 'hourly usage harus 24 slot');
            $this->assertGreaterThanOrEqual(2, (int)$hourly[$hour], 'slot jam sekarang harus >= 2');
        } finally {
            $this->db->exec("DELETE FROM api_usage_logs WHERE endpoint = '/gpt'");
        }
    }

    public function testSettingsRoundtrip(): void
    {
        $admin = new ApiManagementAdmin($this->db);
        $key = 'test_' . bin2hex(random_bytes(4));
        try {
            // Seed langsung (write ada di POST handler halaman) — slice cukup mampu membaca
            $this->db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)")
                ->execute([$key, 'nilai']);
            $settings = $admin->settings();
            $this->assertSame('nilai', $settings[$key] ?? null, 'setting harus terbaca');
        } finally {
            $this->db->prepare("DELETE FROM system_settings WHERE setting_key = ?")->execute([$key]);
        }
    }
}

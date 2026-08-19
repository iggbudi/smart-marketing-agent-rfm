<?php
/**
 * tests/ApiManagementPostCsrfTest.php
 * Regression keamanan & fungsi admin/api-management.php:
 * 1. Handler POST wajib requireCsrf() (sebelumnya TANPA — form modal & JS tidak mengirim token).
 * 2. clear_logs: `INTERVAL ? DAY` dengan placeholder PDO di-quote jadi string →
 *    error (gotcha AGENTS §2.1); harus inline (int)cast.
 * 3. clear_logs/update_settings harus benar-benar bekerja bila token CSRF valid.
 */

use PHPUnit\Framework\TestCase;

class ApiManagementPostCsrfTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function seedAdminSession(): array
    {
        $email = 'admin_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare("INSERT INTO users (email, password, full_name, role, is_active, email_verified)
                            VALUES (?, ?, ?, 'super_admin', 1, 1)")
            ->execute([$email, password_hash('x', PASSWORD_DEFAULT), 'Test Admin']);
        $uid = (int)$this->db->lastInsertId();
        $token = bin2hex(random_bytes(32));
        $this->db->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
            ->execute([$uid, $token]);
        return [$uid, $email, $token];
    }

    public function testHandlerWajibCsrf(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/api-management.php');
        $this->assertStringContainsString('requireCsrf()', $src, 'handler POST wajib memanggil requireCsrf()');
        $this->assertGreaterThanOrEqual(2, substr_count($src, 'csrf_field()'), 'semua form modal wajib csrf_field');
    }

    public function testClearLogsBerhasilDenganCsrfDanIntervalFix(): void
    {
        [$uid, $adminEmail, $sessionToken] = $this->seedAdminSession();
        $root = dirname(__DIR__);
        $csrf = bin2hex(random_bytes(32));
        $marker = '/marker_' . bin2hex(random_bytes(4));

        // Seed log API 40 hari lalu (harus terhapus oleh clear_logs days=30)
        $this->db->prepare(
            "INSERT INTO api_usage_logs (business_id, api_type, endpoint, status, created_at)
             VALUES (NULL, 'openai', ?, 'success', DATE_SUB(NOW(), INTERVAL 40 DAY))"
        )->execute([$marker]);
        $this->db->prepare(
            "INSERT INTO api_usage_logs (business_id, api_type, endpoint, status, created_at)
             VALUES (NULL, 'openai', '/baru', 'success', NOW())"
        )->execute();

        $_SERVER['PHP_SELF'] = '/admin/api-management.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['user_id'] = $uid;
        $_SESSION['user_email'] = $adminEmail;
        $_SESSION['user_name'] = 'Test Admin';
        $_SESSION['user_role'] = 'super_admin';
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['csrf_token'] = $csrf;
        $_POST = ['csrf_token' => $csrf, 'action' => 'clear_logs', 'days' => '30'];

        try {
            ob_start();
            require $root . '/admin/api-management.php';
            ob_end_clean();

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM api_usage_logs WHERE endpoint = ?");
            $stmt->execute([$marker]);
            $this->assertSame(0, (int)$stmt->fetchColumn(), 'log 40 hari lalu harus terhapus (INTERVAL fix, dgn CSRF)');

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM api_usage_logs WHERE endpoint = '/baru'");
            $stmt->execute();
            $this->assertSame(1, (int)$stmt->fetchColumn(), 'log hari ini tidak ikut terhapus');
        } finally {
            $this->db->prepare("DELETE FROM api_usage_logs WHERE endpoint IN (?, '/baru')")->execute([$marker]);
            $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $_POST = [];
            unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['session_token'], $_SESSION['csrf_token']);
        }
    }

    public function testUpdateSettingsBerhasilDenganCsrf(): void
    {
        [$uid, $adminEmail, $sessionToken] = $this->seedAdminSession();
        $root = dirname(__DIR__);
        $csrf = bin2hex(random_bytes(32));
        $key = 'test_' . bin2hex(random_bytes(4));

        $_SERVER['PHP_SELF'] = '/admin/api-management.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['user_id'] = $uid;
        $_SESSION['user_email'] = $adminEmail;
        $_SESSION['user_name'] = 'Test Admin';
        $_SESSION['user_role'] = 'super_admin';
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['csrf_token'] = $csrf;
        $_POST = ['csrf_token' => $csrf, 'action' => 'update_settings', 'settings' => [$key => 'nilai']];

        try {
            ob_start();
            require $root . '/admin/api-management.php';
            ob_end_clean();

            $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $this->assertSame('nilai', $stmt->fetchColumn(), 'setting API harus tersimpan dgn CSRF');
        } finally {
            $this->db->prepare("DELETE FROM system_settings WHERE setting_key = ?")->execute([$key]);
            $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $_POST = [];
            unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['session_token'], $_SESSION['csrf_token']);
        }
    }
}

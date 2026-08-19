<?php
/**
 * tests/SettingsPostCsrfTest.php
 * Regression keamanan admin/settings.php: tombol "Cadangkan Database" & "Bersihkan Cache"
 * membuat form POST via JS TANPA csrf_token → requireCsrf() menolak (403). Fix: JS harus
 * menyertakan token CSRF, dan POST backup/clear-cache harus berfungsi dgn token valid.
 */

use PHPUnit\Framework\TestCase;

class SettingsPostCsrfTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    public function testJsBackupDanClearCacheMenyertakanCsrf(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/settings.php');
        $this->assertStringContainsString("name = 'csrf_token'", $src, 'JS backup/clearCache wajib membuat input csrf_token');
        // Handler tetap wajib requireCsrf
        $this->assertStringContainsString('requireCsrf()', $src, 'handler POST wajib requireCsrf');
    }

    public function testBackupDatabaseBerhasilDenganCsrf(): void
    {
        $email = 'admin_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare("INSERT INTO users (email, password, full_name, role, is_active, email_verified)
                            VALUES (?, ?, ?, 'super_admin', 1, 1)")
            ->execute([$email, password_hash('x', PASSWORD_DEFAULT), 'Test Admin']);
        $uid = (int)$this->db->lastInsertId();
        $sessionToken = bin2hex(random_bytes(32));
        $this->db->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
            ->execute([$uid, $sessionToken]);
        $root = dirname(__DIR__);
        $csrf = bin2hex(random_bytes(32));

        $_SERVER['PHP_SELF'] = '/admin/settings.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['user_id'] = $uid;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = 'Test Admin';
        $_SESSION['user_role'] = 'super_admin';
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['csrf_token'] = $csrf;
        $_POST = ['csrf_token' => $csrf, 'action' => 'backup_database'];

        try {
            ob_start();
            require $root . '/admin/settings.php';
            ob_end_clean();

            $stmt = $this->db->prepare("SELECT action FROM activity_logs WHERE user_id = ? AND action = 'database_backup' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$uid]);
            $this->assertNotFalse($stmt->fetch(), 'backup_database harus terproses dgn CSRF (tercatat di activity_logs)');
        } finally {
            $this->db->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $_POST = [];
            unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['session_token'], $_SESSION['csrf_token']);
        }
    }
}

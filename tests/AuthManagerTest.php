<?php
/**
 * tests/AuthManagerTest.php
 * Unit test AuthManager terhadap database TEST (smart_marketing_rfm_test)
 * — jangan pernah dijalankan terhadap DB produksi (lihat tests/bootstrap.php).
 *
 * Cakupan (sesuai RENCANA_PERBAIKAN 4.1):
 * - login sukses / gagal (password salah, user non-aktif)
 * - session expiry
 * - role check (hasRequiredRole)
 * - logout membersihkan session DB
 */

use PHPUnit\Framework\TestCase;

class AuthManagerTest extends TestCase
{
    /** @var \PDO */
    private $db;
    private $testEmail;
    private $testPassword = 'PasswordTest#123';
    private $userId;

    protected function setUp(): void
    {
        $this->db = getDB();
        $this->testEmail = 'test_' . bin2hex(random_bytes(4)) . '@test.local';
        $stmt = $this->db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active) VALUES (?, ?, ?, 'umkm_owner', 1)"
        );
        $stmt->execute([$this->testEmail, password_hash($this->testPassword, PASSWORD_DEFAULT), 'Test User']);
        $this->userId = (int)$this->db->lastInsertId();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$this->userId]);
            $this->db->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$this->userId]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$this->userId]);
        }
        $_SESSION = [];
    }

    // ---- Login ----

    public function testLoginSuccess()
    {
        $auth = new AuthManager();
        $result = $auth->login($this->testEmail, $this->testPassword);

        $this->assertTrue($result['success']);
        $this->assertSame($this->testEmail, $result['user']['email']);
        $this->assertSame($this->userId, $_SESSION['user_id']);
        $this->assertSame('umkm_owner', $_SESSION['user_role']);
        $this->assertNotEmpty($_SESSION['session_token']);

        // Token tersimpan di user_sessions
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND session_token = ? AND expires_at > NOW()"
        );
        $stmt->execute([$this->userId, $_SESSION['session_token']]);
        $this->assertSame(1, (int)$stmt->fetchColumn(), 'Session token harus tercatat di DB');

        // Activity log login tercatat
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM activity_logs WHERE user_id = ? AND action = 'login'");
        $stmt->execute([$this->userId]);
        $this->assertGreaterThanOrEqual(1, (int)$stmt->fetchColumn());
    }

    public function testLoginFailsWithWrongPassword()
    {
        $auth = new AuthManager();
        $result = $auth->login($this->testEmail, 'password-salah');

        $this->assertFalse($result['success']);
        $this->assertArrayNotHasKey('user_id', $_SESSION, 'Session tidak boleh terisi saat login gagal');

        // Tidak ada session row yang bocor
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    public function testLoginFailsForInactiveUser()
    {
        // User non-aktif
        $email = 'inactive_' . bin2hex(random_bytes(4)) . '@test.local';
        $stmt = $this->db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active) VALUES (?, ?, ?, 'umkm_owner', 0)"
        );
        $stmt->execute([$email, password_hash('pass123', PASSWORD_DEFAULT), 'Inactive User']);
        $inactiveId = (int)$this->db->lastInsertId();

        try {
            $auth = new AuthManager();
            $result = $auth->login($email, 'pass123');
            $this->assertFalse($result['success']);
        } finally {
            $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$inactiveId]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$inactiveId]);
        }
    }

    // ---- Session validity & expiry ----

    public function testIsLoggedInFalseWithoutSession()
    {
        $_SESSION = [];
        $auth = new AuthManager();
        $this->assertFalse($auth->isLoggedIn());
    }

    public function testIsLoggedInTrueWithActiveToken()
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare(
            "INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
        );
        $stmt->execute([$this->userId, $token]);

        $_SESSION['user_id'] = $this->userId;
        $_SESSION['session_token'] = $token;

        $auth = new AuthManager();
        $this->assertTrue($auth->isLoggedIn());
    }

    public function testIsLoggedInFalseWithExpiredToken()
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare(
            "INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, DATE_SUB(NOW(), INTERVAL 1 HOUR))"
        );
        $stmt->execute([$this->userId, $token]);

        $_SESSION['user_id'] = $this->userId;
        $_SESSION['session_token'] = $token;

        $auth = new AuthManager();
        $this->assertFalse($auth->isLoggedIn(), 'Session yang sudah expire harus ditolak');
    }

    public function testIsLoggedInFalseForUnknownToken()
    {
        $_SESSION['user_id'] = $this->userId;
        $_SESSION['session_token'] = 'token-tidak-ada-di-db';

        $auth = new AuthManager();
        $this->assertFalse($auth->isLoggedIn());
    }

    // ---- Role check ----

    public function testHasRequiredRole()
    {
        $_SESSION['user_role'] = 'umkm_owner';
        $auth = new AuthManager();

        $this->assertTrue($auth->hasRequiredRole(['umkm_owner']));
        $this->assertFalse($auth->hasRequiredRole(['super_admin']));
        $this->assertFalse($auth->hasRequiredRole(['super_admin', 'analyst']));
        $this->assertTrue($auth->hasRequiredRole([]), 'Daftar role kosong = semua role diizinkan');
        $this->assertTrue($auth->hasRequiredRole(['umkm_owner', 'super_admin']));
    }

    // ---- Logout ----

    public function testLogoutCleansSessionAndDb()
    {
        $auth = new AuthManager();
        $auth->login($this->testEmail, $this->testPassword);

        $this->assertTrue($auth->logout());
        $this->assertFalse($auth->isLoggedIn());

        // Token sudah dihapus dari DB
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }
}

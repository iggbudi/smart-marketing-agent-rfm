<?php
/**
 * tests/UsersPostTest.php
 * Regression: POST handler admin/users.php (add_user & edit_user) memakai kolom
 * `password_hash` padahal schema tabel users memakai kolom `password` →
 * PDOException "Unknown column 'password_hash'" → tambah/edit user selalu gagal.
 */

use PHPUnit\Framework\TestCase;

class UsersPostTest extends TestCase
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
        $this->db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active, email_verified)
             VALUES (?, ?, ?, 'super_admin', 1, 1)"
        )->execute([$email, password_hash('x', PASSWORD_DEFAULT), 'Test Admin']);
        $uid = (int)$this->db->lastInsertId();
        $token = bin2hex(random_bytes(32));
        $this->db->prepare(
            "INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
        )->execute([$uid, $token]);
        return [$uid, $email, $token];
    }

    public function testAddUserPostTersimpan(): void
    {
        [$uid, $adminEmail, $token] = $this->seedAdminSession();
        $root = dirname(__DIR__);
        $csrf = bin2hex(random_bytes(32));
        $newEmail = 'add_' . bin2hex(random_bytes(4)) . '@test.local';

        // Set session super_admin + CSRF + POST add_user, lalu render halaman
        $_SERVER['PHP_SELF'] = '/admin/users.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['user_id'] = $uid;
        $_SESSION['user_email'] = $adminEmail;
        $_SESSION['user_name'] = 'Test Admin';
        $_SESSION['user_role'] = 'super_admin';
        $_SESSION['session_token'] = $token;
        $_SESSION['csrf_token'] = $csrf;
        $_POST = [
            'csrf_token' => $csrf,
            'action'     => 'add_user',
            'email'      => $newEmail,
            'password'   => 'rahasia123',
            'full_name'  => 'Budi Baru',
            'role'       => 'umkm_owner',
        ];

        try {
            ob_start();
            require $root . '/admin/users.php';
            ob_end_clean();

            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$newEmail]);
            $row = $stmt->fetch();
            $this->assertNotFalse($row, 'User baru harus tersimpan lewat POST add_user');
            $this->assertSame('Budi Baru', $row['full_name']);
            $this->assertSame('umkm_owner', $row['role']);
            $this->assertTrue(password_verify('rahasia123', $row['password']), 'password harus ter-hash di kolom password');
        } finally {
            $this->db->prepare("DELETE FROM users WHERE email = ?")->execute([$newEmail]);
            $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $_POST = [];
            unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['session_token'], $_SESSION['csrf_token']);
        }
    }

    public function testEditUserPostMengupdatePasswordDanRole(): void
    {
        [$uid, $adminEmail, $token] = $this->seedAdminSession();
        $root = dirname(__DIR__);
        $csrf = bin2hex(random_bytes(32));
        // Seed target user (umkm_owner) yg akan di-edit
        $targetEmail = 'target_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active, email_verified)
             VALUES (?, ?, ?, 'umkm_owner', 1, 1)"
        )->execute([$targetEmail, password_hash('lama', PASSWORD_DEFAULT), 'Target Lama']);
        $targetId = (int)$this->db->lastInsertId();

        $newEmail = 'target_' . bin2hex(random_bytes(4)) . '@test.local';
        $_SERVER['PHP_SELF'] = '/admin/users.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['user_id'] = $uid;
        $_SESSION['user_email'] = $adminEmail;
        $_SESSION['user_name'] = 'Test Admin';
        $_SESSION['user_role'] = 'super_admin';
        $_SESSION['session_token'] = $token;
        $_SESSION['csrf_token'] = $csrf;
        $_POST = [
            'csrf_token' => $csrf,
            'action'     => 'edit_user',
            'user_id'    => $targetId,
            'email'      => $newEmail,
            'full_name'  => 'Target Baru',
            'role'       => 'super_admin',
            'password'   => 'baru999',
        ];

        try {
            ob_start();
            require $root . '/admin/users.php';
            ob_end_clean();

            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $row = $stmt->fetch();
            $this->assertNotFalse($row, 'User target harus tetap ada');
            $this->assertSame($newEmail, $row['email']);
            $this->assertSame('Target Baru', $row['full_name']);
            $this->assertSame('super_admin', $row['role']);
            $this->assertTrue(password_verify('baru999', $row['password']), 'password baru harus ter-update (kolom password)');
            $this->assertTrue(password_verify('lama', $row['password']) === false, 'password lama tidak boleh tersisa');
        } finally {
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
            $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$uid]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $_POST = [];
            unset($_SESSION['user_id'], $_SESSION['user_email'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['session_token'], $_SESSION['csrf_token']);
        }
    }
}

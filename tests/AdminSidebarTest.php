<?php
/**
 * tests/AdminSidebarTest.php
 * Regression test: HTTP 500 (OOM) setelah login super_admin.
 *
 * Root cause (log nginx 2026-08-18 14:11:34):
 *   admin/includes/sidebar.php (wrapper fase3) mengeksekusi
 *   `include dirname(__DIR__) . '/includes/sidebar.php'` yang dengan
 *   __DIR__ = admin/includes me-resolve ke DIRINYA SENDIRI → rekursi include
 *   tak berujung → "Allowed memory size exhausted" → 500 di semua halaman
 *   admin (dashboard, analytics, reports, settings, api-management).
 *
 * Bug hanya muncul saat CWD PHP tidak berisi includes/sidebar.php (mis. FPM
 * pool), karena include 'includes/sidebar.php' dari halaman admin jatuh ke
 * direktori admin/ → wrapper. Dengan CWD = root repo, include_path '.'
 * menyelamatkan (resolve langsung ke sidebar root) — sehingga render CLI
 * biasa tidak menangkapnya.
 *
 * Test ini mengunci:
 * 1. Ekspresi include di wrapper harus berujung ke includes/sidebar.php ROOT,
 *    bukan ke dirinya sendiri (unit, tanpa DB).
 * 2. admin/dashboard.php dapat dirender sebagai super_admin via CLI child
 *    dengan CWD = admin/ (meniru FPM; OOM hanya membunuh child, bukan PHPUnit).
 */

use PHPUnit\Framework\TestCase;

class AdminSidebarTest extends TestCase
{
    public function testWrapperSidebarResolveKeSidebarRoot(): void
    {
        $wrapper = realpath(dirname(__DIR__) . '/admin/includes/sidebar.php');
        $this->assertNotFalse($wrapper, 'admin/includes/sidebar.php harus ada');

        $src = file_get_contents($wrapper);
        $this->assertMatchesRegularExpression(
            '/include\s+dirname\(__DIR__[^;]*sidebar\.php\s*\x27;/',
            $src,
            'Wrapper harus berisi include dirname(__DIR__...) . \'/includes/sidebar.php\''
        );

        // Evaluasi ekspresi include PERSIS seperti runtime (__DIR__ = direktori file wrapper)
        preg_match('/include\s+(dirname\(__DIR__[^;]*sidebar\.php\s*\x27;)/', $src, $m);
        $expr = str_replace('__DIR__', var_export(dirname($wrapper), true), $m[1]);
        $target = realpath(eval('return ' . $expr . ';'));

        $expected = realpath(dirname(__DIR__) . '/includes/sidebar.php');
        $this->assertSame(
            $expected,
            $target,
            'Target include wrapper harus sidebar ROOT, bukan dirinya sendiri (rekursi = OOM/500)'
        );
        $this->assertNotSame($wrapper, $target, 'Wrapper tidak boleh meng-include dirinya sendiri');
    }

    public function testAdminDashboardRenderSebagaiSuperAdmin(): void
    {
        $root = dirname(__DIR__);
        $db = getDB();

        // Setup user super_admin + session valid di DB TEST
        $email = 'admin_' . bin2hex(random_bytes(4)) . '@test.local';
        $stmt = $db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active, email_verified) VALUES (?, ?, ?, 'super_admin', 1, 1)"
        );
        $stmt->execute([$email, password_hash('x', PASSWORD_DEFAULT), 'Test Admin']);
        $userId = (int)$db->lastInsertId();
        $token = bin2hex(random_bytes(32));
        $db->prepare(
            "INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
        )->execute([$userId, $token]);

        $child = null;
        try {
            // Child script: CWD = admin/ (meniru FPM) + session super_admin, lalu render.
            $child = tempnam(sys_get_temp_dir(), 'adminrender_') . '.php';
            file_put_contents($child, '<?php
                chdir(' . var_export($root . '/admin', true) . ');
                require_once ' . var_export($root . '/config/database.php', true) . ';
                require_once ' . var_export($root . '/config/auth.php', true) . ';
                $_SESSION["user_id"] = (int)$argv[1];
                $_SESSION["user_email"] = $argv[2];
                $_SESSION["user_name"] = "Test Admin";
                $_SESSION["user_role"] = "super_admin";
                $_SESSION["session_token"] = $argv[3];
                $_SERVER["PHP_SELF"] = "/admin/dashboard.php";
                $_SERVER["REQUEST_METHOD"] = "GET";
                $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
                include ' . var_export($root . '/admin/dashboard.php', true) . ';
            ');

            // -d memory_limit=128M: meniru memory_limit pool FPM. Tanpa ini, CLI
            // default -1 (unlimited) membuat rekursi include berjalan tanpa henti.
            $cmd = PHP_BINARY . ' -d memory_limit=128M ' . escapeshellarg($child) . ' '
                . escapeshellarg($userId) . ' ' . escapeshellarg($email) . ' ' . escapeshellarg($token) . ' 2>&1';
            exec($cmd, $output, $code);

            $this->assertSame(
                0,
                $code,
                "admin/dashboard.php gagal dirender (exit $code):\n" . implode("\n", array_slice($output, -15))
            );
            $html = implode("\n", $output);
            $this->assertStringContainsString('Platform Overview', $html, 'Konten dashboard admin harus tampil');
            $this->assertStringContainsString('Admin Panel', $html, 'Sidebar admin harus tampil');
            $this->assertStringNotContainsString('Allowed memory size', $html);
        } finally {
            if ($child !== null) {
                @unlink($child);
            }
            $db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM activity_logs WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        }
    }
}

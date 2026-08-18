<?php
/**
 * tests/MobileMenuToggleTest.php
 * Regression test: menu sidebar hilang di tampilan mobile (HTTP page render).
 *
 * Root cause (ditemukan 2026-08-18):
 *   assets/user-styles.css @media (max-width:768px) menyembunyikan .sidebar
 *   off-canvas (transform: translateX(-100%)); menu baru tampil bila elemen
 *   .sidebar diberi kelas .show — yang hanya terjadi lewat tombol
 *   .mobile-menu-toggle + JS toggleSidebar(). Halaman ai-content.php dan
 *   profile.php memuat includes/sidebar.php + user-styles.css tapi TIDAK
 *   memiliki tombol/JS tersebut -> di mobile menu hilang total & tak bisa
 *   dibuka. Halaman user lain (dashboard, customers, transactions, upload,
 *   analysis) sudah punya pola yang sama (tombol + fungsi + event click
 *   menutup saat klik di luar).
 *
 * Test mengunci:
 * 1. ai-content.php & profile.php WAJIB memuat tombol .mobile-menu-toggle
 *    dan fungsi toggleSidebar() (struktur, tanpa DB).
 * 2. ai-content.php dapat dirender sebagai umkm_owner via CLI child dan HTML
 *    akhir memuat tombol toggle + fungsi (integrasi, DB test).
 */

use PHPUnit\Framework\TestCase;

class MobileMenuToggleTest extends TestCase
{
    public static function halamanTanpaToggleProvider(): array
    {
        return [
            'ai-content.php' => ['ai-content.php'],
            'profile.php'    => ['profile.php'],
        ];
    }

    /**
     * @dataProvider halamanTanpaToggleProvider
     */
    public function testHalamanUserMemuatTombolMobileToggle(string $page): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/' . $page);
        $this->assertNotFalse($src, "$page harus bisa dibaca");

        // Tombol kini berasal dari include top bar (SATU sumber) yang wajib dipasang.
        $this->assertStringContainsString(
            'mobile-topbar.php',
            $src,
            "$page: wajib include top bar (sumber tombol .mobile-menu-toggle)"
        );
        $this->assertStringContainsString(
            'function toggleSidebar()',
            $src,
            "$page: JS toggleSidebar() wajib ada agar tombol bisa membuka sidebar"
        );

        // Tombol itu sendiri hidup di include — pastikan isinya benar.
        $topbar = file_get_contents(dirname(__DIR__) . '/includes/mobile-topbar.php');
        $this->assertNotFalse($topbar, 'mobile-topbar.php harus bisa dibaca');
        $this->assertStringContainsString('mobile-menu-toggle', $topbar, 'mobile-topbar.php: tombol toggle wajib ada');
    }

    public function testAiContentRenderMemuatTombolToggle(): void
    {
        $root = dirname(__DIR__);
        $db = getDB();

        // Setup user umkm_owner + business + session valid di DB TEST
        $email = 'owner_' . bin2hex(random_bytes(4)) . '@test.local';
        $db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active, email_verified) VALUES (?, ?, ?, 'umkm_owner', 1, 1)"
        )->execute([$email, password_hash('x', PASSWORD_DEFAULT), 'Test Owner']);
        $userId = (int)$db->lastInsertId();
        $db->prepare(
            "INSERT INTO businesses (user_id, name, owner_name, email, phone, address, business_type) VALUES (?, 'Test Bisnis', 'Test Owner', ?, '081200000000', 'Jl. Test 1', 'Lainnya')"
        )->execute([$userId, $email]);
        $token = bin2hex(random_bytes(32));
        $db->prepare(
            "INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
        )->execute([$userId, $token]);

        $child = null;
        try {
            $child = tempnam(sys_get_temp_dir(), 'aicontent_') . '.php';
            file_put_contents($child, '<?php
                require_once ' . var_export($root . '/config/database.php', true) . ';
                require_once ' . var_export($root . '/config/auth.php', true) . ';
                $_SESSION["user_id"] = (int)$argv[1];
                $_SESSION["user_email"] = $argv[2];
                $_SESSION["user_name"] = "Test Owner";
                $_SESSION["user_role"] = "umkm_owner";
                $_SESSION["session_token"] = $argv[3];
                $_SERVER["PHP_SELF"] = "/ai-content.php";
                $_SERVER["REQUEST_METHOD"] = "GET";
                $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
                include ' . var_export($root . '/ai-content.php', true) . ';
            ');

            $cmd = PHP_BINARY . ' -d memory_limit=128M ' . escapeshellarg($child) . ' '
                . escapeshellarg($userId) . ' ' . escapeshellarg($email) . ' ' . escapeshellarg($token) . ' 2>&1';
            exec($cmd, $output, $code);

            $this->assertSame(
                0,
                $code,
                "ai-content.php gagal dirender (exit $code):\n" . implode("\n", array_slice($output, -15))
            );
            $html = implode("\n", $output);
            $this->assertStringContainsString('Generator Konten AI', $html, 'Konten halaman AI harus tampil');
            $this->assertStringContainsString('mobile-menu-toggle', $html, 'Tombol mobile toggle harus ada di HTML akhir');
            $this->assertStringContainsString('function toggleSidebar()', $html, 'JS toggleSidebar harus ada di HTML akhir');
        } finally {
            if ($child !== null) {
                @unlink($child);
            }
            $db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM businesses WHERE user_id = ?")->execute([$userId]);
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        }
    }
}

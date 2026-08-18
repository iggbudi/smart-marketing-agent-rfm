<?php
/**
 * tests/MobileRelayoutTest.php
 * Mengunci relayout & recolor UI mobile (segmen UMKM Indonesia):
 * 1. Design tokens (--brand-*) ada di user-styles.css & admin-styles.css.
 * 2. Shell mobile (topbar/bottom-nav/backdrop/mobile.js) terpasang di 7 halaman user.
 * 3. Komponen: table-cards.js di customers/transactions, FAB, label Indonesia di dashboard.
 * (Diperluas bertahap per task — pola tests/MobileResponsiveTest.php)
 */

use PHPUnit\Framework\TestCase;

class MobileRelayoutTest extends TestCase
{
    public static function halamanUserProvider(): array
    {
        return [
            'dashboard.php'   => ['dashboard.php'],
            'customers.php'   => ['customers.php'],
            'transactions.php'=> ['transactions.php'],
            'analysis.php'    => ['analysis.php'],
            'upload.php'      => ['upload.php'],
            'ai-content.php'  => ['ai-content.php'],
            'profile.php'     => ['profile.php'],
        ];
    }

    public function testUserStylesheetPunyaDesignTokens(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/user-styles.css');
        $this->assertNotFalse($css, 'user-styles.css harus bisa dibaca');
        $this->assertStringContainsString('--brand-1: #0f766e', $css, 'user-styles.css: token brand-1 (teal) wajib ada');
        $this->assertStringContainsString('--brand-2: #059669', $css, 'user-styles.css: token brand-2 (emerald) wajib ada');
        $this->assertStringContainsString('--accent: #f59e0b', $css, 'user-styles.css: token accent (amber) wajib ada');
        $this->assertStringContainsString('var(--grad-brand)', $css, 'user-styles.css: sidebar/btn wajib pakai var(--grad-brand)');
    }

    public function testAdminStylesheetPunyaDesignTokens(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/admin/assets/admin-styles.css');
        $this->assertNotFalse($css, 'admin-styles.css harus bisa dibaca');
        $this->assertStringContainsString('--brand-1: #0f766e', $css, 'admin-styles.css: token brand-1 wajib ada');
        $this->assertStringContainsString('var(--grad-brand)', $css, 'admin-styles.css: sidebar wajib pakai var(--grad-brand)');
    }

    public function testLandingDanLoginPakaiIdentitasBaru(): void
    {
        foreach ([
            dirname(__DIR__) . '/assets/landing.css',
            dirname(__DIR__) . '/assets/login.css',
        ] as $css) {
            $src = file_get_contents($css);
            $this->assertNotFalse($src, basename($css) . ' harus bisa dibaca');
            $this->assertStringContainsString('--brand-1: #0f766e', $src, basename($css) . ': brand-1 teal wajib');
            $this->assertStringContainsString('--brand-2: #059669', $src, basename($css) . ': brand-2 emerald wajib');
            $this->assertStringContainsString('#f59e0b', $src, basename($css) . ': aksen amber wajib');
        }
    }

    public function testStatsCardKonsistenDiHalamanData(): void
    {
        foreach (['customers.php', 'transactions.php'] as $page) {
            $src = file_get_contents(dirname(__DIR__) . '/' . $page);
            $this->assertNotFalse($src, "$page harus bisa dibaca");
            $this->assertStringContainsString('stats-card', $src, "$page: kartu statistik pakai class .stats-card (bukan .stat-card bg-*)");
            $this->assertStringNotContainsString('stat-card bg-', $src, "$page: jangan pakai warna bootstrap acak");
        }
    }

    public function testShellIncludeAda(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/includes/mobile-topbar.php', 'top bar include wajib ada');
        $this->assertFileExists(dirname(__DIR__) . '/includes/bottom-nav.php', 'bottom nav include wajib ada');
    }
}

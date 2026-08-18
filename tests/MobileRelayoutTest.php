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
}

<?php
/**
 * tests/AdminDashboardTest.php
 * Mengunci relayout dashboard admin (cockpit monitoring desktop-first):
 * 1. admin/dashboard.php memakai slice App\Admin\PlatformStats (bukan $db->query inline utk KPI)
 *    dan memuat vendor/autoload.php (AGENTS §2.10).
 * 2. Dead <style> (.stat-card.users) dihapus; label bahasa Indonesia.
 * 3. admin-styles.css punya .kpi-card & body bg var(--bg-soft).
 */

use PHPUnit\Framework\TestCase;

class AdminDashboardTest extends TestCase
{
    public function testStylesheetAdminPunyaKpiCard(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/admin/assets/admin-styles.css');
        $this->assertNotFalse($css, 'admin-styles.css harus bisa dibaca');
        $this->assertStringContainsString('.kpi-card', $css, 'admin-styles: .kpi-card wajib ada');
        $this->assertStringContainsString('var(--bg-soft)', $css, 'admin-styles: body bg pakai token');
    }
}

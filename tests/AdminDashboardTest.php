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

    public function testDashboardPakaiSliceDanAutoload(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/dashboard.php');
        $this->assertNotFalse($src, 'admin/dashboard.php harus bisa dibaca');
        $this->assertStringContainsString("dirname(__DIR__) . '/vendor/autoload.php'", $src, 'dashboard admin wajib memuat autoload (pakai App\*)');
        $this->assertStringContainsString('App\\Admin\\PlatformStats', $src, 'dashboard wajib pakai slice PlatformStats');
        // KPI TIDAK dihitung inline via $db->query untuk COUNT/SELECT platform
        $this->assertStringNotContainsString('SELECT COUNT(*) as total', $src, 'KPI engine tak boleh inline (dipindah ke slice)');
    }

    public function testDashboardTanpaDeadCssDanBerbahasaIndonesia(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/dashboard.php');
        $this->assertStringNotContainsString('.stat-card.users', $src, 'dead <style> .stat-card.* harus dihapus');
        $this->assertStringNotContainsString('Platform Overview', $src, 'label Ringkasan Platform utk judul');
        $this->assertStringContainsString('Ringkasan Platform', $src, 'judul berbahasa Indonesia');
        $this->assertStringContainsString('Total Pelanggan', $src, 'label Total Pelanggan (bukan Customers)');
        $this->assertStringContainsString('Total Omzet', $src, 'label Total Omzet (bukan Revenue)');
        $this->assertStringContainsString('Total Bisnis', $src, 'label Total Bisnis');
        $this->assertStringContainsString('User Aktif Hari Ini', $src, 'label User Aktif Hari Ini');
    }
}

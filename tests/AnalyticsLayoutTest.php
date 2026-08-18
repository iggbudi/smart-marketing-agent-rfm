<?php
/**
 * tests/AnalyticsLayoutTest.php
 * Mengunci penyesuaian layout admin/analytics.php mengikuti gaya dashboard admin:
 * 1. Memakai slice App\Admin\AnalyticsAdmin (bukan query baca inline) + autoload (AGENTS §2.10).
 * 2. Dead <style> .stat-card.* dihapus; KPI pakai .kpi-card (identitas).
 * 3. lang="id" + label berbahasa Indonesia.
 */

use PHPUnit\Framework\TestCase;

class AnalyticsLayoutTest extends TestCase
{
    public function testAnalyticsPakaiSliceDanAutoload(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/analytics.php');
        $this->assertNotFalse($src, 'admin/analytics.php harus bisa dibaca');
        $this->assertStringContainsString("dirname(__DIR__) . '/vendor/autoload.php'", $src, 'wajib autoload (pakai App\*)');
        $this->assertStringContainsString('App\\Admin\\AnalyticsAdmin', $src, 'wajib pakai slice AnalyticsAdmin');
    }

    public function testAnalyticsTanpaDeadCssDanKonsistenIdentitas(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/analytics.php');
        $this->assertStringNotContainsString('.stat-card.revenue', $src, 'dead <style> .stat-card.* harus dihapus');
        $this->assertStringContainsString('kpi-card', $src, 'KPI pakai .kpi-card (identitas dashboard)');
        $this->assertStringContainsString('lang="id"', $src, 'html lang harus id');
    }

    public function testAnalyticsBerbahasaIndonesia(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/analytics.php');
        $this->assertStringNotContainsString('Platform Analytics', $src, 'judul Analitik Platform');
        $this->assertStringContainsString('Analitik Platform', $src, 'judul berbahasa Indonesia');
        $this->assertStringContainsString('Total Pengguna', $src, 'label Total Pengguna');
        $this->assertStringContainsString('Total Omzet', $src, 'label Total Omzet');
        $this->assertStringContainsString('Sesi Aktif', $src, 'label Sesi Aktif');
        $this->assertStringContainsString('Bisnis Terbaik', $src, 'judul tabel Bisnis Terbaik');
        $this->assertStringContainsString('Distribusi Segmen RFM', $src, 'judul Distribusi Segmen RFM');
        $this->assertStringContainsString('Aktivitas Terbaru', $src, 'judul Aktivitas Terbaru');
    }
}

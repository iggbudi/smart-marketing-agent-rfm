<?php
/**
 * tests/ApiManagementLayoutTest.php
 * Mengunci penyesuaian layout admin/api-management.php mengikuti gaya dashboard admin:
 * 1. Memakai slice App\Admin\ApiManagementAdmin (bukan query baca inline) + autoload (AGENTS §2.10).
 * 2. Dead <style> .stat-card.* dihapus; KPI pakai .kpi-card (identitas).
 * 3. lang="id" + label berbahasa Indonesia.
 */

use PHPUnit\Framework\TestCase;

class ApiManagementLayoutTest extends TestCase
{
    public function testApiManagementPakaiSliceDanAutoload(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/api-management.php');
        $this->assertNotFalse($src, 'admin/api-management.php harus bisa dibaca');
        $this->assertStringContainsString("dirname(__DIR__) . '/vendor/autoload.php'", $src, 'wajib autoload (pakai App\*)');
        $this->assertStringContainsString('App\\Admin\\ApiManagementAdmin', $src, 'wajib pakai slice ApiManagementAdmin');
    }

    public function testApiManagementTanpaDeadCssDanKonsistenIdentitas(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/api-management.php');
        $this->assertStringNotContainsString('.stat-card.response', $src, 'dead <style> .stat-card.* harus dihapus');
        $this->assertStringContainsString('kpi-card', $src, 'KPI pakai .kpi-card (identitas dashboard)');
        $this->assertStringContainsString('lang="id"', $src, 'html lang harus id');
        // markup rusak (fragment Error Rate duplikat di luar card) harus beres
        $this->assertStringNotContainsString('Error Rate (24h)', $src, 'fragment markup rusak duplikat dihapus');
    }

    public function testApiManagementBerbahasaIndonesia(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/api-management.php');
        $this->assertStringNotContainsString('API Management', $src, 'judul Manajemen API');
        $this->assertStringContainsString('Manajemen API', $src, 'judul berbahasa Indonesia');
        $this->assertStringContainsString('Total Permintaan', $src, 'label Total Permintaan');
        $this->assertStringContainsString('Permintaan Hari Ini', $src, 'label Permintaan Hari Ini');
        $this->assertStringContainsString('Rata-rata Token', $src, 'label Rata-rata Token');
        $this->assertStringContainsString('Biaya 24 Jam', $src, 'label Biaya 24 Jam');
        $this->assertStringContainsString('Tingkat Error', $src, 'judul Tingkat Error');
        $this->assertStringContainsString('Penggunaan API (Hari Ini)', $src, 'judul chart Penggunaan API');
        $this->assertStringContainsString('Endpoint Teratas', $src, 'judul Endpoint Teratas');
        $this->assertStringContainsString('Kosongkan Log', $src, 'tombol Kosongkan Log');
    }
}

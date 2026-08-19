<?php
/**
 * tests/ReportsLayoutTest.php
 * Mengunci penyesuaian layout admin/reports.php mengikuti gaya dashboard admin:
 * 1. Memakai slice App\Admin\ReportsAdmin (bukan query inline) + autoload (AGENTS §2.10).
 * 2. Dead <style> .stat-card.* dihapus; summary pakai .kpi-card (identitas).
 * 3. lang="id" + label berbahasa Indonesia.
 */

use PHPUnit\Framework\TestCase;

class ReportsLayoutTest extends TestCase
{
    public function testReportsPakaiSliceDanAutoload(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/reports.php');
        $this->assertNotFalse($src, 'admin/reports.php harus bisa dibaca');
        $this->assertStringContainsString("dirname(__DIR__) . '/vendor/autoload.php'", $src, 'wajib autoload (pakai App\*)');
        $this->assertStringContainsString('App\\Admin\\ReportsAdmin', $src, 'wajib pakai slice ReportsAdmin');
    }

    public function testReportsTanpaDeadCssDanKonsistenIdentitas(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/reports.php');
        $this->assertStringNotContainsString('.report-type-btn', $src, 'dead <style> .report-type-btn dihapus');
        $this->assertStringContainsString('lang="id"', $src, 'html lang harus id');
    }

    public function testReportsBerbahasaIndonesia(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/reports.php');
        $this->assertStringNotContainsString('Generate Report', $src, 'tombol Buat Laporan');
        $this->assertStringContainsString('Buat Laporan', $src, 'tombol berbahasa Indonesia');
        $this->assertStringContainsString('Jenis Laporan', $src, 'label Jenis Laporan');
        $this->assertStringContainsString('Tanggal Mulai', $src, 'label Tanggal Mulai');
        $this->assertStringContainsString('Tanggal Selesai', $src, 'label Tanggal Selesai');
        $this->assertStringContainsString('Total Data', $src, 'kartu Total Data');
        $this->assertStringContainsString('Rentang Tanggal', $src, 'kartu Rentang Tanggal');
        $this->assertStringContainsString('Tidak ada data', $src, 'pesan data kosong dalam Bahasa Indonesia');
    }
}

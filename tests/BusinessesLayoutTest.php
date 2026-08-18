<?php
/**
 * tests/BusinessesLayoutTest.php
 * Mengunci penyesuaian layout admin/businesses.php mengikuti gaya dashboard admin:
 * 1. Memakai slice App\Admin\BusinessAdmin (bukan query baca inline) + autoload (AGENTS §2.10).
 * 2. Dead <style> .stat-card.* dihapus; KPI pakai .kpi-card (identitas).
 * 3. lang="id" + label berbahasa Indonesia.
 */

use PHPUnit\Framework\TestCase;

class BusinessesLayoutTest extends TestCase
{
    public function testBusinessesPakaiSliceDanAutoload(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/businesses.php');
        $this->assertNotFalse($src, 'admin/businesses.php harus bisa dibaca');
        $this->assertStringContainsString("dirname(__DIR__) . '/vendor/autoload.php'", $src, 'wajib autoload (pakai App\*)');
        $this->assertStringContainsString('App\\Admin\\BusinessAdmin', $src, 'wajib pakai slice BusinessAdmin');
    }

    public function testBusinessesTanpaDeadCssDanKonsistenIdentitas(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/businesses.php');
        $this->assertStringNotContainsString('.stat-card.active', $src, 'dead <style> .stat-card.* harus dihapus');
        $this->assertStringContainsString('kpi-card', $src, 'KPI pakai .kpi-card (identitas dashboard)');
        $this->assertStringContainsString('lang="id"', $src, 'html lang harus id');
    }

    public function testBusinessesBerbahasaIndonesia(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/businesses.php');
        $this->assertStringNotContainsString('Business Management', $src, 'judul Kelola Bisnis');
        $this->assertStringContainsString('Kelola Bisnis', $src, 'judul berbahasa Indonesia');
        $this->assertStringContainsString('Total Bisnis', $src, 'label Total Bisnis');
        $this->assertStringContainsString('Bisnis Aktif', $src, 'label Bisnis Aktif');
        $this->assertStringContainsString('Total Pelanggan', $src, 'label Total Pelanggan');
        $this->assertStringContainsString('Total Transaksi', $src, 'label Total Transaksi');
        $this->assertStringContainsString('Tambah Bisnis', $src, 'tombol Tambah Bisnis');
        $this->assertStringContainsString('Daftar Bisnis', $src, 'judul tabel Daftar Bisnis');
        $this->assertStringContainsString('<th>Nama Bisnis</th>', $src, 'header Nama Bisnis');
        $this->assertStringContainsString('<th>Pemilik</th>', $src, 'header Pemilik');
        $this->assertStringContainsString('<th>Aksi</th>', $src, 'header Aksi');
    }
}

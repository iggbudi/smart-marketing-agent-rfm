<?php
/**
 * tests/UsersLayoutTest.php
 * Mengunci penyesuaian layout admin/users.php mengikuti gaya dashboard admin:
 * 1. Memakai slice App\Admin\UserAdmin (bukan query baca inline) + autoload (AGENTS §2.10).
 * 2. Dead <style> .stat-card.* dihapus; KPI pakai .kpi-card (identitas).
 * 3. lang="id" + label berbahasa Indonesia (judul, kartu, header tabel, modal).
 */

use PHPUnit\Framework\TestCase;

class UsersLayoutTest extends TestCase
{
    public function testUsersPakaiSliceDanAutoload(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/users.php');
        $this->assertNotFalse($src, 'admin/users.php harus bisa dibaca');
        $this->assertStringContainsString("dirname(__DIR__) . '/vendor/autoload.php'", $src, 'wajib autoload (pakai App\*)');
        $this->assertStringContainsString('App\\Admin\\UserAdmin', $src, 'wajib pakai slice UserAdmin');
    }

    public function testUsersTanpaDeadCssDanKonsistenIdentitas(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/users.php');
        $this->assertStringNotContainsString('.stat-card.users', $src, 'dead <style> .stat-card.* harus dihapus');
        $this->assertStringContainsString('kpi-card', $src, 'KPI pakai .kpi-card (identitas dashboard)');
        $this->assertStringContainsString('lang="id"', $src, 'html lang harus id');
    }

    public function testUsersBerbahasaIndonesia(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/users.php');
        $this->assertStringNotContainsString('User Management', $src, 'judul Manajemen/Kelola User');
        $this->assertStringContainsString('Kelola User', $src, 'judul berbahasa Indonesia');
        $this->assertStringContainsString('Total Pengguna', $src, 'label Total Pengguna');
        $this->assertStringContainsString('Sesi Aktif', $src, 'label Sesi Aktif');
        $this->assertStringContainsString('Super Admin', $src, 'label Super Admin');
        $this->assertStringContainsString('UMKM Owner', $src, 'label UMKM Owner');
        $this->assertStringContainsString('Tambah User', $src, 'tombol Tambah User');
        $this->assertStringContainsString('Daftar User', $src, 'judul tabel Daftar User');
        // Header tabel
        $this->assertStringContainsString('<th>Nama</th>', $src, 'header Nama');
        $this->assertStringContainsString('<th>Peran</th>', $src, 'header Peran');
        $this->assertStringContainsString('<th>Aksi</th>', $src, 'header Aksi');
    }
}

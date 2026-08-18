<?php
/**
 * tests/SettingsLayoutTest.php
 * Mengunci penyesuaian layout admin/settings.php mengikuti gaya dashboard admin:
 * 1. Memakai slice App\Admin\SettingsAdmin (bukan query baca inline) + autoload (AGENTS §2.10).
 * 2. <style> inline dihapus (nav-pills active pakai token di admin-styles); lang="id".
 * 3. Label berbahasa Indonesia; form POST tetap punya CSRF.
 */

use PHPUnit\Framework\TestCase;

class SettingsLayoutTest extends TestCase
{
    public function testSettingsPakaiSliceDanAutoload(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/settings.php');
        $this->assertNotFalse($src, 'admin/settings.php harus bisa dibaca');
        $this->assertStringContainsString("dirname(__DIR__) . '/vendor/autoload.php'", $src, 'wajib autoload (pakai App\*)');
        $this->assertStringContainsString('App\\Admin\\SettingsAdmin', $src, 'wajib pakai slice SettingsAdmin');
    }

    public function testSettingsTanpaInlineStyleDanKonsistenIdentitas(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/settings.php');
        $this->assertStringNotContainsString('#667eea', $src, 'warna ungu lama (inline <style>) dihapus');
        $this->assertStringContainsString('lang="id"', $src, 'html lang harus id');
        // Form POST harus tetap punya CSRF (konvensi)
        $this->assertStringContainsString('csrf_field()', $src, 'form POST wajib csrf_field');
    }

    public function testSettingsBerbahasaIndonesia(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/settings.php');
        $this->assertStringNotContainsString('System Settings', $src, 'judul Pengaturan Sistem');
        $this->assertStringContainsString('Pengaturan Sistem', $src, 'judul berbahasa Indonesia');
        $this->assertStringContainsString('Informasi Sistem', $src, 'judul Informasi Sistem');
        $this->assertStringContainsString('Statistik Platform', $src, 'judul Statistik Platform');
        $this->assertStringContainsString('Cadangkan Database', $src, 'tombol Cadangkan Database');
        $this->assertStringContainsString('Bersihkan Cache', $src, 'tombol Bersihkan Cache');
    }
}

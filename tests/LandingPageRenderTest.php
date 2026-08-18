<?php
/**
 * tests/LandingPageRenderTest.php
 * Smoke test render halaman publik (landing page & login) via CLI.
 * Memastikan struktur landing baru tampil, dan kredensial demo / statistik palsu
 * TIDAK bocor ke publik, serta tidak ada error fatal saat render.
 *
 * Catatan: proses child (`php index.php`) mewarisi env DB test dari
 * tests/bootstrap.php (putenv), jadi tidak pernah menyentuh DB produksi.
 */

use PHPUnit\Framework\TestCase;

class LandingPageRenderTest extends TestCase
{
    /** Render halaman via CLI, kembalikan output HTML (exit 0 wajib). */
    private function renderPage(string $page): string
    {
        $cmd = PHP_BINARY . ' ' . escapeshellarg(dirname(__DIR__) . '/' . $page) . ' 2>&1';
        exec($cmd, $output, $code);
        $this->assertSame(0, $code, "Halaman '$page' gagal dirender (exit code $code):\n" . implode("\n", $output));
        return implode("\n", $output);
    }

    // ---- Landing page (index.php) ----

    public function testLandingPageMenampilkanStrukturSectionBaru()
    {
        $html = $this->renderPage('index.php');

        $markers = [
            'id="hero"',
            'id="fitur"',
            'id="cara-kerja"',
            'id="segmen"',
            'id="faq"',
            'assets/landing.css',
            'assets/landing.js',
            'Loyal Customers',
            'Lost Customers',
        ];
        foreach ($markers as $marker) {
            $this->assertStringContainsString($marker, $html, "Marker '$marker' tidak ditemukan di landing page");
        }
    }

    public function testLandingPageTidakMenampilkanKredensialDemoAtauStatistikPalsu()
    {
        $html = $this->renderPage('index.php');

        $forbidden = ['password123', 'admin@smartmarketing.local', '53+', '5 RFM Segments'];
        foreach ($forbidden as $text) {
            $this->assertStringNotContainsString($text, $html, "Konten '$text' tidak boleh tampil di halaman publik");
        }
    }
}

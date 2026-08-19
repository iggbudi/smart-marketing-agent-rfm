<?php
/**
 * tests/DashboardUmkmLayoutTest.php
 * Mengunci relayout dashboard UMKM owner menjadi "cockpit" (identitas sama dgn admin):
 * 1. user-styles.css punya .kpi-card (identitas kartu KPI).
 * 2. dashboard.php memakai .kpi-card untuk metrik utama + insight segmen berisiko.
 */

use PHPUnit\Framework\TestCase;

class DashboardUmkmLayoutTest extends TestCase
{
    public function testUserStylesheetPunyaKpiCard(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/user-styles.css');
        $this->assertNotFalse($css, 'user-styles.css harus bisa dibaca');
        $this->assertStringContainsString('.kpi-card', $css, 'user-styles: .kpi-card wajib ada (identitas)');
        $this->assertStringContainsString('.kpi-icon', $css, 'user-styles: ikon KPI wajib ada');
    }

    public function testDashboardPakaiKpiCardDanInsight(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/dashboard.php');
        $this->assertNotFalse($src, 'dashboard.php harus bisa dibaca');
        $this->assertStringContainsString('kpi-card', $src, 'dashboard wajib pakai .kpi-card utk metrik utama');
        $this->assertStringContainsString('getAttentionCount', $src, 'dashboard wajib memakai metrik getAttentionCount');
        $this->assertStringContainsString('Butuh Perhatian', $src, 'KPI/label insight "Butuh Perhatian"');
    }
}

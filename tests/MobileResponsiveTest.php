<?php
/**
 * tests/MobileResponsiveTest.php
 * Mengunci perilaku tampilan mobile (struktur, tanpa headless browser).
 * - Semua halaman admin (7) wajib punya tombol .mobile-menu-toggle + toggleSidebar()
 *   dan TIDAK memakai layout grid lama (col-md-2 sidebar) & sidebar inline.
 * - analysis.php: tidak ada <div> nyasar setelah </script>, DataTables pakai scrollX.
 * - user-styles.css & admin-styles.css punya blok @media (max-width:575.98px)
 *   + aturan wrap header.
 */

use PHPUnit\Framework\TestCase;

class MobileResponsiveTest extends TestCase
{
    /** @dataProvider adminPagesProvider */
    public function testAdminPagePunyaMobileToggleTanpaGridLama(string $page): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/' . $page);
        $this->assertNotFalse($src, "admin/$page harus bisa dibaca");

        $this->assertStringContainsString('mobile-menu-toggle', $src, "$page: tombol toggle wajib ada");
        $this->assertStringContainsString('function toggleSidebar()', $src, "$page: JS toggleSidebar wajib ada");
        $this->assertStringNotContainsString('col-md-2 sidebar', $src, "$page: layout grid lama harus dihapus");
    }

    public static function adminPagesProvider(): array
    {
        return [
            'dashboard.php'     => ['dashboard.php'],
            'users.php'         => ['users.php'],
            'businesses.php'    => ['businesses.php'],
            'analytics.php'     => ['analytics.php'],
            'api-management.php'=> ['api-management.php'],
            'settings.php'      => ['settings.php'],
            'reports.php'       => ['reports.php'],
        ];
    }

    public function testAdminPagePakaiSidebarTerpusat(): void
    {
        foreach (['users.php', 'businesses.php'] as $page) {
            $src = file_get_contents(dirname(__DIR__) . '/admin/' . $page);
            $this->assertStringContainsString('includes/sidebar.php', $src, "$page: wajib pakai sidebar terpusat (bukan nav inline)");
        }
    }

    public function testAnalysisTidakAdaDivNyasarDanScrollX(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/analysis.php');
        $this->assertStringNotContainsString('End main-content', $src, 'analysis.php tidak boleh ada <div> nyasar setelah </script>');
        $this->assertStringContainsString('scrollX: true', $src, 'analysis.php DataTables wajib scrollX agar tabel RFM bisa scroll di mobile');
    }

    public function testStylesheetPunyaBlokMobile(): void
    {
        foreach ([
            dirname(__DIR__) . '/assets/user-styles.css',
            dirname(__DIR__) . '/admin/assets/admin-styles.css',
        ] as $css) {
            $src = file_get_contents($css);
            $this->assertStringContainsString('@media (max-width: 575.98px)', $src, basename($css) . ' wajib punya blok mobile');
            $this->assertStringContainsString('flex-wrap: wrap', $src, basename($css) . ' wajib wrap header di mobile');
        }
    }

    public function testMobileToggleDisplayNoneTidakMenimpaMediaQuery(): void
    {
        // Root cause (diverifikasi via Playwright mode mobile, 2026-08-18):
        // aturan dasar `.mobile-menu-toggle { display:none }` ditulis SETELAH blok
        // @media (max-width:768px) yang berisi `display:block`. Cascade CSS memenangkan
        // aturan terakhir => tombol hamburger selalu display:none walau di mobile,
        // sehingga menu sidebar off-canvas tak bisa dibuka (menu mobile hilang).
        foreach ([
            dirname(__DIR__) . '/assets/user-styles.css',
            dirname(__DIR__) . '/admin/assets/admin-styles.css',
        ] as $css) {
            $src = file_get_contents($css);
            $posNone = strpos($src, ".mobile-menu-toggle {\n    display: none;");
            $posToggleMedia = strpos($src, "@media (max-width: 768px) {\n    .mobile-menu-toggle");
            $this->assertNotFalse($posNone, basename($css) . ': aturan dasar display:none wajib ada');
            $this->assertNotFalse($posToggleMedia, basename($css) . ': media query toggle mobile wajib ada');
            $this->assertStringContainsString(
                'display: block',
                substr($src, $posToggleMedia, strpos($src, '}', $posToggleMedia) - $posToggleMedia),
                basename($css) . ': media query toggle harus menampilkan tombol (display: block)'
            );
            $this->assertLessThan(
                $posToggleMedia,
                $posNone,
                basename($css) . ': display:none harus SEBELUM @media toggle, jika setelahnya dia menimpa display:block (bug menu mobile hilang)'
            );
        }
    }
}

# Redesign Landing Page (index.php) — Implementation Plan

**Goal:** Redesign `index.php` menjadi landing page publik berbahasa Indonesia penuh yang
menjual produk dengan jujur: tanpa kredensial demo yang bocor, tanpa statistik palsu
(statistik dinamis dari DB), section lengkap (Hero, Statistik, Fitur, Cara Kerja, Segmen
RFM sesuai `src/Rfm.php`, CTA, FAQ, Footer), dan aset CSS/JS diekstrak ke file eksternal
agar siap-CSP (mendukung item deferred RENCANA_PERBAIKAN 2.4).

**Architecture:** Tetap pola halaman prosedural plain PHP — `index.php` halaman publik
tanpa guard auth (hanya redirect bila session aktif). Statistik diambil dari DB via
`getDB()` (config/database.php) dalam try/catch; bila DB tidak tersedia, section statistik
disembunyikan (graceful, tanpa angka palsu). CSS/JS landing dipisah ke `assets/landing.css`
& `assets/landing.js` (dipakai `index.php`), mengikuti pola `assets/user-styles.css`.
Satu halaman, tanpa form POST (FAQ = accordion Bootstrap client-side), jadi tanpa CSRF.

**Tech Stack:** PHP 7.4+ (runtime 8.3.6), PDO/MariaDB, Bootstrap 5.3 CDN, Font Awesome 6 CDN,
PHPUnit 9.6, `node` untuk lint JS (`node --check`).

**Spec:** Keputusan UI yang disetujui pengguna (percakapan 2026-08-18: "sesuai rekomendasimu"):
redesign `index.php`; akun demo dihapus dari halaman publik; statistik dinamis dari DB;
mockup hero pakai CSS (tanpa screenshot); bahasa Indonesia penuh; section sesuai struktur
yang diusulkan. Acuan teknis: AGENTS.md §2 (PDO/prepared, htmlspecialchars), §7.1 (pola
halaman), §5 (test), §6 (commit), `src/Rfm.php` (5 segmen: Champions, Loyal Customers,
Potential Loyalists, At Risk, Lost Customers — single source of truth, JANGAN diubah).

## Global Constraints
- Satu commit = satu unit kerja (AGENTS.md §6, prefix `feat:` / `security:` / `docs:`).
- Semua query dinamis: PDO prepared statement; output `htmlspecialchars()`. Landing tidak
  punya input user — query statistik statis (tanpa placeholder) tetap memakai PDO.
- `composer test` hijau sebelum commit; test memakai DB `smart_marketing_rfm_test`
  (tests/bootstrap.php putenv → diwarisi child `exec()`), JANGAN pernah arahkan ke DB
  produksi (`smart_marketing_rfm` dari `.env`).
- Jangan commit rahasia (.env, config/*.php). Jangan ubah `src/Rfm.php`.
- Copy segmen di landing harus konsisten dengan `segmentFromScores()` di `src/Rfm.php`
  (5 segmen — bukan 7/8 seperti dokumen lama).

---

### Task 1: Redesign landing page — `index.php` + `assets/landing.css` + `assets/landing.js` + smoke test

**Files:**
- Create: `assets/landing.css` (seluruh style landing; tidak ada `<style>` inline lagi)
- Create: `assets/landing.js` (navbar scroll state, reveal on scroll, tutup menu mobile)
- Rewrite: `index.php` (struktur section baru, bahasa Indonesia, statistik dinamis)
- Create: `tests/LandingPageRenderTest.php` (smoke test render CLI)

**Interfaces:**
- Consumes: `getDB()` dari `config/database.php` (hanya untuk statistik, dibungkus
  try/catch `Throwable`); `PHP_BINARY` (konstanta PHP) untuk render halaman via `exec()`;
  env DB test dari `tests/bootstrap.php` (putenv → diwarisi proses child).
- Produces: halaman publik `index.php` dengan section `id="hero"`, `id="statistik"`
  (kondisional), `id="fitur"`, `id="cara-kerja"`, `id="segmen"`, `id="demo"`, `id="faq"`,
  memakai `assets/landing.css` & `assets/landing.js`; test class `LandingPageRenderTest`
  dengan 2 method (struktur + tidak-bocor).

- [ ] **Step 1: Tulis test yang gagal** — `tests/LandingPageRenderTest.php`:

```php
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
```

- [ ] **Step 2: Jalankan & pastikan gagal** —
  `COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/LandingPageRenderTest.php`
  → FAIL: halaman lama tidak punya `id="cara-kerja"` / `assets/landing.css`, dan memuat
  `password123` (2×), `admin@smartmarketing.local`, `53+`.

- [ ] **Step 3: Implementasi** — buat 3 file berikut:

**3a. `assets/landing.css`** (konten penuh):

```css
/* Smart Marketing Agent — Landing page styles (dipakai index.php) */
:root {
    --brand-1: #667eea;
    --brand-2: #764ba2;
    --grad-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --grad-secondary: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --ink: #1f2937;
    --muted: #6b7280;
    --bg-soft: #f8fafc;
    --border: #e5e7eb;
    --radius: 16px;
    --shadow: 0 10px 30px rgba(102, 126, 234, 0.12);
    --shadow-lg: 0 20px 50px rgba(102, 126, 234, 0.18);
}

html { scroll-behavior: smooth; }

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    color: var(--ink);
    overflow-x: hidden;
}

section[id] { scroll-margin-top: 76px; }

/* ---------- Navbar ---------- */
.navbar-landing {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid transparent;
    transition: border-color .3s ease, box-shadow .3s ease;
}
.navbar-landing.scrolled {
    border-bottom-color: var(--border);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}
.brand-text {
    font-weight: 700;
    background: var(--grad-primary);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.navbar-landing .nav-link { color: var(--ink); font-weight: 500; }
.navbar-landing .nav-link:hover { color: var(--brand-1); }

/* ---------- Hero ---------- */
.hero-section {
    background: var(--grad-primary);
    position: relative;
    overflow: hidden;
    padding: 120px 0 80px;
}
.hero-section::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(600px 400px at 15% 20%, rgba(255, 255, 255, 0.14), transparent 60%),
        radial-gradient(500px 350px at 85% 75%, rgba(255, 255, 255, 0.10), transparent 60%);
}
.hero-section .container { position: relative; z-index: 2; }
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #fff;
    padding: .4rem 1rem;
    border-radius: 50px;
    font-size: .85rem;
    font-weight: 600;
}
.hero-title { color: #fff; }
.hero-subtitle { color: rgba(255, 255, 255, 0.85); }

.btn-gradient {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: var(--grad-secondary);
    color: #fff;
    font-weight: 600;
    padding: .75rem 1.6rem;
    border-radius: 50px;
    border: none;
    text-decoration: none;
    transition: transform .2s ease, box-shadow .2s ease;
}
.btn-gradient:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(79, 172, 254, 0.35); color: #fff; }

.btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: transparent;
    color: #fff;
    font-weight: 600;
    padding: .75rem 1.6rem;
    border-radius: 50px;
    border: 2px solid rgba(255, 255, 255, 0.6);
    text-decoration: none;
    transition: background .2s ease, color .2s ease;
}
.btn-ghost:hover { background: #fff; color: var(--brand-2); }

/* Mockup panel (hero kanan) */
.mockup {
    background: #fff;
    border-radius: 20px;
    box-shadow: var(--shadow-lg);
    padding: 1.5rem;
}
.mockup .mockup-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border);
    padding-bottom: .75rem;
    margin-bottom: 1rem;
}
.segment-chip { border-radius: 10px; color: #fff; font-weight: 600; padding: 1rem; }
.segment-chip small { color: rgba(255, 255, 255, 0.85); font-weight: 400; }

/* ---------- Stats ---------- */
.stats-section { background: var(--bg-soft); padding: 48px 0; }
.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    background: var(--grad-primary);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    line-height: 1.1;
}

/* ---------- Sections generik ---------- */
.section-title {
    font-size: 2rem;
    font-weight: 700;
    background: var(--grad-primary);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: .5rem;
}
.section-subtitle { color: var(--muted); }

/* ---------- Feature cards ---------- */
.feature-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 2rem;
    height: 100%;
    transition: transform .25s ease, box-shadow .25s ease;
}
.feature-card:hover { transform: translateY(-6px); box-shadow: var(--shadow); }
.feature-icon {
    width: 64px; height: 64px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; color: #fff;
    margin-bottom: 1.25rem;
}
.icon-rfm { background: var(--grad-primary); }
.icon-ai { background: var(--grad-secondary); }
.icon-insight { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

/* ---------- Cara kerja ---------- */
.step-item { position: relative; text-align: center; }
.step-number {
    width: 56px; height: 56px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    background: var(--grad-primary);
    color: #fff;
    font-size: 1.4rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}

/* ---------- Segmen RFM (5 segmen sesuai src/Rfm.php) ---------- */
.seg-card { border-radius: var(--radius); padding: 1.5rem; height: 100%; color: #fff; }
.seg-card .seg-icon { font-size: 1.8rem; margin-bottom: .75rem; }
.seg-card p { color: rgba(255, 255, 255, 0.9); margin-bottom: 0; }
.seg-champions { background: linear-gradient(135deg, #f59e0b, #f97316); }
.seg-loyal { background: linear-gradient(135deg, #10b981, #059669); }
.seg-potential { background: linear-gradient(135deg, #3b82f6, #6366f1); }
.seg-risk { background: linear-gradient(135deg, #ef4444, #dc2626); }
.seg-lost { background: linear-gradient(135deg, #6b7280, #4b5563); }

/* ---------- CTA ---------- */
.cta-section { background: var(--grad-primary); }
.cta-section h2 { color: #fff; }
.cta-section p { color: rgba(255, 255, 255, 0.85); }

/* ---------- Footer ---------- */
.footer { background: #1f2937; color: rgba(255, 255, 255, 0.8); }
.footer a { color: rgba(255, 255, 255, 0.8); text-decoration: none; }
.footer a:hover { color: #fff; }
.footer .footer-head { color: #fff; font-weight: 700; }

/* ---------- Reveal on scroll ---------- */
.reveal { opacity: 0; transform: translateY(24px); transition: opacity .6s ease, transform .6s ease; }
.reveal.revealed { opacity: 1; transform: none; }

@media (max-width: 991.98px) {
    .hero-section { padding: 96px 0 56px; }
}
```

**3b. `assets/landing.js`** (konten penuh):

```js
// Smart Marketing Agent — Landing page interactions (dipakai index.php)
(function () {
    'use strict';

    var navbar = document.querySelector('.navbar-landing');

    // Navbar: beri bayangan saat di-scroll
    function updateNavbar() {
        if (navbar) {
            navbar.classList.toggle('scrolled', window.scrollY > 10);
        }
    }
    window.addEventListener('scroll', updateNavbar, { passive: true });
    updateNavbar();

    // Reveal on scroll (IntersectionObserver, fallback tampilkan semua)
    var reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && reveals.length > 0) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        reveals.forEach(function (el) { observer.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('revealed'); });
    }

    // Tutup menu mobile setelah klik link anchor
    var navCollapse = document.getElementById('navLanding');
    if (navCollapse) {
        navCollapse.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (navCollapse.classList.contains('show')) {
                    var instance = bootstrap.Collapse.getInstance(navCollapse);
                    if (instance) { instance.hide(); }
                }
            });
        });
    }
})();
```

**3c. `index.php`** (rewrite penuh — pertahankan redirect session di bagian atas):

```php
<?php
// Landing page publik. Jika sudah login, arahkan ke dashboard sesuai peran.
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'super_admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

// Statistik ringkas (dinamis dari DB). Bila DB tidak tersedia, section disembunyikan.
require_once 'config/database.php';
$stats = null;
try {
    $db = getDB();
    $row = $db->query(
        "SELECT
            (SELECT COUNT(*) FROM businesses) AS businesses,
            (SELECT COUNT(*) FROM customers) AS customers,
            (SELECT COUNT(*) FROM transactions) AS transactions"
    )->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stats = [
            'businesses'   => (int)$row['businesses'],
            'customers'    => (int)$row['customers'],
            'transactions' => (int)$row['transactions'],
        ];
    }
} catch (Throwable $e) {
    error_log('Landing stats gagal dimuat: ' . $e->getMessage());
    $stats = null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Marketing Agent — Analisis RFM untuk UMKM Indonesia</title>
    <meta name="description" content="Platform analisis pelanggan berbasis RFM (Recency, Frequency, Monetary) untuk UMKM Indonesia: segmentasi otomatis, insight data, dan pembuatan konten pemasaran dengan AI.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/landing.css" rel="stylesheet">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-landing fixed-top">
        <div class="container">
            <a class="navbar-brand brand-text" href="#hero">
                <i class="fas fa-chart-line me-2"></i>Smart Marketing Agent
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navLanding" aria-controls="navLanding" aria-expanded="false" aria-label="Buka menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navLanding">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="#fitur">Fitur</a></li>
                    <li class="nav-item"><a class="nav-link" href="#cara-kerja">Cara Kerja</a></li>
                    <li class="nav-item"><a class="nav-link" href="#segmen">Segmen</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-primary rounded-pill px-4" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Login
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section id="hero" class="hero-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="hero-badge mb-3"><i class="fas fa-star"></i> Dirancang untuk UMKM Indonesia</span>
                    <h1 class="hero-title display-4 fw-bold mb-3">
                        Pahami Pelanggan Anda,<br>Tumbuhkan Bisnis UMKM
                    </h1>
                    <p class="hero-subtitle lead mb-4">
                        Analisis RFM (Recency, Frequency, Monetary) otomatis: tahu siapa pelanggan terbaik,
                        siapa yang berisiko pergi, dan apa yang harus dilakukan — tanpa perlu jadi ahli data.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="login.php" class="btn-gradient"><i class="fas fa-rocket me-1"></i>Mulai Sekarang</a>
                        <a href="#cara-kerja" class="btn-ghost"><i class="fas fa-play me-1"></i>Lihat Cara Kerja</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="mockup">
                        <div class="mockup-head">
                            <strong><i class="fas fa-users me-2 text-primary"></i>Segmen Pelanggan</strong>
                            <span class="badge bg-success">Aktif</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-6"><div class="segment-chip" style="background: linear-gradient(135deg,#f59e0b,#f97316);"><i class="fas fa-trophy me-1"></i>Champions<br><small>Pelanggan terbaik</small></div></div>
                            <div class="col-6"><div class="segment-chip" style="background: linear-gradient(135deg,#10b981,#059669);"><i class="fas fa-heart me-1"></i>Loyal Customers<br><small>Rutin belanja</small></div></div>
                            <div class="col-6"><div class="segment-chip" style="background: linear-gradient(135deg,#3b82f6,#6366f1);"><i class="fas fa-star me-1"></i>Potential Loyalists<br><small>Calon setia</small></div></div>
                            <div class="col-6"><div class="segment-chip" style="background: linear-gradient(135deg,#ef4444,#dc2626);"><i class="fas fa-exclamation me-1"></i>At Risk<br><small>Perlu perhatian</small></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistik (dinamis dari DB; disembunyikan bila DB tidak tersedia) -->
    <?php if ($stats): ?>
    <section id="statistik" class="stats-section">
        <div class="container">
            <div class="row text-center g-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-number"><?= number_format($stats['businesses']) ?></div>
                    <h6 class="fw-bold mt-2 mb-0">Bisnis Terdaftar</h6>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-number"><?= number_format($stats['customers']) ?></div>
                    <h6 class="fw-bold mt-2 mb-0">Pelanggan Dikelola</h6>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-number"><?= number_format($stats['transactions']) ?></div>
                    <h6 class="fw-bold mt-2 mb-0">Transaksi Dianalisis</h6>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-number">5</div>
                    <h6 class="fw-bold mt-2 mb-0">Segmen Pelanggan</h6>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Fitur -->
    <section id="fitur" class="py-5">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">Fitur Unggulan</h2>
                <p class="section-subtitle lead">Solusi lengkap analisis pelanggan untuk UMKM</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 reveal">
                    <div class="feature-card">
                        <div class="feature-icon icon-rfm"><i class="fas fa-chart-pie"></i></div>
                        <h4 class="fw-bold mb-2">Analisis RFM Otomatis</h4>
                        <p class="text-muted mb-0">Skor Recency, Frequency, dan Monetary tiap pelanggan dihitung otomatis dari data transaksi Anda, lalu dikelompokkan ke segmen yang jelas.</p>
                    </div>
                </div>
                <div class="col-lg-4 reveal">
                    <div class="feature-card">
                        <div class="feature-icon icon-ai"><i class="fas fa-magic"></i></div>
                        <h4 class="fw-bold mb-2">Konten Pemasaran dengan AI</h4>
                        <p class="text-muted mb-0">Buat caption Instagram, pesan WhatsApp, atau email promosi yang disesuaikan dengan tiap segmen pelanggan — langsung dari platform.</p>
                    </div>
                </div>
                <div class="col-lg-4 reveal">
                    <div class="feature-card">
                        <div class="feature-icon icon-insight"><i class="fas fa-lightbulb"></i></div>
                        <h4 class="fw-bold mb-2">Dashboard & Ekspor Laporan</h4>
                        <p class="text-muted mb-0">Pantau kinerja bisnis lewat dashboard interaktif dan ekspor data pelanggan/transaksi ke CSV atau Excel untuk kebutuhan Anda.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Cara Kerja -->
    <section id="cara-kerja" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">Cara Kerja</h2>
                <p class="section-subtitle lead">Tiga langkah menuju keputusan berbasis data</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 reveal">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <h5 class="fw-bold">Unggah Data Transaksi</h5>
                        <p class="text-muted mb-0">Impor data pelanggan & transaksi dari file Excel atau CSV (format .xlsx, .xls, .csv, maksimal 5 MB).</p>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <h5 class="fw-bold">Analisis RFM Otomatis</h5>
                        <p class="text-muted mb-0">Sistem menghitung skor Recency, Frequency, Monetary dan mengelompokkan pelanggan ke segmen secara otomatis.</p>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <h5 class="fw-bold">Buat Aksi Pemasaran</h5>
                        <p class="text-muted mb-0">Pilih segmen target, buat konten promosi dengan bantuan AI, lalu kirim pesan yang tepat sasaran.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Segmen RFM (5 segmen sesuai src/Rfm.php) -->
    <section id="segmen" class="py-5">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">5 Segmen Pelanggan</h2>
                <p class="section-subtitle lead">Setiap pelanggan otomatis dikelompokkan agar perlakuan pemasaran tepat sasaran</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="seg-card seg-champions">
                        <div class="seg-icon"><i class="fas fa-trophy"></i></div>
                        <h5 class="fw-bold">Champions</h5>
                        <p>Pelanggan terbaik — sering belanja, baru belanja, nilai tinggi. Pertahankan dengan reward & program member VIP.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="seg-card seg-loyal">
                        <div class="seg-icon"><i class="fas fa-heart"></i></div>
                        <h5 class="fw-bold">Loyal Customers</h5>
                        <p>Pelanggan yang rutin belanja. Pertahankan dengan program loyalitas agar naik kelas menjadi Champions.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="seg-card seg-potential">
                        <div class="seg-icon"><i class="fas fa-star"></i></div>
                        <h5 class="fw-bold">Potential Loyalists</h5>
                        <p>Pelanggan baru dengan nilai belanja tinggi. Dorong repeat purchase dengan penawaran menarik.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="seg-card seg-risk">
                        <div class="seg-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <h5 class="fw-bold">At Risk</h5>
                        <p>Dulu aktif, kini mulai jarang belanja. Hubungi segera dengan penawaran spesial sebelum benar-benar pergi.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="seg-card seg-lost">
                        <div class="seg-icon"><i class="fas fa-user-slash"></i></div>
                        <h5 class="fw-bold">Lost Customers</h5>
                        <p>Sudah lama tidak belanja. Kirim kampanye "kami rindu" atau diskon comeback untuk mengajak kembali.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 d-flex align-items-stretch reveal">
                    <div class="feature-card w-100 d-flex flex-column justify-content-center text-center">
                        <i class="fas fa-chart-line text-primary mb-3" style="font-size: 2rem;"></i>
                        <h5 class="fw-bold mb-2">Siap melihat segmen pelanggan Anda?</h5>
                        <div><a href="login.php" class="btn btn-primary rounded-pill px-4"><i class="fas fa-sign-in-alt me-1"></i>Login</a></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section id="demo" class="cta-section py-5">
        <div class="container text-center py-4">
            <h2 class="display-6 fw-bold mb-3">Siap memahami pelanggan Anda?</h2>
            <p class="lead mb-4 mx-auto" style="max-width: 640px;">
                Masuk ke platform dan mulai analisis RFM untuk bisnis Anda hari ini.
            </p>
            <a href="login.php" class="btn btn-light btn-lg rounded-pill px-5 fw-bold">
                <i class="fas fa-sign-in-alt me-2"></i>Masuk ke Platform
            </a>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">Pertanyaan Umum</h2>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true" aria-controls="faq1">
                                    Apa itu analisis RFM?
                                </button>
                            </h3>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    RFM adalah singkatan dari <strong>Recency</strong> (berapa lama sejak pembelian terakhir), <strong>Frequency</strong> (berapa sering belanja), dan <strong>Monetary</strong> (berapa besar total belanja). Platform menilai ketiganya lalu mengelompokkan pelanggan ke segmen yang mudah dipahami.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                                    Bagaimana cara mulai menggunakannya?
                                </button>
                            </h3>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Admin platform membuat akun untuk UMKM. Setelah masuk, Anda melengkapi profil bisnis, mengunggah data transaksi (Excel/CSV), dan sistem otomatis menghitung analisis RFM.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                                    Apakah data bisnis saya aman?
                                </button>
                            </h3>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Ya. Data setiap bisnis diisolasi per akun pemilik, semua query memakai prepared statement, form dilindungi CSRF, dan sesi login dikelola dengan aman. Detail lebih lanjut tersedia di dokumentasi keamanan.
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                                    Bisa ekspor hasil analisis?
                                </button>
                            </h3>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Bisa. Pelanggan dan transaksi dapat diekspor ke CSV atau Excel (XLSX) langsung dari halaman data.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <h5 class="footer-head mb-3"><i class="fas fa-chart-line me-2"></i>Smart Marketing Agent</h5>
                    <p class="mb-3">Platform analisis pelanggan berbasis RFM untuk UMKM Indonesia — segmentasi otomatis, insight data, dan konten pemasaran berbantuan AI.</p>
                </div>
                <div class="col-lg-3">
                    <h6 class="footer-head mb-3">Navigasi</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><a href="#fitur">Fitur</a></li>
                        <li class="mb-2"><a href="#cara-kerja">Cara Kerja</a></li>
                        <li class="mb-2"><a href="#segmen">Segmen RFM</a></li>
                        <li class="mb-2"><a href="#faq">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-lg-4">
                    <h6 class="footer-head mb-3">Akses</h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><a href="login.php"><i class="fas fa-sign-in-alt me-2"></i>Login</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4" style="border-color: rgba(255,255,255,0.15);">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <p class="mb-0 small">&copy; 2026 Smart Marketing Agent. Semua hak dilindungi.</p>
                <small class="d-inline-flex align-items-center"><i class="fas fa-heart text-danger me-1"></i>Dibuat untuk UMKM Indonesia</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/landing.js"></script>
</body>
</html>
```

Catatan implementasi:
- `getDB()` melempar `RuntimeException` (config/database.php membungkus PDOException) —
  `catch (Throwable)` menutup keduanya; detail error hanya ke `error_log`, tidak ke output.
- Query statistik statis (tanpa input user) — PDO `query()` aman; tidak perlu placeholder.
  Output `number_format()` untuk int hasil COUNT (bukan input user, tidak perlu htmlspecialchars).
- Redirect login di atas halaman **dipertahankan** (perilaku lama, jangan diubah).

- [ ] **Step 4: Jalankan & pastikan pass** —
  `COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/LandingPageRenderTest.php` → 2 test PASS.

- [ ] **Step 5: Lint & test penuh** —
  - `php -l index.php`
  - `node --check assets/landing.js` (CSS tidak ada linter di repo; verifikasi visual via render)
  - `COMPOSER_ALLOW_SUPERUSER=1 composer test` → seluruh suite hijau (termasuk
    AuthManagerTest/RfmTest/ExportTest yang sudah ada — tidak boleh regress)
  - Smoke render manual: `php index.php > /tmp/landing.html; grep -c password123 /tmp/landing.html` → 0

- [ ] **Step 6: Commit** —
  `git add assets/landing.css assets/landing.js index.php tests/LandingPageRenderTest.php`
  `git commit -m "feat: redesign landing page (index.php) — bahasa Indonesia penuh, statistik dinamis dari DB, kredensial demo & stat palsu dihapus, aset CSS/JS diekstrak (siap-CSP)"`

---

### Task 2: Hapus kredensial demo & teks development dari `login.php`

**Files:**
- Modify: `login.php` (hapus blok "Demo Accounts (Development)" + footer "Development Version")
- Modify: `tests/LandingPageRenderTest.php` (tambah method test login page)

**Interfaces:**
- Consumes: `renderPage()` yang sama dari Task 1 (`tests/LandingPageRenderTest.php`).
- Produces: `login.php` tanpa bocoran kredensial demo; test method
  `testLoginPageTidakMenampilkanKredensialDemo`.

- [ ] **Step 1: Tulis test yang gagal** — tambahkan method ini ke
  `tests/LandingPageRenderTest.php` (setelah method landing):

```php
    // ---- Login page (login.php) ----

    public function testLoginPageTidakMenampilkanKredensialDemo()
    {
        $html = $this->renderPage('login.php');

        $this->assertStringNotContainsString('password123', $html);
        $this->assertStringNotContainsString('admin@smartmarketing.local', $html);
        $this->assertStringNotContainsString('Development Version', $html);
    }
```

- [ ] **Step 2: Jalankan & pastikan gagal** —
  `COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/LandingPageRenderTest.php`
  → method baru FAIL (login.php masih memuat `password123`, `admin@smartmarketing.local`,
  "Development Version"). 2 method lama tetap PASS.

- [ ] **Step 3: Implementasi** — `login.php`, dua edit (pakai `edit` tool, oldText harus
  match persis):

  **Edit A** — hapus blok demo accounts (dari `</form>` hingga penutup `.p-4`):

  oldText:
  ```
                            </form>
                            
                            <hr>
                            
                            <div class="text-center">
                                <h6 class="text-muted mb-3">Demo Accounts (Development):</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-primary"><strong>Super Admin</strong></small><br>
                                        <small>admin@smartmarketing.local</small><br>
                                        <small>password123</small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-success"><strong>UMKM Owner</strong></small><br>
                                        <small>budi@batiksemarang.com</small><br>
                                        <small>password123</small>
                                    </div>
                                </div>
                            </div>
                        </div>
  ```

  newText:
  ```
                            </form>
                            
                        </div>
  ```

  **Edit B** — hapus footer "Development Version - Localhost Environment":

  oldText:
  ```
                    <div class="text-center mt-3">
                        <small class="text-light">
                            <i class="fas fa-code"></i> Development Version - Localhost Environment
                        </small>
                    </div>
  ```

  newText: (kosong — hapus blok; jangan sentuh struktur lain di login.php, termasuk
  `csrf_field()` di form, validasi, dan redirect session)

- [ ] **Step 4: Jalankan & pastikan pass** —
  `COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/LandingPageRenderTest.php` → 3 method PASS.

- [ ] **Step 5: Lint & test penuh** —
  - `php -l login.php`
  - `COMPOSER_ALLOW_SUPERUSER=1 composer test` → seluruh suite hijau
  - Smoke: `php login.php | grep -c password123` → 0

- [ ] **Step 6: Commit** —
  `git add login.php tests/LandingPageRenderTest.php`
  `git commit -m "security: hapus kredensial demo & teks development dari login.php (tidak bocor ke publik)"`

---

### Task 3: Update README (file structure)

**Files:**
- Modify: `README.md:191-227` (bagian `## File Structure`)

**Interfaces:**
- Consumes: — (dokumentasi)
- Produces: tree file structure yang akurat untuk aset landing.

- [ ] **Step 1–4: (tanpa test — dokumentasi murni).** Edit tree `assets/` di README:

  oldText:
  ```
  ├── assets/                   # Static assets
  │   ├── css/                # Custom stylesheets
  │   ├── js/                 # JavaScript files
  │   └── images/             # Image assets
  ```

  newText:
  ```
  ├── assets/                   # Static assets
  │   ├── landing.css         # Stylesheet landing page (index.php)
  │   ├── landing.js          # Interaksi landing page (index.php)
  │   └── user-styles.css     # Stylesheet dashboard user
  ```

- [ ] **Step 5: Verifikasi** — `composer test` tetap hijau (tidak ada kode berubah);
  baca ulang bagian yang diedit untuk memastikan tree konsisten.

- [ ] **Step 6: Commit** —
  `git add README.md`
  `git commit -m "docs: update file structure README (assets landing page)"`

---

## Self-Review

1. **Coverage spec** (keputusan pengguna 2026-08-18):
   - Redesign `index.php` → Task 1. ✅
   - Hapus akun demo dari publik → Task 1 (landing) + Task 2 (login). ✅
   - Statistik dinamis dari DB → Task 1 (`getDB()` + try/catch, graceful). ✅
   - Mockup CSS tanpa screenshot → Task 1 hero. ✅
   - Bahasa Indonesia penuh → Task 1 (semua copy ID; test tidak lagi mengharuskan
     "Features" EN). ✅
   - Section: Navbar, Hero, Statistik, Fitur, Cara Kerja, Segmen (5 real), CTA, FAQ,
     Footer → Task 1. ✅
   - Aset eksternal (siap-CSP, mendukung RENCANA 2.4) → Task 1 (`assets/landing.css|js`,
     tanpa AOS, tanpa `<style>`/`<script>` inline di `index.php`). ✅
2. **Scan placeholder:** tidak ada TBD/TODO; semua step berisi kode aktual atau
   oldText/newText persis. ✅
3. **Konsistensi:** segmen = 5 sesuai `src/Rfm.php` (`segmentFromScores()`); id section
   konsisten antara HTML (`id="cara-kerja"`), nav (`href="#cara-kerja"`), dan test
   (`id="cara-kerja"`); nama aset `landing.css`/`landing.js` konsisten di test & HTML. ✅
4. **Tidak menyentuh:** `src/Rfm.php`, `config/*`, `includes/*`, halaman lain, DB schema.
   Tidak ada migrasi SQL. Tidak ada form POST baru (FAQ client-side → tanpa CSRF).

## Handoff

Setelah plan disetujui, eksekusi task-by-task di sesi ini (inline), satu commit per task,
checkpoint + verifikasi dengan skill `verification-before-completion` di tiap akhir task.

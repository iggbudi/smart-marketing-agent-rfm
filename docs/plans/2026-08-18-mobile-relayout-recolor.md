# Relayout & Recolor UI Mobile untuk Segmen UMKM Indonesia — Implementation Plan

**Goal:** Merombak tampilan mobile aplikasi (halaman UMKM owner, 7 halaman) agar
konsisten, hangat, dan mudah dipakai pemilik UMKM Indonesia di smartphone:
satu identitas warna (hijau-teal + amber), shell mobile modern (top bar + sidebar
overlay + navigasi bawah), tabel berubah jadi kartu, dan seluruh label berbahasa
Indonesia.

**Architecture:** Saat ini UI sudah "responsif secara teknis" (off-canvas sidebar,
tabel scrollX, blok `@media`) tetapi **bukan desain mobile-first**: warna tidak
konsisten (sidebar user biru-cyan `#2193b0→#6dd5ed`, tombol primary ungu
`#667eea→#764ba2`, landing/login ungu), stats cards punya 2 pola berbeda
(`.stats-card` gradient di dashboard vs `.stat-card bg-*` Bootstrap di
customers/transactions), tabel tetap harus di-scroll horizontal, tidak ada top bar
maupun navigasi bawah, dan label dashboard masih bahasa Inggris.

Perbaikan = **design tokens** (CSS variables) + **shell mobile reusable**
(`includes/mobile-topbar.php`, `includes/bottom-nav.php`, `assets/mobile.js`,
`assets/table-cards.js`) yang dipasang konsisten di 7 halaman user. Tanpa build
step (plain CSS/JS + Bootstrap 5 CDN). Semua perubahan visual via CSS/JS/markup
— TIDAK menyentuh query, aturan bisnis, `src/*`, atau CSRF.

**Tech Stack:** PHP 7.4+ (runtime 8.3.6), Bootstrap 5.3 CDN, Chart.js, DataTables
1.13.6, PHPUnit 9.6 (DB `smart_marketing_rfm_test`), plain CSS/JS.

**Spec:** Permintaan pengguna: "relayout dan recolor UI untuk tampilan mobile,
harus sesuai dengan target segmen, pecah jadi sprint" (2026-08-18). Acuan:
AGENTS.md §2/§8, skill `writing-plans`, plan existing
`docs/plans/2026-08-18-optimasi-mobile.md` (selesai — fondasi responsif teknis).

---

## 1. Target Segmen & Implikasi Desain

**Persona (dari data demo & konteks produk):** Budi, 38 tahun, pemilik UMKM
fashion batik ("Batik Semarang"). Akses utama: **smartphone Android entry-level
(360–412px)**, koneksi data terbatas, bukan pengguna teknis. Kebiasaan: familiar
dengan pola aplikasi Indonesia (WhatsApp, e-commerce) — navigasi bawah, kartu,
tombol besar, bahasa Indonesia. Tujuan: cepat tahu omzet, pelanggan yang "mulai
tidur" (At Risk), dan konten promo.

| Karakteristik segmen | Implikasi desain (diputuskan di plan ini) |
|---|---|
| Android murah, layar 360–412px | Breakpoint mobile 575.98px & 767.98px, konten satu kolom, tanpa asset berat (tetap CDN) |
| Non-teknis, "lihat sekilas" | Angka besar, ikon jelas, label bahasa Indonesia, kardus (card) per entitas |
| Jempol, sering satu tangan | Bottom navigation 5 menu + FAB "+", touch target ≥44px, input ≥16px (anti auto-zoom iOS/Android) |
| Omzet = uang, pertumbuhan | Warna brand **hijau-teal** (tumbuh, uang, netral) + aksen **amber** (optimisme/sukses); bukan ungu korporat |
| Emosi: takut kehilangan pelanggan | Segmen RFM diberi warna semantik konsisten (Champions amber, Loyal hijau, Potential biru, At Risk merah, Lost abu) |
| Data plan terbatas | Font sistem (`system-ui`), tanpa font web tambahan |

**Palet brand baru (satu identitas di semua stylesheet):**

```
--brand-1: #0f766e   (teal-700)        → sidebar, link, fokus
--brand-2: #059669   (emerald-600)      → gradasi
--grad-brand: linear-gradient(135deg, #0f766e 0%, #059669 100%)
--accent:   #f59e0b  (amber-500)        → CTA sekunder, highlight, omzet
--danger:   #dc2626                     → hapus, At Risk
--bg-soft:  #f6f7f4  (netral hangat)    → background body
--ink:      #1f2937  --muted: #6b7280   --border: #e5e7eb
```

Palet segmen RFM (disamakan dengan `assets/landing.css` yang sudah ada):
`seg-champions` amber, `seg-loyal` hijau, `seg-potential` biru, `seg-risk` merah,
`seg-lost` abu.

---

## 2. Peta File (Struktur Dulu)

| File | Status | Tanggung jawab |
|---|---|---|
| `assets/user-styles.css` | Modify | Design tokens `:root`, recolor sidebar/btn/stats, shell mobile (topbar, backdrop, bottom nav), komponen (card view, FAB, modal bottom-sheet) |
| `admin/assets/admin-styles.css` | Modify | Tokens + recolor identitas sama (admin ikut brand, tanpa relayout) |
| `assets/landing.css`, `assets/login.css` | Modify | Recolor `--brand-*`/gradient ke identitas baru (konsistensi global) |
| `includes/mobile-topbar.php` | Create | Top bar mobile reusable (hamburger + judul halaman + avatar) — SATU sumber |
| `includes/bottom-nav.php` | Create | Bottom navigation mobile (5 menu utama) — SATU sumber |
| `assets/mobile.js` | Create | Backdrop sidebar + close-on-outside + sinkron `.show` |
| `assets/table-cards.js` | Create | Transformasi `<table class="table-cards-target">` → kartu di ≤575px |
| `dashboard.php`, `customers.php`, `transactions.php`, `analysis.php`, `upload.php`, `ai-content.php`, `profile.php` | Modify | Pasang shell (topbar + bottom-nav + mobile.js), class `table-cards-target` (customers/transactions), FAB (customers/transactions), label Indonesia (dashboard), h1→h2 (ai-content) |
| `tests/MobileRelayoutTest.php` | Create | Regression: tokens, shell terpasang di 7 halaman, komponen, label Indonesia |
| `README.md`, `AGENTS.md` | Modify | Catat dukungan UI mobile baru |

Test existing yang TIDAK boleh rusak (dipertahankan apa adanya):
`MobileResponsiveTest` (posisi `.mobile-menu-toggle { display:none }` sebelum
`@media (max-width:768px)`, blok `@media (max-width:575.98px)` + `flex-wrap`).
`MobileMenuToggleTest` di-*update* SAJA pada 1 method strukturalnya di Task 2.4
karena tombol toggle pindah ke include `mobile-topbar.php` (invariant baru: tombol
tersedia via include yang wajib dipasang semua halaman; test render CLI yang
memeriksa HTML AKHIR tetap utuh dan PASS). `function toggleSidebar()` inline di
tiap halaman TETAP dipertahankan (diunci test render & dipakai onclick tombol).

---

## 3. Sprint Overview

| Sprint | Isi | Commit utama |
|---|---|---|
| **S1** Design System & Recolor | Tokens, recolor 4 stylesheet, konsolidasi stats cards, test | `feat(ui): design tokens & recolor ke identitas hijau-teal-amber` |
| **S2** Mobile Shell | topbar + bottom-nav + backdrop + mobile.js, pasang di 7 halaman | `feat(mobile): shell top bar, sidebar overlay & bottom navigation` |
| **S3** Komponen Mobile | stats 2 kolom, tabel→kartu, modal bottom-sheet, FAB | `feat(mobile): komponen kartu, bottom-sheet modal & FAB` |
| **S4** Bahasa & Microcopy | Label dashboard Indonesia, heading konsisten | `feat(i18n): label dashboard bahasa Indonesia & heading konsisten` |
| **S5** E2E & Docs | Playwright mobile ad-hoc, README/AGENTS, verifikasi final | `docs: catat UI mobile baru (top bar, bottom nav, card view)` |

---

## 4. Global Constraints

- Satu commit = satu unit kerja (AGENTS.md §6). Prefix: `feat:`/`fix:`/`test:`/`docs:`.
- TIDAK ada form POST baru → tanpa tambahan CSRF (hanya markup/CSS/JS; tombol yang
  ditambah = `data-bs-toggle="modal"`, bukan submit).
- Output tetap `htmlspecialchars`; jangan pindahkan query/aturan bisnis; jangan
  sentuh `src/*`, `config/*`, `database_*.sql`.
- `composer test` hijau sebelum commit (baseline saat ini: OK 68 tests).
- Jangan commit rahasia. Sidebar tetap SATU sumber (`includes/sidebar.php`).
- Test lama (`MobileResponsiveTest`, `MobileMenuToggleTest`) harus tetap hijau —
  pola `.mobile-menu-toggle { display:none }` → `@media (max-width:768px)` dan
  inline `function toggleSidebar()` DIHAPUS tidak boleh.

---

# Sprint 1 — Design System & Recolor Global

## Task 1.1: Design tokens + recolor `assets/user-styles.css`

**Files:**
- Modify: `assets/user-styles.css` (tambah `:root` tokens; ubah warna sidebar,
  `.btn-primary`, `.stats-card`, focus, `body` background, upload-area)
- Test: `tests/MobileRelayoutTest.php` (baru, sebagian — token)

**Step 1 — Tulis test gagal** (`tests/MobileRelayoutTest.php`, dibuat di task ini,
diperluas di task berikutnya):

```php
<?php
/**
 * tests/MobileRelayoutTest.php
 * Mengunci relayout & recolor UI mobile (segmen UMKM Indonesia):
 * 1. Design tokens (--brand-*) ada di user-styles.css & admin-styles.css.
 * 2. Shell mobile (topbar/bottom-nav/backdrop/mobile.js) terpasang di 7 halaman user.
 * 3. Komponen: table-cards.js di customers/transactions, FAB, label Indonesia di dashboard.
 */

use PHPUnit\Framework\TestCase;

class MobileRelayoutTest extends TestCase
{
    public static function halamanUserProvider(): array
    {
        return [
            'dashboard.php'   => ['dashboard.php'],
            'customers.php'   => ['customers.php'],
            'transactions.php'=> ['transactions.php'],
            'analysis.php'    => ['analysis.php'],
            'upload.php'      => ['upload.php'],
            'ai-content.php'  => ['ai-content.php'],
            'profile.php'     => ['profile.php'],
        ];
    }

    public function testUserStylesheetPunyaDesignTokens(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/user-styles.css');
        $this->assertStringContainsString('--brand-1: #0f766e', 'user-styles.css: token brand-1 (teal) wajib ada');
        $this->assertStringContainsString('--brand-2: #059669', 'user-styles.css: token brand-2 (emerald) wajib ada');
        $this->assertStringContainsString('--accent: #f59e0b', 'user-styles.css: token accent (amber) wajib ada');
        $this->assertStringContainsString('var(--grad-brand)', 'user-styles.css: sidebar/btn wajib pakai var(--grad-brand)');
    }

    public function testAdminStylesheetPunyaDesignTokens(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/admin/assets/admin-styles.css');
        $this->assertStringContainsString('--brand-1: #0f766e', 'admin-styles.css: token brand-1 wajib ada');
        $this->assertStringContainsString('var(--grad-brand)', 'admin-styles.css: sidebar wajib pakai var(--grad-brand)');
    }
}
```

**Step 2 — Jalankan & pastikan gagal:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
```
→ FAIL (`--brand-1` belum ada). `MobileResponsiveTest` & `MobileMenuToggleTest`
harus tetap PASS (belum ada perubahan).

**Step 3 — Implementasi:**

a) Di **paling atas** `assets/user-styles.css` (sebelum `body`), tambah:

```css
/* ===== Design Tokens (identitas: hijau-teal + amber, segmen UMKM Indonesia) ===== */
:root {
    --brand-1: #0f766e;
    --brand-2: #059669;
    --grad-brand: linear-gradient(135deg, #0f766e 0%, #059669 100%);
    --accent: #f59e0b;
    --danger: #dc2626;
    --bg-soft: #f6f7f4;
    --ink: #1f2937;
    --muted: #6b7280;
    --border: #e5e7eb;
}
```

b) Ganti warna hardcoded lama (jangan hapus blok/blok media lama — hanya ubah nilai):

- `body { background-color: #f8f9fa; }` → `body { background-color: var(--bg-soft); }`
- `.sidebar { background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%); }`
  → `.sidebar { background: var(--grad-brand); }`
- `.stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }`
  → `.stats-card { background: var(--grad-brand); }`
- `.stats-card.customers { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }`
  → `.stats-card.customers { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }`
- `.stats-card.transactions { background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); }`
  → `.stats-card.transactions { background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%); }`
- `.stats-card.revenue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }`
  → `.stats-card.revenue { background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); }`
- `.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }`
  → `.btn-primary { background: var(--grad-brand); }`
- `.form-control:focus, .form-select:focus { border-color: #2193b0; box-shadow: 0 0 0 0.2rem rgba(33, 147, 176, 0.25); }`
  → `border-color: var(--brand-1); box-shadow: 0 0 0 0.2rem rgba(15, 118, 110, 0.25);`
- `.mobile-menu-toggle` di `@media (max-width:768px)`: `background: #2193b0` →
  `background: var(--brand-1)`; hover `#1a7a95` → `#0c5f58`
- `.upload-area:hover` & `.upload-area.dragover`: `border-color: #2193b0` →
  `var(--brand-1)`; `background: #e3f2fd` → `#ecfdf5`

**Step 4 — Jalankan & pastikan pass:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
COMPOSER_ALLOW_SUPERUSER=1 composer test   # semua hijau (68 + 2 baru)
```

**Step 5 — Commit:**
```bash
git add assets/user-styles.css tests/MobileRelayoutTest.php
git commit -m "feat(ui): design tokens hijau-teal-amber & recolor user stylesheet"
```

---

## Task 1.2: Recolor `admin/assets/admin-styles.css`

**Files:**
- Modify: `admin/assets/admin-styles.css`
- Test: `tests/MobileRelayoutTest.php` (sudah ditulis di Task 1.1)

**Step 1 — Test sudah ada** (`testAdminStylesheetPunyaDesignTokens`) → FAIL sekarang.

**Step 2 — Implementasi:** tambah `:root` tokens yang sama di atas file; ganti:

- `.sidebar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }`
  → `.sidebar { background: var(--grad-brand); }`
- `.form-control:focus, .form-select:focus { border-color: #667eea; box-shadow: ... rgba(102,126,234,0.25); }`
  → `border-color: var(--brand-1); box-shadow: 0 0 0 0.2rem rgba(15, 118, 110, 0.25);`
- `.mobile-menu-toggle` (dasar `background: #667eea`, hover `#5a6fd8`)
  → `background: var(--brand-1)`, hover `#0c5f58`

**Step 3 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
COMPOSER_ALLOW_SUPERUSER=1 composer test
git add admin/assets/admin-styles.css tests/MobileRelayoutTest.php
git commit -m "feat(ui): recolor admin stylesheet ke identitas hijau-teal (tokens sama)"
```

---

## Task 1.3: Recolor `assets/landing.css` & `assets/login.css`

**Files:**
- Modify: `assets/landing.css` (`:root`), `assets/login.css` (`:root`)
- Test: tambah method ke `MobileRelayoutTest`:

```php
    public function testLandingDanLoginPakaiIdentitasBaru(): void
    {
        foreach ([
            dirname(__DIR__) . '/assets/landing.css',
            dirname(__DIR__) . '/assets/login.css',
        ] as $css) {
            $src = file_get_contents($css);
            $this->assertStringContainsString('--brand-1: #0f766e', basename($css) . ': brand-1 teal wajib');
            $this->assertStringContainsString('--brand-2: #059669', basename($css) . ': brand-2 emerald wajib');
            $this->assertStringContainsString('#f59e0b', basename($css) . ': aksen amber wajib');
        }
    }
```

**Step 1 — Test FAIL** dulu, lalu **implementasi** di kedua file `:root`:

```css
:root {
    --brand-1: #0f766e;
    --brand-2: #059669;
    --grad-primary: linear-gradient(135deg, #0f766e 0%, #059669 100%);
    --grad-secondary: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
    --ink: #1f2937;
    --muted: #6b7280;
    --bg-soft: #f8fafc;
    --border: #e5e7eb;
    --radius: 16px;
    --shadow: 0 10px 30px rgba(15, 118, 110, 0.12);
    --shadow-lg: 0 20px 50px rgba(15, 118, 110, 0.18);
}
```

> `login.css` tidak punya `--grad-secondary`/`--bg-soft` — biarkan hanya token yang
> dipakai file tsb (tidak wajib identik). Palet `seg-*` di landing.css sudah
> amber/hijau/biru/merah/abu → biarkan (sudah selaras palette segmen).

**Step 2 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
git add assets/landing.css assets/login.css tests/MobileRelayoutTest.php
git commit -m "feat(ui): recolor landing & login ke identitas hijau-teal-amber"
```

---

## Task 1.4: Konsolidasi stats cards (dashboard vs customers/transactions)

**Files:**
- Modify: `dashboard.php` (sudah pakai `.stats-card` — cukup pastikan urutan class),
  `customers.php`, `transactions.php` (ganti `.stat-card bg-*` → `.stats-card` + warna token)
- Test: `tests/MobileRelayoutTest.php` — tambah:

```php
    public function testStatsCardKonsistenDiHalamanData(): void
    {
        foreach (['customers.php', 'transactions.php'] as $page) {
            $src = file_get_contents(dirname(__DIR__) . '/' . $page);
            $this->assertStringContainsString('stats-card', $src, "$page: kartu statistik pakai class .stats-card (bukan .stat-card bg-*)");
            $this->assertStringNotContainsString('stat-card bg-', $src, "$page: jangan pakai warna bootstrap acak");
        }
    }
```

**Step 1 — FAIL.** **Step 2 — Implementasi** di `customers.php` & `transactions.php`
(4 kartu per halaman; contoh `customers.php`):

SEBELUM (tiap kartu):
```html
<div class="card stat-card bg-primary text-white">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-0"><?= number_format($totalCustomers, 0, ',', '.') ?></h3>
                <p class="mb-0">Total Pelanggan</p>
            </div>
            <i class="fas fa-users fa-2x opacity-75"></i>
        </div>
    </div>
</div>
```

SESUDAH (class `stats-card` + varian makna):
```html
<div class="card stats-card customers">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-0"><?= number_format($totalCustomers, 0, ',', '.') ?></h3>
                <p class="mb-0">Total Pelanggan</p>
            </div>
            <i class="fas fa-users fa-2x opacity-75"></i>
        </div>
    </div>
</div>
```

Pemetaan varian (customers): `bg-primary→stats-card customers`, `bg-success→stats-card loyal`(hijau), `bg-info→stats-card potential`(biru), `bg-warning→stats-card revenue`(amber).
Pemetaan (transactions): `bg-primary→stats-card transactions`(biru), `bg-success→stats-card loyal`(hijau), `bg-info→stats-card potential`(biru), `bg-warning→stats-card revenue`(amber).

> Tambahkan 2 varian CSS di user-styles.css (Task 1.1 belum ada):
> `.stats-card.loyal { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }`
> `.stats-card.potential { background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%); }`
> (letakkan setelah `.stats-card.revenue`).

**Step 3 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
COMPOSER_ALLOW_SUPERUSER=1 composer test
git add customers.php transactions.php assets/user-styles.css tests/MobileRelayoutTest.php
git commit -m "feat(ui): konsolidasi stats cards ke .stats-card bertoken (customers & transactions)"
```

---

# Sprint 2 — Mobile Shell (Top Bar + Sidebar Overlay + Bottom Nav)

## Task 2.1: File shell baru — `includes/mobile-topbar.php`, `includes/bottom-nav.php`

**Files:**
- Create: `includes/mobile-topbar.php`, `includes/bottom-nav.php`
- Test: `tests/MobileRelayoutTest.php` (perluas — cek file ada & struktur)

**Step 1 — Test gagal:**

```php
    public function testShellFileAda(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/includes/mobile-topbar.php');
        $this->assertFileExists(dirname(__DIR__) . '/includes/bottom-nav.php');
        $this->assertFileExists(dirname(__DIR__) . '/assets/mobile.js');
        $this->assertFileExists(dirname(__DIR__) . '/assets/table-cards.js');
    }
```

**Step 2 — Implementasi.** `includes/mobile-topbar.php` (judul via variabel,
default aman — pola sama `includes/sidebar.php`):

```php
<?php
// Mobile top bar — SATU sumber utk semua halaman user.
// Set $mobilePageTitle di halaman sebelum include; default utk render CLI/test.
$mobilePageTitle = $mobilePageTitle ?? 'Smart Marketing';
?>
<div class="mobile-topbar">
    <button class="mobile-menu-toggle" onclick="toggleSidebar()" aria-label="Buka menu">
        <i class="fas fa-bars"></i>
    </button>
    <span class="mobile-topbar-title"><?= htmlspecialchars($mobilePageTitle) ?></span>
    <a class="mobile-topbar-avatar" href="profile.php" aria-label="Profil">
        <i class="fas fa-user"></i>
    </a>
</div>
```

`includes/bottom-nav.php` (5 menu utama UMKM; `basename($_SERVER['PHP_SELF'])`
sama pola sidebar.php):

```php
<?php
// Bottom navigation mobile — SATU sumber utk halaman user.
$bnPage = basename($_SERVER['PHP_SELF'] ?? ''); // fallback utk render CLI/test
$bnItems = [
    ['dashboard.php',  'fa-tachometer-alt', 'Dashboard'],
    ['customers.php',  'fa-users',          'Data'],
    ['analysis.php',   'fa-chart-pie',      'RFM'],
    ['ai-content.php', 'fa-magic',          'AI'],
    ['profile.php',    'fa-user',           'Profil'],
];
?>
<nav class="bottom-nav" aria-label="Navigasi bawah">
    <?php foreach ($bnItems as $bnItem): ?>
    <a class="bottom-nav-item <?= $bnPage === $bnItem[0] ? 'active' : '' ?>" href="<?= $bnItem[0] ?>">
        <i class="fas <?= $bnItem[1] ?>"></i>
        <span><?= $bnItem[2] ?></span>
    </a>
    <?php endforeach; ?>
</nav>
```

**Step 3 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
git add includes/mobile-topbar.php includes/bottom-nav.php tests/MobileRelayoutTest.php
git commit -m "feat(mobile): file shell reusable top bar & bottom navigation"
```

---

## Task 2.2: CSS shell mobile di `assets/user-styles.css`

**Files:**
- Modify: `assets/user-styles.css` (append blok shell SETELAH blok
  "Optimasi Tampilan Mobile" existing — JANGAN hapus blok lama, test menguncinya)
- Test: `tests/MobileRelayoutTest.php` — tambah:

```php
    public function testUserStylesheetPunyaShellMobile(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/user-styles.css');
        foreach (['.mobile-topbar', '.sidebar-backdrop', '.bottom-nav', '.table-cards', '.fab'] as $sel) {
            $this->assertStringContainsString($sel, $css, "user-styles.css: selector $sel wajib ada");
        }
        // topbar hanya tampil di mobile (display:none di dasar, tampil di media)
        $this->assertStringContainsString('.mobile-topbar { display: none; }', $css);
    }
```

**Step 1 — FAIL. Step 2 — Implementasi (append ke akhir file):**

```css
/* ===== Mobile Shell: top bar + sidebar overlay + bottom navigation ===== */
.mobile-topbar { display: none; }
.bottom-nav { display: none; }
.fab { display: none; }
.sidebar-backdrop { display: none; }

@media (max-width: 767.98px) {
    .mobile-topbar {
        display: flex;
        align-items: center;
        gap: .5rem;
        position: sticky;
        top: 0;
        z-index: 1050;
        background: var(--grad-brand);
        color: #fff;
        padding: .55rem .85rem;
        box-shadow: 0 2px 10px rgba(15, 118, 110, .25);
    }
    .mobile-topbar .mobile-menu-toggle {
        position: static;
        background: transparent;
        border: none;
        color: #fff;
        padding: .5rem .65rem;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .mobile-topbar-title { flex: 1; font-weight: 600; font-size: 1.05rem; }
    .mobile-topbar-avatar {
        width: 36px; height: 36px;
        border-radius: 50%;
        background: rgba(255,255,255,.15);
        color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        text-decoration: none;
    }

    /* Sidebar overlay full + backdrop (di atas topbar, di bawah sidebar) */
    .sidebar { width: 100%; max-width: 320px; z-index: 1100; }
    .sidebar-backdrop {
        display: block;
        position: fixed; inset: 0;
        background: rgba(15, 23, 42, .5);
        z-index: 1055;
        opacity: 0;
        visibility: hidden;
        transition: opacity .3s ease, visibility .3s ease;
    }
    .sidebar-backdrop.show { opacity: 1; visibility: visible; }

    /* Bottom navigation */
    .bottom-nav {
        display: flex;
        position: fixed; left: 0; right: 0; bottom: 0;
        z-index: 1040;
        background: #fff;
        border-top: 1px solid var(--border);
        padding-bottom: env(safe-area-inset-bottom);
        box-shadow: 0 -2px 10px rgba(0,0,0,.06);
    }
    .bottom-nav-item {
        flex: 1;
        display: flex; flex-direction: column; align-items: center;
        gap: .2rem;
        padding: .5rem 0 .4rem;
        color: var(--muted);
        text-decoration: none;
        font-size: .68rem;
        font-weight: 600;
    }
    .bottom-nav-item i { font-size: 1.15rem; }
    .bottom-nav-item.active { color: var(--brand-1); }

    /* Beri ruang bawah utk bottom nav */
    .main-content { padding-bottom: 72px; }
}

@media (max-width: 575.98px) {
    .fab {
        display: inline-flex;
        align-items: center; justify-content: center;
        position: fixed;
        right: 1rem;
        bottom: calc(64px + env(safe-area-inset-bottom));
        z-index: 1030;
        width: 56px; height: 56px;
        border-radius: 50%;
        border: none;
        background: var(--grad-brand);
        color: #fff;
        font-size: 1.3rem;
        box-shadow: 0 6px 16px rgba(15, 118, 110, .35);
    }
    .fab:active { transform: scale(.95); }

    /* Modal jadi bottom sheet */
    .modal-dialog {
        align-items: flex-end;
        min-height: calc(100% - 1rem);
        margin: 0;
    }
    .modal.fade .modal-dialog { transform: translateY(60px); }
    .modal.show .modal-dialog { transform: none; }
    .modal-content { border-radius: 16px 16px 0 0; }
    .modal-body { max-height: 70vh; overflow-y: auto; }
}
```

**Step 3 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
COMPOSER_ALLOW_SUPERUSER=1 composer test   # MobileResponsiveTest tetap hijau (blok lama utuh)
git add assets/user-styles.css tests/MobileRelayoutTest.php
git commit -m "feat(mobile): CSS shell top bar, sidebar overlay + backdrop, bottom nav, FAB, modal sheet"
```

---

## Task 2.3: `assets/mobile.js` — backdrop & close-on-outside

**Files:**
- Create: `assets/mobile.js`
- Test: `tests/MobileRelayoutTest.php` — tambah:

```php
    public function testMobileJsMengaturBackdrop(): void
    {
        $js = file_get_contents(dirname(__DIR__) . '/assets/mobile.js');
        $this->assertStringContainsString('sidebar-backdrop', $js, 'mobile.js: backdrop wajib dibuat');
        $this->assertStringContainsString('classList.remove(\'show\')', $js, 'mobile.js: klik backdrop menutup sidebar');
        $this->assertStringContainsString('DOMContentLoaded', $js, 'mobile.js: inisialisasi saat DOM siap');
    }
```

**Step 1 — FAIL. Step 2 — Implementasi** (TIDAK mendefinisikan `toggleSidebar()`
— fungsi itu tetap inline per halaman karena diunci `MobileMenuToggleTest`):

```js
// assets/mobile.js — perilaku shell mobile: backdrop sidebar + close on outside.
// Catatan: function toggleSidebar() TETAP inline di tiap halaman (diunci test);
// file ini hanya menambah backdrop & sinkronisasi class .show.
(function () {
    'use strict';

    function init() {
        var sidebar = document.querySelector('.sidebar');
        var toggle = document.querySelector('.mobile-menu-toggle');
        if (!sidebar || !toggle) { return; }

        var backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);

        // Klik backdrop -> tutup sidebar
        backdrop.addEventListener('click', function () {
            sidebar.classList.remove('show');
        });

        // Sinkronkan tampilan backdrop dengan class .show (toggleSidebar inline)
        function sync() {
            backdrop.classList.toggle('show', sidebar.classList.contains('show'));
        }
        if (window.MutationObserver) {
            new MutationObserver(sync).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        } else {
            sidebar.addEventListener('click', sync);
            toggle.addEventListener('click', sync);
        }

        // Tutup dengan tombol ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { sidebar.classList.remove('show'); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

**Step 3 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
git add assets/mobile.js tests/MobileRelayoutTest.php
git commit -m "feat(mobile): mobile.js backdrop sidebar + tutup saat klik luar/ESC"
```

---

## Task 2.4: Pasang shell di 7 halaman user

**Files:**
- Modify: `dashboard.php`, `customers.php`, `transactions.php`, `analysis.php`,
  `upload.php`, `ai-content.php`, `profile.php`
- Modify: `tests/MobileMenuToggleTest.php` (1 method struktural — tombol pindah ke include)
- Test: `tests/MobileRelayoutTest.php` — tambah:

```php
    /** @dataProvider halamanUserProvider */
    public function testHalamanUserPasangShellMobile(string $page): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/' . $page);
        $this->assertStringContainsString('mobile-topbar.php', $src, "$page: wajib include top bar");
        $this->assertStringContainsString('bottom-nav.php', $src, "$page: wajib include bottom nav");
        $this->assertStringContainsString('mobile.js', $src, "$page: wajib memuat assets/mobile.js");
    }
```

**Step 1 — FAIL (test baru) + UPDATE test lama** — sebelum implementasi, sesuaikan
`tests/MobileMenuToggleTest.php` (tombol tidak lagi literal di src halaman,
tapi di include yang dipasang semua halaman):

```php
    /**
     * @dataProvider halamanTanpaToggleProvider
     */
    public function testHalamanUserMemuatTombolMobileToggle(string $page): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/' . $page);
        $this->assertNotFalse($src, "$page harus bisa dibaca");

        // Tombol kini berasal dari include top bar (SATU sumber) yang wajib dipasang.
        $this->assertStringContainsString(
            'mobile-topbar.php',
            $src,
            "$page: wajib include top bar (sumber tombol .mobile-menu-toggle)"
        );
        $this->assertStringContainsString(
            'function toggleSidebar()',
            $src,
            "$page: JS toggleSidebar() wajib ada agar tombol bisa membuka sidebar"
        );

        // Tombol itu sendiri hidup di include — pastikan isinya benar.
        $topbar = file_get_contents(dirname(__DIR__) . '/includes/mobile-topbar.php');
        $this->assertStringContainsString('mobile-menu-toggle', $topbar, 'mobile-topbar.php: tombol toggle wajib ada');
    }
```

> `testAiContentRenderMemuatTombolToggle` (render CLI, HTML akhir) TIDAK diubah —
> hasil render sudah memuat include sehingga assertion `mobile-menu-toggle` di
> HTML akhir tetap PASS.

**Step 2 — Implementasi** (pola sama di 7 halaman):

a) Set variabel judul di area PHP setelah `$business` check (contoh `dashboard.php`
— judul lain di daftar bawah):

```php
$mobilePageTitle = 'Dashboard';
```

b) Ganti blok tombol toggle lama (tiga baris) dengan include topbar:

SEBELUM:
```html
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
```

SESUDAH:
```html
    <!-- Mobile Top Bar (hamburger + judul + avatar) -->
    <?php include 'includes/mobile-topbar.php'; ?>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
```

> `mobile-topbar.php` berisi tombol `.mobile-menu-toggle` → test
> `MobileMenuToggleTest` (`mobile-menu-toggle` di src) tetap hijau.

c) Tambahkan `<script src="assets/mobile.js"></script>` di tiap halaman
**sebelum** `</body>` (setelah `bootstrap.bundle.min.js`):

```html
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/mobile.js"></script>
```

d) Tambahkan bottom nav **sebelum** `</body>` (setelah `main-content` ditutup,
sebelum modal/form/script — sama urutan di semua halaman):

```html
    <!-- Bottom Navigation (mobile) -->
    <?php include 'includes/bottom-nav.php'; ?>
```

> `analysis.php` & `upload.php` punya blok `<?php require ... ?>`/fungsi di tengah
> body — bottom nav tetap diletakkan tepat sebelum `</body>`.

Judul per halaman (`$mobilePageTitle`):
- `dashboard.php` → `'Dashboard'`
- `customers.php` → `'Data Pelanggan'`
- `transactions.php` → `'Data Transaksi'`
- `analysis.php` → `'RFM Analysis'`
- `upload.php` → `'Upload Data'`
- `ai-content.php` → `'Generator Konten AI'`
- `profile.php` → `'Profil Bisnis'`

**Step 3 — Lint & test (semua):**
```bash
php -l dashboard.php customers.php transactions.php analysis.php upload.php ai-content.php profile.php
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileMenuToggleTest.php tests/MobileResponsiveTest.php tests/MobileRelayoutTest.php
COMPOSER_ALLOW_SUPERUSER=1 composer test
```
→ seluruh `MobileRelayoutTest` (shell), `MobileResponsiveTest`, dan
`MobileMenuToggleTest` (yang sudah di-update) hijau.

**Step 4 — Commit:**
```bash
git add dashboard.php customers.php transactions.php analysis.php upload.php ai-content.php profile.php tests/MobileRelayoutTest.php tests/MobileMenuToggleTest.php
git commit -m "feat(mobile): pasang shell top bar + bottom nav + mobile.js di 7 halaman user (tombol toggle via include)"
```

---

## Task 2.5: Verifikasi Sprint 2

```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
COMPOSER_ALLOW_SUPERUSER=1 composer test
COMPOSER_ALLOW_SUPERUSER=1 composer validate --no-check-publish
COMPOSER_ALLOW_SUPERUSER=1 composer audit
```
Tidak ada commit terpisah (verifikasi saja).

---

# Sprint 3 — Komponen Mobile (Stats, Tabel→Kartu, Bottom-Sheet, FAB)

## Task 3.1: Stats cards mobile — grid 2 kolom & angka besar

**Files:**
- Modify: `assets/user-styles.css` (tambah blok di media 575.98px existing —
  jangan buat media baru yang bentrok)
- Test: `tests/MobileRelayoutTest.php` — tambah:

```php
    public function testStatsCardMobileDuaKolom(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/user-styles.css');
        $this->assertStringContainsString('grid-template-columns: repeat(2, 1fr)', $css, 'stats cards: grid 2 kolom di mobile');
        $this->assertStringContainsString('.stats-card h3 { font-size: 1.5rem; }', $css);
    }
```

**Step 1 — FAIL. Step 2 — Implementasi:** di blok `@media (max-width: 575.98px)`
existing (blok "Optimasi Tampilan Mobile" — jangan tambah media baru), tambahkan
di akhir blok:

```css
    /* Statistik: 2 kolom, angka besar (bukan 4 kolom menyusut) */
    .row .col-md-3, .row .col-md-4 {
        flex: 0 0 50%;
        max-width: 50%;
    }
    .row .col-md-3 .card, .row .col-md-4 .card { margin-bottom: 12px; }
    .stats-card { padding: 14px 12px; min-height: 104px; }
    .stats-card h3 { font-size: 1.5rem; }
    .stats-card p { font-size: .8rem; }
```

> Khusus `analysis.php` kolom segment summary pakai `col-md-2` → tambah juga:
> `.row .col-md-2 { flex: 0 0 50%; max-width: 50%; }` di blok yang sama
> (segment summary jadi 2 kolom rapi).

**Step 3 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
git add assets/user-styles.css tests/MobileRelayoutTest.php
git commit -m "feat(mobile): stats cards grid 2 kolom & angka besar di layar sempit"
```

---

## Task 3.2: Tabel → Kartu (`assets/table-cards.js` + class di 2 halaman)

**Files:**
- Create: `assets/table-cards.js`
- Modify: `customers.php`, `transactions.php` (tambah class
  `table-cards-target` di `<table>` + `<script src="assets/table-cards.js">`),
  `assets/user-styles.css` (CSS kartu)
- Test: `tests/MobileRelayoutTest.php` — tambah:

```php
    public function testTableCardsDipasang(): void
    {
        $js = file_get_contents(dirname(__DIR__) . '/assets/table-cards.js');
        $this->assertStringContainsString('table-cards-target', $js, 'table-cards.js: selector wajib');
        $this->assertStringContainsString('innerWidth <= 575', $js, 'table-cards.js: hanya aktif ≤575px');

        foreach (['customers.php', 'transactions.php'] as $page) {
            $src = file_get_contents(dirname(__DIR__) . '/' . $page);
            $this->assertStringContainsString('table-cards-target', $src, "$page: tabel wajib class table-cards-target");
            $this->assertStringContainsString('table-cards.js', $src, "$page: wajib memuat assets/table-cards.js");
        }
    }
```

**Step 1 — FAIL. Step 2 — Implementasi:**

`assets/table-cards.js` (ubah `<table class="table-cards-target">` → kartu di
≤575px; baris pertama jadi judul kartu, sel lain label+nilai, tombol aksi
dipertahankan via innerHTML — konten sudah di-escape server-side):

```js
// assets/table-cards.js — ubah tabel menjadi kartu di layar ≤575px (mobile-first).
(function () {
    'use strict';

    function isMobile() { return window.innerWidth <= 575; }

    function buildCards(table) {
        var thead = table.querySelector('thead');
        var tbody = table.querySelector('tbody');
        if (!thead || !tbody) { return null; }

        var labels = Array.prototype.map.call(thead.querySelectorAll('th'), function (th) {
            return th.textContent.trim();
        });

        var container = document.createElement('div');
        container.className = 'table-cards';

        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (row) {
            var cells = row.querySelectorAll('td');
            if (!cells.length) { return; }

            var card = document.createElement('div');
            card.className = 'table-card';

            var title = document.createElement('div');
            title.className = 'table-card-title';
            title.innerHTML = cells[0].innerHTML; // kolom pertama (nama) jadi judul
            card.appendChild(title);

            var body = document.createElement('div');
            body.className = 'table-card-body';
            for (var i = 1; i < cells.length; i++) {
                var item = document.createElement('div');
                item.className = 'table-card-item';

                var label = document.createElement('span');
                label.className = 'table-card-label';
                label.textContent = labels[i] || '';

                var value = document.createElement('span');
                value.className = 'table-card-value';
                value.innerHTML = cells[i].innerHTML;

                item.appendChild(label);
                item.appendChild(value);
                body.appendChild(item);
            }
            card.appendChild(body);
            container.appendChild(card);
        });

        table.parentNode.insertBefore(container, table);
        table.style.display = 'none';
        return container;
    }

    function apply() {
        document.querySelectorAll('.table-cards-target').forEach(function (table) {
            if (isMobile() && !table.dataset.cardsBuilt) {
                buildCards(table);
                table.dataset.cardsBuilt = '1';
            } else if (!isMobile() && table.dataset.cardsBuilt) {
                var container = table.parentNode.querySelector('.table-cards');
                if (container) { container.remove(); }
                table.style.display = '';
                delete table.dataset.cardsBuilt;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', apply);
    window.addEventListener('resize', apply);
})();
```

CSS (append di user-styles.css, blok dasar — tampil di semua ukuran karena kartu
hanya dibuat JS saat mobile):

```css
/* ===== Card view untuk tabel (dibuat oleh assets/table-cards.js saat ≤575px) ===== */
.table-cards { display: flex; flex-direction: column; gap: 10px; }
.table-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 14px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, .05);
}
.table-card-title { font-weight: 700; color: var(--ink); margin-bottom: 8px; }
.table-card-body { display: flex; flex-direction: column; gap: 6px; }
.table-card-item {
    display: flex; justify-content: space-between; align-items: center; gap: .75rem;
    font-size: .9rem;
}
.table-card-label { color: var(--muted); font-size: .78rem; }
.table-card-value { font-weight: 600; color: var(--ink); text-align: right; }
.table-card-item .btn-group { gap: .35rem; }
```

`customers.php` & `transactions.php`:
- `<table class="table table-striped table-hover">` →
  `<table class="table table-striped table-hover table-cards-target">`
- Tambah `<script src="assets/table-cards.js"></script>` setelah
  `<script src="assets/mobile.js"></script>`.

**Step 3 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
COMPOSER_ALLOW_SUPERUSER=1 composer test
git add assets/table-cards.js customers.php transactions.php assets/user-styles.css tests/MobileRelayoutTest.php
git commit -m "feat(mobile): tabel customers & transactions jadi kartu di layar ≤575px"
```

> `analysis.php` TIDAK dapat table-cards (DataTables) — tetap DataTables `scrollX`
> (sudah ada) + segment summary 2 kolom (Task 3.1).

---

## Task 3.3: Modal bottom-sheet (CSS sudah di Task 2.2 — verifikasi saja)

CSS `.modal-dialog { align-items: flex-end; ... }` sudah ditambahkan di Task 2.2
blok 575.98px. **Verifikasi** dengan test tambahan:

```php
    public function testModalBottomSheet(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/user-styles.css');
        $this->assertStringContainsString('align-items: flex-end;', $css, 'modal jadi bottom sheet di mobile');
        $this->assertStringContainsString('translateY(60px)', $css, 'animasi slide-up modal');
    }
```

Tidak ada perubahan file → tidak ada commit (test ditambahkan ke
`MobileRelayoutTest` di task 3.2; jalankan & pastikan pass).

---

## Task 3.4: FAB "Tambah" di customers & transactions

**Files:**
- Modify: `customers.php`, `transactions.php` (tombol FAB sebelum bottom nav)
- Modify: `assets/user-styles.css` (CSS `.fab` sudah ada di Task 2.2 — tidak perlu)
- Test: `tests/MobileRelayoutTest.php` — tambah:

```php
    public function testFabTambahAda(): void
    {
        foreach (['customers.php', 'transactions.php'] as $page) {
            $src = file_get_contents(dirname(__DIR__) . '/' . $page);
            $this->assertStringContainsString('class="fab"', $src, "$page: FAB tambah wajib ada");
            $this->assertStringContainsString('data-bs-target="#addCustomerModal"', $src, "$page: FAB membuka modal tambah");
        }
    }
```

> `#addCustomerModal` di customers.php dan `#addTransactionModal` di
> transactions.php — sesuaikan selector per halaman di assert (tulis dua assert
> terpisah bila perlu).

**Step 1 — FAIL. Step 2 — Implementasi** (di kedua halaman, tepat sebelum
`<!-- Bottom Navigation (mobile) -->`):

```html
    <!-- FAB: tambah cepat (mobile) -->
    <button class="fab" data-bs-toggle="modal" data-bs-target="#addCustomerModal" aria-label="Tambah pelanggan">
        <i class="fas fa-plus"></i>
    </button>
```
(transactions.php: `#addTransactionModal`, aria-label `"Tambah transaksi"`.)

**Step 3 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
COMPOSER_ALLOW_SUPERUSER=1 composer test
git add customers.php transactions.php tests/MobileRelayoutTest.php
git commit -m "feat(mobile): FAB tambah cepat customers & transactions (buka modal)"
```

---

## Task 3.5: Verifikasi Sprint 3

```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
COMPOSER_ALLOW_SUPERUSER=1 composer test
```

---

# Sprint 4 — Bahasa Indonesia & Microcopy

## Task 4.1: Label dashboard → Bahasa Indonesia

**Files:**
- Modify: `dashboard.php`
- Test: `tests/MobileRelayoutTest.php` — tambah:

```php
    public function testDashboardBerbahasaIndonesia(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/dashboard.php');
        $this->assertStringContainsString('Total Pelanggan', $src, 'dashboard: label Total Pelanggan');
        $this->assertStringContainsString('Total Transaksi', $src, 'dashboard: label Total Transaksi');
        $this->assertStringContainsString('Total Omzet', $src, 'dashboard: label Total Omzet');
        $this->assertStringContainsString('Transaksi Terbaru', $src, 'dashboard: judul tabel Transaksi Terbaru');
        $this->assertStringNotContainsString('Total Customers', $src, 'dashboard: jangan label Inggris');
        $this->assertStringNotContainsString('Recent Transactions', $src, 'dashboard: jangan judul Inggris');
    }
```

**Step 1 — FAIL. Step 2 — Implementasi** di `dashboard.php`:

| Sebelum | Sesudah |
|---|---|
| `Total Customers` | `Total Pelanggan` |
| `Total Transactions` | `Total Transaksi` |
| `Total Revenue` | `Total Omzet` |
| `RFM Segments Distribution` | `Distribusi Segmen RFM` |
| `Revenue Trend (6 Months)` | `Tren Omzet (6 Bulan)` |
| `Recent Transactions` | `Transaksi Terbaru` |
| `Upload & Process` | `Upload & Proses` |
| `Generate Content` | `Buat Konten` |
| `No RFM data available. Upload customer data first.` | `Belum ada data RFM. Upload data pelanggan terlebih dahulu.` |
| `No revenue data available.` | `Belum ada data omzet.` |
| `No transactions yet. Upload your data to get started.` | `Belum ada transaksi. Upload data Anda untuk mulai.` |

**Step 3 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
COMPOSER_ALLOW_SUPERUSER=1 composer test
git add dashboard.php tests/MobileRelayoutTest.php
git commit -m "feat(i18n): label dashboard bahasa Indonesia (Total Pelanggan/Transaksi/Omzet)"
```

---

## Task 4.2: Heading konsisten (`<h1>` → `<h2>`) di `ai-content.php`

**Files:**
- Modify: `ai-content.php` (satu `<h1>` → `<h2>`; class `mb-4` dipertahankan)
- Test: `tests/MobileRelayoutTest.php` — tambah:

```php
    public function testHeadingKonsistenH2(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/ai-content.php');
        $this->assertStringContainsString('<h2', $src, 'ai-content: heading h2');
        $this->assertStringNotContainsString('<h1>', $src, 'ai-content: tidak boleh ada h1 (konsisten dgn halaman lain)');
    }
```

**Step 1 — FAIL. Step 2 — Implementasi:**
`<h1><i class="fas fa-magic me-2"></i> Generator Konten AI</h1>` →
`<h2><i class="fas fa-magic me-2"></i> Generator Konten AI</h2>`

**Step 3 — Test & commit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/MobileRelayoutTest.php
COMPOSER_ALLOW_SUPERUSER=1 composer test   # MobileMenuToggleTest tetap hijau ('Generator Konten AI' string utuh)
git add ai-content.php tests/MobileRelayoutTest.php
git commit -m "feat(i18n): konsistenkan heading h2 di ai-content (sama dgn halaman lain)"
```

---

## Task 4.3: Verifikasi Sprint 4

```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
COMPOSER_ALLOW_SUPERUSER=1 composer test
```

---

# Sprint 5 — E2E Mobile & Dokumentasi

## Task 5.1: E2E Playwright mobile (ad-hoc, TIDAK di-commit)

**Files:**
- Create: `/tmp/pw-mobile-ui/check-mobile-ui.js` (di luar repo — pola
  `tests/` + `/tmp`, lihat AGENTS.md §5.5)

**Step 1 — Tulis skrip ad-hoc** (pola `docs/plans/2026-08-18-optimasi-mobile.md`
E2E; `ignoreHTTPSErrors: true`, emulasi Pixel 5 393x851):

```js
// /tmp/pw-mobile-ui/check-mobile-ui.js — verifikasi shell & komponen mobile
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({
    viewport: { width: 393, height: 851 },
    hasTouch: true,
    ignoreHTTPSErrors: true,
  });
  const page = await ctx.newPage();

  await page.goto('https://smartrfm.my.id/login.php');
  await page.fill('#email', 'budi@batiksemarang.com');
  await page.fill('#password', 'password123');
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);

  // 1) Top bar + hamburger terlihat
  await page.waitForSelector('.mobile-topbar');
  console.log('OK topbar');
  // 2) Bottom nav 5 item
  const items = await page.locator('.bottom-nav-item').count();
  console.log('bottom nav items:', items);
  // 3) Sidebar overlay + backdrop
  await page.click('.mobile-menu-toggle');
  await page.waitForSelector('.sidebar-backdrop.show');
  const sbBox = await page.locator('.sidebar').boundingBox();
  console.log('sidebar x>=0 saat terbuka:', sbBox.x >= 0);
  await page.click('.sidebar-backdrop'); // tutup via backdrop
  // 4) Dashboard label Indonesia
  const body = await page.textContent('body');
  console.log('Total Pelanggan ada:', body.includes('Total Pelanggan'));

  await browser.close();
})();
```

**Step 2 — Jalankan:** `cd /tmp/pw-mobile-ui && npm i playwright && node check-mobile-ui.js`
→ semua `OK` tercetak. Login rate-limit (rb_login burst=5) — jangan hammer.

> Skrip tidak di-commit (aturan AGENTS.md: skrip e2e ad-hoc di /tmp).

## Task 5.2: Update README.md & AGENTS.md

**Files:**
- Modify: `README.md` (bagian "Tampilan mobile khusus" — tambah shell baru)
- Modify: `AGENTS.md` (bila perlu — catat `includes/mobile-topbar.php`,
  `includes/bottom-nav.php`, `assets/mobile.js`, `assets/table-cards.js` sebagai
  cross-cutting baru)

**Step 1 — Update README** (di bagian Performance Optimization → Frontend,
ganti baris "Tampilan mobile khusus" menjadi):

```
- **Tampilan mobile khusus (segmen UMKM Indonesia):** satu identitas warna
  hijau-teal + amber (design tokens `--brand-*`), top bar sticky dengan hamburger
  di semua halaman user (`includes/mobile-topbar.php`), sidebar overlay + backdrop
  (`assets/mobile.js`), bottom navigation 5 menu (`includes/bottom-nav.php`),
  tabel Customers & Transactions berubah jadi kartu di layar ≤575px
  (`assets/table-cards.js`), FAB tambah cepat, modal bottom-sheet, label
  berbahasa Indonesia.
```

**Step 2 — Update AGENTS.md** (tabel "Logika terpusat" §1): tambah baris
`includes/mobile-topbar.php`, `includes/bottom-nav.php` + `assets/mobile.js`,
`assets/table-cards.js` (cross-cutting UI mobile). Pastikan §8 Upload/Auth tidak
berubah.

**Step 3 — Lint & commit:**
```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
COMPOSER_ALLOW_SUPERUSER=1 composer test
git add README.md AGENTS.md
git commit -m "docs: catat shell & komponen UI mobile baru (top bar, bottom nav, card view, tokens)"
```

## Task 5.3: Verifikasi final

```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
COMPOSER_ALLOW_SUPERUSER=1 composer test
COMPOSER_ALLOW_SUPERUSER=1 composer validate --no-check-publish
COMPOSER_ALLOW_SUPERUSER=1 composer audit    # 0 CVE
git status --short                            # hanya file yang dimaksud
```

## Task 5.4: Commit final docs (bila ada sisa)

```bash
git add docs/plans/2026-08-18-mobile-relayout-recolor.md
git commit -m "docs: plan relayout & recolor UI mobile (segmen UMKM Indonesia, 5 sprint)"
```

---

## 5. Di Luar Scope (didefer/terpisah)

- **Admin panel relayout** — admin hanya di-recolor (identitas sama), tidak di-shell
  (topbar/bottom nav). UMKM owner = target segmen; admin dipakai super_admin di
  desktop. Bila diminta: sprint terpisah.
- **Landing & login relayout** — hanya recolor tokens; struktur sudah responsif.
- **Dark mode / tema** — tidak diminta; tokens memudahkan penambahan nanti.
- **RENCANA_PERBAIKAN 2.4 (CSP)** & **2.2 (kunci IP/UA)** — tetap didefer, tidak
  disentuh (file JS/CSS baru tidak menambah inline script baru).
- **PWA / offline** — di luar scope plan ini.

## 6. Self-Review

1. **Coverage spec:** "relayout" → Sprint 2 (shell: top bar, sidebar overlay,
   bottom nav) + Sprint 3 (komponen: stats 2 kolom, tabel→kartu, bottom-sheet,
   FAB); "recolor sesuai target segmen" → Sprint 1 (design tokens hijau-teal-amber
   + konsolidasi stats + palet segmen) + Sprint 4 (bahasa Indonesia).
   "Pecah jadi sprint" → 5 sprint, tiap sprint = unit kerja commit.
2. **Placeholder:** tidak ada TBD — semua diff konkret (token, CSS, JS, markup,
   pemetaan label).
3. **Konsistensi:** `$mobilePageTitle` dipakai semua halaman sebelum include;
   `toggleSidebar()` tetap inline di tiap halaman; `.mobile-menu-toggle`
   `display:none` dasar + media 768px dipertahankan (MobileResponsiveTest);
   blok `@media (max-width:575.98px)` + `flex-wrap` dipertahankan.
   **Update test lama:** hanya `MobileMenuToggleTest::testHalamanUserMemuatTombolMobileToggle`
   yang diubah (tombol kini di include topbar — invariant baru: halaman wajib include
   topbar + include wajib memuat tombol); test render CLI tidak diubah.
4. **Test baru** `tests/MobileRelayoutTest.php` diperluas bertahap (pola
   `MobileResponsiveTest`) — tidak ada task yang menuntut test sebelum file
   test-nya didefinisikan. Perubahan `MobileMenuToggleTest` terjadi di task yang
   sama dengan perpindahan tombol (Task 2.4), sesuai pola TDD.
5. **Risiko rendering CLI:** topbar include punya default judul → render test
   (`MobileMenuToggleTest` ai-content) tidak error meski `$mobilePageTitle`
   belum diset di halaman.

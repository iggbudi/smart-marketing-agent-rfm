# Optimasi Tampilan Mobile — Implementation Plan

**Goal:** Membuat seluruh aplikasi (halaman UMKM + super admin) nyaman dipakai di layar
sempit (≤768px, terutama smartphone ~360px): sidebar off-canvas dapat dibuka di SEMUA
halaman, tabel lebar bisa di-scroll horizontal, header baris judul/tombol tidak overflow,
dan kontrol punya ukuran sentuh yang ergonomis.

**Architecture:** Pola existing sudah ada dasar responsif (`.sidebar` off-canvas via
`.show` + tombol `.mobile-menu-toggle` di `user-styles.css`/`admin-styles.css`, stat cards
kolom stack). Masalahnya **inkonsisten & tidak lengkap**:
1. `admin/users.php` & `admin/businesses.php` masih pakai layout grid lama
   (`col-md-2 sidebar`) → di mobile menu tidak off-canvas, **tidak bisa dibuka** (bug
   fungsional, mirror fix `98b9197` untuk ai-content/profile tapi untuk 2 halaman admin).
2. `analysis.php`: ada `<div>` nyasar setelah `</script>` (HTML invalid) + tabel RFM 9
   kolom tidak bisa scroll X di mobile (DataTables tanpa `scrollX`).
3. Header baris judul + tombol (`d-flex justify-content-between`) di banyak halaman tidak
   `flex-wrap` → overflow horizontal di layar sempit.
4. Belum ada blok CSS mobile khusus (touch target, font iOS 16px, modal, chart height).

Perbaikan = DRY & terpusat: responsif CSS ditambah sekali di 2 stylesheet; 2 halaman admin
lama dimigrasi ke "shell" layout yang sama dgn 5 halaman admin lain (pakai
`includes/sidebar.php` + admin-styles.css). Landing (`index.php`) & login sudah responsif
(sudah diverifikasi `landing.css`/`login.css`) → **di luar scope**.

**Tech Stack:** PHP 7.4+ (runtime 8.3.6), Bootstrap 5.3 CDN, DataTables 1.13.6 (jQuery 3.7),
PHPUnit 9.6 (DB `smart_marketing_rfm_test`), plain CSS (tanpa build step).

**Spec:** Permintaan pengguna "buat plan optimize khusus tampilan mobile" (percakapan
2026-08-18). Acuan: AGENTS.md §2 (konvensi), §8 (checklist refactor), CSRF skill,
RENCANA_PERBAIKAN 2.4 didefer (CSP inline script) — plan ini TIDAK menyentuh CSP.

## Global Constraints
- Satu commit = satu unit kerja (AGENTS.md §6, prefix `feat:`/`fix:`/`test:`/`docs:`).
- Tidak ada form POST baru → tanpa tambahan CSRF (hanya layout/CSS/JS client-side).
  (Catatan: `upload.php` drag&drop `fetch` tanpa CSRF adalah bug terpisah & LUAR scope.)
- Output tetap `htmlspecialchars`; jangan pindahkan query/aturan bisnis.
- `composer test` hijau sebelum commit (baseline saat ini: OK 62 tests).
- Jangan commit rahasia. Jangan ubah `src/Rfm.php` / `src/*`.
- Sidebar tetap SATU sumber: hanya `includes/sidebar.php` (wrapper admin = `admin/includes/sidebar.php`).

---

### Task 1: Migrasi `admin/users.php` & `admin/businesses.php` ke sidebar off-canvas mobile

**Problem:** Kedua halaman masih layout grid lama (`col-md-2 sidebar` + `col-md-10`),
tidak memuat `admin-styles.css`, nav sidebar DICOPY inline (melanggar DRY),
tanpa tombol `.mobile-menu-toggle` & `toggleSidebar()` → on mobile sidebar tersembunyi
di off-canvas (dari CSS global? tidak — mereka tidak pakai admin-styles.css, jadi sidebar
tetap kolom penuh di atas konten, memakan layar) namun TIDAK ada cara membukanya via toggle.
Migrasi ke "shell" `admin/dashboard.php` agar konsisten + sidebar terpusat.

**Files:**
- Modify: `admin/users.php` (head + body shell + script toggle)
- Modify: `admin/businesses.php` (head + body shell + script toggle)
- Test: `tests/MobileResponsiveTest.php` (baru)

> Kedua file strukturnya identik; langkah di bawah untuk `users.php`, ulangi sama untuk
> `businesses.php` (kolom tabel berbeda, tapi shell body + script sama).

**Step 1 — Tulis tes gagal** (`tests/MobileResponsiveTest.php`):

```php
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
            $this->assertStringContainsString('@media (max-width: 575.98px)', basename($css) . ' wajib punya blok mobile');
            $this->assertStringContainsString('flex-wrap: wrap', basename($css) . ' wajib wrap header di mobile');
        }
    }
}
```

**Step 2 — Jalankan & pastikan gagal:**
```bash
vendor/bin/phpunit tests/MobileResponsiveTest.php
```
→ FAIL (users.php/businesses.php belum punya toggle, analysis.php masih `End main-content`,
CSS belum punya @media 575.98px — Task 1 ini hanya user pas untuk admin). Tulis anggota tes
admin dulu; tes yang menyangkut CSS/analysis akan pass bertahap di Task 2–4
(karena itu di Task 1, hanya harapkan `testAdminPagePunyaMobileToggleTanpaGridLama` &
`testAdminPagePakaiSidebarTerpusat` hijau).

**Step 3 — Migrasi `admin/users.php`:**

a) `<head>` — tambah link admin-styles.css setelah font-awesome, sebelum dataTables css:
```html
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
```

b) Buka `<?php requireAuth(['super_admin']); ?>`? — users.php saat ini tidak menunjukkan
letak requireAuth di atas, pastikan tetap ada. Tidak ada edit auth.

c) Di blok `<style>...</style>`, HAPUS aturan `.sidebar { min-height:100vh; ... }` ,
`.sidebar .nav-link`, `.nav-link:hover/.active` (kini dari admin-styles.css). KEEP aturan
`.stat-card.*` gradient (users: `.users/.sessions/.admins`; businesses:
`.active/.customers/.transactions`) karena admin-styles.css tidak mendefinisikannya.

d) Ganti shell body dari grid menjadi off-canvas (persis pola dashboard.php):

SEBELUM:
```html
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar px-3 py-4">
                <div class="d-flex align-items-center mb-4">
                    <i class="fas fa-shield-alt fa-2x text-white me-2"></i>
                    <h5 class="text-white mb-0">Admin Panel</h5>
                </div>
                <nav class="nav flex-column">
                    ... (nav inline: Dashboard/Users/Businesses/.../Logout) ...
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center py-4">
                    <h2><i class="fas fa-users me-2"></i> User Management</h2>
                    <button class="btn btn-primary" ...>...</button>
                </div>
                ... (konten: alert, stats, tabel) ...
            </div>
        </div>
    </div>
```

SESUDAH:
```html
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar (satu sumber) -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-users me-2"></i> User Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-2"></i> Add New User
            </button>
        </div>
        ... (konten: alert, stats, tabel) ...
    </div>
```
(Container `container-fluid`/`row`/`col-md-10` dihapus; `.main-content` dari
admin-styles.css menangani padding & responsive. Modal tetap setelah `main-content`.)

e) Script: tambahkan toggle + close-on-outside di `<script>` terakhir (gabung dgn fungsi
editUser/deleteUser yang sudah ada):
```html
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            if (window.innerWidth <= 768 &&
                !sidebar.contains(event.target) &&
                !toggle.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });

        $(document).ready(function() { ... DataTable ... });
        function editUser(user) { ... }
        function deleteUser(userId, userName) { ... }
    </script>
```

**Step 4 — Migrasi `admin/businesses.php`** dengan pola persis sama (link admin-styles.css,
hapus `.sidebar` inline, keep `.stat-card.*`, shell `mobile-menu-toggle` + `includes/sidebar.php`
+ `.main-content`, tambah toggle JS). `includes/sidebar.php` di sini meresolve wrapper admin.

**Step 5 — Lint & tes:**
```bash
php -l admin/users.php; php -l admin/businesses.php
COMPOSER_ALLOW_SUPERUSER=1 composer test
```
→ `testAdminPagePunyaMobileToggleTanpaGridLama` + `testAdminPagePakaiSidebarTerpusat` PASS.
(`testAnalysis...` & `testStylesheet...` masih FAIL — direncanakan di Task 2–4.)

**Step 6 — Commit:**
```bash
git add admin/users.php admin/businesses.php tests/MobileResponsiveTest.php
git commit -m "feat(mobile): migrasi admin users & businesses ke sidebar off-canvas + tombol toggle"
```

---

### Task 2: Perbaiki `analysis.php` (div nyasar + DataTables scrollX mobile)

**Files:**
- Modify: `analysis.php:161-176`
- Test: `tests/MobileResponsiveTest.php` (extension)

**Step 1 — Tulis tes gagal:** tambah method ke `MobileResponsiveTest` (sudah ada di Task 1:
`testAnalysisTidakAdaDivNyasarDanScrollX`).

**Step 2 — Jalankan & pastikan gagal:**
```bash
vendor/bin/phpunit --filter testAnalysisTidakAdaDivNyasarDanScrollX
```
→ FAIL.

**Step 3 — Implementasi:** di blok script DataTables, tambah `scrollX: true` dan hapus
div nyasar setelah `</script>`:

SEBELUM:
```js
        $(document).ready(function() {
            $('#rfmTable').DataTable({
                pageLength: 25,
                order: [[5, 'asc']],
                columnDefs: [
                    { targets: [2, 3, 4], className: 'text-center' },
                    { targets: [6, 7], className: 'text-end' }
                ]
            });
        });

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
    </script>
    </div> <!-- End main-content -->
</body>
```

SESUDAH:
```js
        $(document).ready(function() {
            $('#rfmTable').DataTable({
                pageLength: 25,
                order: [[5, 'asc']],
                scrollX: true,
                columnDefs: [
                    { targets: [2, 3, 4], className: 'text-center' },
                    { targets: [6, 7], className: 'text-end' }
                ]
            });
        });

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }
    </script>
</body>
```
(`</div>` nyasar + komentar `End main-content` dihapus; tabel sudah dibungkus
`<div class="table-responsive">` di line 102 → scrollX aktif di mobile.)

**Step 4 — Jalankan & pastikan pass:**
```bash
vendor/bin/phpunit --filter testAnalysisTidakAdaDivNyasarDanScrollX
```
→ PASS.

**Step 5 — Lint & tes penuh:** `php -l analysis.php` + `composer test` (sudah ada analis,
semua hijau utk bagian non-admin yang dituntut).

**Step 6 — Commit:**
```bash
git add analysis.php tests/MobileResponsiveTest.php
git commit -m "fix(mobile): analysis.php hapus div nyasar & aktifkan DataTables scrollX utk tabel RFM"
```

---

### Task 3: Blok CSS mobile di `assets/user-styles.css`

**Files:**
- Modify: `assets/user-styles.css` (append after `.chart-container`)
- Test: `tests/MobileResponsiveTest.php` (extension css)

**Step 1 — Tulis tes gagal:** (sudah: `testStylesheetPunyaBlokMobile`, sebagian hijau
setelah ini)

**Step 2 — Jalankan & pastikan gagal:** `vendor/bin/phpunit --filter testStylesheetPunyaBlokMobile`
→ FAIL karena user-styles.css belum punya `@media (max-width: 575.98px)` & `flex-wrap`.

**Step 3 — Implementasi (append ke akhir file):**

```css
/* ===== Optimasi Tampilan Mobile ===== */
@media (max-width: 767.98px) {
    .main-content { padding: 12px; }
    h2 { font-size: 1.35rem; }
    .card { margin-bottom: 14px; }
    .card-header { padding: 12px 14px; }
    .card-body { padding: 14px; }
    .table th, .table td { padding: 10px 8px; }
    .table-responsive { -webkit-overflow-scrolling: touch; }
    .chart-container { height: 240px; }

    /* Baris judul + tombol / tanggal tidak overflow */
    .main-content > .d-flex.justify-content-between,
    .card-header > .d-flex.justify-content-between {
        flex-wrap: wrap;
        gap: .5rem .75rem;
    }
}

@media (max-width: 575.98px) {
    .main-content { padding: 10px; }
    .stats-card { padding: 16px; }
    .stats-card h3 { font-size: 1.5rem; }

    /* Cegah iOS auto-zoom (input ≥16px) */
    .form-control, .form-select { font-size: 16px; }

    /* Touch target ergonomis */
    .btn { padding: 10px 14px; }
    .btn-sm { padding: .375rem .5rem; }

    /* Modal pas di layar kecil */
    .modal-dialog { margin: .5rem; }
}
```

*(Blok ini membuat header flex-wrap, tabel tanpa overflow berlebihan, input tidak auto-zoom
di iOS, tombol nyaman disentuh, modal & chart proporsional di layar sempit.)*

**Step 4 — Jalankan & pastikan pass:** `vendor/bin/phpunit --filter testStylesheetPunyaBlokMobile`
→ PASS (kedua stylesheet).

**Step 5 — Lint & tes penuh:** `composer test`.

**Step 6 — Commit:**
```bash
git add assets/user-styles.css tests/MobileResponsiveTest.php
git commit -m "feat(mobile): blok CSS responsif user stylesheet (wrap header, touch target, iOS 16px, modal)"
```

---

### Task 4: Blok CSS mobile di `admin/assets/admin-styles.css`

**Files:**
- Modify: `admin/assets/admin-styles.css` (append setelah `.mobile-menu-toggle { display:none }`)
- Test: `tests/MobileResponsiveTest.php` (extension css — sudah, hijau)

**Step 1 — Tulis tes gagal:** (sudah ada dari Task 1, sekarang admin-styles belum punya
blok → FAIL sampai diimplementasi)

**Step 2 — Jalankan & pastikan gagal:** `vendor/bin/phpunit --filter testStylesheetPunyaBlokMobile`.

**Step 3 — Implementasi (append ke akhir file):**

```css
/* ===== Optimasi Tampilan Mobile (admin) ===== */
@media (max-width: 767.98px) {
    .main-content { padding: 12px; }
    h2 { font-size: 1.35rem; }
    .card { margin-bottom: 14px; }
    .card-header { padding: 12px 14px; }
    .card-body { padding: 14px; }
    .table th, .table td { padding: 10px 8px; }
    .table-responsive { -webkit-overflow-scrolling: touch; }

    .main-content > .d-flex.justify-content-between,
    .card-header > .d-flex.justify-content-between {
        flex-wrap: wrap;
        gap: .5rem .75rem;
    }
}

@media (max-width: 575.98px) {
    .main-content { padding: 10px; }
    .form-control, .form-select { font-size: 16px; }
    .btn { padding: 10px 14px; }
    .modal-dialog { margin: .5rem; }
}
```

**Step 4 — Jalankan & pastikan pass:** `vendor/bin/phpunit --filter testStylesheetPunyaBlokMobile`
→ PASS. Seluruh `MobileResponsiveTest` hijau sekarang.

**Step 5 — Lint & tes penuh:** `composer test` (harus OK 62 + 6 = 68 tests).

**Step 6 — Commit:**
```bash
git add admin/assets/admin-styles.css tests/MobileResponsiveTest.php
git commit -m "feat(mobile): blok CSS responsif admin stylesheet (wrap header, touch target, iOS 16px)"
```

---

### Task 5: Dokumentasi & verifikasi akhir

**Files:**
- Modify: `README.md` (section UI/responsif bila ada; sebutkan dukungan mobile)
- Modify (jika relevan): `docs/*` — tambah catatan ringkas dukungan mobile
- Test: jalankan rangkaian penuh

**Step 1 — Update docs:**
- `README.md`: tambahkan 1 baris di deskripsi fitur — "Tampilan responsif untuk mobile
  (sidebar off-canvas, tabel scroll horizontal, kontrol sentuh ergonomis)".
- Pastikan tidak menulis "done" sebelum test dijalankan (skill verification-before-completion).

**Step 2 — Lint semua PHP (wajib):**
```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

**Step 3 — Test penuh & audit:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 composer test
composer validate --no-check-publish
composer audit
```

**Step 4 — Render smoke admin/users & businesses** (pola CLI child, session super_admin,
lihat `tests/AuthManagerTest.php`/`MobileMenuToggleTest.php` untuk pola session + DB test)
untuk memastikan migrasi layout tidak error saat dirender.

**Step 5 — Commit:**
```bash
git add README.md docs
git commit -m "docs: catat dukungan tampilan mobile (sidebar off-canvas, tabel scroll)"
```

## Ringkasan Scope
| Task | File | Jenis |
|---|---|---|
| 1 | admin/users.php, admin/businesses.php, tests/MobileResponsiveTest.php | feat(mobile) |
| 2 | analysis.php, tests/MobileResponsiveTest.php | fix(mobile) |
| 3 | assets/user-styles.css, tests/MobileResponsiveTest.php | feat(mobile) |
| 4 | admin/assets/admin-styles.css, tests/MobileResponsiveTest.php | feat(mobile) |
| 5 | README.md, docs/* | docs |

**Di luar scope (didefer/terpisah):**
- Bug `upload.php` drag&drop `fetch` tanpa CSRF (bug fungsional, bukan murni mobile) →
  usulan commit terpisah bila diminta.
- Landing `index.php` & `login.php` sudah responsif (diverifikasi) → tanpa perubahan.
- RENCANA_PERBAIKAN 2.4 (CSP) & 2.2 (kunci IP/UA) tetap didefer — tidak disentuh.

## Self-Review
1. **Coverage spec:** semua permintaan "optimasi mobile" terpetakan → sidebar admin
   (Task 1), tabel RFM & analysis (Task 2), CSS user (Task 3), CSS admin (Task 4),
   docs (Task 5).
2. **Placeholder:** tidak ada TBD — semua diff konkret.
3. **Konsistensi tipe:** `toggleSidebar()` konsisten dipakai semua halaman (sama dgn
   dashboard.php & commit 98b9197). `scrollX` valid untuk DataTables 1.13.6.

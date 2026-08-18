# Redesign Admin Dashboard ("Cockpit Monitoring") — Implementation Plan

**Goal:** Merancang ulang `admin/dashboard.php` agar menjadi *cockpit monitoring*
desktop-first yang sesuai kebutuhan super_admin: KPI platform lengkap dalam satu
baris, grafis business growth + API usage, tabel aktivitas terbaru yang padat,
identitas visual hijau-teal-amber yang konsisten (sama dgn user UMKM), dan label
berbahasa Indonesia.

**Architecture:** Saat ini `admin/dashboard.php` punya beberapa masalah:
1. **Query KPI inline** `$db->query(...)` langsung di halaman (melanggar pola slice
   `src/<Fitur>/` yang sudah jadi standar sisi user).
2. **Dead code**: blok `<style>` di `<head>` berisi `body{...}` + `.stat-card.users/
   businesses/customers/transactions` gradient yang **TIDAK dipakai** markup
   (markup pakai `card stats-card text-center` + ikon `text-primary/success/...`).
3. **Query `business_growth` dihitung tapi tidak pernah dirender** (dead query).
4. **Label campur Inggris**: Platform Overview, Total Customers, Total Revenue, dll.
5. Tidak memuat `vendor/autoload.php` (jadi belum bisa pakai class `App\*`; akan
   WAJIB setelah memakai slice — AGENTS §2.10).

Perbaikan = ekstrak query platform ke **slice `src/Admin/PlatformStats.php`**
(mirip `src/Dashboard/DashboardStats.php`), lalu relayout markup halaman ke grid
KPI ringkas `.kpi-card`, grafis business growth (Chart.js bar) + API usage
(doughnut, sudah ada), tabel aktivitas padat, label Indonesia, hapus dead code.
Admin tetap desktop-first — TIDAK pakai bottom-nav/table-cards ala user UMKM.

**Tech Stack:** PHP 7.4+ (runtime 8.3.6), Bootstrap 5.3 CDN, Chart.js (CDN, sudah
dimuat di head), PHPUnit 9.6 (DB `smart_marketing_rfm_test`), plain CSS/JS.

**Spec:** Permintaan pengguna (2026-08-18): "layout dashboard yang paling sesuai
untuk admin" → disepakati: dashboard admin = cockpit monitoring desktop-first.
Acuan: AGENTS.md §1/§2/§10 (admin = subsistem terpisah; slice user sebagai pola),
skill `writing-plans`, dokumen yang baru selesai
`docs/plans/2026-08-18-mobile-relayout-recolor.md` (menetapkan design tokens
`--brand-*` yang dipakai di sini).

---

## 1. Target Pengguna (Admin)

**Persona:** Super Admin SmartRFM, bekerja di laptop/desktop, jarang di HP. Tugas
utama: kelola user & bisnis, pantau pemakaian platform (transaksi agregat, API
usage, aktivitas), pastikan sistem sehat. Bukan pembuat konten/analisa pelanggan
langsung (itu tugas UMKM owner).

| Kebutuhan admin | Implikasi layout |
|---|---|
| Situasi platform sekilas | **6 KPI dalam 1 baris** (umkm, bisnis, pelanggan, transaksi, omzet, user aktif) |
| Pantau pertumbuhan & pemakaian API | 2 grafik berdampingan: business growth (bar) + API usage (doughnut) |
| Audit aktivitas | Tabel aktivitas terbaru full-width, kolom rapat, badge aksi |
| Bekerja lama di satu layar | Sidebar permanen (desktop), densitas tinggi, tanpa bottom-nav |
| Identitas platform | Sama dgn sisi user: design tokens hijau-teal + amber |

---

## 2. Peta File (Struktur Dulu)

| File | Status | Tanggung jawab |
|---|---|---|
| `src/Admin/PlatformStats.php` | Create | Slice: `getKpis()`, `getApiUsageToday()`, `getRecentActivities()`, `getBusinessGrowth()` |
| `admin/dashboard.php` | Modify | Panggil slice, relayout KPI/graphic/table, label Indonesia, hapus dead `<style>`, autoload |
| `admin/assets/admin-styles.css` | Modify | Tambah `body{background:var(--bg-soft)}` + `.kpi-card` styles |
| `tests/PlatformStatsTest.php` | Create | Unit test slice (RED→GREEN) |
| `tests/AdminDashboardTest.php` | Create | Regression: struktur halaman (slice dipakai, label ID, chart ada, tak ada dead CSS) |
| `README.md` | Modify | Catat dashboard admin sebagai cockpit monitoring + slice `src/Admin` |

Output lain yang sudah ada & dipakai: design tokens di `admin-styles.css`,
`includes/sidebar.php` (satuan sumber sidebar), Chart.js CDN.

---

## 3. Sprint Overview

| Sprint | Isi | Commit utama |
|---|---|---|
| **S1** | Slice `src/Admin/PlatformStats` + unit test | `feat(admin): slice PlatformStats untuk KPI platform` |
| **S2** | Relayout `admin/dashboard.php` (cockpit) + `.kpi-card` + bahasa ID + hapus dead code | `feat(admin): relayout dashboard jadi cockpit monitoring + label Indonesia` |
| **S3** | Verifikasi final + docs README | `docs: catat dashboard admin cockpit & slice src/Admin` |

---

## 4. Global Constraints

- Satu commit = satu unit kerja (AGENTS §6). Prefix `feat:`/`test:`/`docs:`.
- Halaman admin memakai class `App\*` **wajib** memuat `vendor/autoload.php`
  sendiri (`require_once dirname(__DIR__) . '/vendor/autoload.php';` — AGENTS §2.10).
- TIDAK ada form POST baru → tanpa tambahan CSRF. Query slice = **statis tanpa
  input user** → aman tanpa prepared-statement dinamis; `LIMIT`/`INTERVAL` pakai
  inline `(int)` cast (gotcha PDO pagination, AGENTS §2.1).
- Perilaku revenue dipertahankan: `SUM(amount)` (harga satuan, mengabaikan qty) —
  konsisten dgn `DashboardStats` & komentar `DashboardStatsTest`.
- ADMIN tidak di-shell mobile (bottom-nav/kartu) — desktop-first. Perbaikan hanya
  `admin/dashboard.php`, bukan 6 halaman admin lain.
- `composer test` hijau sebelum commit (baseline saat ini: OK 95 tests).
- Jangan commit rahasia. Jangan ubah `src/Rfm*`, `config/*`, `database_*.sql`.

---

# Sprint 1 — Slice `src/Admin/PlatformStats`

## Task 1.1: Buat slice + unit test

**Files:**
- Create: `src/Admin/PlatformStats.php`
- Test: `tests/PlatformStatsTest.php`

**Interfaces:**
- Consumes: `\PDO` (dari `getDB()`)
- Produces (dipakai Task 2.1 di `admin/dashboard.php`):
  - `getKpis(): array` → keys `total_umkm, total_businesses, total_customers,
    total_transactions, total_revenue (float), active_today`
  - `getApiUsageToday(): array` → `[api_type => count]`
  - `getRecentActivities(int $limit = 10): array` → baris `activity_logs` +
    joined `full_name`, `business_name`
  - `getBusinessGrowth(int $days = 7): array` → `[['date'=>..., 'count'=>...]]`

**Step 1 — Tulis test yang gagal** (`tests/PlatformStatsTest.php`):

```php
<?php
/**
 * tests/PlatformStatsTest.php
 * Slice Admin\PlatformStats: KPI platform (lintas-business), API usage, aktivitas, pertumbuhan bisnis.
 */

use App\Admin\PlatformStats;
use App\Customers\CustomerRepository;
use App\Transactions\TransactionRepository;
use PHPUnit\Framework\TestCase;

class PlatformStatsTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createOwner(): int
    {
        $email = 'owner_' . bin2hex(random_bytes(4)) . '@test.local';
        $this->db->prepare(
            "INSERT INTO users (email, password, full_name, role, is_active, email_verified)
             VALUES (?, ?, ?, 'umkm_owner', 1, 1)"
        )->execute([$email, password_hash('x', PASSWORD_DEFAULT), 'Owner Uji']);
        return (int)$this->db->lastInsertId();
    }

    public function testKpisMenghitungPlatform(): void
    {
        $base = (new PlatformStats($this->db))->getKpis();

        // Seed 1 owner + 1 business + 1 customer + 1 transaksi
        $owner = $this->createOwner();
        $this->db->prepare(
            "INSERT INTO businesses (user_id, name, owner_name, email) VALUES (?, 'Biz Uji', 'Owner Uji', ?)"
        )->execute([$owner, 'biz_' . uniqid() . '@test.local']);
        $biz = (int)$this->db->lastInsertId();

        $cust = (new CustomerRepository($this->db))->add($biz, 'Andi', '081100', '');
        (new TransactionRepository($this->db))->add($biz, $cust, date('Y-m-d'), 150000, 'Batik', 1);

        try {
            $kpis = (new PlatformStats($this->db))->getKpis();
            $this->assertSame($base['total_umkm'] + 1, $kpis['total_umkm']);
            $this->assertSame($base['total_businesses'] + 1, $kpis['total_businesses']);
            $this->assertSame($base['total_customers'] + 1, $kpis['total_customers']);
            $this->assertSame($base['total_transactions'] + 1, $kpis['total_transactions']);
            // revenue = SUM(amount), perilaku lama (abaikan qty)
            $this->assertEqualsWithDelta($base['total_revenue'] + 150000.0, (float)$kpis['total_revenue'], 0.01);

            // Pertumbuhan bisnis mencakup hari ini
            $growth = (new PlatformStats($this->db))->getBusinessGrowth(7);
            $today = array_filter($growth, fn($r) => $r['date'] === date('Y-m-d'));
            $this->assertNotEmpty($today, 'business growth harus memuat hari ini');

            // Aktivitas (log-nya dibuat seed di atas? activity_logs dikosongkan uji manual)
            $act = (new PlatformStats($this->db))->getRecentActivities(1);
            $this->assertIsArray($act);
        } finally {
            $this->db->prepare("DELETE FROM transactions WHERE customer_id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM customers WHERE id = ?")->execute([$cust]);
            $this->db->prepare("DELETE FROM businesses WHERE id = ?")->execute([$biz]);
            $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$owner]);
        }
    }

    public function testApiUsageTodayMengelompokkanBerdasarkanTipe(): void
    {
        $before = (new PlatformStats($this->db))->getApiUsageToday();
        $this->db->prepare(
            "INSERT INTO api_usage_logs (business_id, api_type, endpoint, status, created_at)
             VALUES (NULL, 'openai', '/x', 'success', NOW())"
        )->execute();
        $this->db->prepare(
            "INSERT INTO api_usage_logs (business_id, api_type, endpoint, status, created_at)
             VALUES (NULL, 'openai', '/y', 'success', NOW())"
        )->execute();
        try {
            $usage = (new PlatformStats($this->db))->getApiUsageToday();
            $this->assertGreaterThanOrEqual(($before['openai'] ?? 0) + 2, (int)($usage['openai'] ?? 0));
        } finally {
            $this->db->exec("DELETE FROM api_usage_logs WHERE api_type = 'openai' AND endpoint IN ('/x','/y')");
        }
    }
}
```

**Step 2 — Jalankan & pastikan gagal:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/PlatformStatsTest.php
```
→ FAIL/Error: class `App\Admin\PlatformStats` belum ada (autoload belum tahu file-nya).

**Step 3 — Implementasi minimal** (`src/Admin/PlatformStats.php`):

```php
<?php
/**
 * src/Admin/PlatformStats.php
 * Slice vertikal "Admin" (panel super_admin): agregat platform lintas-business
 * untuk admin/dashboard.php. Pola meniru src/Dashboard/DashboardStats.php.
 */

namespace App\Admin;

class PlatformStats
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** KPI platform. revenue = SUM(amount) (perilaku lama, abaikan qty). */
    public function getKpis(): array
    {
        return [
            'total_umkm'        => (int)$this->scalar("SELECT COUNT(*) FROM users WHERE role = 'umkm_owner' AND is_active = 1"),
            'total_businesses'  => (int)$this->scalar('SELECT COUNT(*) FROM businesses'),
            'total_customers'   => (int)$this->scalar('SELECT COUNT(*) FROM customers'),
            'total_transactions'=> (int)$this->scalar('SELECT COUNT(*) FROM transactions'),
            'total_revenue'     => (float)$this->scalar('SELECT COALESCE(SUM(amount),0) FROM transactions'),
            'active_today'      => (int)$this->scalar('SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE DATE(created_at) = CURDATE()'),
        ];
    }

    /** Pemakaian API hari ini: [api_type => count]. */
    public function getApiUsageToday(): array
    {
        $stmt = $this->db->query(
            "SELECT api_type, COUNT(*) as c FROM api_usage_logs WHERE DATE(created_at) = CURDATE() GROUP BY api_type"
        );
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Aktivitas terbaru + data user/bisnis. LIMIT inline (int) — gotcha PDO. */
    public function getRecentActivities(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, u.full_name, b.name as business_name
             FROM activity_logs a
             LEFT JOIN users u ON a.user_id = u.id
             LEFT JOIN businesses b ON a.business_id = b.id
             ORDER BY a.created_at DESC
             LIMIT " . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Pertumbuhan bisnis per hari (N hari terakhir). */
    public function getBusinessGrowth(int $days = 7): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM businesses
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)
             GROUP BY DATE(created_at)
             ORDER BY date"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function scalar(string $sql)
    {
        return $this->db->query($sql)->fetchColumn();
    }
}
```

> Pastikan `composer dump-autoload -o` (PSR-4 `App\` → `src/`) agar class baru
> terautoload, lalu autoload sudah di-register oleh test bootstrap.

**Step 4 — Jalankan & pastikan pass:**
```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/PlatformStatsTest.php
```
→ PASS (2 test). Lalu suite penuh (jangan rusakkan test lain).

**Step 5 — Lint & test penuh:**
```bash
php -l src/Admin/PlatformStats.php
COMPOSER_ALLOW_SUPERUSER=1 composer test
```

**Step 6 — Commit:**
```bash
git add src/Admin/PlatformStats.php tests/PlatformStatsTest.php
git commit -m "feat(admin): slice PlatformStats untuk KPI platform lintas-business"
```

> Catatan TDD: `getRecentActivities` hanya diuji strukturnya (`assertIsArray`) —
> aktivitas tidak di-seed di test (log seed bisa settle antar-test lain). Unit yang
> benar-benar diuji nilainya: KPI (delta) & API usage grouping. Ini keputusan test
> yang jujur, bukan placeholder.

---

# Sprint 2 — Relayout `admin/dashboard.php` (Cockpit)

## Task 2.1: Slice untuk `.kpi-card` di `admin/assets/admin-styles.css`

**Files:**
- Modify: `admin/assets/admin-styles.css` (tambah `body` bg + `.kpi-card`)
- Test: `tests/AdminDashboardTest.php` (baru, sebagian)

**Step 1 — Tulis test gagal** (`tests/AdminDashboardTest.php`):

```php
<?php
/**
 * tests/AdminDashboardTest.php
 * Mengunci relayout dashboard admin (cockpit monitoring desktop-first):
 * 1. admin/dashboard.php memakai slice App\Admin\PlatformStats (bukan $db->query inline utk KPI)
 *    dan memuat vendor/autoload.php (AGENTS §2.10).
 * 2. Dead <style> (.stat-card.users) dihapus; label bahasa Indonesia.
 * 3. admin-styles.css punya .kpi-card & body bg var(--bg-soft).
 */

use PHPUnit\Framework\TestCase;

class AdminDashboardTest extends TestCase
{
    public function testDashboardPakaiSliceDanAutoload(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/dashboard.php');
        $this->assertNotFalse($src, 'admin/dashboard.php harus bisa dibaca');
        $this->assertStringContainsString('../vendor/autoload.php', $src, 'dashboard admin wajib memuat autoload (pakai App\*)');
        $this->assertStringContainsString('App\\Admin\\PlatformStats', $src, 'dashboard wajib pakai slice PlatformStats');
        // KPI TIDAK dihitung inline via $db->query untuk COUNT/SELECT platform
        $this->assertStringNotContainsString("SELECT COUNT(*) as total", $src, 'KPI engine tak boleh inline (dipindah ke slice)');
    }

    public function testDashboardTanpaDeadCssDanBerbahasaIndonesia(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/admin/dashboard.php');
        $this->assertStringNotContainsString('.stat-card.users', $src, 'dead <style> .stat-card.* harus dihapus');
        $this->assertStringNotContainsString('Platform Overview', $src, 'label Ringkasan Platform utk judul');
        $this->assertStringContainsString('Ringkasan Platform', $src, 'judul berbahasa Indonesia');
        $this->assertStringContainsString('Total Pelanggan', $src, 'label Total Pelanggan (bukan Customers)');
        $this->assertStringContainsString('Total Omzet', $src, 'label Total Omzet (bukan Revenue)');
        $this->assertStringContainsString('Total Bisnis', $src, 'label Total Bisnis');
        $this->assertStringContainsString('User Aktif Hari Ini', $src, 'label User Aktif Hari Ini');
    }

    public function testStylesheetAdminPunyaKpiCard(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/admin/assets/admin-styles.css');
        $this->assertNotFalse($css, 'admin-styles.css harus bisa dibaca');
        $this->assertStringContainsString('.kpi-card', $css, 'admin-styles: .kpi-card wajib ada');
        $this->assertStringContainsString('var(--bg-soft)', $css, 'admin-styles: body bg pakai token');
    }
}
```

**Step 2 — Jalankan & pastikan gagal:** `vendor/bin/phpunit tests/AdminDashboardTest.php`
→ FAIL (class belum dipakai, dead CSS masih ada, .kpi-card belum ada).

**Step 3 — Implementasi CSS (append/admin `admin/assets/admin-styles.css`):**
Tambahkan di bagian atas (setelah `:root` tokens yang sudah ada, Task S1 dari plan
mobile) `body` background, dan append `.kpi-card`:

```css
body { background-color: var(--bg-soft); }
```

Append di akhir file:

```css
/* ===== Kartu KPI dashboard admin (cockpit monitoring) ===== */
.kpi-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 14px;
    height: 100%;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .05);
}
.kpi-card .kpi-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.25rem; color: #fff;
    flex-shrink: 0;
}
.kpi-card .kpi-icon.teal  { background: var(--grad-brand); }
.kpi-card .kpi-icon.amber { background: linear-gradient(135deg, #f59e0b, #f97316); }
.kpi-card .kpi-icon.blue  { background: linear-gradient(135deg, #3b82f6, #6366f1); }
.kpi-card .kpi-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.kpi-card .kpi-icon.red   { background: linear-gradient(135deg, #ef4444, #dc2626); }
.kpi-card .kpi-icon.gray  { background: linear-gradient(135deg, #6b7280, #4b5563); }
.kpi-card .kpi-value { font-size: 1.35rem; font-weight: 700; color: var(--ink); line-height: 1.1; }
.kpi-card .kpi-label { color: var(--muted); font-size: .78rem; }

@media (max-width: 767.98px) {
    .kpi-card { flex-direction: column-reverse; align-items: flex-start; gap: 8px; }
    .kpi-value { font-size: 1.2rem; }
}
```

**Step 4 — Lint & test:** `php -l admin/assets/admin-styles.css` + `composer test`.

**Step 5 — Commit:**
```bash
git add admin/assets/admin-styles.css tests/AdminDashboardTest.php
git commit -m "feat(admin): gaya kartu KPI (.kpi-card) + body bg token utk dashboard admin"
```

---

## Task 2.2: Relayout `admin/dashboard.php` memakai slice + label Indonesia

**Files:**
- Modify: `admin/dashboard.php`
- Test: `tests/AdminDashboardTest.php` (suplemen — sudah ada assertion)

**Step 1 — Test sudah ditulis di Task 2.1** (semua method) → masih FAIL sampai
Task 2.2.

**Step 2 — Implementasi:**

a) Tambah autoload di paling atas file (sebelum include config):

```php
<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/auth.php';

requireAuth(['super_admin']);

$user = getCurrentUser();
$db = getDB();

$platform = new \App\Admin\PlatformStats($db);
$kpis = $platform->getKpis();
$apiUsage = $platform->getApiUsageToday();
$recentActivities = $platform->getRecentActivities(10);
$businessGrowth = $platform->getBusinessGrowth(7);
```

> Pindahkan blok PHP dari tengah `<body>` (yang sekarang) ke atas file sebelum
> `<!DOCTYPE html>`, dan HAPUS 8 query `$db->query(...)` inline sebelumnya yang
> menghitung `total_umkm ... business_growth`. Blok PHP lama (di dalam body) &
> pembukaan `<!DOCTYPE html><head>` + `<style>` usang dimigrasi.

b) `<head>` — hapus blok `<style>` usang (body bg + `.stat-card.*` dead), karena
`admin-styles.css` kini menangani `body` bg & `.kpi-card`. `<head>` menjadi:

```html
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Smart Marketing Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
```

c) `body` — pertahankan tombol `.mobile-menu-toggle` + include sidebar + wrap
`.main-content` (pola yang ada). Ganti judul:

```html
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-tachometer-alt me-2"></i> Ringkasan Platform</h2>
    <div class="text-muted"><i class="fas fa-calendar me-2"></i><?= date('l, d F Y') ?></div>
</div>
```

d) **6 kartu KPI** (ganti seluruh blok 4-kartu `stats-card`):

```html
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <span class="kpi-icon teal"><i class="fas fa-store"></i></span>
            <div>
                <div class="kpi-value"><?= $kpis['total_umkm'] ?></div>
                <div class="kpi-label">Total UMKM</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <span class="kpi-icon amber"><i class="fas fa-building"></i></span>
            <div>
                <div class="kpi-value"><?= $kpis['total_businesses'] ?></div>
                <div class="kpi-label">Total Bisnis</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <span class="kpi-icon green"><i class="fas fa-users"></i></span>
            <div>
                <div class="kpi-value"><?= number_format($kpis['total_customers']) ?></div>
                <div class="kpi-label">Total Pelanggan</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <span class="kpi-icon blue"><i class="fas fa-shopping-cart"></i></span>
            <div>
                <div class="kpi-value"><?= number_format($kpis['total_transactions']) ?></div>
                <div class="kpi-label">Total Transaksi</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <span class="kpi-icon amber"><i class="fas fa-money-bill-wave"></i></span>
            <div>
                <div class="kpi-value">Rp <?= number_format($kpis['total_revenue'] / 1000000, 1) ?>M</div>
                <div class="kpi-label">Total Omzet</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="kpi-card">
            <span class="kpi-icon gray"><i class="fas fa-user-check"></i></span>
            <div>
                <div class="kpi-value"><?= $kpis['active_today'] ?></div>
                <div class="kpi-label">User Aktif Hari Ini</div>
            </div>
        </div>
    </div>
</div>
```

e) **2 grafik** (ganti blok "API Usage & Activity"): API usage (doughnut) +
**business growth (bar, yang tadinya dead query)**:

```html
<div class="row g-3 mb-4">
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-line me-2"></i> Pertumbuhan Bisnis (7 Hari)</h5></div>
            <div class="card-body">
                <?php if (empty($businessGrowth)): ?>
                    <p class="text-muted text-center mb-0">Belum ada bisnis baru 7 hari terakhir.</p>
                <?php else: ?>
                    <div class="chart-container"><canvas id="growthChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i> API Usage Hari Ini</h5></div>
            <div class="card-body">
                <?php if (empty($apiUsage)): ?>
                    <p class="text-muted text-center mb-0">Belum ada aktivitas API hari ini.</p>
                <?php else: ?>
                    <div class="chart-container"><canvas id="apiUsageChart"></canvas></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
```

f) **Tabel aktivitas** (ganti header & pastikan label Indonesia; kolom tetap):
Waktu / User / Bisnis / Aktivitas / Deskripsi — sudah Indonesia kecuali header.
Ganti `<h5>... Aktivitas Terbaru` tetap; header tabel sudah "Waktu/User/Bisnis/
Aktivitas/Deskripsi" (sudah Indonesia). Ubah variabel `$recent_activities` →
`$recentActivities` di loop.

g) **Script Chart.js** — tambah chart growth (bar) dan sesuaikan `apiUsageChart`
tetap doughnut; ganti `$recent_activities` reference:

```js
        // Business Growth Chart
        <?php if (!empty($businessGrowth)): ?>
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        new Chart(growthCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($businessGrowth, 'date')) ?>,
                datasets: [{
                    label: 'Bisnis baru',
                    data: <?= json_encode(array_map('intval', array_column($businessGrowth, 'count'))) ?>,
                    backgroundColor: 'rgba(15, 118, 110, .75)',
                    borderRadius: 6
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
        <?php endif; ?>
```
(blok `apiUsageChart` doughnut dipertahankan; ganti nama variabel jika perlu.)

**Step 3 — Lint & test:**
```bash
php -l admin/dashboard.php
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/AdminDashboardTest.php tests/PlatformStatsTest.php
COMPOSER_ALLOW_SUPERUSER=1 composer test
```
→ `testDashboardPakaiSliceDanAutoload`, `testDashboardTanpaDeadCssDanBerbahasaIndonesia`,
`testStylesheetAdminPunyaKpiCard` PASS.

**Step 4 — Smoke render CLI:** `tests/AdminSidebarTest` SUDAH merender
`admin/dashboard.php` via CLI child (session `super_admin`, DB test) — pastikan
`composer test` tetap hijau setelah relayout+pindah blok PHP ke atas (renders tanpa
HTTP 500/OOM). Ini smoke render otomatis; tidak perlu skrip terpisah.

**Step 5 — Commit:**
```bash
git add admin/dashboard.php tests/AdminDashboardTest.php
git commit -m "feat(admin): relayout dashboard jadi cockpit monitoring + label Indonesia + hapus dead CSS"
```

---

# Sprint 3 — Verifikasi & Docs

## Task 3.1: Dokumentasi README

**Files:**
- Modify: `README.md` (bagian Admin Panel / Performance → sebut dashboard admin
  sebagai cockpit monitoring + slice `src/Admin`)

**Step 1 — Update README:** tambahkan pada deskripsi Admin Panel / File Structure
(baris `src/`): `src/Admin/PlatformStats.php` + 1 kalimat: "Dashboard admin =
cockpit monitoring desktop-first (6 KPI, grafik pertumbuhan & API usage, tabel
aktivitas), memakai slice `App\Admin\PlatformStats`."

**Step 2 — Lint & commit:**
```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
COMPOSER_ALLOW_SUPERUSER=1 composer test
git add README.md
git commit -m "docs: catat dashboard admin cockpit monitoring & slice src/Admin"
```

## Task 3.2: Verifikasi final

```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
COMPOSER_ALLOW_SUPERUSER=1 composer test
COMPOSER_ALLOW_SUPERUSER=1 composer validate --no-check-publish
COMPOSER_ALLOW_SUPERUSER=1 composer audit
git status --short   # hanya file yang dimaksud
```

## Task 3.3: Commit plan

```bash
git add docs/plans/2026-08-18-admin-dashboard-relayout.md
git commit -m "docs: plan redesign dashboard admin (cockpit monitoring, 3 sprint)"
```

---

## 5. Di Luar Scope (didefer/terpisah)

- **6 halaman admin lain** (users, businesses, analytics, api-management, settings,
  reports) — tetap di-recolor saja, tidak di-relayout (plan ini khusus dashboard).
- **Sidebar admin relayout & bottom-nav** — admin desktop-first; tidak perlu.
- **Dark mode / tema** — tidak diminta; tokens memudahkan penambahan.
- **RENCANA_PERBAIKAN 2.4 (CSP) / 2.2 (kunci IP/UA)** — tetap didefer.
- **Table-cards mobile utk admin** — tidak relevan (admin desktop).

## 6. Self-Review

1. **Coverage spec:** "layout dashboard paling sesuai admin" → 6 KPI satu baris +
   grafik pertumbuhan/API + tabel aktivitas (Task 2.2); konsistensi identitas
   (Task 2.1 `.kpi-card`); bahasa Indonesia (Task 2.2). "Sebagai admin = desktop-
   first" → di Global Constraints & Di Luar Scope (tanpa bottom-nav).
2. **Placeholder:** semua diff konkret (slice PHP penuh, markup KPI/grafik, CSS).
3. **Konsistensi:** `PlatformStats` mengikuti pola `DashboardStats`; gotcha
   `LIMIT`/`INTERVAL` inline `(int)`; autoload sesuai AGENTS §2.10; perilaku
   revenue `SUM(amount)` dipertahankan.
4. **TDD:** `PlatformStatsTest` ditulis & dilihat gagal di Task 1.1;
   `AdminDashboardTest` ditulis di Task 2.1 (CSS) & dipecah agar Task 2.1 dan 2.2
   masing-masing hijau. Tidak ada task yang menuntut test belum didefinisikan.
5. **Test DB:** `PlatformStatsTest` menggunakan delta baseline (bukan asumsi empty
   global — test lain bisa menyisakan data) + cleanup di `finally`.

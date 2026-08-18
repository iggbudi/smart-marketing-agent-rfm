# Vertical Slicing (Refactor Fitur-First) — Implementation Plan

**Goal:** Restrukturisasi aplikasi UMKM owner dari struktur **horizontal** (halaman gemuk =
SQL + HTML campur, API tipis dengan query inline, helper `includes/` lintas-fitur) menjadi
**8 slice vertikal**, satu slice per fitur user-visible, yang masing-masing self-contained:
data access + aturan bisnis dalam satu class `App\<Fitur>\*`, halaman/API menjadi tipis
(hanya lapisan HTTP + render), dan satu file test per slice. URL tetap `file.php` di
docroot (tanpa router — konvensi AGENTS.md §1 tetap dijaga), sehingga tidak ada perubahan
URL publik, sidebar, maupun perilaku.

**Architecture:** Pola "thin page → feature class". Setiap fitur memiliki class PSR-4 di
`src/App/<Fitur>/` (autoload `App\` → `src/` sudah ada di composer.json) yang memegang
SEMUA query + aturan bisnis fitur tsb; halaman docroot hanya: `requireAuth` → ambil
business → handle POST (CSRF) → panggil class → render HTML (variabel HTML tidak berubah
sama sekali). Cross-cutting yang TETAP di `includes/`: `sidebar.php` (satu sumber menu) &
`pagination.php`. `config/auth.php` (AuthManager), `config/openai.php` (OpenAIClient),
`config/env.php`, `config/database.php` tetap. `includes/rfm.php`, `includes/import.php`,
`includes/export.php` dibubarkan ke class vertikal masing-masing. `src/Rfm.php` tetap
(single source of truth skor/segmentasi, JANGAN diubah).

**Tech Stack:** PHP 7.4+ (runtime 8.3.6), PDO/MariaDB, PhpSpreadsheet 1.30.6+, PHPUnit 9.6,
`composer dump-autoload` (PSR-4).

**Spec:** Permintaan user "rencanakan vertical slicing project ini" (2026-08-18).
Interpretasi: refactor struktur kode menjadi slice per fitur (bukan slice per lapisan
teknologi, dan bukan perubahan URL/router). Acuan teknis: AGENTS.md §1 (peta arsitektur),
§2 (PDO prepared, htmlspecialchars, CSRF, scope business_id, gotcha LIMIT/OFFSET `(int)`),
§5 (test: DB `smart_marketing_rfm_test`, jangan pernah DB produksi), §6 (commit), §8
(checklist refactor per area).

## 1. Kondisi Sekarang → Target (Peta File)

| Kondisi sekarang (horizontal) | Target (vertikal) |
|---|---|
| `customers.php` (390 baris, SQL inline) | `src/App/Customers/CustomerRepository.php` + `customers.php` tipis |
| `transactions.php` (439 baris, SQL inline) | `src/App/Transactions/TransactionRepository.php` + `transactions.php` tipis |
| `dashboard.php` (351 baris, SQL inline) | `src/App/Dashboard/DashboardStats.php` + `dashboard.php` tipis |
| `analysis.php` + `includes/rfm.php` (recalculateRFM) | `src/App/Rfm/RfmService.php` + `analysis.php` tipis; `includes/rfm.php` DIHAPUS |
| `upload.php` + `api/upload-excel.php` (validasi duplikat) + `includes/import.php` | `src/App/Import/SpreadsheetImporter.php` + `src/App/Upload/UploadValidator.php` (validasi dedupe) + keduanya tipis; `includes/import.php` DIHAPUS |
| `api/export-customers.php` + `api/export-transactions.php` + `includes/export.php` | `src/App/Export/CustomersExporter.php` + `src/App/Export/TransactionsExporter.php`; API tipis; `includes/export.php` DIHAPUS |
| `ai-content.php` (HTTP internal ke API) + `api/generate-content.php` (dummy inline) | `src/App/Ai/ContentGenerator.php`; page panggil class langsung (hapus HTTP internal); API tipis |
| `profile.php` (validasi + update inline) | `src/App/Business/BusinessProfileService.php` + `profile.php` tipis |
| `includes/sidebar.php`, `includes/pagination.php`, `config/*` | TETAP (cross-cutting) |
| `tests/*` (6 file campur) | + 8 file test baru, `ExportTest.php` ditulis ulang |

## 2. Batas Slice, Urutan & Dependensi

Urutan eksekusi = urutan dependensi. Setiap slice berdiri sendiri, `composer test` hijau
setelah tiap commit.

| # | Slice | Dependensi | File baru |
|---|---|---|---|
| 1 | Customers | — | `src/App/Customers/CustomerRepository.php`, `tests/CustomerRepositoryTest.php` |
| 2 | Transactions | Customers (`listForDropdown`) | `src/App/Transactions/TransactionRepository.php`, `tests/TransactionRepositoryTest.php` |
| 3 | Dashboard | Customers + Transactions | `src/App/Dashboard/DashboardStats.php`, `tests/DashboardStatsTest.php` |
| 4 | RFM Analysis | `src/Rfm.php` (tetap) | `src/App/Rfm/RfmService.php`, `tests/RfmServiceTest.php` |
| 5 | Import + Upload | PhpSpreadsheet | `src/App/Import/SpreadsheetImporter.php`, `src/App/Upload/UploadValidator.php`, `tests/ImportTest.php`, `tests/UploadValidatorTest.php` |
| 6 | Export | Customers + Transactions | `src/App/Export/CustomersExporter.php`, `src/App/Export/TransactionsExporter.php`, `tests/ExportTest.php` (tulis ulang) |
| 7 | AI Content | `config/openai.php` | `src/App/Ai/ContentGenerator.php`, `tests/ContentGeneratorTest.php` |
| 8 | Profil Bisnis | — | `src/App/Business/BusinessProfileService.php`, `tests/BusinessProfileServiceTest.php` |
| 9 | Dokumentasi | semua | AGENTS.md + README.md |

**Di luar scope plan ini (jangan disentuh):**
- Admin (`admin/*.php`, 7 halaman ~2900 baris) — subsistem terpisah → plan lanjutan terpisah bila diminta.
- Landing `index.php` (baru selesai di plan sebelumnya), `login.php`/`logout.php`, `unauthorized.php`.
- `budget.php` / `budget_simple.php` — legacy standalone (tidak ada di sidebar, punya nav inline sendiri); tidak dilink, tidak dihapus, tidak dirampingkan.
- `src/Rfm.php` — single source of truth, JANGAN diubah.
- Item deferred `RENCANA_PERBAIKAN.md` (CSP, kunci session IP/UA) — tidak tersentuh.
- **Migrasi framework (Laravel/CodeIgniter) — DITOLAK untuk saat ini (keputusan 2026-08-18).**
  Alasan: kode aplikasi ±9.500 baris (skeleton framework sudah sebesar itu); sistem sudah
  berfungsi & teruji (composer test hijau, audit 0 CVE); constraint `php >=7.4` vs Laravel/CI
  modern (8.1+); deployment docroot=root repo akan berubah total ke `public/` (konfigurasi
  Nginx di luar repo); hal yang framework tawarkan (auth/CSRF/pagination/validasi) sudah ada
  dan di-test. Slice vertikal di plan ini justru memisahkan logika dari lapisan HTTP sehingga
  migrasi framework di masa depan lebih murah (rewrite terpisah, bukan refactor).

**Temuan selama pemetaan (dictatat, TIDAK diperbaiki di plan ini — di luar scope):**
1. `dashboard.php` form AI Content punya `<option value="Cannot Lose Them">` yang bukan segmen
   `src/Rfm.php` (5 segmen) — inkonsistensi lama, biarkan.
2. `analysis.php` punya `</div>` ekstra di akhir (HTML quirk lama) — biarkan.
3. `api/export-customers.php` memakai `$business['business_name']` sedangkan kolom tabel adalah
   `name` → di Task 6 diganti `$business['name']` (bug kecil yang ikut teratasi).

## Global Constraints
- Satu commit = satu unit kerja (AGENTS.md §6): prefix `refactor(<area>): ...` per task.
- Semua query dinamis: PDO prepared statement; output tetap `htmlspecialchars()` di view.
- Scope `business_id` dari session WAJIB di semua method repository (tolak lintas-bisnis).
- Gotcha pagination: `LIMIT/OFFSET` di-cast `(int)` inline — TIDAK boleh placeholder (AGENTS.md §2.1).
- `composer test` hijau sebelum commit; test memakai DB `smart_marketing_rfm_test`
  (tests/bootstrap.php), JANGAN pernah DB produksi. Saat root: `COMPOSER_ALLOW_SUPERUSER=1`.
- Jangan commit rahasia (.env, config/*.php). Jangan ubah `src/Rfm.php`.
- Test DB perlu schema terkini: `sed '/^USE /d' database_schema.sql database_update.sql database_indexes.sql | mysql -u root smart_marketing_rfm_test` bila perlu.

---

### Task 1: Slice Customers

**Files:**
- Create: `src/App/Customers/CustomerRepository.php`
- Modify: `customers.php` (blok PHP baris 1–123 diganti; HTML/JS mulai `<!DOCTYPE html>` TIDAK berubah)
- Test: `tests/CustomerRepositoryTest.php`

**Interfaces:**
- Consumes: `\PDO` (dari `getDB()`); `includes/pagination.php` (tetap di halaman).
- Produces: `CustomerRepository::count()`, `countActive()`, `totalSales()`, `listForDropdown()`,
  `countSearch()`, `search()`, `withStats()`, `add()`, `delete()` — dipakai Task 2 (dropdown),
  Task 3 (count), Task 6 (withStats), dan `customers.php`.

- [ ] **Step 1: Tulis test yang gagal** — `tests/CustomerRepositoryTest.php`:

```php
<?php
/**
 * tests/CustomerRepositoryTest.php
 * Slice Customers: CRUD, search+pagination, agregat — terhadap DB test.
 */

use App\Customers\CustomerRepository;
use PHPUnit\Framework\TestCase;

class CustomerRepositoryTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createBusiness(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO businesses (name, owner_name, email, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['CustRepo Biz ' . uniqid(), 'Owner', 'repo' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    private function createCustomer(int $businessId, string $name, string $phone, string $email = ''): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO customers (business_id, customer_name, phone, email, created_at) VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$businessId, $name, $phone, $email !== '' ? $email : null]);
        return (int)$this->db->lastInsertId();
    }

    private function createTransaction(int $businessId, int $customerId, string $date, float $amount): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO transactions (business_id, customer_id, transaction_date, amount, product_name, quantity, created_at)
             VALUES (?, ?, ?, ?, NULL, 1, NOW())"
        );
        $stmt->execute([$businessId, $customerId, $date, $amount]);
    }

    public function testAddRejectsEmptyNameOrPhone()
    {
        $repo = new CustomerRepository($this->db);
        $biz = $this->createBusiness();

        $this->expectException(\InvalidArgumentException::class);
        $repo->add($biz, '  ', '0811', '');
    }

    public function testDeleteIsScopedByBusiness()
    {
        $bizA = $this->createBusiness();
        $bizB = $this->createBusiness();
        $repo = new CustomerRepository($this->db);

        $id = $repo->add($bizA, 'Andi', '0811', 'andi@test.local');
        $this->assertGreaterThan(0, $id);

        // delete dengan business lain harus gagal (data milik A tidak terhapus)
        $this->assertFalse($repo->delete($bizB, $id));
        $this->assertSame(1, $repo->count($bizA));

        $this->assertTrue($repo->delete($bizA, $id));
        $this->assertSame(0, $repo->count($bizA));
    }

    public function testSearchAndAggregates()
    {
        $biz = $this->createBusiness();
        $repo = new CustomerRepository($this->db);

        $c1 = $this->createCustomer($biz, 'Budi Santoso', '0812');
        $this->createCustomer($biz, 'Sari Dewi', '0822');
        $this->createTransaction($biz, $c1, '2026-08-01', 150000);
        $this->createTransaction($biz, $c1, '2026-08-10', 200000);

        $this->assertSame(2, $repo->count($biz));
        $this->assertSame(1, $repo->countActive($biz));
        $this->assertEqualsWithDelta(350000.0, $repo->totalSales($biz), 0.01);

        // search memfilter nama/HP/email
        $this->assertSame(1, $repo->countSearch($biz, 'santoso'));
        $rows = $repo->search($biz, 'santoso', 20, 0);
        $this->assertCount(1, $rows);
        $this->assertSame('Budi Santoso', $rows[0]['customer_name']);
        $this->assertSame('2', $rows[0]['total_transactions']);
        $this->assertEqualsWithDelta(350000.0, (float)$rows[0]['total_spent'], 0.01);

        // pagination: perPage membatasi baris, total tetap
        $this->assertSame(2, $repo->countSearch($biz, ''));
        $page1 = $repo->search($biz, '', 1, 0);
        $this->assertCount(1, $page1);
    }

    public function testWithStatsAndDropdown()
    {
        $biz = $this->createBusiness();
        $repo = new CustomerRepository($this->db);
        $this->createCustomer($biz, 'Dewi', '0813', 'dewi@test.local');

        $rows = $repo->withStats($biz);
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('total_spent', $rows[0]);
        $this->assertArrayHasKey('last_transaction', $rows[0]);

        $drop = $repo->listForDropdown($biz);
        $this->assertSame('Dewi', $drop[0]['customer_name']);
        $this->assertArrayHasKey('phone', $drop[0]);
    }
}
```

- [ ] **Step 2: Jalankan & pastikan gagal** — `vendor/bin/phpunit tests/CustomerRepositoryTest.php`
  → FAIL: `Class 'App\Customers\CustomerRepository' not found`.

- [ ] **Step 3: Implementasi minimal** — `src/App/Customers/CustomerRepository.php`:

```php
<?php
/**
 * src/App/Customers/CustomerRepository.php
 * Slice vertikal "Customers": akses data + aturan bisnis pelanggan.
 * Dipakai oleh customers.php (list/search/pagination/CRUD), transactions.php
 * (dropdown), dashboard.php (count), api/export-customers.php (withStats).
 */

namespace App\Customers;

class CustomerRepository
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Jumlah seluruh pelanggan milik business. */
    public function count(int $businessId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM customers WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    /** Jumlah pelanggan yang pernah bertransaksi (distinct customer_id). */
    public function countActive(int $businessId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT customer_id) FROM transactions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    /** Total nominal seluruh transaksi business. */
    public function totalSales(int $businessId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (float)$stmt->fetchColumn();
    }

    /** Dropdown pelanggan (dipakai transactions.php). */
    public function listForDropdown(int $businessId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, customer_name, phone FROM customers WHERE business_id = ? ORDER BY customer_name"
        );
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Hitung baris hasil pencarian (untuk paginate). */
    public function countSearch(int $businessId, string $q): int
    {
        [$where, $params] = $this->buildSearchWhere($businessId, $q);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM customers c WHERE " . $where);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Cari pelanggan + agregat transaksi, dengan pagination server-side.
     * LIMIT/OFFSET di-cast (int) — gotcha PDO (AGENTS.md §2.1).
     */
    public function search(int $businessId, string $q, int $perPage, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($businessId, $q);
        $stmt = $this->db->prepare("
            SELECT c.*,
                   COUNT(t.id) as total_transactions,
                   COALESCE(SUM(t.amount), 0) as total_spent,
                   MAX(t.transaction_date) as last_transaction
            FROM customers c
            LEFT JOIN transactions t ON c.id = t.customer_id
            WHERE " . $where . "
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)$offset . "
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Seluruh pelanggan + agregat (untuk export CSV/XLSX). */
    public function withStats(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*,
                   COUNT(t.id) as total_transactions,
                   COALESCE(SUM(t.amount), 0) as total_spent,
                   MAX(t.transaction_date) as last_transaction
            FROM customers c
            LEFT JOIN transactions t ON c.id = t.customer_id
            WHERE c.business_id = ?
            GROUP BY c.id
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Tambah pelanggan. Throw \InvalidArgumentException bila nama/HP kosong. */
    public function add(int $businessId, string $name, string $phone, string $email): int
    {
        if (trim($name) === '' || trim($phone) === '') {
            throw new \InvalidArgumentException('Nama dan nomor HP harus diisi!');
        }
        $stmt = $this->db->prepare(
            "INSERT INTO customers (business_id, customer_name, phone, email, created_at) VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $businessId,
            trim($name),
            trim($phone),
            trim($email) !== '' ? trim($email) : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    /** Hapus pelanggan milik business saja (tolak lintas-bisnis). */
    public function delete(int $businessId, int $customerId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM customers WHERE id = ? AND business_id = ?");
        $stmt->execute([$customerId, $businessId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array{0: string, 1: array} [where, params] */
    private function buildSearchWhere(int $businessId, string $q): array
    {
        $where = 'c.business_id = ?';
        $params = [$businessId];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where .= ' AND (c.customer_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        return [$where, $params];
    }
}
```

- [ ] **Step 4: Jalankan & pastikan pass** — `vendor/bin/phpunit tests/CustomerRepositoryTest.php` → PASS.

- [ ] **Step 5: Rampingkan `customers.php`** — ganti blok PHP baris 1–123 (sampai `?>`
      sebelum `<!DOCTYPE html>`) dengan:

```php
<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/pagination.php';

// Require UMKM owner access
requireAuth(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    die('Error: No business associated with your account. Please contact administrator.');
}

$repo = new \App\Customers\CustomerRepository($db);
$message = '';
$messageType = '';

// Lapisan HTTP + CSRF; logika data & validasi di repository
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (($_POST['action'] ?? '') === 'add') {
        try {
            $repo->add($business['id'], trim($_POST['name'] ?? ''), trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''));
            $message = 'Pelanggan berhasil ditambahkan!';
            $messageType = 'success';
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
            $messageType = 'warning';
        } catch (\PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif (($_POST['action'] ?? '') === 'delete' && isset($_POST['customer_id'])) {
        try {
            $repo->delete($business['id'], (int)$_POST['customer_id']);
            $message = 'Pelanggan berhasil dihapus!';
            $messageType = 'success';
        } catch (\PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Statistik kartu (agregat penuh, bukan dari halaman aktif)
$totalCustomers = $repo->count($business['id']);
$activeCustomers = $repo->countActive($business['id']);
$totalSales = $repo->totalSales($business['id']);

// Pencarian server-side + pagination (LIMIT/OFFSET di-cast (int) di repository)
$search = trim($_GET['q'] ?? '');
$totalRows = $repo->countSearch($business['id'], $search);
[$page, $perPage, $offset, $totalPages] = paginate($totalRows);
$customers = $repo->search($business['id'], $search, $perPage, $offset);
?>
```

HTML/JS mulai `<!DOCTYPE html>` TIDAK berubah (variabel `$totalCustomers`, `$activeCustomers`,
`$totalSales`, `$search`, `$customers`, `$page`, `$perPage`, `$offset`, `$totalPages`,
`$totalRows`, `$message`, `$messageType` tetap sama).

- [ ] **Step 6: Lint & test penuh** — `php -l src/App/Customers/CustomerRepository.php`,
      `php -l customers.php`, `composer dump-autoload`, `composer test` → semua hijau (27 test lama + baru).
      Verifikasi render: `php -r` tidak perlu; cukup `composer test` + smoke via pola
      `tests/LandingPageRenderTest` bila ragu.

- [ ] **Step 7: Commit** — `git add src/App/Customers/CustomerRepository.php tests/CustomerRepositoryTest.php customers.php && git commit -m "refactor(customers): ekstrak slice Customers ke App\\Customers\\CustomerRepository, rampingkan customers.php"`

---

### Task 2: Slice Transactions

**Files:**
- Create: `src/App/Transactions/TransactionRepository.php`
- Modify: `transactions.php` (blok PHP baris 1–133 diganti; HTML/JS tidak berubah)
- Test: `tests/TransactionRepositoryTest.php`

**Interfaces:**
- Consumes: `\PDO`; `CustomerRepository::listForDropdown()` (dropdown pelanggan di halaman).
- Produces: `count()`, `totalRevenue()`, `countActiveCustomers()`, `countSearch()`, `search()`,
  `recent()`, `allWithCustomer()`, `add()`, `delete()` — dipakai Task 3 (count/revenue/recent),
  Task 6 (allWithCustomer), dan `transactions.php`.

- [ ] **Step 1: Tulis test yang gagal** — `tests/TransactionRepositoryTest.php`:

```php
<?php
/**
 * tests/TransactionRepositoryTest.php
 * Slice Transactions: CRUD, search+pagination, agregat — terhadap DB test.
 */

use App\Transactions\TransactionRepository;
use PHPUnit\Framework\TestCase;

class TransactionRepositoryTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createBusiness(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO businesses (name, owner_name, email, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['TxRepo Biz ' . uniqid(), 'Owner', 'txrepo' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    private function createCustomer(int $businessId, string $name, string $phone): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO customers (business_id, customer_name, phone, email, created_at) VALUES (?, ?, ?, NULL, NOW())"
        );
        $stmt->execute([$businessId, $name, $phone]);
        return (int)$this->db->lastInsertId();
    }

    public function testAddValidatesRequiredFields()
    {
        $repo = new TransactionRepository($this->db);
        $biz = $this->createBusiness();
        $cust = $this->createCustomer($biz, 'Andi', '0811');

        $id = $repo->add($biz, $cust, '2026-08-01', 150000, 'Batik Kawung', 2);
        $this->assertGreaterThan(0, $id);

        $this->expectException(\InvalidArgumentException::class);
        $repo->add($biz, $cust, '', 0, '', 1); // tanggal kosong & amount 0
    }

    public function testDeleteIsScopedByBusiness()
    {
        $bizA = $this->createBusiness();
        $bizB = $this->createBusiness();
        $repo = new TransactionRepository($this->db);
        $custA = $this->createCustomer($bizA, 'Sari', '0822');
        $custB = $this->createCustomer($bizB, 'Dewi', '0833');

        $txA = $repo->add($bizA, $custA, '2026-08-01', 100000, 'Batik', 1);
        $txB = $repo->add($bizB, $custB, '2026-08-02', 200000, 'Batik', 1);

        $this->assertFalse($repo->delete($bizA, $txB)); // bukan milik A
        $this->assertSame(1, $repo->count($bizA));
        $this->assertTrue($repo->delete($bizA, $txA));
        $this->assertSame(0, $repo->count($bizA));
    }

    public function testSearchAggregatesAndRecent()
    {
        $biz = $this->createBusiness();
        $repo = new TransactionRepository($this->db);
        $cust = $this->createCustomer($biz, 'Budi Santoso', '0812');

        $repo->add($biz, $cust, '2026-08-01', 150000, 'Batik Kawung', 1);
        $repo->add($biz, $cust, '2026-08-10', 200000, 'Batik Parang', 1);

        $this->assertSame(2, $repo->count($biz));
        $this->assertEqualsWithDelta(350000.0, $repo->totalRevenue($biz), 0.01);
        $this->assertSame(1, $repo->countActiveCustomers($biz));

        // search oleh nama customer
        $this->assertSame(2, $repo->countSearch($biz, 'santoso'));
        $rows = $repo->search($biz, 'santoso', 20, 0);
        $this->assertCount(2, $rows);
        $this->assertSame('Budi Santoso', $rows[0]['customer_name']);

        // pagination
        $this->assertCount(1, $repo->search($biz, '', 1, 0));

        // recent membatasi jumlah
        $this->assertCount(2, $repo->recent($biz, 5));
        $this->assertCount(1, $repo->recent($biz, 1));

        // allWithCustomer untuk export
        $all = $repo->allWithCustomer($biz);
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('phone', $all[0]);
    }
}
```

- [ ] **Step 2: Jalankan & pastikan gagal** — `vendor/bin/phpunit tests/TransactionRepositoryTest.php` → FAIL (class belum ada).

- [ ] **Step 3: Implementasi minimal** — `src/App/Transactions/TransactionRepository.php`:

```php
<?php
/**
 * src/App/Transactions/TransactionRepository.php
 * Slice vertikal "Transactions": akses data + aturan bisnis transaksi.
 * Dipakai oleh transactions.php, dashboard.php (recent/revenue),
 * api/export-transactions.php (allWithCustomer).
 */

namespace App\Transactions;

class TransactionRepository
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function count(int $businessId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM transactions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    public function totalRevenue(int $businessId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (float)$stmt->fetchColumn();
    }

    public function countActiveCustomers(int $businessId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT customer_id) FROM transactions WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    public function countSearch(int $businessId, string $q): int
    {
        [$where, $params] = $this->buildSearchWhere($businessId, $q);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM transactions t JOIN customers c ON t.customer_id = c.id WHERE " . $where
        );
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function search(int $businessId, string $q, int $perPage, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($businessId, $q);
        $stmt = $this->db->prepare("
            SELECT t.*, c.customer_name, c.phone
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE " . $where . "
            ORDER BY t.transaction_date DESC, t.created_at DESC
            LIMIT " . (int)$perPage . " OFFSET " . (int)$offset . "
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Transaksi terbaru (dipakai dashboard). */
    public function recent(int $businessId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, c.customer_name
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE t.business_id = ?
            ORDER BY t.transaction_date DESC
            LIMIT " . (int)$limit . "
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Seluruh transaksi + data customer (untuk export). */
    public function allWithCustomer(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, c.customer_name, c.phone
            FROM transactions t
            JOIN customers c ON t.customer_id = c.id
            WHERE t.business_id = ?
            ORDER BY t.transaction_date DESC, t.created_at DESC
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tambah transaksi. Throw \InvalidArgumentException bila customer/tanggal/amount invalid.
     * quantity di-clamp minimal 1 (sama dgn perilaku lama `(int)$_POST['quantity'] ?: 1`).
     */
    public function add(int $businessId, int $customerId, string $transactionDate, float $amount, ?string $productName, int $quantity): int
    {
        if ($customerId <= 0 || $transactionDate === '' || $amount <= 0) {
            throw new \InvalidArgumentException('Customer, tanggal, dan jumlah harus diisi!');
        }
        $stmt = $this->db->prepare(
            "INSERT INTO transactions (business_id, customer_id, transaction_date, amount, product_name, quantity, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $businessId,
            $customerId,
            $transactionDate,
            $amount,
            $productName !== null && $productName !== '' ? $productName : null,
            max(1, $quantity),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function delete(int $businessId, int $transactionId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM transactions WHERE id = ? AND business_id = ?");
        $stmt->execute([$transactionId, $businessId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array{0: string, 1: array} [where, params] */
    private function buildSearchWhere(int $businessId, string $q): array
    {
        $where = 't.business_id = ?';
        $params = [$businessId];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where .= ' AND (c.customer_name LIKE ? OR t.product_name LIKE ?)';
            array_push($params, $like, $like);
        }
        return [$where, $params];
    }
}
```

- [ ] **Step 4: Jalankan & pastikan pass** — `vendor/bin/phpunit tests/TransactionRepositoryTest.php` → PASS.

- [ ] **Step 5: Rampingkan `transactions.php`** — ganti blok PHP baris 1–133 dengan:

```php
<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/pagination.php';

// Require UMKM owner access
requireAuth(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    die('Error: No business associated with your account. Please contact administrator.');
}

$repo = new \App\Transactions\TransactionRepository($db);
$customerRepo = new \App\Customers\CustomerRepository($db);
$message = '';
$messageType = '';

// Lapisan HTTP + CSRF; logika data & validasi di repository
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (($_POST['action'] ?? '') === 'add') {
        try {
            $repo->add(
                $business['id'],
                (int)trim($_POST['customer_id'] ?? ''),
                trim($_POST['transaction_date'] ?? ''),
                (float)trim($_POST['amount'] ?? ''),
                trim($_POST['product_name'] ?? '') !== '' ? trim($_POST['product_name']) : null,
                (int)($_POST['quantity'] ?? 1)
            );
            $message = 'Transaksi berhasil ditambahkan!';
            $messageType = 'success';
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage();
            $messageType = 'warning';
        } catch (\PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif (($_POST['action'] ?? '') === 'delete' && isset($_POST['transaction_id'])) {
        try {
            $repo->delete($business['id'], (int)$_POST['transaction_id']);
            $message = 'Transaksi berhasil dihapus!';
            $messageType = 'success';
        } catch (\PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Dropdown pelanggan (dari slice Customers)
$customers = $customerRepo->listForDropdown($business['id']);

// Statistik kartu (agregat penuh, bukan dari halaman aktif)
$totalTransactions = $repo->count($business['id']);
$totalRevenue = $repo->totalRevenue($business['id']);
$activeCustomers = $repo->countActiveCustomers($business['id']);
$avgTransaction = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

// Pencarian server-side + pagination
$search = trim($_GET['q'] ?? '');
$totalRows = $repo->countSearch($business['id'], $search);
[$page, $perPage, $offset, $totalPages] = paginate($totalRows);
$transactions = $repo->search($business['id'], $search, $perPage, $offset);
?>
```

HTML/JS tidak berubah (variabel `$customers`, `$totalTransactions`, `$totalRevenue`,
`$activeCustomers`, `$avgTransaction`, `$search`, `$transactions`, `$page`, `$perPage`,
`$offset`, `$totalPages`, `$totalRows` tetap sama).

- [ ] **Step 6: Lint & test penuh** — `php -l src/App/Transactions/TransactionRepository.php`, `php -l transactions.php`, `composer test` → hijau.

- [ ] **Step 7: Commit** — `git add src/App/Transactions/TransactionRepository.php tests/TransactionRepositoryTest.php transactions.php && git commit -m "refactor(transactions): ekstrak slice Transactions ke App\\Transactions\\TransactionRepository, rampingkan transactions.php"`

---

### Task 3: Slice Dashboard

**Files:**
- Create: `src/App/Dashboard/DashboardStats.php`
- Modify: `dashboard.php` (blok PHP baris 1–64 diganti; HTML/JS tidak berubah)
- Test: `tests/DashboardStatsTest.php`

**Interfaces:**
- Consumes: `\PDO`; `CustomerRepository::count()`; `TransactionRepository::count()/totalRevenue()/recent()`.
- Produces: `getStats()`, `getRecentTransactions()`, `getRfmData()`, `getRevenueTrend()`.

- [ ] **Step 1: Tulis test yang gagal** — `tests/DashboardStatsTest.php`:

```php
<?php
/**
 * tests/DashboardStatsTest.php
 * Slice Dashboard: agregat kartu, transaksi terbaru, distribusi RFM, tren revenue.
 */

use App\Customers\CustomerRepository;
use App\Dashboard\DashboardStats;
use App\Transactions\TransactionRepository;
use PHPUnit\Framework\TestCase;

class DashboardStatsTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createBusiness(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO businesses (name, owner_name, email, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['DashBiz ' . uniqid(), 'Owner', 'dash' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    public function testStatsRecentRfmAndTrend()
    {
        $biz = $this->createBusiness();
        $dash = new DashboardStats($this->db);

        // Seed: 2 customer, 2 transaksi, 1 baris rfm_analysis
        $custRepo = new CustomerRepository($this->db);
        $c1 = $custRepo->add($biz, 'Andi', '0811', '');
        $c2 = $custRepo->add($biz, 'Sari', '0822', '');
        $txRepo = new TransactionRepository($this->db);
        $txRepo->add($biz, $c1, date('Y-m-d'), 150000, 'Batik Kawung', 1);
        $txRepo->add($biz, $c2, date('Y-m-d', strtotime('-3 days')), 200000, 'Batik Parang', 2);

        $stmt = $this->db->prepare(
            "INSERT INTO rfm_analysis (business_id, customer_id, recency_score, frequency_score, monetary_score, rfm_segment, analysis_date, created_at)
             VALUES (?, ?, 5, 5, 5, 'Champions', CURDATE(), NOW())"
        );
        $stmt->execute([$biz, $c1]);

        $stats = $dash->getStats($biz);
        $this->assertSame(2, $stats['total_customers']);
        $this->assertSame(2, $stats['total_transactions']);
        $this->assertEqualsWithDelta(550000.0, (float)$stats['total_revenue'], 0.01);

        $recent = $dash->getRecentTransactions($biz, 1);
        $this->assertCount(1, $recent);
        $this->assertArrayHasKey('customer_name', $recent[0]);

        $rfm = $dash->getRfmData($biz);
        $this->assertSame(['Champions' => '1'], $rfm);

        $trend = $dash->getRevenueTrend($biz, 6);
        $this->assertNotEmpty($trend);
        $this->assertSame(date('Y-m'), $trend[0]['month']);
        $this->assertEqualsWithDelta(550000.0, (float)$trend[0]['revenue'], 0.01);
    }

    public function testEmptyBusinessHasZeroStats()
    {
        $biz = $this->createBusiness();
        $stats = (new DashboardStats($this->db))->getStats($biz);
        $this->assertSame(0, $stats['total_customers']);
        $this->assertSame(0, $stats['total_transactions']);
        $this->assertEqualsWithDelta(0.0, (float)$stats['total_revenue'], 0.01);

        $this->assertSame([], (new DashboardStats($this->db))->getRfmData($biz));
        $this->assertSame([], (new DashboardStats($this->db))->getRevenueTrend($biz, 6));
    }
}
```

- [ ] **Step 2: Jalankan & pastikan gagal** — `vendor/bin/phpunit tests/DashboardStatsTest.php` → FAIL.

- [ ] **Step 3: Implementasi minimal** — `src/App/Dashboard/DashboardStats.php`:

```php
<?php
/**
 * src/App/Dashboard/DashboardStats.php
 * Slice vertikal "Dashboard": agregat + data grafik untuk dashboard.php.
 * Memakai repository Customers & Transactions agar query tidak diduplikasi.
 */

namespace App\Dashboard;

use App\Customers\CustomerRepository;
use App\Transactions\TransactionRepository;

class DashboardStats
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /** Kartu statistik: total_customers, total_transactions, total_revenue. */
    public function getStats(int $businessId): array
    {
        $customers = new CustomerRepository($this->db);
        $transactions = new TransactionRepository($this->db);
        return [
            'total_customers' => $customers->count($businessId),
            'total_transactions' => $transactions->count($businessId),
            'total_revenue' => $transactions->totalRevenue($businessId),
        ];
    }

    public function getRecentTransactions(int $businessId, int $limit = 10): array
    {
        return (new TransactionRepository($this->db))->recent($businessId, $limit);
    }

    /** Distribusi segmen RFM: [segment => count]. */
    public function getRfmData(int $businessId): array
    {
        $stmt = $this->db->prepare(
            "SELECT rfm_segment, COUNT(*) as count FROM rfm_analysis WHERE business_id = ? GROUP BY rfm_segment"
        );
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Tren revenue per bulan (N bulan terakhir). */
    public function getRevenueTrend(int $businessId, int $months = 6): array
    {
        $stmt = $this->db->prepare("
            SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(amount) as revenue
            FROM transactions
            WHERE business_id = ? AND transaction_date >= DATE_SUB(NOW(), INTERVAL " . (int)$months . " MONTH)
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

- [ ] **Step 4: Jalankan & pastikan pass** — `vendor/bin/phpunit tests/DashboardStatsTest.php` → PASS.

- [ ] **Step 5: Rampingkan `dashboard.php`** — ganti blok PHP baris 1–64 (sampai `?>` sebelum
      `<!DOCTYPE html>`) dengan:

```php
<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Require UMKM owner access
requireAuth(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    die('Error: No business associated with your account. Please contact administrator.');
}

$dash = new \App\Dashboard\DashboardStats($db);

// Statistik & data grafik untuk business ini
$stats = $dash->getStats($business['id']);
$recent_transactions = $dash->getRecentTransactions($business['id'], 10);
$rfm_data = $dash->getRfmData($business['id']);
$revenue_trend = $dash->getRevenueTrend($business['id'], 6);
?>
```

HTML/JS tidak berubah (variabel `$stats`, `$recent_transactions`, `$rfm_data`, `$revenue_trend` sama).

- [ ] **Step 6: Lint & test penuh** — `php -l src/App/Dashboard/DashboardStats.php`, `php -l dashboard.php`, `composer test` → hijau.

- [ ] **Step 7: Commit** — `git add src/App/Dashboard/DashboardStats.php tests/DashboardStatsTest.php dashboard.php && git commit -m "refactor(dashboard): ekstrak slice Dashboard ke App\\Dashboard\\DashboardStats, rampingkan dashboard.php"`

---

### Task 4: Slice RFM Analysis

**Files:**
- Create: `src/App/Rfm/RfmService.php`
- Delete: `includes/rfm.php` (setelah semua pemakai dipindah; grep: hanya `analysis.php`)
- Modify: `analysis.php` (blok PHP baris 13–73 diganti; HTML/JS tidak berubah)
- Test: `tests/RfmServiceTest.php`

**Interfaces:**
- Consumes: `\PDO`; `\App\Rfm` (src/Rfm.php — single source of truth, TIDAK diubah); `auth()` untuk log aktivitas.
- Produces: `count()`, `recalculate()`, `ensureCalculated()`, `results()`, `segmentSummary()`.

**Catatan namespace:** `RfmService` berada di `namespace App\Rfm`, sehingga panggilan ke class
`App\Rfm` (src/Rfm.php) harus ditulis `\App\Rfm::...` (jangan `Rfm::` — itu akan resolve ke
`App\Rfm\Rfm` yang tidak ada).

- [ ] **Step 1: Tulis test yang gagal** — `tests/RfmServiceTest.php`:

```php
<?php
/**
 * tests/RfmServiceTest.php
 * Slice RFM: rekalkulasi, first-run, hasil, ringkasan segmen — DB test.
 * Segmentasi harus cocok dgn \App\Rfm::segmentFromScores() (single source of truth).
 */

use App\Rfm\RfmService;
use PHPUnit\Framework\TestCase;

class RfmServiceTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createBusiness(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO businesses (name, owner_name, email, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['RfmBiz ' . uniqid(), 'Owner', 'rfm' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    private function createCustomer(int $businessId, string $name): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO customers (business_id, customer_name, phone, email, created_at) VALUES (?, ?, '081', NULL, NOW())"
        );
        $stmt->execute([$businessId, $name]);
        return (int)$this->db->lastInsertId();
    }

    private function createTransaction(int $businessId, int $customerId, string $date, float $amount): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO transactions (business_id, customer_id, transaction_date, amount, product_name, quantity, created_at)
             VALUES (?, ?, ?, ?, NULL, 1, NOW())"
        );
        $stmt->execute([$businessId, $customerId, $date, $amount]);
    }

    public function testEnsureCalculatedOnlyOnFirstRun()
    {
        $biz = $this->createBusiness();
        $svc = new RfmService($this->db);
        $c1 = $this->createCustomer($biz, 'Andi');
        $this->createTransaction($biz, $c1, date('Y-m-d'), 600000);

        $this->assertTrue($svc->ensureCalculated($biz), 'first-run harus menghitung');
        $this->assertFalse($svc->ensureCalculated($biz), 'run kedua tidak boleh menghitung ulang');
        $this->assertSame(1, $svc->count($biz));
    }

    public function testRecalculateProducesSegmentConsistentWithPureLogic()
    {
        $biz = $this->createBusiness();
        $svc = new RfmService($this->db);

        // Champions: belanja baru + sering + besar
        $c1 = $this->createCustomer($biz, 'Andi');
        $this->createTransaction($biz, $c1, date('Y-m-d'), 600000);
        $this->createTransaction($biz, $c1, date('Y-m-d', strtotime('-2 days')), 600000);

        // At Risk: transaksi terakhir 400 hari lalu
        $c2 = $this->createCustomer($biz, 'Budi');
        $this->createTransaction($biz, $c2, date('Y-m-d', strtotime('-400 days')), 150000);

        $svc->recalculate($biz);

        $rows = $svc->results($biz);
        $this->assertCount(2, $rows);

        $byName = [];
        foreach ($rows as $r) {
            $byName[$r['name']] = $r;
        }
        $this->assertSame('Champions', $byName['Andi']['segment']);
        $this->assertSame('At Risk', $byName['Budi']['segment']);

        // konsistensi: skor tersimpan -> segmentFromScores() harus sama
        foreach ($rows as $r) {
            $expected = \App\Rfm::segmentFromScores((int)$r['recency_score'], (int)$r['frequency_score'], (int)$r['monetary_score']);
            $this->assertSame($expected, $r['segment']);
        }

        // ringkasan segmen
        $summary = $svc->segmentSummary($biz);
        $this->assertSame(1, (int)$summary['Champions']);
        $this->assertSame(1, (int)$summary['At Risk']);
    }

    public function testRecalculateIsScopedByBusiness()
    {
        $bizA = $this->createBusiness();
        $bizB = $this->createBusiness();
        $svc = new RfmService($this->db);

        $cA = $this->createCustomer($bizA, 'Andi');
        $this->createTransaction($bizA, $cA, date('Y-m-d'), 600000);
        $cB = $this->createCustomer($bizB, 'Sari');
        $this->createTransaction($bizB, $cB, date('Y-m-d'), 700000);

        $svc->recalculate($bizA);
        $this->assertSame(1, $svc->count($bizA));
        $this->assertSame(0, $svc->count($bizB), 'business lain tidak boleh terhitung');
    }
}
```

- [ ] **Step 2: Jalankan & pastikan gagal** — `vendor/bin/phpunit tests/RfmServiceTest.php` → FAIL.

- [ ] **Step 3: Implementasi minimal** — `src/App/Rfm/RfmService.php`:

```php
<?php
/**
 * src/App/Rfm/RfmService.php
 * Slice vertikal "RFM Analysis": rekalkulasi & pembacaan rfm_analysis.
 * Logika skor/segmentasi tetap di src/Rfm.php (single source of truth);
 * SQL di sini DIBANGUN dari \App\Rfm::*Sql() (sama seperti includes/rfm.php lama).
 */

namespace App\Rfm;

class RfmService
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function count(int $businessId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM rfm_analysis WHERE business_id = ?");
        $stmt->execute([$businessId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Hitung ulang RFM untuk satu business (DELETE + INSERT ulang).
     * Skor R/F/M dihitung sekali di subquery; segmentasi diturunkan dari skor.
     */
    public function recalculate(int $businessId, ?int $userId = null): void
    {
        $this->db->prepare("DELETE FROM rfm_analysis WHERE business_id = ?")->execute([$businessId]);

        $rExpr = \App\Rfm::recencyScoreSql('DATEDIFF(NOW(), MAX(t.transaction_date))');
        $fExpr = \App\Rfm::frequencyScoreSql('COUNT(t.id)');
        $mExpr = \App\Rfm::monetaryScoreSql('AVG(t.amount)');
        $segmentCase = \App\Rfm::segmentCaseSql('d.r', 'd.f', 'd.m');

        $query = "INSERT INTO rfm_analysis
            (business_id, customer_id, recency_score, frequency_score, monetary_score,
             rfm_segment, last_purchase_date, total_transactions, total_spent, analysis_date, created_at)
            SELECT d.business_id, d.customer_id, d.r, d.f, d.m,
            {$segmentCase},
            d.last_purchase_date, d.total_transactions, d.total_spent,
            CURDATE(), NOW()
            FROM (
                SELECT
                    c.business_id,
                    c.id AS customer_id,
                    {$rExpr} AS r,
                    {$fExpr} AS f,
                    {$mExpr} AS m,
                    MAX(t.transaction_date) AS last_purchase_date,
                    COUNT(t.id) AS total_transactions,
                    COALESCE(SUM(t.amount), 0) AS total_spent
                FROM customers c
                LEFT JOIN transactions t ON c.id = t.customer_id
                WHERE c.business_id = ?
                GROUP BY c.id, c.business_id
            ) d";

        $this->db->prepare($query)->execute([$businessId]);

        if ($userId !== null && function_exists('auth')) {
            auth()->logActivity($userId, 'rfm_calculation', 'RFM analysis calculated', $businessId);
        }
    }

    /** Rekalkulasi otomatis hanya saat belum ada data (first-run). @return bool true bila dihitung. */
    public function ensureCalculated(int $businessId, ?int $userId = null): bool
    {
        if ($this->count($businessId) > 0) {
            return false;
        }
        $this->recalculate($businessId, $userId);
        return true;
    }

    /** Baris analisis + data pelanggan (urutan skor terbaik dulu). */
    public function results(int $businessId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.customer_name as name,
                c.email,
                r.recency_score,
                r.frequency_score,
                r.monetary_score,
                r.rfm_segment as segment,
                r.total_transactions,
                r.total_spent,
                r.last_purchase_date as last_transaction
            FROM rfm_analysis r
            JOIN customers c ON r.customer_id = c.id
            WHERE r.business_id = ?
            ORDER BY r.recency_score DESC, r.frequency_score DESC, r.monetary_score DESC
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Ringkasan jumlah per segmen: [segment => count]. */
    public function segmentSummary(int $businessId): array
    {
        $stmt = $this->db->prepare(
            "SELECT rfm_segment as segment, COUNT(*) as count FROM rfm_analysis WHERE business_id = ? GROUP BY rfm_segment"
        );
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
```

- [ ] **Step 4: Jalankan & pastikan pass** — `vendor/bin/phpunit tests/RfmServiceTest.php` → PASS.

- [ ] **Step 5: Rampingkan `analysis.php` + hapus `includes/rfm.php`** — ganti blok PHP
      baris 13–73 (dari `<?php` kedua sampai `?>` sebelum `<!-- Mobile Menu Toggle -->`) dengan:

```php
    <?php
    require_once 'config/database.php';
    require_once 'config/auth.php';

    // Require UMKM owner access
    requireAuth(['umkm_owner']);

    $user = getCurrentUser();
    $db = getDB();

    // Get user's business
    $business = auth()->getUserBusiness($user['id']);
    if (!$business) {
        die('Error: No business associated with your account. Please contact administrator.');
    }

    $rfm = new \App\Rfm\RfmService($db);
    $rfmMessage = '';
    $rfmMessageType = '';

    // Rekalkulasi hanya saat diminta eksplisit (POST+CSRF) atau first-run (belum ada data)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'recalculate') {
        requireCsrf();
        $rfm->recalculate($business['id'], $_SESSION['user_id']);
        $rfmMessage = 'RFM berhasil dihitung ulang (' . date('d/m/Y H:i') . ').';
        $rfmMessageType = 'success';
    } elseif ($rfm->ensureCalculated($business['id'], $_SESSION['user_id'])) {
        $rfmMessage = 'RFM dihitung otomatis (belum ada data analisis).';
        $rfmMessageType = 'info';
    }

    $rfmResults = $rfm->results($business['id']);
    $segmentSummary = $rfm->segmentSummary($business['id']);
    ?>
```

Lalu `git rm includes/rfm.php`. Fungsi `getSegmentBadgeClass()` (view logic) TETAP di
`analysis.php`. HTML/JS tidak berubah.

- [ ] **Step 6: Lint & test penuh** — `php -l src/App/Rfm/RfmService.php`, `php -l analysis.php`,
      `grep -rn "includes/rfm\|recalculateRFM" --include="*.php" . | grep -v vendor` → kosong,
      `composer test` → hijau (termasuk RfmTest 125 kombinasi — tidak tersentuh).

- [ ] **Step 7: Commit** — `git add -A src/App/Rfm tests/RfmServiceTest.php analysis.php includes/rfm.php && git commit -m "refactor(rfm): pindah recalculateRFM ke App\\Rfm\\RfmService, hapus includes/rfm.php"`

---

### Task 5: Slice Import + Upload

**Files:**
- Create: `src/App/Upload/UploadValidator.php`
- Create: `src/App/Import/SpreadsheetImporter.php`
- Delete: `includes/import.php` (grep: hanya upload.php & api/upload-excel.php)
- Modify: `upload.php` (blok PHP baris 1–85 + script drag&drop TETAP), `api/upload-excel.php` (tulis ulang tipis)
- Test: `tests/UploadValidatorTest.php`, `tests/ImportTest.php`

**Interfaces:**
- Consumes: `\PDO`; PhpSpreadsheet (autoload).
- Produces: `UploadValidator::validate(array $file): array{ok,message,ext,mime}` (statis);
  `SpreadsheetImporter::__construct(\PDO)`, `->import(int $businessId, string $filePath, string $originalName): array{processed,failed,errors,message}`,
  `->history(int $businessId, int $limit): array`.

**Catatan:** `UploadValidator` menyatukan validasi yang selama ini diduplikasi di
`upload.php` (baris 21–83) dan `api/upload-excel.php` (baris 23–53). Pesan error disatukan
(perilaku = tetap tolak; teks disamakan).

- [ ] **Step 1: Tulis test yang gagal** — `tests/UploadValidatorTest.php` + `tests/ImportTest.php`:

```php
<?php
/**
 * tests/UploadValidatorTest.php
 * Validasi upload: error code, ukuran 5MB, ekstensi, MIME finfo.
 */

use App\Upload\UploadValidator;
use PHPUnit\Framework\TestCase;

class UploadValidatorTest extends TestCase
{
    public function testRejectsMissingFile()
    {
        $r = UploadValidator::validate(['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'name' => '', 'tmp_name' => '']);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('error code', $r['message']);
    }

    public function testRejectsOversize()
    {
        $r = UploadValidator::validate([
            'error' => UPLOAD_ERR_OK,
            'size' => 5 * 1024 * 1024 + 1,
            'name' => 'data.xlsx',
            'tmp_name' => __FILE__,
        ]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('5 MB', $r['message']);
    }

    public function testRejectsBadExtension()
    {
        $r = UploadValidator::validate([
            'error' => UPLOAD_ERR_OK,
            'size' => 100,
            'name' => 'data.php',
            'tmp_name' => __FILE__,
        ]);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Ekstensi', $r['message']);
    }

    public function testAcceptsCsvFile()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv_val_');
        file_put_contents($tmp, "nama,email,tanggal,nominal\nAndi,a@b.id,2026-08-01,100000\n");
        $r = UploadValidator::validate([
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmp),
            'name' => 'data.csv',
            'tmp_name' => $tmp,
        ]);
        unlink($tmp);
        $this->assertTrue($r['ok']);
        $this->assertSame('csv', $r['ext']);
        $this->assertSame('text/plain', $r['mime']); // finfo mendeteksi CSV polos sebagai text/plain (diizinkan)
    }
}
```

```php
<?php
/**
 * tests/ImportTest.php
 * Slice Import: impor CSV -> upsert customer per business + transaksi,
 * normalisasi tanggal/nominal, laporan per-baris — DB test.
 */

use App\Import\SpreadsheetImporter;
use PHPUnit\Framework\TestCase;

class ImportTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createBusiness(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO businesses (name, owner_name, email, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['ImportBiz ' . uniqid(), 'Owner', 'import' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    public function testImportCsvCreatesCustomersAndTransactions()
    {
        $biz = $this->createBusiness();
        $tmp = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($tmp, implode("\n", [
            "nama,email,hp,tanggal,nominal,produk,qty",
            "Andi Wijaya,andi@a.id,0811,01/08/2026,150000,Batik Kawung,2",
            "Sari Dewi,sari@a.id,0822,2026-08-05,\"1.500.000,50\",Batik Parang,1",
            "Andi Wijaya,andi@a.id,0811,10/08/2026,200000,Batik Megamendung,1",
        ]));

        $importer = new SpreadsheetImporter($this->db);
        $result = $importer->import($biz, $tmp, 'data.csv');
        unlink($tmp);

        $this->assertSame(3, $result['processed']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame([], $result['errors']);

        // 2 customer (Andi di-upsert, bukan duplikat), 3 transaksi
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM customers WHERE business_id = ?");
        $stmt->execute([$biz]);
        $this->assertSame(2, (int)$stmt->fetchColumn());

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM transactions WHERE business_id = ?");
        $stmt->execute([$biz]);
        $this->assertSame(3, (int)$stmt->fetchColumn());

        // normalisasi nominal Indonesia "1.500.000,50" -> 1500000.50
        $stmt = $this->db->prepare(
            "SELECT amount, quantity FROM transactions WHERE business_id = ? AND customer_id = (SELECT id FROM customers WHERE customer_name = 'Sari Dewi')"
        );
        $stmt->execute([$biz]);
        $sari = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta(1500000.50, (float)$sari['amount'], 0.001);
        $this->assertSame('1', $sari['quantity']);
    }

    public function testImportReportsPerRowFailures()
    {
        $biz = $this->createBusiness();
        $tmp = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($tmp, implode("\n", [
            "nama,tanggal,nominal",
            "Andi,2026-08-01,150000",
            ",2026-08-02,100000",          // nama kosong -> failed
            "Budi,invalid-date,100000",    // tanggal invalid -> failed
            "Sari,2026-08-03,abc",         // nominal invalid -> failed
        ]));

        $result = (new SpreadsheetImporter($this->db))->import($biz, $tmp, 'data.csv');
        unlink($tmp);

        $this->assertSame(1, $result['processed']);
        $this->assertSame(3, $result['failed']);
        $this->assertCount(3, $result['errors']);
        $this->assertStringContainsString('Baris 2', $result['errors'][0]);
    }

    public function testImportRejectsUnknownHeader()
    {
        $biz = $this->createBusiness();
        $tmp = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($tmp, "foo,bar\n1,2\n");

        $result = (new SpreadsheetImporter($this->db))->import($biz, $tmp, 'data.csv');
        unlink($tmp);

        $this->assertSame(0, $result['processed']);
        $this->assertStringContainsString('Nama Pelanggan', $result['message']);
    }
}
```

- [ ] **Step 2: Jalankan & pastikan gagal** — `vendor/bin/phpunit tests/UploadValidatorTest.php tests/ImportTest.php` → FAIL.

- [ ] **Step 3a: Implementasi `UploadValidator`** — `src/App/Upload/UploadValidator.php`:

```php
<?php
/**
 * src/App/Upload/UploadValidator.php
 * Validasi upload spreadsheet: error code, ukuran (5MB), ekstensi, MIME finfo.
 * Menyatukan validasi yang dulu diduplikasi di upload.php & api/upload-excel.php.
 */

namespace App\Upload;

class UploadValidator
{
    public const MAX_SIZE = 5 * 1024 * 1024;
    public const ALLOWED_EXT = ['xlsx', 'xls', 'csv'];

    private const ALLOWED_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel',                                          // .xls
        'application/octet-stream',                                          // beberapa .xls lama
        'text/csv',
        'text/plain',
    ];

    /**
     * @param array $file Elemen $_FILES['excel_file'].
     * @return array{ok: bool, message: string, ext: string, mime: string}
     */
    public static function validate(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [
                'ok' => false,
                'message' => 'Gagal mengunggah file (error code ' . (int)($file['error'] ?? 0) . ').',
                'ext' => '',
                'mime' => '',
            ];
        }
        if (($file['size'] ?? 0) > self::MAX_SIZE) {
            return [
                'ok' => false,
                'message' => 'Ukuran file melebihi batas maksimal 5 MB.',
                'ext' => '',
                'mime' => '',
            ];
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return [
                'ok' => false,
                'message' => 'Ekstensi file tidak diizinkan. Gunakan .xlsx, .xls, atau .csv.',
                'ext' => $ext,
                'mime' => '',
            ];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name'] ?? '') ?: '';
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return [
                'ok' => false,
                'message' => 'Tipe file tidak valid. Pastikan file yang diunggah benar-benar spreadsheet/CSV. (terdeteksi: ' . $mime . ')',
                'ext' => $ext,
                'mime' => $mime,
            ];
        }

        return ['ok' => true, 'message' => '', 'ext' => $ext, 'mime' => $mime];
    }
}
```

- [ ] **Step 3b: Implementasi `SpreadsheetImporter`** — `src/App/Import/SpreadsheetImporter.php`.
      Isi `import()` = transkripsi `importCustomerSpreadsheet()` dari `includes/import.php`
      baris 30–144 dengan substitusi: `$db` → `$this->db`, `_importReadCsv(...)` →
      `$this->readCsv(...)`, `_importMapColumns(...)` → `$this->mapColumns(...)`,
      `_importCell(...)` → `$this->cell(...)`, `_importNormalizeDate(...)` →
      `$this->normalizeDate(...)`, `_importNormalizeAmount(...)` → `$this->normalizeAmount(...)`,
      `_importUpsertCustomer(...)` → `$this->upsertCustomer(...)`, `_importLogStart(...)` →
      `$this->logStart(...)`, `_importLogFinish(...)` → `$this->logFinish(...)`.
      Helper dipindah verbatim menjadi method private dengan nama di atas (sumber:
      `includes/import.php` baris 146 `_importReadCsv`, 164 `_importMapColumns`,
      211 `_importCell`, 221 `_importNormalizeDate`, 249 `_importNormalizeAmount`,
      283 `_importUpsertCustomer`, 312 `_importLogStart`, 324 `_importLogFinish`).
      Tambahan public `history()`:

```php
    /** Riwayat upload terbaru (dipakai upload.php). */
    public function history(int $businessId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, filename, records_imported, status, error_message, created_at
             FROM upload_history WHERE business_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit
        );
        $stmt->execute([$businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
```

- [ ] **Step 4: Jalankan & pastikan pass** — `vendor/bin/phpunit tests/UploadValidatorTest.php tests/ImportTest.php` → PASS.

- [ ] **Step 5: Rampingkan `upload.php` + `api/upload-excel.php` + hapus `includes/import.php`**

  `upload.php` — ganti blok PHP baris 1–85 (dari `<?php` sampai `?>` sebelum `<!DOCTYPE html>`)
  dengan:

```php
<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Require UMKM owner access
requireAuth(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    die('Error: No business associated with your account. Please contact administrator.');
}

$importer = new \App\Import\SpreadsheetImporter($db);
$message = '';
$messageType = '';

// Handle Excel upload (validasi & impor di slice Import/Upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    requireCsrf();

    $validation = \App\Upload\UploadValidator::validate($_FILES['excel_file']);
    if (!$validation['ok']) {
        $message = $validation['message'];
        $messageType = 'danger';
    } else {
        // Simpan dengan nama acak ke folder terproteksi (bukan nama asli user)
        $uploadDir = __DIR__ . '/storage/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0770, true);
        }
        $newName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $validation['ext'];
        $uploadPath = $uploadDir . $newName;

        if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $uploadPath)) {
            $import = $importer->import($business['id'], $uploadPath, $_FILES['excel_file']['name']);
            $message = $import['message'];
            $messageType = ($import['processed'] > 0) ? 'success' : 'warning';
            if (!empty($import['errors'])) {
                $message .= ' ' . implode(' ', array_slice($import['errors'], 0, 5));
            }
        } else {
            $message = 'Gagal mengupload file.';
            $messageType = 'danger';
        }
    }
}

// Riwayat upload
$uploadHistory = $importer->history($business['id'], 10);
?>
```

Lalu di HTML, ganti blok `<tbody>` tabel "Riwayat Upload" (yang sekarang hardcoded
"Belum ada riwayat upload") dengan:

```html
                                <tbody>
                                    <?php if (empty($uploadHistory)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            Belum ada riwayat upload
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($uploadHistory as $h): ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                                            <td><?= htmlspecialchars($h['filename']) ?></td>
                                            <td>Pelanggan & Transaksi</td>
                                            <td>
                                                <span class="badge bg-<?= $h['status'] === 'completed' ? 'success' : ($h['status'] === 'processing' ? 'warning' : 'danger') ?>">
                                                    <?= htmlspecialchars($h['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= (int)$h['records_imported'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
```

Catatan: tabel riwayat selama ini kosong statis (data upload_history tidak pernah ditampilkan);
pengisian ini membuat riwayat benar-benar tampil — tetap dalam perilaku halaman (tidak ada
regresi, hanya menghilangkan placeholder). Form & script drag&drop TIDAK berubah.

  `api/upload-excel.php` — tulis ulang (tipis):

```php
<?php
require_once '../config/auth.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Autentikasi wajib: hanya UMKM owner berstatus login yang boleh mengakses
requireAuthJson(['umkm_owner']);
$user = getCurrentUser();
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No business associated with your account. Please contact administrator.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['excel_file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

// Validasi terpusat (ekstensi + MIME finfo + ukuran)
$validation = \App\Upload\UploadValidator::validate($_FILES['excel_file']);
if (!$validation['ok']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $validation['message']]);
    exit;
}

try {
    $import = (new \App\Import\SpreadsheetImporter(getDB()))
        ->import($business['id'], $_FILES['excel_file']['tmp_name'], $_FILES['excel_file']['name']);

    echo json_encode([
        'success'   => $import['processed'] > 0,
        'message'   => $import['message'],
        'processed' => $import['processed'],
        'failed'    => $import['failed'],
        'errors'    => $import['errors'],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal import: ' . $e->getMessage()]);
}
```

  Lalu `git rm includes/import.php`.

- [ ] **Step 6: Lint & test penuh** — `php -l src/App/Upload/UploadValidator.php`, `php -l src/App/Import/SpreadsheetImporter.php`, `php -l upload.php`, `php -l api/upload-excel.php`,
      `grep -rn "includes/import\|importCustomerSpreadsheet" --include="*.php" . | grep -v vendor` → kosong,
      `composer test` → hijau.

- [ ] **Step 7: Commit** — `git add -A src/App/Upload src/App/Import tests/UploadValidatorTest.php tests/ImportTest.php upload.php api/upload-excel.php includes/import.php && git commit -m "refactor(import): pindah impor ke App\\Import\\SpreadsheetImporter + validasi upload ke App\\Upload\\UploadValidator"`

---

### Task 6: Slice Export

**Files:**
- Create: `src/App/Export/CustomersExporter.php`, `src/App/Export/TransactionsExporter.php`
- Delete: `includes/export.php` (grep: api/export-*.php + tests/bootstrap.php)
- Modify: `api/export-customers.php`, `api/export-transactions.php` (tulis ulang tipis), `tests/bootstrap.php` (hapus require export.php), `tests/ExportTest.php` (tulis ulang ke class baru)
- Test: `tests/ExportTest.php` (tulis ulang — asersi yang sama, panggilan class baru)

**Interfaces:**
- Consumes: `CustomerRepository::withStats()`, `TransactionRepository::allWithCustomer()`, PhpSpreadsheet.
- Produces: `CustomersExporter::headers()/formatRow()/writeCsv()/buildSpreadsheet()` (statis, murni),
  `TransactionsExporter::headers()/formatRow()/writeCsv()/buildSpreadsheet()` (statis, murni).

- [ ] **Step 1: Tulis test yang gagal** — tulis ulang `tests/ExportTest.php`. Asersi IDENTIK
      dengan versi lama (BOM UTF-8, header, format `d/m/Y`, fallback `'-'`, total `amount*qty`,
      round-trip XLSX) — hanya panggilannya diganti:

```php
<?php
/**
 * tests/ExportTest.php
 * Slice Export: format baris, CSV (BOM UTF-8 + header + data), round-trip XLSX.
 * Format dikunci di sini (AGENTS.md §8 Export): jangan ubah tanpa update test ini.
 */

use App\Export\CustomersExporter;
use App\Export\TransactionsExporter;
use PHPUnit\Framework\TestCase;

class ExportTest extends TestCase
{
    private function sampleCustomer($overrides = [])
    {
        return array_merge([
            'customer_name' => 'Andi Wijaya',
            'phone' => '08111111111',
            'email' => 'andi@email.com',
            'total_transactions' => '3',
            'total_spent' => '450000',
            'last_transaction' => '2026-08-01',
            'created_at' => '2026-01-15 10:00:00',
        ], $overrides);
    }

    private function sampleTransaction($overrides = [])
    {
        return array_merge([
            'customer_name' => 'Sari Dewi',
            'phone' => '08222222222',
            'product_name' => 'Batik Kawung',
            'quantity' => '2',
            'amount' => '150000',
            'transaction_date' => '2026-08-05',
        ], $overrides);
    }

    // ---- format baris ----

    public function testFormatCustomerRow()
    {
        $row = CustomersExporter::formatRow(0, $this->sampleCustomer());
        $this->assertSame([1, 'Andi Wijaya', '08111111111', 'andi@email.com', '3', '450000', '01/08/2026', '15/01/2026'], $row);

        $row = CustomersExporter::formatRow(4, $this->sampleCustomer(['email' => '', 'last_transaction' => null]));
        $this->assertSame(5, $row[0]);
        $this->assertSame('-', $row[3]);
        $this->assertSame('-', $row[6]);
    }

    public function testFormatTransactionRow()
    {
        $row = TransactionsExporter::formatRow(0, $this->sampleTransaction());
        $this->assertSame([1, '05/08/2026', 'Sari Dewi', '08222222222', 'Batik Kawung', '2', '150000', 300000], $row);

        $row = TransactionsExporter::formatRow(0, $this->sampleTransaction(['product_name' => null]));
        $this->assertSame('-', $row[4]);
    }

    // ---- CSV ----

    public function testCustomersCsvHasBomHeadersAndRows()
    {
        $file = tempnam(sys_get_temp_dir(), 'csv_cust_');
        CustomersExporter::writeCsv([$this->sampleCustomer()], $file);

        $raw = file_get_contents($file);
        $this->assertStringStartsWith(chr(0xEF) . chr(0xBB) . chr(0xBF), $raw, 'CSV harus berawalan BOM UTF-8');

        $lines = explode("\n", trim($raw));
        $header = str_getcsv(substr($lines[0], 3)); // buang BOM
        $this->assertSame(CustomersExporter::headers(), $header);

        $data = str_getcsv($lines[1]);
        $this->assertSame('1', $data[0]);
        $this->assertSame('Andi Wijaya', $data[1]);
        $this->assertSame('01/08/2026', $data[6]);
        unlink($file);
    }

    public function testTransactionsCsvHasBomHeadersAndRows()
    {
        $file = tempnam(sys_get_temp_dir(), 'csv_tx_');
        TransactionsExporter::writeCsv([$this->sampleTransaction()], $file);

        $raw = file_get_contents($file);
        $this->assertStringStartsWith(chr(0xEF) . chr(0xBB) . chr(0xBF), $raw);

        $lines = explode("\n", trim($raw));
        $header = str_getcsv(substr($lines[0], 3));
        $this->assertSame(TransactionsExporter::headers(), $header);

        $data = str_getcsv($lines[1]);
        $this->assertSame('Batik Kawung', $data[4]);
        $this->assertSame('300000', $data[7]); // total
        unlink($file);
    }

    // ---- XLSX round-trip ----

    public function testCustomersSpreadsheetRoundTrip()
    {
        $spreadsheet = CustomersExporter::buildSpreadsheet('Batik Semarang', [$this->sampleCustomer()]);
        $this->assertInstanceOf(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, $spreadsheet);
        $this->assertSame('Data Pelanggan - Batik Semarang', $spreadsheet->getProperties()->getTitle());

        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('Nama Pelanggan', $sheet->getCell('B1')->getValue());
        $this->assertSame('Total Belanja (Rp)', $sheet->getCell('F1')->getValue());
        $this->assertSame(1, $sheet->getCell('A2')->getValue());
        $this->assertSame('Andi Wijaya', $sheet->getCell('B2')->getValue());
        $this->assertEquals(450000, $sheet->getCell('F2')->getValue());
        $this->assertSame('01/08/2026', $sheet->getCell('G2')->getValue());
    }

    public function testTransactionsSpreadsheetRoundTrip()
    {
        $spreadsheet = TransactionsExporter::buildSpreadsheet('Batik Semarang', [$this->sampleTransaction()]);
        $this->assertSame('Data Transaksi - Batik Semarang', $spreadsheet->getProperties()->getTitle());

        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('Nama Produk', $sheet->getCell('E1')->getValue());
        $this->assertSame('Sari Dewi', $sheet->getCell('C2')->getValue());
        $this->assertEquals(150000, $sheet->getCell('G2')->getValue());
        $this->assertSame(300000, $sheet->getCell('H2')->getValue());
    }

    public function testSpreadsheetCanBeWrittenAsXlsx()
    {
        $spreadsheet = CustomersExporter::buildSpreadsheet('Batik Semarang', [$this->sampleCustomer()]);
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tmp);
        $this->assertFileExists($tmp);
        $this->assertGreaterThan(1000, filesize($tmp), 'File XLSX tidak boleh kosong');

        $loaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        $this->assertSame('Andi Wijaya', $loaded->getActiveSheet()->getCell('B2')->getValue());
        unlink($tmp);
    }
}
```

- [ ] **Step 2: Jalankan & pastikan gagal** — `vendor/bin/phpunit tests/ExportTest.php` → FAIL (class belum ada).

- [ ] **Step 3: Implementasi** — `src/App/Export/CustomersExporter.php` (isi = pemindahan
      `exportCustomersHeaders()`, `formatCustomerExportRow()`, `writeCustomersCsv()`,
      `buildCustomersSpreadsheet()` dari `includes/export.php` baris 13–119 verbatim menjadi
      static method; `styleExportHeaderCell()` menjadi `private static`, dipakai kedua exporter —
      taruh di `CustomersExporter` dan dipanggil `CustomersExporter::styleHeaderCell()` dari
      `TransactionsExporter`, atau duplikasi 18 baris kecil di masing-masing — pilih duplikasi
      agar tidak ada dependensi antar-exporter; format harus tetap sama persis):

```php
<?php
/**
 * src/App/Export/CustomersExporter.php
 * Slice Export (customers): header, format baris, CSV (BOM UTF-8), XLSX.
 * Format DIKUNCI oleh tests/ExportTest.php (AGENTS.md §8 Export).
 * Data diambil via CustomerRepository::withStats() di API.
 */

namespace App\Export;

class CustomersExporter
{
    public static function headers(): array
    {
        return [
            'No',
            'Nama Pelanggan',
            'No HP',
            'Email',
            'Total Transaksi',
            'Total Belanja (Rp)',
            'Transaksi Terakhir',
            'Tanggal Registrasi',
        ];
    }

    public static function formatRow($index, $customer)
    {
        return [
            $index + 1,
            $customer['customer_name'],
            $customer['phone'],
            $customer['email'] ?: '-',
            $customer['total_transactions'],
            $customer['total_spent'],
            $customer['last_transaction'] ? date('d/m/Y', strtotime($customer['last_transaction'])) : '-',
            date('d/m/Y', strtotime($customer['created_at'])),
        ];
    }

    public static function writeCsv(array $customers, $target = 'php://output'): void
    {
        $output = is_resource($target) ? $target : fopen($target, 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, self::headers());
        foreach ($customers as $index => $customer) {
            fputcsv($output, self::formatRow($index, $customer));
        }
        if (!is_resource($target)) {
            fclose($output);
        }
    }

    public static function buildSpreadsheet($businessName, array $customers)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $spreadsheet->getProperties()
            ->setCreator('Smart Marketing Agent')
            ->setLastModifiedBy('Smart Marketing Agent')
            ->setTitle('Data Pelanggan - ' . $businessName)
            ->setSubject('Data Pelanggan')
            ->setDescription('Data pelanggan yang diekspor dari Smart Marketing Agent')
            ->setKeywords('pelanggan, customer, data')
            ->setCategory('Data Export');

        $headers = self::headers();
        foreach ($headers as $colIndex => $header) {
            self::styleHeaderCell($sheet, chr(65 + $colIndex), $header);
        }

        foreach ($customers as $index => $customer) {
            $row = $index + 2;
            $values = self::formatRow($index, $customer);
            foreach ($values as $colIndex => $value) {
                $sheet->setCellValue(chr(65 + $colIndex) . $row, $value);
            }
        }

        $lastRow = count($customers) + 1;
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('A1:H' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
        $sheet->getStyle('A1:A' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E1:E' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F1:F' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('F2:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0');

        return $spreadsheet;
    }

    private static function styleHeaderCell($sheet, $column, $header)
    {
        $sheet->setCellValue($column . '1', $header);
        $sheet->getStyle($column . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '007BFF'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);
    }
}
```

  `src/App/Export/TransactionsExporter.php` — sama persis polanya, memindahkan
  `exportTransactionsHeaders()`, `formatTransactionExportRow()`, `writeTransactionsCsv()`,
  `buildTransactionsSpreadsheet()` dari `includes/export.php` baris 121–228 verbatim
  (header `No, Tanggal Transaksi, Nama Pelanggan, No HP, Nama Produk, Jumlah, Harga Satuan (Rp),
  Total (Rp)`; baris: tanggal `d/m/Y`, `product_name ?: '-'`, total `amount*quantity`; kolom
  G:H number format `#,##0`), plus `private static styleHeaderCell()` yang sama.

- [ ] **Step 4: Jalankan & pastikan pass** — `vendor/bin/phpunit tests/ExportTest.php` → PASS.

- [ ] **Step 5: Rampingkan API + bootstrap + hapus `includes/export.php`**

  `tests/bootstrap.php` — hapus dua baris terakhir (`// Memuat definisi AuthManager ...` dan
  `require_once dirname(__DIR__) . '/includes/export.php';`); autoload composer sudah mencakup
  `App\*`.

  `api/export-customers.php` — tulis ulang (tipis):

```php
<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../vendor/autoload.php';

// Require UMKM owner access
requireAuthJson(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No business associated with your account. Please contact administrator.']);
    exit;
}

// Data dari slice Customers
try {
    $customers = (new \App\Customers\CustomerRepository($db))->withStats($business['id']);
} catch (\PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error loading customers: ' . $e->getMessage()]);
    exit;
}

// Fallback CSV bila PhpSpreadsheet tidak tersedia
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    $filename = 'customers_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    \App\Export\CustomersExporter::writeCsv($customers);
    exit;
}

try {
    $spreadsheet = \App\Export\CustomersExporter::buildSpreadsheet($business['name'], $customers);

    $filename = 'customers_' . $business['name'] . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error creating Excel file: ' . $e->getMessage()]);
    exit;
}
```

Catatan: `$business['business_name']` (kolom tidak ada) diganti `$business['name']` — lihat
temuan #3 di §2 (bug kecil ikut teratasi).

  `api/export-transactions.php` — tulis ulang (tipis, pola sama):

```php
<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../vendor/autoload.php';

// Require UMKM owner access
requireAuthJson(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No business associated with your account. Please contact administrator.']);
    exit;
}

// Data dari slice Transactions
try {
    $transactions = (new \App\Transactions\TransactionRepository($db))->allWithCustomer($business['id']);
} catch (\PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error loading transactions: ' . $e->getMessage()]);
    exit;
}

// Fallback CSV bila PhpSpreadsheet tidak tersedia
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    $filename = 'transactions_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    \App\Export\TransactionsExporter::writeCsv($transactions);
    exit;
}

try {
    $spreadsheet = \App\Export\TransactionsExporter::buildSpreadsheet($business['name'], $transactions);

    $filename = 'transactions_' . $business['name'] . '_' . date('Y-m-d_H-i-s') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error creating Excel file: ' . $e->getMessage()]);
    exit;
}
```

  Lalu `git rm includes/export.php`.

- [ ] **Step 6: Lint & test penuh** — `php -l src/App/Export/*.php`, `php -l api/export-*.php`,
      `php -l tests/bootstrap.php`, `grep -rn "includes/export\|formatCustomerExportRow\|writeCustomersCsv\|buildCustomersSpreadsheet" --include="*.php" . | grep -v vendor` → kosong,
      `composer test` → hijau.

- [ ] **Step 7: Commit** — `git add -A src/App/Export tests/ExportTest.php tests/bootstrap.php api/export-customers.php api/export-transactions.php includes/export.php && git commit -m "refactor(export): pindah helper export ke App\\Export\\CustomersExporter & TransactionsExporter, API tipis"`

---

### Task 7: Slice AI Content

**Files:**
- Create: `src/App/Ai/ContentGenerator.php`
- Modify: `ai-content.php` (blok PHP baris 1–65 diganti; HTML/JS tidak berubah), `api/generate-content.php` (tulis ulang tipis)
- Test: `tests/ContentGeneratorTest.php`

**Interfaces:**
- Consumes: `\PDO`, `\OpenAIClient` (config/openai.php) — di-inject opsional agar bisa di-mock.
- Produces: `ContentGenerator::__construct(\PDO, int $businessId)`,
  `->generate(string $segment, ?\OpenAIClient $client = null): array{success,content,source,note,error}`,
  `->recent(int $limit = 5): array`, `static dummyContent(string $segment): string`.

- [ ] **Step 1: Tulis test yang gagal** — `tests/ContentGeneratorTest.php`:

```php
<?php
/**
 * tests/ContentGeneratorTest.php
 * Slice AI Content: fallback dummy (tanpa panggilan OpenAI sungguhan),
 * persist ke ai_generated_content, dan riwayat — DB test.
 * OpenAIClient di-mock via injeksi parameter (tanpa network).
 */

use App\Ai\ContentGenerator;
use PHPUnit\Framework\TestCase;

class ContentGeneratorTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createBusiness(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO businesses (name, owner_name, email, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['AiBiz ' . uniqid(), 'Owner', 'ai' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    public function testDummyContentForAllKnownSegments()
    {
        foreach (['Champions', 'Loyal Customers', 'Potential Loyalists', 'At Risk', 'Lost Customers', 'Unknown'] as $segment) {
            $content = ContentGenerator::dummyContent($segment);
            $this->assertNotEmpty($content, "segmen '$segment' harus menghasilkan konten");
        }
        $this->assertStringContainsString('Champions', ContentGenerator::dummyContent('Champions'));
    }

    public function testGenerateFallsBackToDummyWhenOpenAiFails()
    {
        $biz = $this->createBusiness();
        $mock = $this->createMock(\OpenAIClient::class);
        $mock->method('generateMarketingContent')->willThrowException(new \Exception('API down'));

        $result = (new ContentGenerator($this->db, $biz))->generate('Champions', $mock);

        $this->assertTrue($result['success']);
        $this->assertSame('dummy', $result['source']);
        $this->assertSame('Generated using fallback content (OpenAI API not available)', $result['note']);
        $this->assertNotEmpty($result['content']);

        // hasil dummy tersimpan ke DB
        $stmt = $this->db->prepare("SELECT segment, content FROM ai_generated_content WHERE business_id = ?");
        $stmt->execute([$biz]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('Champions', $row['segment']);
        $this->assertSame($result['content'], $row['content']);
    }

    public function testGenerateUsesOpenAiContentWhenAvailable()
    {
        $biz = $this->createBusiness();
        $mock = $this->createMock(\OpenAIClient::class);
        $mock->method('generateMarketingContent')->willReturn(['content' => 'Konten dari OpenAI']);

        $result = (new ContentGenerator($this->db, $biz))->generate('At Risk', $mock);

        $this->assertTrue($result['success']);
        $this->assertSame('openai', $result['source']);
        $this->assertSame('Konten dari OpenAI', $result['content']);
        $this->assertNull($result['note']);
    }

    public function testRecentListsLatestFirst()
    {
        $biz = $this->createBusiness();
        $gen = new ContentGenerator($this->db, $biz);
        $gen->generate('Champions', $this->failingClient());
        $gen->generate('At Risk', $this->failingClient());

        $recent = $gen->recent(5);
        $this->assertCount(2, $recent);
        $this->assertSame('At Risk', $recent[0]['segment']); // terbaru duluan
    }

    private function failingClient()
    {
        $mock = $this->createMock(\OpenAIClient::class);
        $mock->method('generateMarketingContent')->willThrowException(new \Exception('down'));
        return $mock;
    }
}
```

- [ ] **Step 2: Jalankan & pastikan gagal** — `vendor/bin/phpunit tests/ContentGeneratorTest.php` → FAIL.

- [ ] **Step 3: Implementasi** — `src/App/Ai/ContentGenerator.php`:

```php
<?php
/**
 * src/App/Ai/ContentGenerator.php
 * Slice vertikal "AI Content": generate konten marketing per segmen + persist.
 * Mencoba OpenAIClient dulu; fallback dummy bila gagal/tidak dikonfigurasi.
 * OpenAIClient di-inject opsional agar bisa di-mock di test (tanpa network).
 */

namespace App\Ai;

class ContentGenerator
{
    /** @var \PDO */
    private $db;
    /** @var int */
    private $businessId;

    public function __construct(\PDO $db, int $businessId)
    {
        $this->db = $db;
        $this->businessId = $businessId;
    }

    /**
     * Generate konten marketing untuk satu segmen & simpan ke ai_generated_content.
     *
     * @param \OpenAIClient|null $client Mock/instance OpenAI (default: buat sendiri).
     * @return array{success: bool, content: string, source: string, note: ?string, error: ?string}
     */
    public function generate(string $segment, ?\OpenAIClient $client = null): array
    {
        try {
            $client = $client ?? new \OpenAIClient();
            $content = $client->generateMarketingContent($segment)['content'] ?? '';
            $this->persist($segment, $content);
            return ['success' => true, 'content' => $content, 'source' => 'openai', 'note' => null, 'error' => null];
        } catch (\Exception $e) {
            $dummy = self::dummyContent($segment);
            $this->persist($segment, $dummy);
            return [
                'success' => true,
                'content' => $dummy,
                'source' => 'dummy',
                'note' => 'Generated using fallback content (OpenAI API not available)',
                'error' => null,
            ];
        }
    }

    /** Riwayat konten terbaru untuk business ini. */
    public function recent(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT segment, content, created_at FROM ai_generated_content
             WHERE business_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit
        );
        $stmt->execute([$this->businessId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function persist(string $segment, string $content): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_generated_content (business_id, segment, content, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$this->businessId, $segment, $content]);
    }

    /**
     * Konten dummy per segmen (fallback offline).
     * Isi = generateDummyContent() di api/generate-content.php (dipindah verbatim).
     */
    public static function dummyContent(string $segment): string
    {
        $businessName = 'Batik Semarang Jaya';

        switch ($segment) {
            case 'Champions':
                return "🏆 STRATEGI MARKETING UNTUK CHAMPIONS\n\n" .
                       "Halo Pelanggan VIP {$businessName}!\n\n" .
                       "Sebagai customer terbaik kami, Anda berhak mendapatkan:\n" .
                       "✨ EXCLUSIVE PREVIEW koleksi batik terbaru\n" .
                       "🎁 GRATIS ongkir selamanya\n" .
                       "💎 Diskon VIP 25% untuk semua produk\n" .
                       "👥 Undang 3 teman, dapatkan voucher Rp 500.000\n\n" .
                       "Program Loyalitas Premium:\n" .
                       "- Akses early bird sale\n" .
                       "- Personal stylist consultation\n" .
                       "- Birthday special discount 50%\n\n" .
                       "Terima kasih telah mempercayai {$businessName} sebagai pilihan utama fashion batik Anda!";
            case 'Loyal Customers':
                return "💙 APRESIASI UNTUK LOYAL CUSTOMERS\n\n" .
                       "Dear Pelanggan Setia {$businessName},\n\n" .
                       "Kesetiaan Anda sangat berarti bagi kami!\n\n" .
                       "Reward Loyalitas Bulan Ini:\n" .
                       "🛍️ Cashback 15% untuk pembelian berikutnya\n" .
                       "📦 Free upgrade ke packaging premium\n" .
                       "⭐ Priority customer service\n" .
                       "🎊 Surprise gift setiap 5 pembelian\n\n" .
                       "Rekomendasi Special:\n" .
                       "- Koleksi batik eksklusif limited edition\n" .
                       "- Bundle package hemat 3 pcs\n" .
                       "- Pre-order koleksi season mendatang\n\n" .
                       "Mari lanjutkan perjalanan fashion batik bersama {$businessName}!";
            case 'Potential Loyalists':
                return "🌟 UNDANGAN KHUSUS POTENTIAL LOYALISTS\n\n" .
                       "Halo Fashion Enthusiast!\n\n" .
                       "Kami melihat Anda memiliki taste yang luar biasa dalam memilih batik berkualitas tinggi.\n\n" .
                       "Special Offer untuk Anda:\n" .
                       "🎯 Diskon 20% untuk pembelian kedua\n" .
                       "💝 Bonus aksesori batik eksklusif\n" .
                       "📱 Join VIP WhatsApp group untuk update terbaru\n" .
                       "🚚 Gratis ongkir untuk 3 pembelian berikutnya\n\n" .
                       "Koleksi Rekomendasi:\n" .
                       "- Batik premium collection\n" .
                       "- Couple set untuk acara special\n" .
                       "- Batik formal untuk profesional\n\n" .
                       "Jadilah bagian dari keluarga besar {$businessName}!";
            case 'At Risk':
                return "⚠️ WE MISS YOU - COMEBACK CAMPAIGN\n\n" .
                       "Halo Sahabat {$businessName},\n\n" .
                       "Sudah lama tidak berjumpa... Kami merindukan Anda! 💔\n\n" .
                       "WELCOME BACK SPECIAL:\n" .
                       "🔥 MEGA DISKON 30% untuk comeback Anda\n" .
                       "🎁 Mystery gift di setiap pembelian\n" .
                       "💳 Cicilan 0% untuk pembelian minimal Rp 500.000\n" .
                       "📞 Personal assistance untuk rekomendasi produk\n\n" .
                       "Yang Baru di {$businessName}:\n" .
                       "- Koleksi batik modern casual\n" .
                       "- Technology fabric anti-wrinkle\n" .
                       "- Custom design service\n\n" .
                       "Jangan lewatkan kesempatan comeback ini! Valid hingga akhir bulan.";
            case 'Lost Customers':
                return "💸 WIN-BACK SUPER CAMPAIGN\n\n" .
                       "Dear Valued Customer,\n\n" .
                       "Kami ingin meminta maaf jika ada yang kurang berkenan di masa lalu.\n\n" .
                       "FORGIVE US MEGA SALE:\n" .
                       "🚨 DISKON GILA 50% semua produk\n" .
                       "🎊 Buy 1 Get 1 untuk kategori tertentu\n" .
                       "🆓 Gratis custom design untuk 10 pembeli pertama\n" .
                       "💌 Personal apology letter dari owner\n\n" .
                       "Pembaruan {$businessName}:\n" .
                       "- Kualitas bahan premium upgrade\n" .
                       "- Layanan customer service 24/7\n" .
                       "- Garansi kepuasan 100%\n" .
                       "- Easy return policy\n\n" .
                       "Berikan kami kesempatan kedua untuk melayani Anda lebih baik!";
            default:
                return "📢 MARKETING CONTENT\n\n" .
                       "Halo Customer {$businessName}!\n\n" .
                       "Terima kasih telah menjadi bagian dari perjalanan kami.\n" .
                       "Dapatkan update terbaru dan penawaran menarik hanya untuk Anda!\n\n" .
                       "Hubungi kami:\n" .
                       "📱 WhatsApp: 08123456789\n" .
                       "📧 Email: info@batiksemarang.com\n" .
                       "🏪 Alamat: Jl. Pandanaran 123, Semarang";
        }
    }
}
```

- [ ] **Step 4: Jalankan & pastikan pass** — `vendor/bin/phpunit tests/ContentGeneratorTest.php` → PASS.

- [ ] **Step 5: Rampingkan `ai-content.php` + `api/generate-content.php`**

  `ai-content.php` — ganti blok PHP baris 1–65 (dari `<?php` sampai `?>` sebelum
  `<!DOCTYPE html>`) dengan:

```php
<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Require UMKM owner access
requireAuth(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    die('Error: Tidak ada bisnis yang terkait dengan akun Anda. Silakan hubungi administrator.');
}

$generator = new \App\Ai\ContentGenerator($db, $business['id']);

$generated_content = '';
$selected_segment = '';
$error_message = '';

// Handle form submission: panggil service langsung (tanpa HTTP internal ke API)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['segment'])) {
    requireCsrf();
    $selected_segment = $_POST['segment'];
    $result = $generator->generate($selected_segment);
    if ($result['success']) {
        $generated_content = nl2br(htmlspecialchars($result['content']));
        if ($result['note'] !== null) {
            $error_message = htmlspecialchars($result['note']);
        }
    } else {
        $error_message = htmlspecialchars($result['error'] ?? 'Gagal menghasilkan konten');
    }
}

// Riwayat konten terbaru
$recent_content = $generator->recent(5);
?>
```

  `api/generate-content.php` — tulis ulang (tipis; fungsi `generateDummyContent()` dipindah
  ke `ContentGenerator::dummyContent()`):

```php
<?php
require_once '../config/auth.php';
require_once '../config/database.php';
require_once '../config/openai.php';

header('Content-Type: application/json');

// Autentikasi wajib: hanya UMKM owner berstatus login yang boleh mengakses
requireAuthJson(['umkm_owner']);
$user = getCurrentUser();
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No business associated with your account. Please contact administrator.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$segment = $input['segment'] ?? '';
if ($segment === '') {
    echo json_encode(['success' => false, 'error' => 'Segment is required']);
    exit;
}

$generator = new \App\Ai\ContentGenerator(getDB(), $business['id']);
$result = $generator->generate($segment);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

$response = [
    'success' => true,
    'content' => nl2br(htmlspecialchars($result['content'])),
    'source' => $result['source'],
];
if ($result['note'] !== null) {
    $response['note'] = $result['note'];
}
echo json_encode($response);
```

  Catatan: perilaku API identik (success + content escaped + source; note hanya saat dummy);
  hanya `generateDummyContent()` tidak lagi di api file.

- [ ] **Step 6: Lint & test penuh** — `php -l src/App/Ai/ContentGenerator.php`, `php -l ai-content.php`,
      `php -l api/generate-content.php`, `grep -rn "generateDummyContent" --include="*.php" . | grep -v vendor` → kosong,
      `composer test` → hijau.

- [ ] **Step 7: Commit** — `git add -A src/App/Ai tests/ContentGeneratorTest.php ai-content.php api/generate-content.php && git commit -m "refactor(ai): pindah logika AI ke App\\Ai\\ContentGenerator, hapus HTTP internal ai-content.php"`

---

### Task 8: Slice Profil Bisnis

**Files:**
- Create: `src/App/Business/BusinessProfileService.php`
- Modify: `profile.php` (blok PHP baris 1–81 diganti; HTML tidak berubah)
- Test: `tests/BusinessProfileServiceTest.php`

**Interfaces:**
- Consumes: `\PDO`.
- Produces: `BusinessProfileService::__construct(\PDO)`,
  `->update(int $businessId, array $data): array{ok: bool, message: string}`,
  `->get(int $businessId): ?array`, `static businessTypes(): array`.

- [ ] **Step 1: Tulis test yang gagal** — `tests/BusinessProfileServiceTest.php`:

```php
<?php
/**
 * tests/BusinessProfileServiceTest.php
 * Slice Profil Bisnis: validasi (wajib + format email + unik), update — DB test.
 */

use App\Business\BusinessProfileService;
use PHPUnit\Framework\TestCase;

class BusinessProfileServiceTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createBusiness(string $email): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO businesses (name, owner_name, email, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['ProfileBiz', 'Owner', $email]);
        return (int)$this->db->lastInsertId();
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Batik Semarang Jaya',
            'owner_name' => 'Budi Santoso',
            'email' => 'budi@batiksemarang.com',
            'phone' => '08123456789',
            'address' => 'Jl. Pandanaran 123',
            'business_type' => 'Fashion/Pakaian',
        ], $overrides);
    }

    public function testUpdateValidatesRequiredFields()
    {
        $biz = $this->createBusiness('a1@test.local');
        $svc = new BusinessProfileService($this->db);

        $r = $svc->update($biz, $this->validData(['name' => '  ']));
        $this->assertFalse($r['ok']);
        $this->assertSame('Nama bisnis wajib diisi', $r['message']);

        $r = $svc->update($biz, $this->validData(['owner_name' => '']));
        $this->assertFalse($r['ok']);
        $this->assertSame('Nama pemilik wajib diisi', $r['message']);

        $r = $svc->update($biz, $this->validData(['email' => 'bukan-email']));
        $this->assertFalse($r['ok']);
        $this->assertSame('Format email tidak valid', $r['message']);
    }

    public function testUpdateRejectsEmailUsedByOtherBusiness()
    {
        $bizA = $this->createBusiness('taken@test.local');
        $bizB = $this->createBusiness('other@test.local');
        $svc = new BusinessProfileService($this->db);

        $r = $svc->update($bizB, $this->validData(['email' => 'taken@test.local']));
        $this->assertFalse($r['ok']);
        $this->assertSame('Email sudah digunakan oleh bisnis lain', $r['message']);

        // email sendiri tetap boleh (tidak berubah)
        $r = $svc->update($bizA, $this->validData(['email' => 'taken@test.local']));
        $this->assertTrue($r['ok']);
    }

    public function testUpdatePersistsAndRefreshes()
    {
        $biz = $this->createBusiness('old@test.local');
        $svc = new BusinessProfileService($this->db);

        $r = $svc->update($biz, $this->validData(['phone' => '08999999999']));
        $this->assertTrue($r['ok']);
        $this->assertSame('Profil bisnis berhasil diperbarui', $r['message']);

        $row = $svc->get($biz);
        $this->assertSame('Batik Semarang Jaya', $row['name']);
        $this->assertSame('08999999999', $row['phone']);
        $this->assertSame('Fashion/Pakaian', $row['business_type']);
    }

    public function testBusinessTypesList()
    {
        $types = BusinessProfileService::businessTypes();
        $this->assertContains('Retail/Eceran', $types);
        $this->assertContains('Lainnya', $types);
    }
}
```

- [ ] **Step 2: Jalankan & pastikan gagal** — `vendor/bin/phpunit tests/BusinessProfileServiceTest.php` → FAIL.

- [ ] **Step 3: Implementasi** — `src/App/Business/BusinessProfileService.php`:

```php
<?php
/**
 * src/App/Business/BusinessProfileService.php
 * Slice vertikal "Profil Bisnis": validasi + update data bisnis UMKM.
 * Dipakai oleh profile.php (email unik dicek lintas bisnis, kecuali diri sendiri).
 */

namespace App\Business;

class BusinessProfileService
{
    /** @var \PDO */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Validasi + update profil bisnis.
     * @param int   $businessId business pemilik (dari session, bukan input user).
     * @param array $data       name, owner_name, email, phone, address, business_type.
     * @return array{ok: bool, message: string}
     */
    public function update(int $businessId, array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $ownerName = trim((string)($data['owner_name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $address = trim((string)($data['address'] ?? ''));
        $businessType = trim((string)($data['business_type'] ?? ''));

        if ($name === '') {
            return ['ok' => false, 'message' => 'Nama bisnis wajib diisi'];
        }
        if ($ownerName === '') {
            return ['ok' => false, 'message' => 'Nama pemilik wajib diisi'];
        }
        if ($email === '') {
            return ['ok' => false, 'message' => 'Email wajib diisi'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Format email tidak valid'];
        }

        // Email unik untuk bisnis LAIN (boleh sama dengan email sendiri)
        $stmt = $this->db->prepare("SELECT id FROM businesses WHERE email = ? AND id != ?");
        $stmt->execute([$email, $businessId]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'Email sudah digunakan oleh bisnis lain'];
        }

        $stmt = $this->db->prepare(
            "UPDATE businesses
             SET name = ?, owner_name = ?, email = ?, phone = ?, address = ?, business_type = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$name, $ownerName, $email, $phone, $address, $businessType, $businessId]);

        return ['ok' => true, 'message' => 'Profil bisnis berhasil diperbarui'];
    }

    /** Ambil satu business (untuk refresh setelah update). */
    public function get(int $businessId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM businesses WHERE id = ?");
        $stmt->execute([$businessId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Daftar jenis bisnis untuk dropdown. */
    public static function businessTypes(): array
    {
        return [
            'Retail/Eceran',
            'F&B/Kuliner',
            'Fashion/Pakaian',
            'Kecantikan/Kosmetik',
            'Elektronik',
            'Otomotif',
            'Kesehatan',
            'Pendidikan',
            'Jasa',
            'Teknologi',
            'Pertanian',
            'Lainnya',
        ];
    }
}
```

- [ ] **Step 4: Jalankan & pastikan pass** — `vendor/bin/phpunit tests/BusinessProfileServiceTest.php` → PASS.

- [ ] **Step 5: Rampingkan `profile.php`** — ganti blok PHP baris 1–81 (dari `<?php` sampai
      `?>` sebelum `<!DOCTYPE html>`) dengan:

```php
<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Require UMKM owner access
requireAuth(['umkm_owner']);

$user = getCurrentUser();
$db = getDB();

// Get user's business
$business = auth()->getUserBusiness($user['id']);
if (!$business) {
    die('Error: Tidak ada bisnis yang terkait dengan akun Anda. Silakan hubungi administrator.');
}

$service = new \App\Business\BusinessProfileService($db);

$success_message = '';
$error_message = '';

// Handle form submission (validasi & update di service)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $result = $service->update($business['id'], [
        'name' => trim($_POST['name'] ?? ''),
        'owner_name' => trim($_POST['owner_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'business_type' => trim($_POST['business_type'] ?? ''),
    ]);
    if ($result['ok']) {
        $success_message = $result['message'];
        // Refresh business data
        $business = $service->get($business['id']);
    } else {
        $error_message = $result['message'];
    }
}

// Business types for dropdown
$business_types = \App\Business\BusinessProfileService::businessTypes();
?>
```

HTML tidak berubah (`$business`, `$success_message`, `$error_message`, `$business_types` sama).

- [ ] **Step 6: Lint & test penuh** — `php -l src/App/Business/BusinessProfileService.php`, `php -l profile.php`, `composer test` → hijau.

- [ ] **Step 7: Commit** — `git add -A src/App/Business tests/BusinessProfileServiceTest.php profile.php && git commit -m "refactor(profile): ekstrak slice Profil Bisnis ke App\\Business\\BusinessProfileService"`

---

### Task 9: Dokumentasi Struktur Baru

**Files:**
- Modify: `AGENTS.md` (§1 tabel peta file: `includes/rfm.php` → `src/App/Rfm/RfmService.php`,
  `includes/import.php` → `src/App/Import/SpreadsheetImporter.php` + `src/App/Upload/UploadValidator.php`,
  `includes/export.php` → `src/App/Export/*`, tambah baris `src/App/<Fitur>/*`; §8 checklist
  refactor: area RFM/export/import/upload merujuk class baru), `README.md` (bagian struktur
  file/arsitektur)

**Interfaces:** — (dokumentasi)

- [ ] **Step 1: Update `AGENTS.md`** — §1 "Peta file": tambahkan baris
      `| Logika per fitur (vertikal) | src/App/<Fitur>/*.php | Customers, Transactions, Dashboard, Rfm, Import, Upload, Export, Ai, Business |`
      dan ubah baris `includes/rfm.php`, `includes/import.php`, `includes/export.php` menjadi
      merujuk class App yang baru (hapus dari daftar includes). §8 "Refactor — Checklist per
      Area": area RFM sekarang `src/App/Rfm/RfmService.php` (+ `src/Rfm.php` tetap single source
      of truth), area Export sekarang `src/App/Export/*` + repository, area Upload/Import sekarang
      `src/App/Upload/UploadValidator.php` + `src/App/Import/SpreadsheetImporter.php`. Sesuaikan
      kalimat "SQL di includes/rfm.php dibangun dari src/Rfm.php" → "SQL di
      src/App/Rfm/RfmService.php dibangun dari src/Rfm.php".

- [ ] **Step 2: Update `README.md`** — bagian struktur file/arsitektur: ganti daftar
      `includes/export.php`, `includes/import.php`, `includes/rfm.php` dengan daftar
      `src/App/<Fitur>/` (9 class) dan catatan bahwa halaman docroot tipis (lapisan HTTP+render)
      sementara logika data & bisnis di class per fitur.

- [ ] **Step 3: Verifikasi penuh** — `find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l`,
      `composer test` (semua hijau), `composer audit` (0 advisory), `git status` bersih.

- [ ] **Step 4: Commit** — `git add AGENTS.md README.md && git commit -m "docs: struktur vertical slicing di AGENTS.md & README (src/App/<Fitur>)"`

---

## Self-Review

1. **Coverage spec:** Permintaan "vertical slicing" → plan ini mengubah struktur horizontal
   (halaman gemuk + helper lintas-fitur) menjadi 8 slice vertikal per fitur + dokumentasi.
   Gap yang disengaja (di luar scope, tercatat di §2): admin, landing, budget legacy, item
   deferred RENCANA_PERBAIKAN, dan perbaikan 2 temuan kecil (dashboard option segmen,
   `</div>` ekstra analysis.php).
2. **Scan placeholder:** Semua step berisi kode aktual atau instruksi pemindahan presisi
   dengan referensi baris sumber (`includes/import.php` baris 30–144, 146–324;
   `includes/export.php` baris 13–119, 121–228; `api/generate-content.php` baris 68 fungsi
   `generateDummyContent`). Tidak ada "TBD/TODO/implement later".
3. **Konsistensi tipe:** `CustomerRepository::listForDropdown()` didefinisikan Task 1,
   dipakai Task 2 & 6; `TransactionRepository::allWithCustomer()` Task 2, dipakai Task 6;
   `DashboardStats::getRfmData()` Task 3, dipakai dashboard.php; `RfmService::ensureCalculated()`
   Task 4, dipakai analysis.php; `SpreadsheetImporter::import()/history()` Task 5;
   `ContentGenerator::generate(segment, ?client)` Task 7 — semua sama di definisi & pemakaian.
   Namespace `App\Rfm` vs class `App\Rfm` (src/Rfm.php) dibedakan eksplisit dengan `\App\Rfm::`
   di Task 4.
4. **Test coverage per slice:** 1 file test per slice (Task 1–8) + ExportTest ditulis ulang
   dengan asersi format yang dikunci (BOM, `d/m/Y`, `'-'`, `amount*qty`, round-trip XLSX).
   RfmTest/AuthManagerTest/AdminSidebarTest/LandingPageRenderTest/MobileMenuToggleTest tidak
   tersentuh.

## Eksekusi (Handoff)

**Peta sprint:** lihat `docs/plans/2026-08-18-vertical-slicing-sprints.md` — 9 task ini
dikelompokkan menjadi 4 sprint (S1: Task 1–2, S2: Task 3–4, S3: Task 5–6, S4: Task 7–9),
masing-masing dengan Definition of Done & verifikasi per sprint.

Plan disimpan di `docs/plans/2026-08-18-vertical-slicing.md`. Eksekusi disarankan **inline**
(di sesi ini, task-by-task, satu commit per task, checkpoint `composer test` setelah tiap
task — ikuti skill `verification-before-completion` sebelum klaim selesai). Task 1–8 bisa
dieksekusi berurutan; Task 9 (docs) opsional terakhir. Bila ingin lanjut, jawab "eksekusi"
dan mulai dari Task 1.

---
name: csrf-safe-form
description: Adds POST forms safely to this PHP codebase (smartrfm.my.id). Use when creating/modifying any HTML form with POST method or its PHP handler. Enforces CSRF token (csrf_field + requireCsrf), PDO prepared statements, htmlspecialchars output, business_id scoping, and the LIMIT/OFFSET pagination gotcha.
---

# CSRF-Safe Form (SmartRFM)

## Overview

Pola baku halaman prosedural di repo ini (plain PHP 7.4+, tanpa framework):

```
require config → requireAuth(['umkm_owner']) → getUserBusiness()
→ handler POST (+requireCsrf) → query PDO → render HTML + sidebar
```

**Setiap form POST TANPA CSRF = bug. Setiap query input dinamis TANPA
prepared statement = bug. Setiap output user TANPA htmlspecialchars = bug.**

## Checklist Form Baru

1. **Form HTML** — sertakan `<?= csrf_field() ?>` di dalam `<form method="post">`.
2. **Handler POST** — baris paling awal setelah auth:
   ```php
   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
       requireCsrf(); // fail-fast 403, sebelum logic apapun
       // validasi server-side (jangan andalkan HTML5 required saja)
   }
   ```
3. **Validasi** — selalu di server: trim, panjang, format, range. Simpan
   error ke `$errors[]` lalu render ulang dengan pesan.
4. **Query** — PDO prepared statements untuk SEMUA nilai dinamis.
5. **Output** — `htmlspecialchars()` semua data user di HTML.
6. **Scope** — `business_id` SELALU dari session (`auth()->getUserBusiness()`),
   tidak pernah dari input user.
7. **Pagination** (bila ada): `LIMIT ? OFFSET ?` GAGAL di PDO (di-quote jadi
   string) → pakai inline cast:
   ```php
   LIMIT " . (int)$perPage . " OFFSET " . (int)$offset
   ```

## Helper yang Sudah Ada (jangan duplikasi)

| Helper | Lokasi | Fungsi |
|---|---|---|
| `csrf_token()` / `csrf_field()` / `requireCsrf()` | `config/auth.php` | Token & verifikasi |
| `requireAuth(['role'])` | `config/auth.php` | Redirect ke login bila belum auth |
| `requireAuthJson(['role'])` | `config/auth.php` | 401/403 JSON untuk API |
| `auth()->getUserBusiness()` | `config/auth.php` | Business milik session |
| `paginate($total, $perPage, $page)` | `includes/pagination.php` | Hitung offset |
| `renderPagination($totalPages, $page)` | `includes/pagination.php` | Link halaman |
| `env($key, $default)` | `config/env.php` | Kredensial, jangan hardcode |

## Pola Handler POST yang Benar

```php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
requireAuth(['umkm_owner']);
$business = auth()->getUserBusiness();
if (!$business) { http_response_code(403); exit('Akses ditolak'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $name = trim($_POST['name'] ?? '');
    if ($name === '') { $errors[] = 'Nama wajib diisi'; }
    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO customers (business_id, name) VALUES (?, ?)');
        $stmt->execute([$business['id'], $name]);
        header('Location: customers.php'); exit;
    }
}
```

## API Endpoint (POST/GET JSON)

- Baris pertama: `requireAuthJson(['umkm_owner'])` — bukan redirect HTML.
- Respon JSON + status benar: 401 belum login, 403 role/ownership, 500 internal.
- Scope `business_id` dari session. Log aktivitas via `auth()->logActivity()` bila relevan.
- Jangan letakkan API key / kredensial di file — pakai `env()`.

## Red Flags — STOP

- `<form>` POST tanpa `csrf_field()` / handler tanpa `requireCsrf()`.
- Query dengan string concatenation dari input user.
- `LIMIT ? OFFSET ?` dengan placeholder.
- Mengambil `business_id` dari `$_POST`/`$_GET`/query string.
- `echo` data user tanpa `htmlspecialchars`.
- Menyalin logika yang sudah terpusat (sidebar, pagination, import, export).

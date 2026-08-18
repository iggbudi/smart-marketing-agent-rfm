# AGENTS.md — Panduan Coding Agent (Smart Marketing Agent / RFM)

> Kontrak kerja untuk semua agen (AI/human) yang mengerjakan repo
> **smartrfm.my.id**. Panduan ini adalah **peta akurat codebase**: arsitektur,
> konvensi wajib, cara menambah fitur, dan checklist sebelum refactor area
> tertentu. Semua di sini merujuk kondisi nyata repo setelah `RENCANA_PERBAIKAN.md`
> tuntas (4 fase selesai, `composer test` hijau).

---

## 1. Ringkasan Arsitektur

**Plain PHP 7.4+ tanpa framework.** Pola halaman prosedural: `require config`
→ `requireAuth()` → ambil `business` → handler POST (+CSRF) → query PDO →
render HTML + `includes/sidebar.php`. Tidak ada router/front controller;
URL = file `.php` langsung di docroot.

### Peta file

| Area | File | Catatan |
|---|---|---|
| Halaman UMKM owner | `dashboard.php`, `customers.php`, `transactions.php`, `analysis.php`, `upload.php`, `ai-content.php`, `profile.php` | `requireAuth(['umkm_owner'])` |
| Halaman admin (super_admin) | `admin/*.php` | `requireAuth(['super_admin'])` |
| Auth & global helpers | `config/auth.php` | `AuthManager` + `auth()`, `requireAuth()`, `requireAuthJson()`, `csrf_*()`, `getCurrentUser()` |
| Kredensial env | `config/env.php` | `env($key, $default)` — prioritas: env var > `.env` > default |
| DB / OpenAI | `config/database.php`, `config/openai.php` (**gitignored**) | Template yang di-commit: `*.example.php` |
| API | `api/*.php` | `requireAuthJson()` → JSON + status HTTP benar |
| Logika terpusat | `includes/rfm.php`, `includes/import.php`, `includes/export.php`, `includes/pagination.php`, `includes/sidebar.php` | Satu-satunya sumber sidebar (admin pakai wrapper) |
| Logika murni (PSR-4 `App\`) | `src/Rfm.php` | Single source of truth skor/segmen RFM; SQL di `includes/rfm.php` **dibangun** dari sini |
| Test | `tests/*.php` | PHPUnit 9.6; bootstrap arahkan ke DB test |
| SQL | `database_schema.sql`, `database_update.sql`, `database_indexes.sql` | Migrasi manual (tidak ada migrasi otomatis) |

### Tabel DB (11)

`businesses`, `users`, `user_sessions`, `activity_logs`, `customers`,
`transactions`, `rfm_analysis`, `ai_generated_content`, `upload_history`,
`api_usage_logs`, `system_settings`.

Relasi kunci: `businesses.user_id → users.id`; `customers/transactions/
rfm_analysis.business_id → businesses.id`. **Semua query data bisnis WAJIB
di-scope `business_id = ?` milik user session** (via `auth()->getUserBusiness()`).

---

## 2. Konvensi Kode (WAJIB)

1. **PDO prepared statements** untuk semua query input dinamis.
   > Gotcha pagination: `LIMIT ? OFFSET ?` **gagal** (PDO meng-quote sebagai string).
   > Pakai inline `(int)` cast: `LIMIT " . (int)$perPage . " OFFSET " . (int)$offset`.
2. Output data user selalu `htmlspecialchars()` (anti-XSS).
3. **CSRF**: semua form POST menyertakan `csrf_field()`; handler POST dipanggil
   `requireCsrf()` (fail-fast 403). Jangan pernah menambah form POST tanpa ini.
4. **API** (`/api/*`): `requireAuthJson(['role'])` → JSON + 401 (belum login) /
   403 (role salah), bukan redirect HTML.
5. Pemilik data: tolak akses lintas-bisnis (selalu `business_id` dari session,
   bukan input user).
6. File rahasia (`config/database.php`, `config/openai.php`, `.env`,
   `.env.*.local`) **tidak pernah di-commit**; hanya commit `*.example.php`
   dan `.env.example`.
7. File `debug_*`, `check_*`, `fix_*`, `test*`, `generate_*` tidak boleh ada
   di web root (tidak bisa diakses via URL).
8. Jangan duplikasi logika yang sudah terpusat: sidebar, RFM, export, import,
   pagination. Kalau menambah fitur serupa, perpanjang helper yang ada.
9. Header keamanan & session cookie sudah diset di `config/auth.php` — jangan
   hapus/double-set. Detail: `docs/SECURITY.md`.

---

## 3. Environment & Kredensial

- Semua kredensial lewat `env()` (`config/env.php`): prioritas **env var asli >
  file `.env` > default**.
- Variabel: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`,
  `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_BASE_URL`.
- Template: `.env.example` (commit); isi lokal: `.env` (gitignored).
- Saat mengubah nama/tambah var env baru: update `.env.example` + bagian
  "Konfigurasi Kredensial" di `README.md`.

---

## 4. Alur Kerja Setiap Perubahan (WAJIB)

1. **Pahami** — baca file terkait + pola di atas; cek `RENCANA_PERBAIKAN.md`
   bila menyentuh item yang belum selesai (tersisa: CSP & kunci IP/UA — didefer).
2. **Kerjakan** — ikuti konvensi §2; satu unit kerja = satu commit.
3. **Test lokal** (§5).
4. **Update docs** — centang checkbox di `RENCANA_PERBAIKAN.md` bila relevan;
   update `README.md` / `docs/*` / `*_SUMMARY.md` untuk perubahan behaviour/setup.
5. **Commit** (§6) → **push** `git push origin main`.

**Aturan keras:**
- Satu commit = satu unit kerja; JANGAN campur banyak perubahan.
- JANGAN commit rahasia (§2.6).
- JANGAN menghapus/merefactor di luar lingkup pekerjaan berjalan.

---

## 5. Local Testing (wajib sebelum commit)

```bash
# 1) Lint semua PHP
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l

# 2) Unit test (PHPUnit 9.6) — DB test: smart_marketing_rfm_test
composer test
#   Catatan: saat jalan sebagai root, composer butuh: COMPOSER_ALLOW_SUPERUSER=1

# 3) Cek dependency & security advisory
composer validate --no-check-publish
composer audit            # harus 0 CVE (saat ini phpspreadsheet 1.30.6)

# 4) Test fungsional (bila DB live aksesibel, MariaDB 10.11 lokal)
#    - recalculateRFM() harus cocok dgn App\Rfm::segmentFromScores()
#    - render halaman via wrapper CLI dengan session valid (pola tests/ + /tmp)
```

Aturan test DB: `tests/bootstrap.php` set `DB_NAME=smart_marketing_rfm_test`
sebelum config dimuat — **jangan pernah jalankan test ke DB produksi**.
Jika schema berubah, refresh test DB:
```bash
mysql -u root -e "DROP DATABASE IF EXISTS smart_marketing_rfm_test; CREATE DATABASE smart_marketing_rfm_test CHARACTER SET utf8mb4;"
for f in database_schema.sql database_update.sql database_indexes.sql; do sed '/^USE /d' "$f" | mysql -u root smart_marketing_rfm_test; done
```

---

## 6. Konvensi Commit

Untuk pekerjaan di luar plan, pakai prefix konvensional:

| Prefix | Contoh |
|---|---|
| `feat:` | `feat: tambah halaman rekap bulanan untuk UMKM` |
| `fix:` | `fix(export): total transaksi salah saat qty > 1` |
| `refactor:` | `refactor(rfm): pindah threshold ke src/Rfm.php` |
| `test:` | `test: tambah kasus segmentasi At Risk` |
| `docs:` | `docs: perbarui SECURITY.md` |
| `security:` | `security: bump phpspreadsheet` |
| `chore:` | `chore: tambah index transaksi` |

Bila masih mengerjakan sisa item `RENCANA_PERBAIKAN.md`, lanjutkan prefix
`faseN:` / `fix(area):`. Git user lokal sudah diset (`iggbudi`).

---

## 7. Menambah Fitur — Checklist Arsitektur

### 7.1 Halaman baru (UMKM owner)
1. Salin pola dari `customers.php`: `require database.php + auth.php` →
   `requireAuth(['umkm_owner'])` → `auth()->getUserBusiness()` (bailout bila null).
2. Handler POST (bila ada form): `requireCsrf()` di awal.
3. Query: prepared statements + scope `business_id`.
4. HTML: `htmlspecialchars` semua output; pakai `includes/sidebar.php` +
   `assets/user-styles.css`; bootstrap 5 CDN; baris baru `index` = `$offset + $index + 1` bila paginated.
5. Tambah menu di `includes/sidebar.php` (SATU sumber — jangan edit wrapper admin).
6. Test: `php -l`, render CLI (session valid), `composer test` bila logika di-extract.

### 7.2 Endpoint API baru
1. `requireAuthJson(['umkm_owner'])` baris paling awal (sebelum query).
2. Semua respon JSON + status benar: 401 belum login, 403 role/ownership,
   500 error internal. Jangan pernah redirect/HTML.
3. Scope `business_id` dari session. Log aktivitas bila relevan
   (`auth()->logActivity()`).
4. Jangan letakkan API key di file (pakai `env('OPENAI_API_KEY')` dst).

### 7.3 Fitur data baru (tabel/migrasi)
1. Buat file migrasi `database_xxx.sql` (konvensi: schema/update/indexes).
   Sertakan `USE smart_marketing_rfm;` di baris awal **dan** catat cara apply
   ke DB lain (strip `USE` via `sed`).
2. Terapkan ke DB live + test DB, lalu refresh test DB (lihat §5).
3. Index komposit untuk query filter/order umum (contoh:
   `idx_trans_biz_cust_date (business_id, customer_id, transaction_date)`).
4. Update `README.md` bagian Database & (bila ada) `docs/SECURITY.md`.

### 7.4 Form baru
- Wajib: `csrf_field()` di form + `requireCsrf()` di handler POST. Gagal = 403.
- Validasi server-side (jangan andalkan HTML5 `required` saja).

### 7.5 Fitur export/import baru
- **Export**: perpanjang `includes/export.php` — tambah `xxxHeaders()`,
  `formatXxxRow()`, `writeXxxCsv()`, `buildXxxSpreadsheet()`; jangan tulis
  logika PhpSpreadsheet inline di API. Tambah kasus di `tests/ExportTest.php`
  (BOM, header, baris, round-trip XLSX).
- **Import**: perpanjang `includes/import.php` (`importCustomerSpreadsheet` +
  helper `_import*`); header fleksibel ID/EN; upsert per `business_id`;
  `beginTransaction/commit/rollBack`; laporan sukses/gagal per baris.
- Validasi upload: ekstensi + MIME `finfo_file()`, ≤ 5MB, rename acak, simpan
  di `storage/uploads/` (terproteksi, lihat SECURITY.md §6).

### 7.6 Fitur AI (OpenAI)
- `api/generate-content.php` + `ai-content.php`; key dari `env('OPENAI_API_KEY')`.
- Jangan commit key; `config/openai.example.php` adalah template.
- Hitung/pantau token via `api_usage_logs` bila relevan.

---

## 8. Refactor — Checklist per Area (WAJIB dicek sebelum mulai)

### RFM (`includes/rfm.php`, `src/Rfm.php`, `analysis.php`)
- `src/Rfm.php` adalah **single source of truth** (skor + segmentasi); SQL di
  `includes/rfm.php` DIBANGUN dari `App\Rfm::*Sql()`. Ubah logika di SATU tempat,
  dua-duanya (PHP & SQL) otomatis sinkron.
- Setelah refactor, jalankan `composer test` (RfmTest 125 kombinasi skor) **dan**
  test fungsional: `recalculateRFM($db, 1)` → cocokkan `rfm_segment` vs
  `App\Rfm::segmentFromScores()`.
- `analysis.php` & dashboard membaca tabel `rfm_analysis` **langsung**
  (skor sudah dipersist) — jangan pindahkan komputasi ke page-load.
- JANGAN jalankan DELETE+INSERT massal di setiap page-load; hanya via tombol
  "Hitung Ulang RFM" (POST+CSRF) atau first-run saat data kosong.

### Auth / session (`config/auth.php`)
- Jaga: `session_regenerate_id(true)` saat login (guard `headers_sent()` utk
  test CLI), cookie flags, `hasRequiredRole()` dipakai `requireAuth` &
  `requireAuthJson`. Update `tests/AuthManagerTest.php` bila logika berubah
  (login/session expiry/role check terhadap DB test).
- Jangan ubah `AuthManager` tanpa update test yang menyangkutnya.

### Export (`includes/export.php`, `api/export-*.php`)
- Pertahankan: BOM UTF-8 di CSV, header kolom, format tanggal `d/m/Y`,
  fallback `'-'`, total `amount*qty`. `tests/ExportTest.php` mengunci format ini.
- API export wajib tetap `requireAuthJson` + scope bisnis; jangan pindahkan
  query data bisnis keluar dari scope.

### Pagination (`includes/pagination.php`, `customers.php`, `transactions.php`)
- Pertahankan query string `q`/`page`; `LIMIT/OFFSET` inline `(int)` cast
  (bukan placeholder — lihat §2.1).
- Statistik kartu = query agregat penuh (bukan dari array halaman aktif).

### DB / query (`database_*.sql`, semua halaman)
- Prepared statements; jangan pecahkan index `idx_trans_biz_cust_date`.
- Pindah/rename tabel? Update semua query + `database_*.sql` + README.

### Config / env (`config/env.php`, `config/database*.php`, `config/openai*.php`)
- Ubah kredensial → `config/database.php`/`openai.php` (gitignored) + `.env`
  lokal; **contoh di-commit** = `*.example.php` + `.env.example`.
- Tambah var env → update `.env.example` + README §Konfigurasi.

### Upload (`upload.php`, `api/upload-excel.php`, `includes/import.php`)
- Jangan longgarkan validasi (MIME `finfo`, 5MB, rename acak, folder terproteksi).
- Error handling: jangan `echo` detail koneksi/query ke output — `error_log` +
  exception netral.

### Rename/pindah file
- Cek referensi: `grep -rn "nama_file" --include="*.php" --include="*.md" .`
  (sidebar, includes, docs, `*_SUMMARY.md`). Update semuanya dalam 1 commit.

---

## 9. Aturan Keamanan (ringkas)

- Prepared statements + `htmlspecialchars` + CSRF + API JSON auth (401/403) —
  semua wajib, jangan diregress.
- Kredensial hanya via env/`.env`; rotasi bila pernah bocor (prosedur:
  `docs/SECURITY.md` §7).
- `composer audit` harus tetap 0 advisory — jangan turunkan versi phpspreadsheet
  ke versi yang punya CVE (min. 1.30.6).
- Perubahan perilaku keamanan → update `docs/SECURITY.md`.

---

## 10. Status Plan & Sisa Item (konteks)

`RENCANA_PERBAIKAN.md` **selesai** (Fase 1–4, DoD semua centang). Sisa 2 item
sengaja **didefer** dan tercatat:
- CSP dasar (butuh refactor inline `<script>`/CDN bertahap) — RENCANA 2.4
- Kunci `user_sessions` ke IP/UA ringan (risiko user IP dinamis) — RENCANA 2.2

Opsional di masa depan: purge blobs PDF lama dari history git
(`git filter-repo`, commit `3802c5c` ke atas).

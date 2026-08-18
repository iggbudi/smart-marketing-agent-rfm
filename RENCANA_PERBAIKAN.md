# Rencana Perbaikan — Smart Marketing Agent (smartrfm.my.id)

> Dokumen ini disusun berdasarkan hasil audit codebase per **18 Agustus 2026**.
> Tujuan: membawa aplikasi dari kondisi MVP ke kondisi **production-ready** secara bertahap.

---

## Ringkasan Kondisi Saat Ini

| Aspek | Status |
|---|---|
| SQL Injection | ✅ Aman (prepared statements konsisten) |
| XSS | ✅ Cukup aman (`htmlspecialchars` dipakai konsisten) |
| Password hashing | ✅ Benar (`password_verify`) |
| Session management | ⚠️ Ada token DB + expiry, tapi tanpa `session_regenerate_id()` |
| Keamanan endpoint API | 🔴 Tidak ada autentikasi di `/api/*` |
| File debug terekspos | 🔴 Ada di web root domain live |
| CSRF protection | 🔴 Tidak ada |
| Fitur upload Excel | 🔴 Placeholder (import data dummy) |
| Duplikasi kode | ⚠️ Tinggi (query RFM, 3 file dashboard) |
| Testing | 🔴 Tidak ada unit test |

---

## Fase 1 — Perbaikan Kritis (Segera, estimasi 1–2 hari)

### 1.1 Amankan semua endpoint `/api`
- [x] `api/generate-content.php`: tambahkan `require_once '../config/auth.php'` + `requireAuth(['umkm_owner'])`, hapus `Access-Control-Allow-Origin: *`
- [x] `api/upload-excel.php`: tambahkan autentikasi; ganti hardcode `business_id = 1` dengan `business_id` dari session user
- [x] `api/export-customers.php` & `api/export-transactions.php`: audit ulang — pastikan wajib login dan export hanya data business milik user (cek `getUserBusiness()`), tolak akses lintas-bisnis
- [x] Pastikan semua endpoint mengembalikan JSON error + HTTP status yang benar saat belum login (bukan redirect HTML)

### 1.2 Hapus / pindahkan file debug & diagnostik dari web root
File yang dihapus (tidak lagi dapat diakses via URL):
- [x] `debug_auth.php` (berisiko membocorkan data user)
- [x] `fix_passwords.php` (berisiko memodifikasi password)
- [x] `check_api_table.php`, `check_missing_tables.php`, `check_tables.php`, `check_transactions_table.php`
- [x] `test-export.php`, `test.html`, `test_date_format.php`, `test_export_format.php`
- [x] `generate_sample_data.php` (hanya untuk seeding awal, jangan terekspos — dipindah jadi script non-web saat Fase 3/4)

### 1.3 Perbaiki bug error-handling di `api/upload-excel.php`
- [x] Catch block meng-update `WHERE upload_id = ?` padahal nama kolomnya `id` → ganti ke `WHERE id = ?`

### 1.4 Rotasi kredensial
- [x] Ganti password user `smartrfm_user` di MySQL (sudah terekspos di file & mungkin di riwayat) — password baru di-rotasi & di-set ke `config/database.php` (file ini di-gitignore, jadi tidak ikut ter-commit)
- [x] Pastikan API key OpenAI tidak pernah di-commit (saat ini masih placeholder — aman, tapi jaga pattern) — sudah diverifikasi via `git log -p` tidak ada key `sk-*` asli di history

---

## Fase 2 — Hardening Keamanan (estimasi 2–4 hari)

### 2.1 CSRF protection
- [x] Tambah helper `csrf_token()` / `csrf_verify()` di `config/auth.php` (termasuk `requireCsrf()` fail-fast 403 & `csrf_field()`)
- [x] Sisipkan token tersembunyi di **semua form POST**: `login.php`, `customers.php`, `transactions.php`, `profile.php`, `upload.php`, `admin/users.php`, `admin/businesses.php`, `admin/settings.php` (+ form upload/AI di `dashboard.php`/`dashboard_new.php` & `ai-content.php`)
- [x] Validasi token di setiap handler POST (fail-fast dengan 403)

### 2.2 Session hardening
- [x] Panggil `session_regenerate_id(true)` setelah login sukses (cegah session fixation)
- [x] Set cookie flags: `HttpOnly`, `Secure`, `SameSite=Lax` via `session_set_cookie_params()` di `config/auth.php`
- [ ] (Opsional) kunci `user_sessions` ke IP/UA ringan — **didefer** (berisiko kunci di luar saat IP dinamis; dipertimbangkan ulang)

### 2.3 Validasi upload file sungguhan
- [x] Validasi ekstensi + MIME via `finfo_file()` (bukan hanya `$file['type']` dari client)
- [x] Batasi ukuran file (maks. 5 MB) dan rename file sebelum disimpan (nama acak, bukan nama user)
- [x] Simpan file upload di luar web root atau folder yang diblokir eksekusi PHP (ke `storage/uploads/` + `.htaccess` blokir PHP/listing)

### 2.4 Header keamanan (di PHP atau vhost Apache)
- [x] `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy` (diset di `config/auth.php`, berlaku ke semua halaman yang memuatnya)
- [ ] Pertimbangkan CSP dasar (perhatikan CDN Bootstrap/FA/Chart.js/DataTables) — **didefer**: banyak halaman memakai inline `<script>` + banyak CDN, butuh refactor bertahap agar tidak merusak fungsionalitas

### 2.5 Perbaiki `Database::getConnection()`
- [x] Jangan `echo 'Connection error.'` ke output — lempar exception netral + log detail ke `error_log` (diterapkan di `config/database.example.php`; file lokal `config/database.php` juga disesuaikan, namun tidak ikut ter-commit karena gitignored)

---

## Fase 3 — Fungsionalitas & Kualitas Kode (estimasi 1–2 minggu)

### 3.1 Implementasi upload Excel yang sebenarnya
- [x] Gunakan PhpSpreadsheet (sudah terinstall via Composer) untuk membaca file yang diupload (xlsx/xls/csv; logika terpusat di `includes/import.php`)
- [x] Mapping kolom: nama, email/telepon, tanggal transaksi, nominal, produk, qty (header fleksibel ID/EN)
- [x] Upsert customer per `business_id` (bukan hanya lookup global by email)
- [x] Transaksi DB (`beginTransaction`/`commit`/`rollBack`) untuk import batch
- [x] Laporkan jumlah baris sukses/gagal + pesan per baris ke user

### 3.2 Refactor kalkulasi RFM
- [x] Pindahkan fungsi `calculateRFM()` dari `analysis.php` ke file terpusat (`includes/rfm.php` → `recalculateRFM()`)
- [x] Hilangkan duplikasi CASE 3-skor (diulang 4× dalam satu query) — pecah jadi subquery skor dulu, lalu segmentasi dari skor
- [x] **Jangan jalankan DELETE+INSERT massal setiap page-load**: jalankan hanya via tombol eksplisit "Hitung Ulang RFM" (POST + CSRF) atau saat first-run (data kosong)
- [x] Simpan juga `last_purchase_date`, `total_transactions`, `total_spent` saat insert (kolom sekarang terisi; halaman analysis membaca langsung dari kolom tsb)

### 3.3 Bersihkan duplikasi
- [x] Hapus `dashboard_old.php` dan `dashboard_new.php` (identik/versi lama dari `dashboard.php`)
- [x] Satukan sidebar include (`includes/sidebar.php` vs `admin/includes/sidebar.php` → pola konsisten; `admin/includes/sidebar.php` jadi wrapper yang memunculkan satu sumber)

### 3.4 Pagination & performa
- [x] Pagination server-side untuk `customers.php` dan `transactions.php` (LIMIT/OFFSET via `includes/pagination.php`, 20 baris/halaman, pencarian server-side `?q=`, penomoran lanjut antar halaman)
- [x] Tambah index DB: `transactions(business_id, customer_id, transaction_date)` via `database_indexes.sql`; `customers(business_id)` & `rfm_analysis(business_id)` sudah ada otomatis dari FK
- [x] Pertimbangkan VIEW `v_rfm_scores` — **tidak dibuat**: skor R/F/M sudah dipersist di `rfm_analysis` sejak Fase 3.2 (`recalculateRFM()`), `analysis.php` & dashboard membaca tabel tsb langsung; VIEW hanya duplikasi komputasi tanpa keuntungan query

### 3.5 Kredensial via environment
- [x] Baca konfigurasi DB & OpenAI dari `getenv()` dengan fallback (helper `config/env.php`: prioritas env var > `.env` > default; dipakai `config/database.php` & `config/openai.php`)
- [x] Update `.env.example` / dokumentasi deployment (README: bagian Environment & Deployment)

---

## Fase 4 — Testing & Dokumentasi (estimasi 3–5 hari)

### 4.1 Unit test (PHPUnit)
- [x] Install `phpunit/phpunit` sebagai dev-dependency (+ bump `phpoffice/phpspreadsheet` 1.30.0 → 1.30.6: menutup 9 advisory keamanan, `composer audit` 0 CVE)
- [x] Test `AuthManager` (login sukses/gagal, session expiry, role check via `hasRequiredRole()`) dengan DB test `smart_marketing_rfm_test` (tests/AuthManagerTest.php)
- [x] Test segmentasi RFM — logika skor & segmen diekstrak ke `src/Rfm.php` (single source of truth; SQL di includes/rfm.php dibangun dari fungsi yang sama) (tests/RfmTest.php, 125 kombinasi skor)
- [x] Test helper export — logika export diekstrak ke `includes/export.php` (CSV BOM/header/baris + round-trip XLSX) (tests/ExportTest.php)
- [x] CI sederhana: script `composer test` → `phpunit` (phpunit.xml + tests/bootstrap.php arahkan ke DB test; catatan: saat menjalankan sebagai root perlu `COMPOSER_ALLOW_SUPERUSER=1`)

### 4.2 Kebersihan repo
- [ ] Hapus file PDF besar dari repo (`imk.pdf` 3.9MB, `panduan.pdf` 3.9MB, `Budget_...pdf` 39MB) — simpan di storage eksternal / Git LFS
- [ ] Hapus CSV sample data dari repo jika hanya untuk seeding (pindah ke script seeder)

### 4.3 Dokumentasi
- [ ] Update README: instruksi deployment server Linux (bukan hanya XAMPP), hardening checklist, catatan env var
- [ ] Tambah `docs/SECURITY.md`: daftar header keamanan, kebijakan session, rotasi kredensial

---

## Checklist Definisi Selesai (Definition of Done)

- [ ] Semua endpoint `/api/*` menolak request tanpa session valid (401/403 JSON)
- [ ] Tidak ada file `debug_*`, `check_*`, `fix_*`, `test*`, `generate_*` yang bisa diakses via URL
- [ ] Semua form POST memiliki CSRF token yang diverifikasi server
- [ ] `session_regenerate_id()` dipanggil saat login
- [ ] Upload Excel benar-benar membaca file user dan mengimport sesuai business
- [ ] RFM hanya dihitung saat diminta eksplisit, bukan setiap page-load
- [x] `composer test` hijau (27 test/509 asersi); dashboard duplikat dihapus; pagination aktif

---

## Urutan Eksekusi yang Disarankan

1. **Fase 1.1 + 1.2** (amankan API & bersihkan file debug) — dampak keamanan terbesar, effort kecil
2. **Fase 1.3 + 2.1 + 2.2** (bugfix + CSRF + session)
3. **Fase 3.1** (upload Excel fungsional) — nilai fungsional terbesar
4. **Fase 3.2 + 3.3** (refactor RFM & duplikasi)
5. **Fase 4** (testing, kebersihan repo, dokumentasi)



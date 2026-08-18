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
- [ ] Ganti password user `smartrfm_user` di MySQL (sudah terekspos di file & mungkin di riwayat)
- [ ] Pastikan API key OpenAI tidak pernah di-commit (saat ini masih placeholder — aman, tapi jaga pattern)

---

## Fase 2 — Hardening Keamanan (estimasi 2–4 hari)

### 2.1 CSRF protection
- [ ] Tambah helper `csrf_token()` / `csrf_verify()` di `config/auth.php`
- [ ] Sisipkan token tersembunyi di **semua form POST**: `login.php`, `customers.php`, `transactions.php`, `profile.php`, `upload.php`, `admin/users.php`, `admin/businesses.php`, `admin/settings.php`
- [ ] Validasi token di setiap handler POST (fail-fast dengan 403)

### 2.2 Session hardening
- [ ] Panggil `session_regenerate_id(true)` setelah login sukses (cegah session fixation)
- [ ] Set cookie flags: `HttpOnly`, `Secure`, `SameSite=Lax` via `session_set_cookie_params()` di `config/auth.php`
- [ ] (Opsional) kunci `user_sessions` ke IP/UA ringan

### 2.3 Validasi upload file sungguhan
- [ ] Validasi ekstensi + MIME via `finfo_file()` (bukan hanya `$file['type']` dari client)
- [ ] Batasi ukuran file (mis. 5 MB) dan rename file sebelum disimpan
- [ ] Simpan file upload di luar web root atau folder yang diblokir eksekusi PHP

### 2.4 Header keamanan (di PHP atau vhost Apache)
- [ ] `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`
- [ ] Pertimbangkan CSP dasar (perhatikan CDN Bootstrap/FA/Chart.js/DataTables)

### 2.5 Perbaiki `Database::getConnection()`
- [ ] Jangan `echo 'Connection error.'` ke output — lempar exception atau tampilkan halaman error netral; log detail ke `error_log`

---

## Fase 3 — Fungsionalitas & Kualitas Kode (estimasi 1–2 minggu)

### 3.1 Implementasi upload Excel yang sebenarnya
- [ ] Gunakan PhpSpreadsheet (sudah terinstall via Composer) untuk membaca file yang diupload
- [ ] Mapping kolom: nama, email/telepon, tanggal transaksi, nominal, produk, qty
- [ ] Upsert customer per `business_id` (jangan hanya lookup global by email)
- [ ] Transaksi DB (`beginTransaction`/`commit`/`rollBack`) untuk import batch
- [ ] Laporkan jumlah baris sukses/gagal + pesan per baris ke user

### 3.2 Refactor kalkulasi RFM
- [ ] Pindahkan fungsi `calculateRFM()` dari `analysis.php` ke file terpusat (mis. `includes/rfm.php`)
- [ ] Hilangkan duplikasi CASE 3-skor (diulang 4× dalam satu query) — pecah jadi subquery skor dulu, lalu segmentasi dari skor
- [ ] **Jangan jalankan DELETE+INSERT massal setiap page-load**: jalankan hanya via tombol eksplisit "Hitung Ulang RFM" (POST + CSRF) atau cron
- [ ] Simpan juga `last_purchase_date`, `total_transactions`, `total_spent` saat insert (kolom sudah ada tapi tidak diisi)

### 3.3 Bersihkan duplikasi
- [ ] Hapus `dashboard_old.php` dan `dashboard_new.php` (identik/versi lama dari `dashboard.php`)
- [ ] Satukan sidebar include (`includes/sidebar.php` vs `admin/includes/sidebar.php` → pola konsisten)

### 3.4 Pagination & performa
- [ ] Pagination server-side untuk `customers.php` dan `transactions.php` (LIMIT/OFFSET atau DataTables server-side)
- [ ] Tambah index DB: `transactions(business_id, customer_id, transaction_date)`, `customers(business_id)`, `rfm_analysis(business_id)`
- [ ] Pertimbangkan VIEW `v_rfm_scores` agar query analisis lebih ringkas

### 3.5 Kredensial via environment
- [ ] Baca konfigurasi DB & OpenAI dari `getenv()` dengan fallback
- [ ] Update `.env.example` / dokumentasi deployment

---

## Fase 4 — Testing & Dokumentasi (estimasi 3–5 hari)

### 4.1 Unit test (PHPUnit)
- [ ] Install `phpunit/phpunit` sebagai dev-dependency
- [ ] Test `AuthManager` (login sukses/gagal, session expiry, role check) dengan DB test
- [ ] Test segmentasi RFM (input skor → segment yang benar untuk semua kategori)
- [ ] Test helper export (format CSV/Excel benar)
- [ ] CI sederhana: script `composer test`

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
- [ ] `composer test` hijau; dashboard duplikat dihapus; pagination aktif

---

## Urutan Eksekusi yang Disarankan

1. **Fase 1.1 + 1.2** (amankan API & bersihkan file debug) — dampak keamanan terbesar, effort kecil
2. **Fase 1.3 + 2.1 + 2.2** (bugfix + CSRF + session)
3. **Fase 3.1** (upload Excel fungsional) — nilai fungsional terbesar
4. **Fase 3.2 + 3.3** (refactor RFM & duplikasi)
5. **Fase 4** (testing, kebersihan repo, dokumentasi)



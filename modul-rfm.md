# Smart Marketing Agent — Modul & Alur RFM

Dokumen ini merangkum modul, alur proses bisnis end-to-end, halaman/API, tabel, serta kriteria selesai dan metrik untuk platform Smart Marketing Agent RFM.

## Ringkasan Tujuan
- Memastikan alur Super Admin dan UMKM Owner berjalan berurutan dan konsisten.
- Menjadi acuan implementasi modul, validasi data, analitik RFM, AI content, dan pelaporan.

## Aktor & Peran
- Super Admin: Kelola user, bisnis, parameter sistem, monitoring & governance.
- UMKM Owner: Kelola data bisnis, pelanggan, transaksi, analisis RFM, kampanye, laporan.

## Alur End-to-End
0. Setup & Konfigurasi
1. Akses & Autentikasi
2. Onboarding Bisnis
3. Sumber Data (Impor/Entry)
4. Validasi & Normalisasi Data
5. Pengelolaan Master Data
6. Analisis RFM
7. Dashboard & Analitik
8. Tindakan Berbasis Segmen (AI/Kampanye)
9. Pelaporan & Ekspor
10. Admin & Governance
11. API Layer & Integrasi
12. Keamanan & Audit
13. Observability & Maintenance

---

## Tahap 0 — Setup & Konfigurasi
- Halaman/Script: `README.md`, `INSTALL_EXPORT.md`, `config/database.php`, `config/openai.php`
- Tabel: `system_settings`, baseline skema (`database_schema.sql`), multi-user (`database_update.sql`)
- Alur: Siapkan DB → jalankan migrasi → set koneksi → isi pengaturan (API key, ambang RFM)
- Selesai: Semua tabel tersedia, akun admin default terbentuk, koneksi DB OK
- Metrik: Status health DB, jumlah migrasi sukses/gagal

## Tahap 1 — Akses & Autentikasi
- Halaman/API: `login.php`, `logout.php`, `unauthorized.php`, guard di `config/auth.php`
- Tabel: `users`, `user_sessions`, `activity_logs`
- Alur: Login → verifikasi kredensial (bcrypt) → buat session → redirect sesuai peran; logout hapus session
- Selesai: RBAC aktif (Super Admin vs Owner), session hardening (expiry, regenerasi ID)
- Metrik: Login success rate, failed attempts, active sessions

## Tahap 2 — Onboarding Bisnis
- Halaman: `admin/businesses.php`, `profile.php`
- Tabel: `businesses` (link `user_id` → `users`)
- Alur: Super Admin membuat bisnis → assign owner; Owner melengkapi profil (kontak, tipe)
- Selesai: Bisnis aktif memiliki owner terkait
- Metrik: Jumlah bisnis aktif, coverage owner assignment

## Tahap 3 — Sumber Data (Impor/Entry)
- Halaman/API: `upload.php`, `api/upload-excel.php`, `customers.php`, `transactions.php`
- Tabel: `upload_history`, `customers`, `transactions`
- Alur: Pilih file CSV/XLSX → unggah → pratinjau parsing → validasi awal → konfirmasi impor → catat riwayat
- Selesai: Data pelanggan/transaksi tersimpan, riwayat impor tercatat
- Metrik: Baris diimpor, baris gagal, durasi proses per file

## Tahap 4 — Validasi & Normalisasi Data
- Script/Helper: `test_date_format.php`, `test_export_format.php`, `check_transactions_table.php`
- Tabel: `upload_history` (status: `processing|completed|failed`)
- Alur: Cek kolom wajib → normalisasi tanggal/angka → deduplikasi → rollback jika gagal → laporkan error
- Selesai: Semua baris valid dan konsisten (tanggal, nominal)
- Metrik: Error rate per impor, kategori error (tanggal/angka/duplikat)

## Tahap 5 — Pengelolaan Master Data
- Halaman/API: `customers.php`, `transactions.php`; CRUD API sesuai README (`/api/customers`, `/api/transactions`)
- Tabel: `customers` (per `business_id`), `transactions` (FK `customer_id`)
- Alur: Listing + filter + pagination; tambah/edit/hapus; validasi relasi bisnis dan FK
- Selesai: CRUD stabil, konsistensi referensial terjaga
- Metrik: Jumlah pelanggan aktif, transaksi per periode, AOV (avg order value)

## Tahap 6 — Analisis RFM
- Halaman/API: `analysis.php`; rencana endpoint `GET /api/rfm-analysis`
- Tabel: `rfm_analysis` (skor R/F/M, segmen, ringkasan: `last_purchase_date`, `total_transactions`, `total_spent`)
- Alur: Pilih bisnis & periode → jalankan perhitungan R/F/M → skoring & segmentasi → simpan/refresh hasil → tampilkan
- Selesai: Semua pelanggan bertransaksi memiliki skor & segmen akurat
- Metrik: Distribusi segmen, rata-rata skor R/F/M, proxy CLV (mis. total_spent)

## Tahap 7 — Dashboard & Analitik
- Halaman: `dashboard.php`, `dashboard_new.php`, `admin/analytics.php`
- Data: agregasi dari `transactions`, `rfm_analysis`, `customers`
- Alur: Tampilkan KPI (pendapatan, order, pelanggan aktif) + grafik tren + distribusi segmen; dukung filter waktu/bisnis
- Selesai: KPI/visual konsisten dengan data dan filter
- Metrik: Revenue, jumlah order, pelanggan aktif, segmen dominan

## Tahap 8 — Tindakan Berbasis Segmen (AI/Kampanye)
- Halaman/API: `ai-content.php`, `api/generate-content.php`
- Tabel: `ai_generated_content`, `api_usage_logs`
- Alur: Pilih segmen & kanal (email/WA/promo) → generate copy (OpenAI) → review → simpan/ekspor → tracking token/biaya
- Selesai: Konten tersimpan dengan metadata model/tokens; siap dieksekusi
- Metrik: Konten per segmen/kanal, tokens dan biaya per konten

## Tahap 9 — Pelaporan & Ekspor
- Halaman/API: `admin/reports.php`, `api/export-customers.php`, `api/export-transactions.php`, `test-export.php`
- Sumber: `customers`, `transactions`, `rfm_analysis`
- Alur: Pilih laporan & periode → preview → ekspor CSV/XLSX → verifikasi format
- Selesai: File sesuai skema kolom & format; dapat dibuka Excel
- Metrik: Jumlah ekspor, ukuran file, error ekspor

## Tahap 10 — Admin & Governance
- Halaman: `admin/users.php`, `admin/settings.php`, `admin/api-management.php`, `admin/dashboard.php`
- Tabel: `users`, `system_settings`, `api_usage_logs`, `activity_logs`
- Alur: CRUD user & role; ubah parameter sistem (ambang RFM, kuota AI); pantau pemakaian API
- Selesai: Pengaturan diterapkan tanpa restart, audit tercatat
- Metrik: User aktif, perubahan setting, biaya API per bisnis

## Tahap 11 — API Layer & Integrasi
- Endpoint (sesuai README):
  - Auth: `POST /api/login`, `POST /api/logout`
  - Data: `GET/POST/PUT/DELETE /api/customers`, `.../transactions`
  - Analytics: `GET /api/rfm-analysis`, `GET /api/analytics`
  - Export: `GET /api/reports`
- Tabel: `api_usage_logs`, `user_sessions`
- Alur: Session/Token-based auth; RBAC per bisnis; logging & rate limit per endpoint
- Selesai: Endpoint terlindungi, validasi input ketat, error handling rapi
- Metrik: QPS, error rate, p95 latency, penggunaan per endpoint

## Tahap 12 — Keamanan & Audit
- Komponen: `config/auth.php`, `vendor/ezyang/htmlpurifier/*`, `debug_auth.php`
- Tabel: `activity_logs`, `user_sessions`
- Alur: Bcrypt, prepared statements, sanitasi HTML, CSRF token form, audit aksi penting
- Selesai: Tidak ada injeksi/HTML berbahaya; audit trail memadai
- Metrik: Insiden keamanan, jumlah audit event/hari

## Tahap 13 — Observability & Maintenance
- Skrip/Docs: `check_tables.php`, `check_missing_tables.php`, `check_api_table.php`, `plan.md`
- Alur: Health check integritas skema, deteksi tabel hilang, rencana upgrade
- Selesai: Health check hijau; alarm bila gagal
- Metrik: Waktu henti, error terdeteksi vs ditangani

---

## Pemetaan Berkas Utama (Saat Ini)
- Auth: `login.php`, `logout.php`, `config/auth.php`, `unauthorized.php`
- Session/Config: `config/database.php`
- Admin Panel: `admin/dashboard.php`, `admin/users.php`, `admin/businesses.php`, `admin/analytics.php`, `admin/api-management.php`, `admin/settings.php`, `admin/reports.php`
- UMKM Owner: `dashboard.php`, `customers.php`, `transactions.php`, `profile.php`
- API: `api/upload-excel.php`, `api/generate-content.php`, `api/export-customers.php`, `api/export-transactions.php`
- AI: `ai-content.php`, `config/openai.php`
- Import/Export Tools: `upload.php`, `test-export.php`, `test_export_format.php`
- RFM/Analitik: `analysis.php`, `dashboard_new.php`, `dashboard_old.php`
- Includes/Assets: `includes/sidebar.php`, `assets/user-styles.css`
- DB & Diagnostik: `database_schema.sql`, `database_update.sql`, `check_tables.php`, `check_transactions_table.php`, `check_missing_tables.php`, `check_api_table.php`, `debug_auth.php`, `generate_sample_data.php`, `fix_passwords.php`

---

## Catatan Implementasi
- RBAC: Pastikan semua halaman/API memeriksa peran dan `business_id` milik user.
- Validasi: Terapkan sanitasi input dan prepared statements untuk semua operasi DB.
- Tanggal & Uang: Normalisasi format di impor dan UI (IDR, `YYYY-MM-DD`).
- Kinerja: Index di kolom pencarian/filter (`business_id`, `customer_id`, `transaction_date`).
- Logging: Catat aktivitas penting ke `activity_logs`, pemakaian API ke `api_usage_logs`.

## Next Steps (Opsional)
- Tambah contoh payload/response untuk endpoint utama.
- Wireframe singkat untuk halaman Dashboard, Customers, Transactions, Analysis, Reports.
- Definisikan ambang default R/F/M di `system_settings` agar mudah dikonfigurasi.


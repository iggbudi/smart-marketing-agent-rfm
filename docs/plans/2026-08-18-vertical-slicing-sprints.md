# Vertical Slicing — Sprint Plan (4 Sprint)

**Goal:** Restrukturisasi aplikasi UMKM owner dari struktur horizontal (halaman gemuk,
helper `includes/` lintas-fitur) menjadi slice vertikal per fitur (`src/App/<Fitur>/` +
halaman/API tipis + test per slice), dalam **4 sprint** — setiap sprint menghasilkan
**increment yang shippable**: aplikasi tetap berjalan, `composer test` hijau, satu commit
per task (AGENTS.md §6).

**Acuan eksekusi detail:** `docs/plans/2026-08-18-vertical-slicing.md` (9 task, berisi kode
aktual per step: test gagal → implementasi → perampingan halaman → commit). File ini = peta
sprint + definisi selesai + verifikasi per sprint.

**Tech Stack:** PHP 7.4+ (runtime 8.3.6), PDO/MariaDB, PhpSpreadsheet 1.30.6+, PHPUnit 9.6.
**Status keputusan:** migrasi Laravel/CI ditolak (lihat §2 plan detail) — plain PHP tetap.

## Peta Sprint ↔ Task

| Sprint | Fokus | Task (dari plan detail) | Dependensi |
|---|---|---|---|
| **S1** | Pola repository + data inti | Task 1 (Customers), Task 2 (Transactions) | — |
| **S2** | Analitik: dashboard & RFM | Task 3 (Dashboard), Task 4 (RFM) | S1 (DashboardStats pakai repo S1) |
| **S3** | Siklus data: import/upload & export | Task 5 (Import+Upload), Task 6 (Export) | S1 (export pakai repo S1) |
| **S4** | AI, profil & dokumentasi | Task 7 (AI Content), Task 8 (Profil), Task 9 (Docs) | — |

## Aturan Global (semua sprint)

- Satu commit = satu unit kerja; prefix `refactor(<area>): ...`.
- TDD: test gagal dulu, implementasi, baru rampingkan halaman.
- Semua query PDO prepared + scope `business_id` dari session; `LIMIT/OFFSET` di-cast `(int)`.
- Sebelum mulai: refresh DB test
  `mysql -u root -e "DROP DATABASE IF EXISTS smart_marketing_rfm_test; CREATE DATABASE smart_marketing_rfm_test CHARACTER SET utf8mb4;"`
  lalu `for f in database_schema.sql database_update.sql database_indexes.sql; do sed '/^USE /d' "$f" | mysql -u root smart_marketing_rfm_test; done`
  (sekaligus verifikasi baseline: `composer test` hijau sebelum sprint 1).
- Verifikasi tiap sprint (wajib, lihat skill `verification-before-completion`):
  `find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l` &&
  `composer test` && `composer audit` (0 advisory) && `git status` bersih.

---

## Sprint 1 — Pola Repository + Data Inti (Customers & Transactions)

**Durasi estimasi:** 1 minggu. **Increment:** halaman `customers.php` & `transactions.php`
menjadi tipis; pola "thin page → class repository" terbentuk dan dipakai 2 fitur utama.

**Scope:**
- Create `src/App/Customers/CustomerRepository.php` + `tests/CustomerRepositoryTest.php`
- Create `src/App/Transactions/TransactionRepository.php` + `tests/TransactionRepositoryTest.php`
- Modify `customers.php`, `transactions.php` (blok PHP diganti; HTML/JS TIDAK berubah)

**Task:** Task 1 (Customers), Task 2 (Transactions) di plan detail.

**Definition of Done:**
- [ ] `CustomerRepositoryTest` + `TransactionRepositoryTest` hijau (CRUD, search+pagination,
      agregat, tolak lintas-bisnis, validasi `\InvalidArgumentException`).
- [ ] `customers.php` & `transactions.php` tanpa SQL inline (grep query `FROM customers`/
      `FROM transactions` di kedua halaman → hanya lewat repository).
- [ ] Perilaku halaman identik (pagination, search `?q=`, statistik kartu, CSRF, pesan).
- [ ] `composer test` penuh hijau (test lama Rfm/Auth/Export/Landing/Mobile/Sidebar tidak rusak).

**Verifikasi:** aturan global + `vendor/bin/phpunit tests/CustomerRepositoryTest.php tests/TransactionRepositoryTest.php`.

**Risiko & mitigasi:** perubahan blok PHP halaman bisa mengubah variabel yang dipakai HTML →
cek daftar variabel di plan detail (Task 1 Step 5, Task 2 Step 5) sebelum commit.

---

## Sprint 2 — Analitik: Dashboard & RFM Analysis

**Durasi estimasi:** 1 minggu. **Increment:** seluruh halaman analitik tipis; logika RFM
pindah ke `App\Rfm\RfmService`; `includes/rfm.php` dihapus.

**Scope:**
- Create `src/App/Dashboard/DashboardStats.php` + `tests/DashboardStatsTest.php`
- Create `src/App/Rfm/RfmService.php` + `tests/RfmServiceTest.php`
- Modify `dashboard.php`, `analysis.php`
- Delete `includes/rfm.php`

**Task:** Task 3 (Dashboard), Task 4 (RFM) di plan detail.

**Definition of Done:**
- [ ] `DashboardStatsTest` + `RfmServiceTest` hijau (agregat, tren, distribusi segmen;
      rekalkulasi first-run vs eksplisit; segmen konsisten dengan `\App\Rfm::segmentFromScores()`).
- [ ] `grep -rn "includes/rfm\|recalculateRFM" --include="*.php" . | grep -v vendor` → kosong.
- [ ] `src/Rfm.php` TIDAK berubah (git diff kosong untuk file itu).
- [ ] `analysis.php` & `dashboard.php` tanpa SQL inline; `RfmTest` (125 kombinasi) tetap hijau.

**Verifikasi:** aturan global + `vendor/bin/phpunit tests/DashboardStatsTest.php tests/RfmServiceTest.php tests/RfmTest.php`.

**Risiko & mitigasi:** namespace `App\Rfm` vs class `App\Rfm` (src/Rfm.php) — panggilan harus
`\App\Rfm::...` (sudah dikunci di kode Task 4 Step 3).

---

## Sprint 3 — Siklus Data: Import/Upload & Export

**Durasi estimasi:** 1 minggu. **Increment:** validasi upload ter-dedupe, impor & export
pindah ke `src/App/`, `includes/import.php` & `includes/export.php` dihapus, API export tipis,
riwayat upload benar-benar tampil di halaman.

**Scope:**
- Create `src/App/Upload/UploadValidator.php` + `tests/UploadValidatorTest.php`
- Create `src/App/Import/SpreadsheetImporter.php` + `tests/ImportTest.php`
- Create `src/App/Export/CustomersExporter.php`, `src/App/Export/TransactionsExporter.php`
- Rewrite `tests/ExportTest.php` (asersi format sama, panggilan class baru)
- Modify `upload.php`, `api/upload-excel.php`, `api/export-customers.php`,
  `api/export-transactions.php`, `tests/bootstrap.php`
- Delete `includes/import.php`, `includes/export.php`

**Task:** Task 5 (Import+Upload), Task 6 (Export) di plan detail.

**Definition of Done:**
- [ ] `ImportTest`, `UploadValidatorTest`, `ExportTest` (tulis ulang) hijau — format export
      dikunci: BOM UTF-8, `d/m/Y`, fallback `'-'`, total `amount*qty`, round-trip XLSX.
- [ ] `grep -rn "includes/import\|importCustomerSpreadsheet\|includes/export\|formatCustomerExportRow\|writeCustomersCsv\|buildCustomersSpreadsheet" --include="*.php" . | grep -v vendor` → kosong.
- [ ] Bug kecil teratasi: `api/export-customers.php` pakai `$business['name']` (bukan
      `$business['business_name']` yang tidak ada).
- [ ] Tabel "Riwayat Upload" di `upload.php` menampilkan data `upload_history` (bukan placeholder).
- [ ] Upload validasi: ekstensi + MIME `finfo` + ≤5MB + rename acak tetap (tidak dilonggarkan).

**Verifikasi:** aturan global + `vendor/bin/phpunit tests/UploadValidatorTest.php tests/ImportTest.php tests/ExportTest.php`.

**Risiko & mitigasi:** impor adalah kode pindahan presisi — ikuti substitusi `$db → $this->db`,
`_import* → $this->*` sesuai referensi baris di Task 5 Step 3b (sudah diverifikasi: 30–144, 146–324).

---

## Sprint 4 — AI Content, Profil Bisnis & Dokumentasi

**Durasi estimasi:** 1 minggu. **Increment:** halaman AI tidak lagi memanggil API via HTTP
internal; logika profil bisnis ter-extract; struktur baru terdokumentasi di AGENTS.md/README.

**Scope:**
- Create `src/App/Ai/ContentGenerator.php` + `tests/ContentGeneratorTest.php`
- Create `src/App/Business/BusinessProfileService.php` + `tests/BusinessProfileServiceTest.php`
- Modify `ai-content.php`, `api/generate-content.php`, `profile.php`
- Modify `AGENTS.md` (§1 peta file, §8 checklist refactor), `README.md` (struktur file)

**Task:** Task 7 (AI Content), Task 8 (Profil), Task 9 (Docs) di plan detail.

**Definition of Done:**
- [ ] `ContentGeneratorTest` + `BusinessProfileServiceTest` hijau (OpenAI di-mock tanpa
      network; validasi wajib + format email + unik lintas bisnis).
- [ ] `grep -rn "generateDummyContent" --include="*.php" . | grep -v vendor` → kosong
      (dummy pindah ke `ContentGenerator::dummyContent()`).
- [ ] `ai-content.php` tidak lagi `file_get_contents('http://localhost...')` — panggil
      `ContentGenerator` langsung (hapus ketergantungan path `/smart/` hardcoded).
- [ ] `AGENTS.md` & `README.md` konsisten dengan struktur baru (`src/App/<Fitur>/`; daftar
      `includes/` yang tersisa: sidebar, pagination).
- [ ] Verifikasi penuh terakhir: lint seluruh repo, `composer test`, `composer audit`, `git status` bersih.

**Verifikasi:** aturan global + `vendor/bin/phpunit tests/ContentGeneratorTest.php tests/BusinessProfileServiceTest.php`.

**Risiko & mitigasi:** `ai-content.php` menampilkan konten yang dulu sudah di-escape API →
setelah panggil langsung, escape di halaman: `nl2br(htmlspecialchars($content))` (sudah di
kode Task 7 Step 5).

---

## Catatan Eksekusi

- Jalankan **inline** sprint-by-sprint: sprint selesai = checkpoint (lint + `composer test` +
  `composer audit` hijau) sebelum lanjut sprint berikutnya.
- Satu commit per task (9 commit total): Task 1–8 → `refactor(<area>): ...`, Task 9 → `docs: ...`.
- Bila ingin menghentikan di tengah: berhenti di akhir sprint (semua increment tetap shippable).
- Item yang sengaja TIDAK disentuh (tetap): `admin/*.php`, `index.php`, `budget*.php`,
  `src/Rfm.php`, item deferred RENCANA_PERBAIKAN (CSP, session IP/UA).

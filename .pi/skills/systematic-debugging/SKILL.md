---
name: systematic-debugging
description: Root-cause debugging for this PHP codebase (smartrfm.my.id). Use when encountering any bug, test failure, or unexpected behavior, before proposing fixes. Enforces investigation-first (reproduce, trace root cause via php -l/logs/DB test) and forbids symptom fixes. No fixes without root cause.
---

# Systematic Debugging (SmartRFM)

> Port dari `obra/superpowers:systematic-debugging`, diadaptasi ke repo ini.

## The Iron Law

```
NO FIXES WITHOUT ROOT CAUSE INVESTIGATION FIRST
```

Belum selesai Fase 1 → tidak boleh mengusulkan fix. Fix gejala = kegagalan.

## Kapan Dipakai

UNTUK SEMUA masalah teknis:
- Test gagal (`composer test`, PHPUnit)
- Bug di halaman/API (dashboard, customers, upload, api/*)
- Perilaku tak terduga, error PDO, blank page, 500
- Masalah performa query / pagination
- Build/deploy (composer, nginx/php-fpm)

**Gunakan TERUTAMA saat:** tertekan waktu, "fix sekali saja kayaknya",
sudah coba beberapa fix, fix sebelumnya tidak berhasil, belum paham masalahnya.

## Fase 1 — Investigasi (WAJIB, sebelum fix)

1. **Reproduksi** — dapatkan langkah/input minimal yang memunculkan bug.
   Catat URL, POST data, user role, business_id.
2. **Kumpulkan bukti:**
   - `php -l <file>` — cek syntax (karena PHP plain, typo = fatal blank page)
   - `tail -50 /var/log/php*-fpm.log` / `error_log` — PHP 7.4+ menampilkan
     error di log (production) atau on-screen (dev)
   - `composer test` — apakah unit test menangkapnya? tambah test reproduksi
   - Cek apakah file terkait masih ada: `grep -rn "nama_file" --include="*.php" .`
     (rename/missing include adalah sumber blank page klasik)
   - Query DB: jalankan SQL yang sama di mysql client dengan nilai yang sama
3. **Trace root cause** — dari gejala ke akar. Tanya: mana lapisan yang
   memproduksi gejala ini? (HTML → handler → query → DB → data). Periksa
   satu per satu, jangan tebak.
4. **Tuliskan root cause** dalam satu kalimat, dengan bukti yang menunjuk.

## Fase 2 — Fix (baru setelah Fase 1)

1. Tulis **failing test** yang mereproduksi bug (skill: test-driven-development).
   Kalau bug di SQL/PHP murni → `tests/` + DB test.
2. Lihat test gagal karena alasan yang benar (fitur/root cause, bukan typo).
3. Implementasi minimal, scope bisnis terjaga, prepared statements, CSRF bila
   form/API (skill: csrf-safe-form).
4. Test hijau → `composer test` penuh → `php -l`.
5. Commit `fix(area): deskripsi root cause`.

## Root Cause vs Gejala

| Fix gejala (SALAH) | Fix akar (BENAR) |
|---|---|
| Tambah `@` / suppress error | Perbaiki query/typo penyebab error |
| Hardcode `business_id = 1` | Scope dari session (`getUserBusiness()`) |
| Buang `<script>` bermasalah tanpa tahu kenapa | Trace error console/js, fix sumber |
| Re-import ulang data saat import gagal | Perbaiki logika upsert/mapping kolom |
| `echo` data tanpa sanitasi | `htmlspecialchars` di titik output |

## Common Traps Repo Ini

- **Blank page** → biasanya fatal error PHP: `php -l` dulu, cek error log.
- **Test gagal karena DB salah** → pastikan `tests/bootstrap.php` set
  `DB_NAME=smart_marketing_rfm_test`; jangan pernah test ke DB produksi.
- **Pagination rusak** → cek `LIMIT/OFFSET` inline `(int)` cast (bukan placeholder).
- **403 CSRF** → form tanpa `csrf_field()` atau handler tanpa `requireCsrf()`.
- **RFM tidak sinkron** → PHP (`src/Rfm.php`) vs SQL (`includes/rfm.php` builder)
  divergen; cek `recalculateRFM` dan `App\Rfm::segmentFromScores()`.

## Checklist Sebelum Menyatakan Selesai

- [ ] Bug dapat direproduksi dengan input minimal
- [ ] Root cause teridentifikasi & dituliskan (satu kalimat + bukti)
- [ ] Ada test yang mereproduksi bug, dilihat gagal, lalu hijau
- [ ] `composer test` penuh hijau; `php -l` bersih
- [ ] Tidak ada fix gejala; tidak ada suppress error
- [ ] Commit dengan pesan `fix(area):` menyebut root cause

---
name: rfm-analysis
description: Recency-Frequency-Monetary analysis for this codebase (smartrfm.my.id). Use when working on RFM scoring/segmentation, recalculateRFM(), analysis.php, dashboard RFM cards, or RfmTest. src/Rfm.php is the single source of truth; SQL in includes/rfm.php is BUILT from it. Never recompute on page-load.
---

# RFM Analysis (SmartRFM)

## Overview

Skor & segmen RFM punya **single source of truth**: `src/Rfm.php` (`App\Rfm`).
SQL di `includes/rfm.php` **dibangun** dari fungsi `App\Rfm::*Sql()` — ubah
logika di SATU tempat (PHP class), SQL otomatis sinkron. Jangan pernah
menduplikasi CASE 3-skor di query manual.

**Iron law:** jangan jalankan DELETE+INSERT massal di setiap page-load.
Rekalkulasi hanya via tombol "Hitung Ulang RFM" (POST + CSRF) atau saat
first-run data kosong.

## File Map

| File | Peran |
|---|---|
| `src/Rfm.php` | Single source of truth: skor + segmentasi + builder SQL |
| `includes/rfm.php` | `recalculateRFM(\PDO $db, $businessId, $userId = null)` — persist ke `rfm_analysis` |
| `analysis.php`, `dashboard.php` | Membaca tabel `rfm_analysis` **langsung** (skor sudah dipersist) |
| `tests/RfmTest.php` | 125 kombinasi skor; kunci format skor/segmen |

## Public API `App\Rfm`

- `scoreRecency(int $daysSinceLastPurchase): int` → skor R
- `scoreFrequency(int $transactionCount): int` → skor F
- `scoreMonetary(float $averageAmount): int` → skor M
- `segmentFromScores(int $r, int $f, int $m): string` → segmen (Champions, Loyal, At Risk, ...)
- `recencyScoreSql(string $dateDiffExpr): string` → ekspresi skor R utk SQL
- `frequencyScoreSql(string $countExpr): string` → ekspresi skor F utk SQL
- `monetaryScoreSql(string $avgExpr): string` → ekspresi skor M utk SQL
- `segmentCaseSql(string $rExpr, string $fExpr, string $mExpr): string` → CASE segmen utk SQL

## Workflow saat mengubah perilaku RFM

1. Ubah logika di `src/Rfm.php` (skor / threshold / nama segmen).
2. Jangan sentuh SQL manual — `includes/rfm.php` sudah memakai builder.
3. Update/uji `tests/RfmTest.php` (kasus per kombinasi skor yang berubah).
4. Jalankan `composer test` (wajib hijau).
5. Test fungsional: `recalculateRFM($db, $businessId)` lalu cocokkan
   `rfm_segment` di `rfm_analysis` dengan `App\Rfm::segmentFromScores()`.
6. Update `analysis.php`/dashboard **hanya** jika tampilan segmen berubah
   (label/ikon/warna), bukan komputasinya.

## Constraints

- Semua query data bisnis wajib di-scope `business_id` dari session
  (`auth()->getUserBusiness()`), bukan input user.
- Prepared statements; output `htmlspecialchars()`.
- Rekalkulasi hanya via POST + `requireCsrf()` (tombol di `analysis.php`).
- `recalculateRFM()` harus tetap menerima `$userId` utk audit log.

## Red Flags — STOP

- Menulis CASE 3-skor manual di query baru → pakai `App\Rfm::segmentCaseSql()`.
- DELETE+INSERT `rfm_analysis` di page-load → itu regresi fase3.2.
- Mengubah skor di SQL tanpa mengubah `src/Rfm.php` → dua-duanya akan divergen.
- Test fungsional tidak cocok antara PHP & SQL → jangan lanjut sebelum sinkron.

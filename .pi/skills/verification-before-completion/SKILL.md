---
name: verification-before-completion
description: Evidence-based completion claims for this PHP codebase (smartrfm.my.id). Use before claiming work is done, fixed, or passing, and before committing/pushing. Requires running fresh verification commands (php -l, composer test, composer audit) and reading their output before making any success claim.
---

# Verification Before Completion (SmartRFM)

> Port dari `obra/superpowers:verification-before-completion`, diadaptasi
> ke perintah verifikasi repo ini (AGENTS.md §5).

## The Iron Law

```
NO COMPLETION CLAIMS WITHOUT FRESH VERIFICATION EVIDENCE
```

Kalau belum menjalankan perintah verifikasi di pesan ini, kamu tidak boleh
mengklaim berhasil.

## The Gate Function

Sebelum mengklaim status apa pun:

1. **IDENTIFY:** perintah apa yang membuktikan klaim ini?
2. **RUN:** jalankan perintah LENGKAP, fresh, di sesi ini.
3. **READ:** baca seluruh output, cek exit code, hitung failure.
4. **VERIFY:** apakah output mengonfirmasi klaim? Bila tidak → sebut status
   aktual dengan bukti. Bila ya → klaim DENGAN bukti.
5. **ONLY THEN:** baru klaim selesai.

Melewatkan satu langkah = berbohong, bukan memverifikasi.

## Perintah Verifikasi Repo Ini

| Klaim | Perintah | Bukti yang Diperlukan |
|---|---|---|
| PHP valid | `find . -path ./vendor -prune -o -name '*.php' -print0 \| xargs -0 -n1 php -l` | 0 error |
| Unit test hijau | `COMPOSER_ALLOW_SUPERUSER=1 composer test` | OK (N tests, M assertions) |
| RFM PHP vs SQL sinkron | `recalculateRFM($db, 1)` → cocokkan `rfm_segment` dgn `App\Rfm::segmentFromScores()` | cocok semua baris |
| Tidak ada CVE | `COMPOSER_ALLOW_SUPERUSER=1 composer audit` | 0 advisory |
| Dependency valid | `composer validate --no-check-publish` | valid |
| Bug ter-fix | Jalankan ulang gejala asli (test/curl) | pass |
| Tidak ada perubahan liar | `git status --short` | hanya file yang dimaksud |
| Tidak ada rahasia ter-commit | `git diff` / `git log -p \| grep sk-` | kosong |
| Commit selesai | `git log --oneline -1` | pesan sesuai konvensi |

## Common Failures

| Klaim | Tidak Cukup |
|---|---|
| Test pass | Hasil run sebelumnya, "seharusnya pass" |
| Lint bersih | Cek parsial, ekstrapolasi |
| Bug fixed | Kode diubah lalu diasumsikan fix |
| Selesai | Agent melaporkan "sukses" tanpa bukti |
| Requirement terpenuhi | Checklist tanpa menjalankan test |

## Red Flags — STOP

- Menggunakan "seharusnya", "mungkin", "kayaknya"
- Puas sebelum verifikasi ("Mantap!", "Selesai!", "Done!")
- Mau commit/push tanpa verifikasi
- Mempercayai laporan sukses agen/executor
- Verifikasi parsial lalu lanjut

## Pengecekan Pre-Commit (AGENTS.md §5)

Sebelum `git commit`:
1. `php -l` semua file PHP (kecuali vendor).
2. `composer test` hijau (bootstrap sudah arahkan ke DB test).
3. `composer audit` = 0 CVE (jangan turunkan phpspreadsheet < 1.30.6).
4. `git status --short` — hanya file unit kerja ini.
5. Pastikan `.env`, `config/database.php`, `config/openai.php` TIDAK masuk commit.
6. Satu commit = satu unit kerja; push `git push origin main`.

---
name: test-driven-development
description: Test-first development for this PHP codebase (smartrfm.my.id). Use when implementing any feature or bugfix before writing implementation code. Enforces PHPUnit 9.6 red-green-refactor, php -l lint, DB test smart_marketing_rfm_test, and the rule that production code requires a failing test first.
---

# Test-Driven Development (SmartRFM)

> Port dari `obra/superpowers:test-driven-development`, diadaptasi ke
> PHPUnit 9.6 + DB test `smart_marketing_rfm_test`.

## The Iron Law

```
NO PRODUCTION CODE WITHOUT A FAILING TEST FIRST
```

Nulis kode sebelum test? Hapus. Mulai ulang dari test. Tidak ada pengecualian.

## Kapan Dipakai

**Selalu:** fitur baru, bugfix, refactor, perubahan perilaku.
**Kecuali (tanya user):** prototype sekali pakai, kode generated, file konfigurasi.

## RED — Tulis Test yang Gagal

Satu perilaku, nama jelas, tes perilaku nyata (hindari mock kecuali perlu).

```php
// tests/RfmTest.php (pola)
public function testAtRiskSegment(): void
{
    $this->assertSame('At Risk', App\Rfm::segmentFromScores(4, 2, 2));
}
```

## Verify RED — Wajib, Jangan Dilewati

```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/RfmTest.php
```

Pastikan:
- Gagal (bukan error), pesan kegagalan sesuai dugaan
- Gagal karena fitur belum ada, bukan karena typo
- **Test langsung pass?** Kamu menguji perilaku yang sudah ada → perbaiki test.

## GREEN — Kode Minimal

Tulis kode paling sederhana agar test pass. Jangan over-engineer, jangan
menambah fitur di luar test, jangan refactor kode lain.

## Verify GREEN — Wajib

```bash
COMPOSER_ALLOW_SUPERUSER=1 vendor/bin/phpunit tests/RfmTest.php
# lalu seluruh suite:
composer test   # (bootstrap set DB_NAME=smart_marketing_rfm_test; jangan pernah ke DB produksi)
```

Pastikan: pass, test lain tetap pass, output bersih (tanpa warning/error).

**Test gagal?** Perbaiki kode, bukan test. **Test lain ikut gagal?** Perbaiki sekarang.

## REFACTOR

Setelah green: hapus duplikasi, perbaiki nama, ekstrak helper (ke `src/`
untuk logika murni, ke `includes/` untuk helper halaman). Test tetap hijau.

## Lint Sebelum Commit

```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Catatan Test DB

- `tests/bootstrap.php` set `DB_NAME=smart_marketing_rfm_test` **sebelum**
  config dimuat — jangan pernah jalankan test ke DB produksi.
- Schema berubah → refresh test DB:
  ```bash
  mysql -u root -e "DROP DATABASE IF EXISTS smart_marketing_rfm_test; CREATE DATABASE smart_marketing_rfm_test CHARACTER SET utf8mb4;"
  for f in database_schema.sql database_update.sql database_indexes.sql; do sed '/^USE /d' "$f" | mysql -u root smart_marketing_rfm_test; done
  ```

## Rationalization — Realita

| Alasan | Realita |
|---|---|
| "Terlalu sederhana" | Kode sederhana tetap bisa rusak; test butuh 30 detik |
| "Saya test setelahnya" | Test pasca-implementasi langsung pass = tidak membuktikan apa-apa |
| "Sudah tes manual" | Manual tidak tercatat, tidak bisa diulang, mudah lupa kasus |
| "TDD memperlambat" | TDD justru jalur pragmatis: tangkap bug sebelum commit |
| "Kode existing tanpa test" | Justru kamu yang memperbaikinya — tambahkan test |

## Red Flags — STOP & Mulai Ulang

- Kode sebelum test / test setelah implementasi
- Test langsung pass tanpa pernah gagal
- Tidak bisa menjelaskan kenapa test gagal
- "TDD dogmatis, saya pragmatis" / "sekali ini saja"
- "Simpan sebagai referensi" → kamu akan mengadaptasinya = testing after. Hapus berarti hapus.

## Checklist Selesai

- [ ] Setiap fungsi/metode baru punya test
- [ ] Setiap test dilihat gagal dulu sebelum implementasi
- [ ] Gagal karena alasan yang diharapkan (fitur hilang, bukan typo)
- [ ] Kode minimal untuk pass
- [ ] `composer test` hijau (27+ test / 500+ asersi, output bersih)
- [ ] `php -l` bersih untuk semua file yang disentuh
- [ ] Edge case & error covered

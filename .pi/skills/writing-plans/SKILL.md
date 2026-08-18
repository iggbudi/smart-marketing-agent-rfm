---
name: writing-plans
description: Writes implementation plans for multi-step tasks in this PHP codebase (smartrfm.my.id). Use when you have a spec/requirements for a feature or refactor that spans multiple files or commits, before touching code. Produces bite-sized tasks with exact file paths, actual code, test steps, and one commit per task per AGENTS.md.
---

# Writing Plans (SmartRFM)

> Port dari metodologi `obra/superpowers:writing-plans`, diadaptasi ke
> konvensi repo ini (AGENTS.md): plain PHP 7.4, PHPUnit 9.6, satu commit =
> satu unit kerja, migrasi SQL manual.

## Overview

Tulis rencana implementasi berasumsi engineer punya **nol konteks** tentang
codebase ini: sebut file persis yang disentuh, kode, cara test, commit.
DRY. YAGNI. TDD. Commit kecil-kecil.

**Context:** Spec = `RENCANA_PERBAIKAN.md` (bila lanjut itemnya), deskripsi
fitur, atau issue. Jangan mulai nulis kode sebelum plan disetujui.

**Simpan plan ke:** `docs/plans/YYYY-MM-DD-<nama-fitur>.md`

## Scope Check

Kalau spec mencakup beberapa subsistem independen → pecah jadi beberapa plan,
satu plan per subsistem. Setiap plan harus menghasilkan software yang
berjalan & teruji sendiri-sendiri.

## Struktur File Dulu

Sebelum definisi task, petakan file yang dibuat/diubah dan tanggung jawabnya.
Di codebase ini, ikuti pola yang sudah ada — jangan restrukturisasi sepihak:

- Halaman baru → tiru pola `customers.php` (lihat skill `csrf-safe-form`).
- Logika murni → `src/` (PSR-4 `App\`), contoh `src/Rfm.php`.
- Logika terpusat → perpanjang `includes/` yang ada (rfm, export, import, pagination, sidebar).
- Test → `tests/` (PHPUnit 9.6, bootstrap arahkan ke DB `smart_marketing_rfm_test`).
- Migrasi DB → file `database_xxx.sql` baru (sertakan `USE smart_marketing_rfm;` + cara apply via `sed '/^USE /d'`).

## Format Plan

```markdown
# [Nama Fitur] Implementation Plan

**Goal:** [satu kalimat]
**Architecture:** [2-3 kalimat]
**Tech Stack:** PHP 7.4+, PDO, MariaDB, PhpSpreadsheet 1.30.6+, PHPUnit 9.6
**Spec:** [path ke spec — plan harus bisa diargumenkan dari spec]

## Global Constraints
- Satu commit = satu unit kerja (AGENTS.md §6, prefix konvensional: feat/fix/refactor/test/docs/security/chore)
- Semua form POST: CSRF (skill: csrf-safe-form)
- Prepared statements + htmlspecialchars + scope business_id
- `composer test` hijau sebelum commit; jangan commit rahasia (.env, config/*.php)
```

### Task N: [Nama Komponen]

**Files:**
- Create: `exact/path/file.php`
- Modify: `exact/path/file.php:123-145`
- Test: `tests/ExactTest.php`

**Interfaces:**
- Consumes: [signature dari task sebelumnya]
- Produces: [nama fungsi + tipe yang dipakai task berikutnya]

- [ ] **Step 1: Tulis test yang gagal** — kode test aktual
- [ ] **Step 2: Jalankan & pastikan gagal** — `vendor/bin/phpunit tests/XxxTest.php` → FAIL karena fitur belum ada
- [ ] **Step 3: Implementasi minimal** — kode aktual
- [ ] **Step 4: Jalankan & pastikan pass** — PASS
- [ ] **Step 5: Lint & test penuh** — `php -l` file + `composer test`
- [ ] **Step 6: Commit** — `git add ... && git commit -m "feat: ..."`
```

## No Placeholders

Setiap step harus berisi konten aktual. Ini PLAN FAILURE, jangan pernah:
- "TBD", "TODO", "implement later"
- "Tambahkan validasi/error handling" tanpa kode
- "Tulis test untuk di atas" tanpa kode test
- "Mirip Task N" — ulangi kodenya, engineer bisa baca task out of order
- Referensi fungsi/tipe yang tidak didefinisikan di task mana pun

## Self-Review (sebelum handoff)

1. **Coverage spec:** tiap kebutuhan spec → bisa tunjuk ke task mana? Daftar gap-nya.
2. **Scan placeholder:** cari pola "No Placeholders" di atas, perbaiki.
3. **Konsistensi tipe:** nama fungsi/properti di task akhir harus sama dengan
   yang didefinisikan di task awal (typofix: `clearLayers()` vs `clearFullLayers()`).

Temuan → perbaiki inline, langsung.

## Handoff Eksekusi

Setelah plan disimpan, tawarkan eksekusi:
- **Inline (disarankan utk repo ini):** eksekusi task-by-task di sesi ini,
  ikuti step checkbox, satu commit per task, checkpoint setelah tiap task.
- Task selesai → verifikasi dengan skill `verification-before-completion`.

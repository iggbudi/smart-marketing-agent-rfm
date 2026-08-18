# AGENTS.md — Pedoman Pengerjaan Plan

> Panduan ini adalah kontrak kerja untuk semua agen (AI/human) yang mengerjakan
> **`RENCANA_PERBAIKAN.md`** pada repo **smartrfm.my.id** (Smart Marketing Agent / RFM).

---

## 1. Tujuan

Membawa aplikasi dari kondisi MVP menuju **production-ready** secara bertahap
sesuai 4 fase di `RENCANA_PERBAIKAN.md`:

- **Fase 1** — Perbaikan Kritis (keamanan API, bersihkan file debug, bugfix)
- **Fase 2** — Hardening Keamanan (CSRF, session, upload, header keamanan)
- **Fase 3** — Fungsionalitas & Kualitas Kode (Excel real, refactor RFM, pagination)
- **Fase 4** — Testing & Dokumentasi (PHPUnit, kebersihan repo, docs)

Selalu kerjakan **berurutan** kecuali ada instruksi eksplisit lain.

---

## 2. Kontrak Kerja per Tahap (WAJIB)

Setiap tahap/unit kerja **harus** melalui alur berikut secara berurutan,
**di satu tahap selesai sebelum pindah ke tahap berikutnya**:

1. **Pahami** — baca item yang relevan di `RENCANA_PERBAIKAN.md` dan kode terkait.
2. **Kerjakan** — implementasikan perubahan dengan mengikuti konvensi kode repo
   (PHP 7.4+, PDO + prepared statements, `htmlspecialchars` untuk output, dsb).
3. **Test lokal** — pastikan tidak merusak apa pun:
   - `php -l` (lint) untuk semua file yang diubah.
   - Jalankan fungsi/script yang memungkinkan di CLI bila tidak butuh browser.
4. **Update docs** — perbarui:
   - Centang `[ ]` → `[x]` pada item yang telah selesai di `RENCANA_PERBAIKAN.md`.
   - `README.md` / `docs/*` jika ada yang berubah secara behaviour atau setup.
   - File deskriptif fitur (`*_SUMMARY.md`) bila relevan.
5. **Commit** — gunakan pesan commit ber-prefix **tahap** (lihat §4).
6. **Push** — `git push origin main` setelah commit sukses.

**Aturan keras:**
- JANGAN commit sekaligus berbilang tahap. Satu commit = satu tahap/unit kerja.
- JANGAN pindah ke tahap berikutnya sebelum tahap sekarang sudah di-commit & di-push.
- JANGAN commit file rahasia: `config/database.php`, `config/openai.php`,
  `.env`, `.env.*.local` (sudah di-`.gitignore`). Hanya commit `*.example.php`.
- JANGAN menghapus/merefactor sesuatu di luar lingkup tahap berjalan.

---

## 3. Local Testing

### 3.1 Lint PHP (wajib tiap tahap)
```bash
find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```
atau per file:
```bash
php -l path/to/file.php
```

### 3.2 Cek dependensi
```bash
composer validate --no-check-publish
composer show            # lihat versi phpspreadsheet dst.
```

### 3.3 Unit test (setelah Fase 4 mengaktifkan PHPUnit)
```bash
composer test
```

> Catatan: folder `src/` (PSR-4 `App\`) dan `tests/` belum ada dan akan dibuat
> pada Fase 4. Jika entorno tak punya MySQL aksesible, gunakan `php -l` +
> review manual + `composer validate` sebagai test minimum.

---

## 4. Konvensi Commit

Format: `[Tahap] singkatan deskripsi`

| Area | Contoh |
|------|--------|
| Docs/workflow | `docs: add AGENTS.md pengerjaan rotatif` |
| Fase 1 | `fase1: amankan endpoint /api` , `fase1: hapus file debug dari web root` |
| Fase 1 bugfix | `fix(upload-excel): ganti kolom WHERE upload_id -> id` |
| Fase 2 | `fase2: tambah CSRF helper & token pada semua form` |
| Fase 3 | `fase3: implementasi upload excel via PhpSpreadsheet` |
| Fase 4 | `fase4: pasang PHPUnit + composer test` |

Gunakan git config lokal yang sudah diset:
```bash
git config user.name  "iggbudi"
git config user.email "iggbudi@gmail.com"
```

---

## 5. Pedoman Keamanan yang Wajib Dipertahankan

- Hanya **prepared statements** (PDO) untuk query dengan input dinamis.
- Output semua data user lewat `htmlspecialchars()` (anti-XSS).
- Endpoint `/api/*` harus **wajib autentikasi**, mengembalikan **JSON + HTTP
  status yang benar** (401/403), bukan redirect HTML.
- Data bisnis hanya boleh diakses oleh pemiliknya (`getUserBusiness()`), tolak
  akses lintas-bisnis.
- Jangan pernah menyimpan kredensial/API key di dalam file yang di-commit.
- File `debug_*`, `check_*`, `fix_*`, `test*`, `generate_*` **tidak boleh** bisa
  diakses via URL public.

---

## 6. Definisi Selesai (DoD) — gate sebelum menandai tahap selesai

- [ ] Semua endpoint `/api/*` menolak request tanpa session valid (401/403 JSON).
- [ ] Tidak ada file rahasia/debug yang bisa diakses via URL.
- [ ] Semua form POST verifikasi CSRF.
- [ ] `session_regenerate_id()` dipanggil saat login.
- [ ] Upload Excel benar-benar membaca file user sesuai business.
- [ ] RFM dihitung hanya saat diminta eksplisit.
- [ ] `composer test` hijau, dashboard duplikat hilang, pagination aktif.

---

## 7. Urutan Eksekusi (ikut RENCANA_PERBAIKAN.md)

1. **Fase 1.1 + 1.2** — amankan API & bersihkan file debug.
2. **Fase 1.3 + 2.1 + 2.2** — bugfix upload + CSRF + session.
3. **Fase 3.1** — upload Excel fungsional.
4. **Fase 3.2 + 3.3** — refactor RFM & hapus duplikasi.
5. **Fase 4** — testing, kebersihan repo, dokumentasi.

Setiap langkah wajib melewati alur §2 (test → docs → commit → push).
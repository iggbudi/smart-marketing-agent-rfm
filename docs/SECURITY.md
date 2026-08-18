# SECURITY.md — Kebijakan Keamanan Smart Marketing Agent

> Ringkasan kontrol keamanan yang aktif, kebijakan session, dan prosedur rotasi
> kredensial. Berlaku untuk deployment di `smartrfm.my.id` (Linux/Nginx/PHP-FPM)
> dan lingkungan lain. Kontrol diimplementasikan bertahap sejak Fase 1–2
> (lihat `RENCANA_PERBAIKAN.md`).

---

## 1. Header Keamanan

Diterapkan di `config/auth.php` (berlaku ke semua halaman yang memuatnya):

| Header | Nilai | Tujuan |
|---|---|---|
| `X-Frame-Options` | `DENY` | Cegah clickjacking (iframe lintas domain) |
| `X-Content-Type-Options` | `nosniff` | Cegah MIME sniffing browser |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Batasi info referrer keluar |

Belum aktif (didefer): **CSP** — banyak halaman memakai inline `<script>` + CDN
(Bootstrap/FA/Chart.js/DataTables); refactor bertahap diperlukan agar tidak
merusak fungsionalitas (lihat RENCANA 2.4).

## 2. Kebijakan Session

- Cookie session: `HttpOnly`, `Secure` (saat HTTPS), `SameSite=Lax`
  (diset via `session_set_cookie_params()` di `config/auth.php`).
- `session_regenerate_id(true)` dipanggil setelah **login sukses** (anti session fixation).
- Token session disimpan di tabel `user_sessions` dengan `expires_at` (+24 jam);
  `AuthManager::isLoggedIn()` memvalidasi token vs DB (`expires_at > NOW()`).
- Logout menghapus baris token dari DB + `session_destroy()`.
- Didefer (opsional): mengunci `user_sessions` ke IP/User-Agent — berisiko
  menendang user dengan IP dinamis (RENCANA 2.2).

## 3. CSRF

- Helper `csrf_token()` / `csrf_field()` / `requireCsrf()` di `config/auth.php`.
- **Semua form POST** menyertakan token tersembunyi; handler memverifikasi via
  `requireCsrf()` — gagal = HTTP 403 fail-fast.
- Verifikasi memakai `hash_equals()` (constant-time).

## 4. Autentikasi API (`/api/*`)

- Semua endpoint `requireAuthJson()` sebelum memproses request.
- Belum login → **HTTP 401 JSON** (bukan redirect HTML).
- Role tidak diizinkan → **HTTP 403 JSON**.
- Data bisnis hanya untuk pemiliknya: `auth()->getUserBusiness($userId)`;
  tolak akses lintas-bisnis.

## 5. SQL Injection & XSS

- Semua query dengan input dinamis memakai **PDO prepared statements**.
- Output data user selalu lewat `htmlspecialchars()`.
- `LIMIT/OFFSET` pagination di-inline dengan `(int)` cast (bukan placeholder)
  karena PDO meng-quote placeholder LIMIT sebagai string.

## 6. Upload File

- Ekstensi + MIME diverifikasi via `finfo_file()` (bukan `$_FILES['type']`).
- Batas ukuran **5 MB**; file di-rename acak (bukan nama user).
- Disimpan di `storage/uploads/` dengan proteksi:
  - Apache: `storage/uploads/.htaccess` (`Options -Indexes` + blokir eksekusi PHP).
  - Nginx: rule `deny` untuk eksekusi PHP di `storage/uploads/` (lihat README, bagian deployment Linux).

## 7. Kredensial & Rotasi

- **Jangan pernah commit** kredensial: `config/database.php`, `config/openai.php`,
  `.env`, `.env.*.local` sudah di `.gitignore`.
- Kredensial DB & OpenAI dibaca dari environment (prioritas: env var > `.env` > default)
  via `config/env.php` — lihat `.env.example`.
- API key OpenAI harus ber-prefix `sk-` dan di-rotasi jika pernah bocor.

### Prosedur rotasi kredensial DB (user `smartrfm_user`)
```bash
sudo mysql
ALTER USER 'smartrfm_user'@'localhost' IDENTIFIED BY '<password-baru-kuat>';
FLUSH PRIVILEGES;
exit
# Update .env (jangan commit):
sed -i 's|^DB_PASSWORD=.*|DB_PASSWORD=<password-baru-kuat>|' /var/www/smartrfm.my.id/.env
# Verifikasi:
php -r "require '/var/www/smartrfm.my.id/config/database.php'; getDB(); echo 'DB OK' . PHP_EOL;"
```
Jika password lama pernah terekspos (mis. di file debug/history), rotasi wajib
dilakukan — riwayat rotasi: Fase 1.4 (RENCANA_PERBAIKAN.md).

## 8. Checklist Hardening Deployment

- [ ] `composer install --no-dev --optimize-autoloader` (tanpa PHPUnit di produksi)
- [ ] HTTPS aktif (Let's Encrypt) — cookie `Secure` hanya efektif via HTTPS
- [ ] Nginx: deny akses `config/`, `includes/`, `src/`, `tests/`, `storage/`, `.env`
- [ ] Tidak ada file `debug_*`, `check_*`, `fix_*`, `test*`, `generate_*` di docroot
- [ ] User DB terpisah (bukan root) dengan hak hanya ke database aplikasi
- [ ] `composer audit` = 0 advisory (saat ini: phpspreadsheet 1.30.6, 9 CVE tertutup)
- [ ] Session cookie `HttpOnly` + `Secure` + `SameSite=Lax` terverifikasi di browser
- [ ] Rotasi kredensial terjadwal / setelah insiden

## 9. Pelaporan Kerentanan

Laporkan kerentanan ke pemilik repository (lih. README bagian Kontak). Jangan
membuka isu publik dengan detail exploit sebelum ada perbaikan.

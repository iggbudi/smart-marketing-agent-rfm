# Smart Marketing Agent - Platform Documentation

## Overview
Smart Marketing Agent adalah platform komprehensif untuk analisis RFM (Recency, Frequency, Monetary) yang dirancang khusus untuk UMKM Indonesia. Platform ini menyediakan sistem multi-user dengan peran Super Admin dan UMKM Owner.

## Features

### 🔐 Authentication System
- **Multi-role Authentication**: Super Admin dan UMKM Owner
- **Session Management**: Secure session handling with timeout
- **Activity Logging**: Track all user activities
- **Password Security**: Encrypted passwords with strength requirements

### 👥 User Management (Super Admin)
- Create, edit, and delete user accounts
- Assign roles and manage permissions
- View user activity logs
- Business assignment management

### 🏢 Business Management
- **For Super Admin**: Manage all businesses across the platform
- **For UMKM Owner**: Manage their own business data
- Business categorization and contact information
- Owner assignment and access control

### 📊 RFM Analysis
- **Recency**: Days since last purchase
- **Frequency**: Number of transactions
- **Monetary**: Total amount spent
- **Segmentation**: Automatic customer categorization
  - Champions
  - Loyal Customers
  - Potential Loyalists
  - New Customers
  - Promising
  - Customers Needing Attention
  - About to Sleep
  - At Risk

### 📈 Analytics & Reports
- **Dashboard**: Key metrics and visualizations
- **Business Analytics**: Performance tracking per business
- **Customer Insights**: RFM segment distribution
- **Transaction Reports**: Financial analysis
- **Export Options**: CSV export for all reports

### 🔧 Admin Panel Features
- **User Management**: Complete CRUD operations
- **Business Management**: Assign owners and manage details
- **System Analytics**: Platform-wide statistics
- **API Management**: Monitor API usage and performance
- **System Settings**: Configure platform parameters
- **Reports**: Generate various business reports

## Installation

### Prerequisites
- XAMPP (Apache, MySQL, PHP 7.4+)
- Web browser (Chrome, Firefox, Safari)

### Setup Steps

1. **Install XAMPP**
   ```
   Download and install XAMPP from https://www.apachefriends.org/
   ```

2. **Clone/Copy Project**
   ```
   Copy project files to d:\xampp\htdocs\smart\
   ```

3. **Database Setup**
   ```
   Start XAMPP Apache and MySQL services
   Import database.sql using phpMyAdmin
   Or run the setup script: php setup_database.php
   ```

4. **Configure Database**
   ```
   Edit config/database.php if needed
   Default settings work with XAMPP
   ```

5. **Access Platform**
   ```
   Landing Page: http://localhost/smart/
   Admin Panel: http://localhost/smart/admin/
   ```

### Linux Production Deployment (Nginx + PHP-FPM + MariaDB)

> Instruksi ini untuk server Linux (Debian/Ubuntu). Server live `smartrfm.my.id`
> memakai pola ini. Sertakan `--no-dev` agar PHPUnit tidak terpasang di produksi.

1. **Dependency:**
   ```bash
   sudo apt update
   sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-curl php8.3-xml php8.3-mbstring php8.3-zip mariadb-server composer git
   ```

2. **Clone & instal dependensi:**
   ```bash
   cd /var/www
   git clone https://github.com/iggbudi/smart-marketing-agent-rfm.git smartrfm.my.id
   cd smartrfm.my.id
   composer install --no-dev --optimize-autoloader
   ```

3. **Database (buat user terpisah, jangan root):**
   ```bash
   sudo mysql
   CREATE DATABASE smart_marketing_rfm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'smartrfm_user'@'localhost' IDENTIFIED BY '<password-kuat>';
   GRANT ALL PRIVILEGES ON smart_marketing_rfm.* TO 'smartrfm_user'@'localhost';
   FLUSH PRIVILEGES;
   exit

   mysql -u root smart_marketing_rfm < database_schema.sql
   mysql -u root smart_marketing_rfm < database_update.sql
   mysql -u root smart_marketing_rfm < database_indexes.sql
   ```
   > Catatan: `database_update.sql` & `database_indexes.sql` berisi `USE smart_marketing_rfm;`.
   > Jika nama DB Anda berbeda, hapus baris `USE` tersebut (`sed '/^USE /d' file.sql | mysql -u root nama_db`).

4. **Environment (kredensial via env var / .env, lihat `config/env.php`):**
   ```bash
   cp .env.example .env
   nano .env        # isi DB_HOST/DB_USER/DB_PASSWORD, OPENAI_API_KEY (opsional)
   ```

5. **Nginx vhost (`/etc/nginx/sites-available/smartrfm`):**
   ```nginx
   server {
       listen 80;
       server_name smartrfm.my.id www.smartrfm.my.id;
       root /var/www/smartrfm.my.id;
       index index.php;

       location / { try_files $uri $uri/ =404; }

       # PHP-FPM
       location ~ \.php$ {
           include snippets/fastcgi-php.conf;
           fastcgi_pass unix:/run/php/php8.3-fpm.sock;
       }

       # Larangan akses: config, includes, src, tests, storage, vendor, .env
       location ~ ^/(config|includes|src|tests|storage|vendor|scripts)/ { deny all; }
       location ~ /\.env$ { deny all; }

       # Upload tidak boleh dieksekusi PHP (untuk Apache sudah via storage/uploads/.htaccess)
       location ~ ^/storage/uploads/.*\.(php|phtml|php[0-9]|phar)$ { deny all; }
   }
   ```
   Lalu:
   ```bash
   sudo ln -s /etc/nginx/sites-available/smartrfm /etc/nginx/sites-enabled/
   sudo nginx -t && sudo systemctl reload nginx
   ```

6. **HTTPS (wajib di produksi):**
   ```bash
   sudo apt install -y certbot python3-certbot-nginx
   sudo certbot --nginx -d smartrfm.my.id -d www.smartrfm.my.id
   ```

7. **Cek instalasi:**
   ```bash
   cd /var/www/smartrfm.my.id
   find . -path ./vendor -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l   # lint semua
   mysql -u root -e "SELECT VERSION();" smart_marketing_rfm
   ```
   Buka https://smartrfm.my.id/login.php dan login sebagai Super Admin/UMKM Owner.

8. **Hardening checklist** — lihat `docs/SECURITY.md`.

## Default Accounts

### Super Admin
- **Email**: admin@smartmarketing.local
- **Password**: password123
- **Access**: Full platform administration

### UMKM Owner (Demo)
- **Email**: budi@batiksemarang.com
- **Password**: password123
- **Access**: Business-specific data only

> Kredensial aktual diambil dari tabel `users` DB live (kedua akun ber-hash sama).

## File Structure

```
smart/
├── index.php                  # Landing page
├── login.php                 # Authentication
├── logout.php                # Session termination
├── dashboard.php             # UMKM Owner dashboard
├── 
├── admin/                    # Admin panel
│   ├── dashboard.php         # Admin dashboard
│   ├── users.php            # User management
│   ├── businesses.php       # Business management
│   ├── analytics.php        # Platform analytics
│   ├── api-management.php   # API monitoring
│   ├── settings.php         # System settings
│   └── reports.php          # Report generation
├── 
├── config/                   # Configuration files
│   ├── database.php         # Database connection
│   ├── auth.php            # Authentication functions
│   └── session.php         # Session management
├── 
├── assets/                   # Static assets
│   ├── landing.css         # Stylesheet landing page (index.php)
│   ├── landing.js          # Interaksi landing page (index.php)
│   └── user-styles.css     # Stylesheet dashboard user
├── 
├── includes/                # Shared components
│   ├── header.php          # Common header
│   ├── footer.php          # Common footer
│   └── navigation.php      # Navigation menu
└── 
└── database.sql             # Database schema
```

## Database Schema

### Tables Overview

1. **users** - User accounts and authentication
2. **businesses** - Business information and ownership
3. **customers** - Customer data per business
4. **transactions** - Transaction records
5. **rfm_analysis** - RFM analysis results
6. **user_sessions** - Active user sessions
7. **activity_logs** - User activity tracking
8. **api_usage_logs** - API monitoring data
9. **system_settings** - Platform configuration

### Key Relationships

- Users → Businesses (One-to-Many)
- Businesses → Customers (One-to-Many)
- Customers → Transactions (One-to-Many)
- Customers → RFM Analysis (One-to-One)

## API Endpoints

### Authentication
```
POST /api/login          # User authentication
POST /api/logout         # Session termination
```

### Business Data
```
GET /api/customers       # Retrieve customers
POST /api/customers      # Add new customer
PUT /api/customers/{id}  # Update customer
DELETE /api/customers/{id} # Delete customer
```

### Analytics
```
GET /api/rfm-analysis    # Get RFM data
GET /api/reports         # Generate reports
GET /api/analytics       # Platform analytics
```

## Security Features

> 📄 Kebijakan lengkap (header, session, CSRF, upload, rotasi kredensial, checklist
> hardening): [`docs/SECURITY.md`](docs/SECURITY.md)

### Authentication
- Bcrypt password hashing
- Session-based authentication
- Role-based access control
- Activity logging
- Login attempt limiting

### Data Protection
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- CSRF protection (token validation)
- Data isolation by business

### Session Management
- Secure session handling
- Configurable session timeout
- Session regeneration
- Concurrent session control

## Configuration Options

### System Settings (via Admin Panel)

1. **General Settings**
   - Platform name and description
   - Contact information
   - Language and timezone
   - Maintenance mode

2. **Email Settings**
   - SMTP configuration
   - Email templates
   - Notification settings

3. **Security Settings**
   - Session timeout
   - Password requirements
   - Login attempt limits
   - Two-factor authentication

## Performance Optimization

### Database
- Indexed columns for fast queries
- Optimized RFM calculation queries
- Efficient joins and aggregations

### Frontend
- Bootstrap 5 for responsive design
- Chart.js for visualizations
- DataTables for large datasets
- Lazy loading for images
- **Tampilan mobile khusus (segmen UMKM Indonesia):** satu identitas warna
  hijau-teal + amber (design tokens `--brand-*` di semua stylesheet), top bar
  sticky dengan hamburger di semua halaman user (`includes/mobile-topbar.php`),
  sidebar overlay + backdrop (`assets/mobile.js`), bottom navigation 5 menu
  (`includes/bottom-nav.php`), stats card 2 kolom, tabel Customers & Transactions
  berubah jadi kartu di layar ≤575px (`assets/table-cards.js`), FAB tambah cepat,
  modal bottom-sheet, dan label berbahasa Indonesia. Data tables lebar tetap
  bisa di-scroll horizontal (DataTables `scrollX`), input 16px anti auto-zoom iOS.

### Backend
- Prepared statements for security and performance
- Connection pooling
- Query optimization
- Caching strategies

## Monitoring & Analytics

### Platform Metrics
- User activity tracking
- Business performance monitoring
- API usage statistics
- System health indicators

### Reports Available
- User activity reports
- Business performance reports
- Transaction analysis
- RFM segment distribution
- API usage reports

## Maintenance

### Regular Tasks
1. **Database Cleanup**
   - Archive old transactions
   - Clean activity logs
   - Optimize database tables

2. **Security Updates**
   - Update passwords regularly
   - Review user permissions
   - Monitor suspicious activities

3. **Performance Monitoring**
   - Check query performance
   - Monitor server resources
   - Review error logs

### Backup Procedures
- Database backup via admin panel
- File system backup
- Configuration backup
- Regular restore testing

## Troubleshooting

### Common Issues

1. **Login Problems**
   - Check credentials
   - Verify user account status
   - Clear browser cache

2. **Database Errors**
   - Check connection settings
   - Verify table structure
   - Review error logs

3. **Performance Issues**
   - Check server resources
   - Optimize database queries
   - Clear system cache

### Error Logs
- Application logs in `/logs/`
- Database error logs
- Web server error logs
- PHP error logs

## Support & Contact

For technical support or questions:
- Email: admin@smartmarketing.local
- Documentation: Available in platform
- GitHub: [Repository URL]

## License

This platform is developed for educational and business purposes.
Please ensure compliance with local data protection regulations.

---

**Version**: 1.0
**Last Updated**: December 2024
**Maintained by**: Smart Marketing Agent Team
- OpenAI API Key (opsional untuk fitur AI)

## Langkah-langkah Setup

### 1. **Persiapan XAMPP**
```bash
# Pastikan XAMPP sudah terinstall
# Start Apache dan MySQL melalui XAMPP Control Panel
```

### 2. **Setup Database**
1. Buka phpMyAdmin (http://localhost/phpmyadmin)
2. Buat database baru: `smart_marketing`
3. Import file: `database_schema.sql`, lalu `database_update.sql` (multi-user) dan `database_indexes.sql` (index performa Fase 3.4)

```sql
# Atau jalankan manual:
mysql -u root -p
CREATE DATABASE smart_marketing;
USE smart_marketing;
SOURCE database_schema.sql;
SOURCE database_update.sql;
SOURCE database_indexes.sql;
```

### 3. **Konfigurasi Kredensial (Environment)**

Kredensial DB & OpenAI dibaca dari **environment** dengan prioritas:
`env var` (SetEnv Apache / systemd / export shell) > file `.env` > default di kode
(lihat `config/env.php`, Fase 3.5).

1. Salin contoh: `cp .env.example .env`
2. Isi nilai asli di `.env` — file ini, `config/database.php`, dan `config/openai.php`
   **tidak di-commit** (sudah di `.gitignore`):

```ini
# .env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=smart_marketing_rfm
DB_USER=root
DB_PASSWORD=password_anda

OPENAI_API_KEY=sk-...       # opsional, untuk fitur AI content
OPENAI_MODEL=gpt-3.5-turbo
OPENAI_BASE_URL=https://api.openai.com/v1/chat/completions
```

3. Alternatif tanpa `.env` — set environment variable langsung:
   - **Apache vhost/.htaccess**: `SetEnv DB_USER root`
   - **systemd unit**: `Environment=DB_USER=root`
   - **CLI/cron**: `export DB_USER=root` sebelum menjalankan PHP

> `config/database.example.php` & `config/openai.example.php` adalah template yang
> di-commit; file asli `config/database.php` / `config/openai.php` bersifat lokal.

### 4. **Struktur File Akhir**
```
d:/xampp/htdocs/smart/
├── config/                     # Kredensial & bootstrap (AuthManager, env, DB, OpenAI)
│   ├── database.php            # getDB() (gitignored; template: database.example.php)
│   ├── openai.php              # class OpenAIClient (gitignored; template: openai.example.php)
│   └── auth.php                # AuthManager + requireAuth/requireAuthJson/csrf_*
├── src/                        # Slice vertikal per fitur (PSR-4 App\ => src/)
│   ├── Customers/CustomerRepository.php
│   ├── Transactions/TransactionRepository.php
│   ├── Dashboard/DashboardStats.php
│   ├── Rfm/RfmService.php      # rekalkulasi & baca rfm_analysis
│   ├── Rfm.php                 # single source of truth skor/segmen (TIDAK diubah)
│   ├── Import/SpreadsheetImporter.php
│   ├── Upload/UploadValidator.php
│   ├── Export/CustomersExporter.php
│   ├── Export/TransactionsExporter.php
│   ├── Ai/ContentGenerator.php
│   └── Business/BusinessProfileService.php
├── includes/                   # Cross-cutting saja
│   ├── sidebar.php             # satu-satunya sumber menu (user + admin)
│   └── pagination.php
├── api/                        # Endpoint JSON (tipis; auth + panggil class src/)
│   ├── generate-content.php
│   └── upload-excel.php
│   └── export-customers.php / export-transactions.php
├── *.php (docroot)             # Halaman UMKM tipis: requireAuth -> panggil class -> render
│   ├── dashboard.php / customers.php / transactions.php / analysis.php
│   └── upload.php / ai-content.php / profile.php / index.php / login.php
├── admin/                      # Panel super_admin (subsistem terpisah, belum di-slice)
├── tests/                      # PHPUnit 9.6 (DB test: smart_marketing_rfm_test)
├── database_*.sql              # Migrasi manual (schema/update/indexes)
└── README.md
```
> Catatan: halaman/API yang memakai class `App\*` memuat `vendor/autoload.php` sendiri
> di awal (bukan dari `config/database.php`). Slice alur kerja: lihat
> `docs/plans/2026-08-18-vertical-slicing-sprints.md` (4 sprint, sudah selesai S1–S4).

### 5. **Testing Setup**

**Unit test (PHPUnit, Fase 4.1):**
```bash
composer test
# atau: ./vendor/bin/phpunit
# DB test: smart_marketing_rfm_test (lihat tests/bootstrap.php) — jangan jalankan ke DB produksi!
```
> Catatan: saat dijalankan sebagai root, composer menolak plugin tanpa
> `COMPOSER_ALLOW_SUPERUSER=1`; gunakan di lingkungan CI/non-root.

1. **Test Database Connection:**
   - Buka: http://localhost/smart/dashboard.php
   - Jika ada error koneksi, periksa konfigurasi database

2. **Test Budget Page:**
   - Buka: http://localhost/smart/budget.php
   - Test fungsi PDF export

3. **Test RFM Analysis:**
   - Buka: http://localhost/smart/analysis.php
   - Lihat hasil analisis dengan data sample

4. **Test AI Content (opsional):**
   - Di dashboard, pilih segment dan klik "Generate Content"
   - Memerlukan OpenAI API key yang valid

### 6. **Troubleshooting**

#### Error Database Connection:
```
Error: Connection failed: SQLSTATE[HY000] [1045] Access denied
```
**Solusi:** Periksa username/password MySQL di `config/database.php`

#### Error Apache/PHP:
```
This site can't be reached
```
**Solusi:** 
- Pastikan Apache running di XAMPP
- Periksa port 80 tidak digunakan aplikasi lain

#### Error OpenAI API:
```
Error: Invalid API key
```
**Solusi:** 
- Daftar di https://platform.openai.com
- Generate API key dan masukkan ke `config/openai.php`

### 7. **Pengembangan Lanjutan**

#### Menambah Data Customer:
1. Gunakan form upload di dashboard
2. Atau insert manual via phpMyAdmin:
```sql
INSERT INTO customers (name, email) VALUES ('Nama Customer', 'email@domain.com');
INSERT INTO transactions (customer_id, transaction_date, amount) VALUES (1, '2024-01-15', 250000);
```

#### Customize Segment Logic:
Edit file `analysis.php` pada bagian query RFM calculation untuk menyesuaikan:
- Threshold recency (hari)
- Threshold frequency (jumlah transaksi)
- Threshold monetary (nilai rata-rata)

#### Menambah Fitur Export:
- Excel export: Install PhpSpreadsheet
- Email marketing: Integrate dengan mail service
- WhatsApp API: Integrate untuk automated messaging

### 8. **Security Checklist**
- [x] Ganti password MySQL default & rotasi kredensial DB (Fase 1.4)
- [x] Simpan OpenAI API key / kredensial DB di environment variable / `.env` (Fase 3.5)
- [x] Validasi input data upload: ekstensi + MIME via `finfo`, batas 5MB, rename acak (Fase 2.3)
- [x] Implement user authentication (login system) + CSRF semua form + session hardening (Fase 1–2)
- [x] Endpoint `/api/*` wajib autentikasi (401/403 JSON) (Fase 1.1)
- [ ] Setup HTTPS untuk production (aktifkan Let's Encrypt — lihat bagian Linux Deployment & `docs/SECURITY.md` §8)

> Detail lengkap: [`docs/SECURITY.md`](docs/SECURITY.md)

### 9. **Performance Optimization**
- Database indexing untuk tabel besar
- Caching untuk query RFM yang kompleks
- Pagination untuk tabel dengan banyak data
- Optimize images dan assets

### 10. **Backup & Maintenance**
```bash
# Backup database
mysqldump -u root -p smart_marketing_rfm > backup_$(date +%Y%m%d).sql

# Backup files (repo + arsip kredensial lokal; jangan commit .env)
tar -czf smart_backup_$(date +%Y%m%d).tar.gz /var/www/smartrfm.my.id/ --exclude=vendor --exclude=.git
```

## Kontak Support
Jika mengalami kendala, dokumentasikan:
1. Error message lengkap
2. Screenshot (jika perlu)
3. Versi PHP/MySQL yang digunakan
4. Steps yang sudah dicoba

---
**Catatan:** Sistem ini didesain untuk environment development. Untuk production, pertimbangkan security hardening dan performance optimization tambahan.

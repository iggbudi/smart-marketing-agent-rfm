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

5. **Generate Sample Data**
   ```
   php generate_sample_data.php
   ```

6. **Access Platform**
   ```
   Landing Page: http://localhost/smart/
   Admin Panel: http://localhost/smart/admin/
   ```

## Default Accounts

### Super Admin
- **Username**: admin
- **Password**: admin123
- **Access**: Full platform administration

### UMKM Owner (Demo Accounts)
- **Username**: umkm1, umkm2, umkm3, umkm4, umkm5
- **Password**: umkm123
- **Access**: Business-specific data only

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
│   ├── css/                # Custom stylesheets
│   ├── js/                 # JavaScript files
│   └── images/             # Image assets
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
3. Import file: `database_schema.sql`

```sql
# Atau jalankan manual:
mysql -u root -p
CREATE DATABASE smart_marketing;
USE smart_marketing;
SOURCE database_schema.sql;
```

### 3. **Konfigurasi File**

#### Edit `config/database.php`:
```php
<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'smart_marketing';  // Sesuaikan nama database
    private $username = 'root';            // Username MySQL
    private $password = '';                // Password MySQL (kosong untuk XAMPP default)
    private $conn;
    
    // ... rest of the code
}
```

#### Edit `config/openai.php` (opsional):
```php
private $apiKey = 'your-openai-api-key-here';  // Ganti dengan API key Anda
```

### 4. **Struktur File Akhir**
```
d:/xampp/htdocs/smart/
├── config/
│   ├── database.php
│   └── openai.php
├── api/
│   ├── generate-content.php
│   └── upload-excel.php
├── database_schema.sql
├── dashboard.php
├── budget.php
├── analysis.php
└── README.md
```

### 5. **Testing Setup**

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
- [ ] Ganti password MySQL default
- [ ] Simpan OpenAI API key di environment variable
- [ ] Validate input data upload
- [ ] Implement user authentication (login system)
- [ ] Setup HTTPS untuk production

### 9. **Performance Optimization**
- Database indexing untuk tabel besar
- Caching untuk query RFM yang kompleks
- Pagination untuk tabel dengan banyak data
- Optimize images dan assets

### 10. **Backup & Maintenance**
```bash
# Backup database
mysqldump -u root -p smart_marketing > backup_$(date +%Y%m%d).sql

# Backup files
tar -czf smart_backup_$(date +%Y%m%d).tar.gz /xampp/htdocs/smart/
```

## Kontak Support
Jika mengalami kendala, dokumentasikan:
1. Error message lengkap
2. Screenshot (jika perlu)
3. Versi PHP/MySQL yang digunakan
4. Steps yang sudah dicoba

---
**Catatan:** Sistem ini didesain untuk environment development. Untuk production, pertimbangkan security hardening dan performance optimization tambahan.

## A. PANDUAN SUPER ADMIN

### 1. Dashboard Super Admin
Super Admin memiliki akses penuh untuk mengelola seluruh sistem dan memonitor semua aktivitas.

#### Halaman Utama Super Admin
- **Overview Statistics**
  - Total pengrajin batik terdaftar
  - Total transaksi sistem
  - Revenue platform (jika ada subscription)
  - Active users hari ini
  - API usage (OpenAI, Email, WhatsApp)

#### Menu yang Tersedia:
1. **Dashboard** - Overview sistem
2. **Manajemen Pengguna** - Kelola semua user
3. **Manajemen UMKM** - Monitor semua UMKM
4. **Analytics** - Statistik platform
5. **API Management** - Monitor usage & cost
6. **System Settings** - Konfigurasi sistem
7. **Reports** - Laporan platform

### 2. Fitur-Fitur Super Admin

#### A. Manajemen Pengguna
**Akses Menu:** Dashboard → Manajemen Pengguna

**Yang bisa dilakukan:**
- ✅ Lihat daftar semua pengguna (pengrajin batik)
- ✅ Tambah pengguna baru
- ✅ Edit profil pengguna
- ✅ Suspend/aktifkan akun
- ✅ Reset password pengguna
- ✅ Lihat log aktivitas per user
- ✅ Set limit/quota per user

**Cara menambah user baru:**
1. Klik tombol "Tambah Pengguna"
2. Isi form:
   - Nama lengkap
   - Email
   - Nama UMKM
   - Nomor telepon
   - Alamat
3. Sistem akan kirim email invitation
4. User aktivasi via link email

#### B. Manajemen UMKM
**Akses Menu:** Dashboard → Manajemen UMKM

**Yang bisa dilakukan:**
- ✅ Lihat semua UMKM terdaftar
- ✅ Lihat detail bisnis setiap UMKM
- ✅ Monitor jumlah customer per UMKM
- ✅ Monitor jumlah transaksi
- ✅ Lihat RFM analysis result
- ✅ Export data UMKM

#### C. API Management
**Akses Menu:** Dashboard → API Management

**Yang bisa dilakukan:**
- ✅ Monitor OpenAI API usage
  - Tokens used per day/month
  - Cost estimation
  - Usage per UMKM
- ✅ Monitor Email API
  - Emails sent
  - Bounce rate
  - Success rate
- ✅ Monitor WhatsApp API
  - Messages sent
  - Delivery status
- ✅ Set API limits per UMKM
- ✅ View API error logs

#### D. System Settings
**Akses Menu:** Dashboard → System Settings

**Yang bisa dilakukan:**
- ✅ Konfigurasi email templates
- ✅ Set default RFM thresholds
- ✅ Manage AI prompts templates
- ✅ Configure cron job schedules
- ✅ Backup database settings
- ✅ Maintenance mode on/off

#### E. Reports & Analytics
**Akses Menu:** Dashboard → Reports

**Reports yang tersedia:**
- 📊 Platform growth report
- 📊 Revenue report (if subscription)
- 📊 API usage & cost report
- 📊 User activity report
- 📊 System performance report

**Export format:** PDF, Excel, CSV

### 3. Workflow Super Admin

#### Daily Tasks
1. **Pagi (09:00)**
   - Check dashboard overview
   - Review API usage from yesterday
   - Check for any system alerts

2. **Siang (14:00)**
   - Review new user registrations
   - Respond to user issues/tickets
   - Monitor system performance

3. **Sore (17:00)**
   - Check daily report
   - Ensure all cron jobs ran successfully
   - Review error logs if any

#### Weekly Tasks
- Generate weekly platform report
- Review API costs projection
- User satisfaction check
- System maintenance planning

#### Monthly Tasks
- Full platform analytics review
- API budget review
- User feedback analysis
- Feature planning based on usage

---

## B. PANDUAN PEMILIK UMKM (USER)

### 1. Dashboard Pemilik UMKM
Pemilik UMKM dapat mengelola data pelanggan dan mendapatkan insights marketing.

#### Halaman Utama User
- **Business Overview**
  - Total pelanggan
  - Total transaksi bulan ini
  - Revenue bulan ini
  - Customer segments distribution

#### Menu yang Tersedia:
1. **Dashboard** - Overview bisnis
2. **Data Pelanggan** - Kelola customer
3. **Data Transaksi** - Input & manage
4. **Analisis RFM** - Lihat segmentasi
5. **Marketing Content** - Generate konten
6. **Laporan** - Download reports
7. **Pengaturan** - Profil & preferences

### 2. Fitur-Fitur Pemilik UMKM

#### A. Manajemen Data Pelanggan
**Akses Menu:** Dashboard → Data Pelanggan

**Yang bisa dilakukan:**
- ✅ Tambah pelanggan baru
- ✅ Edit data pelanggan
- ✅ Import pelanggan (Excel/CSV)
- ✅ Lihat riwayat transaksi per pelanggan
- ✅ Tag/label pelanggan
- ✅ Export data pelanggan

**Cara import data pelanggan:**
1. Klik "Import Data"
2. Download template Excel
3. Isi sesuai format:
   - Nama
   - Email
   - No. HP
   - Alamat (optional)
4. Upload file
5. Review & confirm import

#### B. Manajemen Transaksi
**Akses Menu:** Dashboard → Data Transaksi

**Yang bisa dilakukan:**
- ✅ Input transaksi manual
- ✅ Import bulk transaksi (Excel/CSV)
- ✅ Edit/hapus transaksi
- ✅ Filter transaksi (tanggal, customer, produk)
- ✅ Lihat statistik transaksi

**Data transaksi yang diinput:**
- Tanggal transaksi
- Nama pelanggan
- Produk batik yang dibeli
- Nilai transaksi
- Channel (online/offline)
- Catatan (optional)

#### C. Analisis RFM
**Akses Menu:** Dashboard → Analisis RFM

**Yang bisa dilihat:**
- 📊 Grafik distribusi segmen pelanggan
- 📊 Detail setiap segmen:
  - **Champions**: Pelanggan terbaik
  - **Loyal Customers**: Pelanggan setia
  - **Potential Loyalists**: Berpotensi loyal
  - **New Customers**: Pelanggan baru
  - **At Risk**: Berisiko churn
  - **Can't Lose Them**: Harus dipertahankan
  - **Lost**: Sudah tidak aktif
- 📊 Rekomendasi aksi per segmen
- 📊 Trend perubahan segmen

**Update otomatis:** Setiap Senin pagi

#### D. Generate Marketing Content
**Akses Menu:** Dashboard → Marketing Content

**Yang bisa dilakukan:**
- ✅ Generate konten per segmen
- ✅ Pilih jenis konten:
  - Caption Instagram/Facebook
  - WhatsApp broadcast message
  - Email marketing
  - SMS marketing
  - Promo ideas
- ✅ Edit hasil generate
- ✅ Save template favorit
- ✅ Copy langsung untuk digunakan

**Cara generate content:**
1. Pilih customer segment
2. Pilih jenis konten
3. Klik "Generate dengan AI"
4. Review & edit hasil
5. Copy atau save template

#### E. Laporan
**Akses Menu:** Dashboard → Laporan

**Laporan yang tersedia:**
- 📄 Laporan RFM Analysis (PDF)
- 📄 Customer list per segment
- 📄 Transaction summary
- 📄 Revenue report
- 📄 Customer lifetime value

**Fitur laporan:**
- Filter periode (bulanan/mingguan)
- Include charts & graphics
- Branded dengan logo UMKM
- Send via email

#### F. Notifikasi & Alerts
**Automatic notifications:**
- ✉️ Email setiap Senin: RFM analysis update
- ✉️ Alert customer at risk
- ✉️ Monthly business summary
- 📱 (Future) WhatsApp notifications

### 3. Workflow Pemilik UMKM

#### Setup Awal
1. **Lengkapi profil bisnis**
   - Logo UMKM
   - Informasi kontak
   - Jenis produk batik

2. **Import data pelanggan**
   - Via Excel/CSV
   - Atau input manual

3. **Import histori transaksi**
   - Minimal 3 bulan terakhir
   - Untuk analisis akurat

#### Penggunaan Rutin

**Harian:**
- Input transaksi baru
- Cek dashboard overview

**Mingguan:**
- Review RFM analysis (auto update Senin)
- Generate marketing content
- Kirim campaign ke customer

**Bulanan:**
- Download laporan bulanan
- Review customer trends
- Plan marketing strategy

### 4. Best Practices

#### Untuk Super Admin:
1. Monitor API usage daily to avoid overlimit
2. Regular backup data UMKM
3. Respond quickly to user issues
4. Keep system updated
5. Document setiap perubahan konfigurasi

#### Untuk Pemilik UMKM:
1. Input transaksi secara konsisten
2. Lengkapi data customer (email & no. HP)
3. Gunakan RFM insights untuk campaign
4. Personalisasi content per segment
5. Track hasil campaign

### 5. FAQ

**Q: Berapa sering RFM analysis diupdate?**
A: Otomatis setiap Senin pagi jam 06:00 WIB

**Q: Berapa maksimal generate content per hari?**
A: 10 content per hari per UMKM

**Q: Bisa export data customer?**
A: Ya, dalam format Excel atau CSV

**Q: Ada limit jumlah customer?**
A: Tidak ada limit untuk data customer

**Q: Bisa integrasi dengan tokopedia/shopee?**
A: Belum tersedia, input manual atau via Excel

### 6. Support & Help

**Untuk Super Admin:**
- Technical documentation
- System logs access
- Direct developer contact

**Untuk Pemilik UMKM:**
- In-app help center
- Video tutorial
- Email support
- WhatsApp support (future)
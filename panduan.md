# Panduan Sederhana Penggunaan Aplikasi Smart Marketing Agent

## 🎯 Apa itu Smart Marketing Agent?
Smart Marketing Agent adalah aplikasi yang membantu pengrajin batik untuk:
- Mengelola data pelanggan
- Mengetahui pelanggan mana yang loyal dan mana yang hampir pergi
- Membuat konten marketing otomatis dengan bantuan AI

Ada 2 jenis pengguna: **Super Admin** (pengelola aplikasi) dan **Pemilik UMKM** (pengrajin batik).

---

## 👤 ALUR PENGGUNAAN UNTUK PEMILIK UMKM (PENGRAJIN BATIK)

### 1. **Mulai Menggunakan Aplikasi**

**Langkah pertama:**
1. Buka aplikasi di browser (Chrome/Firefox)
2. Masukkan email dan password yang sudah diberikan
3. Klik tombol "Masuk"
4. Anda akan masuk ke halaman utama (dashboard)

### 2. **Memasukkan Data Pelanggan**

**Cara mudahnya:**
1. Di menu sebelah kiri, klik "Data Pelanggan"
2. Ada 2 cara input data:
   
   **Cara A - Satu per satu:**
   - Klik tombol "Tambah Pelanggan"
   - Isi: Nama, No HP, Email (kalau ada)
   - Klik "Simpan"
   
   **Cara B - Banyak sekaligus pakai Excel:**
   - Klik tombol "Import Excel"
   - Download dulu templatenya
   - Isi data pelanggan di Excel
   - Upload file Excel tersebut
   - Sistem akan memasukkan semua data otomatis

### 3. **Memasukkan Data Penjualan**

**Setiap ada transaksi:**
1. Klik menu "Data Transaksi"
2. Klik "Tambah Transaksi"
3. Isi:
   - Pilih nama pelanggan (dari dropdown)
   - Tanggal beli
   - Produk yang dibeli (misal: Batik Parang)
   - Total belanja (misal: Rp 350.000)
4. Klik "Simpan"

**Tips:** Usahakan input transaksi setiap hari agar analisisnya akurat.

### 4. **Melihat Hasil Analisis Pelanggan**

**Otomatis setiap Senin pagi:**
1. Sistem akan menganalisis semua pelanggan Anda
2. Buka menu "Analisis RFM"
3. Anda akan lihat pelanggan dibagi jadi beberapa kelompok:

   📌 **Penjelasan Sederhana Kelompok Pelanggan:**
   
   - **🏆 Champions (Juara)**
     → Pelanggan terbaik! Sering beli, baru beli, belanja banyak
     → Perlakuan: Beri reward, undang jadi member VIP
   
   - **❤️ Loyal Customers (Pelanggan Setia)**
     → Pelanggan yang rutin beli
     → Perlakuan: Pertahankan dengan program loyalitas
   
   - **⭐ Potential Loyalists (Calon Setia)**
     → Pelanggan baru yang berpotensi jadi setia
     → Perlakuan: Beri penawaran menarik agar sering balik
   
   - **🆕 New Customers (Pelanggan Baru)**
     → Baru pertama kali beli
     → Perlakuan: Buat kesan pertama yang baik, follow up
   
   - **⚠️ At Risk (Berisiko Pergi)**
     → Dulu sering beli, sekarang mulai jarang
     → Perlakuan: Hubungi segera! Tanya ada masalah apa
   
   - **🚨 Can't Lose Them (Jangan Sampai Hilang)**
     → Pelanggan bagus yang hampir pergi
     → Perlakuan: Beri diskon spesial, perhatian khusus
   
   - **😔 Lost (Sudah Pergi)**
     → Sudah lama tidak beli
     → Perlakuan: Campaign "kami rindu", diskon comeback

### 5. **Membuat Konten Marketing Otomatis**

**Pakai AI untuk buat konten:**
1. Klik menu "Marketing Content"
2. Pilih mau buat konten untuk kelompok mana
   (misal: untuk "At Risk")
3. Pilih jenis konten:
   - Caption Instagram/Facebook
   - Pesan WhatsApp
   - Email promosi
4. Klik "Generate dengan AI"
5. Dalam hitungan detik, AI akan buatkan konten
6. Anda bisa edit kalau perlu
7. Copy dan pakai untuk marketing

**Contoh hasil AI:**
```
Untuk kelompok "At Risk":
"Hai Kak, sudah lama tidak jumpa! 😊
Kami punya koleksi batik terbaru yang 
pastinya Kakak suka. Khusus untuk 
pelanggan setia kami, diskon 20% 
sampai akhir bulan. Yuk mampir!"
```

### 6. **Melihat Laporan**

**Setiap bulan:**
1. Klik menu "Laporan"
2. Pilih jenis laporan:
   - Laporan analisis pelanggan (PDF)
   - Daftar pelanggan per kelompok (Excel)
   - Laporan penjualan
3. Klik "Download"
4. File akan terdownload ke komputer Anda

---

## 👨‍💼 ALUR PENGGUNAAN UNTUK SUPER ADMIN

### 1. **Tugas Utama Super Admin**

Super Admin adalah pengelola aplikasi yang bertugas:
- Mendaftarkan pengrajin batik baru
- Memantau penggunaan aplikasi
- Memastikan sistem berjalan lancar
- Mengatasi masalah teknis

### 2. **Menambah Pengguna Baru (Pengrajin Batik)**

**Saat ada pengrajin baru mau pakai aplikasi:**
1. Login sebagai Super Admin
2. Klik menu "Manajemen Pengguna"
3. Klik "Tambah Pengguna"
4. Isi data:
   - Nama pengrajin
   - Nama UMKM
   - Email
   - No HP
5. Klik "Kirim Undangan"
6. Pengrajin akan terima email berisi link aktivasi
7. Setelah aktivasi, mereka bisa login

### 3. **Memantau Penggunaan Aplikasi**

**Setiap hari Super Admin cek:**
1. **Dashboard Utama**
   - Berapa pengrajin yang aktif hari ini
   - Total transaksi yang diinput
   - Ada error atau tidak

2. **API Usage** (Penggunaan layanan)
   - Cek penggunaan AI (OpenAI)
   - Cek email terkirim
   - Cek WhatsApp terkirim
   - Pastikan tidak over limit

### 4. **Mengatasi Masalah**

**Jika ada pengrajin lapor masalah:**
1. Cek di menu "System Logs" 
2. Lihat error apa yang terjadi
3. Ambil tindakan:
   - Reset password jika lupa
   - Aktifkan kembali akun jika tersuspend
   - Perbaiki data jika ada yang salah

### 5. **Membuat Laporan Bulanan**

**Akhir bulan:**
1. Klik menu "Reports"
2. Generate laporan:
   - Pertumbuhan pengguna
   - Penggunaan sistem
   - Biaya API yang terpakai
3. Download dan kirim ke manajemen

---

## 📱 NOTIFIKASI OTOMATIS

### Untuk Pengrajin Batik:
- **Email setiap Senin**: "Hasil analisis pelanggan minggu ini"
- **Alert**: "Ada 5 pelanggan berisiko pergi"
- **Reminder**: "Jangan lupa input transaksi hari ini"

### Untuk Super Admin:
- **Alert**: "API usage mencapai 80%"
- **Error**: "Ada sistem yang bermasalah"
- **Report**: "Laporan mingguan siap"

---

## 💡 TIPS SUKSES MENGGUNAKAN APLIKASI

### Untuk Pengrajin Batik:
1. **Rutin input data** - Jangan menumpuk, input setiap hari
2. **Lengkapi data pelanggan** - Minimal nama dan no HP
3. **Manfaatkan AI** - Hemat waktu buat konten
4. **Action sesuai analisis** - Jangan cuma lihat, tapi lakukan
5. **Follow up pelanggan** - Terutama yang "At Risk"

### Untuk Super Admin:
1. **Monitor setiap hari** - 15 menit cek dashboard
2. **Respon cepat** - Jika ada keluhan user
3. **Backup rutin** - Untuk jaga-jaga
4. **Update pengrajin** - Info fitur baru
5. **Manage API budget** - Jangan sampai overlimit

---

## ❓ PERTANYAAN UMUM

**T: Berapa lama hasil analisis keluar?**
J: Otomatis setiap Senin jam 6 pagi

**T: Bisa ganti konten yang dibuat AI?**
J: Bisa! Edit sesuka hati sebelum dipakai

**T: Data pelanggan aman?**
J: Sangat aman, terenkripsi dan tidak bisa diakses orang lain

**T: Kalau lupa password?**
J: Klik "Lupa Password", ikuti petunjuk di email

**T: Bisa pakai di HP?**
J: Bisa! Buka browser HP, tampilannya menyesuaikan

---


---

*Membantu pengrajin batik mengenal pelanggan lebih baik dan meningkatkan penjualan dengan teknologi AI* 🎯
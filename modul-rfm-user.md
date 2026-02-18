# Modul Penggunaan Smart Marketing Agent RFM (Versi Pengguna)

Modul ini merangkum alur kerja Smart Marketing Agent RFM secara sederhana agar mudah dipahami oleh Super Admin maupun Pemilik UMKM. Isi dokumen merujuk pada modul detail di `modul-rfm.md`, tetapi menggunakan bahasa praktis dan contoh situasi sehari-hari.

---

## 1. Kenapa Perlu Memahami Alur RFM?
- **RFM** (Recency, Frequency, Monetary) membantu mengenali pelanggan terbaik, pelanggan yang perlu diperhatikan, dan pelanggan yang harus disapa kembali.
- Dengan mengikuti modul ini, Anda bisa:
  - Memastikan data pelanggan dan transaksi terekam rapi.
  - Membaca dashboard dan laporan tanpa bingung.
  - Menggunakan rekomendasi AI untuk kampanye yang tepat sasaran.

---

## 2. Peran dan Tanggung Jawab Utama

| Peran | Fokus Kegiatan Harian | Kegiatan Berkala |
| --- | --- | --- |
| **Super Admin** | Memastikan semua pengguna dapat login, mengecek kesehatan sistem, membantu impor data jika ada masalah. | Menambahkan bisnis baru, mengatur parameter RFM & kuota AI, memantau log aktivitas. |
| **Pemilik UMKM** | Menginput pelanggan dan transaksi terbaru, memantau segmen pelanggan, menjalankan kampanye. | Mengevaluasi hasil analisis RFM mingguan, menyiapkan laporan bulanan, meninjau konten AI. |

---

## 3. Alur Utama Langkah demi Langkah

1. **Siapkan Sistem (Super Admin)**
   - Pastikan database aktif dan koneksi `config/database.php` sudah benar.
   - Cek pengaturan umum seperti ambang RFM, API key OpenAI, dan data bisnis awal.

2. **Login & Keamanan (Semua Pengguna)**
   - Akses halaman `login.php`, masuk dengan email dan password.
   - Bila selesai menggunakan aplikasi, klik `logout` agar sesi tertutup aman.

3. **Onboarding Bisnis (Super Admin & Pemilik UMKM)**
   - Super Admin menambahkan bisnis dan menetapkan pemiliknya.
   - Pemilik UMKM melengkapi profil bisnis: alamat, kontak, jenis produk.

4. **Masukkan Data Pelanggan & Transaksi (Pemilik UMKM)**
   - Input pelanggan satu per satu atau impor Excel melalui `upload.php`.
   - Catat transaksi terbaru, pastikan tanggal dan nominal sesuai.
   - Lihat riwayat impor untuk memastikan tidak ada baris gagal.

5. **Validasi Data (Opsional tetapi dianjurkan)**
   - Gunakan alat bantu seperti `check_transactions_table.php` untuk mendeteksi format tanggal atau nominal yang salah.
   - Perbaiki data yang ditandai sebelum melanjutkan analisis.

6. **Kelola Data Sehari-hari**
   - Perbarui informasi pelanggan (nomor telepon, status aktif).
   - Koreksi transaksi jika ada pembatalan atau retur.

7. **Analisis RFM Otomatis**
   - Buka menu Analisis atau jalankan perhitungan melalui `analysis.php`.
   - Sistem memberi skor R, F, dan M serta menempatkan pelanggan ke segmen seperti Champions, Loyal, At Risk, dan lain-lain.
   - Tinjau ringkasan seperti tanggal pembelian terakhir dan total belanja.

8. **Dashboard & Insight**
   - Gunakan `dashboard.php` atau `dashboard_new.php` untuk melihat KPI: pendapatan, jumlah transaksi, dan distribusi segmen.
   - Terapkan filter waktu atau bisnis bila menangani beberapa cabang.

9. **Tindakan Lanjutan dengan AI**
   - Buka `ai-content.php`, pilih segmen pelanggan, kemudian jenis konten (misal WhatsApp, Email, Caption IG).
   - Klik generate; revisi bila perlu, lalu simpan untuk eksekusi kampanye.

10. **Laporan & Ekspor**
    - Gunakan menu laporan atau API ekspor untuk mengunduh data pelanggan, transaksi, atau hasil RFM.
    - Pastikan file CSV/XLSX terbuka baik di Excel atau Google Sheets.

11. **Pengawasan & Audit**
    - Super Admin memantau `admin/dashboard.php`, `admin/users.php`, dan log aktivitas.
    - Catat perubahan penting agar mudah ditelusuri bila ada masalah.

12. **Perawatan Sistem**
    - Jalankan skrip pengecekan tabel secara berkala (`check_tables.php`, `check_missing_tables.php`).
    - Rencanakan upgrade atau perbaikan yang tercatat di `plan.md`.

---

## 4. Checklist Cepat untuk Pengguna

### Pemilik UMKM (Mingguan)
- [ ] Data pelanggan baru sudah ditambahkan.
- [ ] Semua transaksi terbaru sudah tercatat.
- [ ] Segmen RFM sudah ditinjau dan dipahami tindakannya.
- [ ] Konten AI untuk segmen penting sudah dibuat.
- [ ] Laporan ringkas dikirim ke tim/owner lain jika perlu.

### Super Admin (Mingguan)
- [ ] Tidak ada login gagal berulang atau akun mencurigakan.
- [ ] Status impor data terakhir sukses.
- [ ] Parameter RFM dan kuota AI masih relevan.
- [ ] Backup database terakhir tersimpan aman.
- [ ] Tidak ada tabel yang hilang atau gagal terbaca.

---

## 5. Tips Praktis
- Gunakan template Excel resmi agar format tanggal dan angka konsisten.
- Atur jadwal rutin (misal tiap Senin pagi) untuk menjalankan analisis RFM.
- Segera perbarui password bila ada notifikasi login mencurigakan.
- Manfaatkan fitur filter di dashboard untuk membandingkan performa antar bulan.
- Dokumentasikan perubahan penting (misal ganti ambang RFM) di catatan tim.

---

## 6. Pertanyaan yang Sering Muncul

**1. Analisis RFM kosong, apa yang harus dilakukan?**  
Pastikan data transaksi sudah ada dan memiliki tanggal terbaru. Jalankan ulang proses impor bila perlu.

**2. Segmen pelanggan terasa tidak sesuai.**  
Periksa parameter ambang RFM di pengaturan sistem. Segmen berubah bila ambang dinaikkan/diturunkan.

**3. Konten AI tidak muncul.**  
Cek koneksi internet, pastikan API key OpenAI aktif, dan lihat limit token di halaman pengaturan.

**4. Gagal ekspor laporan.**  
Pastikan kolom wajib sudah terisi, coba ulang dengan rentang tanggal lebih kecil, atau unduh melalui menu admin.

**5. Bagaimana kalau lupa password?**  
Hubungi Super Admin untuk reset password atau membuat akun baru.

---

## 7. Ringkasan Siklus Kerja
1. **Masuk ke aplikasi** → 2. **Input/validasi data** → 3. **Jalankan analisis RFM** → 4. **Baca insight di dashboard** → 5. **Lakukan tindakan (AI/kampanye)** → 6. **Unduh laporan & evaluasi** → 7. **Pantau keamanan & lakukan perawatan**.

Ikuti siklus ini secara konsisten agar manfaat RFM terasa maksimal untuk pertumbuhan bisnis.


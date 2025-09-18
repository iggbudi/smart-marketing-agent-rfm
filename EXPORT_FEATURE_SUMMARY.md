# Fitur Export Excel - Data Pelanggan

## Ringkasan Implementasi

Fitur export Excel telah berhasil ditambahkan ke halaman `customers.php`. Tombol export ditempatkan di sebelah kiri kotak pencarian pelanggan sesuai permintaan.

## File yang Ditambahkan/Dimodifikasi

### 1. File Baru
- `api/export-customers.php` - Handler untuk export data pelanggan
- `composer.json` - Konfigurasi dependencies untuk PhpSpreadsheet
- `composer.lock` - Lock file dependencies
- `INSTALL_EXPORT.md` - Panduan instalasi
- `test-export.php` - File test untuk verifikasi
- `EXPORT_FEATURE_SUMMARY.md` - Dokumentasi ini

### 2. File yang Dimodifikasi
- `customers.php` - Menambahkan tombol export dan JavaScript handler

## Fitur yang Ditambahkan

### Tombol Export Excel
- **Lokasi**: Sebelah kiri kotak pencarian pelanggan
- **Style**: Button hijau dengan ikon Excel
- **Fungsi**: Mengekspor semua data pelanggan ke format Excel

### Format Export
- **File Excel (.xlsx)** dengan styling lengkap
- **Fallback CSV** jika PhpSpreadsheet tidak tersedia
- **Nama file**: `customers_[nama_bisnis]_[tanggal_waktu].xlsx`

### Data yang Diekspor
1. No (Nomor urut)
2. Nama Pelanggan
3. No HP
4. Email
5. Total Transaksi
6. Total Belanja (Angka murni tanpa pemisah ribuan untuk kompatibilitas Excel)
7. Transaksi Terakhir (Format Indonesia: dd/mm/yyyy)
8. Tanggal Registrasi (Format Indonesia: dd/mm/yyyy)

### Styling Excel
- Header dengan background biru dan teks putih
- Border pada semua sel
- Auto-size columns
- Alignment yang sesuai (center untuk angka, right untuk currency)

## Keamanan
- Hanya dapat diakses oleh UMKM owner
- Data terisolasi per bisnis
- Validasi autentikasi dan otorisasi

## Dependencies
- **PhpSpreadsheet 1.30.0** - Library untuk generate Excel files
- **PHP 7.4+** - Versi PHP minimum
- **Composer** - Package manager

## Cara Penggunaan

1. **Login** sebagai UMKM owner
2. **Buka** halaman `customers.php`
3. **Klik** tombol "Export Excel" (hijau dengan ikon Excel)
4. **File** akan otomatis terdownload

## Troubleshooting

### Jika file tidak terdownload:
1. Pastikan sudah login sebagai UMKM owner
2. Periksa error log PHP
3. Pastikan folder `vendor/` ada dan berisi PhpSpreadsheet

### Jika muncul error:
1. Jalankan `composer install --ignore-platform-reqs`
2. Pastikan extension GD dan ZIP terinstall di PHP
3. Periksa permission folder

## Testing
Buka `test-export.php` untuk memverifikasi instalasi berhasil.

## Catatan Teknis
- Menggunakan PhpSpreadsheet untuk format Excel yang profesional
- Fallback ke CSV jika library tidak tersedia
- JavaScript loading indicator saat export
- Responsive design yang konsisten dengan UI existing
- **Angka currency diekspor tanpa pemisah ribuan untuk kompatibilitas Excel**
- Format angka menggunakan `#,##0` di Excel untuk tampilan yang rapi

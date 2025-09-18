# Instalasi Fitur Export Excel

## Persyaratan
- PHP 7.4 atau lebih tinggi
- Composer (untuk menginstall PhpSpreadsheet)

## Langkah Instalasi

### 1. Install Composer Dependencies
Jalankan perintah berikut di terminal/command prompt di direktori project:

```bash
composer install
```

### 2. Verifikasi Instalasi
Setelah instalasi selesai, pastikan folder `vendor/` sudah terbuat dan berisi library PhpSpreadsheet.

### 3. Testing Fitur Export
1. Buka halaman `customers.php`
2. Klik tombol "Export Excel" di sebelah kiri kotak pencarian
3. File Excel akan otomatis terdownload

## Fitur Export Excel

### Format File
- **Nama file**: `customers_[nama_bisnis]_[tanggal_waktu].xlsx`
- **Format**: Excel (.xlsx) dengan styling lengkap

### Data yang Diekspor
1. **No** - Nomor urut
2. **Nama Pelanggan** - Nama lengkap pelanggan
3. **No HP** - Nomor telepon
4. **Email** - Alamat email (jika ada)
5. **Total Transaksi** - Jumlah transaksi yang dilakukan
6. **Total Belanja (Rp)** - Total nilai belanja dalam rupiah
7. **Transaksi Terakhir** - Tanggal transaksi terakhir
8. **Tanggal Registrasi** - Tanggal pelanggan terdaftar

### Styling Excel
- Header dengan background biru dan teks putih
- Border pada semua sel
- Auto-size columns
- Alignment yang sesuai (center untuk angka, right untuk currency)

## Fallback CSV Export
Jika PhpSpreadsheet tidak tersedia, sistem akan otomatis mengekspor ke format CSV sebagai fallback.

## Troubleshooting

### Error: Class 'PhpOffice\PhpSpreadsheet\Spreadsheet' not found
1. Pastikan Composer sudah terinstall
2. Jalankan `composer install` atau `composer update`
3. Pastikan folder `vendor/` ada dan berisi autoload.php

### Error: Permission denied
1. Pastikan folder project memiliki permission write
2. Pastikan web server dapat mengakses folder vendor/

### File tidak terdownload
1. Periksa error log PHP
2. Pastikan output buffering tidak aktif
3. Pastikan tidak ada whitespace sebelum `<?php` di file export


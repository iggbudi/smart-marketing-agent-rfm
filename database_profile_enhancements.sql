-- Database update untuk Profile enhancements
-- File ini berisi penambahan kolom opsional untuk profil bisnis yang lebih lengkap

USE smart_marketing_rfm;

-- Tambahkan kolom opsional untuk profil bisnis (jalankan jika diperlukan)
-- ALTER TABLE businesses ADD COLUMN logo_url VARCHAR(500) AFTER business_type;
-- ALTER TABLE businesses ADD COLUMN website VARCHAR(255) AFTER logo_url;
-- ALTER TABLE businesses ADD COLUMN social_media JSON AFTER website;
-- ALTER TABLE businesses ADD COLUMN description TEXT AFTER social_media;
-- ALTER TABLE businesses ADD COLUMN business_hours JSON AFTER description;
-- ALTER TABLE businesses ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER business_hours;

-- Contoh JSON untuk social_media:
-- {
--   "facebook": "https://facebook.com/mybusiness",
--   "instagram": "@mybusiness",
--   "whatsapp": "08123456789"
-- }

-- Contoh JSON untuk business_hours:
-- {
--   "monday": {"open": "08:00", "close": "17:00"},
--   "tuesday": {"open": "08:00", "close": "17:00"},
--   "wednesday": {"open": "08:00", "close": "17:00"},
--   "thursday": {"open": "08:00", "close": "17:00"},
--   "friday": {"open": "08:00", "close": "17:00"},
--   "saturday": {"open": "08:00", "close": "12:00"},
--   "sunday": {"closed": true}
-- }

-- Untuk saat ini, struktur tabel businesses sudah cukup untuk fitur profil dasar:
-- - name (nama bisnis)
-- - owner_name (nama pemilik)
-- - email (email bisnis)
-- - phone (nomor telepon)
-- - address (alamat)
-- - business_type (jenis bisnis)
-- - created_at, updated_at (timestamp)

-- database_indexes.sql
-- Fase 3.4: index performa untuk query halaman daftar & analisis RFM.
-- DB: smart_marketing_rfm (MySQL / MariaDB)
--
-- Catatan: index tunggal pada kolom FK (business_id, customer_id) sudah dibuat
-- otomatis oleh engine untuk transactions, customers, dan rfm_analysis.
-- Berikut index komposit untuk query umum:
--   - customers.php  : WHERE business_id + GROUP BY id + LEFT JOIN transactions
--   - transactions.php : WHERE business_id + JOIN customers + ORDER BY transaction_date
--   - recalculateRFM : WHERE business_id + JOIN customers->transactions + GROUP BY

USE smart_marketing_rfm;

-- Index komposit untuk filter/pengurutan transaksi per bisnis & pelanggan.
ALTER TABLE transactions
    ADD INDEX idx_trans_biz_cust_date (business_id, customer_id, transaction_date);

-- (Opsional, dipertimbangkan tetapi TIDAK dibuat setelah Fase 3.2)
-- VIEW v_rfm_scores: skor R/F/M sudah dipersist di tabel rfm_analysis oleh
-- recalculateRFM() (includes/rfm.php). analysis.php & dashboard membaca tabel
-- tsb langsung, sehingga VIEW hanya duplikasi komputasi tanpa keuntungan query.

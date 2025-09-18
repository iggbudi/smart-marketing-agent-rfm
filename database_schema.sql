-- Database Schema untuk Smart Marketing Agent RFM
-- MySQL Version untuk XAMPP

CREATE DATABASE IF NOT EXISTS smart_marketing_rfm;
USE smart_marketing_rfm;

-- 1. Tabel Businesses/UMKM
CREATE TABLE businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    business_type VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Tabel Customers
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- 3. Tabel Transactions
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    customer_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    product_name VARCHAR(255),
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- 4. Tabel RFM Analysis
CREATE TABLE rfm_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    customer_id INT NOT NULL,
    recency_score INT NOT NULL,
    frequency_score INT NOT NULL,
    monetary_score INT NOT NULL,
    rfm_segment VARCHAR(50) NOT NULL,
    last_purchase_date DATE,
    total_transactions INT,
    total_spent DECIMAL(15,2),
    analysis_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- 5. Tabel AI Generated Content
CREATE TABLE ai_generated_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    segment VARCHAR(50) NOT NULL,
    content_type ENUM('email', 'whatsapp', 'promo') DEFAULT 'email',
    subject VARCHAR(255),
    content TEXT NOT NULL,
    tokens_used INT,
    model_used VARCHAR(50) DEFAULT 'gpt-3.5-turbo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- 6. Tabel Upload History
CREATE TABLE upload_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500),
    records_imported INT DEFAULT 0,
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Insert sample business
INSERT INTO businesses (name, owner_name, email, phone, address, business_type) 
VALUES ('Batik Semarang Jaya', 'Budi Santoso', 'budi@batiksemarang.com', '08123456789', 'Jl. Pandanaran 123, Semarang', 'Batik');

-- Insert sample customers
INSERT INTO customers (business_id, customer_name, email, phone) VALUES
(1, 'Andi Wijaya', 'andi@email.com', '08111111111'),
(1, 'Sari Dewi', 'sari@email.com', '08222222222'),
(1, 'Joko Susanto', 'joko@email.com', '08333333333');

-- Insert sample transactions
INSERT INTO transactions (business_id, customer_id, transaction_date, amount, product_name, quantity) VALUES
(1, 1, '2024-01-15', 150000, 'Batik Kawung', 1),
(1, 1, '2024-02-20', 200000, 'Batik Parang', 1),
(1, 2, '2024-01-10', 100000, 'Batik Mega Mendung', 1),
(1, 3, '2024-03-01', 300000, 'Batik Set Couple', 1);

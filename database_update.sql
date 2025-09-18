-- Update database schema untuk multi-user system
USE smart_marketing_rfm;

-- Tambah tabel users untuk authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'umkm_owner') DEFAULT 'umkm_owner',
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Update tabel businesses untuk link ke users
ALTER TABLE businesses ADD COLUMN user_id INT;
ALTER TABLE businesses ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- Tabel untuk sessions
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel untuk activity logs
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    business_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Tabel untuk API usage tracking
CREATE TABLE api_usage_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT,
    api_type ENUM('openai', 'email', 'whatsapp', 'sms') NOT NULL,
    endpoint VARCHAR(255),
    tokens_used INT DEFAULT 0,
    cost DECIMAL(10,4) DEFAULT 0,
    status ENUM('success', 'error') NOT NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Tabel untuk system settings
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default super admin
INSERT INTO users (email, password, full_name, role, is_active, email_verified) 
VALUES ('admin@smartmarketing.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'super_admin', TRUE, TRUE);
-- Password: "password123"

-- Insert default UMKM user dan link ke business existing
INSERT INTO users (email, password, full_name, role, is_active, email_verified) 
VALUES ('budi@batiksemarang.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Santoso', 'umkm_owner', TRUE, TRUE);

-- Update business existing untuk link ke user
UPDATE businesses SET user_id = 2 WHERE id = 1;

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('openai_api_key', 'demo-mode', 'OpenAI API Key for content generation'),
('rfm_recency_days', '7,30,90,180', 'RFM Recency thresholds in days'),
('rfm_frequency_counts', '1,2,3,4,5', 'RFM Frequency thresholds'),
('rfm_monetary_amounts', '100000,200000,300000,400000,600000', 'RFM Monetary thresholds in IDR'),
('max_daily_content_generation', '10', 'Maximum content generation per day per business'),
('maintenance_mode', 'false', 'System maintenance mode'),
('platform_name', 'Smart Marketing Agent RFM', 'Platform name');

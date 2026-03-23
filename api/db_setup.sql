-- Platform master-admin tables — run against cloudkart_master DB only
-- Store tenant tables (ck_*) live in the separate cloudkart DB (STORE_DB_NAME)
--
-- Setup order:
--   1. CREATE DATABASE cloudkart_master CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   2. CREATE DATABASE cloudkart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;  (if not exists)
--   3. Run this file against cloudkart_master
--   4. Run install/sql/schema.sql against cloudkart (store tables template)

-- 1. Users Table (Platform Owners/Users)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'owner') DEFAULT 'owner',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Platform Plans Table
CREATE TABLE IF NOT EXISTS platform_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    price_one_time DECIMAL(10, 2) NOT NULL,
    annual_hosting DECIMAL(10, 2) NOT NULL,
    annual_domain DECIMAL(10, 2) NOT NULL,
    feature_set JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert Initial Plans
INSERT IGNORE INTO platform_plans (name, price_one_time, annual_hosting, annual_domain, feature_set) VALUES
('Business Plan', 14999.00, 3000.00, 0.00, '{"products": -1, "themes": 3, "support": "priority", "whatsapp_otp": true, "subscription_schemes": true, "combo_packs": true, "food_delivery": true}'),
('App Plan', 24999.00, 3000.00, 0.00, '{"products": -1, "themes": 3, "support": "24/7", "mobile_app": true, "custom_features": true, "white_label": true, "food_delivery": true}');

-- 3. Platform Stores Table
CREATE TABLE IF NOT EXISTS platform_stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    store_name VARCHAR(100) NOT NULL,
    subdomain VARCHAR(50) UNIQUE,
    db_name VARCHAR(64),
    db_user VARCHAR(64),
    db_pass VARCHAR(64),
    table_prefix VARCHAR(50),
    theme VARCHAR(50) DEFAULT 'general',
    custom_domain VARCHAR(100) UNIQUE,
    plan_id INT NOT NULL,
    status ENUM('provisioning', 'trial', 'active', 'suspended', 'cancelled', 'trial_expired') DEFAULT 'provisioning',
    is_trial TINYINT(1) DEFAULT 1,
    trial_ends_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES platform_plans(id)
);

-- 4. Platform Invoices Table
CREATE TABLE IF NOT EXISTS platform_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    invoice_number VARCHAR(20) UNIQUE,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'paid', 'void') DEFAULT 'pending',
    billing_period_start DATE,
    billing_period_end DATE,
    payment_method VARCHAR(50),
    payment_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES platform_stores(id) ON DELETE CASCADE
);

-- 5. Platform Licences Table (Updated status)
CREATE TABLE IF NOT EXISTS platform_licences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    licence_key VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'revoked', 'expired', 'trial') DEFAULT 'trial',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES platform_stores(id) ON DELETE CASCADE
);

-- Note: Password for test admin is 'admin123'
INSERT IGNORE INTO users (name, email, password, role, is_active) VALUES ('System Admin', 'admin@cloudkart.com', '$2y$10$WpQ8xX.q6XzX6XzX6XzX6.XzX6XzX6XzX6XzX6XzX6XzX6XzX6XzX', 'admin', 1);

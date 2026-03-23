-- Drop existing table if it exists to ensure correct schema
DROP TABLE IF EXISTS settings;

-- Settings table for multi-theme and feature toggling
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    group_name VARCHAR(50) DEFAULT 'general',
    field_type ENUM('text', 'textarea', 'boolean', 'color', 'number', 'select') DEFAULT 'text',
    is_public BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed initial settings for "Crackers" vs "Organic" support
INSERT INTO settings (setting_key, setting_value, group_name, field_type, is_public) VALUES
('active_theme', 'crackers', 'appearance', 'select', 1),
('site_logo', 'src/images/logo.png', 'appearance', 'text', 1),
('primary_color', '#f97316', 'appearance', 'color', 1),
('secondary_color', '#ef4444', 'appearance', 'color', 1),
('enable_quick_buy', '1', 'features', 'boolean', 1),
('enable_fund_scheme', '1', 'features', 'boolean', 1),
('cart_mode', 'estimate', 'features', 'select', 1), -- 'estimate' or 'direct_checkout'
('cart_button_label', 'Add to Estimate', 'labels', 'text', 1),
('minimum_order_value', '2000', 'orders', 'number', 1);

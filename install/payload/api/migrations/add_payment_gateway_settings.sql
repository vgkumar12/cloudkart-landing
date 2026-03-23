-- Payment Gateway Integration Migration
-- Add payment gateway settings and update orders table

-- Add payment gateway settings
INSERT INTO settings (setting_key, setting_value, group_name, field_type, is_public) VALUES
-- Payment Mode
('payment_mode', 'estimate', 'payment', 'select', 1), -- 'estimate' or 'online'

-- Razorpay
('razorpay_enabled', '0', 'payment', 'boolean', 0),
('razorpay_key_id', '', 'payment', 'text', 0),
('razorpay_key_secret', '', 'payment', 'text', 0),
('razorpay_test_mode', '1', 'payment', 'boolean', 0),

-- PhonePe
('phonepe_enabled', '0', 'payment', 'boolean', 0),
('phonepe_merchant_id', '', 'payment', 'text', 0),
('phonepe_salt_key', '', 'payment', 'text', 0),
('phonepe_salt_index', '1', 'payment', 'text', 0),
('phonepe_test_mode', '1', 'payment', 'boolean', 0),

-- Cashfree
('cashfree_enabled', '0', 'payment', 'boolean', 0),
('cashfree_app_id', '', 'payment', 'text', 0),
('cashfree_secret_key', '', 'payment', 'text', 0),
('cashfree_test_mode', '1', 'payment', 'boolean', 0),

-- Cash on Delivery
('cod_enabled', '1', 'payment', 'boolean', 1)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Update orders table to support payment tracking
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'cod' AFTER order_status,
ADD COLUMN IF NOT EXISTS payment_status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending' AFTER payment_method,
ADD COLUMN IF NOT EXISTS payment_gateway_order_id VARCHAR(255) NULL AFTER payment_status,
ADD COLUMN IF NOT EXISTS payment_gateway_response TEXT NULL AFTER payment_gateway_order_id,
ADD COLUMN IF NOT EXISTS payment_completed_at TIMESTAMP NULL AFTER payment_gateway_response;

-- Create index for faster payment lookups
CREATE INDEX IF NOT EXISTS idx_payment_gateway_order_id ON orders(payment_gateway_order_id);
CREATE INDEX IF NOT EXISTS idx_payment_status ON orders(payment_status);

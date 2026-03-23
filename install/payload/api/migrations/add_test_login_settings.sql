-- Add test login settings to settings table
-- Run this script to add test_login_enabled and dummy_password settings

-- Add test_login_enabled setting
INSERT INTO settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES (
    'test_login_enabled', 
    'true', 
    'boolean', 
    'Enable test login for development (set to false in production)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    `type` = 'boolean',
    `description` = 'Enable test login for development (set to false in production)',
    `updated_at` = NOW();

-- Add dummy_password setting
INSERT INTO settings (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES (
    'dummy_password', 
    'test123', 
    'string', 
    'Default password for test login (development only)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    `type` = 'string',
    `description` = 'Default password for test login (development only)',
    `updated_at` = NOW();

-- Verify the settings were added
SELECT * FROM settings WHERE `key` IN ('test_login_enabled', 'dummy_password');

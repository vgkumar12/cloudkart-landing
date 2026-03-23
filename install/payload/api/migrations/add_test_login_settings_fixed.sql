-- Check the settings table structure
DESCRIBE settings;

-- Check if test_login_enabled exists and its current value
SELECT * FROM settings WHERE setting_key = 'test_login_enabled';

-- If it doesn't exist, insert it
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at) 
VALUES (
    'test_login_enabled', 
    '1', 
    'boolean', 
    'Enable test login for development (set to false in production)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'boolean',
    description = 'Enable test login for development (set to false in production)',
    updated_at = NOW();

-- Also add dummy_password
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at) 
VALUES (
    'dummy_password', 
    'test123', 
    'string', 
    'Default password for test login (development only)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'string',
    description = 'Default password for test login (development only)',
    updated_at = NOW();

-- Verify
SELECT * FROM settings WHERE setting_key IN ('test_login_enabled', 'dummy_password');

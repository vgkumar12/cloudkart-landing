-- Add settings_version to track when settings change
-- This allows automatic cache invalidation on the frontend

-- Create or update the settings_version setting
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at) 
VALUES (
    'settings_version', 
    '1', 
    'number', 
    'Internal version number for cache invalidation (auto-incremented on settings update)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'number',
    description = 'Internal version number for cache invalidation (auto-incremented on settings update)',
    updated_at = NOW();

-- Verify
SELECT * FROM settings WHERE setting_key = 'settings_version';

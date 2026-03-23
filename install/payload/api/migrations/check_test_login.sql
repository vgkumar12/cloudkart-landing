-- Check current value of test_login_enabled
SELECT `key`, `value`, `type` FROM settings WHERE `key` = 'test_login_enabled';

-- If it shows as string "true", update to ensure it's boolean
UPDATE settings SET `type` = 'boolean' WHERE `key` = 'test_login_enabled';

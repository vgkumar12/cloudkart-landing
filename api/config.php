<?php

/**
 * CloudKart Landing API Configuration
 */

// Database — two separate databases
define('DB_HOST',         'localhost');
define('DB_USER',         'root');
define('DB_PASS',         '');
define('DB_CHARSET',      'utf8mb4');

// Platform DB — master admin tables only (users, platform_stores, platform_plans, platform_licences)
define('PLATFORM_DB_NAME', 'cloudkart_master');

// Stores DB — all tenant store tables (ck_* prefixed tables)
define('STORE_DB_NAME',    'cloudkart');

// Legacy alias — keeps any remaining code that still calls DB_NAME working
define('DB_NAME', PLATFORM_DB_NAME);

// Site Settings
define('SITE_URL', 'http://localhost/cloudkart/landing');
define('API_BASE_URL', SITE_URL . '/api');

// Cashfree Settings
define('CASHFREE_APP_ID', '');
define('CASHFREE_SECRET_KEY', '');
define('CASHFREE_MODE', 'test'); // test or production

// CPanel Settings (Required for live provisioning)
/*define('CPANEL_HOST', 'peace.herosite.pro');      // e.g. 'cpanel.yourdomain.com'
define('CPANEL_USER', 'dbvklnmy');      // cPanel Username
define('CPANEL_TOKEN', 'OZFJP93H8IIR6V9HC03OL568SLSNQBJ7');     // cPanel API Token
define('MAIN_DOMAIN', 'cloudkart24.com'); // Main domain for subdomains
*/
define('CPANEL_HOST', '');      // e.g. 'cpanel.yourdomain.com'
define('CPANEL_USER', '');      // cPanel Username
define('CPANEL_TOKEN', '');     // cPanel API Token
define('MAIN_DOMAIN', 'cloudkart24.com');
// CloudKart root directory (2 levels up from landing/api/)
// landing/api/config.php → landing/api/ → landing/ → cloudkart/
define('CLOUDKART_ROOT', dirname(dirname(dirname(__FILE__))));

// SMTP Settings (for welcome emails / platform notifications)
// Leave SMTP_HOST empty to fall back to PHP mail()
define('SMTP_HOST', '');           // e.g. 'smtp.gmail.com'
define('SMTP_PORT', 587);          // 587 (TLS), 465 (SSL), 25 (plain)
define('SMTP_ENC', 'tls');         // 'tls', 'ssl', or ''
define('SMTP_USER', '');           // SMTP username / email
define('SMTP_PASS', '');           // SMTP password
define('SMTP_FROM', 'noreply@cloudkart24.com');
define('SMTP_FROM_NAME', 'CloudKart');

// App secret — used to sign HMAC auth tokens (change before going live)
define('APP_SECRET', 'change-this-to-a-64-char-random-secret-before-production');

// Platform version — bump this after each dist/ rebuild
define('PLATFORM_VERSION', '1.1.0');

// Migration secret — required to call POST /api/migrate
// Change this to a strong random string before going live
define('MIGRATION_SECRET', 'change-this-to-a-strong-secret');

// Timezone
date_default_timezone_set('Asia/Kolkata');

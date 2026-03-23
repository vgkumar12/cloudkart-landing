<?php
/**
 * Settings Migration Script
 * Migrates settings from config.php to database
 * Run this once to populate settings table
 */

// Include config from api folder (prioritize local config.php)
$possiblePaths = [
    __DIR__ . '/config.php',              // Local: upgrade/frontend/api/config.php (PRIORITY)
    dirname(__DIR__) . '/config.php',     // Local: alternative path calculation
    __DIR__ . '/../../../config.php',     // Root: upgrade/frontend/api/ -> upgrade/frontend/ -> upgrade/ -> root (fallback)
    dirname(__DIR__, 3) . '/config.php',  // Root: alternative path calculation (fallback)
];

$configPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        $configPath = $path;
        break;
    }
}

if (!$configPath) {
    echo "❌ config.php not found. Tried the following paths:\n";
    foreach ($possiblePaths as $path) {
        echo "   - $path\n";
    }
    echo "\nPlease ensure config.php exists in the project root.\n";
    exit(1);
}

require_once $configPath;
echo "✓ Loaded config from: $configPath\n\n";
require_once __DIR__ . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Models\Setting;

Autoloader::register();

echo "=== Settings Migration from config.php ===\n\n";

// Define all settings from config.php with their types and descriptions
$settingsToMigrate = [
    // Site Information
    ['key' => 'site_url', 'value' => defined('SITE_URL') ? SITE_URL : '', 'type' => 'string', 'description' => 'Main site URL'],
    ['key' => 'site_name', 'value' => defined('SITE_NAME') ? SITE_NAME : '', 'type' => 'string', 'description' => 'Site name'],
    ['key' => 'site_description', 'value' => defined('SITE_DESCRIPTION') ? SITE_DESCRIPTION : '', 'type' => 'string', 'description' => 'Site description'],
    ['key' => 'site_logo', 'value' => defined('SITE_LOGO') ? SITE_LOGO : '', 'type' => 'string', 'description' => 'Site logo path'],
    ['key' => 'site_phone', 'value' => defined('SITE_PHONE') ? SITE_PHONE : '', 'type' => 'string', 'description' => 'Site contact phone'],
    ['key' => 'site_email', 'value' => defined('SITE_EMAIL') ? SITE_EMAIL : '', 'type' => 'string', 'description' => 'Site contact email'],
    ['key' => 'site_address', 'value' => defined('SITE_ADDRESS') ? SITE_ADDRESS : '', 'type' => 'string', 'description' => 'Site contact address'],
    ['key' => 'site_hours', 'value' => defined('SITE_HOURS') ? SITE_HOURS : '', 'type' => 'string', 'description' => 'Business hours'],
    
    // Company Information
    ['key' => 'company_name', 'value' => defined('COMPANY_NAME') ? COMPANY_NAME : '', 'type' => 'string', 'description' => 'Company name'],
    
    // Email Settings
    ['key' => 'from_email', 'value' => defined('FROM_EMAIL') ? FROM_EMAIL : '', 'type' => 'string', 'description' => 'Default from email address'],
    ['key' => 'from_name', 'value' => defined('FROM_NAME') ? FROM_NAME : '', 'type' => 'string', 'description' => 'Default from name'],
    ['key' => 'admin_email', 'value' => defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '', 'type' => 'string', 'description' => 'Admin email address'],
    
    // SMTP Configuration
    ['key' => 'smtp_enabled', 'value' => defined('SMTP_ENABLED') ? SMTP_ENABLED : false, 'type' => 'boolean', 'description' => 'Enable SMTP email'],
    ['key' => 'smtp_host', 'value' => defined('SMTP_HOST') ? SMTP_HOST : '', 'type' => 'string', 'description' => 'SMTP host'],
    ['key' => 'smtp_port', 'value' => defined('SMTP_PORT') ? SMTP_PORT : 587, 'type' => 'number', 'description' => 'SMTP port'],
    ['key' => 'smtp_username', 'value' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '', 'type' => 'string', 'description' => 'SMTP username'],
    ['key' => 'smtp_password', 'value' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '', 'type' => 'string', 'description' => 'SMTP password'],
    ['key' => 'smtp_encryption', 'value' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls', 'type' => 'string', 'description' => 'SMTP encryption (tls/ssl)'],
    
    // Google OAuth
    ['key' => 'google_client_id', 'value' => defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '', 'type' => 'string', 'description' => 'Google OAuth Client ID'],
    ['key' => 'google_client_secret', 'value' => defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '', 'type' => 'string', 'description' => 'Google OAuth Client Secret'],
    ['key' => 'google_redirect_uri', 'value' => defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : '', 'type' => 'string', 'description' => 'Google OAuth Redirect URI'],
    ['key' => 'google_signin_enabled', 'value' => defined('GOOGLE_SIGNIN_ENABLED') ? GOOGLE_SIGNIN_ENABLED : false, 'type' => 'boolean', 'description' => 'Enable Google Sign-In'],
    
    // Order Settings
    ['key' => 'order_prefix', 'value' => defined('ORDER_PREFIX') ? ORDER_PREFIX : 'SC', 'type' => 'string', 'description' => 'Order number prefix'],
    ['key' => 'require_login_for_orders', 'value' => defined('REQUIRE_LOGIN_FOR_ORDERS') ? REQUIRE_LOGIN_FOR_ORDERS : true, 'type' => 'boolean', 'description' => 'Require login to place orders'],
    ['key' => 'max_order_quantity', 'value' => defined('MAX_ORDER_QUANTITY') ? MAX_ORDER_QUANTITY : 10, 'type' => 'number', 'description' => 'Maximum order quantity'],
    ['key' => 'min_delivery_days', 'value' => defined('MIN_DELIVERY_DAYS') ? MIN_DELIVERY_DAYS : 1, 'type' => 'number', 'description' => 'Minimum delivery days'],
    ['key' => 'max_delivery_days', 'value' => defined('MAX_DELIVERY_DAYS') ? MAX_DELIVERY_DAYS : 30, 'type' => 'number', 'description' => 'Maximum delivery days'],
    ['key' => 'minimum_order_value', 'value' => defined('MINIMUM_ORDER_VALUE') ? MINIMUM_ORDER_VALUE : 2000, 'type' => 'number', 'description' => 'Minimum order value in rupees'],
    
    // Payment Settings
    ['key' => 'currency', 'value' => defined('CURRENCY') ? CURRENCY : 'INR', 'type' => 'string', 'description' => 'Currency code'],
    ['key' => 'currency_symbol', 'value' => defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : '₹', 'type' => 'string', 'description' => 'Currency symbol'],
    ['key' => 'free_delivery_threshold', 'value' => defined('FREE_DELIVERY_THRESHOLD') ? FREE_DELIVERY_THRESHOLD : 500, 'type' => 'number', 'description' => 'Free delivery threshold in rupees'],
    ['key' => 'gpay_number', 'value' => defined('GPAY_NUMBER') ? GPAY_NUMBER : '', 'type' => 'string', 'description' => 'GPay phone number'],
    ['key' => 'gpay_upi_id', 'value' => defined('GPAY_UPI_ID') ? GPAY_UPI_ID : '', 'type' => 'string', 'description' => 'GPay UPI ID'],
    ['key' => 'scheme_payment_qr', 'value' => defined('SCHEME_PAYMENT_QR') ? SCHEME_PAYMENT_QR : '', 'type' => 'string', 'description' => 'Scheme payment QR code path'],
    ['key' => 'fund_qr_path', 'value' => defined('SCHEME_PAYMENT_QR') ? SCHEME_PAYMENT_QR : '', 'type' => 'string', 'description' => 'Fund scheme QR code path (alias)'],
    ['key' => 'fund_upi_id', 'value' => defined('GPAY_UPI_ID') ? GPAY_UPI_ID : '', 'type' => 'string', 'description' => 'Fund scheme UPI ID (alias)'],
    ['key' => 'fund_upi_number', 'value' => defined('GPAY_NUMBER') ? GPAY_NUMBER : '', 'type' => 'string', 'description' => 'Fund scheme UPI number (alias)'],
    
    // File Upload Settings
    ['key' => 'upload_enabled', 'value' => defined('UPLOAD_ENABLED') ? UPLOAD_ENABLED : false, 'type' => 'boolean', 'description' => 'Enable file uploads'],
    ['key' => 'upload_path', 'value' => defined('UPLOAD_PATH') ? UPLOAD_PATH : 'uploads/', 'type' => 'string', 'description' => 'Upload directory path'],
    ['key' => 'max_file_size', 'value' => defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : 5242880, 'type' => 'number', 'description' => 'Maximum file size in bytes'],
    ['key' => 'allowed_extensions', 'value' => defined('ALLOWED_EXTENSIONS') ? ALLOWED_EXTENSIONS : ['jpg', 'jpeg', 'png', 'gif'], 'type' => 'json', 'description' => 'Allowed file extensions'],
    
    // Logging Settings
    ['key' => 'log_enabled', 'value' => defined('LOG_ENABLED') ? LOG_ENABLED : true, 'type' => 'boolean', 'description' => 'Enable logging'],
    ['key' => 'log_level', 'value' => defined('LOG_LEVEL') ? LOG_LEVEL : 'INFO', 'type' => 'string', 'description' => 'Log level (DEBUG, INFO, WARNING, ERROR)'],
    ['key' => 'log_path', 'value' => defined('LOG_PATH') ? LOG_PATH : 'logs/', 'type' => 'string', 'description' => 'Log directory path'],
    ['key' => 'log_max_size', 'value' => defined('LOG_MAX_SIZE') ? LOG_MAX_SIZE : 10485760, 'type' => 'number', 'description' => 'Maximum log file size in bytes'],
    
    // Cache Settings
    ['key' => 'cache_enabled', 'value' => defined('CACHE_ENABLED') ? CACHE_ENABLED : false, 'type' => 'boolean', 'description' => 'Enable caching'],
    ['key' => 'cache_path', 'value' => defined('CACHE_PATH') ? CACHE_PATH : 'cache/', 'type' => 'string', 'description' => 'Cache directory path'],
    ['key' => 'cache_lifetime', 'value' => defined('CACHE_LIFETIME') ? CACHE_LIFETIME : 3600, 'type' => 'number', 'description' => 'Cache lifetime in seconds'],
    
    // Security Settings
    ['key' => 'force_ssl', 'value' => defined('FORCE_SSL') ? FORCE_SSL : false, 'type' => 'boolean', 'description' => 'Force SSL/HTTPS'],
    ['key' => 'ssl_redirect_code', 'value' => defined('SSL_REDIRECT_CODE') ? SSL_REDIRECT_CODE : 301, 'type' => 'number', 'description' => 'SSL redirect code (301 or 302)'],
    ['key' => 'enable_security_headers', 'value' => defined('ENABLE_SECURITY_HEADERS') ? ENABLE_SECURITY_HEADERS : true, 'type' => 'boolean', 'description' => 'Enable security headers'],
    
    // Environment Settings
    ['key' => 'environment', 'value' => defined('ENVIRONMENT') ? ENVIRONMENT : 'production', 'type' => 'string', 'description' => 'Environment (development/production)'],
    
    // Authentication Settings
    ['key' => 'email_verification_required', 'value' => defined('EMAIL_VERIFICATION_REQUIRED') ? EMAIL_VERIFICATION_REQUIRED : false, 'type' => 'boolean', 'description' => 'Require email verification'],
    ['key' => 'email_verification_expiry_hours', 'value' => defined('EMAIL_VERIFICATION_EXPIRY_HOURS') ? EMAIL_VERIFICATION_EXPIRY_HOURS : 24, 'type' => 'number', 'description' => 'Email verification expiry in hours'],
];

$migrated = 0;
$skipped = 0;
$errors = 0;

echo "Migrating " . count($settingsToMigrate) . " settings...\n\n";

foreach ($settingsToMigrate as $setting) {
    try {
        // Check if setting already exists
        $existing = Setting::get($setting['key']);
        
        if ($existing !== null) {
            // Update existing
            Setting::set(
                $setting['key'],
                $setting['value'],
                $setting['type'],
                $setting['description']
            );
            echo "✓ Updated: {$setting['key']}\n";
        } else {
            // Create new
            Setting::set(
                $setting['key'],
                $setting['value'],
                $setting['type'],
                $setting['description']
            );
            echo "✓ Created: {$setting['key']}\n";
            $migrated++;
        }
    } catch (Exception $e) {
        echo "✗ Error migrating {$setting['key']}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Migration Complete ===\n";
echo "Created: $migrated\n";
echo "Updated: " . (count($settingsToMigrate) - $migrated - $errors) . "\n";
echo "Errors: $errors\n";
echo "\n✅ Settings have been migrated to database!\n";
echo "You can now manage settings through the admin panel.\n";


<?php

/**
 * Sun Crackers - Configuration File
 * Production-ready configuration for SSL, Database, and SMTP settings
 */

// Define session settings FIRST (before any session operations)
// Note: We now use separate sessions for frontend and admin
// - Frontend sessions use: 'app_customer'
// - Admin sessions use: 'app_admin'
// Session name is determined dynamically based on request context via SessionHelper
define('SESSION_LIFETIME', 86400 * 30); // 30 days (increased from 7 days)

// Session initialization is now handled by SessionHelper based on request context
// DO NOT start session here - it will be started in index.php after determining context

// Include PHPMailer autoloader if vendor folder exists
// Suppress warnings if PHPMailer is not installed
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    @require_once __DIR__ . '/vendor/autoload.php';
}

// Include security functions
require_once __DIR__ . '/includes/security_functions.php';

// Include helper functions
require_once __DIR__ . '/includes/helpers.php';

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================

// SSL/HTTPS Configuration
if (!defined('FORCE_SSL')) {
    define('FORCE_SSL', false);
}
if (!defined('SSL_REDIRECT_CODE')) {
    define('SSL_REDIRECT_CODE', 301); // 301 = Permanent, 302 = Temporary
}
if (!defined('API_KEY')) {
    define('API_KEY', '68aSo1NMUYgyuLrasqiIFHVYDNXufVk8');
}
// Security Headers
define('ENABLE_SECURITY_HEADERS', true);

// JWT Configuration
// IMPORTANT: Change this to a strong random string in production!
// Generate with: bin2hex(random_bytes(32))
define('JWT_SECRET', 'suncrackers-jwt-secret-key-' . md5(__DIR__) . '-change-in-production');
define('JWT_EXPIRATION', 3600); // 1 hour
define('JWT_REFRESH_EXPIRATION', 30 * 24 * 3600); // 30 days

// Allowed Origins for API Access
// Can be set via environment variable or config file
// Format: Comma-separated list of origins, e.g., "https://example.com,https://www.example.com,http://localhost:3000"
// Wildcards supported: "*.example.com" matches all subdomains
// If ALLOWED_ORIGINS is empty or not set, all origins are allowed (development mode behavior)
// In production, it's recommended to explicitly set allowed origins
$allowedOriginsEnv = getenv('ALLOWED_ORIGINS');
if ($allowedOriginsEnv) {
    $allowedOrigins = array_map('trim', explode(',', $allowedOriginsEnv));
} else {
    // Default: Allow common development origins and auto-detect current origin
    $allowedOrigins = [
        'http://localhost:3000',
        'http://localhost',
    ];
    
    // Auto-detect current server origin if available
    if (isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $currentOrigin = $protocol . '://' . $host;
        if (!in_array($currentOrigin, $allowedOrigins)) {
            $allowedOrigins[] = $currentOrigin;
        }
        // Also add without port if port is present
        $hostWithoutPort = preg_replace('/:\d+$/', '', $host);
        if ($hostWithoutPort !== $host) {
            $originWithoutPort = $protocol . '://' . $hostWithoutPort;
            if (!in_array($originWithoutPort, $allowedOrigins)) {
                $allowedOrigins[] = $originWithoutPort;
            }
        }
    }
}
if (!defined('ALLOWED_ORIGINS')) {
    define('ALLOWED_ORIGINS', $allowedOrigins);
}

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

// Database Connection Settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'crackers1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Database Tables
define('TABLE_ORDERS', 'orders');
define('TABLE_ORDER_ITEMS', 'order_items');
define('TABLE_CUSTOMERS', 'customers');

// ============================================================================
// SMTP CONFIGURATION
// ============================================================================

// Email Configuration
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'mail.suncrackers.in');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'orders@suncrackers.in');
define('SMTP_PASSWORD', 'm5[@vRmr(s4LMcba');
define('SMTP_ENCRYPTION', 'ssl'); // 'tls' or 'ssl' - Port 465 typically uses SSL

// Email Settings
define('FROM_EMAIL', 'orders@suncrackers.in');
define('FROM_NAME', 'Sun Crackers Orders');
define('ADMIN_EMAIL', 'orders@suncrackers.in');
define('COMPANY_NAME', 'Sun Crackers');

// ============================================================================
// GOOGLE SIGN-IN CONFIGURATION
// ============================================================================

// Site Configuration (must be defined first)
define('SITE_URL', 'https://suncrackers.in'); // Change this to your actual domain

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', '331179999719-k1uah42a0rfib9le7574e6u26nvok3un.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', SITE_URL . '/auth/google-callback.php');

// Google Sign-In Settings
define('GOOGLE_SIGNIN_ENABLED', true);
define('REQUIRE_EMAIL_VERIFICATION', false); // Google emails are pre-verified

// Test Login Configuration (for local development when Google OAuth is not working)
define('TEST_LOGIN_ENABLED', true); // Set to false in production
define('DUMMY_PASSWORD', 'test123'); // Default password for test login
define('TEST_ADMIN_EMAIL', 'admin@suncrackers.in'); // Admin test account

// ============================================================================
// APPLICATION SETTINGS
// ============================================================================

// Site Information
define('SITE_NAME', 'Sun Crackers - Premium Diwali Crackers');
define('SITE_DESCRIPTION', 'Celebrate Diwali with premium quality crackers and fireworks');
define('SITE_LOGO', 'src/images/logo.png'); // Default site logo path
define('SITE_PHONE', '+91 79047 91220'); // Site contact phone number
define('SITE_EMAIL', 'orders@suncrackers.in'); // Site contact email
define('SITE_ADDRESS', '1/185-1, R.S.R Nagar, <br/>Pernaickenpatti, <br/>Sivakasi - 626189 <br/>Tamil Nadu '); // Site contact address
define('SITE_HOURS', 'Mon-Sat: 9AM-8PM'); // Site business hours

// Order Settings
define('ORDER_PREFIX', 'SC');
define('REQUIRE_LOGIN_FOR_ORDERS', true); // Set to true to require login for placing orders
define('MAX_ORDER_QUANTITY', 10);
define('MIN_DELIVERY_DAYS', 1);
define('MAX_DELIVERY_DAYS', 30);
define('MINIMUM_ORDER_VALUE', 2000); // Minimum order value in rupees

// Payment Settings
define('CURRENCY', 'INR');
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', '₹');
}
define('FREE_DELIVERY_THRESHOLD', 500);

// GPay / UPI Payment (used in customer emails)
if (!defined('GPAY_NUMBER')) {
    define('GPAY_NUMBER', ''); // e.g., '+91 98xxxxxx90'
}
if (!defined('GPAY_UPI_ID')) {
    define('GPAY_UPI_ID', ''); // e.g., 'suncrackers@oksbi'
}
if (!defined('SCHEME_PAYMENT_QR')) {
    define('SCHEME_PAYMENT_QR', 'src/images/upi-qr.png'); // Update with your QR code path
}

// Admin Settings
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'diwali2024'); // ⚠️ CHANGE THIS TO A SECURE PASSWORD!

// ============================================================================
// FILE UPLOAD CONFIGURATION
// ============================================================================

// Upload Settings
define('UPLOAD_ENABLED', false);
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// ============================================================================
// LOGGING CONFIGURATION
// ============================================================================

// Logging Settings
define('LOG_ENABLED', true);
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_PATH', __DIR__ . '/logs/');
define('LOG_MAX_SIZE', 10485760); // 10MB

// ============================================================================
// CACHE CONFIGURATION
// ============================================================================

// Cache Settings
define('CACHE_ENABLED', false);
define('CACHE_PATH', 'cache/');
define('CACHE_LIFETIME', 3600); // 1 hour

// ============================================================================
// ERROR REPORTING & DEBUGGING
// ============================================================================

// Development/Production Mode
define('ENVIRONMENT', 'development'); // 'development' or 'production'

// Error Reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_PATH . 'php_errors.log');
}

// ============================================================================
// TIMEZONE & LOCALE
// ============================================================================

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Set locale for number formatting
setlocale(LC_MONETARY, 'en_IN');

// ============================================================================
// AUTHENTICATION CONFIGURATION
// ============================================================================

// Email verification settings
define('EMAIL_VERIFICATION_REQUIRED', false);
define('EMAIL_VERIFICATION_EXPIRY_HOURS', 24);

// User roles
define('ROLE_CUSTOMER', 'customer');
define('ROLE_ADMIN', 'admin');

// ============================================================================
// INITIALIZATION
// ============================================================================

// Force SSL if enabled
forceSSL();

// Set security headers
setSecurityHeaders();

// Initialize database connection (optional - only when needed)
// You can call getDBConnection() when you need database access

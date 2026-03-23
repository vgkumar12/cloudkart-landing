<?php

/**
 * Settings Helper
 * Provides easy access to settings from database with fallback to config.php constants
 */

namespace App\Helpers;

use App\Models\Setting;

class SettingsHelper {
    
    private static $cache = [];
    
    /**
     * Get setting value with fallback to config constant
     * 
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @param string|null $configConstant Config constant name (if different from key)
     * @return mixed
     */
    public static function get(string $key, $default = null, ?string $configConstant = null) {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        // Try to get from database
        $value = Setting::get($key);
        
        // If not found in database, fallback to config constant
        if ($value === null) {
            $constantName = $configConstant ?: strtoupper($key);
            
            // Convert key to constant format (site_name -> SITE_NAME)
            if (!$configConstant) {
                $constantName = strtoupper(str_replace(['-', ' '], '_', $key));
            }
            
            if (defined($constantName)) {
                $value = constant($constantName);
            } else {
                $value = $default;
            }
        }
        
        // Cache the value
        self::$cache[$key] = $value;
        
        return $value;
    }
    
    /**
     * Get all settings as associative array
     * 
     * @return array
     */
    public static function getAll(): array {
        $settings = Setting::getAllAsArray();
        
        // Fallback to config constants for missing settings
        $configMappings = [
            'site_url' => 'SITE_URL',
            'site_name' => 'SITE_NAME',
            'site_description' => 'SITE_DESCRIPTION',
            'site_logo' => 'SITE_LOGO',
            'site_phone' => 'SITE_PHONE',
            'site_email' => 'SITE_EMAIL',
            'site_address' => 'SITE_ADDRESS',
            'site_hours' => 'SITE_HOURS',
            'company_name' => 'COMPANY_NAME',
            'from_email' => 'FROM_EMAIL',
            'from_name' => 'FROM_NAME',
            'admin_email' => 'ADMIN_EMAIL',
            'smtp_enabled' => 'SMTP_ENABLED',
            'smtp_host' => 'SMTP_HOST',
            'smtp_port' => 'SMTP_PORT',
            'smtp_username' => 'SMTP_USERNAME',
            'smtp_password' => 'SMTP_PASSWORD',
            'smtp_encryption' => 'SMTP_ENCRYPTION',
            'google_client_id' => 'GOOGLE_CLIENT_ID',
            'google_client_secret' => 'GOOGLE_CLIENT_SECRET',
            'google_redirect_uri' => 'GOOGLE_REDIRECT_URI',
            'google_signin_enabled' => 'GOOGLE_SIGNIN_ENABLED',
            'order_prefix' => 'ORDER_PREFIX',
            'require_login_for_orders' => 'REQUIRE_LOGIN_FOR_ORDERS',
            'max_order_quantity' => 'MAX_ORDER_QUANTITY',
            'min_delivery_days' => 'MIN_DELIVERY_DAYS',
            'max_delivery_days' => 'MAX_DELIVERY_DAYS',
            'minimum_order_value' => 'MINIMUM_ORDER_VALUE',
            'currency' => 'CURRENCY',
            'currency_symbol' => 'CURRENCY_SYMBOL',
            'free_delivery_threshold' => 'FREE_DELIVERY_THRESHOLD',
            'gpay_number' => 'GPAY_NUMBER',
            'gpay_upi_id' => 'GPAY_UPI_ID',
            'test_login_enabled' => 'TEST_LOGIN_ENABLED',
            'dummy_password' => 'DUMMY_PASSWORD',
            'environment' => 'ENVIRONMENT',
        ];
        
        foreach ($configMappings as $key => $constant) {
            if (!isset($settings[$key]) && defined($constant)) {
                $settings[$key] = constant($constant);
            }
        }
        
        return $settings;
    }
    
    /**
     * Clear cache (useful after settings update)
     */
    public static function clearCache(): void {
        self::$cache = [];
    }
    
    /**
     * Helper methods for common settings
     */
    public static function getSiteName(): string {
        return self::get('site_name', 'Sun Crackers', 'SITE_NAME');
    }
    
    public static function getCompanyName(): string {
        return self::get('company_name', 'Sun Crackers', 'COMPANY_NAME');
    }
    
    public static function getSiteEmail(): string {
        return self::get('site_email', 'orders@suncrackers.in', 'SITE_EMAIL');
    }
    
    public static function getSitePhone(): string {
        return self::get('site_phone', '+91 79047 91220', 'SITE_PHONE');
    }
    
    public static function getSiteAddress(): string {
        return self::get('site_address', '', 'SITE_ADDRESS');
    }
    
    public static function getFromEmail(): string {
        return self::get('from_email', 'orders@suncrackers.in', 'FROM_EMAIL');
    }
    
    public static function getFromName(): string {
        return self::get('from_name', 'Sun Crackers Orders', 'FROM_NAME');
    }
    
    public static function getAdminEmail(): string {
        return self::get('admin_email', 'orders@suncrackers.in', 'ADMIN_EMAIL');
    }
    
    public static function getOrderPrefix(): string {
        return self::get('order_prefix', 'SC', 'ORDER_PREFIX');
    }
    
    public static function getMinimumOrderValue(): float {
        return (float)self::get('minimum_order_value', 2000, 'MINIMUM_ORDER_VALUE');
    }
    
    public static function getCurrencySymbol(): string {
        return self::get('currency_symbol', '₹', 'CURRENCY_SYMBOL');
    }
    
    public static function getGoogleClientId(): string {
        return self::get('google_client_id', '', 'GOOGLE_CLIENT_ID');
    }
}


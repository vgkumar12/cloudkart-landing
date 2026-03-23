<?php

/**
 * Sun Crackers - Helper Functions
 * 
 * This file contains all utility and helper functions used throughout the application.
 * Extracted from config.php for better organization and maintainability.
 */

// ============================================================================
// AUTHENTICATION FUNCTIONS
// ============================================================================

/**
 * Require authentication for protected pages
 */
function requireAuth()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Require admin role
 */
function requireAdmin()
{
    requireAuth();

    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== ROLE_ADMIN) {
        header('Location: index.php?error=access_denied');
        exit;
    }
}

/**
 * Verify Google ID Token
 */
function verifyGoogleToken($idToken)
{
    try {
        // Google's token verification endpoint
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $idToken;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            logMessage("Google token verification failed with HTTP code: $httpCode", 'ERROR');
            return false;
        }

        $data = json_decode($response, true);

        // Verify the token is for our app
        if (isset($data['aud']) && $data['aud'] === GOOGLE_CLIENT_ID) {
            return [
                'google_id' => $data['sub'],
                'email' => $data['email'],
                'name' => $data['name'],
                'picture' => $data['picture'] ?? null,
                'email_verified' => $data['email_verified'] ?? false
            ];
        }

        logMessage("Google token verification failed: Invalid audience", 'ERROR');
        return false;
    } catch (Exception $e) {
        logMessage("Google token verification error: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Get or create user from Google data
 */
function getOrCreateUserFromGoogle($googleData)
{
    try {
        $conn = getDBConnection();

        // First, try to find user by Google ID
        $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ?");
        $stmt->execute([$googleData['google_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Update user info if needed
            $stmt = $conn->prepare("
                UPDATE users 
                SET name = ?, email = ?, picture_url = ?, email_verified = ?, last_login = NOW() 
                WHERE google_id = ?
            ");
            $stmt->execute([
                $googleData['name'],
                $googleData['email'],
                $googleData['picture'],
                $googleData['email_verified'],
                $googleData['google_id']
            ]);

            // Return updated user data
            $user['name'] = $googleData['name'];
            $user['email'] = $googleData['email'];
            $user['picture_url'] = $googleData['picture'];
            $user['email_verified'] = $googleData['email_verified'];

            return $user;
        }

        // Try to find user by email
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$googleData['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Link existing user to Google account
            $stmt = $conn->prepare("
                UPDATE users 
                SET google_id = ?, name = ?, picture_url = ?, email_verified = ?, last_login = NOW() 
                WHERE email = ?
            ");
            $stmt->execute([
                $googleData['google_id'],
                $googleData['name'],
                $googleData['picture'],
                $googleData['email_verified'],
                $googleData['email']
            ]);

            // Return updated user data
            $user['google_id'] = $googleData['google_id'];
            $user['name'] = $googleData['name'];
            $user['picture_url'] = $googleData['picture'];
            $user['email_verified'] = $googleData['email_verified'];

            return $user;
        }

        // Create new user
        $stmt = $conn->prepare("
            INSERT INTO users (google_id, email, name, picture_url, email_verified, is_verified, is_active, role, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, 1, 'customer', NOW())
        ");
        $stmt->execute([
            $googleData['google_id'],
            $googleData['email'],
            $googleData['name'],
            $googleData['picture'],
            $googleData['email_verified']
        ]);

        $userId = $conn->lastInsertId();

        // Return new user data
        return [
            'id' => $userId,
            'google_id' => $googleData['google_id'],
            'email' => $googleData['email'],
            'name' => $googleData['name'],
            'picture_url' => $googleData['picture'],
            'email_verified' => $googleData['email_verified'],
            'is_verified' => true,
            'is_active' => true,
            'role' => 'customer'
        ];
    } catch (Exception $e) {
        logMessage("Error getting/creating user from Google: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Login user and set session
 */
function loginUser($user)
{
    // Session is already started in config.php

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_picture'] = $user['picture_url'] ?? null;
    $_SESSION['is_logged_in'] = true;
    $_SESSION['login_time'] = time();

    // Update login count
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE users SET login_count = login_count + 1, last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
    } catch (Exception $e) {
        logMessage("Error updating login count: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    // Session is already started in config.php

    return isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
}

/**
 * Get current logged in user
 */
function getCurrentUser()
{
    if (!isLoggedIn()) {
        return false;
    }

    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'],
        'name' => $_SESSION['user_name'],
        'picture_url' => $_SESSION['user_picture'] ?? null
    ];
}

/**
 * Logout user
 */
function logoutUser()
{
    // Session is already started in config.php

    session_destroy();
}

/**
 * Test login function for local development
 * @param string $email
 * @param string $password
 * @return array
 */
function testLogin($email, $password)
{
    if (!TEST_LOGIN_ENABLED) {
        return [
            'success' => false,
            'message' => 'Test login is disabled'
        ];
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid email format'
        ];
    }

    // Check password
    if ($password !== DUMMY_PASSWORD) {
        return [
            'success' => false,
            'message' => 'Invalid password. Use: ' . DUMMY_PASSWORD
        ];
    }

    try {
        $conn = getDBConnection();

        // Check if user exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Create new test user
            $name = explode('@', $email)[0]; // Use email prefix as name
            $phone = 'test' . rand(100000, 999999); // Generate shorter unique phone for test users
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, phone, is_verified, is_active, created_at) 
                VALUES (?, ?, ?, 1, 1, NOW())
            ");
            $stmt->execute([$name, $email, $phone]);

            // Get the newly created user
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            logMessage("Test user created: $email", 'INFO');
        }

        if ($user) {
            // Log the user in
            loginUser($user);

            logMessage("Test login successful for: $email", 'INFO');

            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => $user
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to create or retrieve user'
        ];
    } catch (Exception $e) {
        print_r($e);
        exit;
        logMessage("Test login error: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'message' => 'Database error occurred'
        ];
    }
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Get application base web path (e.g., /suncrackers-m), stripping subfolders like /pages or /admin
 */
function getAppBasePath()
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    if ($base === '/' || $base === '\\') { $base = ''; }
    // Strip trailing /pages or /admin if script is under those
    if ($base !== '') {
        if (preg_match('#/(pages|admin)$#', $base)) {
            $base = rtrim(substr($base, 0, strrpos($base, '/')), '/');
        }
    }
    return $base;
}

/**
 * Get image URL with fallback to site logo
 * @param string $image_path The image path to check
 * @param string $fallback_type Type of fallback ('logo', 'placeholder', 'default')
 * @return string The final image URL
 */
function getImageWithFallback($image_path, $fallback_type = 'logo')
{
    // Normalize and check using absolute filesystem path, return project-relative URL
    $basePath = getAppBasePath();

    if (!empty($image_path)) {
        $relativePath = ltrim($image_path, '/');
        $fsPath = __DIR__ . '/../' . $relativePath;
        if (file_exists($fsPath)) {
            return ($basePath !== '' ? $basePath . '/' : '/') . $relativePath;
        }
    }

    // Fallback options
    switch ($fallback_type) {
        case 'logo':
            return ($basePath !== '' ? $basePath . '/' : '/') . ltrim(SITE_LOGO, '/');
        case 'placeholder':
            return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDMwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxjaXJjbGUgY3g9IjE1MCIgY3k9IjE1MCIgcj0iNDAiIGZpbGw9IiNGRjZCMzUiLz4KPHN2ZyB4PSIxMzAiIHk9IjEzMCIgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiB2aWV3Qm94PSIwIDAgMjQgMjQiIGZpbGw9IndoaXRlIj4KPHN0YXIgY3g9IjEyIiBjeT0iMTIiIHI9IjgiIGZpbGw9IndoaXRlIi8+CjwvZz4KPC9zdmc+';
        case 'default':
        default:
            return 'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=300&h=300&fit=crop&auto=format';
    }
}

/**
 * Get product image with fallback
 * @param string $product_image_path The product image path
 * @return string The final image URL
 */
function getProductImage($product_image_path)
{
    return getImageWithFallback($product_image_path, 'logo');
}

/**
 * Get combo pack image with fallback
 * @param string $combo_image_path The combo pack image path
 * @return string The final image URL
 */
function getComboImage($combo_image_path)
{
    return getImageWithFallback($combo_image_path, 'logo');
}

/**
 * Get thumbnail URL for a combo image if available, else return full image URL.
 */
function getComboThumbnail($combo_image_path)
{
    $basePath = getAppBasePath();
    if (empty($combo_image_path)) {
        return getImageWithFallback(SITE_LOGO, 'logo');
    }
    $relative = ltrim($combo_image_path, '/');
    $dir = dirname($relative);
    $file = basename($relative);
    $thumbRel = ($dir === '.' ? '' : $dir . '/') . 'thumbs/' . $file;
    $thumbFs = __DIR__ . '/../' . $thumbRel;
    if (file_exists($thumbFs)) {
        return ($basePath !== '' ? $basePath . '/' : '/') . $thumbRel;
    }
    return getComboImage($combo_image_path);
}

/**
 * Get thumbnail URL for a product image if available, else return full image URL.
 * Expects DB path like 'uploads/products/filename.ext'
 */
function getProductThumbnail($product_image_path)
{
    $basePath = getAppBasePath();
    if (empty($product_image_path)) {
        return getImageWithFallback(SITE_LOGO, 'logo');
    }
    $relative = ltrim($product_image_path, '/');
    // Build thumbs relative path
    $dir = dirname($relative);
    $file = basename($relative);
    $thumbRel = ($dir === '.' ? '' : $dir . '/') . 'thumbs/' . $file;
    $thumbFs = __DIR__ . '/../' . $thumbRel;
    if (file_exists($thumbFs)) {
        return ($basePath !== '' ? $basePath . '/' : '/') . $thumbRel;
    }
    // Fallback to full image URL
    return getImageWithFallback($product_image_path, 'logo');
}

/**
 * Get database connection
 */
function getDBConnection()
{
    static $conn = null;

    if ($conn === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            if (ENVIRONMENT === 'development') {
                die("Database connection failed: " . $e->getMessage());
            } else {
                error_log("Database connection failed: " . $e->getMessage());
                die("Database connection error. Please try again later.");
            }
        }
    }

    return $conn;
}

/**
 * Retrieve a setting value from the settings table with optional caching.
 */
function getSettingValue($key, $default = null)
{
    global $APP_SETTINGS_CACHE;
    if (!is_array($APP_SETTINGS_CACHE)) {
        $APP_SETTINGS_CACHE = array();
    }

    $normalizedKey = strtolower($key);
    if (array_key_exists($normalizedKey, $APP_SETTINGS_CACHE)) {
        return $APP_SETTINGS_CACHE[$normalizedKey];
    }

    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute(array($key));
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null) {
            $APP_SETTINGS_CACHE[$normalizedKey] = $default;
            return $default;
        }
        $APP_SETTINGS_CACHE[$normalizedKey] = $value;
        return $value;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Persist a setting value to the settings table and update the cache.
 */
function setSettingValue($key, $value, $type = 'string', $description = null)
{
    global $APP_SETTINGS_CACHE;
    if (!is_array($APP_SETTINGS_CACHE)) {
        $APP_SETTINGS_CACHE = array();
    }

    $allowedTypes = array('string', 'number', 'boolean', 'json');
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'string';
    }

    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute(array($key));
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $update = $conn->prepare("UPDATE settings SET setting_value = ?, setting_type = ?, description = ?, updated_at = NOW() WHERE id = ?");
            $update->execute(array($value, $type, $description, (int)$existingId));
        } else {
            $insert = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $insert->execute(array($key, $value, $type, $description));
        }

        $APP_SETTINGS_CACHE[strtolower($key)] = $value;
        return true;
    } catch (Exception $e) {
        logMessage('Failed to save setting "' . $key . '": ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Force SSL redirect
 */
function forceSSL()
{
    if (FORCE_SSL && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
        $redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: " . $redirect, true, SSL_REDIRECT_CODE);
        exit();
    }
}

/**
 * Set security headers
 */
function setSecurityHeaders()
{
    if (!ENABLE_SECURITY_HEADERS) return;

    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');

    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');

    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy (with YouTube and Google Sign-In support)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://accounts.google.com https://apis.google.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; img-src 'self' data: https:; frame-src 'self' https://www.youtube.com https://youtube.com https://accounts.google.com; connect-src 'self' https://accounts.google.com https://oauth2.googleapis.com; media-src 'self' https://www.youtube.com https://youtube.com;");

    // HSTS (HTTP Strict Transport Security)
    if (FORCE_SSL) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

// ============================================================================
// ORDER & SUBSCRIPTION FUNCTIONS
// ============================================================================

/**
 * Generate order number
 */
function generateOrderNumber()
{
    return ORDER_PREFIX . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Generate subscription number
 */
function generateSubscriptionNumber($subscriptionId, $schemeId)
{
    $schemeId = (int)$schemeId;
    $subscriptionId = (int)$subscriptionId;
    return sprintf('SC%03d-%05d', $schemeId, $subscriptionId);
}

/**
 * Ensure subscription number value exists
 */
function ensureSubscriptionNumberValue($subscriptionId, $schemeId, $currentValue = null)
{
    if (!empty($currentValue)) {
        return $currentValue;
    }
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT subscription_number FROM scheme_subscriptions WHERE id = ? LIMIT 1");
        $stmt->execute(array((int)$subscriptionId));
        $existing = $stmt->fetchColumn();
        if (!empty($existing)) {
            return $existing;
        }
        $newNumber = generateSubscriptionNumber($subscriptionId, $schemeId);
        $update = $conn->prepare("UPDATE scheme_subscriptions SET subscription_number = ? WHERE id = ? LIMIT 1");
        $update->execute(array($newNumber, (int)$subscriptionId));
        return $newNumber;
    } catch (Exception $e) {
        logMessage('Failed to ensure subscription number for subscription_id=' . (int)$subscriptionId . ': ' . $e->getMessage(), 'ERROR');
        return $currentValue ?: ('SUB-' . (int)$subscriptionId);
    }
}

// ============================================================================
// DATA PROCESSING FUNCTIONS
// ============================================================================

/**
 * Sanitize input data
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 */
function formatCurrency($amount)
{
    $numericAmount = _normalizeAmount($amount);
    $formattedNumber = _formatNumberIndian($numericAmount);
    $symbol = defined('CURRENCY_SYMBOL') ? (string)CURRENCY_SYMBOL : (string) '₹';
    return '₹' . $formattedNumber;
}

/**
 * Normalize incoming amount to float.
 * Accepts numbers or strings like "₹1,23,456.78" and returns 123456.78
 */
function _normalizeAmount($amount)
{
    if (is_numeric($amount)) {
        return (float)$amount;
    }
    if (is_string($amount)) {
        // Remove currency symbols, commas, spaces and any non-numeric except dot and minus
        $clean = preg_replace('/[^0-9.\-]/', '', $amount);
        if ($clean === '' || $clean === '-' || $clean === '.' || $clean === '-.') {
            return 0.0;
        }
        return (float)$clean;
    }
    return 0.0;
}

/**
 * Format number using Indian numbering system with two decimals.
 * Example: 1234567.5 -> 12,34,567.50
 */
function _formatNumberIndian($number)
{
    $negative = $number < 0;
    $number = abs($number);

    $parts = explode('.', number_format($number, 2, '.', ''));
    $intPart = $parts[0];
    $decPart = isset($parts[1]) ? $parts[1] : '00';

    $len = strlen($intPart);
    if ($len <= 3) {
        $resultInt = $intPart;
    } else {
        $last3 = substr($intPart, -3);
        $rest = substr($intPart, 0, $len - 3);
        // Add commas after every 2 digits in the rest
        $restGrouped = '';
        while (strlen($rest) > 2) {
            $restGrouped = ',' . substr($rest, -2) . $restGrouped;
            $rest = substr($rest, 0, -2);
        }
        $resultInt = $rest . ($rest !== '' ? ',' : '') . $last3;
        // Prepend remaining grouped part
        if ($restGrouped !== '') {
            $resultInt = $rest . $restGrouped . ',' . $last3;
            if ($rest === '') {
                // Remove leading comma if rest empty
                $resultInt = ltrim($restGrouped, ',') . ',' . $last3;
            }
        }
    }

    $result = $resultInt . '.' . $decPart;
    return $negative ? '-' . $result : $result;
}

// ============================================================================
// EMAIL FUNCTIONS
// ============================================================================

/**
 * Send email notification using SMTP
 */
function sendEmailNotification($to, $subject, $message, $pdf_attachment = null, $headers = [])
{
    // Get SMTP enabled setting with fallback
    $smtpEnabled = true; // Default
    if (class_exists('App\\Helpers\\SettingsHelper')) {
        $smtpEnabled = \App\Helpers\SettingsHelper::get('smtp_enabled', true, 'SMTP_ENABLED');
    } elseif (defined('SMTP_ENABLED')) {
        $smtpEnabled = SMTP_ENABLED;
    }
    
    if (!$smtpEnabled) {
        logMessage("Email sending disabled: SMTP is not enabled", 'INFO');
        return false;
    }

    try {
        // Use PHPMailer if available
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return sendEmailWithPHPMailer($to, $subject, $message, $pdf_attachment, $headers);
        } else {
            // SMTP is enabled but PHPMailer is not available
            // Don't use mail() fallback as it won't use SMTP settings
            logMessage("Email sending failed: PHPMailer library not found. Please install PHPMailer or disable SMTP in settings.", 'ERROR');
            return false;
        }
    } catch (Exception $e) {
        logMessage("Email sending failed: " . $e->getMessage(), 'ERROR');
        return false;
    } catch (\Exception $e) {
        logMessage("Email sending failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Send email using PHPMailer (if available)
 */
function sendEmailWithPHPMailer($to, $subject, $message, $pdf_attachment = null, $headers = [])
{
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Get SMTP settings from database with fallback to config
        $smtpHost = SMTP_HOST ?? 'localhost';
        $smtpPort = SMTP_PORT ?? 587;
        $smtpUsername = SMTP_USERNAME ?? '';
        $smtpPassword = SMTP_PASSWORD ?? '';
        $smtpEncryption = SMTP_ENCRYPTION ?? 'tls';
        $fromEmail = FROM_EMAIL ?? 'noreply@example.com';
        $fromName = FROM_NAME ?? 'Sun Crackers';
        
        if (class_exists('App\\Helpers\\SettingsHelper')) {
            $smtpHost = \App\Helpers\SettingsHelper::get('smtp_host', $smtpHost, 'SMTP_HOST');
            $smtpPort = \App\Helpers\SettingsHelper::get('smtp_port', $smtpPort, 'SMTP_PORT');
            $smtpUsername = \App\Helpers\SettingsHelper::get('smtp_username', $smtpUsername, 'SMTP_USERNAME');
            $smtpPassword = \App\Helpers\SettingsHelper::get('smtp_password', $smtpPassword, 'SMTP_PASSWORD');
            $smtpEncryption = \App\Helpers\SettingsHelper::get('smtp_encryption', $smtpEncryption, 'SMTP_ENCRYPTION');
            $fromEmail = \App\Helpers\SettingsHelper::getFromEmail();
            $fromName = \App\Helpers\SettingsHelper::getFromName();
        }
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = $smtpEncryption;
        $mail->Port = (int)$smtpPort;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        // Add PDF attachment if provided
        if ($pdf_attachment && file_exists($pdf_attachment)) {
            $mail->addAttachment($pdf_attachment, basename($pdf_attachment));
        }

        $mail->send();
        logMessage("Email sent successfully to: $to", 'INFO');
        return true;
    } catch (Exception $e) {
        logMessage("PHPMailer error: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Send email using basic mail() function (fallback - only used when SMTP is disabled)
 * Note: This function requires proper mail server configuration in php.ini
 */
function sendEmailWithBasicMail($to, $subject, $message, $pdf_attachment = null, $headers = [])
{
    // Check if mail server is properly configured
    $iniSendmailPath = ini_get('sendmail_path');
    if (empty($iniSendmailPath) && (PHP_OS_FAMILY === 'Windows')) {
        logMessage("Email sending failed: mail() function requires SMTP configuration or sendmail. Use PHPMailer with SMTP settings instead.", 'ERROR');
        return false;
    }
    
    // Generate boundary for multipart message
    $boundary = md5(uniqid(time()));

    // Get FROM_EMAIL and FROM_NAME with fallback to settings
    $fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@example.com';
    $fromName = defined('FROM_NAME') ? FROM_NAME : 'Sun Crackers';
    
    if (class_exists('App\\Helpers\\SettingsHelper')) {
        $fromEmail = \App\Helpers\SettingsHelper::getFromEmail();
        $fromName = \App\Helpers\SettingsHelper::getFromName();
    }

    $defaultHeaders = [
        'MIME-Version: 1.0',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail
    ];

    // If PDF attachment is provided, use multipart message
    if ($pdf_attachment && file_exists($pdf_attachment)) {
        $defaultHeaders[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        // Build multipart message
        $body = "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $message . "\r\n\r\n";

        // Add PDF attachment
        $pdf_content = file_get_contents($pdf_attachment);
        $pdf_filename = basename($pdf_attachment);
        $pdf_encoded = chunk_split(base64_encode($pdf_content));

        $body .= "--$boundary\r\n";
        $body .= "Content-Type: application/pdf; name=\"$pdf_filename\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n";
        $body .= "Content-Disposition: attachment; filename=\"$pdf_filename\"\r\n\r\n";
        $body .= $pdf_encoded . "\r\n";
        $body .= "--$boundary--\r\n";

        $message = $body;
    } else {
        // Regular HTML email
        $defaultHeaders[] = 'Content-Type: text/html; charset=UTF-8';
    }

    $headers = array_merge($defaultHeaders, $headers);

    // Suppress warnings and handle errors gracefully
    // Turn off error display for mail() function to prevent warnings in JSON responses
    $oldErrorReporting = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
    $oldDisplayErrors = ini_get('display_errors');
    ini_set('display_errors', '0');
    
    $result = @mail($to, $subject, $message, implode("\r\n", $headers));
    
    // Restore error reporting
    error_reporting($oldErrorReporting);
    ini_set('display_errors', $oldDisplayErrors);
    
    if ($result) {
        logMessage("Email sent successfully to: $to (basic mail)", 'INFO');
    } else {
        // Clear any errors from mail() function
        error_clear_last();
        logMessage("Failed to send email to: $to (basic mail). Please check mail server configuration.", 'ERROR');
    }
    
    return $result;
}

/**
 * Send email notification to user
 */
function sendEmailNotificationToUser($userId, $subject, $message)
{
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['email']) {
            return sendEmailNotification($user['email'], $subject, $message);
        }

        return false;
    } catch (Exception $e) {
        logMessage("Failed to send email notification to user: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Send order confirmation email
 */
function sendOrderConfirmationEmail($orderData, $customerData)
{
    try {
        // Get site/company info from settings
        $siteName = 'Sun Crackers';
        $companyName = 'Sun Crackers';
        $siteEmail = 'orders@suncrackers.in';
        $adminEmail = 'orders@suncrackers.in';
        
        if (class_exists('App\\Helpers\\SettingsHelper')) {
            $siteName = \App\Helpers\SettingsHelper::getSiteName();
            $companyName = \App\Helpers\SettingsHelper::getCompanyName();
            $siteEmail = \App\Helpers\SettingsHelper::getSiteEmail();
            $adminEmail = \App\Helpers\SettingsHelper::getAdminEmail();
        } elseif (defined('SITE_NAME')) {
            $siteName = SITE_NAME;
            $companyName = defined('COMPANY_NAME') ? COMPANY_NAME : $siteName;
            $siteEmail = defined('SITE_EMAIL') ? SITE_EMAIL : (defined('FROM_EMAIL') ? FROM_EMAIL : 'orders@suncrackers.in');
            $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : $siteEmail;
        }
        
        $subject = "Order Confirmation - " . $orderData['order_number'];
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #0F9B9B; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .order-details { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { background: #4B2E00; color: white; padding: 15px; text-align: center; font-size: 12px; }
                .highlight { color: #FF7A00; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>🎆 " . htmlspecialchars($siteName) . "</h1>
                <h2>Order Confirmation</h2>
            </div>
            
            <div class='content'>
                <p>Dear " . htmlspecialchars($customerData['name']) . ",</p>
                
                <p>Thank you for your order! We're excited to help you celebrate Diwali with our premium crackers.</p>
                
                <div class='order-details'>
                    <h3>Order Details</h3>
                    <p><strong>Order Number:</strong> <span class='highlight'>" . htmlspecialchars($orderData['order_number']) . "</span></p>
                    <p><strong>Order Date:</strong> " . date('d M Y, h:i A', strtotime($orderData['created_at'])) . "</p>
                    <p><strong>Total Amount:</strong> <span class='highlight'>" . formatCurrency($orderData['total_amount']) . "</span></p>
                    <p><strong>Status:</strong> " . ucfirst($orderData['status']) . "</p>
                </div>
                
                <div class='order-details'>
                    <h3>Delivery Information</h3>
                    <p><strong>Name:</strong> " . htmlspecialchars($customerData['name']) . "</p>
                    <p><strong>Phone:</strong> " . htmlspecialchars($customerData['phone']) . "</p>
                    <p><strong>Address:</strong> " . htmlspecialchars($customerData['address']) . "</p>
                    <p><strong>City:</strong> " . htmlspecialchars($customerData['city']) . "</p>
                    <p><strong>State:</strong> " . htmlspecialchars($customerData['state']) . "</p>
                    <p><strong>Pincode:</strong> " . htmlspecialchars($customerData['pincode']) . "</p>
                </div>
                
                <p>We'll process your order and contact you soon for delivery details.</p>
                
                <p>Thank you for choosing " . htmlspecialchars($companyName) . "!</p>
            </div>
            
            <div class='footer'>
                <p>" . htmlspecialchars($companyName) . "</p>
                <p>For any queries, contact us at: " . htmlspecialchars($siteEmail) . "</p>
            </div>
        </body>
        </html>";

        // Send to customer
        $customerEmailSent = sendEmailNotification($customerData['email'], $subject, $message);
        
        // Send notification to admin
        $adminSubject = "New Order Received - " . $orderData['order_number'];
        $adminMessage = "
        <html>
        <head><style>body { font-family: Arial, sans-serif; } .highlight { color: #FF7A00; font-weight: bold; }</style></head>
        <body>
            <h2>New Order Received</h2>
            <p><strong>Order Number:</strong> <span class='highlight'>" . htmlspecialchars($orderData['order_number']) . "</span></p>
            <p><strong>Customer:</strong> " . htmlspecialchars($customerData['name']) . "</p>
            <p><strong>Email:</strong> " . htmlspecialchars($customerData['email']) . "</p>
            <p><strong>Phone:</strong> " . htmlspecialchars($customerData['phone']) . "</p>
            <p><strong>Total Amount:</strong> <span class='highlight'>" . formatCurrency($orderData['total_amount']) . "</span></p>
            <p><strong>Order Date:</strong> " . date('d M Y, h:i A', strtotime($orderData['created_at'])) . "</p>
        </body>
        </html>";
        
        $adminEmailSent = sendEmailNotification($adminEmail, $adminSubject, $adminMessage);
        
        // Log the results
        if ($customerEmailSent) {
            logMessage("Order confirmation email sent to customer: " . $customerData['email'], 'INFO');
        } else {
            logMessage("Failed to send order confirmation email to customer: " . $customerData['email'], 'ERROR');
        }
        
        if ($adminEmailSent) {
            logMessage("Order notification email sent to admin", 'INFO');
        } else {
            logMessage("Failed to send order notification email to admin", 'ERROR');
        }
        
        return $customerEmailSent;
        
    } catch (Exception $e) {
        logMessage("Error sending order confirmation email: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// ============================================================================
// LOGGING & SECURITY FUNCTIONS
// ============================================================================

/**
 * Log messages
 */
function logMessage($message, $level = 'INFO')
{
    if (!LOG_ENABLED) return;

    $logLevels = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];
    if (!in_array(strtoupper($level), $logLevels)) {
        $level = 'INFO';
    }

    $logFile = LOG_PATH . 'app_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;

    // Create log directory if it doesn't exist
    if (!is_dir(LOG_PATH)) {
        // Suppress warnings and check result to avoid noisy output in UI
        $created = @mkdir(LOG_PATH, 0755, true);
        if (!$created && !is_dir(LOG_PATH)) {
            // If creation failed, fallback to PHP's default error_log
            error_log("Failed to create log directory: " . LOG_PATH);
            return; // Avoid file_put_contents to a non-existent path
        }
    }

    // Ensure directory is writable before attempting to write
    if (!is_writable(LOG_PATH)) {
        error_log("Log directory not writable: " . LOG_PATH);
        return;
    }

    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Generate random bytes safely
 */
function generateRandomBytesSafe($length)
{
    if (function_exists('random_bytes')) {
        try {
            return random_bytes($length);
        } catch (Exception $e) {
            // fallback below
        }
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if ($bytes !== false) {
            return $bytes;
        }
    }
    $bytes = '';
    for ($i = 0; $i < $length; $i++) {
        $bytes .= chr(mt_rand(0, 255));
    }
    return $bytes;
}

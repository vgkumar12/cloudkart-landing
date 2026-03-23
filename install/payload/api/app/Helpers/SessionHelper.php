<?php

/**
 * Session Helper
 * Manages separate sessions for frontend and admin contexts
 */

namespace App\Helpers;

class SessionHelper {
    /**
     * Determine if request is for admin or frontend
     * This must be called FIRST before any session operations
     */
    public static function getContext(?string $path = null): string {
        // 1. Check for X-App-Context header first (most reliable)
        // Check $_SERVER (standard PHP/FastCGI)
        $headerContext = $_SERVER['HTTP_X_APP_CONTEXT'] ?? $_SERVER['X_APP_CONTEXT'] ?? null;
        
        // Fallback to apache_request_headers if available
        if (!$headerContext && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (is_array($headers)) {
                foreach ($headers as $key => $value) {
                    if (strtolower($key) === 'x-app-context') {
                        $headerContext = $value;
                        break;
                    }
                }
            }
        }
        
        if ($headerContext === 'admin' || $headerContext === 'customer') {
            $detected = $headerContext === 'admin' ? 'admin' : 'frontend';
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                error_log("SessionHelper: Detected context '{$detected}' from header. URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
            }
            return $detected;
        }

        // 3. Path-based detection (most reliable for API calls)
    if ($path === null) {
        $path = $_SERVER['REQUEST_URI'] ?? '';
    }
    
    // API Route detection
    if (stripos($path, '/api/') !== false) {
        if (stripos($path, '/api/admin/') !== false) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                error_log("SessionHelper: Detected context 'admin' from API path: {$path}");
            }
            return 'admin';
        }
        // All other /api/ calls are frontend
        return 'frontend';
    }

    // 4. Fallback for non-API calls (document refreshing)
    if (stripos($path, '/admin') !== false || stripos($path, 'admin.html') !== false) {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            error_log("SessionHelper: Detected context 'admin' from document path fallback: {$path}");
        }
        return 'admin';
    }
    
    // 5. Fallback: Referer (only for non-API or if path matching failed)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (stripos($referer, '/admin') !== false || stripos($referer, 'admin.html') !== false) {
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            error_log("SessionHelper: Detected context 'admin' from Referer fallback: " . substr($referer, 0, 50));
        }
        return 'admin';
    }

    return 'frontend';
}
    
    /**
     * Get session name for context
     */
    public static function getSessionName(string $context): string {
        return ($context === 'admin') ? 'sa_admin' : 'sa_customer';
    }
    
    /**
     * Get cookie path for context (to ensure complete isolation)
     * Detects deployment subfolder and adjusts cookie path accordingly
     */
    public static function getCookiePath(string $context): string {
        // ALWAYS use root path to prevent fragmentation and ensure backend/frontend visibility.
        // This resolves issues where cookies set in /api/auth are not visible in /api/cart or vice versa.
        return '/';
    }
    
    /**
     * Check which session cookie is present in the request
     */
    public static function getPresentCookieName(): ?string {
        $adminCookie = 'sa_admin';
        $customerCookie = 'sa_customer';
        
        if (isset($_COOKIE[$adminCookie])) {
            return $adminCookie;
        }
        if (isset($_COOKIE[$customerCookie])) {
            return $customerCookie;
        }
        
        return null;
    }
    
    /**
     * Initialize session for the given context
     * CRITICAL: This ensures complete isolation - each context ONLY reads its own cookie
     * 
     * IMPORTANT FOR SINGLE-BROWSER SCENARIOS:
     * When using the same browser for both frontend and admin (different tabs),
     * BOTH cookies will be sent with every request. This method ensures:
     * 1. We temporarily remove the other context's cookie from $_COOKIE
     * 2. We explicitly set session_id() from the expected cookie
     * 3. We call session_start() which will only see the cookie we want
     * 4. We verify we got the correct session (not the other context's)
     * 5. We restore the other cookie to $_COOKIE (for reference, session already initialized)
     */
    public static function startSession(string $context): void {
        $sessionName = self::getSessionName($context);
        $cookiePath = self::getCookiePath($context);
        $lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : (86400 * 30);
        $otherSessionName = ($context === 'admin') ? 'sa_customer' : 'sa_admin';
        $referer = $_SERVER['HTTP_REFERER'] ?? 'none';
        
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            error_log("SessionHelper: Starting session for '{$context}'. Referer: {$referer}");
        }
        
        // STEP 1: ALWAYS close any existing session completely
        if (session_status() === PHP_SESSION_ACTIVE) {
            $currentName = session_name();
            
            // If already using correct session, verify it's truly correct and return
            if ($currentName === $sessionName) {
                // Additional verification: ensure session ID matches expected cookie
                $expectedCookieId = $_COOKIE[$sessionName] ?? null;
                $currentSessionId = session_id();
                
                // If we have an expected cookie and it matches, we're good
                if (!$expectedCookieId || $currentSessionId === $expectedCookieId) {
                    self::refreshSessionCookie();
                    return;
                }
                
                // Session name matches but session ID doesn't - need to fix it
                // Fall through to restart session
            }
            
            // Close current session - CRITICAL for isolation
            session_write_close();
            $_SESSION = []; // Clear all data
        }
        
        // STEP 2: CRITICAL - Handle cookie isolation for single-browser scenarios
        // In a single browser, BOTH cookies will be sent with every request
        // We MUST ensure we only read the cookie for the current context
        
        // Store both cookie values BEFORE modifying $_COOKIE
    $expectedCookieId = $_COOKIE[$sessionName] ?? null;
    $otherCookieId = $_COOKIE[$otherSessionName] ?? null;

    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        error_log("SessionHelper: Cookie state BEFORE start - " . json_encode([
            'context' => $context,
            'session_name' => $sessionName,
            'expected_id' => $expectedCookieId ? substr($expectedCookieId, 0, 10) . '...' : 'null',
            'all_cookies' => array_keys($_COOKIE)
        ]));
    }
        
        // STEP 3: Configure session settings BEFORE starting
        $sameSite = 'Lax';
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            $sameSite = (defined('FORCE_SSL') && FORCE_SSL) ? 'None' : 'Lax';
        }
        
        // Set session name FIRST - this is critical
        session_name($sessionName);
        
        // Configure cookie parameters
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => (defined('FORCE_SSL') && FORCE_SSL) ? true : false,
            'httponly' => true,
            'samesite' => $sameSite
        ]);
        
        ini_set('session.gc_maxlifetime', $lifetime);
        ini_set('session.cookie_lifetime', $lifetime);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        
        // STEP 4: CRITICAL - Remove ALL other context cookies from $_COOKIE to prevent PHP from reading them
        // In single-browser scenarios, multiple cookies starting with 'sa_' or 'app_' might be sent
        // We MUST ensure PHP only sees the ONE cookie for the current context
        foreach ($_COOKIE as $name => $value) {
            if ($name !== $sessionName && (strpos($name, 'sa_') === 0 || strpos($name, 'app_') === 0 || $name === 'PHPSESSID' || $name === 'session_id')) {
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    error_log("SessionHelper: Hiding cookie '{$name}' from PHP for context '{$context}'");
                }
                unset($_COOKIE[$name]); // Hide from PHP session_start()
                
                // If there's an active session with the wrong name, close it
                if (session_status() === PHP_SESSION_ACTIVE && session_name() === $name) {
                    session_write_close();
                    $_SESSION = [];
                }
            }
        }
        
        // STEP 4.5: REMOVED - Proactive cleanup is no longer needed since we enforce root path '/'
        // Previous logic was deleting valid cookies on some environments.
        /* 
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development' && !headers_sent()) {
            $cleanupPaths = ['/api', '/api/admin', '/admin', '/'];
            foreach ($cleanupPaths as $path) {
                if ($path !== $cookiePath) {
                    setcookie($sessionName, '', time() - 3600, $path);
                    setcookie($otherSessionName, '', time() - 3600, $path);
                }
            }
        }
        */
        
        // STEP 5: Explicitly set session ID from expected cookie (if it exists)
        if ($expectedCookieId) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                error_log("SessionHelper: Using existing session ID from cookie '{$sessionName}': " . substr($expectedCookieId, 0, 10) . "...");
            }
            session_id($expectedCookieId);
        }
        
        // STEP 6: Start session
        session_start();
        
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            error_log("SessionHelper: Session started. Name: " . session_name() . ", ID: " . session_id() . ", user_id: " . ($_SESSION['user_id'] ?? 'none'));
        }
        
        // Get the actual session ID that was used
        $actualSessionId = session_id();
        
        // STEP 7: Restore the other cookie in $_COOKIE AFTER session is started
        // This allows verification, but session is already initialized with correct cookie
        if ($otherCookieId !== null) {
            $_COOKIE[$otherSessionName] = $otherCookieId;
        }
        
        // STEP 8: CRITICAL VERIFICATION - Ensure we're using the correct session
        // Perform ALL checks BEFORE restoring the other cookie to $_COOKIE
        
        // Check 1: Session ID must match expected cookie (if we had one)
        if ($expectedCookieId && $actualSessionId !== $expectedCookieId) {
            // Session ID mismatch - close and restart with correct ID
            // IMPORTANT: Preserve $_SESSION data if it exists
            $preservedData = $_SESSION;
            session_write_close();
            session_name($sessionName);
            session_id($expectedCookieId);
            // Ensure other cookie is still removed
            if (isset($_COOKIE[$otherSessionName])) {
                unset($_COOKIE[$otherSessionName]);
            }
            session_start();
            // Restore session data if we had any
            if (!empty($preservedData)) {
                $_SESSION = array_merge($_SESSION, $preservedData);
            }
            $actualSessionId = session_id();
            
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                error_log("SessionHelper: Session ID mismatch corrected. Expected: {$expectedCookieId}, Got: {$actualSessionId}, Preserved data: " . (empty($preservedData) ? 'none' : 'yes'));
            }
        }
        
        // Check 2: Session ID must NOT match the other context's cookie
        // This is critical for single-browser scenarios where both cookies are sent
        if ($otherCookieId && $actualSessionId === $otherCookieId) {
            // SECURITY BREACH: We read the wrong context's session!
            // This should never happen, but if it does, destroy it immediately
            
            session_write_close();
            $_SESSION = [];
            
            // Destroy the wrong cookie on all possible paths to prevent future confusion
            $paths = ['/api/admin', '/api', '/'];
            foreach ($paths as $path) {
                setcookie($otherSessionName, '', [
                    'expires' => time() - 3600,
                    'path' => $path,
                    'domain' => '',
                    'secure' => false,
                    'httponly' => true,
                    'samesite' => $sameSite
                ]);
            }
            
            // Force create a completely new session for this context
            session_id(''); // Force new ID
            session_name($sessionName); // Re-assert name
            // Ensure other cookie is removed
            if (isset($_COOKIE[$otherSessionName])) {
                unset($_COOKIE[$otherSessionName]);
            }
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => $cookiePath,
                'domain' => '',
                'secure' => (defined('FORCE_SSL') && FORCE_SSL) ? true : false,
                'httponly' => true,
                'samesite' => $sameSite
            ]);
            session_start();
            $actualSessionId = session_id();
            
            if (defined('ENVIRONMENT')) {
                error_log("SessionHelper: SECURITY - Prevented wrong session! Context: {$context}, Expected: {$sessionName}, Blocked: {$otherSessionName}, New ID: {$actualSessionId}");
            }
        }
        
        // Check 3: Session name must match expected
        $actualSessionName = session_name();
        if ($actualSessionName !== $sessionName) {
            // Session name mismatch - this is a critical error
            session_write_close();
            $_SESSION = [];
            
            // Restart with correct session name
            session_name($sessionName);
            // Ensure other cookie is removed
            if (isset($_COOKIE[$otherSessionName])) {
                unset($_COOKIE[$otherSessionName]);
            }
            if ($expectedCookieId) {
                session_id($expectedCookieId);
            }
            session_start();
            $actualSessionId = session_id();
            $actualSessionName = session_name();
            
            if (defined('ENVIRONMENT')) {
                error_log("SessionHelper: CRITICAL - Session name mismatch corrected. Expected: {$sessionName}, Got: {$actualSessionName}");
            }
        }
        
        // STEP 9: DO NOT restore the other cookie in $_COOKIE
        // We removed it to prevent PHP from reading it, and we should keep it removed
        // The session is already correctly initialized with the right cookie
        // Restoring it could cause confusion in subsequent session operations
        // Other code should not need to access the other context's cookie value
        
        // Debug logging
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            $debugInfo = [
                'context' => $context,
                'session_name' => session_name(),
                'session_id' => session_id(),
                'expected_cookie' => $expectedCookieId ? 'present' : 'absent',
                'other_cookie' => $otherCookieId ? 'present' : 'absent',
                'user_id' => $_SESSION['user_id'] ?? 'none',
                'cookie_path' => $cookiePath
            ];
            error_log("SessionHelper: " . json_encode($debugInfo));
        }
    }
    
    /**
     * Refresh session cookie expiration
     * MUST be called after startSession() to ensure correct context
     * Also destroys the other context's cookie to maintain isolation
     */
    public static function refreshSessionCookie(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return; // No active session to refresh
        }
        
        $currentSessionName = session_name();
        $sessionId = session_id();
        
        if (!$sessionId) {
            return; // No session ID to set
        }
        
        $lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : (86400 * 30);
        $sameSite = 'Lax';
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            $sameSite = (defined('FORCE_SSL') && FORCE_SSL) ? 'None' : 'Lax';
        }
        
        // Determine context from current session name
        $context = ($currentSessionName === 'sa_admin') ? 'admin' : 'frontend';
        $cookiePath = self::getCookiePath($context);
        $otherSessionName = ($context === 'admin') ? 'sa_customer' : 'sa_admin';
        $otherCookiePath = self::getCookiePath($context === 'admin' ? 'frontend' : 'admin');
        
        // DO NOT destroy the other context's cookie during normal cookie refresh
        // Both cookies can coexist in the browser - we only need to ensure PHP reads the correct one
        // Cookie destruction should only happen if we detect a security breach (wrong session read)
        
        // Set/refresh the current context's cookie
        // CRITICAL: Ensure headers haven't been sent yet
        if (headers_sent($file, $line)) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                error_log("SessionHelper::refreshSessionCookie: ⚠️ WARNING - Headers already sent! File: {$file}, Line: {$line}");
            }
            return; // Can't set cookie if headers already sent
        }
        
        // CRITICAL: Delete ALL existing cookies with the same name on ALL possible paths
        // This prevents multiple cookies with the same name from accumulating in the browser
        // When multiple cookies with the same name exist, the browser sends all of them,
        // which confuses PHP's session handling
        
        // Detect base path for subfolder deployments (with error handling)
        try {
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/api/index.php';
            $scriptName = str_replace('\\', '/', $scriptName);
            $scriptDir = dirname($scriptName);
            
            if ($scriptDir === '.' || $scriptDir === '\\' || $scriptDir === '/') {
                $scriptDir = '/';
            }
            
            $scriptDir = '/' . trim(str_replace('\\', '/', $scriptDir), '/');
            if ($scriptDir === '/' || $scriptDir === '') {
                $scriptDir = '/';
            }
            
            $lastDir = basename($scriptDir);
            if ($lastDir === 'api' && $scriptDir !== '/api') {
                $basePath = dirname($scriptDir);
            } else {
                $basePath = $scriptDir;
            }
            
            $basePath = '/' . trim(str_replace('\\', '/', $basePath), '/');
            if ($basePath === '/' || $basePath === '') {
                $basePath = '';
            }
        } catch (\Exception $e) {
            error_log("SessionHelper::refreshSessionCookie() path detection error: " . $e->getMessage());
            $basePath = '';
        }
        
        // Clean up cookies on all possible paths (root and subfolder deployments)
        $pathsToClean = ['/', '/api', '/api/admin'];
        if ($basePath !== '' && $basePath !== '/') {
            // Also clean up subfolder paths
            $pathsToClean[] = $basePath;
            $pathsToClean[] = $basePath . '/api';
            $pathsToClean[] = $basePath . '/api/admin';
        }
        
        // Remove duplicates from paths array
        $pathsToClean = array_unique($pathsToClean);
        
        foreach ($pathsToClean as $cleanPath) {
            // Always delete on all paths first to ensure clean state
            // Even if it's the same path, we'll set it fresh below
            try {
                @setcookie(
                    $currentSessionName,
                    '',
                    [
                        'expires' => time() - 3600, // Expire in the past (delete)
                        'path' => $cleanPath,
                        'domain' => '',
                        'secure' => (defined('FORCE_SSL') && FORCE_SSL) ? true : false,
                        'httponly' => true,
                        'samesite' => $sameSite
                    ]
                );
            } catch (\Exception $e) {
                // Silently continue if cookie deletion fails for a specific path
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    error_log("SessionHelper: Failed to delete cookie on path {$cleanPath}: " . $e->getMessage());
                }
            }
        }
        
        // Now set the cookie with the correct path (this will be the only one)
        try {
            $cookieResult = @setcookie(
                $currentSessionName,
                $sessionId,
                [
                    'expires' => time() + $lifetime,
                    'path' => $cookiePath, // Use context-specific path
                    'domain' => '', // Empty domain means current domain
                    'secure' => (defined('FORCE_SSL') && FORCE_SSL) ? true : false,
                    'httponly' => true,
                    'samesite' => $sameSite
                ]
            );
            
            // Debug: Log if cookie was set successfully
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                if ($cookieResult) {
                    error_log("SessionHelper::refreshSessionCookie: ✅ Cookie set successfully - name: {$currentSessionName}, id: " . substr($sessionId, 0, 20) . "..., path: {$cookiePath}");
                } else {
                    error_log("SessionHelper::refreshSessionCookie: ⚠️ WARNING - setcookie() returned FALSE! name: {$currentSessionName}, id: {$sessionId}, path: {$cookiePath}");
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail - session might still work if cookie was set previously
            error_log("SessionHelper::refreshSessionCookie: ERROR setting cookie - " . $e->getMessage() . " (name: {$currentSessionName}, path: {$cookiePath})");
        }
    }
    
    /**
     * Auto-detect context from request and start appropriate session
     */
    public static function startSessionForRequest(?string $path = null): void {
        $context = self::getContext($path);
        self::startSession($context);
    }
    
    /**
     * Verify that the current session matches the expected context
     * Returns true if correct, false if wrong context detected
     */
    public static function verifySessionContext(string $expectedContext): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        $expectedSessionName = self::getSessionName($expectedContext);
        $actualSessionName = session_name();
        
        if ($actualSessionName !== $expectedSessionName) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                error_log("SessionHelper: Context mismatch! Expected: {$expectedContext} ({$expectedSessionName}), Got: {$actualSessionName}");
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Force switch to correct session context (use with caution)
     * This will close current session and start the correct one
     */
    public static function forceCorrectSession(string $context): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
            $_SESSION = [];
        }
        self::startSession($context);
    }
}


<?php

/**
 * Authentication Middleware
 * Validates user authentication
 */

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Helpers\SessionHelper;
use PDO;

class AuthMiddleware {
    /**
     * Check if user is authenticated
     */
    public static function handle(Request $request): bool {
        // Determine expected context from request object (uses header + path)
        $context = $request->context();
        
        // Debug logging in development
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            $cookiesReceived = [];
            foreach ($_COOKIE as $name => $value) {
                if (strpos($name, 'sa_') === 0 || strpos($name, 'app_') === 0 || $name === 'PHPSESSID') {
                    $cookiesReceived[$name] = substr($value, 0, 15) . '...';
                }
            }
            $contextSource = $_SERVER['HTTP_X_APP_CONTEXT'] ?? $_SERVER['X_APP_CONTEXT'] ?? 'none';
            error_log("AuthMiddleware: Path " . $request->path() . ", Context: {$context}, Header: {$contextSource}, Cookies: " . json_encode($cookiesReceived));
        }
        
        // Verify current session matches expected context
        // If not, force switch to correct session
        if (!SessionHelper::verifySessionContext($context)) {
            SessionHelper::forceCorrectSession($context);
        } else {
            // Just ensure session is started (might already be active from index.php)
            SessionHelper::startSession($context);
        }
        
        // Refresh session cookie expiration on each authenticated request
        // This keeps the session alive as long as the user is active
        SessionHelper::refreshSessionCookie();
        
        // Double-check we're using the correct session
        if (!SessionHelper::verifySessionContext($context)) {
            $response = new Response();
            $response->error('Session context mismatch', 500);
            return false;
        }
        
        // Check session for user ID
        $userId = $_SESSION['user_id'] ?? null;
        
        // Debug logging in development
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            error_log("AuthMiddleware: Session status - session_name: " . session_name() . ", session_id: " . session_id() . ", user_id: " . ($userId ?? 'none') . ", session_data: " . json_encode(array_keys($_SESSION)));
        }
        
        if (!$userId) {
            $response = new Response();
            $response->unauthorized('Authentication required');
            return false;
        }
        
        // Verify user exists and is active
        try {
            $conn = Database::getConnection();
            $stmt = $conn->prepare("SELECT id, email, name, role, is_active FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $response = new Response();
                $response->unauthorized('Invalid or inactive user');
                return false;
            }
            
            // Store user in request for later use
            $request->user = $user;
            
            return true;
        } catch (\Exception $e) {
            $response = new Response();
            $response->error('Authentication error: ' . $e->getMessage(), 500);
            return false;
        }
    }
    
    /**
     * Check if user is admin
     */
    public static function admin(Request $request): bool {
        if (!self::handle($request)) {
            return false;
        }
        
        $user = $request->user ?? null;
        if (!$user || ($user['role'] ?? 'customer') !== 'admin') {
            $response = new Response();
            $response->forbidden('Admin access required');
            return false;
        }
        
        return true;
    }
}




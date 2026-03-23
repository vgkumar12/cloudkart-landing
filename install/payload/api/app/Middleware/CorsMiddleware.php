<?php

/**
 * CORS Middleware
 * Handles Cross-Origin Resource Sharing
 */

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class CorsMiddleware {
    /**
     * Handle CORS preflight requests
     */
    public static function handle(Request $request): bool {
        // Get origin from request header
        $origin = $request->header('origin');
        
        // Also check $_SERVER directly as fallback
        if (!$origin && isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];
        }
        
        // If no origin header, try to get from referer
        if (!$origin) {
            $referer = $request->header('referer');
            if (!$referer && isset($_SERVER['HTTP_REFERER'])) {
                $referer = $_SERVER['HTTP_REFERER'];
            }
            if ($referer) {
                $parsed = parse_url($referer);
                if (isset($parsed['scheme']) && isset($parsed['host'])) {
                    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
                    $origin = $parsed['scheme'] . '://' . $parsed['host'] . $port;
                }
            }
        }
        
        // If still no origin, default to Vite dev server for development
        // This is required when using withCredentials: true
        if (!$origin) {
            $origin = 'http://localhost:3000';
        }
        
        // Always use specific origin (required for credentials)
        $allowedOrigin = $origin;
        
        // Set CORS headers directly using PHP header() function
        // This ensures headers are sent before any output
        // Use header_remove() to clear any existing CORS headers first
        header_remove('Access-Control-Allow-Origin');
        header_remove('Access-Control-Allow-Credentials');
        
        header("Access-Control-Allow-Origin: {$allowedOrigin}", true);
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH", true);
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, X-API-Key, x-api-key, X-App-Context", true);
        header("Access-Control-Allow-Credentials: true", true); // Safe to use since we have specific origin
        header("Access-Control-Max-Age: 86400", true); // 24 hours
        
        // Handle preflight OPTIONS request
        if ($request->method() === 'OPTIONS') {
            http_response_code(200);
            echo json_encode(['message' => 'OK']);
            exit;
        }
        
        return true; // Continue processing
    }
}


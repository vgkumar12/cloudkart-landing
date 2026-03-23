<?php

/**
 * Origin Validation Middleware
 * Validates that requests come from allowed origins
 */

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class OriginMiddleware {
    
    /**
     * Handle origin validation
     * 
     * @param Request $request HTTP request
     * @return bool True if valid, false otherwise
     */
    public static function handle(Request $request): bool {
        // In development mode, allow all origins for easier debugging
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            return true;
        }
        
        // Get allowed origins from config
        $allowedOrigins = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : [];
        
        // If no origins configured, allow all
        if (empty($allowedOrigins)) {
            return true;
        }
        
        // Get origin from request
        $origin = $request->header('origin');
        
        // If no origin header, try referer
        if (!$origin) {
            $referer = $request->header('referer');
            if ($referer) {
                $parsed = parse_url($referer);
                if (isset($parsed['scheme']) && isset($parsed['host'])) {
                    $origin = $parsed['scheme'] . '://' . $parsed['host'];
                    if (isset($parsed['port'])) {
                        $origin .= ':' . $parsed['port'];
                    }
                }
            }
        }
        
        // If still no origin, reject in production
        if (!$origin) {
            $response = new Response();
            $response->forbidden('Origin validation failed: No origin header');
            return false;
        }
        
        // Normalize origin (remove trailing slash if present)
        $origin = rtrim($origin, '/');
        
        // Check if origin is in allowed list
        $originAllowed = false;
        foreach ($allowedOrigins as $allowed) {
            // Normalize allowed origin (remove trailing slash)
            $allowed = rtrim($allowed, '/');
            
            // Exact match
            if ($origin === $allowed) {
                $originAllowed = true;
                break;
            }
            // Wildcard subdomain match (e.g., *.suncrackers.in)
            if (strpos($allowed, '*') !== false) {
                $pattern = str_replace('*', '.*', preg_quote($allowed, '/'));
                if (preg_match('/^' . $pattern . '$/', $origin)) {
                    $originAllowed = true;
                    break;
                }
            }
        }
        
        if (!$originAllowed) {
            $response = new Response();
            $response->forbidden('Origin validation failed: ' . $origin . ' is not allowed');
            return false;
        }
        
        return true;
    }
}


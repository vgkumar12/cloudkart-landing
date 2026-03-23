<?php

/**
 * Method Spoofing Middleware
 * Allows PUT/DELETE/PATCH requests to be sent as POST with _method parameter
 * This is necessary for hosting environments that block these HTTP methods
 */

namespace App\Middleware;

use App\Core\Request;

class MethodSpoofingMiddleware {
    /**
     * Handle method spoofing
     * Checks for _method parameter in POST requests and overrides REQUEST_METHOD
     */
    public static function handle(Request $request): bool {
        // Only process POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true;
        }
        
        // Check for _method in POST data first, then query string
        $spoofedMethod = null;
        
        // Check POST data
        if (isset($_POST['_method'])) {
            $spoofedMethod = $_POST['_method'];
        }
        // Check query string as fallback
        elseif (isset($_GET['_method'])) {
            $spoofedMethod = $_GET['_method'];
        }
        // Check JSON body
        elseif ($request->getContentType() === 'application/json') {
            $body = $request->all();
            if (isset($body['_method'])) {
                $spoofedMethod = $body['_method'];
            }
        }
        
        // If _method found, override REQUEST_METHOD
        if ($spoofedMethod) {
            $allowedMethods = ['PUT', 'DELETE', 'PATCH'];
            $spoofedMethod = strtoupper($spoofedMethod);
            
            if (in_array($spoofedMethod, $allowedMethods)) {
                // Store original method for logging
                $_SERVER['ORIGINAL_REQUEST_METHOD'] = 'POST';
                
                // Override the request method
                $_SERVER['REQUEST_METHOD'] = $spoofedMethod;
                
                // Log in development
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                    error_log("MethodSpoofing: POST request converted to {$spoofedMethod} for {$request->path()}");
                }
            }
        }
        
        return true;
    }
}

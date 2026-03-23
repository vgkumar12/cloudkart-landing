<?php

/**
 * API Key Middleware
 * Validates that requests have a valid API key
 */

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class ApiKeyMiddleware {
    
    /**
     * Handle API key validation
     * 
     * @param Request $request HTTP request
     * @return bool True if valid, false otherwise
     */
    public static function handle(Request $request): bool {
        // Skip API key check in development or if API_KEY is not defined
        if (!defined('API_KEY') || (defined('ENVIRONMENT') && ENVIRONMENT === 'development')) {
            return true;
        }
        
        // Get API key from header or query param
        $apiKey = $request->header('X-API-Key') ?: $request->input('api_key');
        
        if (!$apiKey || $apiKey !== API_KEY) {
            $response = new Response();
            $response->unauthorized('Invalid or missing API Key');
            return false;
        }
        
        return true;
    }
}

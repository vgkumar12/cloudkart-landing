<?php

/**
 * Rate Limiting Middleware
 * Prevents API abuse by limiting requests per IP
 */

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class RateLimitMiddleware {
    private static array $requests = [];
    private static int $maxRequests = 100; // per minute
    private static int $windowSeconds = 60;
    
    /**
     * Handle rate limiting
     */
    public static function handle(Request $request): bool {
        $ip = self::getClientIp($request);
        $now = time();
        
        // Clean old entries
        self::cleanOldEntries($now);
        
        // Check rate limit
        if (!isset(self::$requests[$ip])) {
            self::$requests[$ip] = [];
        }
        
        // Remove requests outside the time window
        self::$requests[$ip] = array_filter(
            self::$requests[$ip],
            fn($timestamp) => ($now - $timestamp) < self::$windowSeconds
        );
        
        // Check if limit exceeded
        if (count(self::$requests[$ip]) >= self::$maxRequests) {
            $response = new Response();
            $response->error('Rate limit exceeded. Please try again later.', 429);
            return false;
        }
        
        // Record this request
        self::$requests[$ip][] = $now;
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIp(Request $request): string {
        $ip = $request->header('x-forwarded-for');
        if ($ip) {
            $ips = explode(',', $ip);
            return trim($ips[0]);
        }
        
        $ip = $request->header('x-real-ip');
        if ($ip) {
            return $ip;
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Clean old entries
     */
    private static function cleanOldEntries(int $now): void {
        foreach (self::$requests as $ip => $timestamps) {
            self::$requests[$ip] = array_filter(
                $timestamps,
                fn($timestamp) => ($now - $timestamp) < self::$windowSeconds
            );
            
            if (empty(self::$requests[$ip])) {
                unset(self::$requests[$ip]);
            }
        }
    }
    
    /**
     * Set rate limit configuration
     */
    public static function setLimit(int $maxRequests, int $windowSeconds): void {
        self::$maxRequests = $maxRequests;
        self::$windowSeconds = $windowSeconds;
    }
}


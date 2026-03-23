<?php

/**
 * Role Middleware
 * Checks user roles and permissions
 */

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class RoleMiddleware {
    /**
     * Require admin role
     */
    public static function requireAdmin(Request $request): bool {
        // Log middleware execution
        error_log("RoleMiddleware::requireAdmin called for path: " . $request->path());
        
        // Check if user is authenticated first
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            error_log("RoleMiddleware: No user_id in session");
            $response = new Response();
            $response->error('Authentication required', 401);
            return false;
        }
        
        error_log("RoleMiddleware: User ID = $userId");
        
        // Check if user has admin role
        $userRole = $_SESSION['user_role'] ?? null;
        $isAdmin = $_SESSION['is_admin'] ?? false;
        
        error_log("RoleMiddleware: user_role = " . ($userRole ?? 'null') . ", is_admin = " . ($isAdmin ? 'true' : 'false'));
        
        if ($userRole !== 'admin' && !$isAdmin) {
            error_log("RoleMiddleware: Access denied - not admin");
            $response = new Response();
            $response->error('Admin access required', 403);
            return false;
        }
        
        error_log("RoleMiddleware: Access granted - user is admin");
        return true;
    }
    
    /**
     * Require customer role
     */
    public static function requireCustomer(Request $request): bool {
        // Check if user is authenticated first
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            $response = new Response();
            $response->error('Authentication required', 401);
            return false;
        }
        
        return true;
    }
}

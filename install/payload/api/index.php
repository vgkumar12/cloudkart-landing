<?php

/**
 * Entry Point for MVC Application
 * All requests are routed through this file
 */

// Start output buffering to capture any accidental output
ob_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Define base paths
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', __DIR__);

// Load configuration
if (file_exists(BASE_PATH . '/config.php')) {
    require_once BASE_PATH . '/config.php';
}

try {
    // Include autoloader
    require_once BASE_PATH . '/app/Core/Autoloader.php';
    
    // Register autoloader
    \App\Core\Autoloader::register();
    
    // Initialize session based on request context (admin vs frontend)
    $requestPath = $_SERVER['REQUEST_URI'] ?? '/';
    \App\Helpers\SessionHelper::startSessionForRequest($requestPath);
    
    // Create router
    $router = new \App\Core\Router();
    
    // Dynamically determine base path from SCRIPT_NAME
    $scriptName = $_SERVER['REQUEST_URI'] === $_SERVER['SCRIPT_NAME'] ? $_SERVER['SCRIPT_NAME'] : ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $basePath = dirname($scriptName);
    
    // Normalize base path for router
    if (basename($basePath) === 'api') {
        $basePath = dirname($basePath);
    }
    $basePath = '/' . trim($basePath, '/');
    if ($basePath === '/') {
        $basePath = '';
    }
    $router->setBasePath($basePath);
    
    // Register global middleware
    // IMPORTANT: Method spoofing MUST be first to override REQUEST_METHOD before routing
    $router->middleware('cors', [\App\Middleware\CorsMiddleware::class, 'handle']);
    $router->middleware('origin', [\App\Middleware\OriginMiddleware::class, 'handle']);
    $router->middleware('ratelimit', [\App\Middleware\RateLimitMiddleware::class, 'handle']);
    $router->middleware('apikey', [\App\Middleware\ApiKeyMiddleware::class, 'handle']);
    $router->middleware('auth', [\App\Middleware\AuthMiddleware::class, 'handle']);
    $router->middleware('admin', [\App\Middleware\RoleMiddleware::class, 'requireAdmin']);
    
    // Include routes (routes file will use $router)
    require_once BASE_PATH . '/routes/api.php';
    
    // Dispatch request
    $router->dispatch();
} catch (\Throwable $e) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    
    error_log("BACKEND FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());

    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error: ' . $e->getMessage(),
        'error' => [
            'type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'stack' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $e->getTraceAsString() : null
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

<?php

/**
 * Entry Point for CloudKart SaaS Platform API
 * Handles landing site registration, billing, and licensing.
 */

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
    require_once APP_PATH . '/Core/Autoloader.php';
    
    // Register autoloader
    \App\Core\Autoloader::register();
    
    // Create router
    $router = new \App\Core\Router();

    // Compute basePath from the physical path of this file relative to the document root.
    // This works reliably on cPanel/shared hosting where SCRIPT_NAME may omit subdirectories.
    $docRoot  = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $apiDir   = realpath(__DIR__);
    if ($docRoot && $apiDir && strpos($apiDir, $docRoot) === 0) {
        $basePath = str_replace('\\', '/', substr($apiDir, strlen($docRoot)));
    } else {
        // Fallback: derive from SCRIPT_NAME
        $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    }
    $basePath = '/' . trim($basePath, '/');
    if ($basePath === '/') $basePath = '';
    $router->setBasePath($basePath);
    
    // Register global middleware
    $router->middleware('cors', [\App\Middleware\CorsMiddleware::class, 'handle']);
    
    // Include routes
    require_once BASE_PATH . '/routes/api.php';
    
    // Dispatch request
    $router->dispatch();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Platform API Error: ' . $e->getMessage()
    ]);
}

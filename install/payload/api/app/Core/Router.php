<?php

/**
 * Router Class
 * Handles routing and dispatches requests to controllers
 */

namespace App\Core;

class Router {
    private array $routes = [];
    private array $middleware = [];
    private string $basePath = '/upgrade/backend/public';
    
    /**
     * Add GET route
     */
    public function get(string $path, $handler, array $middleware = []): void {
        $this->addRoute('GET', $path, $handler, $middleware);
    }
    
    /**
     * Add POST route
     */
    public function post(string $path, $handler, array $middleware = []): void {
        $this->addRoute('POST', $path, $handler, $middleware);
    }
    
    /**
     * Add PUT route
     */
    public function put(string $path, $handler, array $middleware = []): void {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }
    
    /**
     * Add DELETE route
     */
    public function delete(string $path, $handler, array $middleware = []): void {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }
    
    /**
     * Add route for any method
     */
    public function any(string $path, $handler, array $middleware = []): void {
        $this->addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'], $path, $handler, $middleware);
    }
    
    /**
     * Add route
     */
    private function addRoute($methods, string $path, $handler, array $middleware = []): void {
        $methods = is_array($methods) ? $methods : [$methods];
        
        // Normalize path (remove leading/trailing slashes)
        $path = '/' . trim($path, '/');
        
        foreach ($methods as $method) {
            $this->routes[] = [
                'method' => strtoupper($method),
                'path' => $path,
                'pattern' => $this->pathToRegex($path),
                'handler' => $handler,
                'middleware' => $middleware
            ];
        }
    }
    
    /**
     * Add global middleware
     * @param string $name
     * @param callable|array|string $handler
     */
    public function middleware(string $name, $handler): void {
        // Convert array/string to callable if needed
        if (is_array($handler) && count($handler) === 2) {
            // Array format: [ClassName::class, 'method']
            $this->middleware[$name] = $handler;
        } elseif (is_string($handler) && strpos($handler, '::') !== false) {
            // String format: 'ClassName::method'
            [$class, $method] = explode('::', $handler, 2);
            $this->middleware[$name] = [$class, $method];
        } else {
            $this->middleware[$name] = $handler;
        }
    }
    
    /**
     * Dispatch request
     */
    public function dispatch(): void {
        $request = new Request();
        $method = $request->method();
        $path = $request->path();
        
        // Remove base path from request path if present
        if ($this->basePath !== '' && strpos($path, $this->basePath) === 0) {
            $path = substr($path, strlen($this->basePath));
        }
        
        // Normalize path
        $path = '/' . trim($path, '/');
        if ($path === '') {
            $path = '/';
        }
        
        // Handle OPTIONS for CORS (fallback if middleware doesn't catch it)
        if ($method === 'OPTIONS') {
            $origin = $request->header('origin');
            if (!$origin) {
                // Try to get from referer
                $referer = $request->header('referer');
                if ($referer) {
                    $parsed = parse_url($referer);
                    if (isset($parsed['scheme']) && isset($parsed['host'])) {
                        $origin = $parsed['scheme'] . '://' . $parsed['host'] . 
                                 (isset($parsed['port']) ? ':' . $parsed['port'] : '');
                    }
                }
            }
            $allowedOrigin = $origin ?: '*';
            header("Access-Control-Allow-Origin: {$allowedOrigin}");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, X-API-Key, x-api-key, X-App-Context");
            if ($allowedOrigin !== '*') {
                header("Access-Control-Allow-Credentials: true");
            }
            http_response_code(200);
            echo json_encode(['message' => 'OK']);
            exit;
        }
        
        // Find matching route
        $route = $this->findRoute($method, $path);
        
        // Debug: Log path and method for troubleshooting
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            error_log("Router: Method=$method, Path=$path, Route found=" . ($route ? 'yes' : 'no'));
        }
        
        if (!$route) {
            // Set CORS headers even for 404 responses
            $origin = $request->header('origin');
            if (!$origin) {
                $referer = $request->header('referer');
                if ($referer) {
                    $parsed = parse_url($referer);
                    if (isset($parsed['scheme']) && isset($parsed['host'])) {
                        $origin = $parsed['scheme'] . '://' . $parsed['host'] . 
                                 (isset($parsed['port']) ? ':' . $parsed['port'] : '');
                    }
                }
            }
            $allowedOrigin = $origin ?: '*';
            header("Access-Control-Allow-Origin: {$allowedOrigin}");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, X-API-Key, x-api-key, X-App-Context");
            if ($allowedOrigin !== '*') {
                header("Access-Control-Allow-Credentials: true");
            }
            
            $response = new Response();
            $response->notFound('Route not found: ' . $path);
            return;
        }
        
        // Extract route parameters
        $params = $this->extractParams($route['pattern'], $path);
        
        // Debug: Log middleware for this route
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            error_log("Router: Route found - Method=$method, Path=$path, Middleware=" . json_encode($route['middleware']));
        }
        
        // Execute middleware
        foreach ($route['middleware'] as $middlewareName) {
            if (isset($this->middleware[$middlewareName])) {
                $result = call_user_func($this->middleware[$middlewareName], $request);
                if ($result === false) {
                    return; // Middleware blocked the request
                }
            } elseif ($middlewareName === 'auth') {
                // Built-in auth middleware
                $result = \App\Middleware\AuthMiddleware::handle($request);
                if ($result === false) {
                    return;
                }
            } elseif ($middlewareName === 'admin') {
                // Built-in admin middleware
                $result = \App\Middleware\AuthMiddleware::admin($request);
                if ($result === false) {
                    return;
                }
            } elseif ($middlewareName === 'apikey') {
                // API Key middleware
                $result = \App\Middleware\ApiKeyMiddleware::handle($request);
                if ($result === false) {
                    return;
                }
            } elseif ($middlewareName === 'jwt') {
                // JWT middleware
                $result = \App\Middleware\JwtMiddleware::handle($request);
                if ($result === false) {
                    return;
                }
            } elseif ($middlewareName === 'origin') {
                // Origin validation middleware
                $result = \App\Middleware\OriginMiddleware::handle($request);
                if ($result === false) {
                    return;
                }
            }
        }
        
        // Execute handler (pass request so middleware-modified request is used)
        $this->executeHandler($route['handler'], $params, $request);
    }
    
    /**
     * Find matching route
     */
    private function findRoute(string $method, string $path): ?array {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                return $route;
            }
        }
        return null;
    }
    
    /**
     * Extract route parameters
     */
    private function extractParams(string $pattern, string $path): array {
        preg_match($pattern, $path, $matches);
        array_shift($matches); // Remove full match
        return $matches;
    }
    
    /**
     * Execute route handler
     */
    private function executeHandler($handler, array $params = [], ?Request $request = null): void {
        if (is_string($handler)) {
            // Format: "Controller@method"
            if (strpos($handler, '@') !== false) {
                [$controllerClass, $method] = explode('@', $handler);
                $controllerClass = 'App\\Controllers\\' . str_replace('/', '\\', $controllerClass);
                
                if (class_exists($controllerClass)) {
                    // Pass request object to controller constructor so middleware-modified request is used
                    $controller = new $controllerClass($request);
                    if (method_exists($controller, $method)) {
                        call_user_func_array([$controller, $method], $params);
                        return;
                    }
                }
            }
        } elseif (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }
        
        $response = new Response();
        $response->error('Invalid route handler', 500);
    }
    
    /**
     * Convert path pattern to regex
     * Handles route parameters like {id} and {id?}
     */
    private function pathToRegex(string $path): string {
        // Split the path into segments
        $segments = explode('/', trim($path, '/'));
        $patternParts = [];
        
        foreach ($segments as $segment) {
            if (preg_match('/^\{(\w+)\?\}$/', $segment, $matches)) {
                // Optional parameter: {param?}
                $patternParts[] = '([^\/]*)';
            } elseif (preg_match('/^\{(\w+)\}$/', $segment, $matches)) {
                // Required parameter: {param}
                $patternParts[] = '([^\/]+)';
            } else {
                // Literal segment - escape it
                $patternParts[] = preg_quote($segment, '/');
            }
        }
        
        $pattern = implode('\/', $patternParts);
        return '/^\/' . $pattern . '$/';
    }
    
    /**
     * Set base path
     */
    public function setBasePath(string $path): void {
        $this->basePath = $path;
    }
}


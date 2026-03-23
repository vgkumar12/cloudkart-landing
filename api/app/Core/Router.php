<?php

namespace App\Core;

class Router {
    protected $routes = [];
    protected $middleware = [];
    protected $basePath = '';

    public function get($path, $handler, $middleware = []) {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, $middleware = []) {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    protected function addRoute($method, $path, $handler, $middleware) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    public function middleware($name, $handler) {
        $this->middleware[$name] = $handler;
    }

    public function setBasePath($path) {
        $this->basePath = rtrim($path, '/');
    }

    public function dispatch() {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // Remove base path from URI
        if (!empty($this->basePath) && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }
        
        // Ensure uri starts with /
        if (empty($uri)) $uri = '/';
        if ($uri[0] !== '/') $uri = '/' . $uri;

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $uri) {
                // Execute middleware
                foreach ($route['middleware'] as $mw) {
                    if (isset($this->middleware[$mw])) {
                        call_user_func($this->middleware[$mw]);
                    }
                }

                // Execute handler
                if (is_callable($route['handler'])) {
                    call_user_func($route['handler']);
                } else {
                    list($controller, $action) = explode('@', $route['handler']);
                    $controller = "App\\Controllers\\" . $controller;
                    
                    if (!class_exists($controller)) {
                        http_response_code(500);
                        echo json_encode(['success' => false, 'message' => "Controller $controller not found"]);
                        return;
                    }
                    
                    $obj = new $controller();
                    $obj->$action();
                }
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Route not found']);
    }
}

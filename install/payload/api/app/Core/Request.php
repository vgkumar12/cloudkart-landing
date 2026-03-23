<?php

/**
 * HTTP Request Handler
 * Wraps $_GET, $_POST, $_SERVER, etc.
 */

namespace App\Core;

class Request {
    private array $get;
    private array $post;
    private array $server;
    private array $headers;
    private ?array $json = null;
    private ?string $context = null;
    
    /**
     * Authenticated user
     */
    public $user;
    
    public function __construct() {
        $this->get = $_GET ?? [];
        $this->server = $_SERVER ?? [];
        $this->headers = $this->parseHeaders();
        
        // Determine context (admin vs customer)
        $this->context = $this->header('x-app-context') ?? $this->detectContextFromPath();
        
        // For PUT/PATCH requests with multipart/form-data, PHP doesn't populate $_POST
        // We need to parse it manually
        if (in_array($this->method(), ['PUT', 'PATCH']) && $this->isMultipart()) {
            $this->post = $this->parseMultipartData();
        } else {
            $this->post = $_POST ?? [];
        }
        
        // Parse JSON body if Content-Type is application/json
        if ($this->isJson()) {
            $this->json = json_decode(file_get_contents('php://input'), true) ?? [];
        }
    }
    
    /**
     * Get query parameter
     */
    public function get(string $key, $default = null) {
        return $this->get[$key] ?? $default;
    }
    
    /**
     * Get POST parameter
     */
    public function post(string $key, $default = null) {
        return $this->post[$key] ?? $default;
    }
    
    /**
     * Get request parameter (GET, POST, or JSON)
     */
    public function input(string $key, $default = null) {
        // For JSON requests, check JSON body first
        if ($this->isJson()) {
            $jsonValue = $this->json($key);
            if ($jsonValue !== null) {
                return $jsonValue;
            }
        }
        // Then check POST, then GET
        return $this->post($key) ?? $this->get($key) ?? $default;
    }
    
    /**
     * Get JSON body parameter
     */
    public function json(string $key = null, $default = null) {
        if ($key === null) {
            return $this->json ?? [];
        }
        return $this->json[$key] ?? $default;
    }
    
    /**
     * Get all GET parameters
     */
    public function all(): array {
        return array_merge($this->get, $this->post, $this->json ?? []);
    }
    
    /**
     * Get request body (for JSON requests)
     */
    public function getBody(): array {
        if ($this->isJson()) {
            return $this->json ?? [];
        }
        return array_merge($this->post, $this->get);
    }
    
    /**
     * Get uploaded file
     */
    public function file(string $key): ?array {
        return $_FILES[$key] ?? null;
    }
    
    /**
     * Get request method
     */
    public function method(): string {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }
    
    /**
     * Check if request is GET
     */
    public function isGet(): bool {
        return $this->method() === 'GET';
    }
    
    /**
     * Check if request is POST
     */
    public function isPost(): bool {
        return $this->method() === 'POST';
    }
    
    /**
     * Check if request is PUT
     */
    public function isPut(): bool {
        return $this->method() === 'PUT';
    }
    
    /**
     * Check if request is DELETE
     */
    public function isDelete(): bool {
        return $this->method() === 'DELETE';
    }
    
    /**
     * Get request URI
     */
    public function uri(): string {
        return $this->server['REQUEST_URI'] ?? '/';
    }
    
    /**
     * Get request path (without query string)
     */
    public function path(): string {
        $uri = $this->uri();
        $path = parse_url($uri, PHP_URL_PATH);
        return $path ?? '/';
    }
    
    /**
     * Get header value
     */
    public function header(string $key, $default = null) {
        $key = strtolower($key);
        return $this->headers[$key] ?? $default;
    }
    
    public function context(): string {
        if ($this->context === null) {
            $this->context = \App\Helpers\SessionHelper::getContext($this->path());
        }
        return $this->context;
    }
    
    /**
     * Detect context from path fallback
     */
    private function detectContextFromPath(): string {
        return \App\Helpers\SessionHelper::getContext($this->path());
    }
    
    /**
     * Check if request is JSON
     */
    public function isJson(): bool {
        $contentType = $this->header('content-type', '');
        return strpos($contentType, 'application/json') !== false;
    }
    
    /**
     * Check if request is multipart/form-data
     */
    public function isMultipart(): bool {
        $contentType = $this->header('content-type', '');
        return strpos($contentType, 'multipart/form-data') !== false;
    }
    
    /**
     * Parse multipart/form-data for PUT/PATCH requests
     */
    private function parseMultipartData(): array {
        $data = [];
        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            return [];
        }
        
        // Get boundary from Content-Type header
        $contentType = $this->header('content-type', '');
        if (preg_match('/boundary=([^;]+)/i', $contentType, $matches)) {
            $boundary = '--' . trim($matches[1]);
        } else {
            return [];
        }
        
        // Split by boundary
        $parts = explode($boundary, $input);
        
        foreach ($parts as $part) {
            // Skip empty parts, initial boundary marker, and closing boundary
            $part = trim($part);
            if (empty($part) || $part === '--') {
                continue;
            }
            
            // Split headers and body
            if (strpos($part, "\r\n\r\n") === false) {
                continue;
            }
            
            list($headers, $body) = explode("\r\n\r\n", $part, 2);
            
            // Extract field name from Content-Disposition header
            if (preg_match('/name="([^"]+)"/', $headers, $nameMatches)) {
                $fieldName = $nameMatches[1];
                // Remove trailing \r\n from body
                $value = rtrim($body, "\r\n");
                
                // Convert boolean strings to actual booleans
                if ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                }
                
                $data[$fieldName] = $value;
            }
        }
        
        return $data;
    }
    
    /**
     * Validate request data
     */
    public function validate(array $rules): array {
        $data = [];
        $errors = [];
        
        foreach ($rules as $field => $ruleString) {
            $value = $this->input($field);
            $rulesArray = explode('|', $ruleString);
            
            foreach ($rulesArray as $rule) {
                if ($rule === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = "The {$field} field is required";
                } elseif ($rule === 'numeric' && $value !== null && !is_numeric($value)) {
                    $errors[$field][] = "The {$field} must be numeric";
                } elseif ($rule === 'integer' && $value !== null && !is_int($value) && !ctype_digit($value)) {
                    $errors[$field][] = "The {$field} must be an integer";
                } elseif ($rule === 'string' && $value !== null && !is_string($value)) {
                    $errors[$field][] = "The {$field} must be a string";
                } elseif (strpos($rule, 'min:') === 0) {
                    $min = (int)substr($rule, 4);
                    if ($value !== null && (is_numeric($value) && $value < $min)) {
                        $errors[$field][] = "The {$field} must be at least {$min}";
                    }
                } elseif (strpos($rule, 'max:') === 0) {
                    $max = (int)substr($rule, 4);
                    if ($value !== null && (is_numeric($value) && $value > $max)) {
                        $errors[$field][] = "The {$field} must not exceed {$max}";
                    }
                }
            }
            
            if (!isset($errors[$field])) {
                $data[$field] = $value;
            }
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException(json_encode($errors));
        }
        
        return $data;
    }
    
    /**
     * Parse HTTP headers
     */
    private function parseHeaders(): array {
        $headers = [];
        
        if (function_exists('getallheaders')) {
            $rawHeaders = getallheaders();
            if ($rawHeaders) {
                foreach ($rawHeaders as $key => $value) {
                    $headers[strtolower($key)] = $value;
                }
            }
        } else {
            // Fallback for servers without getallheaders()
            foreach ($this->server as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                    $headers[strtolower($header)] = $value;
                }
            }
        }
        
        return $headers;
    }
}



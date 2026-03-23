<?php

/**
 * HTTP Response Handler
 * Handles JSON responses, status codes, headers
 */

namespace App\Core;

class Response {
    private int $statusCode = 200;
    private array $headers = [];
    private $data = null;
    
    /**
     * Set status code
     */
    public function status(int $code): self {
        $this->statusCode = $code;
        return $this;
    }
    
    /**
     * Set header
     */
    public function header(string $key, string $value): self {
        $this->headers[$key] = $value;
        return $this;
    }
    
    /**
     * Set CORS headers
     */
    public function cors(string $origin = '*'): self {
        $this->header('Access-Control-Allow-Origin', $origin);
        $this->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $this->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-API-Key, x-api-key, X-App-Context');
        $this->header('Access-Control-Allow-Credentials', 'true');
        return $this;
    }
    
    /**
     * Send JSON response
     */
    public function json($data, int $statusCode = 200): void {
        $this->statusCode = $statusCode;
        $this->data = $data;
        
        // Clean any output that may have been accidentally sent (like PHP warnings)
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        // Set headers
        $this->header('Content-Type', 'application/json; charset=utf-8');
        
        // Send headers
        // Note: CORS headers are already set by CorsMiddleware via header() function
        // Don't override CORS headers - only send non-CORS headers from Response object
        http_response_code($this->statusCode);
        foreach ($this->headers as $key => $value) {
            // Skip CORS headers - they're already set by middleware
            $corsHeaders = [
                'access-control-allow-origin',
                'access-control-allow-methods',
                'access-control-allow-headers',
                'access-control-allow-credentials',
                'access-control-max-age'
            ];
            if (!in_array(strtolower($key), $corsHeaders)) {
                header("{$key}: {$value}");
            }
        }
        
        // Send JSON
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send headers without exiting (for middleware)
     */
    public function sendHeaders(): void {
        http_response_code($this->statusCode);
        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }
    }
    
    /**
     * Send success response
     */
    public function success($data = null, string $message = 'Success', int $statusCode = 200): void {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
        $this->json($response, $statusCode);
    }
    
    /**
     * Send error response
     */
    public function error(string $message, int $statusCode = 400, $errors = null): void {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        $this->json($response, $statusCode);
    }
    
    /**
     * Send not found response
     */
    public function notFound(string $message = 'Resource not found'): void {
        $this->error($message, 404);
    }
    
    /**
     * Send unauthorized response
     */
    public function unauthorized(string $message = 'Unauthorized'): void {
        $this->error($message, 401);
    }
    
    /**
     * Send forbidden response
     */
    public function forbidden(string $message = 'Forbidden'): void {
        $this->error($message, 403);
    }
    
    /**
     * Send validation error response
     */
    public function validationError(array $errors, string $message = 'Validation failed'): void {
        $this->error($message, 422, $errors);
    }
}


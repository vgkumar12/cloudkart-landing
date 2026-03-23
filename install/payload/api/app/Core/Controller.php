<?php

/**
 * Base Controller Class
 * Provides common controller functionality
 */

namespace App\Core;

abstract class Controller {
    protected Request $request;
    protected Response $response;
    
    public function __construct(?Request $request = null) {
        $this->request = $request ?? new Request();
        $this->response = new Response();
        
        // CORS headers are handled by CorsMiddleware
        // Don't set them here to avoid overriding middleware headers
    }
    
    /**
     * Send JSON response
     */
    protected function jsonResponse($data, int $statusCode = 200): void {
        $this->response->json($data, $statusCode);
    }
    
    /**
     * Send success response
     */
    protected function success($data = null, string $message = 'Success', int $statusCode = 200): void {
        $this->response->success($data, $message, $statusCode);
    }
    
    /**
     * Send error response
     */
    protected function error(string $message, int $statusCode = 400, $errors = null): void {
        $this->response->error($message, $statusCode, $errors);
    }
    
    /**
     * Send not found response
     */
    protected function notFound(string $message = 'Resource not found'): void {
        $this->response->notFound($message);
    }
    
    /**
     * Send unauthorized response
     */
    protected function unauthorized(string $message = 'Unauthorized'): void {
        $this->response->unauthorized($message);
    }
    
    /**
     * Send forbidden response
     */
    protected function forbidden(string $message = 'Forbidden'): void {
        $this->response->forbidden($message);
    }
    
    /**
     * Send validation error response
     */
    protected function validationError(array $errors, string $message = 'Validation failed'): void {
        $this->response->validationError($errors, $message);
    }
}


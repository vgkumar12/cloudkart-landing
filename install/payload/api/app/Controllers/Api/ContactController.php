<?php

/**
 * Contact Controller
 * Handles contact form submissions
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Validator;

class ContactController extends Controller {
    
    /**
     * Submit contact form
     * POST /api/contact
     */
    public function submit(): void {
        try {
            $data = $this->request->all();
            
            // Validate required fields
            $errors = [];
            
            if (empty($data['name'])) {
                $errors['name'] = ['Name is required'];
            }
            
            if (empty($data['email'])) {
                $errors['email'] = ['Email is required'];
            } elseif (!Validator::email($data['email'])) {
                $errors['email'] = ['Invalid email format'];
            }
            
            if (empty($data['message'])) {
                $errors['message'] = ['Message is required'];
            }
            
            if (!empty($errors)) {
                $this->validationError($errors, 'Validation failed');
                return;
            }
            
            // In a real application, you might want to:
            // 1. Save to database
            // 2. Send email notification
            // 3. Send auto-reply to customer
            
            // For now, just return success
            // You can add a Contact model later if needed
            
            $this->success([
                'message' => 'Thank you for contacting us! We will get back to you soon.'
            ], 'Contact form submitted successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to submit contact form: ' . $e->getMessage(), 500);
        }
    }
}


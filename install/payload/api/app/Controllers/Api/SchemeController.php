<?php

/**
 * Scheme Controller
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Scheme;
use App\Models\SchemeSubscription;

class SchemeController extends Controller {
    
    /**
     * Get all schemes
     * GET /api/schemes
     */
    public function index(): void {
        try {
            $schemes = Scheme::getAll();
            $this->success($schemes, 'Schemes retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve schemes: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single scheme
     * GET /api/schemes/{id}
     */
    public function show(int $id): void {
        try {
            $scheme = Scheme::findById($id);
            
            if (!$scheme) {
                $this->notFound('Scheme not found');
                return;
            }
            
            $this->success($scheme->toArray(), 'Scheme retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve scheme: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get subscriptions for a scheme
     * GET /api/schemes/{id}/subscriptions
     */
    public function subscriptions(int $id): void {
        try {
            $scheme = Scheme::findById($id);
            
            if (!$scheme) {
                $this->notFound('Scheme not found');
                return;
            }
            
            $subscriptions = $scheme->getSubscriptions();
            $this->success($subscriptions, 'Subscriptions retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve subscriptions: ' . $e->getMessage(), 500);
        }
    }
}




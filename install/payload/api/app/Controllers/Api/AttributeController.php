<?php

/**
 * Attribute Controller (Public API)
 * Public endpoints for attributes (used for filtering)
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Attribute;

class AttributeController extends Controller {
    
    /**
     * Get filterable attributes with values
     * GET /api/attributes
     */
    public function index(): void {
        try {
            $attributes = Attribute::getFilterable();
            $this->success($attributes, 'Attributes retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve attributes: ' . $e->getMessage(), 500);
        }
    }
}

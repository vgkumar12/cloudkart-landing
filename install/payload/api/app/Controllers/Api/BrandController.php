<?php

/**
 * Brand Controller (Public API)
 * Public endpoints for brands
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Brand;

class BrandController extends Controller {
    
    /**
     * Get all active brands
     * GET /api/brands
     */
    public function index(): void {
        try {
            $brands = Brand::getAll(true); // Active only
            
            $result = array_map(function($brand) {
                return $brand->toArray();
            }, $brands);
            
            $this->success($result, 'Brands retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve brands: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single brand
     * GET /api/brands/{id}
     */
    public function show(int $id): void {
        try {
            $brand = Brand::findById($id);
            
            if (!$brand || !$brand->is_active) {
                $this->notFound('Brand not found');
                return;
            }
            
            $this->success($brand->toArray(), 'Brand retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve brand: ' . $e->getMessage(), 500);
        }
    }
}

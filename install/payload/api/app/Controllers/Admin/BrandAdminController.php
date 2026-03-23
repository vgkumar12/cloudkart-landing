<?php

/**
 * Brand Admin Controller
 * Admin CRUD operations for brands
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Brand;

class BrandAdminController extends Controller {
    
    /**
     * Get all brands with product count
     * GET /api/admin/brands
     */
    public function index(): void {
        try {
            $brands = Brand::getWithProductCount();
            $this->success($brands, 'Brands retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve brands: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single brand
     * GET /api/admin/brands/{id}
     */
    public function show(int $id): void {
        try {
            $brand = Brand::findById($id);
            
            if (!$brand) {
                $this->notFound('Brand not found');
                return;
            }
            
            $this->success($brand->toArray(), 'Brand retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve brand: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create brand
     * POST /api/admin/brands
     */
    public function store(): void {
        try {
            $data = $this->request->all();
            
            // Validate required fields
            if (empty($data['name'])) {
                $this->error('Brand name is required', 400);
                return;
            }
            
            // Auto-generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['name']);
            }
            
            // Handle logo upload if provided
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 4) . '/uploads/brands/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['logo']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                    $data['logo_path'] = 'uploads/brands/' . $fileName;
                }
            }
            
            // Set defaults
            $data['is_active'] = isset($data['is_active']) ? (bool)$data['is_active'] : true;
            $data['display_order'] = isset($data['display_order']) ? (int)$data['display_order'] : 0;
            
            $brand = Brand::create($data);
            
            $this->success($brand->toArray(), 'Brand created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create brand: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update brand
     * PUT /api/admin/brands/{id}
     */
    public function update(int $id): void {
        try {
            $brand = Brand::findById($id);
            
            if (!$brand) {
                $this->notFound('Brand not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Handle logo upload if provided
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 4) . '/uploads/brands/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Delete old logo if exists
                if ($brand->logo_path && file_exists(dirname(__DIR__, 4) . '/' . $brand->logo_path)) {
                    @unlink(dirname(__DIR__, 4) . '/' . $brand->logo_path);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['logo']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                    $data['logo_path'] = 'uploads/brands/' . $fileName;
                }
            }
            
            // Process boolean fields
            if (isset($data['is_active'])) {
                $data['is_active'] = (bool)$data['is_active'];
            }
            
            $brand->update($data);
            
            $this->success($brand->toArray(), 'Brand updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update brand: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete brand
     * DELETE /api/admin/brands/{id}
     */
    public function destroy(int $id): void {
        try {
            $brand = Brand::findById($id);
            
            if (!$brand) {
                $this->notFound('Brand not found');
                return;
            }
            
            // Delete logo if exists
            if ($brand->logo_path && file_exists(dirname(__DIR__, 4) . '/' . $brand->logo_path)) {
                @unlink(dirname(__DIR__, 4) . '/' . $brand->logo_path);
            }
            
            $brand->delete();
            
            $this->success(null, 'Brand deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete brand: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Generate slug from name
     */
    private function generateSlug(string $name): string {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}

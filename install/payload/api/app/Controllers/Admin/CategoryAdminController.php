<?php

/**
 * Category Admin Controller
 * Admin CRUD operations for categories (uses existing Category model)
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Category;
use App\Helpers\ImageHelper;

class CategoryAdminController extends Controller {
    
    /**
     * Get all categories (with pagination)
     * GET /api/admin/categories
     */
    public function index(): void {
        $search = $this->request->get('q', '') ?: $this->request->get('search', '');
        $parentId = $this->request->get('parent_id') ? (int)$this->request->get('parent_id') : null;
        $page = (int)($this->request->get('page') ?? $this->request->get('p') ?? 1);
        $limit = (int)($this->request->get('limit') ?? 20);
        $sortBy = $this->request->get('sort_by') ?: 'id';
        $sortOrder = $this->request->get('sort_order') ?: 'DESC';
        
        // Validate pagination parameters
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 20;
        if ($limit > 100) $limit = 100;
        
        try {
            // Use Model method with sorting
            $result = Category::searchWithPagination($search, $parentId, $page, $limit, $sortBy, $sortOrder);
            
            // Get parent categories for dropdown
            $parentCategories = Category::getAll(null);
            
            $this->success([
                'categories' => $result['categories'],
                'parent_categories' => $parentCategories,
                'pagination' => $result['pagination']
            ], 'Categories retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve categories: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create category
     * POST /api/admin/categories
     */
    public function store(): void {
        try {
            $data = $this->request->all();
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 4) . '/uploads/categories/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $data['image_path'] = 'uploads/categories/' . $fileName;
                    
                    // Generate thumbnail
                    $thumbFileName = $fileName;
                    $thumbDir = $uploadDir . 'thumbs/';
                    $thumbPath = $thumbDir . $thumbFileName;
                    if (ImageHelper::generateThumbnail($targetPath, $thumbPath)) {
                        $data['thumb_path'] = 'uploads/categories/thumbs/' . $thumbFileName;
                    }
                }
            }
            
            $data['is_active'] = isset($data['is_active']) ? 1 : 0;
            $data['parent_id'] = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
            
            $category = Category::create($data);
            
            $this->success($category->toArray(), 'Category created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create category: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update category
     * PUT /api/admin/categories/{id}
     */
    public function update(int $id): void {
        try {
            $category = Category::findById($id);
            
            if (!$category) {
                $this->notFound('Category not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 4) . '/uploads/categories/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Delete old images
                if ($category->image_path && file_exists(dirname(__DIR__, 4) . '/' . $category->image_path)) {
                    @unlink(dirname(__DIR__, 4) . '/' . $category->image_path);
                }
                if ($category->thumb_path && file_exists(dirname(__DIR__, 4) . '/' . $category->thumb_path)) {
                    @unlink(dirname(__DIR__, 4) . '/' . $category->thumb_path);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $data['image_path'] = 'uploads/categories/' . $fileName;
                    
                    // Generate thumbnail
                    $thumbFileName = $fileName;
                    $thumbDir = $uploadDir . 'thumbs/';
                    $thumbPath = $thumbDir . $thumbFileName;
                    if (ImageHelper::generateThumbnail($targetPath, $thumbPath)) {
                        $data['thumb_path'] = 'uploads/categories/thumbs/' . $thumbFileName;
                    }
                }
            }
            
            if (isset($data['is_active'])) {
                $data['is_active'] = $data['is_active'] ? 1 : 0;
            }
            if (isset($data['parent_id'])) {
                $data['parent_id'] = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
            }
            
            $category->update($data);
            
            $this->success($category->toArray(), 'Category updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update category: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete category
     * DELETE /api/admin/categories/{id}
     */
    public function destroy(int $id): void {
        try {
            $category = Category::findById($id);
            
            if (!$category) {
                $this->notFound('Category not found');
                return;
            }
            
            // Delete image if exists
            if ($category->image_path && file_exists(dirname(__DIR__, 4) . '/' . $category->image_path)) {
                @unlink(dirname(__DIR__, 4) . '/' . $category->image_path);
            }
            if ($category->thumb_path && file_exists(dirname(__DIR__, 4) . '/' . $category->thumb_path)) {
                @unlink(dirname(__DIR__, 4) . '/' . $category->thumb_path);
            }
            
            // Soft delete
            $category->update(['is_active' => 0]);
            
            $this->success(null, 'Category deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }
}



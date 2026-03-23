<?php

/**
 * Category Controller
 */

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\Category;

class CategoryController extends Controller {
    
    /**
     * Get all categories
     * GET /api/categories
     */
    public function index(): void {
        $parentId = $this->request->get('parent_id') ? (int)$this->request->get('parent_id') : null;
        
        try {
            $categories = Category::getAll($parentId);
            $this->success($categories, 'Categories retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve categories: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single category
     * GET /api/categories/{id}
     */
    public function show(int $id): void {
        try {
            $category = Category::findById($id);
            
            if (!$category) {
                $this->notFound('Category not found');
                return;
            }
            
            $this->success($category->toArray(), 'Category retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve category: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get category by slug
     * GET /api/categories/slug/{slug}
     */
    public function showBySlug(string $slug): void {
        try {
            $category = Category::findBySlug($slug);
            
            if (!$category) {
                $this->notFound('Category not found');
                return;
            }
            
            $this->success($category->toArray(), 'Category retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve category: ' . $e->getMessage(), 500);
        }
    }
}




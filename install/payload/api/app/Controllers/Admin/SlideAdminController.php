<?php

/**
 * Slide Admin Controller
 * Admin CRUD operations for slides (uses existing Slide model)
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Slide;

class SlideAdminController extends Controller {
    
    /**
     * Get all slides
     * GET /api/admin/slides
     */
    public function index(): void {
        $search = $this->request->get('q', '') ?: $this->request->get('search', '');
        $page = (int)($this->request->get('page') ?? 1);
        $limit = (int)($this->request->get('limit') ?? 20);
        $sortBy = $this->request->get('sort_by') ?: 'id';
        $sortOrder = $this->request->get('sort_order') ?: 'DESC';
        $includeInactive = $this->request->get('include_inactive') === '1';
        
        // Validate pagination parameters
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 20;
        if ($limit > 100) $limit = 100;
        
        try {
            // Use Model method with pagination, search, and sorting
            $result = Slide::searchWithPagination($search, $page, $limit, $sortBy, $sortOrder, $includeInactive);
            
            $this->success($result, 'Slides retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve slides: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single slide
     * GET /api/admin/slides/{id}
     */
    public function show(int $id): void {
        try {
            $slide = Slide::findById($id);
            
            if (!$slide) {
                $this->notFound('Slide not found');
                return;
            }
            
            $this->success(['slide' => $slide->toArray()], 'Slide retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve slide: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create slide
     * POST /api/admin/slides
     */
    public function store(): void {
        try {
            $data = $this->request->all();
            
            // Validate required fields
            if (empty($data['title'])) {
                $this->validationError(['title' => ['Title is required']], 'Validation failed');
                return;
            }
            
            // Handle image upload (required)
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $this->validationError(['image' => ['Image is required']], 'Validation failed');
                return;
            }
            
            $uploadDir = dirname(__DIR__, 4) . '/uploads/slides/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fileName = 'slide_' . uniqid() . '.' . strtolower($ext);
            $targetPath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                $this->error('Failed to upload image', 500);
                return;
            }
            
            $data['image_path'] = 'uploads/slides/' . $fileName;
            $data['is_active'] = isset($data['is_active']) ? 1 : 0;
            
            $slide = Slide::create($data);
            
            $this->success($slide->toArray(), 'Slide created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create slide: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update slide
     * PUT /api/admin/slides/{id}
     */
    public function update(int $id): void {
        try {
            $slide = Slide::findById($id);
            
            if (!$slide) {
                $this->notFound('Slide not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Handle image upload if provided
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 4) . '/uploads/slides/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Delete old image
                if ($slide->image_path && file_exists(dirname(__DIR__, 4) . '/' . $slide->image_path)) {
                    @unlink(dirname(__DIR__, 4) . '/' . $slide->image_path);
                }
                
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $fileName = 'slide_' . uniqid() . '.' . strtolower($ext);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $data['image_path'] = 'uploads/slides/' . $fileName;
                }
            }
            
            if (isset($data['is_active'])) {
                $data['is_active'] = $data['is_active'] ? 1 : 0;
            }
            
            $slide->update($data);
            
            $this->success($slide->toArray(), 'Slide updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update slide: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete slide
     * DELETE /api/admin/slides/{id}
     */
    public function destroy(int $id): void {
        try {
            $slide = Slide::findById($id);
            
            if (!$slide) {
                $this->notFound('Slide not found');
                return;
            }
            
            // Delete image
            if ($slide->image_path && file_exists(dirname(__DIR__, 4) . '/' . $slide->image_path)) {
                @unlink(dirname(__DIR__, 4) . '/' . $slide->image_path);
            }
            
            $slide->delete();
            
            $this->success(null, 'Slide deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete slide: ' . $e->getMessage(), 500);
        }
    }
}



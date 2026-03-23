<?php

/**
 * Combo Pack Admin Controller
 * Admin CRUD operations for combo packs (uses existing ComboPack model)
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\ComboPack;
use App\Models\ComboPackItem;
use App\Helpers\ImageHelper;

class ComboPackAdminController extends Controller {
    
    /**
     * Get all combo packs
     * GET /api/admin/combo-packs
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
            $result = ComboPack::searchWithPagination($search, $page, $limit, $sortBy, $sortOrder, $includeInactive);
            
            $this->success($result, 'Combo packs retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve combo packs: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single combo pack with items
     * GET /api/admin/combo-packs/{id}
     */
    public function show(int $id): void {
        try {
            $comboPack = ComboPack::findById($id);
            
            if (!$comboPack) {
                $this->notFound('Combo pack not found');
                return;
            }
            
            $this->success($comboPack->toArray(), 'Combo pack retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve combo pack: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create combo pack
     * POST /api/admin/combo-packs
     */
    public function store(): void {
        try {
            $data = $this->request->all();
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 4) . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = uniqid('combo_') . '_' . basename($_FILES['image']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imagePath = 'uploads/' . $fileName;
                    $data['image_path'] = $imagePath;
                    
                    // Generate thumbnail
                    $thumbFileName = $fileName;
                    $thumbDir = $uploadDir . 'thumbs/';
                    $thumbPath = $thumbDir . $thumbFileName;
                    if (ImageHelper::generateThumbnail($targetPath, $thumbPath)) {
                        $data['thumb_path'] = 'uploads/thumbs/' . $thumbFileName;
                    }
                }
            }
            
            $data['is_active'] = isset($data['is_active']) ? 1 : 0;
            
            $comboPack = ComboPack::create($data);
            
            $this->success($comboPack->toArray(), 'Combo pack created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create combo pack: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update combo pack
     * PUT /api/admin/combo-packs/{id}
     */
    public function update(int $id): void {
        try {
            $comboPack = ComboPack::findById($id);
            
            if (!$comboPack) {
                $this->notFound('Combo pack not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__, 4) . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Delete old images
                if ($comboPack->image_path && file_exists(dirname(__DIR__, 4) . '/' . $comboPack->image_path)) {
                    @unlink(dirname(__DIR__, 4) . '/' . $comboPack->image_path);
                }
                if ($comboPack->thumb_path && file_exists(dirname(__DIR__, 4) . '/' . $comboPack->thumb_path)) {
                    @unlink(dirname(__DIR__, 4) . '/' . $comboPack->thumb_path);
                }
                
                $fileName = uniqid('combo_') . '_' . basename($_FILES['image']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imagePath = 'uploads/' . $fileName;
                    $data['image_path'] = $imagePath;
                    
                    // Generate thumbnail
                    $thumbFileName = $fileName;
                    $thumbDir = $uploadDir . 'thumbs/';
                    $thumbPath = $thumbDir . $thumbFileName;
                    if (ImageHelper::generateThumbnail($targetPath, $thumbPath)) {
                        $data['thumb_path'] = 'uploads/thumbs/' . $thumbFileName;
                    }
                }
            }
            
            if (isset($data['is_active'])) {
                $data['is_active'] = $data['is_active'] ? 1 : 0;
            }
            
            $comboPack->update($data);
            
            $this->success($comboPack->toArray(), 'Combo pack updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update combo pack: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete combo pack
     * DELETE /api/admin/combo-packs/{id}
     */
    public function destroy(int $id): void {
        try {
            $comboPack = ComboPack::findById($id);
            
            if (!$comboPack) {
                $this->notFound('Combo pack not found');
                return;
            }
            
            // Delete images if exists
            if ($comboPack->image_path && file_exists(dirname(__DIR__, 4) . '/' . $comboPack->image_path)) {
                @unlink(dirname(__DIR__, 4) . '/' . $comboPack->image_path);
            }
            if ($comboPack->thumb_path && file_exists(dirname(__DIR__, 4) . '/' . $comboPack->thumb_path)) {
                @unlink(dirname(__DIR__, 4) . '/' . $comboPack->thumb_path);
            }
            
            $comboPack->delete();
            
            $this->success(null, 'Combo pack deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete combo pack: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get combo pack items
     * GET /api/admin/combo-packs/{id}/items
     */
    public function getItems(int $id): void {
        try {
            $comboPack = ComboPack::findById($id);
            
            if (!$comboPack) {
                $this->notFound('Combo pack not found');
                return;
            }
            
            $items = $comboPack->getItems();
            
            $this->success($items, 'Combo pack items retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve combo pack items: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Add item to combo pack
     * POST /api/admin/combo-packs/{id}/items
     */
    public function addItem(int $id): void {
        try {
            $comboPack = ComboPack::findById($id);
            
            if (!$comboPack) {
                $this->notFound('Combo pack not found');
                return;
            }
            
            $data = $this->request->all();
            $data['combo_pack_id'] = $id;
            
            $item = ComboPackItem::create($data);
            
            $this->success($item->toArray(), 'Item added successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to add item: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update combo pack item
     * PUT /api/admin/combo-packs/{id}/items/{itemId}
     */
    public function updateItem(int $id, int $itemId): void {
        try {
            $item = ComboPackItem::findById($itemId);
            
            if (!$item || $item->combo_pack_id !== $id) {
                $this->notFound('Item not found');
                return;
            }
            
            $data = $this->request->all();
            $item->update($data);
            
            $this->success($item->toArray(), 'Item updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update item: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete combo pack item
     * DELETE /api/admin/combo-packs/{id}/items/{itemId}
     */
    public function deleteItem(int $id, int $itemId): void {
        try {
            $item = ComboPackItem::findById($itemId);
            
            if (!$item || $item->combo_pack_id !== $id) {
                $this->notFound('Item not found');
                return;
            }
            
            $item->delete();
            
            $this->success(null, 'Item deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete item: ' . $e->getMessage(), 500);
        }
    }
}



<?php

/**
 * Scheme Admin Controller
 * Admin CRUD operations for schemes (uses existing Scheme model)
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Scheme;
use App\Models\SchemeSubscription;

class SchemeAdminController extends Controller {
    
    /**
     * Get all schemes
     * GET /api/admin/schemes
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
            $result = Scheme::searchWithPagination($search, $page, $limit, $sortBy, $sortOrder, $includeInactive);
            
            $this->success($result, 'Schemes retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve schemes: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single scheme
     * GET /api/admin/schemes/{id}
     */
    public function show(int $id): void {
        try {
            $scheme = Scheme::findById($id);
            
            if (!$scheme) {
                $this->notFound('Scheme not found');
                return;
            }
            
            $this->success(['scheme' => $scheme->toArray()], 'Scheme retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve scheme: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create scheme
     * POST /api/admin/schemes
     */
    public function store(): void {
        try {
            $data = $this->request->all();
            
            // Process start_month (accept YYYY-MM or YYYY-MM-DD, coerce to first of month)
            if (!empty($data['start_month'])) {
                $raw = (string)$data['start_month'];
                if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
                    $data['start_month'] = $raw . '-01';
                } elseif (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $raw, $m)) {
                    $data['start_month'] = $m[1] . '-' . $m[2] . '-01';
                }
            }
            
            $data['frequency'] = ($data['frequency'] ?? 'monthly') === 'weekly' ? 'weekly' : 'monthly';
            $data['is_active'] = isset($data['is_active']) ? 1 : 0;
            
            $scheme = Scheme::create($data);
            
            $this->success($scheme->toArray(), 'Scheme created successfully', 201);
        } catch (\Exception $e) {
            $this->error('Failed to create scheme: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update scheme
     * PUT /api/admin/schemes/{id}
     */
    public function update(int $id): void {
        try {
            $scheme = Scheme::findById($id);
            
            if (!$scheme) {
                $this->notFound('Scheme not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Process start_month
            if (isset($data['start_month']) && !empty($data['start_month'])) {
                $raw = (string)$data['start_month'];
                if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
                    $data['start_month'] = $raw . '-01';
                } elseif (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $raw, $m)) {
                    $data['start_month'] = $m[1] . '-' . $m[2] . '-01';
                }
            }
            
            if (isset($data['frequency'])) {
                $data['frequency'] = $data['frequency'] === 'weekly' ? 'weekly' : 'monthly';
            }
            if (isset($data['is_active'])) {
                $data['is_active'] = $data['is_active'] ? 1 : 0;
            }
            
            $scheme->update($data);
            
            $this->success($scheme->toArray(), 'Scheme updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update scheme: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Toggle scheme active status
     * PUT /api/admin/schemes/{id}/toggle
     */
    public function toggle(int $id): void {
        try {
            $scheme = Scheme::findById($id);
            
            if (!$scheme) {
                $this->notFound('Scheme not found');
                return;
            }
            
            $newStatus = $scheme->is_active ? 0 : 1;
            $scheme->update(['is_active' => $newStatus]);
            
            $this->success($scheme->toArray(), 'Scheme status updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to toggle scheme: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete scheme
     * DELETE /api/admin/schemes/{id}
     */
    public function destroy(int $id): void {
        try {
            $scheme = Scheme::findById($id);
            
            if (!$scheme) {
                $this->notFound('Scheme not found');
                return;
            }
            
            $scheme->delete();
            
            $this->success(null, 'Scheme deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete scheme: ' . $e->getMessage(), 500);
        }
    }
}



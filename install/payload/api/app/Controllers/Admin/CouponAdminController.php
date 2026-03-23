<?php

/**
 * Coupon Admin Controller
 * Handles coupon management in admin panel
 */

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Coupon;

class CouponAdminController extends Controller {
    
    /**
     * Get all coupons
     * GET /api/admin/coupons
     */
    public function index(): void {
        try {
            $conn = \App\Core\Database::getConnection();
            $stmt = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC");
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $coupons = array_map(function($row) {
                return (new Coupon($row))->toArray();
            }, $results);
            
            $this->success($coupons, 'Coupons retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve coupons: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single coupon
     * GET /api/admin/coupons/{id}
     */
    public function show(int $id): void {
        try {
            $coupon = Coupon::find($id);
            
            if (!$coupon) {
                $this->notFound('Coupon not found');
                return;
            }
            
            $this->success($coupon->toArray(), 'Coupon retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve coupon: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create new coupon
     * POST /api/admin/coupons
     */
    public function store(): void {
        try {
            $data = $this->request->all();
            
            // Validate required fields
            if (empty($data['code']) || empty($data['name']) || empty($data['discount_value'])) {
                $this->validationError([
                    'code' => ['Code is required'],
                    'name' => ['Name is required'],
                    'discount_value' => ['Discount value is required']
                ], 'Validation failed');
                return;
            }
            
            // Normalize code to uppercase
            $data['code'] = strtoupper(trim($data['code']));
            
            // Check if code already exists
            $existing = Coupon::findByCode($data['code']);
            if ($existing) {
                $this->validationError(['code' => ['Coupon code already exists']], 'Validation failed');
                return;
            }
            
            // Convert applicable_ids array to JSON if provided
            if (isset($data['applicable_ids']) && is_array($data['applicable_ids'])) {
                $data['applicable_ids'] = json_encode($data['applicable_ids']);
            }
            
            // Set default values
            $data['is_active'] = $data['is_active'] ?? true;
            $data['discount_type'] = $data['discount_type'] ?? 'percentage';
            $data['applicable_to'] = $data['applicable_to'] ?? 'all';
            $data['used_count'] = 0;
            $data['minimum_order_amount'] = $data['minimum_order_amount'] ?? 0;
            
            if (empty($data['valid_from'])) {
                $data['valid_from'] = date('Y-m-d H:i:s');
            }
            
            $coupon = Coupon::create($data);
            
            $this->success($coupon->toArray(), 'Coupon created successfully');
        } catch (\Exception $e) {
            $this->error('Failed to create coupon: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update coupon
     * PUT /api/admin/coupons/{id}
     */
    public function update(int $id): void {
        try {
            $coupon = Coupon::find($id);
            
            if (!$coupon) {
                $this->notFound('Coupon not found');
                return;
            }
            
            $data = $this->request->all();
            
            // Normalize code to uppercase if provided
            if (isset($data['code'])) {
                $data['code'] = strtoupper(trim($data['code']));
                
                // Check if code already exists (excluding current coupon)
                $existing = Coupon::findByCode($data['code']);
                if ($existing && $existing->id != $id) {
                    $this->validationError(['code' => ['Coupon code already exists']], 'Validation failed');
                    return;
                }
            }
            
            // Convert applicable_ids array to JSON if provided
            if (isset($data['applicable_ids']) && is_array($data['applicable_ids'])) {
                $data['applicable_ids'] = json_encode($data['applicable_ids']);
            }
            
            $coupon->update($data);
            
            $this->success($coupon->toArray(), 'Coupon updated successfully');
        } catch (\Exception $e) {
            $this->error('Failed to update coupon: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete coupon
     * DELETE /api/admin/coupons/{id}
     */
    public function destroy(int $id): void {
        try {
            $coupon = Coupon::find($id);
            
            if (!$coupon) {
                $this->notFound('Coupon not found');
                return;
            }
            
            $coupon->delete();
            
            $this->success(null, 'Coupon deleted successfully');
        } catch (\Exception $e) {
            $this->error('Failed to delete coupon: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get coupon statistics
     * GET /api/admin/coupons/{id}/stats
     */
    public function stats(int $id): void {
        try {
            $coupon = Coupon::find($id);
            
            if (!$coupon) {
                $this->notFound('Coupon not found');
                return;
            }
            
            $conn = \App\Core\Database::getConnection();
            
            // Get total usage
            $stmt = $conn->prepare("SELECT COUNT(*) as total_uses, SUM(discount_amount) as total_discount FROM coupon_usages WHERE coupon_id = ?");
            $stmt->execute([$id]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Get usage by user
            $stmt = $conn->prepare("
                SELECT u.name, u.email, COUNT(*) as usage_count, SUM(cu.discount_amount) as total_saved
                FROM coupon_usages cu
                LEFT JOIN users u ON cu.user_id = u.id
                WHERE cu.coupon_id = ?
                GROUP BY cu.user_id
                ORDER BY usage_count DESC
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $topUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->success([
                'coupon' => $coupon->toArray(),
                'statistics' => [
                    'total_uses' => (int)($stats['total_uses'] ?? 0),
                    'total_discount_given' => (float)($stats['total_discount'] ?? 0),
                    'remaining_uses' => $coupon->usage_limit ? ($coupon->usage_limit - $coupon->used_count) : null,
                    'top_users' => $topUsers
                ]
            ], 'Statistics retrieved successfully');
        } catch (\Exception $e) {
            $this->error('Failed to retrieve statistics: ' . $e->getMessage(), 500);
        }
    }
}
